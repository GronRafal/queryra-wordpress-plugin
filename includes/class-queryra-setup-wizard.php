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
        add_action('wp_ajax_queryra_wizard_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_queryra_wizard_test_search', array($this, 'ajax_test_search'));
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
            'nonce' => wp_create_nonce('queryra_wizard')
        ));
    }

    /**
     * Auto-redirect to wizard on first activation
     */
    public function maybe_redirect_to_wizard() {
        if (get_transient('queryra_activation_redirect')) {
            delete_transient('queryra_activation_redirect');

            // Don't redirect on bulk activation or AJAX
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
        $this->step = isset($_GET['step']) ? absint($_GET['step']) : 1;
        $this->step = max(1, min($this->step, $this->total_steps));

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
                            <div class="queryra-wizard-progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <p class="queryra-wizard-progress-text">
                            Step <?php echo $this->step; ?> of <?php echo $this->total_steps; ?>
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
                            <a href="https://queryra.com/dashboard/settings" target="_blank">Dashboard Settings</a>.
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

        // Get plan info
        $plan = isset($stats['plan']) ? ucfirst($stats['plan']) : 'Free';
        $limit = isset($stats['record_limit']) ? $stats['record_limit'] : 100;

        // Calculate what will be imported
        $will_import = min($published_posts, $limit);
        ?>

        <div class="queryra-wizard-step">
            <h2>Content Import</h2>
            <p class="queryra-step-subtitle">We'll automatically import your content to Queryra</p>

            <!-- Site Content Summary -->
            <div style="background: #f6f7f7; padding: 20px; border-radius: 8px; margin: 25px 0;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #1d2327;">Your WordPress Site:</h3>
                <div style="display: flex; gap: 30px;">
                    <div>
                        <span style="font-size: 32px; font-weight: 700; color: #2271b1;"><?php echo number_format($published_posts); ?></span>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #646970;">Posts</p>
                    </div>
                    <div>
                        <span style="font-size: 32px; font-weight: 700; color: #646970;"><?php echo number_format($published_pages); ?></span>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #646970;">Pages</p>
                    </div>
                </div>
            </div>

            <!-- Plan Info -->
            <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 20px; border-radius: 4px; margin: 25px 0;">
                <p style="margin: 0 0 10px 0; font-size: 15px;">
                    <strong>Your Plan: <?php echo esc_html($plan); ?></strong>
                    <span style="color: #646970;">(<?php echo number_format($limit); ?> items included)</span>
                </p>
            </div>

            <!-- What Will Be Imported -->
            <div style="background: #fff; border: 2px solid #2271b1; padding: 25px; border-radius: 8px; margin: 25px 0;">
                <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #1d2327;">
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                    What will be imported:
                </h3>

                <?php if ($published_posts <= $limit): ?>
                    <!-- All posts fit within limit -->
                    <p style="margin: 0; font-size: 16px; color: #1d2327;">
                        ✅ <strong>All <?php echo number_format($published_posts); ?> Posts</strong>
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 14px; color: #646970;">
                        All your posts will be imported to Queryra for AI search
                    </p>
                <?php else: ?>
                    <!-- Over limit - import newest -->
                    <p style="margin: 0; font-size: 16px; color: #1d2327;">
                        ✅ <strong><?php echo number_format($will_import); ?> most recent Posts</strong>
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 14px; color: #646970;">
                        Sorted by publish date, newest first
                    </p>

                    <!-- Warning: Not all content -->
                    <div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin-top: 15px;">
                        <p style="margin: 0 0 8px 0; color: #856404;">
                            <span class="dashicons dashicons-info" style="font-size: 18px; width: 18px; height: 18px;"></span>
                            <strong>Note:</strong> You have <?php echo number_format($published_posts - $will_import); ?> older posts that won't be imported
                        </p>
                        <p style="margin: 0; font-size: 13px; color: #856404;">
                            Pages and older posts will be skipped. Want to import more?
                            <a href="https://queryra.com/pricing" target="_blank" style="color: #856404; text-decoration: underline;">Upgrade your plan</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info Box -->
            <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin: 25px 0;">
                <p style="margin: 0; font-size: 13px; color: #646970;">
                    💡 <strong>Note:</strong> You can change what content types to sync later in Settings → Content.
                    This import will send your content to Queryra for AI processing.
                </p>
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
                        0 / <?php echo $will_import; ?> items
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
                        Successfully imported <?php echo $will_import; ?> items to Queryra
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
        update_option('queryra_post_types', array('post', 'page'));

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
                    <li>Click <strong>"Sync Now"</strong> to process your <?php echo number_format($records_in_db); ?> items with AI</li>
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
                    Your <?php echo number_format($records_in_db); ?> items are ready for AI-powered search.
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
                Try a search to see your content in action
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

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

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

        // Get newest posts (up to limit)
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if (empty($posts)) {
            wp_send_json_error(array('message' => 'No posts found to import'));
            return;
        }

        // Get post IDs
        $post_ids = array_map(function($post) {
            return $post->ID;
        }, $posts);

        // Use existing sync functionality
        $sync = new Queryra_Sync();
        $result = $sync->sync_posts($post_ids);

        if ($result['success']) {
            // Save that wizard import was completed
            update_option('queryra_wizard_import_done', '1');

            wp_send_json_success(array(
                'message' => sprintf('Successfully imported %d posts', count($posts)),
                'imported' => count($posts)
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
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
     * AJAX: Save plugin settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('queryra_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Get settings from POST
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true' ? '1' : '0';
        $auto_import = isset($_POST['auto_import']) && $_POST['auto_import'] === 'true' ? '1' : '0';
        $include_pages = isset($_POST['include_pages']) && $_POST['include_pages'] === 'true';

        // Build post_types array
        $post_types = array('post'); // Always include posts
        if ($include_pages) {
            $post_types[] = 'page';
        }

        // Save settings
        update_option('queryra_enabled', $enabled);
        update_option('queryra_auto_sync', $auto_import);
        update_option('queryra_post_types', $post_types);

        wp_send_json_success(array(
            'message' => 'Settings saved successfully'
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

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (empty($query)) {
            wp_send_json_error(array('message' => 'Search query is required'));
            return;
        }

        // Search via API
        $api = new Queryra_API();
        $result = $api->search($query, 15);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
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
