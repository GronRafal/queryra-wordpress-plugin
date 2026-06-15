<?php
/**
 * Queryra Admin
 *
 * Handles admin interface and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Admin {

    /**
     * API client
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new Queryra_API();

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handler for clearing cache
        add_action('wp_ajax_queryra_clear_cache', array($this, 'ajax_clear_cache'));

        // AJAX handler for dismissing the UX tip (shared between Settings tab and wizard)
        add_action('wp_ajax_queryra_dismiss_ux_tip', array($this, 'ajax_dismiss_ux_tip'));

        // Plugins page row links
        add_filter('plugin_action_links_' . QUERYRA_PLUGIN_BASENAME, array($this, 'add_action_links'));
        add_filter('plugin_row_meta', array($this, 'add_row_meta'), 10, 2);
    }

    /**
     * Add "Settings" link to plugin action links (left side on Plugins page).
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=queryra-search&tab=settings')) . '">' . esc_html__('Settings', 'queryra-ai-search') . '</a>';
        array_unshift($links, $settings_link);

        // Show "Get API Key" only if no key configured yet.
        if (!get_option('queryra_api_key')) {
            $get_key_link = '<a href="' . esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard')) . '" target="_blank" rel="noopener" style="font-weight:600;color:#2271b1;">' . esc_html__('Get API Key', 'queryra-ai-search') . '</a>';
            array_unshift($links, $get_key_link);
        }

        return $links;
    }

    /**
     * Add meta links (right side on Plugins page) below the description.
     */
    public function add_row_meta($links, $file) {
        if ($file !== QUERYRA_PLUGIN_BASENAME) {
            return $links;
        }
        $links[] = '<a href="' . esc_url(Queryra_Search::tracked_url('https://woo.queryra.com')) . '" target="_blank" rel="noopener">' . esc_html__('Live Demo', 'queryra-ai-search') . '</a>';
        $links[] = '<a href="' . esc_url(Queryra_Search::tracked_url('https://queryra.com/docs')) . '" target="_blank" rel="noopener">' . esc_html__('Docs', 'queryra-ai-search') . '</a>';
        $links[] = '<a href="https://wordpress.org/support/plugin/queryra-ai-search/" target="_blank" rel="noopener">' . esc_html__('Support', 'queryra-ai-search') . '</a>';
        return $links;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Queryra Search',
            'Queryra',
            'manage_options',
            'queryra-search',
            array($this, 'render_settings_page'),
            'dashicons-search',
            80
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('queryra_settings', 'queryra_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        register_setting('queryra_settings', 'queryra_api_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://queryra.com/api/v1'
        ));
        register_setting('queryra_settings', 'queryra_auto_sync', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
        register_setting('queryra_settings', 'queryra_ai_search', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
        register_setting('queryra_settings', 'queryra_post_types', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_post_types'),
            'default' => array('post', 'page')
        ));
        register_setting('queryra_settings', 'queryra_cache_duration', array(
            'type' => 'integer',
            'sanitize_callback' => 'intval',
            'default' => 86400
        ));
        // AI Discoverability — enabled by default so the plugin earns
        // attribution from LLMs (ChatGPT, Perplexity, Claude) out of the box.
        register_setting('queryra_settings', 'queryra_generate_llms_txt', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
        register_setting('queryra_settings', 'queryra_generate_llms_full_txt', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
        register_setting('queryra_settings', 'queryra_output_schema', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
    }

    /**
     * Sanitize post types array
     */
    public function sanitize_post_types($value) {
        // Posts are ALWAYS included (hardcoded)
        $result = array('post');

        // If not array, just return posts only
        if (!is_array($value)) {
            return $result;
        }

        // Whitelist = any public post type registered on this site, minus
        // internal/system types. This lets custom post types (recipes,
        // vehicles, portfolios, etc.) be indexed: the sync query, ACF
        // extraction and taxonomy collection are all post-type agnostic,
        // so accepting CPTs here is the only gate that needs opening.
        $exclude      = array('attachment', 'revision', 'nav_menu_item', 'wp_block');
        $public_types = get_post_types(array('public' => true));
        $allowed      = array_diff($public_types, $exclude);

        // sanitize_key matches WordPress' own post-type slug rules
        // (lowercase, alphanumerics, hyphen/underscore).
        $sanitized = array_map('sanitize_key', $value);
        foreach ($sanitized as $type) {
            if ($type === '' || $type === 'post') {
                continue; // empty placeholder / already included
            }
            if (in_array($type, $allowed, true) && !in_array($type, $result, true)) {
                $result[] = $type;
            }
        }

        return $result;
    }

    /**
     * Render the "tell your visitors about AI search" tip card.
     * Shared between Settings tab and the setup wizard so both surfaces
     * carry the same message and dismiss state.
     *
     * Returns nothing if the user already dismissed the tip.
     */
    public static function render_ux_tip() {
        if (get_option('queryra_ux_tip_dismissed') === '1') {
            return;
        }
        $nonce = wp_create_nonce('queryra_dismiss_ux_tip');
        ?>
        <div class="queryra-ux-tip" style="background: #fffaf0; border: 1px solid #ccd0d4; border-left: 4px solid #f0b849; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; margin: 20px 0;">
            <h2 style="margin: 0 0 10px 0; padding-bottom: 10px; border-bottom: 1px solid #eee; font-size: 20px;">
                <span class="dashicons dashicons-lightbulb" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px; color: #f0b849;"></span>
                Tip: tell your visitors you have AI search
            </h2>
            <p style="margin: 0 0 12px 0;">
                Most visitors search like on Google — one or two keywords. They won't unlock AI semantic search unless they know it's there. A few tiny tweaks change everything:
            </p>

            <div style="display: grid; grid-template-columns: 28px 1fr; gap: 8px 12px; margin: 12px 0; align-items: start;">
                <div style="font-size: 18px;">🏷️</div>
                <div>
                    <strong>Relabel the search field</strong><br>
                    <code>Search</code> → <code>AI Search</code> or <code>Smart Search</code>
                </div>

                <div style="font-size: 18px;">✏️</div>
                <div>
                    <strong>Update the placeholder text</strong><br>
                    <code>Search products...</code> → <code>Describe what you need...</code> or <code>Ask in your own words...</code>
                </div>

                <div style="font-size: 18px;">💬</div>
                <div>
                    <strong>Add a hint below the search bar</strong><br>
                    e.g. <em>"Try a full sentence or question"</em>, <em>"Powered by AI — search naturally"</em>
                </div>
            </div>

            <p style="margin: 12px 0 0 0; font-size: 13px; color: #646970;">
                Where to change this depends on your theme: block themes use the Site Editor, page builders (Elementor, Divi) have a search widget, classic themes use <code>searchform.php</code>.
            </p>

            <p style="margin-top: 16px;">
                <button type="button" class="button button-primary queryra-ux-tip-dismiss" data-nonce="<?php echo esc_attr($nonce); ?>">
                    Got it
                </button>
            </p>
        </div>
        <script>
        (function($) {
            $(document).on('click', '.queryra-ux-tip-dismiss', function() {
                var $btn = $(this);
                var $card = $btn.closest('.queryra-ux-tip');
                $.post(ajaxurl, {
                    action: 'queryra_dismiss_ux_tip',
                    _ajax_nonce: $btn.data('nonce')
                });
                $card.slideUp(300, function() { $card.remove(); });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_queryra-search') {
            return;
        }

        wp_enqueue_style('queryra-admin', QUERYRA_PLUGIN_URL . 'css/admin.css', array(), QUERYRA_VERSION);
        wp_enqueue_script('queryra-admin', QUERYRA_PLUGIN_URL . 'js/admin.js', array('jquery'), QUERYRA_VERSION, true);

        wp_localize_script('queryra-admin', 'queryraData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('queryra_sync'),
            'cacheNonce' => wp_create_nonce('queryra_cache'),
            'hasApiKey' => !empty(get_option('queryra_api_key'))
        ));
    }

    /**
     * Convert status time from UTC to WordPress timezone
     *
     * @param array $status Status from API (with UTC times)
     * @return array Status with WordPress timezone times
     */
    private function convert_status_to_wp_timezone($status) {
        if (!isset($status['next_opens_at'])) {
            return $status;
        }

        // Get WordPress timezone
        $wp_timezone = wp_timezone();

        try {
            // Parse UTC time from API (format: "12:00 UTC")
            $utc_time_string = str_replace(' UTC', '', $status['next_opens_at']);

            // Create DateTime in UTC
            $utc_datetime = new DateTime($utc_time_string . ' UTC');

            // Convert to WordPress timezone
            $utc_datetime->setTimezone($wp_timezone);

            // Format as "13:00" (local time, no timezone label)
            $status['next_opens_at'] = $utc_datetime->format('H:i');

        } catch (Exception $e) {
            // If conversion fails, keep original
        }

        return $status;
    }

    /**
     * Render settings page
     */
    /**
     * Render the "Recent issues" card (Settings tab).
     *
     * Surfaces the local error log written by
     * Queryra_Analytics::report_error() — the same problems the plugin
     * reports to telemetry, shown to the site owner so import, search,
     * key, and integration failures are visible without digging through
     * debug.log. Renders nothing when the log is empty, so a healthy
     * install never sees this card.
     */
    private function render_recent_issues() {
        $entries = get_option(Queryra_Analytics::RECENT_ERRORS_OPTION, array());
        if (empty($entries) || !is_array($entries)) {
            return;
        }

        // Human-friendly category labels + a suggested next step shown
        // once per category present in the log.
        $labels = array(
            'sync'           => 'Content sync',
            'search'         => 'AI search',
            'key_validation' => 'API key',
            'integration'    => 'Integration',
        );
        $hints = array(
            'sync'           => 'Some content failed to reach the Queryra index. Retry the import from the Records tab — if it keeps failing, import posts/pages and products separately.',
            'search'         => 'A visitor\'s search temporarily fell back to standard WordPress keyword search. If this repeats, check your API key and plan limits.',
            'key_validation' => 'Your API key could not be verified. Check the key in Settings below, or get a new one from the Queryra dashboard.',
            'integration'    => 'A plugin integration (e.g. B2BKing) hit a problem and used its safe fallback.',
        );

        $categories_present = array();
        ?>
        <div class="queryra-card" style="border-left: 4px solid #f0b849;">
            <h2 style="margin-bottom: 4px;">
                <span class="dashicons dashicons-warning" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px; color: #f0b849;"></span>
                Recent issues
            </h2>
            <p style="color: #646970; margin-top: 0;">
                Problems the plugin ran into recently (last <?php echo esc_html(Queryra_Analytics::RECENT_ERRORS_MAX); ?> kept, stored only on your site).
            </p>

            <table class="widefat striped" style="margin: 10px 0;">
                <thead>
                    <tr>
                        <th style="width: 110px;">When</th>
                        <th style="width: 130px;">Area</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $category = isset($entry['category']) ? (string) $entry['category'] : 'general';
                    $categories_present[$category] = true;

                    $detail_parts = array();
                    if (!empty($entry['message'])) {
                        $detail_parts[] = $entry['message'];
                    }
                    if (!empty($entry['code'])) {
                        $detail_parts[] = '(' . $entry['code'] . ')';
                    }
                    if (!empty($entry['context'])) {
                        $detail_parts[] = '— ' . str_replace('_', ' ', $entry['context']);
                    }
                    $detail = $detail_parts ? implode(' ', $detail_parts) : 'No details recorded';

                    $count = isset($entry['count']) ? (int) $entry['count'] : 1;
                    ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <?php echo !empty($entry['time']) ? esc_html(human_time_diff((int) $entry['time'], time()) . ' ago') : '—'; ?>
                        </td>
                        <td>
                            <?php echo esc_html(isset($labels[$category]) ? $labels[$category] : ucfirst($category)); ?>
                        </td>
                        <td>
                            <?php echo esc_html($detail); ?>
                            <?php if ($count > 1): ?>
                                <span style="background: #f0b849; color: #1d2327; border-radius: 10px; padding: 1px 8px; font-size: 11px; font-weight: 600; margin-left: 6px;">
                                    &times;<?php echo esc_html($count); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php foreach (array_keys($categories_present) as $category): ?>
                <?php if (isset($hints[$category])): ?>
                    <p style="color: #646970; margin: 6px 0;">
                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <strong><?php echo esc_html($labels[$category]); ?>:</strong>
                        <?php echo esc_html($hints[$category]); ?>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>

            <form method="post" style="margin-top: 10px;">
                <?php wp_nonce_field('queryra_clear_recent_errors'); ?>
                <button type="submit" name="queryra_clear_recent_errors" value="1" class="button button-secondary">
                    Clear list
                </button>
            </form>
        </div>
        <?php
    }

    public function render_settings_page() {
        // Handle "Clear list" from the Recent issues card.
        $recent_errors_cleared = false;
        if (isset($_POST['queryra_clear_recent_errors'])) {
            check_admin_referer('queryra_clear_recent_errors');
            if (current_user_can('manage_options')) {
                delete_option(Queryra_Analytics::RECENT_ERRORS_OPTION);
                $recent_errors_cleared = true;
            }
        }

        // Get current settings
        $api_key = get_option('queryra_api_key', '');
        $api_url = get_option('queryra_api_url', 'https://queryra.com');
        $auto_sync = get_option('queryra_auto_sync', '1');
        $ai_search = get_option('queryra_ai_search', '0'); // Disabled by default
        $post_types = get_option('queryra_post_types', array('post', 'page'));
        $generate_llms_txt      = get_option('queryra_generate_llms_txt', '1');
        $generate_llms_full_txt = get_option('queryra_generate_llms_full_txt', '1');
        $output_schema          = get_option('queryra_output_schema', '1');

        // Get available post types (exclude attachment/media and revisions)
        $all_post_types = get_post_types(array('public' => true), 'objects');
        $available_post_types = array();

        // Filter out unwanted types
        $exclude = array('attachment', 'revision', 'nav_menu_item', 'wp_block');
        foreach ($all_post_types as $post_type) {
            if (!in_array($post_type->name, $exclude)) {
                $available_post_types[$post_type->name] = $post_type;
            }
        }

        // Get API data if API key is set
        // Admin panel ALWAYS fetches fresh data from API (no cache)
        $stats = null;
        $status = null;
        $api_error = false;

        if (!empty($api_key)) {
            $api = new Queryra_API();

            // Get stats (records, limits, plan) - FRESH from API.
            // 10s timeout: this runs on every plugin admin page view, and
            // with the default 30s a slow API would hang each tab click
            // for up to a minute (two calls).
            $stats_response = $api->get_stats(10);
            if (!is_wp_error($stats_response)) {
                $stats = $stats_response;
                // Save to DB for search integration to use
                update_option('queryra_cached_stats', $stats, false);
            } else {
                // Keep the last-known-good cached value. The frontend search
                // path relies on queryra_cached_stats/status for its FREE-plan
                // pre-flight checks — wiping them on a transient admin-side
                // failure would make every search fire doomed API calls.
                $api_error = true;
            }

            // Get status (search window for FREE plan) - FRESH from API
            $status_response = $api->get_status(10);
            if (!is_wp_error($status_response)) {
                $status = $status_response;
                // Convert UTC time to WordPress timezone for display
                $status = $this->convert_status_to_wp_timezone($status);
                // Save to DB for search integration to use
                update_option('queryra_cached_status', $status, false);
            } else {
                $api_error = true;
            }
        }

        // Get active tab
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed for tab display
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';

        ?>
        <div class="wrap">
            <h1>Queryra Search</h1>

            <?php
            // Show success message after saving settings
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP settings API pattern
            if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>';
            }

            if ($recent_errors_cleared) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Recent issues cleared.</strong></p></div>';
            }
            ?>

            <?php settings_errors('queryra_post_types'); ?>

            <!-- Tab Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=queryra-search&tab=settings"
                   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    Settings
                </a>
                <a href="?page=queryra-search&tab=content"
                   class="nav-tab <?php echo $active_tab === 'content' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-media-document" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    Content
                </a>
                <a href="?page=queryra-search&tab=woocommerce"
                   class="nav-tab <?php echo $active_tab === 'woocommerce' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-cart" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    WooCommerce
                </a>
                <a href="?page=queryra-search&tab=records"
                   class="nav-tab <?php echo $active_tab === 'records' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-list-view" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    Records
                </a>
                <a href="?page=queryra-search&tab=sync"
                   class="nav-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    Sync
                </a>
                <a href="?page=queryra-search&tab=search-history"
                   class="nav-tab <?php echo $active_tab === 'search-history' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-chart-line" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    Search History
                </a>
                <a href="?page=queryra-search&tab=cache"
                   class="nav-tab <?php echo $active_tab === 'cache' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-performance" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    Cache
                </a>
                <a href="?page=queryra-search&tab=support"
                   class="nav-tab <?php echo $active_tab === 'support' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-sos" style="font-size: 16px; width: 16px; height: 16px; margin-top: 6px;"></span>
                    Support
                </a>
            </h2>

            <div class="queryra-container">
                <!-- Settings Form -->
                <div class="queryra-main">

                    <?php if ($active_tab === 'settings'): ?>
                    <!-- Settings Tab -->

                    <?php $this->render_recent_issues(); ?>

                    <form method="post" action="options.php">
                        <?php settings_fields('queryra_settings'); ?>
                        <!-- Preserve post types selection -->
                        <?php foreach ($post_types as $pt): ?>
                            <input type="hidden" name="queryra_post_types[]" value="<?php echo esc_attr($pt); ?>">
                        <?php endforeach; ?>
                        <!-- Preserve auto import setting (configured in Content tab) -->
                        <input type="hidden" name="queryra_auto_sync" value="<?php echo esc_attr($auto_sync); ?>">

                        <div class="queryra-card">
                            <h2>
                                <span class="dashicons dashicons-admin-generic" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                                Settings
                            </h2>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="queryra_api_key">API Key</label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               id="queryra_api_key"
                                               name="queryra_api_key"
                                               value="<?php echo esc_attr($api_key); ?>"
                                               class="regular-text queryra-masked-key"
                                               autocomplete="off"
                                               spellcheck="false"
                                               placeholder="sk_live_xxxxxxxxxxxxx">
                                        <p class="description">Your Queryra API key — click to edit</p>
                                        <script>
                                        (function($) {
                                            // Mirror the Connectors API masking: keep last 4 chars visible,
                                            // bullets for the rest; reveal the full key on focus.
                                            function mask(s) {
                                                if (!s || s.length <= 4) return s || '';
                                                var bullets = new Array(Math.min(s.length - 4, 24) + 1).join('•');
                                                return bullets + s.slice(-4);
                                            }
                                            $('.queryra-masked-key').each(function() {
                                                var $input = $(this);
                                                if ($input.data('queryraMaskBound')) return;
                                                $input.data('queryraMaskBound', true);
                                                var real = $input.val();
                                                $input.data('realValue', real);
                                                if (real && real.length > 4) {
                                                    $input.val(mask(real));
                                                }
                                                $input.on('focus', function() {
                                                    var $t = $(this);
                                                    $t.val($t.data('realValue') || '');
                                                    setTimeout(function() { $t.select(); }, 0);
                                                });
                                                $input.on('input', function() {
                                                    $(this).data('realValue', $(this).val());
                                                });
                                                $input.on('blur', function() {
                                                    var $t = $(this);
                                                    var v = $t.data('realValue');
                                                    if (v && v.length > 4) {
                                                        $t.val(mask(v));
                                                    }
                                                });
                                                // Restore real value right before the form submits so
                                                // bullets are never sent to options.php.
                                                $input.closest('form').on('submit', function() {
                                                    $input.val($input.data('realValue') || '');
                                                });
                                            });
                                        })(jQuery);
                                        </script>
                                    </td>
                                </tr>
                                <input type="hidden" name="queryra_api_url" value="<?php echo esc_attr($api_url); ?>">
                                <tr>
                                    <th scope="row">
                                        Plugin Enabled
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="queryra_ai_search"
                                                   value="1"
                                                   <?php checked($ai_search, '1'); ?>>
                                            <strong>Enable Queryra AI Search</strong>
                                        </label>
                                        <p class="description" style="margin-top: 8px;">
                                            When enabled, WordPress native search will automatically use Queryra AI for intelligent, semantic search results.
                                        </p>
                                        <?php if (!empty($api_key) && $ai_search === '1'): ?>
                                            <p style="margin-top: 10px;">
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=queryra-setup-wizard&step=4')); ?>" class="button button-secondary">
                                                    <span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom;"></span>
                                                    Try a test search
                                                </a>
                                                <span class="description" style="margin-left: 6px;">Run a sample query in the wizard sandbox.</span>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin-top: 20px;">
                                <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/api-keys')); ?>" target="_blank" rel="noopener" class="button button-secondary">
                                    Open Dashboard
                                    <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                </a>
                            </p>
                        </div>

                        <?php
                        // Show "tell your visitors about AI search" tip only when AI is enabled
                        // and the user hasn't dismissed it yet.
                        if ($ai_search === '1') {
                            self::render_ux_tip();
                        }
                        ?>

                        <div class="queryra-card">
                            <h2>
                                <span class="dashicons dashicons-share-alt2" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                                AI Discoverability
                            </h2>
                            <p>
                                Help LLMs (ChatGPT, Perplexity, Claude, Google AI Overviews) discover this site and its AI search capability.
                                Improves how AI assistants describe your store to users.
                            </p>
                            <p style="background:#f0f6fc;border-left:4px solid #2271b1;padding:8px 12px;margin:12px 0;">
                                <strong>Safe by design:</strong> nothing is written to your filesystem. All discoverability files are generated on-the-fly when an AI crawler asks for them. If a real <code>llms.txt</code> already exists on your server, it always takes priority.
                            </p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">Structured data</th>
                                    <td>
                                        <input type="hidden" name="queryra_output_schema" value="0">
                                        <label>
                                            <input type="checkbox"
                                                   name="queryra_output_schema"
                                                   value="1"
                                                   <?php checked($output_schema, '1'); ?>>
                                            <strong>Output JSON-LD schema &amp; X-Search-Engine header</strong>
                                        </label>
                                        <p class="description" style="margin-top: 8px;">
                                            Adds <code>SearchResultsPage</code> schema on search pages and <code>Service</code> schema site-wide,
                                            both attributing the search engine to Queryra. Disable only if your SEO plugin already handles this.
                                        </p>
                                    </td>
                                </tr>
                                <?php
                                $llms_state      = Queryra_LLMS::detect_static_file_state('llms.txt');
                                $llms_full_state = Queryra_LLMS::detect_static_file_state('llms-full.txt');
                                $llms_helper     = new Queryra_LLMS();
                                ?>
                                <tr>
                                    <th scope="row">/llms.txt</th>
                                    <td>
                                        <?php // When checkbox is active: hidden=0 lets unchecking disable.
                                              // When checkbox is disabled (static file): preserve current option value. ?>
                                        <input type="hidden" name="queryra_generate_llms_txt" value="<?php echo $llms_state === 'none' ? '0' : esc_attr($generate_llms_txt); ?>">
                                        <label>
                                            <input type="checkbox"
                                                   name="queryra_generate_llms_txt"
                                                   value="1"
                                                   <?php disabled($llms_state !== 'none'); ?>
                                                   <?php checked($generate_llms_txt, '1'); ?>>
                                            <strong>Modify <code>/llms.txt</code> response</strong>
                                        </label>
                                        <p class="description" style="margin-top: 8px;">
                                            When an AI crawler requests <code>/llms.txt</code>, the plugin responds with a brief site identity and AI Search section.
                                            Proposed standard by <a href="https://llmstxt.org/" target="_blank" rel="noopener">llmstxt.org</a>.
                                            No file is written to disk.
                                        </p>

                                        <?php if ($llms_state === 'none'): ?>
                                            <p style="margin-top: 8px;">
                                                📄 <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" rel="noopener">View current /llms.txt →</a>
                                            </p>
                                        <?php elseif ($llms_state === 'has_section'): ?>
                                            <div class="notice notice-success inline" style="margin: 10px 0; padding: 8px 12px;">
                                                <p style="margin: 0;">
                                                    ✓ Static <code>/llms.txt</code> detected with a Queryra AI Search section.
                                                    Looking good — your file is served as-is by the web server.
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <div class="notice notice-warning inline" style="margin: 10px 0; padding: 8px 12px;">
                                                <p style="margin: 0 0 8px 0;">
                                                    ⚠ Static <code>/llms.txt</code> detected at your site root, but it does <strong>not</strong> contain the Queryra AI Search section.
                                                    The web server serves your file directly, so the plugin can't add this automatically.
                                                </p>
                                                <p style="margin: 8px 0 4px 0;">
                                                    <strong>To get AEO benefit, copy this snippet into your <code>/llms.txt</code>:</strong>
                                                </p>
                                                <textarea readonly rows="9" class="large-text code queryra-snippet" onclick="this.select()"><?php echo esc_textarea($llms_helper->get_snippet_short()); ?></textarea>
                                                <button type="button" class="button button-secondary queryra-copy-snippet" data-target=".queryra-snippet:eq(0)" style="margin-top: 6px;">
                                                    <span class="dashicons dashicons-clipboard" style="vertical-align: text-bottom;"></span> Copy snippet
                                                </button>
                                                <span class="queryra-copy-feedback" style="margin-left: 8px; color: #46b450; display: none;">✓ Copied!</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">/llms-full.txt</th>
                                    <td>
                                        <input type="hidden" name="queryra_generate_llms_full_txt" value="<?php echo $llms_full_state === 'none' ? '0' : esc_attr($generate_llms_full_txt); ?>">
                                        <label>
                                            <input type="checkbox"
                                                   name="queryra_generate_llms_full_txt"
                                                   value="1"
                                                   <?php disabled($llms_full_state !== 'none'); ?>
                                                   <?php checked($generate_llms_full_txt, '1'); ?>>
                                            <strong>Modify <code>/llms-full.txt</code> response</strong>
                                        </label>
                                        <p class="description" style="margin-top: 8px;">
                                            Expanded version with detailed AI search capabilities — for deeper AI ingestion.
                                            Same standard as <code>/llms.txt</code>.
                                        </p>

                                        <?php if ($llms_full_state === 'none'): ?>
                                            <p style="margin-top: 8px;">
                                                📄 <a href="<?php echo esc_url(home_url('/llms-full.txt')); ?>" target="_blank" rel="noopener">View current /llms-full.txt →</a>
                                            </p>
                                        <?php elseif ($llms_full_state === 'has_section'): ?>
                                            <div class="notice notice-success inline" style="margin: 10px 0; padding: 8px 12px;">
                                                <p style="margin: 0;">
                                                    ✓ Static <code>/llms-full.txt</code> detected with a Queryra AI Search section.
                                                    Looking good.
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <div class="notice notice-warning inline" style="margin: 10px 0; padding: 8px 12px;">
                                                <p style="margin: 0 0 8px 0;">
                                                    ⚠ Static <code>/llms-full.txt</code> detected, but missing the Queryra AI Search section.
                                                </p>
                                                <p style="margin: 8px 0 4px 0;">
                                                    <strong>Copy this expanded snippet into your <code>/llms-full.txt</code>:</strong>
                                                </p>
                                                <textarea readonly rows="20" class="large-text code queryra-snippet" onclick="this.select()"><?php echo esc_textarea($llms_helper->get_snippet_full()); ?></textarea>
                                                <button type="button" class="button button-secondary queryra-copy-snippet" data-target=".queryra-snippet:eq(1)" style="margin-top: 6px;">
                                                    <span class="dashicons dashicons-clipboard" style="vertical-align: text-bottom;"></span> Copy snippet
                                                </button>
                                                <span class="queryra-copy-feedback" style="margin-left: 8px; color: #46b450; display: none;">✓ Copied!</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <script>
                            (function($) {
                                $(document).on('click', '.queryra-copy-snippet', function() {
                                    var $btn = $(this);
                                    var $textarea = $btn.closest('.notice').find('textarea.queryra-snippet');
                                    if (!$textarea.length) return;
                                    $textarea[0].select();
                                    try {
                                        document.execCommand('copy');
                                        $textarea[0].setSelectionRange(0, 0);
                                        var $feedback = $btn.siblings('.queryra-copy-feedback');
                                        $feedback.fadeIn(150);
                                        setTimeout(function() { $feedback.fadeOut(300); }, 2000);
                                    } catch (e) {
                                        // Fallback: textarea is already selected, user can Ctrl/Cmd+C.
                                    }
                                });
                            })(jQuery);
                            </script>
                        </div>

                        <?php submit_button('Save Settings'); ?>
                    </form>

                    <?php elseif ($active_tab === 'content'): ?>
                    <!-- Content Tab -->
                    <form method="post" action="options.php">
                        <?php settings_fields('queryra_settings'); ?>
                        <!-- Preserve other settings -->
                        <input type="hidden" name="queryra_api_key" value="<?php echo esc_attr($api_key); ?>">
                        <input type="hidden" name="queryra_api_url" value="<?php echo esc_attr($api_url); ?>">
                        <input type="hidden" name="queryra_auto_sync" value="<?php echo esc_attr($auto_sync); ?>">
                        <input type="hidden" name="queryra_ai_search" value="<?php echo esc_attr($ai_search); ?>">
                        <input type="hidden" name="queryra_output_schema" value="<?php echo esc_attr($output_schema); ?>">
                        <input type="hidden" name="queryra_generate_llms_txt" value="<?php echo esc_attr($generate_llms_txt); ?>">
                        <input type="hidden" name="queryra_generate_llms_full_txt" value="<?php echo esc_attr($generate_llms_full_txt); ?>">
                        <!-- Preserve product post type if selected in WooCommerce tab -->
                        <?php if (in_array('product', $post_types)): ?>
                            <input type="hidden" name="queryra_post_types[]" value="product">
                        <?php endif; ?>
                        <!-- Empty value to ensure callback is called even when no checkboxes selected -->
                        <input type="hidden" name="queryra_post_types[]" value="">

                        <div class="queryra-card">
                            <h2>
                                <span class="dashicons dashicons-media-document" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                                WordPress Content
                            </h2>
                            <p>Select which post types to sync with Queryra and include in AI search</p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">Post Types</th>
                                    <td>
                                        <!-- Posts always enabled -->
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox" checked disabled>
                                            <strong>Posts</strong>
                                            <span style="color: #646970; font-size: 13px; margin-left: 8px;">(always enabled)</span>
                                        </label>

                                        <!-- Pages optional -->
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox"
                                                   name="queryra_post_types[]"
                                                   value="page"
                                                   <?php checked(in_array('page', $post_types)); ?>>
                                            <strong>Pages</strong>
                                        </label>

                                        <?php
                                        // Custom post types registered on this site (recipes,
                                        // vehicles, portfolios, etc.). 'post' is always on,
                                        // 'page' has its own row above, and 'product' is
                                        // configured on the dedicated WooCommerce tab — so we
                                        // skip those three and list the rest dynamically.
                                        $cpt_skip = array('post', 'page', 'product');
                                        foreach ($available_post_types as $pt_name => $pt_object):
                                            if (in_array($pt_name, $cpt_skip, true)) {
                                                continue;
                                            }
                                            $pt_label = isset($pt_object->labels->name) ? $pt_object->labels->name : $pt_name;
                                        ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox"
                                                   name="queryra_post_types[]"
                                                   value="<?php echo esc_attr($pt_name); ?>"
                                                   <?php checked(in_array($pt_name, $post_types)); ?>>
                                            <strong><?php echo esc_html($pt_label); ?></strong>
                                            <span style="color: #646970; font-size: 13px; margin-left: 8px;">(custom post type)</span>
                                        </label>
                                        <?php endforeach; ?>

                                        <p class="description">
                                            <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                            Posts are always included. Check any other content type to include it in AI search. After enabling a new type, run <strong>Import All</strong> to send its existing content to Queryra.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        Auto Import
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="queryra_auto_sync"
                                                   value="1"
                                                   <?php checked($auto_sync, '1'); ?>>
                                            <strong>Automatically import content to Queryra when published or updated</strong>
                                        </label>
                                        <p class="description" style="margin-top: 8px;">
                                            <span class="dashicons dashicons-chart-line" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                            <strong>Search Boost:</strong> All posts/pages: 0.5 | Sticky posts: 1.0
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php submit_button('Save Settings'); ?>
                    </form>

                    <?php elseif ($active_tab === 'records'): ?>
                    <!-- Records Tab -->
                    <div class="queryra-card">
                        <h2>
                            <span class="dashicons dashicons-list-view" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                            Records in Queryra
                        </h2>
                        <p style="color: #646970;">Records are your WordPress content (posts, pages, products) imported to Queryra. AI uses these records to understand your content and deliver accurate search results.</p>

                        <table class="form-table" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
                            <?php if (!empty($api_key) && $stats): ?>
                                <!-- Queryra Records -->
                                <tr>
                                    <th scope="row" style="padding: 8px 0; width: 180px;">
                                        <span class="dashicons dashicons-cloud" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                        Imported to Queryra
                                    </th>
                                    <td style="padding: 8px 0;">
                                        <?php
                                        $total = isset($stats['total_records']) ? $stats['total_records'] : 0;
                                        $limit = isset($stats['record_limit']) ? $stats['record_limit'] : 0;
                                        $percentage = isset($stats['usage_percentage']) ? $stats['usage_percentage'] : 0;

                                        // Determine progress bar color based on usage
                                        $bar_color = '#00a32a'; // Green (< 70%)
                                        if ($percentage >= 90) {
                                            $bar_color = '#d63638'; // Red (>= 90%)
                                        } elseif ($percentage >= 70) {
                                            $bar_color = '#f0b849'; // Yellow (70-89%)
                                        }
                                        ?>
                                        <div style="margin-bottom: 8px;">
                                            <strong style="font-size: 18px;">
                                                <?php echo esc_html(number_format($total)); ?>
                                                <?php if ($limit > 0): ?>
                                                    / <?php echo esc_html(number_format($limit)); ?>
                                                    <span style="color: #646970; font-size: 14px;">(<?php echo esc_html($percentage); ?>%)</span>
                                                <?php else: ?>
                                                    <span style="color: #646970; font-size: 14px;">(No metering on this plan)</span>
                                                <?php endif; ?>
                                            </strong>
                                        </div>

                                        <?php if ($limit > 0): ?>
                                            <!-- Progress Bar -->
                                            <div style="background: #f0f0f1; border-radius: 4px; height: 12px; overflow: hidden; max-width: 300px; position: relative;">
                                                <div style="background: <?php echo esc_attr($bar_color); ?>; height: 100%; width: <?php echo esc_attr($percentage); ?>%; transition: width 0.3s ease, background-color 0.3s ease; border-radius: 4px;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="padding: 8px 0;">
                                        <span style="color: #f0b849;">
                                            <span class="dashicons dashicons-warning" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                            Connect your API key in Settings to view records
                                        </span>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php
                            // Get selected post types from settings
                            $selected_post_types = get_option('queryra_post_types', array('post'));
                            if (!is_array($selected_post_types)) {
                                $selected_post_types = array('post');
                            }

                            // Posts (always active)
                            $post_count = wp_count_posts('post');
                            $published_posts = isset($post_count->publish) ? $post_count->publish : 0;

                            // Pages (optional)
                            $page_count = wp_count_posts('page');
                            $published_pages = isset($page_count->publish) ? $page_count->publish : 0;
                            $pages_active = in_array('page', $selected_post_types);

                            // Products (optional, WooCommerce)
                            $published_products = 0;
                            $products_active = false;
                            if (class_exists('WooCommerce')) {
                                $product_count = wp_count_posts('product');
                                $published_products = isset($product_count->publish) ? $product_count->publish : 0;
                                $products_active = in_array('product', $selected_post_types);
                            }

                            // Calculate total active content (posts always + pages/products if enabled)
                            $active_count = $published_posts;
                            if ($pages_active) {
                                $active_count += $published_pages;
                            }
                            if ($products_active) {
                                $active_count += $published_products;
                            }

                            // Custom post types selected for sync (recipes,
                            // vehicles, etc.) — counted into the active total
                            // and the breakdown alongside posts/pages/products.
                            $cpt_skip = array('post', 'page', 'product');
                            $active_custom_types = array();
                            foreach ($selected_post_types as $pt_name) {
                                if (in_array($pt_name, $cpt_skip, true)) {
                                    continue;
                                }
                                $pt_obj = get_post_type_object($pt_name);
                                if (!$pt_obj) {
                                    continue; // type no longer registered
                                }
                                $cpt_counts = wp_count_posts($pt_name);
                                $cpt_published = isset($cpt_counts->publish) ? (int) $cpt_counts->publish : 0;
                                $active_custom_types[] = array(
                                    'label' => isset($pt_obj->labels->name) ? $pt_obj->labels->name : $pt_name,
                                    'count' => $cpt_published,
                                );
                                $active_count += $cpt_published;
                            }
                            ?>

                            <!-- WordPress Active Content -->
                            <tr>
                                <th scope="row" style="padding: 8px 0;">
                                    <span class="dashicons dashicons-wordpress" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                    Active in WordPress
                                </th>
                                <td style="padding: 8px 0;">
                                    <strong style="font-size: 18px;">
                                        <?php echo esc_html(number_format($active_count)); ?>
                                    </strong>
                                    <span style="color: #646970; font-size: 14px; margin-left: 5px;">
                                        (<?php
                                        $parts = array();
                                        if ($published_posts > 0) {
                                            $parts[] = number_format($published_posts) . ' post' . ($published_posts != 1 ? 's' : '');
                                        }
                                        if ($pages_active && $published_pages > 0) {
                                            $parts[] = number_format($published_pages) . ' page' . ($published_pages != 1 ? 's' : '');
                                        }
                                        if ($products_active && $published_products > 0) {
                                            $parts[] = number_format($published_products) . ' product' . ($published_products != 1 ? 's' : '');
                                        }
                                        foreach ($active_custom_types as $ct) {
                                            if ($ct['count'] > 0) {
                                                $parts[] = number_format($ct['count']) . ' ' . strtolower($ct['label']);
                                            }
                                        }
                                        echo esc_html(implode(', ', $parts));
                                        ?>)
                                    </span>
                                </td>
                            </tr>

                            <!-- Auto Import Status - Posts & Pages -->
                            <tr>
                                <th scope="row" style="padding: 8px 0; border-top: 1px solid #ddd; padding-top: 15px;">
                                    <span class="dashicons dashicons-media-document" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                    Auto Import (Posts & Pages)
                                </th>
                                <td style="padding: 8px 0; border-top: 1px solid #ddd; padding-top: 15px;">
                                    <strong style="font-size: 16px;">
                                        <?php echo $auto_sync === '1' ? 'Enabled' : 'Disabled'; ?>
                                    </strong>
                                    <span style="color: #646970; font-size: 14px; margin-left: 8px;">
                                        (<a href="?page=queryra-search&tab=content">Configure in Content</a>)
                                    </span>
                                </td>
                            </tr>

                            <!-- Auto Import Status - Products -->
                            <?php if (class_exists('WooCommerce')): ?>
                                <tr>
                                    <th scope="row" style="padding: 8px 0;">
                                        <span class="dashicons dashicons-cart" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                        Auto Import (Products)
                                    </th>
                                    <td style="padding: 8px 0;">
                                        <strong style="font-size: 16px;">
                                            <?php echo ($auto_sync === '1' && $products_active) ? 'Enabled' : 'Disabled'; ?>
                                        </strong>
                                        <span style="color: #646970; font-size: 14px; margin-left: 8px;">
                                            (<a href="?page=queryra-search&tab=woocommerce">Configure in WooCommerce</a>)
                                        </span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>

                        <div style="background: #f0f6fc; border: 1px solid #d0e4f7; border-radius: 5px; padding: 15px; margin: 20px 0;">
                            <h3 style="margin-top: 0; color: #2271b1;">
                                <span class="dashicons dashicons-upload" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                Import Content to Queryra
                            </h3>
                            <p style="color: #646970; margin: 10px 0;">Send all published posts and pages to Queryra for indexing</p>
                            <button type="button" id="queryra-sync-all" class="button button-primary" style="margin-top: 10px;">
                                <span class="dashicons dashicons-upload" style="font-size: 16px; width: 16px; height: 16px; margin-top: 4px;"></span>
                                Import All to Queryra
                            </button>
                            <p class="description" style="margin-top: 8px;">
                                <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                Content is imported in batches. Keep this tab open during import. If interrupted, you can safely re-run.
                            </p>
                            <div id="queryra-sync-status" style="margin-top: 10px;"></div>
                        </div>

                        <p style="color: #646970; margin-top: 20px;">View all indexed content in Queryra Dashboard:</p>
                        <ul style="color: #646970; margin-left: 20px;">
                            <li>Browse all imported posts, pages, and products</li>
                            <li>View record details (title, content, metadata)</li>
                            <li>Delete individual records</li>
                            <li>Filter and search your indexed content</li>
                        </ul>

                        <p style="margin-top: 20px;">
                            <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/records')); ?>" target="_blank" rel="noopener" class="button button-secondary">
                                Open Dashboard
                                <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                            </a>
                        </p>
                    </div>

                    <?php elseif ($active_tab === 'sync'): ?>
                    <!-- Sync Tab -->
                    <div class="queryra-card">
                        <h2>
                            <span class="dashicons dashicons-update" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                            Sync
                        </h2>

                        <p style="color: #646970; line-height: 1.6;">
                            Sync prepares your <strong>imported records</strong> (posts, pages, products in Queryra) for AI search.
                            Only content that has been imported to Records can be synced and made searchable.
                        </p>

                        <?php if (!empty($api_key) && $stats): ?>
                            <!-- Sync Status -->
                            <?php if (isset($stats['synced_records']) && isset($stats['unsynced_records'])): ?>
                            <?php
                                // Calculate sync progress
                                $synced = $stats['synced_records'];
                                $unsynced = $stats['unsynced_records'];
                                $total_sync = $synced + $unsynced;
                                $sync_percentage = $total_sync > 0 ? round(($synced / $total_sync) * 100) : 100;

                                // Determine sync progress bar color
                                $sync_bar_color = '#00a32a'; // Green (complete or nearly complete)
                                if ($sync_percentage < 50) {
                                    $sync_bar_color = '#d63638'; // Red (< 50%)
                                } elseif ($sync_percentage < 100) {
                                    $sync_bar_color = '#f0b849'; // Yellow (50-99%)
                                }
                            ?>

                            <table class="form-table" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
                                <!-- Sync Progress Overview -->
                                <tr>
                                    <th scope="row" style="padding: 8px 0; width: 180px;">
                                        <span class="dashicons dashicons-update" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                        Sync Progress
                                    </th>
                                    <td style="padding: 8px 0;">
                                        <div style="margin-bottom: 8px;">
                                            <strong style="font-size: 18px;">
                                                <?php echo esc_html(number_format($synced)); ?> / <?php echo esc_html(number_format($total_sync)); ?>
                                                <span style="color: #646970; font-size: 14px;">(<?php echo esc_html($sync_percentage); ?>%)</span>
                                            </strong>
                                        </div>
                                        <!-- Sync Progress Bar -->
                                        <div style="background: #f0f0f1; border-radius: 4px; height: 12px; overflow: hidden; max-width: 300px; position: relative;">
                                            <div style="background: <?php echo esc_attr($sync_bar_color); ?>; height: 100%; width: <?php echo esc_attr($sync_percentage); ?>%; transition: width 0.3s ease, background-color 0.3s ease; border-radius: 4px;"></div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" style="padding: 8px 0; width: 180px;">
                                        <span class="dashicons dashicons-yes-alt" style="font-size: 18px; width: 18px; height: 18px; color: #46b450;"></span>
                                        Synced Records
                                    </th>
                                    <td style="padding: 8px 0;">
                                        <strong style="font-size: 18px; color: #46b450;">
                                            <?php echo esc_html(number_format($stats['synced_records'])); ?>
                                        </strong>
                                        <span style="color: #646970; font-size: 14px; margin-left: 8px;">Ready for AI search</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="padding: 8px 0;">
                                        <span class="dashicons dashicons-clock" style="font-size: 18px; width: 18px; height: 18px; color: #f0b849;"></span>
                                        Unsynced Records
                                    </th>
                                    <td style="padding: 8px 0;">
                                        <strong style="font-size: 18px; color: #f0b849;">
                                            <?php echo esc_html(number_format($stats['unsynced_records'])); ?>
                                        </strong>
                                        <span style="color: #646970; font-size: 14px; margin-left: 8px;">Waiting to be processed</span>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="background: #fff3cd; border-left: 4px solid #f0b849; padding: 15px; margin: 20px 0;">
                                <p style="margin: 0;">
                                    <span class="dashicons dashicons-warning" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    <strong>Connect your API key in Settings to view sync status</strong>
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Important Info Box -->
                        <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; border-radius: 3px;">
                            <p style="margin: 0 0 10px 0;">
                                <span class="dashicons dashicons-info" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                <strong style="font-size: 15px;">Sync is a resource-intensive process</strong>
                            </p>
                            <p style="margin: 0 0 10px 0; color: #2c3338; line-height: 1.6;">
                                Sync analyzes and processes your content for AI understanding. This requires significant computing resources,
                                so it's <strong>limited to once per month</strong> per account.
                            </p>
                            <p style="margin: 0; color: #646970; font-size: 14px;">
                                Next sync available: <strong>Monthly (check Dashboard for exact date)</strong>
                            </p>
                        </div>

                        <div style="background: #f0f6fc; border: 1px solid #d0e4f7; border-radius: 5px; padding: 15px; margin: 20px 0;">
                            <h3 style="margin-top: 0; color: #2271b1;">
                                <span class="dashicons dashicons-update" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                Run Sync
                            </h3>
                            <p style="color: #646970; margin: 10px 0; line-height: 1.6;">
                                Sync happens in Queryra Dashboard where your records are processed and prepared for AI search.
                                View sync history, logs, and trigger sync manually.
                            </p>
                            <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/sync')); ?>" target="_blank" rel="noopener" class="button button-secondary" style="margin-top: 10px;">
                                Open Dashboard
                                <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                            </a>
                        </div>
                    </div>

                    <?php elseif ($active_tab === 'search-history'): ?>
                    <!-- Search History Tab -->
                    <?php
                    // Fetch search stats from API (cached for 10 minutes)
                    $search_stats = null;
                    if (!empty($api_key)) {
                        $cache_key = 'queryra_search_stats';
                        $search_stats = get_transient($cache_key);

                        if ($search_stats === false) {
                            $search_stats = $this->api->get_search_stats('30');
                            if (!is_wp_error($search_stats)) {
                                set_transient($cache_key, $search_stats, 10 * MINUTE_IN_SECONDS);
                            } else {
                                $search_stats = null;
                            }
                        }
                    }
                    ?>
                    <div class="queryra-card">
                        <h2>
                            <span class="dashicons dashicons-chart-line" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                            Search Analytics
                        </h2>

                        <?php if (!$api_key): ?>
                            <div style="background: #fff3cd; border-left: 4px solid #f0b849; padding: 15px; margin: 20px 0;">
                                <p style="margin: 0;">
                                    <span class="dashicons dashicons-warning"></span>
                                    <strong>Connect your API key in Settings to view search analytics</strong>
                                </p>
                            </div>
                        <?php elseif (!$search_stats): ?>
                            <div style="background: #fff3cd; border-left: 4px solid #f0b849; padding: 15px; margin: 20px 0;">
                                <p style="margin: 0;">
                                    <span class="dashicons dashicons-warning"></span>
                                    <strong>Unable to load search analytics. Check your API connection.</strong>
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Total Searches Card -->
                            <div style="background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
                                <span style="font-size: 36px; font-weight: 600; color: #1d2327;">
                                    <?php echo esc_html(number_format($search_stats['total_searches'])); ?>
                                </span>
                                <p style="margin: 5px 0 0 0; color: #646970;">
                                    searches in the last 30 days
                                </p>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <!-- Top 10 Searches -->
                                <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
                                    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                                        <span class="dashicons dashicons-star-filled" style="color: #f0b849;"></span>
                                        Top 10 Searches
                                    </h3>
                                    <?php if (empty($search_stats['top_queries'])): ?>
                                        <p style="color: #646970;">No searches yet.</p>
                                    <?php else: ?>
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <?php foreach (array_slice($search_stats['top_queries'], 0, 10) as $i => $query): ?>
                                                <tr style="border-bottom: 1px solid #f0f0f1;">
                                                    <td style="padding: 8px 0; color: #646970; width: 30px;"><?php echo esc_html($i + 1); ?>.</td>
                                                    <td style="padding: 8px 0;"><?php echo esc_html($query['query']); ?></td>
                                                    <td style="padding: 8px 0; text-align: right; color: #646970;">
                                                        <strong><?php echo esc_html($query['count']); ?></strong>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    <?php endif; ?>
                                </div>

                                <!-- Zero Results (Opportunity!) -->
                                <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
                                    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                                        <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                        Zero Results (Opportunity!)
                                    </h3>
                                    <p style="color: #646970; font-size: 13px; margin-bottom: 15px;">
                                        Customers searched for these but found nothing.
                                    </p>
                                    <?php if (empty($search_stats['zero_results_queries'])): ?>
                                        <p style="color: #00a32a;">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            All searches returned results.
                                        </p>
                                    <?php else: ?>
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <?php foreach (array_slice($search_stats['zero_results_queries'], 0, 10) as $query): ?>
                                                <tr style="border-bottom: 1px solid #f0f0f1;">
                                                    <td style="padding: 8px 0;"><?php echo esc_html($query['query']); ?></td>
                                                    <td style="padding: 8px 0; text-align: right; color: #d63638;">
                                                        <strong><?php echo esc_html($query['count']); ?></strong>×
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <p style="margin-top: 20px;">
                            <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/searches')); ?>" target="_blank" rel="noopener" class="button button-secondary">
                                View Full Analytics in Dashboard
                                <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                            </a>
                        </p>
                    </div>

                    <?php elseif ($active_tab === 'woocommerce'): ?>
                    <!-- WooCommerce Tab -->
                    <?php if (class_exists('WooCommerce')): ?>
                        <!-- WooCommerce Active -->
                        <form method="post" action="options.php">
                            <?php settings_fields('queryra_settings'); ?>
                            <!-- Preserve other settings -->
                            <input type="hidden" name="queryra_api_key" value="<?php echo esc_attr($api_key); ?>">
                            <input type="hidden" name="queryra_api_url" value="<?php echo esc_attr($api_url); ?>">
                            <input type="hidden" name="queryra_auto_sync" value="<?php echo esc_attr($auto_sync); ?>">
                            <input type="hidden" name="queryra_ai_search" value="<?php echo esc_attr($ai_search); ?>">
                        <input type="hidden" name="queryra_output_schema" value="<?php echo esc_attr($output_schema); ?>">
                        <input type="hidden" name="queryra_generate_llms_txt" value="<?php echo esc_attr($generate_llms_txt); ?>">
                        <input type="hidden" name="queryra_generate_llms_full_txt" value="<?php echo esc_attr($generate_llms_full_txt); ?>">
                            <!-- Preserve non-product post types from Content tab -->
                            <?php foreach ($post_types as $pt): ?>
                                <?php if ($pt !== 'product'): ?>
                                    <input type="hidden" name="queryra_post_types[]" value="<?php echo esc_attr($pt); ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <div class="queryra-card">
                                <h2>
                                    <span class="dashicons dashicons-cart" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                                    WooCommerce Products
                                </h2>

                                <!-- WooCommerce Detected -->
                                <div style="background: #e7f7ed; border-left: 4px solid #00a32a; padding: 15px; margin: 20px 0;">
                                    <p style="margin: 0;">
                                        <span class="dashicons dashicons-yes-alt" style="font-size: 20px; width: 20px; height: 20px; margin-right: 5px; color: #00a32a;"></span>
                                        <strong>WooCommerce Detected</strong>
                                    </p>
                                    <p style="margin: 10px 0 0 0; font-size: 13px;">
                                        WooCommerce plugin is active. Enable product search below.
                                    </p>
                                </div>

                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            Include Products
                                        </th>
                                        <td>
                                            <label>
                                                <input type="checkbox"
                                                       name="queryra_post_types[]"
                                                       value="product"
                                                       <?php checked(in_array('product', $post_types)); ?>>
                                                <strong>Enable Product Search</strong>
                                            </label>
                                            <p class="description" style="margin-top: 8px;">
                                                Include WooCommerce products in AI search results and sync them to Queryra.
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <!-- What's Included -->
                                <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
                                    <h4 style="margin: 0 0 10px 0; font-size: 14px;">
                                        <span class="dashicons dashicons-info" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                        What's Automatically Included for Products
                                    </h4>
                                    <p style="margin: 0 0 10px 0; font-size: 13px;">
                                        When you enable product search, Queryra automatically includes these fields in AI embeddings:
                                    </p>
                                    <ul style="margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                                        <li><strong>Product Title & Description</strong> - Full product content</li>
                                        <li><strong>Short Description</strong> - Product summary</li>
                                        <li><strong>SKU</strong> - Product code for exact matches</li>
                                        <li><strong>Price & Stock</strong> - Current pricing and availability</li>
                                        <li><strong>Product Categories & Tags</strong> - Taxonomy classification</li>
                                        <li><strong>Attributes</strong> - Color, Size, Material, and custom attributes</li>
                                        <li><strong>Featured Products</strong> - Boosted ranking (like sticky posts)</li>
                                    </ul>
                                </div>

                            </div>

                            <?php submit_button('Save Settings'); ?>
                        </form>
                    <?php else: ?>
                        <!-- WooCommerce Not Active -->
                        <div class="queryra-card">
                            <h2>
                                <span class="dashicons dashicons-cart" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                                WooCommerce Products
                            </h2>

                            <!-- WooCommerce Not Detected -->
                            <div style="background: #fff3cd; border-left: 4px solid #f0b849; padding: 15px; margin: 20px 0;">
                                <p style="margin: 0;">
                                    <span class="dashicons dashicons-warning" style="font-size: 20px; width: 20px; height: 20px; margin-right: 5px; color: #f0b849;"></span>
                                    <strong>WooCommerce Not Detected</strong>
                                </p>
                                <p style="margin: 10px 0 0 0; font-size: 13px;">
                                    WooCommerce plugin is not active. Install and activate WooCommerce to enable product search.
                                </p>
                            </div>

                            <!-- Information about WooCommerce integration -->
                            <div style="background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 3px;">
                                <h3 style="margin-top: 0;">About WooCommerce Integration</h3>
                                <p>When you install and activate WooCommerce, you'll be able to:</p>
                                <ul style="line-height: 1.8;">
                                    <li>Include products in AI search results</li>
                                    <li>Automatically sync product data (title, description, SKU, categories, tags, attributes)</li>
                                    <li>Boost featured products in search rankings</li>
                                    <li>Enable customers to find products using natural language queries</li>
                                </ul>
                                <p style="margin-bottom: 0;">
                                    <a href="https://wordpress.org/plugins/woocommerce/" target="_blank" rel="noopener" class="button button-secondary">
                                        Learn More About WooCommerce
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php elseif ($active_tab === 'cache'): ?>
                    <!-- Cache Tab -->
                    <?php $cache_duration = get_option('queryra_cache_duration', 86400); ?>
                    <form method="post" action="options.php">
                        <?php settings_fields('queryra_settings'); ?>
                        <!-- Preserve other settings -->
                        <input type="hidden" name="queryra_api_key" value="<?php echo esc_attr($api_key); ?>">
                        <input type="hidden" name="queryra_api_url" value="<?php echo esc_attr($api_url); ?>">
                        <input type="hidden" name="queryra_auto_sync" value="<?php echo esc_attr($auto_sync); ?>">
                        <input type="hidden" name="queryra_ai_search" value="<?php echo esc_attr($ai_search); ?>">
                        <input type="hidden" name="queryra_output_schema" value="<?php echo esc_attr($output_schema); ?>">
                        <input type="hidden" name="queryra_generate_llms_txt" value="<?php echo esc_attr($generate_llms_txt); ?>">
                        <input type="hidden" name="queryra_generate_llms_full_txt" value="<?php echo esc_attr($generate_llms_full_txt); ?>">
                        <?php foreach ($post_types as $pt): ?>
                            <input type="hidden" name="queryra_post_types[]" value="<?php echo esc_attr($pt); ?>">
                        <?php endforeach; ?>

                        <div class="queryra-card">
                            <h2>
                                <span class="dashicons dashicons-performance" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                                Search Cache
                            </h2>

                            <div class="queryra-info-box" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0;">
                                <p style="margin: 0 0 12px 0;">
                                    <strong>How cache reduces API calls:</strong>
                                </p>
                                <ul style="margin: 5px 0 5px 20px; padding: 0; list-style: disc;">
                                    <li><strong>Without cache:</strong> Every search = 1 API call</li>
                                    <li><strong>With cache:</strong> Same search repeated = 0 API calls</li>
                                    <li><strong>Result:</strong> 10x-100x fewer API calls, faster responses</li>
                                </ul>
                                <p style="margin: 12px 0 0 0; font-size: 13px; color: #646970;">
                                    Cache is managed automatically by WordPress using Transients API.
                                    <a href="https://developer.wordpress.org/apis/transients/" target="_blank" rel="noopener" style="color: #2271b1;">Learn about WordPress Transients →</a>
                                </p>
                            </div>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="queryra_cache_duration">Cache Duration</label>
                                    </th>
                                    <td>
                                        <select name="queryra_cache_duration" id="queryra_cache_duration" style="min-width: 200px;">
                                            <option value="0" <?php selected($cache_duration, 0); ?>>Disabled (no cache)</option>
                                            <option value="60" <?php selected($cache_duration, 60); ?>>1 minute</option>
                                            <option value="600" <?php selected($cache_duration, 600); ?>>10 minutes</option>
                                            <option value="3600" <?php selected($cache_duration, 3600); ?>>1 hour</option>
                                            <option value="86400" <?php selected($cache_duration, 86400); ?>>1 day (recommended)</option>
                                            <option value="604800" <?php selected($cache_duration, 604800); ?>>1 week</option>
                                            <option value="-1" <?php selected($cache_duration, -1); ?>>Forever (until cleared)</option>
                                        </select>
                                        <p class="description" style="margin-top: 8px;">
                                            How long to cache search results. Longer = fewer API calls, but slower updates when content changes.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button('Save Settings'); ?>

                            <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

                            <h3 style="margin-top: 0;">
                                <span class="dashicons dashicons-trash" style="font-size: 20px; width: 20px; height: 20px; margin-right: 8px;"></span>
                                Clear Cache
                            </h3>
                            <p style="color: #646970; margin-bottom: 15px;">
                                Clears all cached search results. Next search will fetch fresh data from Queryra API.
                            </p>
                            <button type="button" id="queryra-clear-cache" class="button button-secondary">
                                <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px; margin-top: 4px;"></span>
                                Clear All Search Cache
                            </button>
                            <span id="queryra-cache-status" style="margin-left: 10px;"></span>
                        </div>
                    </form>

                    <?php elseif ($active_tab === 'support'): ?>
                    <!-- Support Tab -->
                    <div class="queryra-card">
                        <h2>
                            <span class="dashicons dashicons-sos" style="font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                            Help & Support
                        </h2>
                        <p>Quick links to help you get the most out of Queryra</p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <span class="dashicons dashicons-dashboard" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    Dashboard
                                </th>
                                <td>
                                    <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard')); ?>" target="_blank" rel="noopener" class="button button-secondary">
                                        Open Dashboard
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                    <p class="description">Manage your synced records, view usage stats, and configure settings</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="dashicons dashicons-book" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    Resources
                                </th>
                                <td>
                                    <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/docs/wordpress-integration')); ?>" target="_blank" rel="noopener" class="button button-secondary">
                                        View Resources
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                    <p class="description">Getting started guides, plugin compatibility matrix (translation plugins, WooCommerce extensions), FAQs, and troubleshooting</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="dashicons dashicons-format-chat" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    Community Help
                                </th>
                                <td>
                                    <a href="https://wordpress.org/support/plugin/queryra-ai-search/" target="_blank" rel="noopener" class="button button-secondary">
                                        Visit Forum
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                    <p class="description">Independent community support on WordPress.org</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="dashicons dashicons-groups" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    Clubs
                                </th>
                                <td>
                                    <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/club')); ?>" target="_blank" rel="noopener" class="button button-secondary">
                                        Join a Club
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                    <p class="description">
                                        <strong>Sandbox:</strong> come in — try Queryra free during your testing period.
                                        All we ask in return is a link to queryra.com from your site.
                                        Backlinks matter a lot to us at this stage; it's a small exchange, and we genuinely appreciate it.
                                        <br><br>
                                        <strong>Partner clubs:</strong> prefer to earn instead? Join one of our partner clubs — refer users, earn commissions, and keep Queryra free for yourself.
                                        New clubs are added regularly; pick the one that fits.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="dashicons dashicons-cloud-upload" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    Need more records or searches?
                                </th>
                                <td>
                                    <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard')); ?>" target="_blank" rel="noopener" class="button button-secondary">
                                        Open Dashboard
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                    <p class="description">If you need more space for testing — more products or posts than your current limit — feel free to send us a request from the dashboard.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="dashicons dashicons-admin-generic" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    Instance ID
                                </th>
                                <td>
                                    <code style="padding: 4px 8px; background: #f0f0f0; user-select: all;"><?php echo esc_html(get_option('queryra_instance_id', 'N/A')); ?></code>
                                    <p class="description">Share this with support if requested</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php endif; ?>

                </div>

                <!-- Sidebar -->
                <div class="queryra-sidebar">
                    <?php if ($api_error): ?>
                        <!-- API Error -->
                        <div class="queryra-card" style="border-left: 4px solid #dc3232;">
                            <h3>
                                <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                                API Status
                            </h3>
                            <p style="margin: 10px 0; color: #646970;">
                                <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                                API unavailable
                            </p>
                            <p style="margin: 10px 0; font-size: 13px; color: #646970;">
                                Check your API key or try again later.
                            </p>
                        </div>
                    <?php elseif ($stats): ?>
                        <!-- Connection Status -->
                        <div class="queryra-card">
                            <h3>
                                <span class="dashicons dashicons-admin-network"></span>
                                Connection
                            </h3>

                            <p style="margin: 10px 0; color: #46b450;">
                                <span class="dashicons dashicons-yes-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                <strong>Connected to Queryra</strong>
                            </p>
                            <p style="margin: 10px 0; font-size: 14px; color: #646970;">
                                <?php echo esc_html(number_format(isset($stats['synced_records']) ? (int) $stats['synced_records'] : 0)); ?> records synced | Plan: <?php echo esc_html(ucfirst(isset($stats['plan']) ? $stats['plan'] : 'unknown')); ?>
                            </p>
                        </div>

                        <!-- AI Search Status -->
                        <div class="queryra-card">
                            <h3>
                                <span class="dashicons dashicons-search"></span>
                                AI Search
                            </h3>

                            <?php
                            // Check all conditions for AI search.
                            // isset-guarded: the stats/status payload shape
                            // depends on the API version and plan — a missing
                            // key must not throw PHP 8 warnings into the page.
                            $plugin_enabled = $ai_search === '1';
                            $has_synced = !empty($stats['synced_records']);
                            $is_free_plan = isset($stats['plan']) && $stats['plan'] === 'free';
                            $window_open = true; // Default for paid plans
                            if ($is_free_plan && $status) {
                                $window_open = !empty($status['available']);
                            }

                            $can_search = $plugin_enabled && $has_synced && $window_open;
                            ?>

                            <?php if ($can_search): ?>
                                <!-- Everything OK -->
                                <div style="padding: 10px; background: #e7f5e7; border-left: 3px solid #46b450; border-radius: 3px; margin: 10px 0;">
                                    <p style="margin: 0 0 5px 0; color: #46b450;">
                                        <span class="dashicons dashicons-yes-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                        <strong>Plugin Configured Correctly</strong>
                                    </p>
                                    <p style="margin: 0; font-size: 13px; color: #646970;">
                                        AI search is active and ready to use
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- Issues found -->
                                <div style="padding: 10px; background: #fff3cd; border-left: 3px solid #f0b849; border-radius: 3px; margin: 10px 0;">
                                    <p style="margin: 0 0 8px 0; color: #f0b849;">
                                        <span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                        <strong>AI Search Unavailable</strong>
                                    </p>

                                    <?php if (!$plugin_enabled): ?>
                                        <p style="margin: 0 0 5px 0; font-size: 13px; color: #646970;">
                                            • Plugin is disabled. <a href="?page=queryra-search&tab=settings">Enable in Settings</a>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!$has_synced): ?>
                                        <p style="margin: 0 0 5px 0; font-size: 13px; color: #646970;">
                                            • No synced records. <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/sync')); ?>" target="_blank" rel="noopener">Sync in Dashboard</a>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($plugin_enabled && $has_synced && !$window_open): ?>
                                        <p style="margin: 0 0 5px 0; font-size: 13px; color: #646970;">
                                            • Search window closed (FREE plan). Opens in <?php echo esc_html(isset($status['minutes_until_open']) ? $status['minutes_until_open'] : '—'); ?> min
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Status Details -->
                            <div style="margin: 10px 0; font-size: 13px;">
                                <p style="margin: 0 0 5px 0; color: #646970;">
                                    <span class="dashicons dashicons-<?php echo $plugin_enabled ? 'yes' : 'no'; ?>" style="font-size: 14px; width: 14px; height: 14px; color: <?php echo $plugin_enabled ? '#46b450' : '#d63638'; ?>;"></span>
                                    Plugin: <?php echo $plugin_enabled ? 'Enabled' : 'Disabled'; ?>
                                </p>
                                <p style="margin: 0 0 5px 0; color: #646970;">
                                    <span class="dashicons dashicons-<?php echo $has_synced ? 'yes' : 'no'; ?>" style="font-size: 14px; width: 14px; height: 14px; color: <?php echo $has_synced ? '#46b450' : '#d63638'; ?>;"></span>
                                    Synced: <?php echo esc_html(number_format(isset($stats['synced_records']) ? (int) $stats['synced_records'] : 0)); ?> records
                                </p>
                                <?php if ($is_free_plan): ?>
                                    <p style="margin: 0; color: #646970;">
                                        <span class="dashicons dashicons-<?php echo $window_open ? 'yes' : 'no'; ?>" style="font-size: 14px; width: 14px; height: 14px; color: <?php echo $window_open ? '#46b450' : '#d63638'; ?>;"></span>
                                        Window: <?php echo $window_open ? 'Open' : 'Closed'; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="queryra-card">
                        <h3>Support</h3>
                        <p>Need help? Contact us:</p>
                        <p><a href="mailto:contact@queryra.com">contact@queryra.com</a></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Clear all search cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('queryra_cache', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        // Invalidate every cached search by bumping the version salt that
        // is part of each cache key. This works regardless of where
        // transients actually live — on Redis/Memcached hosts they are not
        // in wp_options, so the SQL cleanup below alone would delete zero
        // rows while reporting success.
        $cache_version = (int) get_option('queryra_cache_version', 1);
        update_option('queryra_cache_version', $cache_version + 1);

        global $wpdb;

        // Delete orphaned transient rows from the DB (sites without an
        // external object cache).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup
        $deleted = $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_queryra_search_%'
            OR option_name LIKE '_transient_timeout_queryra_search_%'
        ");

        if ($deleted !== false) {
            wp_send_json_success(array(
                'message' => 'Cache cleared successfully',
                'deleted' => $deleted
            ));
        } else {
            wp_send_json_error('Failed to clear cache');
        }
    }

    /**
     * AJAX: Persist that the user has dismissed the "tell visitors about AI search" tip.
     * Shared option between Settings tab and wizard step 4.
     */
    public function ajax_dismiss_ux_tip() {
        check_ajax_referer('queryra_dismiss_ux_tip');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
            return;
        }
        update_option('queryra_ux_tip_dismissed', '1');
        wp_send_json_success();
    }
}
