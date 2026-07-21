/**
 * Queryra Deactivation Survey JavaScript
 */

jQuery(document).ready(function($) {

    var modal = $('#queryra-deactivation-modal');
    var deactivateLink = '';

    // Intercept deactivate link click
    // The plugin path is passed from PHP via localized script
    var pluginPath = queryraDeactivation.pluginSlug;

    // Find the exact plugin row using data-plugin attribute
    var $pluginRow = $('tr[data-plugin="' + pluginPath + '"]');

    if ($pluginRow.length === 0) {
        // Fallback: try to find by slug (folder name)
        var slug = pluginPath.split('/')[0];
        $pluginRow = $('tr[data-slug="' + slug + '"]');
    }

    // If found, attach click handler to deactivate link
    if ($pluginRow.length > 0) {
        var $deactivateLink = $pluginRow.find('.deactivate a');

        if ($deactivateLink.length > 0) {
            $deactivateLink.on('click', function(e) {
                e.preventDefault();
                deactivateLink = $(this).attr('href');
                modal.fadeIn(200);
            });
            console.log('Queryra: Deactivation survey attached successfully');
        }
    } else {
        // Debug info
        console.log('Queryra: Could not find plugin row');
        console.log('Looking for:', pluginPath);
    }

    // One shared textarea: reveal it and swap the prompt to match the chosen
    // reason. Whatever the user already typed is deliberately KEPT when they
    // change their mind — with per-reason fields it used to be silently
    // dropped, because submit only read the selected reason's textarea.
    $('input[name="queryra_deactivation_reason"]').on('change', function() {
        var $wrap = $('#queryra-deactivation-details-wrap');
        var prompt = $(this).data('prompt');

        if (prompt) {
            $('#queryra-deactivation-details').attr('placeholder', prompt);
        }

        $wrap.addClass('active');
        $('#queryra-deactivation-details').focus();

        // Enable submit button
        $('#queryra-deactivation-submit').prop('disabled', false);
    });

    // Cancel button - close modal
    $('#queryra-deactivation-cancel').on('click', function() {
        modal.fadeOut(200);
        resetModal();
    });

    // Skip link — deactivate without any survey ANSWER. The interaction
    // STATE (feedback_status=skipped) rides as a flag on the deactivation
    // request itself — a transmission that happens regardless of the modal —
    // so the backend can tell "saw the modal, declined" from "WP-CLI/bulk
    // deactivation, modal never existed" (= no flag = not_shown). No survey
    // payload is ever sent for a skip (Guideline 7 boundary per spec
    // 2026-07-20, Part I item 3).
    $('.queryra-deactivation-skip').on('click', function(e) {
        e.preventDefault();
        modal.fadeOut(200);
        window.location.href = deactivateLink + '&queryra_fb=skipped';
    });

    // Submit button - send feedback and deactivate
    $('#queryra-deactivation-submit').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();

        var reason = $('input[name="queryra_deactivation_reason"]:checked').val();
        // Single shared field — nothing to hunt for, nothing to lose.
        var details = $('#queryra-deactivation-details').val() || '';

        // Disable button and show loading
        $button.prop('disabled', true).text('Submitting...');

        // Send feedback via AJAX
        $.ajax({
            url: queryraDeactivation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_deactivation_feedback',
                nonce: queryraDeactivation.nonce,
                reason: reason,
                details: details,
                reply_email: $('#queryra-deactivation-reply-email').val()
            },
            success: function(response) {
                // Close modal
                modal.fadeOut(200);

                // Redirect to deactivate, flagging feedback_status=submitted
                window.location.href = deactivateLink + '&queryra_fb=submitted';
            },
            error: function() {
                // Even if AJAX fails, still deactivate (the answer may be
                // lost, but the interaction state still rides the flag)
                modal.fadeOut(200);
                window.location.href = deactivateLink + '&queryra_fb=submitted';
            }
        });
    });

    // Close modal when clicking outside
    modal.on('click', function(e) {
        if (e.target === modal[0]) {
            modal.fadeOut(200);
            resetModal();
        }
    });

    // Reset modal state
    function resetModal() {
        $('input[name="queryra_deactivation_reason"]').prop('checked', false);
        $('#queryra-deactivation-details-wrap').removeClass('active');
        $('#queryra-deactivation-details').val('');
        $('#queryra-deactivation-reply-email').val('');
        $('#queryra-deactivation-submit').prop('disabled', true).text('Submit & Deactivate');
    }

});
