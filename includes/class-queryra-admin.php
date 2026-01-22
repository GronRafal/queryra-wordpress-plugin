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

        // Get stats if API key is set
        $stats = null;
        if (!empty($api_key)) {
            $api = new Queryra_API();
            $stats_response = $api->get_stats();
            if (!is_wp_error($stats_response)) {
                $stats = $stats_response;
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
                            <p style="margin: 0 0 8px 0;"><strong>ℹ️ How it works:</strong></p>
                            <ol style="margin: 5px 0 5px 20px; padding: 0;">
                                <li>Posts are sent to Queryra (happens here)</li>
                                <li>Generate embeddings in <a href="https://queryra.com/dashboard/sync" target="_blank">Queryra dashboard</a></li>
                                <li>Records become searchable</li>
                            </ol>
                        </div>

                        <!-- Tips Box -->
                        <div class="queryra-tips-box" style="background: #f0f0f1; border-left: 4px solid #72aee6; padding: 12px; margin: 15px 0;">
                            <p style="margin: 0 0 8px 0;"><strong>💡 Tips:</strong></p>
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
                    <?php if ($stats): ?>
                        <div class="queryra-card">
                            <h3>Stats</h3>
                            <div class="queryra-stat">
                                <div class="queryra-stat-label">Total Records</div>
                                <div class="queryra-stat-value"><?php echo number_format($stats['total_records']); ?></div>
                            </div>
                            <div class="queryra-stat">
                                <div class="queryra-stat-label">Synced</div>
                                <div class="queryra-stat-value"><?php echo number_format($stats['synced_records']); ?></div>
                            </div>
                            <div class="queryra-stat">
                                <div class="queryra-stat-label">Unsynced</div>
                                <div class="queryra-stat-value"><?php echo number_format($stats['unsynced_records']); ?></div>
                            </div>
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
