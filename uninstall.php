<?php
/**
 * Queryra uninstall routine.
 *
 * Runs ONLY when the plugin is deleted from the WordPress admin — never on
 * deactivation. Removes the data that is pure leftover junk (caches,
 * transients, internal flags/hashes, local logs) while DELIBERATELY KEEPING:
 *
 *   - queryra_instance_id : an anonymous support/telemetry handle (a random
 *     UUID, no personal data). Preserving it keeps history continuous if the
 *     plugin is reinstalled, and lets support look up the install by the ID
 *     shown on the Support tab. Deleting it would fork one site into two
 *     unrelated installs on reinstall.
 *   - user configuration  : API key, API URL, enable flags, post types — so a
 *     reinstall picks up exactly where the user left off.
 *
 * A future "Remove ALL Queryra data on uninstall" opt-in could extend this to
 * delete the kept items too; for now we intentionally leave them.
 *
 * @package Queryra_AI_Search
 */

// Security: block direct web access (Plugin Check requires the ABSPATH
// guard) and only ever execute inside WordPress' uninstall flow.
if (!defined('ABSPATH')) {
    exit;
}
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove Queryra's junk options and transients for a single site.
 */
function queryra_uninstall_cleanup() {
    global $wpdb;

    // Junk options: caches, internal flags/hashes, local logs.
    // NOT removed (kept on purpose) — the anonymous install id plus every
    // user-configured setting, so a reinstall picks up where it left off:
    //   queryra_instance_id, queryra_api_key, queryra_api_url,
    //   queryra_enabled, queryra_ai_search, queryra_auto_sync,
    //   queryra_post_types, queryra_cache_duration, queryra_output_schema,
    //   queryra_generate_llms_txt, queryra_generate_llms_full_txt.
    $options = array(
        'queryra_cached_stats',
        'queryra_cached_status',
        'queryra_cache_version',
        'queryra_api_key_validation',
        'queryra_api_key_validated_hash',
        'queryra_api_key_notified_hash',
        'queryra_first_search_tracked',
        'queryra_wizard_import_done',
        'queryra_ux_tip_dismissed',
        // "Setup survey already handled" flag. Same category as the flags
        // above. No survey data is stored on the site — the answer went out
        // as an event — so nothing is lost by clearing it.
        'queryra_site_profile_done',
        // Legacy: a pre-release build briefly stored the survey ANSWER here.
        // Nothing reads it any more; clear it so no answer data is left behind.
        'queryra_site_profile',
        'queryra_plugin_version',
        'queryra_recent_errors',
        'queryra_deactivation_feedback',
        'queryra_pending_activation_event',
    );
    foreach ($options as $option) {
        delete_option($option);
    }

    // Named transients (including the pre-1.4 legacy key-status transient).
    $transients = array(
        'queryra_api_down',
        'queryra_activation_redirect',
        'queryra_wizard_opened_tracked',
        'queryra_search_stats',
        'queryra_api_key_status',
    );
    foreach ($transients as $transient) {
        delete_transient($transient);
    }

    // Dynamic search-result transients (queryra_search_<md5>) — the keys are
    // not enumerable, so clear the rows directly. Covers sites without an
    // external object cache; on Redis/Memcached these entries expire on their
    // own TTL (and are not "database junk").
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off uninstall cleanup
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_queryra_search_%'
            OR option_name LIKE '_transient_timeout_queryra_search_%'"
    );
}

/**
 * Dispatch cleanup across all sites (multisite) or just the current site.
 *
 * Wrapped in a function so its loop variables stay local — variables in an
 * uninstall.php run at global scope and would otherwise need a plugin prefix.
 */
function queryra_run_uninstall() {
    if (is_multisite()) {
        $queryra_blog_ids = get_sites(array('fields' => 'ids', 'number' => 0));
        foreach ($queryra_blog_ids as $queryra_blog_id) {
            switch_to_blog($queryra_blog_id);
            queryra_uninstall_cleanup();
            restore_current_blog();
        }
    } else {
        queryra_uninstall_cleanup();
    }
}

queryra_run_uninstall();
