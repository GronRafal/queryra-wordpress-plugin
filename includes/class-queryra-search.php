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

        $search_term = $query->get('s');

        // Check if AI search is enabled in settings
        $ai_enabled = get_option('queryra_ai_search', '0');
        if ($ai_enabled !== '1') {
            return; // AI search disabled, use WordPress default
        }

        // Empty search term - let WordPress handle
        if (empty($search_term)) {
            return;
        }

        // Get cached stats and status from WordPress DB (saved by admin page)
        // Search integration ONLY reads from DB, never calls API directly
        $stats = get_option('queryra_cached_stats');
        $status = get_option('queryra_cached_status');


        // Pre-flight check: No synced records?
        if ($stats && isset($stats['synced_records']) && $stats['synced_records'] == 0) {
            return; // No records in Queryra at all
        }

        // Get pagination info
        $paged = max(1, (int) $query->get('paged'));
        $per_page = 10; // WordPress default posts per page
        $needed = $paged * $per_page;

        // Cache key (unique per search term)
        $cache_key = 'queryra_search_' . md5($search_term);

        // Check cache FIRST - even if stale, it's better than SQL
        $cached_ids = get_transient($cache_key);

        if ($cached_ids) {
        } else {
        }

        // SMART STRATEGY for FREE plan:
        // If window closed BUT we have cache → use cache (even if stale)
        // Better to show AI results from cache than fallback to SQL
        if ($stats && isset($stats['plan']) && $stats['plan'] === 'free' && $status) {
            $window_closed = false;

            // Check if window is closed
            if (isset($status['available']) && $status['available'] === false) {
                $window_closed = true;
            } elseif (isset($status['minutes_until_open']) && $status['minutes_until_open'] > 2) {
                $window_closed = true;
            }

            // If window closed BUT we have cache → use it!
            if ($window_closed && $cached_ids && count($cached_ids) > 0) {
                // Don't fetch from API, just use cache below
                // This saves requests and gives better results
            } elseif ($window_closed && !$cached_ids) {
                // No cache available, must fallback to SQL
                return;
            }
        }

        // If cache doesn't have enough results, fetch from API
        // BUT: If FREE plan window closed and we have SOME cache, use what we have
        $should_fetch_api = false;

        if (!$cached_ids || count($cached_ids) < $needed) {
            // Check if we should skip API call (FREE plan window closed with partial cache)
            $skip_api = false;

            if ($stats && isset($stats['plan']) && $stats['plan'] === 'free' && $status) {
                if ((isset($status['available']) && $status['available'] === false) ||
                    (isset($status['minutes_until_open']) && $status['minutes_until_open'] > 2)) {

                    if ($cached_ids && count($cached_ids) > 0) {
                        // We have some cache - use it instead of making API call that will fail
                        $skip_api = true;
                    }
                }
            }

            if (!$skip_api) {
                // Calculate how many we need: (page × 10) + 1
                // Page 1: 11, Page 2: 21, Page 3: 31, etc.
                $limit = ($paged * 10) + 1;


                // Call Queryra API
                $results = $this->api->search($search_term, $limit);


                // If API fails or returns error, fallback to WordPress search
                if (is_wp_error($results)) {
                    $error_msg = $results->get_error_message();
                    $error_data = $results->get_error_data();

                    // Log detailed error info
                    if ($error_data && isset($error_data['status'])) {
                    }

                    // Special handling for FREE plan window closed
                    if (strpos($error_msg, 'FREE plan') !== false || strpos($error_msg, 'window') !== false) {

                        // Update cached status so we don't try again until window opens
                        if ($error_data && isset($error_data['data']['errors']['api_error'][0])) {
                            $error_info = $error_data['data']['errors']['api_error'][0];

                            // Build updated status
                            $updated_status = array(
                                'available' => false,
                                'plan' => 'free',
                                'minutes_left' => 0,
                                'message' => isset($error_info['message']) ? $error_info['message'] : 'Search window closed',
                                'next_available_at' => isset($error_info['next_available_at']) ? $error_info['next_available_at'] : null,
                                'minutes_until_open' => isset($error_info['minutes_until_open']) ? $error_info['minutes_until_open'] : null
                            );

                            // Cache for 5 minutes (will be updated by admin page sooner)
                            update_option('queryra_cached_status', $updated_status);
                        }
                    }

                    // Let WordPress handle search normally
                    return;
                }

                if (empty($results['results'])) {
                    // Let WordPress handle search normally
                    return;
                }


                // Extract post IDs from results
                // Convert "wp-123" → 123, filter out non-WordPress records
                $all_ids = array();
                foreach ($results['results'] as $result) {
                    // Only process WordPress records (id starts with "wp-")
                    if (strpos($result['id'], 'wp-') === 0) {
                        $id = (int) str_replace('wp-', '', $result['id']);
                        if ($id > 0) {
                            $all_ids[] = $id;
                        }
                    } else {
                    }
                }

                // Cache the results (10 minutes)
                set_transient($cache_key, $all_ids, $this->cache_duration);

                $cached_ids = $all_ids;
            } // end if (!$skip_api)
        } // end if (!$cached_ids || count($cached_ids) < $needed)

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

        // Override WordPress query with AI results

        // Flag that we used AI search (for attribution footer)
        $query->set('queryra_ai_used', true);

        // NUCLEAR OPTION: Use posts_pre_query to bypass WordPress query entirely
        // This is most reliable - theme/plugins can't override it
        add_filter('posts_pre_query', function($posts, $query_obj) use ($query, $page_ids, $cached_ids, $per_page) {
            if ($query_obj !== $query) {
                return $posts; // Not our query
            }


            // Fetch posts directly (bypass WP_Query)
            global $wpdb;
            if (empty($page_ids)) {
                return array();
            }

            $ids_string = implode(',', array_map('intval', $page_ids));

            // Get allowed post types from settings
            // Only show content types that user enabled in settings
            $post_types = get_option('queryra_post_types', array('post', 'page'));

            $post_types_string = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";

            $results = $wpdb->get_results("
                SELECT * FROM {$wpdb->posts}
                WHERE ID IN ($ids_string)
                AND post_status = 'publish'
                AND post_type IN ($post_types_string)
                ORDER BY FIELD(ID, $ids_string)
            ");


            // CRITICAL: Set pagination properties directly on query object
            // When bypassing query with posts_pre_query, we must set these manually
            $total_results = count($cached_ids);
            $query_obj->found_posts = $total_results;
            $query_obj->max_num_pages = ceil($total_results / $per_page);


            return $results;
        }, 10, 2);

        // Don't clear 's' - keep search term for display in theme
        // WordPress won't use it for SQL query because we set post__in
        // $query->set('s', ''); // <-- Removed to preserve search term display

        // Set posts per page to match what we're showing
        $query->set('posts_per_page', $per_page); // Always 10, not count($page_ids)


        // For pagination to work, set found_posts and max_num_pages
        add_filter('found_posts', function($found_posts, $query_obj) use ($cached_ids, $query) {
            if ($query_obj === $query) {
                $total = count($cached_ids);
                return $total;
            }
            return $found_posts;
        }, 10, 2);

        // Debug: Log what WordPress actually found
        add_action('pre_get_posts', function($q) use ($query) {
            if ($q === $query) {
                add_filter('posts_results', function($posts) use ($query) {
                    if (count($posts) > 0) {
                        $post_ids = array_map(function($p) { return $p->ID; }, $posts);
                    }
                    return $posts;
                }, 999, 1);
            }
        }, 999);
    }
}
