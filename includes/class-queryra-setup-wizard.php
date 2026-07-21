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
        add_action('wp_ajax_queryra_wizard_save_post_types', array($this, 'ajax_save_post_types'));
        add_action('wp_ajax_queryra_wizard_check_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_queryra_wizard_test_search', array($this, 'ajax_test_search'));
        add_action('wp_ajax_queryra_wizard_mark_import_done', array($this, 'ajax_mark_import_done'));

        // Site-profile question (install survey / onboarding ad — SPEC
        // 2026-07-20). Saves the answer or the skip; skip sends NOTHING.
        add_action('wp_ajax_queryra_wizard_save_site_profile', array($this, 'ajax_save_site_profile'));

        // Setup survey — rendered on the wizard screen (activation redirects
        // there), asked once per activation.
        add_action('admin_footer', array($this, 'maybe_render_site_profile_prompt'));
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
        // The site-profile question is NOT rendered here — it comes from the
        // single admin_footer path (maybe_render_site_profile_prompt), which
        // covers this page too. One render path = no double-modal, no drift.
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
                               class="queryra-input queryra-masked-key"
                               placeholder="sk_live_..."
                               autocomplete="off"
                               spellcheck="false"
                               value="<?php echo esc_attr($current_api_key); ?>">
                        <p class="queryra-field-note">
                            Enter your Queryra API key (click the field to edit). You can find it in your
                            <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/api-keys')); ?>" target="_blank" rel="noopener">Dashboard Settings</a>.
                        </p>
                        <script>
                        (function($) {
                            // Connectors-API-style masking: last 4 chars visible, bullets for the rest.
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
                            });
                            // Wizard.js reads the value via .val() on click of the Continue button.
                            // Intercept BEFORE that handler (mousedown fires earlier than click) to
                            // restore the real value, so bullets never reach the AJAX request.
                            $(document).on('mousedown', '#queryra-save-api-key, #queryra-save-api-key-new', function() {
                                var $key = $('.queryra-masked-key');
                                $key.each(function() {
                                    var $t = $(this);
                                    var v = $t.data('realValue');
                                    if (typeof v !== 'undefined' && v !== null) {
                                        $t.val(v);
                                    }
                                });
                            });
                        })(jQuery);
                        </script>
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

                        <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/signup')); ?>"
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
        update_option('queryra_cached_stats', $stats_response, false);
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

        // Custom post types — same discovery rule as the Settings → Content
        // tab so the wizard offers exactly the types a user can later toggle.
        // Skip post/page/product (their own rows above) and internal types.
        $cpt_skip       = array('post', 'page', 'product', 'attachment', 'revision', 'nav_menu_item', 'wp_block');
        $custom_types   = array();
        $custom_total   = 0;
        foreach (get_post_types(array('public' => true), 'objects') as $pt_object) {
            if (in_array($pt_object->name, $cpt_skip, true)) {
                continue;
            }
            $cpt_count = wp_count_posts($pt_object->name);
            $cpt_published = isset($cpt_count->publish) ? (int) $cpt_count->publish : 0;
            $custom_types[] = array(
                'name'  => $pt_object->name,
                'label' => isset($pt_object->labels->name) ? $pt_object->labels->name : $pt_object->name,
                'count' => $cpt_published,
            );
            $custom_total += $cpt_published;
        }

        // Get plan info
        $plan = isset($stats['plan']) ? ucfirst($stats['plan']) : 'Free';
        $limit = isset($stats['record_limit']) ? $stats['record_limit'] : 100;

        // Calculate total content available
        $total_content = $published_posts + $published_pages + $published_products + $custom_total;

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

            <!-- Select Content Types -->
            <div id="queryra-import-types" style="background: #fff; border: 2px solid #2271b1; padding: 25px; border-radius: 8px; margin: 25px 0;" data-limit="<?php echo (int) $limit; ?>">
                <h3 style="margin: 0 0 20px 0; font-size: 18px; color: #1d2327;">
                    <span class="dashicons dashicons-upload" style="color: #2271b1; font-size: 24px; width: 24px; height: 24px; margin-right: 8px;"></span>
                    Select what to import:
                </h3>

                <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; background: #f6f7f7; border-radius: 4px; cursor: pointer; font-size: 15px;">
                        <input type="checkbox" class="queryra-type-checkbox" value="post" data-count="<?php echo (int) $published_posts; ?>" checked <?php disabled($published_posts, 0); ?>>
                        <strong style="color: #2271b1; font-size: 18px; min-width: 60px; text-align: right;"><?php echo number_format($published_posts); ?></strong>
                        <span>Posts</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; background: #f6f7f7; border-radius: 4px; cursor: pointer; font-size: 15px;">
                        <input type="checkbox" class="queryra-type-checkbox" value="page" data-count="<?php echo (int) $published_pages; ?>" checked <?php disabled($published_pages, 0); ?>>
                        <strong style="color: #2271b1; font-size: 18px; min-width: 60px; text-align: right;"><?php echo number_format($published_pages); ?></strong>
                        <span>Pages</span>
                    </label>
                    <?php if ($has_woocommerce): ?>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; background: #f6f7f7; border-radius: 4px; cursor: pointer; font-size: 15px;">
                        <input type="checkbox" class="queryra-type-checkbox" value="product" data-count="<?php echo (int) $published_products; ?>" checked <?php disabled($published_products, 0); ?>>
                        <strong style="color: #2271b1; font-size: 18px; min-width: 60px; text-align: right;"><?php echo number_format($published_products); ?></strong>
                        <span>Products</span>
                    </label>
                    <?php endif; ?>
                    <?php foreach ($custom_types as $cpt): ?>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; background: #f6f7f7; border-radius: 4px; cursor: pointer; font-size: 15px;">
                        <input type="checkbox" class="queryra-type-checkbox" value="<?php echo esc_attr($cpt['name']); ?>" data-count="<?php echo (int) $cpt['count']; ?>" checked <?php disabled($cpt['count'], 0); ?>>
                        <strong style="color: #2271b1; font-size: 18px; min-width: 60px; text-align: right;"><?php echo number_format($cpt['count']); ?></strong>
                        <span><?php echo esc_html($cpt['label']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Summary: within limit -->
                <div id="queryra-summary-within-limit" style="display: none; background: #e7f7ed; padding: 15px 20px; border-radius: 4px;">
                    <p style="margin: 0; font-size: 15px; color: #1d2327;">
                        <span style="color: #00a32a; font-size: 20px; margin-right: 8px;">✅</span>
                        <strong><span id="queryra-will-import-count">0</span> records</strong> will be imported.
                    </p>
                </div>

                <!-- Summary: over limit -->
                <div id="queryra-summary-over-limit" style="display: none;">
                    <div style="background: #fff3cd; padding: 20px; border-radius: 4px; margin-bottom: 12px;">
                        <p style="margin: 0 0 10px 0; font-size: 16px; color: #856404;">
                            <span class="dashicons dashicons-info" style="font-size: 20px; width: 20px; height: 20px;"></span>
                            <strong>Plan Limit Reached</strong>
                        </p>
                        <p style="margin: 0; font-size: 14px; color: #646970;">
                            You selected <strong><span id="queryra-selected-total">0</span> records</strong>, but your plan allows <strong><?php echo number_format($limit); ?> records</strong>.
                        </p>
                        <p style="margin: 10px 0 0 0; font-size: 14px; color: #646970;">
                            We'll import the <strong><?php echo number_format($limit); ?> most recent records</strong> (sorted by publish date, across the selected types).
                        </p>
                    </div>
                    <div style="background: #e7f3ff; padding: 15px; border-radius: 4px;">
                        <p style="margin: 0; font-size: 13px; color: #646970;">
                            Need more records? <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard')); ?>" target="_blank" style="color: #2271b1; text-decoration: underline; font-weight: 600;">Request an increase from your Queryra dashboard →</a>
                        </p>
                    </div>
                </div>

                <!-- Summary: nothing selected -->
                <div id="queryra-summary-none" style="display: none; background: #fef2f2; padding: 15px 20px; border-radius: 4px; border-left: 4px solid #dc3232;">
                    <p style="margin: 0; font-size: 14px; color: #dc3232;">
                        Please select at least one content type to import.
                    </p>
                </div>
            </div>

            <!-- Info Box -->
            <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin: 25px 0;">
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #646970;">
                    💡 <strong>After import:</strong> You can manage your imported content anytime:
                </p>
                <ul style="margin: 0 0 0 20px; padding: 0; font-size: 13px; color: #646970;">
                    <li>Delete unwanted records from <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/records')); ?>" target="_blank">Queryra Dashboard</a></li>
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
        // Note: queryra_post_types is set by the user in step 2 (see ajax_save_post_types)

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

                <a href="<?php echo esc_url(Queryra_Search::tracked_url('https://queryra.com/dashboard/sync')); ?>"
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

            <?php
            // "Tell your visitors about AI search" tip — same component as Settings tab,
            // shared dismiss state so users don't see it twice after acknowledging.
            if (class_exists('Queryra_Admin')) {
                Queryra_Admin::render_ux_tip();
            }
            ?>

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
     * Setup survey, shown on the wizard screen.
     *
     * Activation redirects to the wizard, so this is what the user sees
     * immediately after activating — before doing any setup, which is the
     * point: asking at the end would lose the answer for everyone who
     * struggles with or abandons installation.
     *
     * Deliberately simple, mirroring the deactivation modal: one screen,
     * one condition (question not dealt with yet), state in one option.
     */
    public function maybe_render_site_profile_prompt() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // ONE screen only: the wizard — where activation lands the user.
        // Same shape as the deactivation modal, which is scoped to the
        // plugins page. No time windows, no extra state to drift.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== 'queryra-setup-wizard') {
            return;
        }

        // ONE condition: a flag saying the question was already dealt with
        // (answered or skipped). No survey data is kept locally — the answer
        // goes out as an event and that's it. The flag is cleared on
        // deactivation, so a deactivate/activate cycle asks again.
        if (get_option('queryra_site_profile_done')) {
            return;
        }

        $this->render_site_profile_modal('wizard');
    }

    /**
     * Site-profile modal markup: TWO questions on ONE screen.
     *
     * Q1 (radio, no default) tells us who the user is; Q2 (multi-select, NO
     * selection limit) is deliberately an AD dressed as a question — the user
     * reads a benefit list of what the product can do (including features
     * they didn't know about: insights, bot protection) at the moment of
     * highest attention, and "sells" them to themselves by clicking. The
     * click distribution is a secondary bonus signal for homepage/pricing
     * copy. Visual pattern recycled from the battle-tested deactivation
     * modal. Self-contained (inline style + vanilla JS) so it works on both
     * the wizard page and the settings screen without extra enqueues.
     *
     * @param string $source wizard|settings
     */
    private function render_site_profile_modal($source) {
        // Several entry points can fire in one request (wizard page render,
        // then admin_footer). Render at most once per page load.
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $ajax_url = admin_url('admin-ajax.php');
        $nonce    = wp_create_nonce('queryra_wizard');
        ?>
        <style>
            #queryra-site-profile-modal {
                position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
            }
            #queryra-site-profile-content {
                position: relative; background: #fff; margin: 4% auto; padding: 0;
                border: 1px solid #dcdcde; border-radius: 4px; width: 90%; max-width: 560px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); max-height: 88vh; overflow-y: auto;
            }
            #queryra-site-profile-header { padding: 20px 25px; border-bottom: 1px solid #dcdcde; background: #f6f7f7; }
            #queryra-site-profile-header h2 { margin: 0; font-size: 18px; line-height: 1.4; }
            #queryra-site-profile-body { padding: 25px; }
            #queryra-site-profile-body h3 { margin: 0 0 12px; font-size: 14px; color: #1d2327; }
            .queryra-sp-option { margin-bottom: 8px; }
            .queryra-sp-option label {
                display: flex; align-items: flex-start; padding: 9px 10px; cursor: pointer;
                border: 1px solid #dcdcde; border-radius: 4px; transition: background 0.15s, border-color 0.15s;
            }
            .queryra-sp-option label:hover { background: #f6f7f7; border-color: #2271b1; }
            .queryra-sp-option input { margin: 3px 8px 0 0; flex-shrink: 0; }
            #queryra-sp-expectations { display: none; margin-top: 22px; }
            #queryra-sp-expectations.active { display: block; }
            .queryra-sp-confirm { display: none; margin: 4px 0 0 30px; font-size: 12px; color: #2271b1; }
            .queryra-sp-confirm.active { display: block; }
            #queryra-site-profile-footer {
                padding: 20px 25px; border-top: 1px solid #dcdcde; background: #f6f7f7;
                display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
            }
            #queryra-site-profile-footer .queryra-sp-skip { color: #646970; text-decoration: none; font-size: 13px; }
            #queryra-site-profile-footer .queryra-sp-skip:hover { color: #135e96; }
            .queryra-sp-helper { flex-basis: 100%; margin: 12px 0 0; font-size: 12px; color: #646970; }
        </style>
        <div id="queryra-site-profile-modal">
            <div id="queryra-site-profile-content">
                <div id="queryra-site-profile-header">
                    <h2>Before we start — a couple of quick questions (optional)</h2>
                </div>
                <div id="queryra-site-profile-body">
                    <h3>What kind of site is this for?</h3>
                    <?php
                    // No option is pre-selected — a default would poison the data.
                    $profiles = array(
                        'store'     => 'An online store — I sell products',
                        'content'   => 'A blog, news or content site',
                        'directory' => 'A directory, catalog or knowledge base',
                        'client'    => "I'm building this for a client",
                        'exploring' => 'Just exploring for now',
                    );
                    foreach ($profiles as $value => $label) :
                    ?>
                    <div class="queryra-sp-option">
                        <label>
                            <input type="radio" name="queryra_sp_profile" value="<?php echo esc_attr($value); ?>">
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>

                    <!-- Q2 slides in only after Q1 is answered (one screen, two beats). -->
                    <div id="queryra-sp-expectations">
                        <h3>What do you want better search to do for your site?</h3>
                        <div class="queryra-sp-option">
                            <label>
                                <input type="checkbox" name="queryra_sp_expect" value="sell_more">
                                <span><strong>Sell more</strong> — customers find what they mean, not just what they type</span>
                            </label>
                        </div>
                        <div class="queryra-sp-option">
                            <label>
                                <input type="checkbox" name="queryra_sp_expect" value="understand">
                                <span><strong>Understand real questions</strong> — typos, full sentences, 100+ languages</span>
                            </label>
                        </div>
                        <div class="queryra-sp-option">
                            <label>
                                <input type="checkbox" name="queryra_sp_expect" value="replace_theme">
                                <span><strong>Replace a theme search</strong> that keeps returning nothing</span>
                            </label>
                        </div>
                        <div class="queryra-sp-option">
                            <label>
                                <input type="checkbox" name="queryra_sp_expect" value="insights" id="queryra-sp-insights">
                                <span><strong>See what visitors search for</strong> — including what they couldn't find</span>
                            </label>
                            <!-- Ad-loop closer: promised → shown where it's delivered. -->
                            <p class="queryra-sp-confirm" id="queryra-sp-insights-confirm">
                                You'll find this in your dashboard under Search History.
                            </p>
                        </div>
                        <div class="queryra-sp-option">
                            <label>
                                <input type="checkbox" name="queryra_sp_expect" value="bot_protection">
                                <span><strong>Stop bots and spam</strong> from flooding your search</span>
                            </label>
                        </div>
                        <div class="queryra-sp-option">
                            <label>
                                <input type="checkbox" name="queryra_sp_expect" value="exploring2">
                                <span>Just exploring</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div id="queryra-site-profile-footer">
                    <a href="#" class="queryra-sp-skip">Skip</a>
                    <button type="button" class="button button-primary" id="queryra-sp-continue" disabled>Continue</button>
                    <p class="queryra-sp-helper">
                        This helps us make the plugin better for sites like yours. Your answer is optional and you can skip it.
                    </p>
                </div>
            </div>
        </div>
        <script>
        (function() {
            var AJAX_URL = <?php echo wp_json_encode($ajax_url); ?>;
            var NONCE    = <?php echo wp_json_encode($nonce); ?>;
            var SOURCE   = <?php echo wp_json_encode($source); ?>;

            var modal = document.getElementById('queryra-site-profile-modal');
            if (!modal) { return; }

            var expectations = document.getElementById('queryra-sp-expectations');
            var continueBtn  = document.getElementById('queryra-sp-continue');

            // Q1 answered → reveal Q2, enable Continue.
            var radios = modal.querySelectorAll('input[name="queryra_sp_profile"]');
            for (var i = 0; i < radios.length; i++) {
                radios[i].addEventListener('change', function() {
                    expectations.classList.add('active');
                    continueBtn.disabled = false;
                });
            }

            // Insights confirmation line (ad-loop closer).
            var insights = document.getElementById('queryra-sp-insights');
            if (insights) {
                insights.addEventListener('change', function() {
                    var confirmLine = document.getElementById('queryra-sp-insights-confirm');
                    if (confirmLine) {
                        confirmLine.classList.toggle('active', insights.checked);
                    }
                });
            }

            function send(params, always) {
                var body = new URLSearchParams();
                body.append('action', 'queryra_wizard_save_site_profile');
                body.append('nonce', NONCE);
                body.append('source', SOURCE);
                for (var key in params) {
                    if (Array.isArray(params[key])) {
                        for (var j = 0; j < params[key].length; j++) {
                            body.append(key + '[]', params[key][j]);
                        }
                    } else {
                        body.append(key, params[key]);
                    }
                }
                fetch(AJAX_URL, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(always, always);
            }

            function close() { modal.style.display = 'none'; }

            // Skip: record the skip LOCALLY so we don't nag again — the
            // handler sends nothing remote for skips (Guideline 7).
            modal.querySelector('.queryra-sp-skip').addEventListener('click', function(e) {
                e.preventDefault();
                send({ skipped: 1 }, close);
            });

            // Clicking the dark backdrop just closes the modal — records
            // nothing, sends nothing (same behavior as the deactivation
            // modal). The question may show again on a later visit.
            modal.addEventListener('click', function(e) {
                if (e.target === modal) { close(); }
            });

            continueBtn.addEventListener('click', function() {
                var picked = modal.querySelector('input[name="queryra_sp_profile"]:checked');
                if (!picked) { return; }
                var expects = [];
                var boxes = modal.querySelectorAll('input[name="queryra_sp_expect"]:checked');
                for (var k = 0; k < boxes.length; k++) { expects.push(boxes[k].value); }
                continueBtn.disabled = true;
                send({ site_profile: picked.value, expectations: expects }, close);
            });
        })();
        </script>
        <?php
    }

    /**
     * AJAX: persist the site-profile answer (or skip).
     *
     * Skip = local record ONLY, nothing leaves the site (wp.org Guideline 7:
     * sending data after the user declined would be automated collection
     * without confirmation). An answer = explicit consent via a visible
     * click, so it is stored locally AND reported: once as a `site_profile`
     * analytics event, and continuously as extra params on the existing
     * /status ping (see Queryra_API::build_status_params()) so the backend
     * can attach it to the account — no new endpoint, no dedicated call path.
     */
    public function ajax_save_site_profile() {
        check_ajax_referer('queryra_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above by check_ajax_referer.
        $skipped = !empty($_POST['skipped']);
        $profile = isset($_POST['site_profile']) ? sanitize_key(wp_unslash($_POST['site_profile'])) : '';
        $source  = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'wizard';

        $allowed_profiles     = array('store', 'content', 'directory', 'client', 'exploring');
        $allowed_expectations = array('sell_more', 'understand', 'replace_theme', 'insights', 'bot_protection', 'exploring2');

        $expectations = array();
        if (isset($_POST['expectations']) && is_array($_POST['expectations'])) {
            foreach (array_map('sanitize_key', wp_unslash($_POST['expectations'])) as $expect) {
                if (in_array($expect, $allowed_expectations, true) && !in_array($expect, $expectations, true)) {
                    $expectations[] = $expect;
                }
            }
        }
        // phpcs:enable

        if (!in_array($source, array('wizard', 'settings'), true)) {
            $source = 'wizard';
        }

        // SKIP: send the interaction STATE only — no answer, no content.
        // Mirrors feedback_status on plugin_deactivated (spec Part I item 3,
        // "Analogicznie wizard: qualification_status"). Guideline 7 boundary:
        // we never transmit a survey answer from someone who declined, but
        // "the question was shown and declined" is state, not user data —
        // and without it a skip is indistinguishable from never having seen
        // the question at all, which is exactly what we need to measure.
        if ($skipped) {
            if (class_exists('Queryra_Analytics')) {
                Queryra_Analytics::track('site_profile', array(
                    'qualification_status' => 'skipped',
                    'site_profile_source'  => $source,
                    'site_profile_at'      => gmdate('c'),
                ));
            }
            update_option('queryra_site_profile_done', 1, false);
            wp_send_json_success();
        }

        if (!in_array($profile, $allowed_profiles, true)) {
            wp_send_json_error(array('message' => 'Invalid profile'));
        }

        // The ANSWER is not stored locally — it goes out as an event and that
        // is the end of it. The event carries instance_id, so the backend
        // attaches it to the right install/account. All we keep on the site is
        // a flag saying it was already sent, so we don't ask twice.
        if (class_exists('Queryra_Analytics')) {
            Queryra_Analytics::track('site_profile', array(
                'qualification_status' => 'submitted',
                'site_profile'         => $profile,
                'expectations'         => $expectations,
                'site_profile_source'  => $source,
                'site_profile_at'      => gmdate('c'),
            ));
        }

        update_option('queryra_site_profile_done', 1, false);

        wp_send_json_success();
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

        // Validate BEFORE saving — the old order (save, then test) meant a
        // mistyped key permanently overwrote a working one: the wizard
        // showed "Invalid API key" but the bad key was already stored,
        // silently breaking auto-sync and search. validate_key() checks
        // the candidate key without mutating any state.
        $api = new Queryra_API();
        $test = $api->validate_key($api_key);

        if ($test !== true) {
            wp_send_json_error(array('message' => 'Invalid API key or connection failed'));
            return;
        }

        // Save API key (only after successful validation)
        update_option('queryra_api_key', $api_key);

        // Track signup completed (API key successfully connected)
        Queryra_Analytics::track('signup_completed');

        wp_send_json_success(array(
            'message' => 'API key saved successfully'
        ));
    }

    /**
     * AJAX: Save selected post types before starting wizard import.
     * The sync endpoints (get_sync_info, sync_batch) then use the
     * queryra_post_types option for the import.
     */
    public function ajax_save_post_types() {
        check_ajax_referer('queryra_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $raw = array();
        if (isset($_POST['post_types']) && is_array($_POST['post_types'])) {
            $raw = array_map('sanitize_key', wp_unslash($_POST['post_types']));
        }

        // Whitelist = any public post type registered on this site, minus
        // internal/system types. Matches Queryra_Admin::sanitize_post_types()
        // so the wizard and the Settings tab accept the exact same set,
        // including custom post types (recipes, vehicles, portfolios, etc.).
        $exclude = array('attachment', 'revision', 'nav_menu_item', 'wp_block');
        $allowed = array_diff(get_post_types(array('public' => true)), $exclude);

        $selected = array();
        foreach ($raw as $type) {
            if (in_array($type, $allowed, true) && !in_array($type, $selected, true)) {
                $selected[] = $type;
            }
        }

        if (empty($selected)) {
            wp_send_json_error(array('message' => 'Select at least one content type.'));
            return;
        }

        update_option('queryra_post_types', array_values(array_unique($selected)));

        wp_send_json_success(array('post_types' => $selected));
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
                    '💡 Want full-time AI search without the daily window? <a href="' . esc_url(Queryra_Search::tracked_url('https://queryra.com/pricing')) . '" target="_blank" style="color: #2271b1; font-weight: 600;">Upgrade to STARTER plan</a>',
                    $time_text,
                    $next_time
                );

                // is_html: this message is plugin-authored markup (upgrade
                // link); wizard.js renders it as HTML only when flagged —
                // every other error message gets escaped client-side.
                wp_send_json_error(array('message' => $friendly_message, 'is_html' => true));
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
