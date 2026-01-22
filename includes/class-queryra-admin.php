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
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
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
        register_setting('queryra_settings', 'queryra_api_key');
        register_setting('queryra_settings', 'queryra_api_url');
        register_setting('queryra_settings', 'queryra_auto_sync');
        register_setting('queryra_settings', 'queryra_post_types');
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_queryra-search') {
            return;
        }

        wp_enqueue_style('queryra-admin', QUERYRA_PLUGIN_URL . 'assets/admin.css', array(), QUERYRA_VERSION);
        wp_enqueue_script('queryra-admin', QUERYRA_PLUGIN_URL . 'assets/admin.js', array('jquery'), QUERYRA_VERSION, true);

        wp_localize_script('queryra-admin', 'queryraData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('queryra_sync'),
            'hasApiKey' => !empty(get_option('queryra_api_key'))
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Get current settings
        $api_key = get_option('queryra_api_key', '');
        $api_url = get_option('queryra_api_url', 'https://queryra.com');
        $auto_sync = get_option('queryra_auto_sync', '1');
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
        $stats = null;
        $status = null;
        $api_error = false;

        if (!empty($api_key)) {
            $api = new Queryra_API();

            // Get stats (records, limits, plan)
            $stats_response = $api->get_stats();
            if (!is_wp_error($stats_response)) {
                $stats = $stats_response;
            } else {
                $api_error = true;
            }

            // Get status (search window for FREE plan)
            $status_response = $api->get_status();
            if (!is_wp_error($status_response)) {
                $status = $status_response;
            } else {
                $api_error = true;
            }
        }

        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-search"></span>
                Queryra Search Settings
            </h1>

            <div class="queryra-container">
                <!-- Settings Form -->
                <div class="queryra-main">
                    <form method="post" action="options.php">
                        <?php settings_fields('queryra_settings'); ?>

                        <!-- API Settings -->
                        <div class="queryra-card">
                            <h2>API Configuration</h2>
                            <p>Get your API key from <a href="https://queryra.com/dashboard" target="_blank">Queryra Dashboard</a></p>

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
                                <tr>
                                    <th scope="row">
                                        <label for="queryra_api_url">API URL</label>
                                    </th>
                                    <td>
                                        <input type="url"
                                               id="queryra_api_url"
                                               name="queryra_api_url"
                                               value="<?php echo esc_attr($api_url); ?>"
                                               class="regular-text">
                                        <p class="description">Default: https://queryra.com</p>
                                    </td>
                                </tr>
                            </table>

                            <p>
                                <button type="button" id="queryra-test-connection" class="button button-secondary">
                                    Test Connection
                                </button>
                                <span id="queryra-connection-status"></span>
                            </p>
                        </div>

                        <!-- Send Settings -->
                        <div class="queryra-card">
                            <h2>Send Settings</h2>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">Auto-Send</th>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="queryra_auto_sync"
                                                   value="1"
                                                   <?php checked($auto_sync, '1'); ?>>
                                            Automatically send posts to Queryra when published or updated
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Post Types</th>
                                    <td>
                                        <?php foreach ($available_post_types as $post_type): ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox"
                                                       name="queryra_post_types[]"
                                                       value="<?php echo esc_attr($post_type->name); ?>"
                                                       <?php checked(in_array($post_type->name, $post_types)); ?>>
                                                <?php echo esc_html($post_type->label); ?>
                                            </label>
                                        <?php endforeach; ?>
                                        <p class="description">Select which post types to sync with Queryra</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php submit_button(); ?>
                    </form>

                    <!-- Send to Queryra -->
                    <div class="queryra-card">
                        <h2>Send to Queryra</h2>
                        <p>Send all published posts and pages to Queryra.</p>

                        <!-- Info Box -->
                        <div class="queryra-info-box" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 12px; margin: 15px 0;">
                            <p style="margin: 0 0 8px 0;">
                                <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                <strong>How it works:</strong>
                            </p>
                            <ol style="margin: 5px 0 5px 20px; padding: 0;">
                                <li>Posts are sent to Queryra (happens here)</li>
                                <li>Generate embeddings in <a href="https://queryra.com/dashboard/sync" target="_blank">Queryra dashboard</a></li>
                                <li>Records become searchable</li>
                            </ol>
                        </div>

                        <!-- Tips Box -->
                        <div class="queryra-tips-box" style="background: #f0f0f1; border-left: 4px solid #72aee6; padding: 12px; margin: 15px 0;">
                            <p style="margin: 0 0 8px 0;">
                                <span class="dashicons dashicons-lightbulb" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                <strong>Tips:</strong>
                            </p>
                            <ul style="margin: 5px 0 0 20px; padding: 0;">
                                <li>To send a single post: Edit it and click "Update"</li>
                                <li>Manage records in <a href="https://queryra.com/dashboard/records" target="_blank">Queryra dashboard</a></li>
                                <li>Deleted WordPress posts are automatically removed from Queryra</li>
                            </ul>
                        </div>

                        <button type="button" id="queryra-sync-all" class="button button-primary">
                            Send All Posts
                        </button>
                        <div id="queryra-sync-status"></div>
                    </div>
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
                        <!-- API Stats -->
                        <div class="queryra-card">
                            <h3>
                                <span class="dashicons dashicons-chart-bar"></span>
                                API Stats
                            </h3>

                            <!-- Plan Info -->
                            <div style="margin: 15px 0; padding: 10px; background: #f6f7f7; border-radius: 4px;">
                                <p style="margin: 0 0 5px 0;">
                                    <span class="dashicons dashicons-admin-network" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    <strong>Plan:</strong> <?php echo esc_html(ucfirst($stats['plan'])); ?>
                                </p>
                                <p style="margin: 0;">
                                    <span class="dashicons dashicons-portfolio" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    <strong>Records:</strong> <?php echo number_format($stats['total_records']); ?> / <?php echo number_format($stats['record_limit']); ?>
                                    <span style="color: #646970;">(<?php echo $stats['usage_percentage']; ?>%)</span>
                                </p>
                            </div>

                            <!-- Search Window (FREE plan only) -->
                            <?php if ($status && $stats['plan'] === 'free'): ?>
                                <div style="margin: 15px 0; padding: 10px; background: <?php echo $status['available'] ? '#e7f5e7' : '#fff3cd'; ?>; border-radius: 4px; border-left: 3px solid <?php echo $status['available'] ? '#46b450' : '#f0b849'; ?>;">
                                    <p style="margin: 0 0 5px 0;">
                                        <span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                        <strong>Search Window:</strong>
                                    </p>
                                    <?php if ($status['available']): ?>
                                        <p style="margin: 0; color: #46b450;">
                                            <span class="dashicons dashicons-yes-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                            Active (<?php echo $status['minutes_left']; ?> min left)
                                        </p>
                                    <?php else: ?>
                                        <p style="margin: 0; color: #f0b849;">
                                            <span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                            Opens in <?php echo $status['minutes_until_open']; ?> min
                                        </p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #646970;">
                                            Next: <?php echo $status['next_opens_at']; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Sync Status -->
                            <div style="margin: 15px 0;">
                                <p style="margin: 0 0 8px 0; font-weight: 600; color: #1d2327;">Sync Status:</p>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span>
                                        <span class="dashicons dashicons-yes" style="color: #46b450; font-size: 16px; width: 16px; height: 16px;"></span>
                                        Synced
                                    </span>
                                    <strong><?php echo number_format($stats['synced_records']); ?></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>
                                        <span class="dashicons dashicons-upload" style="color: #f0b849; font-size: 16px; width: 16px; height: 16px;"></span>
                                        Unsynced
                                    </span>
                                    <strong><?php echo number_format($stats['unsynced_records']); ?></strong>
                                </div>
                            </div>

                            <!-- Dashboard Link -->
                            <?php if ($stats['unsynced_records'] > 0): ?>
                                <a href="https://queryra.com/dashboard/sync" target="_blank" class="button button-secondary" style="width: 100%; text-align: center; margin-top: 10px;">
                                    <span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
                                    Sync in Dashboard
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="queryra-card">
                        <h3>Documentation</h3>
                        <ul>
                            <li><a href="https://queryra.com/docs" target="_blank">Getting Started</a></li>
                            <li><a href="https://queryra.com/docs/wordpress-integration" target="_blank">WordPress Integration</a></li>
                            <li><a href="https://queryra.com/faq" target="_blank">FAQ</a></li>
                        </ul>
                    </div>

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
}
