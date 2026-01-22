<?php
/**
 * Queryra Search Integration
 *
 * Overrides WordPress default search with Queryra AI semantic search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Search_Integration {

    /**
     * API client
     */
    private $api;

    /**
     * Cache duration in seconds (10 minutes)
     */
    private $cache_duration = 600;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new Queryra_API();

        // Override WordPress search with Queryra
        add_action('pre_get_posts', array($this, 'override_search'));
    }

    /**
     * Override WordPress search with Queryra AI
     *
     * Smart Strategy:
     * 1. Pre-flight checks (avoid unnecessary API calls):
     *    - AI Search enabled?
     *    - Synced records > 0?
     *    - FREE plan + window open?
     * 2. Pagination with smart caching:
     *    - Page 1: Fetch 11 results, cache, show 10
     *    - Page 2: Check cache, fetch 21 if needed, show 11-20
     *    - Page 3: Check cache, fetch 31 if needed, show 21-30
     *    - Pattern: limit = (page × 10) + 1
     * 3. Fallback: If any check fails or API down → WordPress MySQL search
     *
     * @param WP_Query $query WordPress query object
     */
    public function override_search($query) {
        // Only override frontend main search query
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return;
        }

        // Check if AI search is enabled in settings
        if (get_option('queryra_ai_search', '0') !== '1') {
            return; // AI search disabled, use WordPress default
        }

        $search_term = $query->get('s');

        // Empty search term - let WordPress handle
        if (empty($search_term)) {
            return;
        }

        // Get cached stats and status from WordPress DB (saved by admin page)
        // Search integration ONLY reads from DB, never calls API directly
        $stats = get_option('queryra_cached_stats');
        $status = get_option('queryra_cached_status');

        // Pre-flight checks to avoid unnecessary API calls
        if ($stats) {
            // Check 1: Are there any synced records?
            if (isset($stats['synced_records']) && $stats['synced_records'] == 0) {
                // No synced records in Queryra - fallback to WordPress search
                return;
            }

            // Check 2: FREE plan + search window closed?
            if (isset($stats['plan']) && $stats['plan'] === 'free' && $status) {
                if (isset($status['available']) && $status['available'] === false) {
                    // FREE plan window is closed - fallback to WordPress search
                    // Don't ping API, save the request
                    return;
                }
            }
        }

        // Get pagination info
        $paged = max(1, (int) $query->get('paged'));
        $per_page = 10; // WordPress default posts per page
        $needed = $paged * $per_page;

        // Cache key (unique per search term)
        $cache_key = 'queryra_search_' . md5($search_term);

        // Check cache
        $cached_ids = get_transient($cache_key);

        // If cache doesn't have enough results, fetch from API
        if (!$cached_ids || count($cached_ids) < $needed) {
            // Calculate how many we need: (page × 10) + 1
            // Page 1: 11, Page 2: 21, Page 3: 31, etc.
            $limit = ($paged * 10) + 1;

            // Call Queryra API
            $results = $this->api->search($search_term, $limit);

            // If API fails or returns error, fallback to WordPress search
            if (is_wp_error($results) || empty($results['results'])) {
                // Let WordPress handle search normally
                return;
            }

            // Extract post IDs from results
            // Convert "wp-123" → 123
            $all_ids = array_map(function($result) {
                return (int) str_replace('wp-', '', $result['id']);
            }, $results['results']);

            // Filter out invalid IDs and posts that don't exist
            $all_ids = array_filter($all_ids, function($id) {
                return $id > 0;
            });

            // Cache the results (10 minutes)
            set_transient($cache_key, $all_ids, $this->cache_duration);

            $cached_ids = $all_ids;
        }

        // If no results, show empty
        if (empty($cached_ids)) {
            $query->set('post__in', array(0)); // No results
            return;
        }

        // Get IDs for current page
        // Page 1: IDs 0-9, Page 2: IDs 10-19, etc.
        $offset = ($paged - 1) * $per_page;
        $page_ids = array_slice($cached_ids, $offset, $per_page);

        // If no IDs for this page (beyond available results), show empty
        if (empty($page_ids)) {
            $query->set('post__in', array(0)); // No results
            return;
        }

        // Override WordPress query
        $query->set('post__in', $page_ids);
        $query->set('orderby', 'post__in'); // Preserve Queryra ranking order
        $query->set('s', ''); // Disable WordPress default search

        // Set posts per page to match what we're showing
        $query->set('posts_per_page', count($page_ids));

        // For pagination to work, set found_posts
        add_filter('found_posts', function($found_posts, $query_obj) use ($cached_ids, $query) {
            if ($query_obj === $query) {
                return count($cached_ids);
            }
            return $found_posts;
        }, 10, 2);
    }
}
