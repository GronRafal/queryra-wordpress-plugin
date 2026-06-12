jQuery(document).ready(function($) {

    // Escape a value before concatenating it into HTML. Server error
    // messages can embed raw Queryra API response text — injecting them
    // unescaped into admin pages is an XSS sink.
    function escapeHtml(value) {
        return $('<div>').text(String(value)).html();
    }

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
                error: function(xhr, textStatus, error) {
                    // Build a meaningful message — the bare jQuery error string
                    // ("error" / "timeout") is useless for diagnosing a stuck
                    // batch when the same condition will reproduce on retry.
                    var httpStatus = xhr && xhr.status ? xhr.status : 0;
                    var responseExcerpt = xhr && xhr.responseText
                        ? String(xhr.responseText).substring(0, 300)
                        : '';
                    var userMessage = 'Batch failed';

                    if (httpStatus === 0) {
                        userMessage += ' — the request was interrupted (likely a timeout or hosting limit). Try uploading Posts + Pages first, then Products separately from Settings.';
                    } else if (httpStatus === 413) {
                        userMessage += ' (HTTP 413 — payload too large). Try splitting the import: send Posts + Pages first, Products later, from Settings.';
                    } else if (httpStatus === 504 || httpStatus === 502 || httpStatus === 503) {
                        userMessage += ' (HTTP ' + httpStatus + ' — server timeout). Try splitting Posts/Pages from Products in Settings, or retry in a few minutes.';
                    } else if (httpStatus === 500) {
                        userMessage += ' (HTTP 500 — server error). Check wp-content/debug.log on your site for details, then contact support.';
                    } else {
                        userMessage += ' (HTTP ' + httpStatus + ' / ' + textStatus + '): ' + (error || 'unknown error');
                    }

                    // Fire-and-forget telemetry — surface the failure pattern
                    // server-side so we can diagnose without asking customers
                    // for debug.log. Server-side handler respects the
                    // QUERYRA_DISABLE_ANALYTICS opt-out.
                    try {
                        $.ajax({
                            url: queryraData.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'queryra_report_client_error',
                                nonce: queryraData.nonce,
                                context: 'admin_sync',
                                http_status: httpStatus,
                                error_text: responseExcerpt,
                                batch_offset: offset,
                                batch_size: currentBatch
                            }
                        });
                    } catch (e) { /* never let telemetry break the UX */ }

                    onSyncError(userMessage);
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
                '<span style="color: #dc3232;">Error: ' + escapeHtml(msg) + '</span><br>' +
                '<small style="color: #646970;">Already imported records are safe. You can re-run import anytime.</small>'
            );
            $button.prop('disabled', false);
        }

        sendNextBatch();
    }

    function showSyncError($status, $button, msg) {
        $status.html('<span style="color: #dc3232;">' + escapeHtml(msg) + '</span>')
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
                    $status.html('✗ ' + escapeHtml(response.data))
                           .css('color', '#dc3232');
                }
            },
            error: function(xhr, status, error) {
                $status.html('✗ Failed: ' + escapeHtml(error))
                       .css('color', '#dc3232');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

});
