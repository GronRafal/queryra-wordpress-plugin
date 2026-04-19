/**
 * Queryra Setup Wizard JavaScript
 */

jQuery(document).ready(function($) {

    // Step 1: Connection mode toggle
    $('input[name="connection_mode"]').on('change', function() {
        if ($(this).val() === 'existing') {
            $('#queryra-existing-form').show();
            $('#queryra-new-form').hide();
        } else {
            $('#queryra-existing-form').hide();
            $('#queryra-new-form').show();
        }
    });

    // Step 1: Save API key (existing account)
    $('#queryra-save-api-key').on('click', function() {
        saveApiKey($(this), '#queryra-api-key');
    });

    // Step 1: Save API key (new account)
    $('#queryra-save-new-key').on('click', function() {
        saveApiKey($(this), '#queryra-api-key-new');
    });

    // Common function to save API key
    function saveApiKey($button, inputSelector) {
        var apiKey = $(inputSelector).val().trim();

        if (!apiKey) {
            showStatus('error', 'Please enter your API key');
            return;
        }

        $button.prop('disabled', true).text('Checking...');

        // Save to WordPress options
        $.ajax({
            url: queryraWizard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_wizard_save_api_key',
                nonce: queryraWizard.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to step 2
                    window.location.href = '?page=queryra-setup-wizard&step=2';
                } else {
                    showStatus('error', response.data.message);
                    $button.prop('disabled', false).html('Continue <span class="dashicons dashicons-arrow-right-alt2"></span>');
                }
            },
            error: function() {
                showStatus('error', 'Connection failed. Please try again.');
                $button.prop('disabled', false).html('Continue <span class="dashicons dashicons-arrow-right-alt2"></span>');
            }
        });
    }

    // Step 2: Post-type checkboxes — dynamic summary + Start button gating
    function updateImportSummary() {
        var $container = $('#queryra-import-types');
        if (!$container.length) return;

        var limit = parseInt($container.data('limit'), 10) || 0;
        var total = 0;
        var selectedCount = 0;

        $('.queryra-type-checkbox').each(function() {
            if ($(this).is(':checked') && !$(this).is(':disabled')) {
                total += parseInt($(this).data('count'), 10) || 0;
                selectedCount++;
            }
        });

        $('#queryra-summary-within-limit, #queryra-summary-over-limit, #queryra-summary-none').hide();

        var $startBtn = $('#queryra-start-import');

        if (selectedCount === 0) {
            $('#queryra-summary-none').show();
            $startBtn.prop('disabled', true);
        } else if (limit > 0 && total > limit) {
            $('#queryra-selected-total').text(total.toLocaleString());
            $('#queryra-summary-over-limit').show();
            $startBtn.prop('disabled', false);
        } else {
            $('#queryra-will-import-count').text(total.toLocaleString());
            $('#queryra-summary-within-limit').show();
            $startBtn.prop('disabled', false);
        }
    }

    $('.queryra-type-checkbox').on('change', updateImportSummary);
    updateImportSummary();

    // Step 2: Start Import (batched)
    $('#queryra-start-import').on('click', function() {
        var $button = $(this);

        var selectedTypes = $('.queryra-type-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedTypes.length === 0) {
            alert('Please select at least one content type to import.');
            return;
        }

        $button.prop('disabled', true).text('Saving selection...');

        // Show progress bar
        $('#queryra-import-progress').show();
        $('#queryra-import-success').hide();

        // Phase 0: Save selected post types so sync endpoints use them
        $.ajax({
            url: queryraWizard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_wizard_save_post_types',
                nonce: queryraWizard.nonce,
                post_types: selectedTypes
            },
            success: function(saveResp) {
                if (!saveResp.success) {
                    alert('Error: ' + saveResp.data.message);
                    resetImportButton($button);
                    return;
                }
                fetchSyncInfoAndImport($button);
            },
            error: function() {
                alert('Failed to save content type selection. Please try again.');
                resetImportButton($button);
            }
        });
    });

    function fetchSyncInfoAndImport($button) {
        $button.text('Checking plan...');

        // Phase 1: Get sync info (plan limits, batch size)
        $.ajax({
            url: queryraWizard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_get_sync_info',
                nonce: queryraWizard.syncNonce
            },
            success: function(response) {
                if (!response.success) {
                    alert('Error: ' + response.data.message);
                    resetImportButton($button);
                    return;
                }

                var info = response.data;

                // Plan is full
                if (info.record_limit > 0 && info.current_records >= info.record_limit) {
                    alert('Plan limit reached (' + info.current_records + '/' + info.record_limit + ' records). Please upgrade your plan or delete existing records.');
                    resetImportButton($button);
                    return;
                }

                // No posts
                if (info.will_sync === 0) {
                    alert('No published content found to import.');
                    resetImportButton($button);
                    return;
                }

                // Phase 2: Batched import
                startWizardBatchSync($button, info);
            },
            error: function() {
                alert('Failed to check plan limits. Please try again.');
                resetImportButton($button);
            }
        });
    }

    function startWizardBatchSync($button, info) {
        var offset = 0;
        var totalSynced = 0;
        var willSync = info.will_sync;
        var batchSize = info.batch_size;

        $button.text('Importing...');
        $('#queryra-import-status').text('Importing content... Please keep this tab open.');
        $('#queryra-import-info').text('0 / ' + willSync + ' records');
        $('#queryra-import-progress-bar').css('width', '0%').text('');

        function sendBatch() {
            if (totalSynced >= willSync) {
                onImportDone();
                return;
            }

            var currentBatch = Math.min(batchSize, willSync - totalSynced);

            $.ajax({
                url: queryraWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'queryra_sync_batch',
                    nonce: queryraWizard.syncNonce,
                    offset: offset,
                    batch_size: currentBatch
                },
                success: function(resp) {
                    if (!resp.success) {
                        onImportError('Batch error: ' + resp.data.message);
                        return;
                    }

                    var synced = resp.data.synced;

                    if (synced === 0) {
                        onImportDone();
                        return;
                    }

                    totalSynced += synced;
                    offset += synced;

                    var percent = Math.min(Math.round((totalSynced / willSync) * 100), 100);
                    $('#queryra-import-progress-bar').css('width', percent + '%').text(percent + '%');
                    $('#queryra-import-info').text(totalSynced + ' / ' + willSync + ' records');

                    sendBatch();
                },
                error: function() {
                    onImportError('Import batch failed. Please try again.');
                }
            });
        }

        function onImportDone() {
            $('#queryra-import-progress-bar').css('width', '100%').text('100%');
            $('#queryra-import-info').text(totalSynced + ' / ' + totalSynced + ' records');

            // Mark import as done
            $.ajax({
                url: queryraWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'queryra_wizard_mark_import_done',
                    nonce: queryraWizard.syncNonce
                }
            });

            setTimeout(function() {
                $('#queryra-import-progress').hide();
                $('#queryra-import-success').show();
                $('#queryra-success-message').text('Successfully imported ' + totalSynced + ' records to Queryra');
                $('#queryra-continue-step3').prop('disabled', false);
                $button.hide();
            }, 500);
        }

        function onImportError(msg) {
            alert(msg + '\n\nAlready imported records are safe. You can retry.');
            $button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Retry Import');
        }

        sendBatch();
    }

    function resetImportButton($button) {
        $button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Start Import Now');
        $('#queryra-import-progress').hide();
    }

    // Step 2: Continue to Step 3
    $('#queryra-continue-step3').on('click', function() {
        window.location.href = '?page=queryra-setup-wizard&step=3';
    });

    // Step 3: Check Status
    $('#queryra-check-status').on('click', function() {
        var $button = $(this);
        var originalText = $button.html();

        // Disable button and show checking
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span> Checking...');

        // Check status
        $.ajax({
            url: queryraWizard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_wizard_check_status',
                nonce: queryraWizard.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;

                    // Update stats
                    $('#queryra-total-records').text(data.records_count.toLocaleString());
                    $('#queryra-pending-sync-number span').text(data.unsynced_count.toLocaleString());

                    // Update color based on status
                    if (data.is_synced) {
                        $('#queryra-pending-sync-number span').css('color', '#00a32a');
                        $('#queryra-sync-instructions').hide();
                        $('#queryra-sync-complete').show();
                        $('#queryra-continue-step4').prop('disabled', false);
                    } else {
                        $('#queryra-pending-sync-number span').css('color', '#d63638');
                        $('#queryra-sync-instructions').show();
                        $('#queryra-sync-complete').hide();
                        $('#queryra-continue-step4').prop('disabled', true);
                    }

                    // Re-enable button
                    $button.prop('disabled', false).html(originalText);
                } else {
                    alert('Error: ' + response.data.message);
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('Failed to check status. Please try again.');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Step 3: Continue to Step 4
    $('#queryra-continue-step4').on('click', function() {
        window.location.href = '?page=queryra-setup-wizard&step=4';
    });

    // Step 4: Test Search
    $('#queryra-test-search').on('click', function() {
        var query = $('#queryra-test-query').val().trim();

        if (!query) {
            alert('Please enter a search query');
            return;
        }

        var $button = $(this);
        var originalText = $button.html();

        // Disable button
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span> Searching...');

        // Hide previous results/errors
        $('#queryra-test-results').hide();
        $('#queryra-no-results').hide();
        $('#queryra-search-error').hide();

        // Perform search
        $.ajax({
            url: queryraWizard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_wizard_test_search',
                nonce: queryraWizard.nonce,
                query: query
            },
            success: function(response) {
                $button.prop('disabled', false).html(originalText);

                if (response.success) {
                    var results = response.data.results;
                    var total = response.data.total;

                    if (results.length === 0) {
                        $('#queryra-no-results').show();
                    } else {
                        // Build results HTML
                        var html = '<div style="border: 1px solid #dcdcde; border-radius: 4px; overflow: hidden;">';

                        results.forEach(function(item, index) {
                            var bgColor = index % 2 === 0 ? '#ffffff' : '#f9f9f9';
                            html += '<div style="display: flex; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid #f0f0f1; background: ' + bgColor + ';">';
                            html += '<span style="font-weight: 500; color: #1d2327;">' + item.name + '</span>';
                            html += '<span style="color: #2271b1; font-weight: 600;">' + item.score + '</span>';
                            html += '</div>';
                        });

                        html += '</div>';

                        $('#queryra-results-count').text('(' + total + ' found)');
                        $('#queryra-results-list').html(html);
                        $('#queryra-test-results').show();
                    }
                } else {
                    $('#queryra-search-error').html('<div style="background: #fef2f2; border-left: 4px solid #dc3232; padding: 12px; border-radius: 4px; color: #dc3232;"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</div>').show();
                }
            },
            error: function() {
                $button.prop('disabled', false).html(originalText);
                $('#queryra-search-error').html('<div style="background: #fef2f2; border-left: 4px solid #dc3232; padding: 12px; border-radius: 4px; color: #dc3232;"><span class="dashicons dashicons-warning"></span> Search failed. Please try again.</div>').show();
            }
        });
    });

    // Step 4: Enter key to search
    $('#queryra-test-query').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#queryra-test-search').click();
        }
    });

    // Helper: Show status message
    function showStatus(type, message) {
        var $status = $('#queryra-connection-status');
        var className = type === 'error' ? 'queryra-error' :
                        type === 'success' ? 'queryra-success' :
                        'queryra-info';

        $status.html('<div class="' + className + '">' + message + '</div>');

        if (type === 'success') {
            setTimeout(function() {
                $status.empty();
            }, 5000);
        }
    }

});
