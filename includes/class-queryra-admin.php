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

        // Filter to only valid values and remove empty strings
        $sanitized = array_map('sanitize_text_field', $value);
        $sanitized = array_filter($sanitized, function($item) {
            return !empty($item);
        });

        // Add page if selected
        if (in_array('page', $sanitized)) {
            $result[] = 'page';
        }

        // Add product if selected
        if (in_array('product', $sanitized)) {
            $result[] = 'product';
        }

        return $result;
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
    public function render_settings_page() {
        // Get current settings
        $api_key = get_option('queryra_api_key', '');
        $api_url = get_option('queryra_api_url', 'https://queryra.com');
        $auto_sync = get_option('queryra_auto_sync', '1');
        $ai_search = get_option('queryra_ai_search', '0'); // Disabled by default
        $post_types = get_option('queryra_post_types', array('post', 'page'));

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

            // Get stats (records, limits, plan) - FRESH from API
            $stats_response = $api->get_stats();
            if (!is_wp_error($stats_response)) {
                $stats = $stats_response;
                // Save to DB for search integration to use
                update_option('queryra_cached_stats', $stats, false);
            } else {
                $api_error = true;
                delete_option('queryra_cached_stats');
            }

            // Get status (search window for FREE plan) - FRESH from API
            $status_response = $api->get_status();
            if (!is_wp_error($status_response)) {
                $status = $status_response;
                // Convert UTC time to WordPress timezone for display
                $status = $this->convert_status_to_wp_timezone($status);
                // Save to DB for search integration to use
                update_option('queryra_cached_status', $status, false);
            } else {
                $api_error = true;
                delete_option('queryra_cached_status');
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
                                               class="regular-text"
                                               placeholder="sk_live_xxxxxxxxxxxxx">
                                        <p class="description">Your Queryra API key</p>
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
                                    </td>
                                </tr>
                            </table>

                            <p style="margin-top: 20px;">
                                <a href="https://queryra.com/dashboard/api-keys" target="_blank" class="button button-secondary">
                                    Open Dashboard
                                    <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                </a>
                            </p>
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

                                        <p class="description">
                                            <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                            Posts are always included. Check Pages to also include them in search.
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
                                                    <span style="color: #646970; font-size: 14px;">(Unlimited)</span>
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
                            <a href="https://queryra.com/dashboard/records" target="_blank" class="button button-secondary">
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
                            <a href="https://queryra.com/dashboard/sync" target="_blank" class="button button-secondary" style="margin-top: 10px;">
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
                            <a href="https://queryra.com/dashboard/searches" target="_blank" class="button button-secondary">
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
                                    <a href="https://wordpress.org/plugins/woocommerce/" target="_blank" class="button button-secondary">
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
                                    <a href="https://developer.wordpress.org/apis/transients/" target="_blank" style="color: #2271b1;">Learn about WordPress Transients →</a>
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
                                    <a href="https://queryra.com/dashboard" target="_blank" class="button button-secondary">
                                        Open Dashboard
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                    <p class="description">Manage your synced records, view usage stats, and configure settings</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="dashicons dashicons-book" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    Documentation
                                </th>
                                <td>
                                    <a href="https://queryra.com/docs/wordpress-integration" target="_blank" class="button button-secondary">
                                        View Docs
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                    <p class="description">Getting started guides, FAQs, and troubleshooting tips</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="dashicons dashicons-format-chat" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    Community Help
                                </th>
                                <td>
                                    <a href="https://wordpress.org/support/plugin/queryra-ai-search/" target="_blank" class="button button-secondary">
                                        Visit Forum
                                        <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px;"></span>
                                    </a>
                                    <p class="description">Independent community support on WordPress.org</p>
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
                                <?php echo esc_html(number_format($stats['synced_records'])); ?> records synced | Plan: <?php echo esc_html(ucfirst($stats['plan'])); ?>
                            </p>
                        </div>

                        <!-- AI Search Status -->
                        <div class="queryra-card">
                            <h3>
                                <span class="dashicons dashicons-search"></span>
                                AI Search
                            </h3>

                            <?php
                            // Check all conditions for AI search
                            $plugin_enabled = $ai_search === '1';
                            $has_synced = $stats['synced_records'] > 0;
                            $window_open = true; // Default for paid plans
                            if ($stats['plan'] === 'free' && $status) {
                                $window_open = $status['available'];
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
                                            • No synced records. <a href="https://queryra.com/dashboard/sync" target="_blank">Sync in Dashboard</a>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($plugin_enabled && $has_synced && !$window_open): ?>
                                        <p style="margin: 0 0 5px 0; font-size: 13px; color: #646970;">
                                            • Search window closed (FREE plan). Opens in <?php echo esc_html($status['minutes_until_open']); ?> min
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
                                    Synced: <?php echo esc_html(number_format($stats['synced_records'])); ?> records
                                </p>
                                <?php if ($stats['plan'] === 'free'): ?>
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

        global $wpdb;

        // Delete all Queryra search transients
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
}
