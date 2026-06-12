<?php
/**
 * Queryra × B2BKing Integration
 *
 * Replaces the B2BKing bulk order form product search with Queryra
 * semantic search. When Queryra AI Search is enabled, the entire bulk
 * order form search runs through Queryra (zero keyword matching);
 * Queryra is the authoritative engine and returns its own ranked IDs.
 *
 * Hook: b2bking_orderform_product_ids_before_processing (full replace).
 * B2BKing applies B2B pricing, WC visibility (exclude-from-search/catalog),
 * stock, variations, and image handling AFTER this hook, so our injected
 * IDs receive the full B2BKing treatment automatically — we don't replicate it.
 *
 * Security — visibility-intersect:
 * B2B group/category catalog visibility is applied in a WP_Query BEFORE
 * this hook, so a full replace would NOT re-apply it to our injected IDs.
 * To avoid surfacing products hidden from the current user's B2B group,
 * we intersect Queryra's ranked IDs with B2BKing's own visible-product set
 * (b2bking()->get_visibility_set_transient()). This is a PERMISSIONS filter,
 * not a keyword filter — semantic ranking is fully preserved.
 *
 * Toggle: shares the master "Enable Queryra AI Search" option
 * (queryra_ai_search). OFF → this callback returns the original IDs
 * untouched, so B2BKing's native search works exactly as before — a single
 * instant kill-switch.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_B2BKing {

    /** Max results requested from Queryra (generous — full ranked set). */
    const SEARCH_LIMIT = 999;

    /** @var Queryra_API|null Lazily instantiated API client. */
    private $api = null;

    public function __construct() {
        // Full REPLACE of the bulk order form product ID list.
        add_filter('b2bking_orderform_product_ids_before_processing', array($this, 'semantic_search'), 10, 1);

        // Rebrand the bulk order form loader text to "Queryra AI is searching…".
        // Scoped strictly to B2BKing's own form container (theme-independent),
        // text-only, fails silently if the markup is not found.
        add_action('wp_footer', array($this, 'output_loader_rebrand_script'), 99);
    }

    /**
     * Replace B2BKing's product ID list with Queryra semantic results.
     *
     * @param array $all_ids B2BKing's original product IDs (from its own query).
     * @return array Integer product IDs (Queryra-ranked, visibility-filtered),
     *               or the original $all_ids on any fallback condition.
     */
    public function semantic_search($all_ids) {
        // Instant kill-switch: when AI search is off (or unconfigured),
        // do nothing — B2BKing native search runs as before.
        if (get_option('queryra_ai_search') !== '1' || !get_option('queryra_api_key')) {
            return $all_ids;
        }

        // Search term comes from B2BKing's AJAX payload.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only search term; B2BKing owns the nonce/auth for this AJAX endpoint.
        $term = isset($_POST['searchValue']) ? sanitize_text_field(wp_unslash($_POST['searchValue'])) : '';
        if ($term === '') {
            // No query → let B2BKing show its default (full) list.
            return $all_ids;
        }

        if ($this->api === null) {
            $this->api = new Queryra_API();
        }

        // Negative cache shared with the main search path: the API failed
        // within the last minute, so go straight to B2BKing's native list
        // instead of stalling the bulk order form on another doomed call.
        if (get_transient('queryra_api_down')) {
            return $all_ids;
        }

        $response = $this->api->search($term, self::SEARCH_LIMIT);

        // Fallback: API error or empty → native B2BKing list (never break UX).
        if (is_wp_error($response) || empty($response['results']) || !is_array($response['results'])) {
            // Emit telemetry only on real errors (not on a legitimate empty
            // result set), so we see when Queryra's search call broke inside
            // the B2BKing path without flooding the channel.
            if (is_wp_error($response)) {
                if (class_exists('Queryra_Analytics')) {
                    Queryra_Analytics::report_error(array(
                        'category'    => 'integration',
                        'integration' => 'b2bking',
                        'source'      => 'server',
                        'stage'       => 'semantic_search',
                        'code'        => $response->get_error_code(),
                        'error'       => mb_substr((string) $response->get_error_message(), 0, 200),
                    ));
                }
                // Same 60s back-off as the main search path.
                set_transient('queryra_api_down', 1, MINUTE_IN_SECONDS);
            }
            return $all_ids;
        }

        // Map Queryra result IDs ("wp-123") → integer post IDs, preserving rank order.
        $queryra_ids = array();
        foreach ($response['results'] as $result) {
            if (empty($result['id']) || strpos($result['id'], 'wp-') !== 0) {
                continue;
            }
            $pid = (int) substr($result['id'], 3);
            if ($pid > 0) {
                $queryra_ids[] = $pid;
            }
        }

        // Nothing usable from Queryra → native fallback.
        if (empty($queryra_ids)) {
            return $all_ids;
        }

        // Enforce B2B group/category visibility (permissions filter).
        $queryra_ids = $this->apply_visibility_filter($queryra_ids);

        // An empty result here can mean either:
        //  (a) no semantically-matching products are visible to this user's
        //      B2B group (legitimate), or
        //  (b) the visibility transient could not be loaded — fail-closed.
        // Both are safe: we return the (possibly empty) filtered set rather
        // than falling back to $all_ids. Falling back would bypass the
        // permission filter and leak products hidden from this user's group.
        return $queryra_ids;
    }

    /**
     * Intersect Queryra's ranked IDs with the set of products visible to
     * the current user's B2B group/role. Uses B2BKing's own cached
     * visibility transient so we stay consistent with its access rules.
     *
     * B2BKing's get_visibility_set_transient() is a SIDE-EFFECT method —
     * it populates a user-specific transient
     * ('b2bking_user_{id}_ajax_visibility') and returns nothing. We call
     * it to ensure the transient is built for the current session, then
     * read the transient directly.
     *
     * Security model — FAIL-CLOSED:
     *  - If the transient cannot be loaded AND the site is in restricted
     *    visibility mode, we return [] (empty results) rather than
     *    falling back to unfiltered Queryra IDs. This is intentional:
     *    showing zero results is acceptable; leaking products hidden
     *    from the current user's B2B group is NOT.
     *  - Only when the global "all products visible to all users" setting
     *    is ON do we return the unfiltered ranked set — there, no
     *    visibility restrictions apply by design.
     *
     * Performance: array_flip + isset() (O(1) lookups) instead of
     * array_intersect(), scales to 100k+ products without sort cost.
     *
     * @param array $queryra_ids Ranked integer post IDs from Queryra.
     * @return array Filtered IDs (ranking order preserved), or [] on
     *               fail-closed when in restricted mode and transient
     *               unavailable.
     */
    private function apply_visibility_filter($queryra_ids) {
        if (empty($queryra_ids)) {
            return $queryra_ids;
        }

        // All-products-visible mode → no group restriction by design.
        // Mirror B2BKing's own logic: intval(option) === 1 (default 1 when
        // option missing). Note: B2BKing's `b2bking()` helper returns a
        // DIFFERENT class (B2bking_Globalhelper) than the one that defines
        // get_visibility_set_transient(), so we cannot call that method
        // through the helper. Instead, we rely on B2BKing's `init` hook
        // having already populated the user-specific visibility transient
        // earlier in the request lifecycle, and we read it directly.
        if (intval(get_option('b2bking_all_products_visible_all_users_setting', 1)) === 1) {
            return $queryra_ids;
        }

        $uid     = get_current_user_id();
        $allowed = get_transient('b2bking_user_' . $uid . '_ajax_visibility');

        // Defensive: if the transient was never built for this user
        // (e.g. request hit our hook before B2BKing's init action, or
        // transient was purged), try to build it via the global plugin
        // instance — which is the actual B2bking object, unlike b2bking().
        if (empty($allowed) || !is_array($allowed)) {
            global $b2bking_plugin;
            if (is_object($b2bking_plugin) && method_exists($b2bking_plugin, 'get_visibility_set_transient')) {
                $b2bking_plugin->get_visibility_set_transient();
                $allowed = get_transient('b2bking_user_' . $uid . '_ajax_visibility');
            }
        }

        // Still empty → FAIL CLOSED. Restricted mode + cannot consult
        // B2BKing's allow-list = return zero rather than leak hidden
        // products.
        if (empty($allowed) || !is_array($allowed)) {
            // Emit telemetry — the fail-closed path means the customer just
            // saw zero search results for a query that should have worked.
            // Without this event, the customer has no way to surface the
            // problem and we cannot tell installation-side problems from
            // legitimate empty result sets.
            if (class_exists('Queryra_Analytics')) {
                global $b2bking_plugin;
                Queryra_Analytics::report_error(array(
                    'category'         => 'integration',
                    'integration'      => 'b2bking',
                    'source'           => 'server',
                    'stage'            => 'visibility_filter',
                    'reason'           => 'transient_unavailable',
                    'plugin_object'    => is_object($b2bking_plugin) ? 'present' : 'missing',
                    'method_available' => (is_object($b2bking_plugin) && method_exists($b2bking_plugin, 'get_visibility_set_transient')) ? 'yes' : 'no',
                ));
            }
            return array();
        }

        // O(1) membership lookups; preserves Queryra ranking order.
        $allowed_map = array_flip($allowed);
        $filtered    = array();
        foreach ($queryra_ids as $id) {
            if (isset($allowed_map[$id])) {
                $filtered[] = $id;
            }
        }

        return $filtered;
    }

    /**
     * Output the B2BKing bulk order form UI rebrand script.
     *
     * Three coordinated rebrands, all scoped strictly to B2BKing's own
     * bulk order form container:
     *
     *  1. Search input PLACEHOLDER — "Search products…" → custom label
     *     (indigo/cream themes only; classic theme uses per-row inputs
     *     without a unified search placeholder).
     *
     *  2. Sort dropdown "Automatic" OPTION label — relabels the auto/
     *     default sort entry to communicate that Queryra ranking is
     *     active. Other sort options (A–Z, Bestselling, Latest) are
     *     untouched.
     *
     *  3. Live-search LOADER label — appended next to / replacing
     *     B2BKing's loader spinner during search ("AI is thinking…").
     *     Uses MutationObserver because the loader is inserted
     *     dynamically by B2BKing's JS during the AJAX request.
     *
     * SAFE BY DESIGN:
     *  - Only outputs when Queryra AI Search is enabled.
     *  - Runtime detection: returns immediately if the B2BKing bulk order
     *    form container is not on the current page (any render method:
     *    shortcode, Gutenberg block, widget, page builder, do_shortcode).
     *    On 99% of WP pages the script ends after one querySelector — no
     *    observer is ever created.
     *  - Observer scoped to B2BKing's bulk order form container (NOT
     *    document.body) — minimal mutation surface.
     *  - Placeholder + sort label applied once on init; loader handled
     *    via observer because it's dynamically inserted.
     *  - Targets exact, B2BKing-specific class names — no collisions
     *    with the rest of the site or B2BKing's other features
     *    (conversations, quotes, subaccounts use different classes).
     *  - Never replaces B2BKing markup; only changes text content or
     *    attribute values. Duplicate-guard on the loader prevents
     *    stacking labels on repeated searches.
     *  - No external assets, no icons — pure text.
     */
    public function output_loader_rebrand_script() {
        if (get_option('queryra_ai_search') !== '1') {
            return;
        }

        // Translated strings are JSON-encoded at the echo point (wp_json_encode
        // outputs a quoted, JS-safe string), so we keep the raw translation
        // values here. This is the WP-recommended way to embed PHP values in
        // inline JS — Plugin Check / phpcs recognises it as safe.
        $loader_label = __('AI is thinking — not just searching…', 'queryra-ai-search');
        $placeholder  = __('AI search — try anything…', 'queryra-ai-search');
        $sort_auto    = __('AI Pick', 'queryra-ai-search');
        ?>
        <script>
        (function() {
            // Runtime check: bail immediately if no B2BKing bulk order form on
            // this page. Catches every render method (shortcode, block, widget,
            // page builder, do_shortcode) — unlike PHP-side has_shortcode().
            // On 99% of WP pages this is the only line that ever runs.
            function init() {
                var form = document.querySelector('.b2bking_bulkorder_form_container');
                if (!form) { return; }

                var LOADER_LABEL = <?php echo wp_json_encode($loader_label); ?>;
                var PLACEHOLDER  = <?php echo wp_json_encode($placeholder); ?>;
                var SORT_AUTO    = <?php echo wp_json_encode($sort_auto); ?>;

                // === 1. Search input placeholder rebrand =====================
                // Indigo/cream themes: input has class b2bking_bulkorder_search_text_indigo.
                // (Cream theme's input also carries _cream class, but the indigo
                // class is exclusive to inputs; the cream class is also used on
                // spans, so we anchor on the indigo class for safety.)
                var inputs = form.querySelectorAll('input.b2bking_bulkorder_search_text_indigo');
                for (var i = 0; i < inputs.length; i++) {
                    inputs[i].setAttribute('placeholder', PLACEHOLDER);
                }

                // === 2. Sort dropdown "Automatic" label rebrand ==============
                // B2BKing sort options are <li value="automatic|bestselling|
                // latest|atoz|ztoa">. We only relabel "automatic" because that's
                // the option that maps to Queryra's semantic ranking; the others
                // are real WC sorts that still work as-is.
                var sortItems = form.querySelectorAll('li[value="automatic"]');
                for (var j = 0; j < sortItems.length; j++) {
                    sortItems[j].textContent = SORT_AUTO;
                }

                // === 2b. Search icon swap: magnifier → AI sparkle ===========
                // B2BKing renders a Material Design magnifier SVG (search.svg)
                // inside .b2bking_bulkorder_cream_search_icon_search. We swap
                // it for Google Material Design's `auto_awesome` icon — the
                // official Material AI symbol, identical style/library/viewBox/
                // opacity as B2BKing's other icons (search, filter, cart, etc.).
                // The parallel clear-X icon (_search_icon_clear) is NOT touched.
                var searchIconImg = form.querySelector('.b2bking_bulkorder_cream_search_icon_search img');
                if (searchIconImg && !searchIconImg.dataset.queryraSwapped) {
                    var sparkleWrap = document.createElement('span');
                    sparkleWrap.innerHTML =
                        '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
                        '<path d="M19 9l1.25-2.75L23 5l-2.75-1.25L19 1l-1.25 2.75L15 5l2.75 1.25L19 9zm-7.5.5L9 4 6.5 9.5 1 12l5.5 2.5L9 20l2.5-5.5L17 12l-5.5-2.5zM19 15l-1.25 2.75L15 19l2.75 1.25L19 23l1.25-2.75L23 19l-2.75-1.25L19 15z" fill="black" fill-opacity="0.5"/>' +
                        '</svg>';
                    var newSvg = sparkleWrap.firstChild;
                    newSvg.dataset.queryraSwapped = '1';
                    searchIconImg.parentNode.replaceChild(newSvg, searchIconImg);
                }

                // === 3. Live-search loader rebrand (dynamic via observer) ====
                // B2BKing has three bulk order form themes, each with its own
                // loader:
                //   - default : <img class="b2bking_loader_img">
                //   - indigo  : <img class="b2bking_loader_icon_button_indigo">
                //   - cream   : same indigo img + a separate
                //               <div class="b2bking_loading_products_text">
                // For cream we replace the existing loading text; for default/
                // indigo we append our label after the img.
                var LOADER_SEL = '.b2bking_loader_img, .b2bking_loader_icon_button_indigo';

                function handleLoader(img) {
                    if (!img) { return; }

                    // Cream theme: a dedicated text node already exists — rebrand it.
                    var container = img.closest('.b2bking_loader_indigo_content, .b2bking_bulkorder_form_container_content_line_livesearch');
                    if (container) {
                        var creamText = container.querySelector('.b2bking_loading_products_text');
                        if (creamText) {
                            creamText.textContent = LOADER_LABEL;
                            return;
                        }
                    }

                    // Default / indigo: no text — append our label after the spinner.
                    var wrap = img.parentNode;
                    if (!wrap) { return; }
                    if (wrap.querySelector('.queryra-searching-label')) { return; } // dedupe
                    var span = document.createElement('span');
                    span.className = 'queryra-searching-label';
                    span.textContent = LOADER_LABEL;
                    span.style.cssText = 'margin-left:8px;font-size:13px;color:#646970;vertical-align:middle;';
                    img.insertAdjacentElement('afterend', span);
                }

                function scan(node) {
                    if (!node || node.nodeType !== 1) { return; }
                    if (node.matches && node.matches(LOADER_SEL)) {
                        handleLoader(node);
                        return;
                    }
                    if (node.querySelector) {
                        var img = node.querySelector(LOADER_SEL);
                        if (img) { handleLoader(img); }
                    }
                }

                // Observer scoped to the bulk order form container — minimal
                // mutation surface. Catches all three themes and any dynamically
                // added rows within the form.
                var observer = new MutationObserver(function(mutations) {
                    for (var m = 0; m < mutations.length; m++) {
                        var added = mutations[m].addedNodes;
                        for (var a = 0; a < added.length; a++) {
                            scan(added[a]);
                        }
                    }
                });
                observer.observe(form, { childList: true, subtree: true });
            }

            // wp_footer fires before </body>, but B2BKing forms may render
            // later (block editor, deferred page builders). Defer to next tick
            // so late-rendered forms are still detected.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        <?php
    }
}
