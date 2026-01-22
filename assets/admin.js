jQuery(document).ready(function($) {

    // Check if API key is saved - disable Test Connection if not
    function updateTestConnectionButton() {
        var $button = $('#queryra-test-connection');
        var $hint = $('#queryra-test-hint');

        // Check if API key exists (passed from PHP)
        if (!queryraData.hasApiKey) {
            $button.prop('disabled', true).addClass('button-disabled');
            if ($hint.length === 0) {
                $button.after('<p id="queryra-test-hint" class="description" style="color: #999; margin-top: 8px;"><span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span> Save settings first to enable connection test</p>');
            }
        } else {
            $button.prop('disabled', false).removeClass('button-disabled');
            $hint.remove();
        }
    }

    // Initialize on page load
    updateTestConnectionButton();

    // Test Connection
    $('#queryra-test-connection').on('click', function() {
        var $button = $(this);
        var $status = $('#queryra-connection-status');

        // Disable button
        $button.prop('disabled', true);

        // Show loading
        $status.html('<span class="queryra-spinner"></span> Testing connection...')
               .removeClass('queryra-status-success queryra-status-error')
               .addClass('queryra-status-loading');

        // Make AJAX request
        $.ajax({
            url: queryraData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_test_connection',
                nonce: queryraData.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('✓ ' + response.data.message)
                           .removeClass('queryra-status-loading queryra-status-error')
                           .addClass('queryra-status-success');
                } else {
                    // Parse error message (could be string or array of validation errors)
                    var errorMsg = response.data.message;
                    if (Array.isArray(errorMsg)) {
                        // FastAPI validation errors
                        errorMsg = errorMsg.map(function(err) {
                            return err.loc ? err.loc.join('.') + ': ' + err.msg : JSON.stringify(err);
                        }).join('<br>');
                    } else if (typeof errorMsg === 'object') {
                        errorMsg = JSON.stringify(errorMsg, null, 2);
                    }

                    $status.html('✗ ' + errorMsg)
                           .removeClass('queryra-status-loading queryra-status-success')
                           .addClass('queryra-status-error');
                }
            },
            error: function(xhr, status, error) {
                $status.html('✗ Connection failed: ' + error)
                       .removeClass('queryra-status-loading queryra-status-success')
                       .addClass('queryra-status-error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Send All Posts
    $('#queryra-sync-all').on('click', function() {
        var $button = $(this);
        var $status = $('#queryra-sync-status');

        if (!confirm('This will send all published posts and pages to Queryra. Continue?')) {
            return;
        }

        // Disable button
        $button.prop('disabled', true);

        // Show loading
        $status.html('<span class="queryra-spinner"></span> Sending posts...')
               .removeClass('queryra-status-success queryra-status-error')
               .addClass('queryra-status-loading')
               .show();

        // Make AJAX request
        $.ajax({
            url: queryraData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_sync_all',
                nonce: queryraData.nonce
            },
            success: function(response) {
                if (response.success) {
                    var successHtml = '<div style="margin-top: 15px;">';
                    successHtml += '✓ ' + response.data.message;
                    successHtml += '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 12px;">';
                    successHtml += '<p style="margin: 0 0 8px 0;"><strong>⚠️ Next step required:</strong></p>';
                    successHtml += '<p style="margin: 0 0 8px 0;">Records are sent but need to be synced in Queryra dashboard to generate AI embeddings and become searchable.</p>';
                    successHtml += '<a href="https://queryra.com/dashboard/sync" target="_blank" class="button button-primary" style="margin-top: 8px;">Go to Queryra Dashboard →</a>';
                    successHtml += '</div></div>';

                    $status.html(successHtml)
                           .removeClass('queryra-status-loading queryra-status-error')
                           .addClass('queryra-status-success');

                    // Reload page after 5 seconds to refresh stats
                    setTimeout(function() {
                        location.reload();
                    }, 5000);
                } else {
                    // Parse error message (could be string or array of validation errors)
                    var errorMsg = response.data.message;
                    if (Array.isArray(errorMsg)) {
                        // FastAPI validation errors
                        errorMsg = errorMsg.map(function(err) {
                            return err.loc ? err.loc.join('.') + ': ' + err.msg : JSON.stringify(err);
                        }).join('<br>');
                    } else if (typeof errorMsg === 'object') {
                        errorMsg = JSON.stringify(errorMsg, null, 2);
                    }

                    $status.html('✗ ' + errorMsg)
                           .removeClass('queryra-status-loading queryra-status-success')
                           .addClass('queryra-status-error');
                }
            },
            error: function(xhr, status, error) {
                $status.html('✗ Send failed: ' + error)
                       .removeClass('queryra-status-loading queryra-status-success')
                       .addClass('queryra-status-error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

});
