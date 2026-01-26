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

    // Step 2: Start Import
    $('#queryra-start-import').on('click', function() {
        var $button = $(this);

        // Disable button
        $button.prop('disabled', true).text('Importing...');

        // Show progress bar
        $('#queryra-import-progress').show();
        $('#queryra-import-success').hide();

        // Start import
        $.ajax({
            url: queryraWizard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_wizard_import',
                nonce: queryraWizard.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show 100% progress
                    $('#queryra-import-progress-bar').css('width', '100%').text('100%');
                    $('#queryra-import-info').text(response.data.imported + ' / ' + response.data.imported + ' items');

                    // Hide progress, show success
                    setTimeout(function() {
                        $('#queryra-import-progress').hide();
                        $('#queryra-import-success').show();
                        $('#queryra-success-message').text(response.data.message);

                        // Enable Continue button
                        $('#queryra-continue-step3').prop('disabled', false);

                        // Hide Start Import button
                        $button.hide();
                    }, 500);
                } else {
                    // Show error
                    alert('Import failed: ' + response.data.message);
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Start Import Now');
                    $('#queryra-import-progress').hide();
                }
            },
            error: function() {
                alert('Import failed. Please try again.');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Start Import Now');
                $('#queryra-import-progress').hide();
            }
        });
    });

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

    // Step 3: Save Settings
    $('#queryra-save-settings').on('click', function() {
        var $button = $(this);
        var originalText = $button.html();

        // Get settings
        var enabled = $('#queryra-enabled').is(':checked');
        var includePages = $('#queryra-include-pages').is(':checked');
        var autoImport = $('#queryra-auto-import').is(':checked');

        // Disable button
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span> Saving...');

        // Save settings
        $.ajax({
            url: queryraWizard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'queryra_wizard_save_settings',
                nonce: queryraWizard.nonce,
                enabled: enabled.toString(),
                include_pages: includePages.toString(),
                auto_import: autoImport.toString()
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#queryra-settings-status').html('<div style="background: #e7f7ed; border-left: 4px solid #00a32a; padding: 12px; border-radius: 4px; color: #1d2327;"><span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ' + response.data.message + '</div>');

                    // Hide success message after 3 seconds
                    setTimeout(function() {
                        $('#queryra-settings-status').empty();
                    }, 3000);

                    // Re-enable button
                    $button.prop('disabled', false).html(originalText);
                } else {
                    alert('Error: ' + response.data.message);
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('Failed to save settings. Please try again.');
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
