<?php
/**
 * Queryra Setup Wizard
 *
 * Guides users through initial setup: API key, import, sync, test
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Setup_Wizard {

    /**
     * Current step
     */
    private $step = 1;

    /**
     * Total steps
     */
    private $total_steps = 4;

    /**
     * Constructor
     */
    public function __construct() {
        // Add wizard submenu
        add_action('admin_menu', array($this, 'add_wizard_menu'), 20);

        // Enqueue wizard styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Auto-redirect to wizard on first activation
        add_action('admin_init', array($this, 'maybe_redirect_to_wizard'));

        // AJAX handlers
        add_action('wp_ajax_queryra_wizard_save_api_key', array($this, 'ajax_save_api_key'));
        add_action('wp_ajax_queryra_wizard_import', array($this, 'ajax_import_content'));
        add_action('wp_ajax_queryra_wizard_check_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_queryra_wizard_test_search', array($this, 'ajax_test_search'));
        add_action('wp_ajax_queryra_wizard_mark_import_done', array($this, 'ajax_mark_import_done'));
    }

    /**
     * Add wizard submenu under Queryra
     */
    public function add_wizard_menu() {
        add_submenu_page(
            'queryra-search',           // Parent slug
            'Setup Wizard',             // Page title
            'Setup Wizard',             // Menu title
            'manage_options',           // Capability
            'queryra-setup-wizard',     // Menu slug
            array($this, 'render_wizard') // Callback
        );
    }

    /**
     * Enqueue wizard CSS and JS
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'queryra_page_queryra-setup-wizard') {
            return;
        }

        wp_enqueue_style('queryra-wizard', QUERYRA_PLUGIN_URL . 'css/wizard.css', array(), QUERYRA_VERSION);
        wp_enqueue_script('queryra-wizard', QUERYRA_PLUGIN_URL . 'js/wizard.js', array('jquery'), QUERYRA_VERSION, true);

        wp_localize_script('queryra-wizard', 'queryraWizard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('queryra_wizard'),
            'syncNonce' => wp_create_nonce('queryra_sync')
        ));
    }

    /**
     * Auto-redirect to wizard on first activation
     */
    public function maybe_redirect_to_wizard() {
        if (get_transient('queryra_activation_redirect')) {
            delete_transient('queryra_activation_redirect');

            // Don't redirect on bulk activation or AJAX
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP activation check
            if (!isset($_GET['activate-multi']) && !wp_doing_ajax()) {
                wp_safe_redirect(admin_url('admin.php?page=queryra-setup-wizard'));
                exit;
            }
        }
    }

    /**
     * Render wizard page
     */
    public function render_wizard() {
        // Get current step from URL
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Step display only, no data modification
        $this->step = isset($_GET['step']) ? absint($_GET['step']) : 1;
        $this->step = max(1, min($this->step, $this->total_steps));

        // Track wizard opened (only on first step, first visit)
        if ($this->step === 1 && !get_transient('queryra_wizard_opened_tracked')) {
            Queryra_Analytics::track('wizard_opened');
            set_transient('queryra_wizard_opened_tracked', true, DAY_IN_SECONDS);
        }

        // Calculate progress percentage
        $progress = ($this->step / $this->total_steps) * 100;

        ?>
        <div class="queryra-wizard-wrap">
            <div class="queryra-wizard-container">

                <!-- Header -->
                <div class="queryra-wizard-header">
                    <h1>
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                        Queryra Setup Wizard
                    </h1>

                    <!-- Progress Bar -->
                    <div class="queryra-wizard-progress">
                        <div class="queryra-wizard-progress-bar">
                            <div class="queryra-wizard-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%"></div>
                        </div>
                        <p class="queryra-wizard-progress-text">
                            Step <?php echo esc_html( $this->step ); ?> of <?php echo esc_html( $this->total_steps ); ?>
                        </p>
                    </div>
                </div>

                <!-- Content -->
                <div class="queryra-wizard-content">
                    <?php
                    // Render current step
                    switch ($this->step) {
                        case 1:
                            $this->render_step_connect();
                            break;
                        case 2:
                            $this->render_step_import();
                            break;
                        case 3:
                            $this->render_step_sync();
                            break;
                        case 4:
                            $this->render_step_test();
                            break;
                    }
                    ?>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Step 1: Welcome & Connect
     */
    private function render_step_connect() {
        $current_api_key = get_option('queryra_api_key', '');
        $has_key = !empty($current_api_key);
        $admin_email = get_option('admin_email');
        $site_url = get_site_url();
        ?>

        <div class="queryra-wizard-step">

            <!-- Welcome Banner -->
            <div class="queryra-welcome-banner">
                <h2>Welcome to Queryra AI Search</h2>
                <p class="queryra-welcome-subtitle">
                    Let's get you set up in just a few steps
                </p>
            </div>

            <?php if ($has_key): ?>
                <!-- Warning: Already has API key -->
                <div style="background: #fff3cd; border-left: 4px solid #f0b849; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p style="margin: 0 0 8px 0;">
                        <span class="dashicons dashicons-warning" style="font-size: 20px; width: 20px; height: 20px; color: #f0b849;"></span>
                        <strong style="color: #856404;">You already have an API key configured</strong>
                    </p>
                    <p style="margin: 0; font-size: 14px; color: #856404;">
                        Creating a new account or changing the key will disconnect your current data.
                        If you want to keep your existing setup, use "I have an API key" option.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Connection Mode Selection -->
            <div class="queryra-connect-section">
                <h3>Connect Your Account</h3>

                <!-- Radio Buttons -->
                <div class="queryra-connection-mode">
                    <label class="queryra-radio-card">
                        <input type="radio" name="connection_mode" value="new" <?php checked(!$has_key); ?>>
                        <div class="queryra-radio-content">
                            <strong>Create New Account</strong>
                            <span>Quick setup with automatic registration</span>
                        </div>
                    </label>

                    <label class="queryra-radio-card">
                        <input type="radio" name="connection_mode" value="existing" <?php checked($has_key); ?>>
                        <div class="queryra-radio-content">
                            <strong>I Have an API Key</strong>
                            <span>Connect with your existing account</span>
                        </div>
                    </label>
                </div>

                <!-- Form: Existing API Key -->
                <div id="queryra-existing-form" class="queryra-connection-form" style="<?php echo $has_key ? '' : 'display: none;'; ?>">
                    <div class="queryra-form-field">
                        <label for="queryra-api-key">API Key</label>
                        <input type="text"
                               id="queryra-api-key"
                               class="queryra-input"
                               placeholder="sk_live_..."
                               value="<?php echo esc_attr($current_api_key); ?>">
                        <p class="queryra-field-note">
                            Enter your Queryra API key. You can find it in your
                            <a href="https://queryra.com/dashboard/api-keys" target="_blank">Dashboard Settings</a>.
                        </p>
                    </div>

                    <div id="queryra-connection-status"></div>

                    <button type="button" id="queryra-save-api-key" class="queryra-button queryra-button-primary queryra-button-large">
                        Continue
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>

                    <div style="text-align: right; margin-top: 15px; font-size: 13px;">
                        <a href="?page=queryra-setup-wizard&step=4" style="text-decoration: none; color: #2271b1;">
                            <span class="dashicons dashicons-controls-forward" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
                            Skip to Test Search
                        </a>
                    </div>
                </div>

                <!-- Form: Create New Account - Instructions -->
                <div id="queryra-new-form" class="queryra-connection-form" style="<?php echo $has_key ? 'display: none;' : ''; ?>">

                    <!-- Instructions Box -->
                    <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 20px; border-radius: 4px; margin-bottom: 25px;">
                        <h4 style="margin: 0 0 15px 0; font-size: 16px; color: #1d2327;">
                            <span class="dashicons dashicons-info" style="font-size: 20px; width: 20px; height: 20px; margin-right: 5px;"></span>
                            Quick Setup Instructions
                        </h4>
                        <ol style="margin: 0; padding-left: 20px; color: #1d2327; line-height: 1.8;">
                            <li>Go to <strong>queryra.com/signup</strong> and create your account</li>
                            <li>Verify your email address</li>
                            <li>Log in to your dashboard and copy your API key</li>
                            <li>Come back here and paste it below</li>
                        </ol>

                        <a href="https://queryra.com/signup"
                           target="_blank"
                           class="queryra-button queryra-button-primary"
                           style="margin-top: 15px;">
                            Open Queryra Signup
                            <span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        </a>
                    </div>

                    <!-- API Key Input -->
                    <div class="queryra-form-field">
                        <label for="queryra-api-key-new">Paste Your API Key</label>
                        <input type="text"
                               id="queryra-api-key-new"
                               class="queryra-input"
                               placeholder="sk_live_...">
                        <p class="queryra-field-note">
                            After creating your account, copy the API key from your dashboard and paste it here
                        </p>
                    </div>

                    <div id="queryra-connection-status"></div>

                    <button type="button" id="queryra-save-new-key" class="queryra-button queryra-button-primary queryra-button-large">
                        Continue
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Step 2: Content Import Info
     */
    private function render_step_import() {
        // Fetch FRESH stats from API with NEW key
        $api = new Queryra_API();
        $stats_response = $api->get_stats();

        if (is_wp_error($stats_response)) {
            // Error fetching stats
            ?>
            <div class="queryra-wizard-step">
                <h2>Content Import</h2>
                <div style="background: #fef2f2; border-left: 4px solid #dc3232; padding: 20px; border-radius: 4px; margin: 25px 0;">
                    <p style="margin: 0; color: #dc3232;">
                        <span class="dashicons dashicons-warning" style="font-size: 20px; width: 20px; height: 20px;"></span>
                        <strong>Unable to connect to Queryra API</strong>
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 14px; color: #646970;">
                        <?php echo esc_html($stats_response->get_error_message()); ?>
                    </p>
                </div>
                <a href="?page=queryra-setup-wizard&step=1" class="queryra-button queryra-button-primary">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    Back to API Key
                </a>
            </div>
            <?php
            return;
        }

        // Save fresh stats to cache
        update_option('queryra_cached_stats', $stats_response);
        $stats = $stats_response;

        // Count posts and pages
        $posts_count = wp_count_posts('post');
        $published_posts = isset($posts_count->publish) ? $posts_count->publish : 0;

        $pages_count = wp_count_posts('page');
        $published_pages = isset($pages_count->publish) ? $pages_count->publish : 0;

        // Count products (if WooCommerce is active)
        $published_products = 0;
        $has_woocommerce = class_exists('WooCommerce');
        if ($has_woocommerce) {
            $products_count = wp_count_posts('product');
            $published_products = isset($products_count->publish) ? $products_count->publish : 0;
        }

        // Get plan info
        $plan = isset($stats['plan']) ? ucfirst($stats['plan']) : 'Free';
        $limit = isset($stats['record_limit']) ? $stats['record_limit'] : 100;

        // Calculate total content available
        $total_content = $published_posts + $published_pages + $published_products;

        // Calculate what will be imported (all content types, up to limit)
        $will_import = min($total_content, $limit);

        // Check if we're over limit
        $is_over_limit = $total_content > $limit;
        ?>

        <div class="queryra-wizard-step">
            <h2>Content Import</h2>
            <p class="queryra-step-subtitle">We'll automatically import your content to Queryra</p>

            <!-- Site Content Summary -->
            <div style="background: #f6f7f7; padding: 20px; border-radius: 8px; margin: 25px 0;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #1d2327;">Your WordPress Site:</h3>
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div>
                        <span style="font-size: 32px; font-weight: 700; color: #2271b1;"><?php echo number_format($published_posts); ?></span>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #646970;">Posts</p>
                    </div>
                    <div>
                        <span style="font-size: 32px; font-weight: 700; color: #2271b1;"><?php echo number_format($published_pages); ?></span>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #646970;">Pages</p>
                    </div>
                    <?php if ($has_woocommerce): ?>
                    <div>
                        <span style="font-size: 32px; font-weight: 700; color: #2271b1;"><?php echo number_format($published_products); ?></span>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #646970;">Products</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Plan Info -->
            <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 20px; border-radius: 4px; margin: 25px 0;">
                <p style="margin: 0 0 10px 0; font-size: 15px;">
                    <strong>Your Plan: <?php echo esc_html($plan); ?></strong>
                    <span style="color: #646970;">(<?php echo number_format($limit); ?> records included)</span>
                </p>
            </div>

            <!-- What Will Be Imported -->
            <div style="background: #fff; border: 2px solid #2271b1; padding: 25px; border-radius: 8px; margin: 25px 0;">
                <h3 style="margin: 0 0 20px 0; font-size: 18px; color: #1d2327;">
                    <span class="dashicons dashicons-upload" style="color: #2271b1; font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                    What will be imported:
                </h3>

                <?php if (!$is_over_limit): ?>
                    <!-- Everything fits within limit -->
                    <div style="background: #e7f7ed; padding: 20px; border-radius: 4px; margin-bottom: 15px;">
                        <p style="margin: 0 0 15px 0; font-size: 16px; color: #1d2327;">
                            <span style="color: #00a32a; font-size: 24px; margin-right: 8px;">✅</span>
                            <strong>All your content will be imported!</strong>
                        </p>
                        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                            <?php if ($published_posts > 0): ?>
                            <div style="text-align: center; min-width: 80px;">
                                <div style="font-size: 32px; font-weight: 700; color: #00a32a; line-height: 1;"><?php echo number_format($published_posts); ?></div>
                                <p style="margin: 8px 0 0 0; font-size: 14px; color: #646970;">Posts</p>
                            </div>
                            <?php endif; ?>
                            <?php if ($published_pages > 0): ?>
                            <div style="text-align: center; min-width: 80px;">
                                <div style="font-size: 32px; font-weight: 700; color: #00a32a; line-height: 1;"><?php echo number_format($published_pages); ?></div>
                                <p style="margin: 8px 0 0 0; font-size: 14px; color: #646970;">Pages</p>
                            </div>
                            <?php endif; ?>
                            <?php if ($has_woocommerce && $published_products > 0): ?>
                            <div style="text-align: center; min-width: 80px;">
                                <div style="font-size: 32px; font-weight: 700; color: #00a32a; line-height: 1;"><?php echo number_format($published_products); ?></div>
                                <p style="margin: 8px 0 0 0; font-size: 14px; color: #646970;">Products</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Over limit - show what fits -->
                    <div style="background: #fff3cd; padding: 20px; border-radius: 4px; margin-bottom: 15px;">
                        <p style="margin: 0 0 10px 0; font-size: 16px; color: #856404;">
                            <span class="dashicons dashicons-info" style="font-size: 20px; width: 20px; height: 20px;"></span>
                            <strong>Plan Limit Reached</strong>
                        </p>
                        <p style="margin: 0; font-size: 14px; color: #646970;">
                            You have <strong><?php echo number_format($total_content); ?> total records</strong>, but your plan allows <strong><?php echo number_format($limit); ?> records</strong>.
                        </p>
                        <p style="margin: 10px 0 0 0; font-size: 14px; color: #646970;">
                            We'll import the <strong><?php echo number_format($will_import); ?> most recent records</strong> (sorted by publish date).
                        </p>
                    </div>
                    <div style="background: #e7f3ff; padding: 15px; border-radius: 4px;">
                        <p style="margin: 0; font-size: 13px; color: #646970;">
                            Want to import all <?php echo number_format($total_content); ?> records?
                            <a href="https://queryra.com/pricing" target="_blank" style="color: #2271b1; text-decoration: underline;">Upgrade your plan</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info Box -->
            <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin: 25px 0;">
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #646970;">
                    💡 <strong>After import:</strong> You can manage your imported content anytime:
                </p>
                <ul style="margin: 0 0 0 20px; padding: 0; font-size: 13px; color: #646970;">
                    <li>Delete unwanted records from <a href="https://queryra.com/dashboard/records" target="_blank">Queryra Dashboard</a></li>
                    <li>Import new content from <strong>Settings → Records</strong></li>
                    <li>Auto-sync keeps everything up to date</li>
                </ul>
            </div>

            <!-- Start Import Button (Top) -->
            <div style="text-align: center; margin: 30px 0;">
                <button type="button" id="queryra-start-import" class="queryra-button queryra-button-primary queryra-button-large">
                    <span class="dashicons dashicons-upload"></span>
                    Start Import Now
                </button>
            </div>

            <!-- Progress Bar (Hidden initially) -->
            <div id="queryra-import-progress" style="display: none; margin: 30px 0;">
                <div style="background: #f6f7f7; padding: 20px; border-radius: 8px;">
                    <p id="queryra-import-status" style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: #1d2327;">
                        Importing content...
                    </p>
                    <div style="background: #dcdcde; height: 24px; border-radius: 12px; overflow: hidden;">
                        <div id="queryra-import-progress-bar" style="background: linear-gradient(90deg, #2271b1 0%, #135e96 100%); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; font-weight: 600;"></div>
                    </div>
                    <p id="queryra-import-info" style="margin: 10px 0 0 0; font-size: 14px; color: #646970;">
                        0 / <?php echo esc_html( $will_import ); ?> records
                    </p>
                </div>
            </div>

            <!-- Success Message (Hidden initially) -->
            <div id="queryra-import-success" style="display: none; margin: 30px 0;">
                <div style="background: #e7f5e7; border-left: 4px solid #46b450; padding: 20px; border-radius: 4px;">
                    <p style="margin: 0; color: #46b450; font-size: 16px; font-weight: 600;">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>
                        Import Complete!
                    </p>
                    <p id="queryra-success-message" style="margin: 10px 0 0 0; font-size: 14px; color: #1d2327;">
                        Successfully imported <?php echo esc_html( $will_import ); ?> records to Queryra
                    </p>
                </div>
            </div>

            <!-- Navigation (Bottom) -->
            <div style="display: flex; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dcdcde;">
                <a href="?page=queryra-setup-wizard&step=1" class="queryra-button queryra-button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    Back
                </a>
                <button type="button" id="queryra-continue-step3" class="queryra-button queryra-button-primary queryra-button-large" style="flex: 1;" disabled>
                    Continue
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Step 3: Sync Status (Informational)
     */
    private function render_step_sync() {
        // Fetch FRESH stats from API
        $api = new Queryra_API();
        $stats_response = $api->get_stats();

        if (is_wp_error($stats_response)) {
            ?>
            <div class="queryra-wizard-step">
                <h2>Sync Status</h2>
                <div style="background: #fef2f2; border-left: 4px solid #dc3232; padding: 20px; border-radius: 4px; margin: 25px 0;">
                    <p style="margin: 0; color: #dc3232;">
                        <span class="dashicons dashicons-warning" style="font-size: 20px; width: 20px; height: 20px;"></span>
                        <strong>Unable to connect to Queryra API</strong>
                    </p>
                    <p style="margin: 10px 0 0 0;"><?php echo esc_html($stats_response->get_error_message()); ?></p>
                </div>
                <a href="?page=queryra-setup-wizard&step=1" class="queryra-button queryra-button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    Back to Step 1
                </a>
            </div>
            <?php
            return;
        }

        $stats = $stats_response;
        $records_in_db = isset($stats['total_records']) ? $stats['total_records'] : 0;
        $unsynced_count = isset($stats['unsynced_records']) ? $stats['unsynced_records'] : 0;
        $is_synced = ($unsynced_count == 0 && $records_in_db > 0);

        // Auto-set plugin settings (hidden)
        update_option('queryra_enabled', '1');
        update_option('queryra_auto_sync', '1');

        // Build post types - include products if WooCommerce active
        $post_types = array('post', 'page');
        if (class_exists('WooCommerce')) {
            $post_types[] = 'product';
        }
        update_option('queryra_post_types', $post_types);

        ?>
        <div class="queryra-wizard-step">
            <h2>Sync to AI Search</h2>
            <p class="queryra-step-subtitle">
                Complete the sync process in your dashboard
            </p>

            <!-- Sync Stats -->
            <div id="queryra-sync-stats" style="background: white; border: 1px solid #dcdcde; border-radius: 4px; padding: 20px; margin: 25px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div>
                        <div style="font-size: 13px; color: #646970; margin-bottom: 5px;">Total Records</div>
                        <div style="font-size: 24px; font-weight: 600; color: #1d2327;">
                            <span id="queryra-total-records"><?php echo number_format($records_in_db); ?></span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 13px; color: #646970; margin-bottom: 5px;">Pending Sync</div>
                        <div style="font-size: 24px; font-weight: 600;" id="queryra-pending-sync-number">
                            <span style="color: <?php echo $is_synced ? '#00a32a' : '#d63638'; ?>">
                                <?php echo number_format($unsynced_count); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instructions Box -->
            <div id="queryra-sync-instructions" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 20px; border-radius: 4px; margin: 25px 0; <?php echo $is_synced ? 'display: none;' : ''; ?>">
                <h4 style="margin: 0 0 15px 0; font-size: 16px;">
                    <span class="dashicons dashicons-admin-site" style="font-size: 20px; width: 20px; height: 20px;"></span>
                    Next Step: Sync in Dashboard
                </h4>
                <ol style="margin: 0; padding-left: 20px; line-height: 1.8;">
                    <li>Open your <strong>Queryra Dashboard</strong></li>
                    <li>Go to the <strong>Sync</strong> page</li>
                    <li>Click <strong>"Sync Now"</strong> to process your <?php echo number_format($records_in_db); ?> records with AI</li>
                    <li>Come back here and click <strong>"Check Status"</strong> to verify</li>
                </ol>

                <a href="https://queryra.com/dashboard/sync"
                   target="_blank"
                   class="queryra-button queryra-button-primary"
                   style="margin-top: 20px;">
                    Open Dashboard → Sync
                    <span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px;"></span>
                </a>
            </div>

            <!-- Success Message -->
            <div id="queryra-sync-complete" style="background: #e7f7ed; border-left: 4px solid #00a32a; padding: 20px; border-radius: 4px; margin: 25px 0; <?php echo $is_synced ? '' : 'display: none;'; ?>">
                <h4 style="margin: 0 0 10px 0; font-size: 15px; color: #00a32a;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
                    All Records Synced!
                </h4>
                <p style="margin: 0; color: #1d2327;">
                    Your <?php echo number_format($records_in_db); ?> records are ready for AI-powered search.
                </p>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <button type="button" id="queryra-check-status" class="queryra-button queryra-button-secondary queryra-button-large" style="flex: 1;">
                    <span class="dashicons dashicons-update"></span>
                    Check Status
                </button>

                <button type="button" id="queryra-continue-step4" class="queryra-button queryra-button-primary queryra-button-large" style="flex: 1;" <?php echo $is_synced ? '' : 'disabled'; ?>>
                    Continue
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Step 4: Test Search
     */
    private function render_step_test() {
        ?>
        <div class="queryra-wizard-step">
            <h2>Test Your AI Search</h2>
            <p class="queryra-step-subtitle">
                Direct API test - searches through Queryra without WordPress settings
            </p>

            <!-- Success Message -->
            <div style="background: #e7f7ed; border-left: 4px solid #00a32a; padding: 20px; border-radius: 4px; margin: 25px 0;">
                <h4 style="margin: 0 0 10px 0; font-size: 15px; color: #00a32a;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
                    Setup Complete!
                </h4>
                <p style="margin: 0; color: #1d2327;">
                    Your content is imported and synced. AI-powered search is ready to use.
                </p>
            </div>

            <!-- Info Box -->
            <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 25px 0;">
                <p style="margin: 0; font-size: 13px; color: #646970;">
                    💡 <strong>Note:</strong> This test queries the Queryra API directly. It ignores your WordPress AI Search settings (enabled/disabled).
                    To test with WordPress settings, use the regular search on your site.
                </p>
            </div>

            <!-- Search Test -->
            <div style="background: white; border: 1px solid #dcdcde; border-radius: 4px; padding: 25px; margin: 25px 0;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px;">Try a Search</h3>

                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <input type="text"
                           id="queryra-test-query"
                           class="queryra-input"
                           placeholder="Type your search query..."
                           style="flex: 1;">
                    <button type="button" id="queryra-test-search" class="queryra-button queryra-button-primary">
                        <span class="dashicons dashicons-search"></span>
                        Search
                    </button>
                </div>

                <!-- Results -->
                <div id="queryra-test-results" style="display: none; margin-top: 20px;">
                    <h4 style="margin: 0 0 15px 0; font-size: 14px; color: #646970;">
                        Search Results <span id="queryra-results-count"></span>
                    </h4>
                    <div id="queryra-results-list"></div>
                </div>

                <!-- No Results -->
                <div id="queryra-no-results" style="display: none; margin-top: 20px; padding: 20px; background: #f6f7f7; border-radius: 4px; text-align: center;">
                    <p style="margin: 0; color: #646970;">No results found. Try a different search term.</p>
                </div>

                <!-- Error -->
                <div id="queryra-search-error" style="display: none; margin-top: 20px;"></div>
            </div>

            <!-- Finish Button -->
            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <a href="?page=queryra-search" class="queryra-button queryra-button-primary queryra-button-large" style="flex: 1; justify-content: center; text-decoration: none;">
                    <span class="dashicons dashicons-yes"></span>
                    Finish & Go to Dashboard
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save existing API key
     */
    public function ajax_save_api_key() {
        check_ajax_referer('queryra_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is required'));
            return;
        }

        // Save API key
        update_option('queryra_api_key', $api_key);

        // Test connection
        $api = new Queryra_API();
        $test = $api->get_stats();

        if (is_wp_error($test)) {
            wp_send_json_error(array('message' => 'Invalid API key or connection failed'));
            return;
        }

        // Track signup completed (API key successfully connected)
        Queryra_Analytics::track('signup_completed');

        wp_send_json_success(array(
            'message' => 'API key saved successfully'
        ));
    }

    /**
     * AJAX: Import content
     */
    public function ajax_import_content() {
        check_ajax_referer('queryra_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Get stats to know the limit
        $stats = get_option('queryra_cached_stats');
        $limit = isset($stats['record_limit']) ? $stats['record_limit'] : 100;

        // Build post types array
        $post_types = array('post', 'page');

        // Add products if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $post_types[] = 'product';
        }

        // Get all published content (posts, pages, products) up to limit
        $all_content = get_posts(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if (empty($all_content)) {
            wp_send_json_error(array('message' => 'No content found to import'));
            return;
        }

        // Get post IDs
        $post_ids = array_map(function($post) {
            return $post->ID;
        }, $all_content);

        // Use existing sync functionality
        $sync = new Queryra_Sync();
        $result = $sync->sync_posts($post_ids);

        if ($result['success']) {
            // Save that wizard import was completed
            update_option('queryra_wizard_import_done', '1');

            wp_send_json_success(array(
                'message' => sprintf('Successfully imported %d records', count($all_content)),
                'imported' => count($all_content)
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Mark wizard import as done (called by batched import)
     */
    public function ajax_mark_import_done() {
        check_ajax_referer('queryra_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        update_option('queryra_wizard_import_done', '1');
        wp_send_json_success();
    }

    /**
     * AJAX: Check sync status
     */
    public function ajax_check_status() {
        check_ajax_referer('queryra_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Get fresh stats from API
        $api = new Queryra_API();
        $stats = $api->get_stats();

        if (is_wp_error($stats)) {
            wp_send_json_error(array('message' => $stats->get_error_message()));
            return;
        }

        $records_count = isset($stats['total_records']) ? $stats['total_records'] : 0;
        $unsynced_count = isset($stats['unsynced_records']) ? $stats['unsynced_records'] : 0;
        $is_synced = ($unsynced_count == 0 && $records_count > 0);

        wp_send_json_success(array(
            'records_count' => $records_count,
            'unsynced_count' => $unsynced_count,
            'is_synced' => $is_synced
        ));
    }


    /**
     * AJAX: Test search
     */
    public function ajax_test_search() {
        check_ajax_referer('queryra_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';

        if (empty($query)) {
            wp_send_json_error(array('message' => 'Search query is required'));
            return;
        }

        // Search via API
        $api = new Queryra_API();
        $result = $api->search($query, 15);

        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $error_message = $result->get_error_message();

            // Check if message is JSON (happens when API returns complex error)
            $json_decoded = json_decode($error_message, true);
            if ($json_decoded && isset($json_decoded['error'])) {
                // Message is JSON - use decoded data
                $error_info = $json_decoded;
            } elseif ($error_data && isset($error_data['data']['errors']['api_error'][0])) {
                // Standard error structure
                $error_info = $error_data['data']['errors']['api_error'][0];
            } else {
                // Unknown structure - return default message
                wp_send_json_error(array('message' => $error_message));
                return;
            }

            // Handle FREE_PLAN_WINDOW_CLOSED
            if (isset($error_info['error']) && $error_info['error'] === 'FREE_PLAN_WINDOW_CLOSED') {
                // Extract info
                $minutes = isset($error_info['minutes_until_open']) ? $error_info['minutes_until_open'] : 0;
                $next_time = isset($error_info['next_available_at']) ? $error_info['next_available_at'] : '';

                // Build user-friendly message
                $hours = floor($minutes / 60);
                $mins = $minutes % 60;
                $time_text = '';

                if ($hours > 0) {
                    $time_text = sprintf('%d hour%s %d minute%s',
                        $hours,
                        $hours > 1 ? 's' : '',
                        $mins,
                        $mins != 1 ? 's' : ''
                    );
                } else {
                    $time_text = sprintf('%d minute%s', $mins, $mins != 1 ? 's' : '');
                }

                $friendly_message = sprintf(
                    '<strong>FREE Plan Search Window Closed</strong><br><br>' .
                    'Your FREE plan allows AI search for <strong>1 hour every 3 hours</strong>.<br><br>' .
                    '⏰ Next search window opens in: <strong>%s</strong> (at %s UTC)<br><br>' .
                    '💡 Want unlimited 24/7 access? <a href="https://queryra.com/pricing" target="_blank" style="color: #2271b1; font-weight: 600;">Upgrade to STARTER plan</a>',
                    $time_text,
                    $next_time
                );

                wp_send_json_error(array('message' => $friendly_message));
                return;
            }

            // Default error handling for other errors
            wp_send_json_error(array('message' => $error_message));
            return;
        }

        // Extract results
        $results = isset($result['results']) ? $result['results'] : array();
        $total = isset($result['total_found']) ? $result['total_found'] : 0;

        // Simplify results - only name and score
        $simplified = array();
        foreach ($results as $item) {
            $simplified[] = array(
                'name' => isset($item['name']) ? $item['name'] : '',
                'score' => isset($item['relevance_score']) ? round($item['relevance_score'], 3) : 0
            );
        }

        wp_send_json_success(array(
            'results' => $simplified,
            'total' => $total
        ));
    }

}
