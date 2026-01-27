<?php
/**
 * Queryra Deactivation Survey
 *
 * Shows a feedback modal when users deactivate the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Deactivation_Survey {

    /**
     * Constructor
     */
    public function __construct() {
        // Only run on plugins page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_footer', array($this, 'render_modal'));

        // AJAX handler for feedback submission
        add_action('wp_ajax_queryra_deactivation_feedback', array($this, 'ajax_handle_feedback'));
    }

    /**
     * Enqueue scripts only on plugins page
     */
    public function enqueue_scripts($hook) {
        // Only load on plugins.php page
        if ($hook !== 'plugins.php') {
            return;
        }

        // Add inline styles (wp-admin is always loaded on admin pages)
        wp_enqueue_style('wp-admin');
        wp_add_inline_style('wp-admin', $this->get_modal_styles());

        // Enqueue modal JavaScript
        wp_enqueue_script(
            'queryra-deactivation-survey',
            QUERYRA_PLUGIN_URL . 'js/deactivation-survey.js',
            array('jquery'),
            QUERYRA_VERSION,
            true
        );

        // Localize script
        wp_localize_script('queryra-deactivation-survey', 'queryraDeactivation', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('queryra_deactivation'),
            'pluginSlug' => QUERYRA_PLUGIN_BASENAME // Dynamic: queryra-wordpress-plugin/queryra-search.php
        ));
    }

    /**
     * Get modal CSS styles
     */
    private function get_modal_styles() {
        return '
            #queryra-deactivation-modal {
                display: none;
                position: fixed;
                z-index: 999999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
            }

            #queryra-deactivation-modal-content {
                position: relative;
                background-color: #fff;
                margin: 5% auto;
                padding: 0;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                width: 90%;
                max-width: 550px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            }

            #queryra-deactivation-modal-header {
                padding: 20px 25px;
                border-bottom: 1px solid #dcdcde;
                background: #f6f7f7;
            }

            #queryra-deactivation-modal-header h2 {
                margin: 0;
                font-size: 18px;
                line-height: 1.4;
            }

            #queryra-deactivation-modal-body {
                padding: 25px;
            }

            #queryra-deactivation-modal-body p {
                margin: 0 0 15px 0;
                color: #50575e;
            }

            .queryra-deactivation-reason {
                margin-bottom: 12px;
            }

            .queryra-deactivation-reason label {
                display: flex;
                align-items: flex-start;
                padding: 8px;
                cursor: pointer;
                border-radius: 3px;
                transition: background 0.15s;
            }

            .queryra-deactivation-reason label:hover {
                background: #f6f7f7;
            }

            .queryra-deactivation-reason input[type="radio"] {
                margin: 3px 8px 0 0;
                flex-shrink: 0;
            }

            .queryra-deactivation-reason-text {
                flex: 1;
            }

            .queryra-deactivation-reason-details {
                display: none;
                margin-top: 10px;
                padding-left: 28px;
            }

            .queryra-deactivation-reason-details.active {
                display: block;
            }

            .queryra-deactivation-reason-details textarea {
                width: 100%;
                min-height: 80px;
                padding: 8px;
                border: 1px solid #dcdcde;
                border-radius: 3px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                font-size: 13px;
                resize: vertical;
            }

            #queryra-deactivation-modal-footer {
                padding: 20px 25px;
                border-top: 1px solid #dcdcde;
                background: #f6f7f7;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            #queryra-deactivation-modal-footer .button {
                margin-left: 10px;
            }

            .queryra-deactivation-skip {
                color: #646970;
                text-decoration: none;
                font-size: 13px;
            }

            .queryra-deactivation-skip:hover {
                color: #135e96;
            }
        ';
    }

    /**
     * Render modal HTML
     */
    public function render_modal() {
        // Only render on plugins page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'plugins') {
            return;
        }

        ?>
        <div id="queryra-deactivation-modal">
            <div id="queryra-deactivation-modal-content">
                <div id="queryra-deactivation-modal-header">
                    <h2>Quick Feedback</h2>
                </div>
                <div id="queryra-deactivation-modal-body">
                    <p>If you have a moment, please let us know why you're deactivating Queryra:</p>

                    <div class="queryra-deactivation-reasons">
                        <div class="queryra-deactivation-reason">
                            <label>
                                <input type="radio" name="queryra_deactivation_reason" value="not_working">
                                <span class="queryra-deactivation-reason-text">
                                    <strong>It's not working</strong>
                                </span>
                            </label>
                            <div class="queryra-deactivation-reason-details">
                                <textarea placeholder="What isn't working? We'd love to help fix it..."></textarea>
                            </div>
                        </div>

                        <div class="queryra-deactivation-reason">
                            <label>
                                <input type="radio" name="queryra_deactivation_reason" value="found_better">
                                <span class="queryra-deactivation-reason-text">
                                    <strong>I found a better plugin</strong>
                                </span>
                            </label>
                            <div class="queryra-deactivation-reason-details">
                                <textarea placeholder="Which plugin are you switching to?"></textarea>
                            </div>
                        </div>

                        <div class="queryra-deactivation-reason">
                            <label>
                                <input type="radio" name="queryra_deactivation_reason" value="temporary">
                                <span class="queryra-deactivation-reason-text">
                                    <strong>It's a temporary deactivation</strong>
                                </span>
                            </label>
                        </div>

                        <div class="queryra-deactivation-reason">
                            <label>
                                <input type="radio" name="queryra_deactivation_reason" value="missing_features">
                                <span class="queryra-deactivation-reason-text">
                                    <strong>Missing features I need</strong>
                                </span>
                            </label>
                            <div class="queryra-deactivation-reason-details">
                                <textarea placeholder="What features would you like to see?"></textarea>
                            </div>
                        </div>

                        <div class="queryra-deactivation-reason">
                            <label>
                                <input type="radio" name="queryra_deactivation_reason" value="too_expensive">
                                <span class="queryra-deactivation-reason-text">
                                    <strong>Too expensive</strong>
                                </span>
                            </label>
                            <div class="queryra-deactivation-reason-details">
                                <textarea placeholder="What pricing would work better for you?"></textarea>
                            </div>
                        </div>

                        <div class="queryra-deactivation-reason">
                            <label>
                                <input type="radio" name="queryra_deactivation_reason" value="dont_need">
                                <span class="queryra-deactivation-reason-text">
                                    <strong>I don't need it anymore</strong>
                                </span>
                            </label>
                        </div>

                        <div class="queryra-deactivation-reason">
                            <label>
                                <input type="radio" name="queryra_deactivation_reason" value="other">
                                <span class="queryra-deactivation-reason-text">
                                    <strong>Other</strong>
                                </span>
                            </label>
                            <div class="queryra-deactivation-reason-details">
                                <textarea placeholder="Please share your reason..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="queryra-deactivation-modal-footer">
                    <a href="#" class="queryra-deactivation-skip">Skip & Deactivate</a>
                    <div>
                        <button type="button" class="button button-secondary" id="queryra-deactivation-cancel">Cancel</button>
                        <button type="button" class="button button-primary" id="queryra-deactivation-submit" disabled>Submit & Deactivate</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle feedback submission
     */
    public function ajax_handle_feedback() {
        check_ajax_referer('queryra_deactivation', 'nonce');

        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        $details = isset($_POST['details']) ? sanitize_textarea_field($_POST['details']) : '';

        // Store feedback locally (optional - can also send to API)
        $feedback_data = array(
            'reason' => $reason,
            'details' => $details,
            'date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'plugin_version' => QUERYRA_VERSION,
            'wp_version' => get_bloginfo('version')
        );

        // Save to WordPress option (keeps history of all feedback)
        $all_feedback = get_option('queryra_deactivation_feedback', array());
        $all_feedback[] = $feedback_data;
        update_option('queryra_deactivation_feedback', $all_feedback, false);

        // Send feedback via email
        $this->send_feedback_via_email($feedback_data);

        wp_send_json_success(array(
            'message' => 'Thank you for your feedback!'
        ));
    }

    /**
     * Send feedback via email
     *
     * @param array $feedback_data Feedback data to send
     */
    private function send_feedback_via_email($feedback_data) {
        // Email recipient
        $to = 'support@queryra.com';

        // Email subject
        $subject = sprintf(
            '[Queryra Plugin] Deactivation Feedback - %s',
            ucwords(str_replace('_', ' ', $feedback_data['reason']))
        );

        // Build email body
        $body = "New deactivation feedback received:\n\n";
        $body .= "REASON: " . ucwords(str_replace('_', ' ', $feedback_data['reason'])) . "\n\n";

        if (!empty($feedback_data['details'])) {
            $body .= "DETAILS:\n" . $feedback_data['details'] . "\n\n";
        }

        $body .= "---\n\n";
        $body .= "Site URL: " . $feedback_data['site_url'] . "\n";
        $body .= "Plugin Version: " . $feedback_data['plugin_version'] . "\n";
        $body .= "WordPress Version: " . $feedback_data['wp_version'] . "\n";
        $body .= "Date: " . $feedback_data['date'] . "\n";

        // Email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: WordPress <wordpress@' . parse_url(get_site_url(), PHP_URL_HOST) . '>'
        );

        // Send email (non-blocking - if it fails, don't break deactivation)
        @wp_mail($to, $subject, $body, $headers);
    }
}
