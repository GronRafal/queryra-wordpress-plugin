jQuery(document).ready(function($) {

    // Import All to Queryra (batched)
    $('#queryra-sync-all').on('click', function() {
        var $button = $(this);
        var $status = $('#queryra-sync-status');

        if (!confirm('This will import all published content to Queryra. Continue?')) {
            return;
        }

        $button.prop('disabled', true);

        // Phase 1: Check plan limits
        $status.html('<span class="queryra-spinner"></span> Checking plan limits...')
               .removeClass('queryra-status-success queryra-status-error')
               .addClass('queryra-status-loading')
               .show();

        $.ajax({
            url: queryraData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_get_sync_info',
                nonce: queryraData.nonce
            },
            success: function(response) {
                if (!response.success) {
                    showSyncError($status, $button, response.data.message);
                    return;
                }

                var info = response.data;

                // Plan is full
                if (info.record_limit > 0 && info.current_records >= info.record_limit) {
                    showSyncError($status, $button,
                        'Plan limit reached (' + info.current_records + '/' + info.record_limit +
                        ' records). <a href="https://queryra.com/pricing" target="_blank">Upgrade your plan</a> to import more content.');
                    return;
                }

                // No posts to sync
                if (info.will_sync === 0) {
                    showSyncError($status, $button, 'No published content found to import.');
                    return;
                }

                // Phase 2: Start batched import
                startBatchedSync($button, $status, info);
            },
            error: function(xhr, status, error) {
                showSyncError($status, $button, 'Failed to check plan limits: ' + error);
            }
        });
    });

    function startBatchedSync($button, $status, info) {
        var offset = 0;
        var totalSynced = 0;
        var willSync = info.will_sync;
        var batchSize = info.batch_size;

        // Show progress UI
        var limitNote = '';
        if (info.record_limit > 0 && info.total_wp_posts > info.record_limit) {
            limitNote = '<p style="margin: 8px 0 0 0; font-size: 12px; color: #dba617;">' +
                'Plan limit: ' + info.record_limit + ' records. ' +
                info.total_wp_posts + ' available in WordPress. Importing most recent ' + willSync + '.' +
                '</p>';
        }

        var progressHtml =
            '<div style="margin-top: 15px;">' +
                '<p id="queryra-batch-status" style="margin: 0 0 10px 0; font-size: 14px; color: #1d2327;">' +
                    '<span class="queryra-spinner"></span> Importing content... Please keep this tab open.' +
                '</p>' +
                '<div style="background: #dcdcde; height: 20px; border-radius: 10px; overflow: hidden;">' +
                    '<div id="queryra-batch-bar" style="background: linear-gradient(90deg, #2271b1 0%, #135e96 100%); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; font-weight: 600; min-width: 30px;">0%</div>' +
                '</div>' +
                '<p id="queryra-batch-info" style="margin: 8px 0 0 0; font-size: 13px; color: #646970;">0 / ' + willSync + ' records</p>' +
                limitNote +
            '</div>';
        $status.html(progressHtml).show();

        function sendNextBatch() {
            if (totalSynced >= willSync) {
                onSyncComplete();
                return;
            }

            var remaining = willSync - totalSynced;
            var currentBatch = Math.min(batchSize, remaining);

            $.ajax({
                url: queryraData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'queryra_sync_batch',
                    nonce: queryraData.nonce,
                    offset: offset,
                    batch_size: currentBatch
                },
                success: function(response) {
                    if (!response.success) {
                        onSyncError(response.data.message);
                        return;
                    }

                    var synced = response.data.synced;

                    if (synced === 0) {
                        onSyncComplete();
                        return;
                    }

                    totalSynced += synced;
                    offset += synced;

                    var percent = Math.min(Math.round((totalSynced / willSync) * 100), 100);
                    $('#queryra-batch-bar').css('width', percent + '%').text(percent + '%');
                    $('#queryra-batch-info').text(totalSynced + ' / ' + willSync + ' records');

                    sendNextBatch();
                },
                error: function(xhr, status, error) {
                    onSyncError('Batch failed: ' + error);
                }
            });
        }

        function onSyncComplete() {
            $('#queryra-batch-bar').css('width', '100%').text('100%');
            $('#queryra-batch-info').text(totalSynced + ' / ' + willSync + ' records');
            $('#queryra-batch-status').html(
                '<span class="dashicons dashicons-yes-alt" style="font-size: 20px; width: 20px; height: 20px; vertical-align: middle; color: #46b450; margin-right: 5px;"></span>' +
                '<strong style="color: #46b450;">Successfully imported ' + totalSynced + ' records to Queryra</strong>'
            );
            $button.prop('disabled', false);

            setTimeout(function() { location.reload(); }, 5000);
        }

        function onSyncError(msg) {
            $('#queryra-batch-status').html(
                '<span style="color: #dc3232;">Error: ' + msg + '</span><br>' +
                '<small style="color: #646970;">Already imported records are safe. You can re-run import anytime.</small>'
            );
            $button.prop('disabled', false);
        }

        sendNextBatch();
    }

    function showSyncError($status, $button, msg) {
        $status.html('<span style="color: #dc3232;">' + msg + '</span>')
               .removeClass('queryra-status-loading')
               .addClass('queryra-status-error');
        $button.prop('disabled', false);
    }

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
