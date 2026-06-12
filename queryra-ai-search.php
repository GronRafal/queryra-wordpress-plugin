<?php
/**
 * Plugin Name: AI Search for WooCommerce – Semantic Search
 * Plugin URI: https://github.com/GronRafal/queryra-wordpress-plugin
 * Description: AI-powered semantic search for your WordPress content. Automatically sends posts, pages, and custom post types to Queryra.
 * Version: 1.4.3
 * Author: Queryra
 * Author URI: https://queryra.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: queryra-ai-search
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('QUERYRA_VERSION', '1.4.3');
define('QUERYRA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QUERYRA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QUERYRA_PLUGIN_FILE', __FILE__);
define('QUERYRA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-api.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-sync.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-admin.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-search.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-setup-wizard.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-deactivation-survey.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-analytics.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-llms.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-postmeta.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/integrations/class-queryra-integration-loader.php';

// WordPress 7.0+ optional integrations.
// Feature detection (not version check) — works even if WP backports
// these APIs to older releases or removes them in future versions.
if (function_exists('wp_register_ability')) {
    require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-abilities.php';
}
if (function_exists('wp_get_connector') || function_exists('wp_is_connector_registered')) {
    require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-connector.php';
}

/**
 * Main Plugin Class
 */
class Queryra_Search {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Defensive guard: prevent destructive empty overwrites of a
        // working API key. The WordPress 7.0 AI plugin's Connectors UI
        // overwrites with an empty string when its credential validation
        // round-trip fails — silently wiping the user's working setup.
        // We block the empty write while keeping the previous value.
        // To intentionally clear the key, use the Settings tab (which
        // re-submits the field with its current value, not empty) or
        // WP-CLI: `wp option delete queryra_api_key`.
        add_filter('pre_update_option_queryra_api_key', array($this, 'guard_api_key_overwrite'), 10, 2);

        // Public validation hook. External code (third-party plugins,
        // AI agents, integration tests) can ask "is this API key valid?"
        // without touching plugin internals:
        //
        //     $result = apply_filters('queryra_validate_api_key', false, $candidate);
        //     // true on success, WP_Error on failure
        //
        // Reuses the existing Queryra_API::test_connection() flow so
        // validation behavior is identical everywhere.
        add_filter('queryra_validate_api_key', array($this, 'validate_api_key_filter'), 10, 2);

        // Self-validate the API key whenever the option changes (no matter
        // who wrote it: our Settings tab, WP-CLI, or a third-party UI like
        // the WP 7.0 Connectors screen — which shows a misleading error
        // because its validation flow does not know how to talk to Queryra).
        // We run our own check via Queryra_API::validate_key() and surface
        // a clear admin notice so the user always knows the real state.
        add_action('update_option_queryra_api_key', array($this, 'verify_api_key_after_save'), 10, 2);
        add_action('add_option_queryra_api_key',    array($this, 'verify_api_key_after_add'), 10, 2);
        add_action('admin_notices',                 array($this, 'maybe_render_api_key_notice'));

        // LAZY validation safety net. The WP 7.0 Connectors UI writes the
        // API key directly to wp_options via $wpdb (bypassing update_option),
        // so our pre_update_option / update_option_* hooks never fire for
        // that save path. We catch it on the next admin page load by
        // comparing a hash of the current key against the last-validated
        // hash; if they differ, we run validate_key() and refresh the
        // transient. Net effect: works for ANY write path (Connectors UI,
        // WP-CLI, direct DB, future third parties).
        add_action('admin_init', array($this, 'maybe_lazy_validate_key'));

