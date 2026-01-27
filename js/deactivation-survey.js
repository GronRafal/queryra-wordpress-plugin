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

    // Show/hide reason details when radio button selected
    $('input[name="queryra_deactivation_reason"]').on('change', function() {
        // Hide all details
        $('.queryra-deactivation-reason-details').removeClass('active');

        // Show details for selected reason (if it has a textarea)
        var $details = $(this).closest('.queryra-deactivation-reason').find('.queryra-deactivation-reason-details');
        if ($details.length > 0) {
            $details.addClass('active');
            $details.find('textarea').focus();
        }

        // Enable submit button
        $('#queryra-deactivation-submit').prop('disabled', false);
    });

    // Cancel button - close modal
    $('#queryra-deactivation-cancel').on('click', function() {
        modal.fadeOut(200);
        resetModal();
    });

    // Skip link - deactivate without feedback
    $('.queryra-deactivation-skip').on('click', function(e) {
        e.preventDefault();
        modal.fadeOut(200);
        window.location.href = deactivateLink;
    });

    // Submit button - send feedback and deactivate
    $('#queryra-deactivation-submit').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();

        var reason = $('input[name="queryra_deactivation_reason"]:checked').val();
        var $details = $('input[name="queryra_deactivation_reason"]:checked')
            .closest('.queryra-deactivation-reason')
            .find('textarea');
        var details = $details.length > 0 ? $details.val() : '';

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
                details: details
            },
            success: function(response) {
                // Close modal
                modal.fadeOut(200);

                // Redirect to deactivate
                window.location.href = deactivateLink;
            },
            error: function() {
                // Even if AJAX fails, still deactivate
                modal.fadeOut(200);
                window.location.href = deactivateLink;
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
        $('.queryra-deactivation-reason-details').removeClass('active');
        $('.queryra-deactivation-reason-details textarea').val('');
        $('#queryra-deactivation-submit').prop('disabled', true).text('Submit & Deactivate');
    }

});
