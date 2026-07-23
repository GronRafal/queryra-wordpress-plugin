<?php
/**
 * Queryra Search Integration
 *
 * Overrides WordPress default search with Queryra AI semantic search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Search_Integration {

    /**
     * API client
     */
    private $api;

    /**
     * Cache duration in seconds
     */
    private $cache_duration;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new Queryra_API();

        // Get cache duration from settings (default: 1 day)
        $this->cache_duration = intval(get_option('queryra_cache_duration', 86400));

        // Override WordPress search with Queryra
        add_action('pre_get_posts', array($this, 'override_search'));

        // When AI search has produced post__in IDs, nullify the SQL search
        // WHERE clause so WordPress does not narrow results by literal LIKE
        // matching. The 's' query var stays intact so themes can display
        // "Search results for: foo" and the search input keeps the value.
        add_filter('posts_search', array($this, 'remove_search_sql'), 10, 2);

        // SEO/AEO: structured data on search results pages
        add_action('wp_head', array($this, 'output_search_schema'), 20);

        // SEO/AEO: site-wide Service schema (every frontend page) so LLMs
        // know the site offers AI semantic search, not just on /?s= pages.
        add_action('wp_head', array($this, 'output_site_schema'), 21);

        // Fingerprint header on search responses (detection tools, AEO)
        add_action('send_headers', array($this, 'output_search_header'));
    }

    /**
     * Output JSON-LD Service schema describing the AI search capability of
     * this site. Emitted on every frontend page (except search results — those
     * already get a stronger SearchResultsPage schema).
     *
     * Purpose: when LLMs (ChatGPT, Perplexity, Google AI Overviews) crawl
     * any page of the site, they learn that natural-language search is
     * supported and how to use it.
     */
    public function output_site_schema() {
        if (is_admin() || wp_doing_ajax() || is_search() || is_404()) {
            return;
        }
        if (get_option('queryra_ai_search') !== '1' || !get_option('queryra_api_key')) {
            return;
        }
        if (get_option('queryra_output_schema', '1') !== '1') {
            return;
        }

        $home = home_url('/');

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => 'AI Semantic Search',
            'serviceType' => 'Site Search',
            'description' => 'AI-powered semantic search. Users can search using natural language — describe needs in full sentences, ask questions, use intent. The AI understands meaning, price filters, and brand exclusions.',
            'provider'    => array(
                '@type'  => 'Organization',
                'name'   => 'Queryra',
                'url'    => 'https://queryra.com',
                'sameAs' => array(
                    'https://wordpress.org/plugins/queryra-ai-search/',
                ),
            ),
            'areaServed'  => array(
                '@type' => 'WebSite',
                'url'   => $home,
                'name'  => get_bloginfo('name'),
            ),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => array(
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $home . '?s={search_term_string}',
                ),
                'query-input' => 'required name=search_term_string',
            ),
        );

        echo "\n<script type=\"application/ld+json\">"
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "</script>\n";
    }

    /**
     * Output JSON-LD SearchResultsPage schema with Queryra as the search
     * provider. Helps LLMs and crawlers attribute the search engine.
     */
    public function output_search_schema() {
        if (!is_search()) {
            return;
        }
        if (get_option('queryra_ai_search') !== '1' || !get_option('queryra_api_key')) {
            return;
        }
        if (get_option('queryra_output_schema', '1') !== '1') {
            return;
        }

        $search_term = get_search_query();
        if (empty($search_term)) {
            return;
        }

        $schema = array(
            '@context'  => 'https://schema.org',
            '@type'     => 'SearchResultsPage',
            'name'      => sprintf(
                /* translators: %s: search term */
                __('Search results for "%s"', 'queryra-ai-search'),
                $search_term
            ),
            'url'       => home_url(add_query_arg(null, null)),
            'isPartOf'  => array(
                '@type' => 'WebSite',
                'url'   => home_url('/'),
                'name'  => get_bloginfo('name'),
            ),
            'provider'  => array(
                '@type'  => 'Organization',
                'name'   => 'Queryra',
                'url'    => 'https://queryra.com',
                'sameAs' => array(
                    'https://wordpress.org/plugins/queryra-ai-search/',
                ),
            ),
        );

        echo "\n<script type=\"application/ld+json\">"
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "</script>\n";
    }

    /**
     * Send X-Search-Engine header on search results pages.
     */
    public function output_search_header() {
        if (!is_search()) {
            return;
        }
        if (get_option('queryra_ai_search') !== '1' || !get_option('queryra_api_key')) {
            return;
        }
        if (get_option('queryra_output_schema', '1') !== '1') {
            return;
        }
        if (headers_sent()) {
            return;
        }
        header('X-Search-Engine: Queryra-AI/' . QUERYRA_VERSION);
    }

    /**
     * Override WordPress search with Queryra AI
     *
     * Simple Strategy:
     * 1. Pre-flight checks (avoid unnecessary API calls)
     * 2. Fetch all matching IDs from Queryra API (limit=999)
     * 3. Set post__in with IDs, let WordPress/WooCommerce handle pagination
     * 4. Fallback: If any check fails or API down → WordPress MySQL search
     *
     * @param WP_Query $query WordPress query object
     */
    public function override_search($query) {
        // Only override frontend main search query
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return;
        }

        // Skip during cron - prevents cache warmers from burning API credits
        if (wp_doing_cron()) {
            return;
        }

        $search_term = $query->get('s');

        // Check if AI search is enabled in settings
        $ai_enabled = get_option('queryra_ai_search', '0');
        if ($ai_enabled !== '1') {
            return; // AI search disabled, use WordPress default
        }

        // Empty search term - let WordPress handle
        if (empty($search_term)) {
            return;
        }

        // Scope filters from the request (filter_<dimension>=<value>). Forwarded
        // to the API, which filters BEFORE retrieval so the ranked set is already
        // scoped — unlike the queryra_result_ids hook, which trims AFTER retrieval
        // and can leave a handful of results out of the top matches.
        $filters = $this->get_request_filters();

        // Get cached stats and status from WordPress DB (saved by admin page)
        $stats = get_option('queryra_cached_stats');
        $status = get_option('queryra_cached_status');

        // Pre-flight check: No synced records?
        if ($stats && isset($stats['synced_records']) && $stats['synced_records'] == 0) {
            return; // No records in Queryra at all
        }

        // Cache key (unique per search term + scope filters). Salted with a
        // version number so "Clear All Search Cache" works on Redis/Memcached
        // too — external object caches don't keep transients in wp_options, so
        // the admin's SQL cleanup can't reach them; bumping the version orphans
        // every old entry instead.
        $cache_version = (int) get_option('queryra_cache_version', 1);
        $cache_seed    = $cache_version . '|' . $search_term;
        if (!empty($filters)) {
            // Only appended when filters are present, so existing cached
            // (unfiltered) searches keep their keys and are not invalidated.
            $cache_seed .= '|' . wp_json_encode($filters);
        }
        $cache_key = 'queryra_search_' . md5($cache_seed);

        // Check cache FIRST - even if stale, it's better than SQL
        $cached_ids = get_transient($cache_key);

        // Cached "no results" marker (empty array): this term was asked
        // recently and the API had nothing — fall back to WordPress search
        // without burning another API call. Misspelled queries repeat a
        // lot; without this marker each repeat costs a full API round-trip.
        if (is_array($cached_ids) && count($cached_ids) === 0) {
            return;
        }

        // SMART STRATEGY for FREE plan:
        // If window closed BUT we have cache → use cache (even if stale)
        // Better to show AI results from cache than fallback to SQL
        if ($stats && isset($stats['plan']) && $stats['plan'] === 'free' && $status) {
            $window_closed = false;

            // Check if window is closed
            if (isset($status['available']) && $status['available'] === false) {
                $window_closed = true;
            } elseif (isset($status['minutes_until_open']) && $status['minutes_until_open'] > 2) {
                $window_closed = true;
            }

            // If window closed BUT we have cache → use it!
            if ($window_closed && $cached_ids && count($cached_ids) > 0) {
                // Don't fetch from API, just use cache below
            } elseif ($window_closed && !$cached_ids) {
                // No cache available, must fallback to SQL
                return;
            }
        }

        // If no cache, fetch from API
        if (!$cached_ids) {
            // Check if we should skip API call (FREE plan window closed)
            $skip_api = false;

            if ($stats && isset($stats['plan']) && $stats['plan'] === 'free' && $status) {
                if ((isset($status['available']) && $status['available'] === false) ||
                    (isset($status['minutes_until_open']) && $status['minutes_until_open'] > 2)) {
                    $skip_api = true;
                }
            }

            if ($skip_api) {
                return; // Fallback to WordPress search
            }

            // Negative cache: the API failed within the last minute. Skip
            // straight to the WordPress fallback instead of paying another
            // doomed HTTP round-trip per search — during an outage every
            // uncached search would otherwise stall for the full timeout.
            if (get_transient('queryra_api_down')) {
                return;
            }

            // Fetch all matching results from API (API filters by relevance threshold)
            $results = $this->api->search($search_term, 999, $filters);

            // If API fails or returns error, fallback to WordPress search
            if (is_wp_error($results)) {
                $error_msg = $results->get_error_message();
                $error_data = $results->get_error_data();

                // Special handling for FREE plan window closed
                $is_window_closed = (strpos($error_msg, 'FREE plan') !== false || strpos($error_msg, 'window') !== false);
                if ($is_window_closed) {
                    if ($error_data && isset($error_data['data']['errors']['api_error'][0])) {
                        $error_info = $error_data['data']['errors']['api_error'][0];

                        $updated_status = array(
                            'available' => false,
                            'plan' => 'free',
                            'minutes_left' => 0,
                            'message' => isset($error_info['message']) ? $error_info['message'] : 'Search window closed',
                            'next_available_at' => isset($error_info['next_available_at']) ? $error_info['next_available_at'] : null,
                            'minutes_until_open' => isset($error_info['minutes_until_open']) ? $error_info['minutes_until_open'] : null
                        );

                        update_option('queryra_cached_status', $updated_status, false);
                    }
                }

                // Emit telemetry — frontend semantic search just silently fell
                // back to WP keyword search. Without this event we have zero
                // signal that a customer's search is broken until they email.
                // Skip the FREE-plan window-closed case: it's a business state,
                // not a malfunction, and would otherwise flood the channel.
                if (!$is_window_closed && class_exists('Queryra_Analytics')) {
                    Queryra_Analytics::report_error(array(
                        'category' => 'search',
                        'source'   => 'server',
                        'code'     => $results->get_error_code(),
                        'error'    => mb_substr((string) $error_msg, 0, 200),
                    ));
                }

                // Mark the API as down for 60s so concurrent/subsequent
                // searches fall back immediately instead of each repeating
                // the failed call. Also naturally rate-limits the error
                // telemetry above to ~1 event per minute during an outage.
                if (!$is_window_closed) {
                    set_transient('queryra_api_down', 1, MINUTE_IN_SECONDS);
                }

                return; // Let WordPress handle search normally
            }

            if (empty($results['results'])) {
                // Cache the empty outcome briefly (short TTL — new content
                // may match soon) unless caching is disabled entirely.
                if ($this->cache_duration !== 0) {
                    set_transient($cache_key, array(), 5 * MINUTE_IN_SECONDS);
                }
                return; // Let WordPress handle search normally
            }

            // Extract post IDs from results
            // Convert "wp-123" → 123, filter out non-WordPress records
            $all_ids = array();
            foreach ($results['results'] as $result) {
                if (!empty($result['id']) && strpos($result['id'], 'wp-') === 0) {
                    $id = (int) str_replace('wp-', '', $result['id']);
                    if ($id > 0) {
                        $all_ids[] = $id;
                    }
                }
            }

            // Track first search (only once per installation)
            if (!get_option('queryra_first_search_tracked')) {
                Queryra_Analytics::track('first_search');
                update_option('queryra_first_search_tracked', true, false);
            }

            // Results contained no WordPress records ("wp-" prefix) — treat
            // like an empty result: brief negative cache + native fallback,
            // consistent with the empty-results branch above.
            if (empty($all_ids)) {
                if ($this->cache_duration !== 0) {
                    set_transient($cache_key, array(), 5 * MINUTE_IN_SECONDS);
                }
                return;
            }

            // Cache the results based on settings
            if ($this->cache_duration === -1) {
                // Forever = 10 years
                set_transient($cache_key, $all_ids, YEAR_IN_SECONDS * 10);
            } elseif ($this->cache_duration > 0) {
                // Normal cache duration
                set_transient($cache_key, $all_ids, $this->cache_duration);
            }
            // cache_duration === 0 means disabled, don't cache

            $cached_ids = $all_ids;
        }

        // If no results from API, show empty
        if (empty($cached_ids)) {
            $query->set('post__in', array(0));
            return;
        }

        /**
         * Filter the ranked post IDs before they reach WordPress.
         *
         * Lets the site narrow the result set by its OWN rules — membership
         * access, B2B groups, facets — before the query runs. The site is the
         * only party that knows its access model, so that logic belongs here
         * rather than in a per-plugin integration on our side.
         *
         * Note this filters AFTER retrieval: on a large catalogue the engine
         * may return 20 and the callback leave 3. Scoping before retrieval is
         * a separate matter.
         *
         * Filter name suggested by @jonnwalker on the WordPress.org forum.
         *
         * @param array  $cached_ids  Ranked post IDs, best match first.
         * @param string $search_term The raw search query.
         */
        $cached_ids = apply_filters('queryra_result_ids', $cached_ids, $search_term);

        // A callback that removed everything must yield NO results. An empty
        // array in post__in means "no restriction" to WP_Query — it would show
        // the entire site, the exact opposite of what the callback intended.
        if (empty($cached_ids) || !is_array($cached_ids)) {
            $query->set('post__in', array(0));
            return;
        }

        // === SIMPLE APPROACH ===
        // Just set the IDs and let WordPress/WooCommerce handle everything else
        // (pagination, posts_per_page, sorting display)

        // Flag that we used AI search (for attribution footer)
        $query->set('queryra_ai_used', true);

        // Tell WordPress: only show these posts, in this exact order
        $query->set('post__in', $cached_ids);
        $query->set('orderby', 'post__in');

        // We deliberately do NOT clear 's' — it stays in query_vars so
        // themes can display "Search results for: foo" via get_search_query()
        // and the search input retains the user's query. The SQL LIKE clause
        // that 's' would normally produce is nullified by remove_search_sql()
        // (posts_search filter) below, since AI has already done the matching.

        // Backward-compat: keep this for any custom theme that already reads it.
        $query->set('queryra_search_term', $search_term);
    }

    /**
     * Collect scope filters from the current search request.
     *
     * A site narrows results by adding filter_<dimension>=<value> parameters
     * to its search form, e.g. /?s=shoes&filter_product_cat=boots. Dimension
     * is a taxonomy slug (or "type"); value is a term/slug. We only sanitise
     * the SHAPE here and forward the flat pairs — the API validates each
     * dimension against its own metadata allow-list and builds the query, so
     * nothing user-supplied is ever turned into a query clause on our side.
     *
     * @return array Map of dimension => value, key-sorted (stable cache keys).
     */
    private function get_request_filters() {
        $filters = array();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only front-end search; same trust level as the core 's' query var
        foreach ($_GET as $raw_key => $raw_value) {
            if (!is_string($raw_key) || strpos($raw_key, 'filter_') !== 0) {
                continue;
            }
            if (!is_scalar($raw_value)) {
                continue; // filter_x[]=… array forms are not supported yet
            }
            $dimension = sanitize_key(substr($raw_key, 7)); // drop "filter_"
            $value     = sanitize_text_field(wp_unslash((string) $raw_value));
            if ($dimension === '' || $value === '') {
                continue;
            }
            $filters[$dimension] = $value;
        }

        ksort($filters);
        return $filters;
    }

    /**
     * Strip the WordPress native search WHERE clause when this query was
     * resolved by Queryra AI. Without this, WP would add
     *     AND (post_title LIKE '%foo%' OR post_content LIKE '%foo%' …)
     * which would filter out semantic matches that don't contain the literal
     * keyword (the whole point of AI search).
     */
    public function remove_search_sql($search, $query) {
        if ($query->get('queryra_ai_used')) {
            return '';
        }
        return $search;
    }
}