        // REST endpoint + floating notice on the WP 7.0 Connectors screen.
        // The Connectors React app covers the standard admin_notices area
        // and unconditionally shows a misleading "connected successfully"
        // toast even when our background validation says the key is invalid.
        // We overlay our own authoritative green/red notice on that screen
        // so the user always sees the truth.
        add_action('rest_api_init',         array($this, 'register_key_status_endpoint'));
        add_action('admin_notices',         array($this, 'render_connectors_floating_notice'));
    }

    /**
     * Debug logger — fires only when WP_DEBUG is on.
     * Single helper so we can grep one prefix to follow the whole flow.
     */
    public static function log($msg) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostic output gated by WP_DEBUG; silent in production.
            error_log('[Queryra v1.4] ' . $msg);
        }
    }

    /**
     * Append plugin-tracked UTM parameters to queryra.com URLs.
     *
     * Used for any clickable link to queryra.com surfaced from the plugin
     * admin UI — settings buttons, setup wizard, Connectors metadata,
     * plugin row links, etc. Lets us identify which installs send traffic
     * to queryra.com via nginx-log UTM parsing.
     *
     * Behaviour:
     *  - Non-queryra.com URLs are returned unchanged (defensive guard so
     *    partner / external URLs are never modified accidentally).
     *  - URLs containing '/api/' are returned unchanged — those are
     *    backend HTTP API calls, not user-facing links.
     *  - utm_content carries the per-install UUID4 from
     *    queryra_instance_id (lazily generated by Queryra_Analytics on
     *    first event). When that option is missing, we emit the sentinel
     *    'pre-init' so the gap is visible in logs rather than silently
     *    dropping utm_content.
     *  - Sentinel discriminates legitimate "fresh install before first
     *    event" from genuine bugs (option lost, race condition, etc.).
     *
     * Output should still be passed through esc_url() at the echo point.
     *
     * @param string $url Absolute URL (typically https://queryra.com/...).
     * @return string Same URL with UTM appended, or unchanged if not eligible.
     */
    public static function tracked_url($url) {
        if (!is_string($url) || $url === '') {
            return $url;
        }
        if (strpos($url, 'queryra.com') === false) {
            return $url;
        }
        if (strpos($url, '/api/') !== false) {
            return $url;
        }

        $iid = get_option('queryra_instance_id', '');
        if (empty($iid)) {
            $iid = 'pre-init';
        }

        $sep = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $sep . 'utm_source=b5361c6f&utm_content=' . rawurlencode($iid);
    }

    /**
     * Reject empty overwrites of a previously-set API key.
     */
    public function guard_api_key_overwrite($new_value, $old_value) {
        self::log('pre_update_option_queryra_api_key — old_len=' . strlen((string)$old_value) . ' new_len=' . strlen((string)$new_value));
        if (empty(trim((string) $new_value)) && !empty($old_value)) {
            self::log('pre_update_option — BLOCKED empty overwrite (preserving old key)');
            return $old_value;
        }
        return $new_value;
    }

    /**
     * Default handler for the `queryra_validate_api_key` filter.
     * Returns true on success, WP_Error on failure.
     *
     * @param mixed  $result Previous filter value (unused — we always re-check).
     * @param string $key    Candidate API key.
     * @return true|WP_Error
     */
    public function validate_api_key_filter($result, $key) {
        $api = new Queryra_API();
        return $api->validate_key($key);
    }

    /**
     * Hook for update_option_queryra_api_key — only fires when value changed.
     */
    public function verify_api_key_after_save($old_value, $new_value) {
        self::log('HOOK update_option_queryra_api_key — old_len=' . strlen((string)$old_value) . ' new_len=' . strlen((string)$new_value));
        $this->verify_api_key($new_value);
    }

    /**
     * Hook for add_option_queryra_api_key — fires when the option is first set.
     */
    public function verify_api_key_after_add($option_name, $value) {
        self::log('HOOK add_option_queryra_api_key — value_len=' . strlen((string)$value));
        $this->verify_api_key($value);
    }

    /**
     * Run our own validation on a freshly-saved key and store the result
     * in a short transient. The admin_notices hook picks it up and shows
     * a clear pass/fail message — giving the user authoritative feedback
     * regardless of what third-party UIs displayed at the moment of save.
     */
    private function verify_api_key($key) {
        self::log('verify_api_key() ENTER — key_len=' . strlen((string)$key));

        if (empty($key)) {
            self::log('verify_api_key — empty key, clearing validation state');
            delete_option('queryra_api_key_validation');
            delete_option('queryra_api_key_validated_hash');
            delete_option('queryra_api_key_notified_hash');
            // legacy transient cleanup (pre-1.4 path)
            delete_transient('queryra_api_key_status');
            return;
        }

        self::log('verify_api_key — instantiating Queryra_API');
        $api    = new Queryra_API();
        self::log('verify_api_key — calling validate_key()');
        $result = $api->validate_key($key);
        self::log('verify_api_key — validate_key() returned: ' . (is_wp_error($result) ? 'WP_Error("' . $result->get_error_message() . '")' : ($result === true ? 'TRUE' : 'unexpected')));

        $hash = md5((string)$key);

        if ($result === true) {
            $validation = array(
                'level'        => 'success',
                'message'      => __('API key verified successfully — Queryra is connected.', 'queryra-ai-search'),
                'validated_at' => time(),
                'key_hash'     => $hash,
            );
            self::log('verify_api_key — SUCCESS, storing validation option');
        } else {
            $message = is_wp_error($result) ? $result->get_error_message() : __('Validation failed', 'queryra-ai-search');
            $validation = array(
                'level'        => 'error',
                'message'      => $message,
                'validated_at' => time(),
                'key_hash'     => $hash,
            );
            self::log('verify_api_key — ERROR, storing validation option message="' . $message . '"');

            // Emit telemetry — key validation failed. Tells us how many
            // customers paste wrong keys, how many keys expire, and the
            // distribution of error reasons. Helps tune the onboarding /
            // dashboard "Get API key" flow.
            if (class_exists('Queryra_Analytics')) {
                Queryra_Analytics::report_error(array(
                    'category' => 'key_validation',
                    'source'   => 'server',
                    'code'     => is_wp_error($result) ? $result->get_error_code() : 'unknown',
                    'error'    => mb_substr((string) $message, 0, 200),
                ));
            }
        }

        // PERSISTENT storage (not transient) so the status survives the
        // single-shot render in admin_notices. Lazy-validation hash uses
        // a separate option so we can detect key changes regardless of
        // whether the previous notice was already shown.
        update_option('queryra_api_key_validation', $validation, false);
        update_option('queryra_api_key_validated_hash', $hash, false);
        self::log('verify_api_key — stored validation + validated_hash');
        self::log('verify_api_key() EXIT');
    }

    /**
     * Lazy validation safety net. Compares md5(current key) against the
     * hash of the last key we validated. If they differ, the key was
     * changed via a write path that bypassed our update_option hooks
     * (most notably the WP 7.0 Connectors UI which uses direct $wpdb
     * writes). We catch up by running validate_key() now so the user
     * sees a correct status notice on this page load.
     *
     * Runs at most ONCE per request (static guard) and only on admin
     * (not on REST/AJAX/cron) to avoid HTTP storms.
     */
    public function maybe_lazy_validate_key() {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        // Only on real admin page loads — skip REST/AJAX/cron which can
        // each fire admin_init in some paths.
        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }

        $key = get_option('queryra_api_key');
        if (empty($key)) {
            return;
        }

        $current_hash = md5((string)$key);
        $last_hash    = (string) get_option('queryra_api_key_validated_hash', '');

        if ($current_hash === $last_hash) {
            // Key unchanged since last validation. If that validation
            // FAILED, retry every 15 minutes — otherwise a transient
            // network blip during validation would brand a perfectly good
            // key "invalid" forever (nothing re-checks until the key
            // itself changes).
            $validation = get_option('queryra_api_key_validation');
            if (is_array($validation)
                && isset($validation['level']) && $validation['level'] === 'error'
                && (time() - (isset($validation['validated_at']) ? (int) $validation['validated_at'] : 0)) > 15 * MINUTE_IN_SECONDS
            ) {
                self::log('LAZY validate — previous validation errored >15 min ago, retrying');
                $this->verify_api_key($key);
            }
            return;
        }

        self::log('LAZY validate — hash changed (last=' . substr($last_hash, 0, 8) . ' current=' . substr($current_hash, 0, 8) . '), running verify_api_key()');
        $this->verify_api_key($key);
    }

    /**
     * Render a dismissible admin notice describing the current API key
     * validation status, if any was recorded by verify_api_key().
     */
    public function maybe_render_api_key_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Skip on the Connectors screen — the floating overlay handles that page.
        // Match by URL too, because WP/ai may register the screen under
        // different parents across releases.
        $screen      = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id   = $screen ? $screen->id : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $known_ids   = array('settings_page_connectors', 'toplevel_page_connectors', 'ai_page_connectors', 'connectors');
        $is_connectors_screen =
            in_array($screen_id, $known_ids, true) ||
            (strpos($request_uri, 'page=connectors') !== false);

        if ($is_connectors_screen) {
            self::log('maybe_render_api_key_notice — on Connectors screen (id=' . $screen_id . '), deferring to floating overlay');
            return;
        }

        $validation = get_option('queryra_api_key_validation');
        if (empty($validation) || empty($validation['level']) || empty($validation['message'])) {
            return;
        }
        if ($validation['level'] === 'unknown' || $validation['level'] === 'missing') {
            return;
        }

        // Single-shot per validation result: only render until the user has
        // seen this specific result once. The validation OPTION stays (so
        // REST and the floating overlay can still report status), but we
        // mark this hash as "notified" so we don't spam every page load.
        $notified_hash = (string) get_option('queryra_api_key_notified_hash', '');
        $current_hash  = isset($validation['key_hash']) ? $validation['key_hash'] : '';
        if ($notified_hash !== '' && $current_hash !== '' && $notified_hash === $current_hash) {
            // Already shown to the user for this exact key + result. Skip.
            return;
        }

        self::log('maybe_render_api_key_notice — rendering level=' . $validation['level'] . ' on screen=' . ($screen ? $screen->id : '?'));

        $is_success   = $validation['level'] === 'success';
        $level_class  = $is_success ? 'notice-success' : 'notice-error';
        $settings_url = admin_url('admin.php?page=queryra-search&tab=settings');
        ?>
        <div class="notice <?php echo esc_attr($level_class); ?> is-dismissible">
            <p>
                <strong>Queryra:</strong>
                <?php if ($is_success): ?>
                    <?php echo esc_html($validation['message']); ?>
                    <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open Queryra Settings →', 'queryra-ai-search'); ?></a>
                <?php else: ?>
                    <?php
                    printf(
                        /* translators: %s: error message returned by Queryra API */
                        esc_html__('The saved API key could not be verified — %s.', 'queryra-ai-search'),
                        esc_html($validation['message'])
                    );
                    ?>
                    <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open Queryra Settings →', 'queryra-ai-search'); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <?php

        // Mark this validation result as "shown" — the next render of the
        // same key+result will be skipped. A new validation (key changed
        // or status flipped) will produce a different key_hash and trigger
        // a fresh notice.
        if ($current_hash !== '') {
            update_option('queryra_api_key_notified_hash', $current_hash, false);
            self::log('maybe_render_api_key_notice — marked notified_hash=' . substr($current_hash, 0, 8));
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize admin
        if (is_admin()) {
            new Queryra_Admin();
            new Queryra_Setup_Wizard();
            new Queryra_Deactivation_Survey();
        }

        // Run upgrade routine if version changed
        $this->maybe_upgrade();

        // Initialize sync
        new Queryra_Sync();

        // Initialize search integration (AI-powered search)
        new Queryra_Search_Integration();

        // Serve /llms.txt and /llms-full.txt for AI crawlers
        new Queryra_LLMS();

        // WordPress 7.0+ Abilities API — register Queryra semantic search
        // as a discoverable ability for AI agents.
        if (class_exists('Queryra_Abilities')) {
            new Queryra_Abilities();
        }

        // WordPress 7.0+ Connectors API — register Queryra in
        // Settings → Connectors alongside OpenAI/Anthropic/Google.
        if (class_exists('Queryra_Connector')) {
            new Queryra_Connector();
        }

        // Third-party plugin integrations (B2BKing, etc.). The loader only
        // pulls in an integration when its target plugin is active, so sites
        // without those plugins parse zero integration code. We run on
        // plugins_loaded (this init) so all other plugins are already loaded.
        $integration_loader = new Queryra_Integration_Loader();
        $integration_loader->load();

        // Frontend fingerprint: enqueue stylesheet + generator meta tag
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_head', array($this, 'output_generator_meta'), 1);
    }

    /**
     * Enqueue a minimal frontend stylesheet so the plugin is discoverable
     * by WordPress ecosystem crawlers (themesinfo, WPHive, Wappalyzer).
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'queryra-ai-search',
            QUERYRA_PLUGIN_URL . 'css/queryra.css',
            array(),
            QUERYRA_VERSION
        );
    }

    /**
     * Output generator meta tag so detection tools (BuiltWith, Wappalyzer)
     * can fingerprint the plugin and its version.
     */
    public function output_generator_meta() {
        echo '<meta name="generator" content="Queryra AI Search ' . esc_attr(QUERYRA_VERSION) . '" />' . "\n";
    }

    /**
     * Run one-time upgrade tasks when plugin version changes.
     */
    private function maybe_upgrade() {
        $stored_version = get_option('queryra_plugin_version', '0');

        if (version_compare($stored_version, QUERYRA_VERSION, '>=')) {
            return;
        }

        // 1.1.6: ping status to register instance_id and plugin_type
        if (version_compare($stored_version, '1.1.6', '<') && get_option('queryra_api_key')) {
            $api = new Queryra_API();
            $api->get_status();
        }

        update_option('queryra_plugin_version', QUERYRA_VERSION);
    }

    /**
     * Activation
     */
    public function activate() {
        // Set default options
        if (!get_option('queryra_api_key')) {
            add_option('queryra_api_key', '');
        }
        if (!get_option('queryra_api_url')) {
            add_option('queryra_api_url', 'https://queryra.com');
        }
        if (!get_option('queryra_enabled')) {
            add_option('queryra_enabled', '1'); // Enabled by default
        }
        if (!get_option('queryra_auto_sync')) {
            add_option('queryra_auto_sync', '1');
        }
        if (!get_option('queryra_ai_search')) {
            add_option('queryra_ai_search', '0'); // Disabled by default
        }
        if (!get_option('queryra_post_types')) {
            add_option('queryra_post_types', array('post', 'page'));
        }

        // Set transient for first-time activation redirect to wizard
        set_transient('queryra_activation_redirect', true, 30);

        // Flush rewrite rules
        flush_rewrite_rules();

        // Track activation (anonymous)
        Queryra_Analytics::track('plugin_activated');
    }

    /**
     * Deactivation
     */
    public function deactivate() {
        // Track deactivation (anonymous)
        Queryra_Analytics::track('plugin_deactivated');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Register the REST endpoint our JS overlay polls to get the
     * authoritative API key validation status. Admin-only.
     */
    public function register_key_status_endpoint() {
        register_rest_route('queryra/v1', '/key-status', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_key_status_handler'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
    }

    public function rest_key_status_handler($request = null) {
        // TEST MODE: ?force_invalid=1 forces an error response. Lets us
        // test the JS toast-hijack on a valid key without invalidating it.
        if (is_object($request) && method_exists($request, 'get_param')) {
            $force_invalid = $request->get_param('force_invalid');
            if (!empty($force_invalid)) {
                self::log('REST /key-status — TEST MODE force_invalid=1, returning fake error');
                return rest_ensure_response(array(
                    'level'        => 'error',
                    'message'      => 'FORCED INVALID FOR TESTING (force_invalid=1)',
                    'validated_at' => time(),
                ));
            }
        }

        $validation = get_option('queryra_api_key_validation');
        self::log('REST /key-status — option=' . (empty($validation) ? 'NULL' : 'level=' . $validation['level']));
        if (empty($validation) || empty($validation['level'])) {
            $key = get_option('queryra_api_key');
            return rest_ensure_response(array(
                'level'   => empty($key) ? 'missing' : 'unknown',
                'message' => '',
            ));
        }
        return rest_ensure_response(array(
            'level'        => $validation['level'],
            'message'      => isset($validation['message']) ? $validation['message'] : '',
            'validated_at' => isset($validation['validated_at']) ? $validation['validated_at'] : null,
            // Stable identifier for this exact validation result — used by
            // the floating notice to remember user-dismissed state via
            // localStorage. Changes only when key or status changes.
            'key_hash'     => isset($validation['key_hash']) ? $validation['key_hash'] : null,
        ));
    }

    /**
     * Render a floating notice overlay on the WP 7.0 Connectors screen.
     * The React-driven Connectors UI covers the standard admin_notices area
     * and unconditionally shows a misleading "connected successfully" toast.
     * Our overlay polls the REST endpoint, then renders green/red status on
     * top so the user always sees the authoritative result.
     */
    public function render_connectors_floating_notice() {
        // No server-side screen check — render on every admin page where
        // the user can manage_options. The JS itself decides whether to
        // activate (it watches the URL + DOM for WP/ai's toast markers).
        // This sidesteps the unreliable get_current_screen() ID matching
        // we hit before.
        if (!current_user_can('manage_options')) {
            return;
        }

        self::log('render_connectors_floating_notice — emitting JS hijacker on admin page');

        $rest_url     = esc_url_raw(rest_url('queryra/v1/key-status'));
        $rest_nonce   = wp_create_nonce('wp_rest');
        $settings_url = esc_url(admin_url('admin.php?page=queryra-search&tab=settings'));
        ?>
        <div id="queryra-connectors-floating-notice" style="display:none;"></div>
        <script>
        (function() {
            var REST_URL     = <?php echo wp_json_encode($rest_url); ?>;
            var REST_NONCE   = <?php echo wp_json_encode($rest_nonce); ?>;
            var SETTINGS_URL = <?php echo wp_json_encode($settings_url); ?>;
            var PREFIX       = '[Queryra JS]';

            function log() {
                if (window.console && console.log) {
                    var args = Array.prototype.slice.call(arguments);
                    args.unshift(PREFIX);
                    console.log.apply(console, args);
                }
            }

            // Detect if we are on the Connectors page (URL-based, stable).
            var onConnectorsPage = window.location.search.indexOf('page=connectors') !== -1;
            log('init — onConnectorsPage=' + onConnectorsPage + ' url=' + window.location.href);

            // Allow forcing invalid for testing without touching the real key:
            //   1. URL:    add ?queryra_force_invalid=1 to the admin URL
            //   2. Console: window.QUERYRA_FORCE_INVALID = true; before save
            var forceInvalidURL = window.location.search.indexOf('queryra_force_invalid=1') !== -1;
            if (forceInvalidURL) {
                window.QUERYRA_FORCE_INVALID = true;
                log('init — TEST MODE: queryra_force_invalid=1 detected, will force error');
            }

            var el = document.getElementById('queryra-connectors-floating-notice');
            if (!el) {
                log('init — ERROR: floating notice element not found in DOM');
                return;
            }

            // ====================================================================
            // Persistent dismiss state via localStorage. Key = validation key_hash,
            // so a NEW validation result (key changed or status flipped) shows
            // again automatically, but the same result the user already saw and
            // dismissed stays hidden across page loads.
            // ====================================================================
            function getDismissedHash() {
                try { return localStorage.getItem('queryra_floating_dismissed') || ''; }
                catch (e) { return ''; }
            }
            function setDismissedHash(h) {
                try { localStorage.setItem('queryra_floating_dismissed', h); }
                catch (e) { /* private mode etc — non-fatal */ }
            }

            // Auto-dismiss timer for success notices (errors stay until dismissed).
            var autoDismissTimer = null;

            function showFloatingNotice(status) {
                if (!status || !status.level || status.level === 'unknown' || status.level === 'missing') {
                    el.style.display = 'none';
                    return;
                }

                // If the user already dismissed THIS exact validation result, stay hidden.
                var hash = status.key_hash || (status.level + ':' + (status.message || ''));
                if (getDismissedHash() === hash) {
                    log('showFloatingNotice — user previously dismissed this status (hash=' + hash.slice(0, 8) + '), skipping');
                    el.style.display = 'none';
                    return;
                }

                var isError = status.level === 'error';
                var bg      = isError ? '#dc3232' : '#46b450';
                var icon    = isError ? '⚠' : '✓';
                var prefix  = isError ? 'API key invalid' : 'API key verified';
                var msg     = status.message || '';

                el.style.cssText = [
                    'position:fixed','top:46px','right:20px','z-index:999999',
                    'padding:14px 18px','border-radius:6px','background:' + bg,
                    'color:#fff','box-shadow:0 4px 14px rgba(0,0,0,0.2)',
                    'max-width:420px','font-size:13px','line-height:1.5','display:block',
                    'transition:opacity 0.4s ease'
                ].join(';');

                el.innerHTML =
                    '<div style="display:flex;align-items:flex-start;gap:8px;">' +
                        '<span style="font-size:18px;line-height:1;">' + icon + '</span>' +
                        '<div style="flex:1;">' +
                            '<strong>Queryra: ' + prefix + '</strong>' +
                            (msg ? ' &mdash; ' + escapeHtml(msg) : '') +
                            ' <a href="' + SETTINGS_URL + '" style="color:#fff;text-decoration:underline;">Open Queryra Settings &rarr;</a>' +
                        '</div>' +
                        '<span class="queryra-dismiss" style="cursor:pointer;font-size:18px;line-height:1;opacity:0.8;" title="Dismiss">&times;</span>' +
                    '</div>';

                // Click × → persist dismiss for this hash, hide notice.
                var dismissBtn = el.querySelector('.queryra-dismiss');
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', function() {
                        setDismissedHash(hash);
                        el.style.opacity = '0';
                        setTimeout(function() { el.style.display = 'none'; }, 400);
                        log('showFloatingNotice — user dismissed, stored hash=' + hash.slice(0, 8));
                    });
                }

                // SUCCESS notice fades on its own after 6 seconds (less critical).
                // ERROR notice stays until user dismisses (actionable).
                if (autoDismissTimer) { clearTimeout(autoDismissTimer); autoDismissTimer = null; }
                if (!isError) {
                    autoDismissTimer = setTimeout(function() {
                        setDismissedHash(hash);
                        el.style.opacity = '0';
                        setTimeout(function() { el.style.display = 'none'; }, 400);
                        log('showFloatingNotice — success auto-dismissed after 6s');
                    }, 6000);
                }

                log('showFloatingNotice — rendered level=' + status.level + ' hash=' + hash.slice(0, 8));
            }

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, function(c) {
                    return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
                });
            }

            function fetchStatus(cb) {
                var url = REST_URL;
                if (window.QUERYRA_FORCE_INVALID) {
                    url += (url.indexOf('?') === -1 ? '?' : '&') + 'force_invalid=1';
                    log('fetchStatus — using TEST MODE url with force_invalid=1');
                }
                log('fetchStatus — GET ' + url);
                fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': REST_NONCE, 'Accept': 'application/json' }
                })
                .then(function(r) {
                    log('fetchStatus — response status=' + r.status);
                    return r.ok ? r.json() : null;
                })
                .then(function(status) {
                    log('fetchStatus — got status:', status);
                    if (cb) cb(status);
                })
                .catch(function(e) {
                    log('fetchStatus — error:', e);
                });
            }

            // ====================================================================
            // TOAST HIJACKER
            // Watches the DOM for WP/ai's "connected successfully" toast for the
            // Queryra connector and, if our REST endpoint reports the key is
            // actually invalid, rewrites the toast in place.
            // ====================================================================
            function looksLikeWpAiToast(node) {
                if (!node || node.nodeType !== 1) return false;
                var text = (node.textContent || '').toLowerCase();
                if (text.indexOf('queryra') === -1) return false;
                // Match either successful or failed save messages from WP/ai
                if (text.indexOf('connect') === -1 && text.indexOf('disconnect') === -1) return false;
                return true;
            }

            function hijackToast(toastEl) {
                log('hijackToast — candidate node detected, text="' + (toastEl.textContent || '').slice(0, 120) + '"');
                fetchStatus(function(status) {
                    if (!status) {
                        log('hijackToast — no status, leaving toast alone');
                        return;
                    }
                    log('hijackToast — applying status level=' + status.level + ' to toast');

                    if (status.level === 'error') {
                        // Rewrite toast text + color
                        var msg = status.message || 'Invalid API key';
                        toastEl.style.background     = '#dc3232';
                        toastEl.style.color          = '#fff';
                        toastEl.style.borderColor    = '#dc3232';
                        toastEl.textContent          = 'Queryra: ' + msg;
                        log('hijackToast — REWROTE to error message');

                        // React may re-render and overwrite our changes; pin it
                        // for a few seconds by repeating the rewrite.
                        var ticks = 0;
                        var pin = setInterval(function() {
                            if (++ticks > 10) { clearInterval(pin); return; }
                            if (toastEl.isConnected) {
                                toastEl.style.background = '#dc3232';
                                toastEl.style.color = '#fff';
                                if (toastEl.textContent.indexOf('Queryra:') !== 0) {
                                    toastEl.textContent = 'Queryra: ' + msg;
                                }
                            } else {
                                clearInterval(pin);
                            }
                        }, 200);
                    } else if (status.level === 'success') {
                        log('hijackToast — status is success, leaving WP/ai toast as-is');
                    } else {
                        log('hijackToast — status is ' + status.level + ', no action');
                    }
                });
            }

            // Start the observer only on Connectors page (toasts elsewhere are
            // irrelevant). Watches the WHOLE body subtree because we do not
            // know exactly where WP/ai mounts its toast portal.
            if (onConnectorsPage) {
                log('init — setting up MutationObserver for WP/ai toasts');
                var observer = new MutationObserver(function(mutations) {
                    for (var i = 0; i < mutations.length; i++) {
                        var m = mutations[i];
                        for (var j = 0; j < m.addedNodes.length; j++) {
                            var n = m.addedNodes[j];
                            if (looksLikeWpAiToast(n)) {
                                hijackToast(n);
                            } else if (n.querySelectorAll) {
                                // Toast may be a child of the added node
                                var nested = n.querySelectorAll('*');
                                for (var k = 0; k < nested.length; k++) {
                                    if (looksLikeWpAiToast(nested[k])) {
                                        hijackToast(nested[k]);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
                log('init — observer attached');
            }

            // Initial fetch for the floating notice (works on all admin pages).
            fetchStatus(showFloatingNotice);

            // Poll status briefly after page load to catch saves in flight.
            var ticks = 0;
            var interval = setInterval(function() {
                if (++ticks > 15) { clearInterval(interval); return; }
                fetchStatus(showFloatingNotice);
            }, 2000);

            // Click anywhere → re-check (catches Save button without us
            // depending on a specific selector inside WP/ai's React tree).
            document.addEventListener('click', function(e) {
                if (e.target && e.target.tagName === 'BUTTON') {
                    setTimeout(function() { fetchStatus(showFloatingNotice); }, 800);
                    setTimeout(function() { fetchStatus(showFloatingNotice); }, 2500);
                }
            });
        })();
        </script>
        <?php
    }
}

// Initialize plugin
Queryra_Search::get_instance();
