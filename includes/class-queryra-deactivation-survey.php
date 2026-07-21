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
            'pluginSlug' => QUERYRA_PLUGIN_BASENAME // Dynamic: queryra-ai-search/queryra-ai-search.php
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
                margin-top: 14px;
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
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
            }

            .queryra-deactivation-disclosure {
                flex-basis: 100%;
                margin: 12px 0 0;
                font-size: 12px;
                color: #646970;
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

                    <?php
                    // ONE shared textarea below the list, with a prompt that
                    // swaps per reason (data-prompt). Two bugs this avoids,
                    // both hit in real use:
                    //  1. Per-reason textareas meant switching the radio
                    //     silently dropped whatever had already been typed —
                    //     submit only ever read the SELECTED reason's field.
                    //  2. Reasons without a textarea ("temporary", "don't
                    //     need it") left people with nothing to write in.
                    // The spec's requirement is a question-specific PROMPT,
                    // which the swapping placeholder satisfies exactly.
                    $reasons = array(
                        'not_working'      => array('It\'s not working', 'What happened?'),
                        'found_better'     => array('I found a better plugin', 'Which plugin are you switching to?'),
                        'temporary'        => array('It\'s a temporary deactivation', 'Anything we could do better before you come back? (optional)'),
                        'missing_features' => array('Missing features I need', 'What was missing?'),
                        // First willingness-to-pay signal we collect anywhere.
                        'too_expensive'    => array('Too expensive', 'What price would make sense for your store?'),
                        'trial_limits'     => array('The trial limits were too small for my store', 'Which limit did you hit first? (records, searches...)'),
                        'dont_need'        => array('I don\'t need it anymore', 'Tell us more (optional)'),
                        'other'            => array('Other', 'Tell us more (optional)'),
                    );
                    ?>
                    <div class="queryra-deactivation-reasons">
                        <?php foreach ($reasons as $value => $reason) : ?>
                        <div class="queryra-deactivation-reason">
                            <label>
                                <input type="radio" name="queryra_deactivation_reason"
                                       value="<?php echo esc_attr($value); ?>"
                                       data-prompt="<?php echo esc_attr($reason[1]); ?>">
                                <span class="queryra-deactivation-reason-text">
                                    <strong><?php echo esc_html($reason[0]); ?></strong>
                                </span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="queryra-deactivation-reason-details" id="queryra-deactivation-details-wrap">
                        <textarea id="queryra-deactivation-details" placeholder="Tell us more (optional)"></textarea>
                    </div>

                    <!-- Optional reply-to. Lesson from a real case: our reply to a
                         user's feedback bounced off a nonexistent wordpress@ sender
                         address, so we ask for a real address to answer at. -->
                    <div class="queryra-deactivation-email" style="margin-top: 15px;">
                        <label for="queryra-deactivation-reply-email" style="display: block; margin-bottom: 5px; color: #50575e; font-size: 13px;">
                            Your email, if you'd like a reply (optional)
                        </label>
                        <input type="email" id="queryra-deactivation-reply-email" placeholder="you@example.com"
                               style="width: 100%; padding: 8px; border: 1px solid #dcdcde; border-radius: 3px;">
                    </div>
                </div>
                <div id="queryra-deactivation-modal-footer">
                    <a href="#" class="queryra-deactivation-skip">Skip & Deactivate</a>
                    <div>
                        <button type="button" class="button button-secondary" id="queryra-deactivation-cancel">Cancel</button>
                        <button type="button" class="button button-primary" id="queryra-deactivation-submit" disabled>Submit & Deactivate</button>
                    </div>
                    <!-- Guideline 7 disclosure: the URL/version are part of the
                         payload, so we say it where the user clicks Submit —
                         that click after this text is the explicit consent
                         (works for users without a Queryra account too). -->
                    <p class="queryra-deactivation-disclosure">
                        Your site URL, plugin version and the email you enter (if any) are included with your feedback.
                    </p>
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

        // Deactivating plugins requires this capability — so does sending
        // feedback about deactivating them.
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $reason      = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';
        $details     = isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '';
        $reply_email = isset($_POST['reply_email']) ? sanitize_email(wp_unslash($_POST['reply_email'])) : '';

        // Store feedback locally (optional - can also send to API)
        $feedback_data = array(
            'reason' => $reason,
            'details' => $details,
            'reply_email' => $reply_email,
            'date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'plugin_version' => QUERYRA_VERSION,
            'wp_version' => get_bloginfo('version')
        );

        // Save to WordPress option (keeps history of all feedback)
        $all_feedback = get_option('queryra_deactivation_feedback', array());
        $all_feedback[] = $feedback_data;
        update_option('queryra_deactivation_feedback', $all_feedback, false);

        // EVENT is the source of truth (not the email — mailboxes bounce and
        // get lost; events land next to the instance in the analytics panel).
        // Consent basis: the modal shows an explicit disclosure directly under
        // the Submit button ("Your site URL and plugin version are included
        // with your feedback"), and reply_email is volunteered by the user.
        // This runs BEFORE the actual deactivation redirect, so the plugin is
        // still active and the event fires normally.
        if (class_exists('Queryra_Analytics')) {
            $event_meta = array(
                'reason'      => $reason,
                'details'     => mb_substr($details, 0, 500),
                'reply_email' => $reply_email,
                'site_url'    => get_site_url(),
            );

            // The analytics layer replaces meta WHOLESALE with a truncation
            // stub when its JSON exceeds ~2 KB. 500 non-Latin characters
            // JSON-escape to ~6 bytes each and would blow that budget —
            // losing reason and reply_email too. Shrink only the details
            // until the encoded meta fits, so the high-value fields always
            // survive (a Bulgarian "what was missing" answer is exactly the
            // kind of feedback this event exists to carry).
            while (strlen((string) wp_json_encode($event_meta)) > 1900 && mb_strlen($event_meta['details']) > 0) {
                $event_meta['details'] = mb_substr(
                    $event_meta['details'],
                    0,
                    (int) floor(mb_strlen($event_meta['details']) * 0.7)
                );
            }

            Queryra_Analytics::track('deactivation_feedback', $event_meta);
        }

        // Courtesy notification only — the event above is authoritative.
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

        if (!empty($feedback_data['reply_email'])) {
            $body .= "REPLY TO: " . $feedback_data['reply_email'] . "\n\n";
        }

        $body .= "---\n\n";
        $body .= "Site URL: " . $feedback_data['site_url'] . "\n";
        $body .= "Plugin Version: " . $feedback_data['plugin_version'] . "\n";
        $body .= "WordPress Version: " . $feedback_data['wp_version'] . "\n";
        $body .= "Date: " . $feedback_data['date'] . "\n";

        // Email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: WordPress <wordpress@' . wp_parse_url(get_site_url(), PHP_URL_HOST) . '>'
        );

        // Replying to the notification should reach the actual person, not
        // the (often nonexistent) wordpress@ sender address.
        if (!empty($feedback_data['reply_email'])) {
            $headers[] = 'Reply-To: ' . $feedback_data['reply_email'];
        }

        // Send email (non-blocking - if it fails, don't break deactivation)
        @wp_mail($to, $subject, $body, $headers);
    }
}
