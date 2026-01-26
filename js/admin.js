jQuery(document).ready(function($) {

    // Send All Posts
    $('#queryra-sync-all').on('click', function() {
        var $button = $(this);
        var $status = $('#queryra-sync-status');

        if (!confirm('⚠️ WARNING: Import will reset synchronization!\n\nAll imported records will become unsynced and AI search will stop working until you run Sync again from Queryra Dashboard.\n\nContinue with import?')) {
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
                    var successHtml = '<div style="background: #e7f5e7; border-left: 4px solid #46b450; padding: 15px; margin-top: 15px; border-radius: 3px;">';
                    successHtml += '<p style="margin: 0 0 10px 0; color: #46b450; font-size: 16px;">';
                    successHtml += '<span class="dashicons dashicons-yes-alt" style="font-size: 20px; width: 20px; height: 20px; vertical-align: middle; margin-right: 5px;"></span>';
                    successHtml += '<strong>' + response.data.message + '</strong>';
                    successHtml += '</p>';
                    successHtml += '<p style="margin: 0 0 10px 0; color: #2c3338; font-size: 14px;">';
                    successHtml += 'View and manage records in Queryra Dashboard';
                    successHtml += '</p>';
                    successHtml += '<a href="https://queryra.com/dashboard/records" target="_blank" class="button button-secondary" style="margin-top: 5px;">';
                    successHtml += 'Open Dashboard';
                    successHtml += '<span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px; margin-left: 5px; vertical-align: middle;"></span>';
                    successHtml += '</a>';
                    successHtml += '</div>';

                    $status.html(successHtml);

                    // Reload page after 9 seconds to refresh stats
                    setTimeout(function() {
                        location.reload();
                    }, 9000);
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

    // Clear Cache
    $('#queryra-clear-cache').on('click', function() {
        var $button = $(this);
        var $status = $('#queryra-cache-status');

        if (!confirm('Clear all cached search results? Next searches will fetch fresh data from Queryra API.')) {
            return;
        }

        // Disable button
        $button.prop('disabled', true);

        // Show loading
        $status.html('<span class="queryra-spinner"></span> Clearing...')
               .css('color', '#666');

        // Make AJAX request
        $.ajax({
            url: queryraData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_clear_cache',
                nonce: queryraData.cacheNonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('✓ Cache cleared')
                           .css('color', '#46b450');

                    // Reload page after 2 seconds to update cache count
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $status.html('✗ ' + response.data)
                           .css('color', '#dc3232');
                }
            },
            error: function(xhr, status, error) {
                $status.html('✗ Failed: ' + error)
                       .css('color', '#dc3232');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

});
