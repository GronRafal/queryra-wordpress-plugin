jQuery(document).ready(function($) {

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
                    $status.html('✗ ' + response.data.message)
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

    // Sync All Posts
    $('#queryra-sync-all').on('click', function() {
        var $button = $(this);
        var $status = $('#queryra-sync-status');

        if (!confirm('This will sync all published posts to Queryra. Continue?')) {
            return;
        }

        // Disable button
        $button.prop('disabled', true);

        // Show loading
        $status.html('<span class="queryra-spinner"></span> Syncing posts...')
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
                    $status.html('✓ ' + response.data.message)
                           .removeClass('queryra-status-loading queryra-status-error')
                           .addClass('queryra-status-success');

                    // Reload page after 2 seconds to refresh stats
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $status.html('✗ ' + response.data.message)
                           .removeClass('queryra-status-loading queryra-status-success')
                           .addClass('queryra-status-error');
                }
            },
            error: function(xhr, status, error) {
                $status.html('✗ Sync failed: ' + error)
                       .removeClass('queryra-status-loading queryra-status-success')
                       .addClass('queryra-status-error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

});
