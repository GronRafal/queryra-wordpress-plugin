<?php
/**
 * Queryra Analytics - Anonymous plugin usage tracking
 *
 * Collects anonymous usage data to improve the plugin.
 * No personal data is collected. See https://queryra.com/privacy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Analytics {

    /**
     * Analytics API endpoint
     */
    const API_ENDPOINT = 'https://queryra.com/api/v1/analytics/events';

    /**
     * Option holding the local "Recent issues" log shown in wp-admin.
     */
    const RECENT_ERRORS_OPTION = 'queryra_recent_errors';

    /**
     * Maximum number of entries kept in the local issue log.
     */
    const RECENT_ERRORS_MAX = 10;

    /**
     * Track an event
     *
     * @param string $event Event name
     * @param array  $meta  Optional event-specific metadata (HTTP status,
     *                      batch info, error text, etc.). Sanitised payload
     *                      kept under 2 KB to stay non-disruptive.
     */
    public static function track($event, $meta = array()) {
        // Check opt-out
        if (defined('QUERYRA_DISABLE_ANALYTICS') && QUERYRA_DISABLE_ANALYTICS) {
            return;
        }

        // Build payload
        $payload = array(
            'instance_id'        => self::get_instance_id(),
            'event'              => $event,
            'environment'        => self::detect_environment(),
            'wp_version'         => get_bloginfo('version'),
            'php_version'        => phpversion(),
            'plugin_version'     => QUERYRA_VERSION,
            'woocommerce_active' => class_exists('WooCommerce'),
            'products_count'     => self::count_products(),
            'posts_count'        => self::count_posts(),
            'pages_count'        => self::count_pages(),
            'custom_post_types'  => self::count_custom_post_types(),
            'timestamp'          => gmdate('c'),
        );

        // Merge optional event-specific metadata. Hard cap on JSON length so a
        // pathological error payload (5MB stack trace, etc.) cannot block the
        // request — telemetry must be cheap and never affect site performance.
        if (!empty($meta) && is_array($meta)) {
            $encoded = wp_json_encode($meta);
            if (is_string($encoded) && strlen($encoded) > 2048) {
                $meta = array('truncated' => true, 'size' => strlen($encoded));
            }
            $payload['meta'] = $meta;
        }

        // Send non-blocking request
        wp_remote_post(self::API_ENDPOINT, array(
            'body'     => wp_json_encode($payload),
            'headers'  => array('Content-Type' => 'application/json'),
            'timeout'  => 5,
            'blocking' => false,
        ));
    }

    /**
     * Report a plugin error: telemetry event + local issue log.
     *
     * Single entry point for every error surface (sync, search, key
     * validation, integrations). Sends the `error_reported` telemetry
     * event AND appends the error to the local "Recent issues" log
     * rendered on the Settings tab — so the site owner sees the same
     * problems we see, without digging through debug.log.
     *
     * The local log is written even when QUERYRA_DISABLE_ANALYTICS is
     * set: the opt-out covers data leaving the site, not the user's
     * own visibility into their install.
     *
     * @param array $meta Error metadata — same shape as track() meta.
     *                    Recognised keys for the local log: category,
     *                    context/stage, code/http_status, error/error_text/reason.
     */
    public static function report_error($meta) {
        self::track('error_reported', $meta);
        self::remember_error($meta);
    }

    /**
     * Append an error to the local "Recent issues" ring buffer.
     *
     * Bounded on two axes so a broken API can never bloat the options
     * table: identical consecutive errors collapse into one entry with
     * a counter (a down API during a traffic spike = 1 row, not 100),
     * and the log is capped at RECENT_ERRORS_MAX entries, newest first.
     *
     * @param array $meta Error metadata (see report_error()).
     */
    private static function remember_error($meta) {
        if (!is_array($meta)) {
            return;
        }

        $message = '';
        foreach (array('error', 'error_text', 'reason') as $key) {
            if (!empty($meta[$key]) && is_string($meta[$key])) {
                $message = $meta[$key];
                break;
            }
        }

        $code = '';
        if (!empty($meta['code'])) {
            $code = (string) $meta['code'];
        } elseif (!empty($meta['http_status'])) {
            $code = 'HTTP ' . (int) $meta['http_status'];
        }

        $context = '';
        foreach (array('context', 'stage', 'integration') as $key) {
            if (!empty($meta[$key]) && is_string($meta[$key])) {
                $context = $meta[$key];
                break;
            }
        }

        $entry = array(
            'time'     => time(),
            'category' => isset($meta['category']) ? (string) $meta['category'] : 'general',
            'context'  => $context,
            'code'     => $code,
            'message'  => mb_substr($message, 0, 200),
            'count'    => 1,
        );

        $entries = get_option(self::RECENT_ERRORS_OPTION, array());
        if (!is_array($entries)) {
            $entries = array();
        }

        // Same error as the newest entry → bump its counter instead of
        // stacking duplicates.
        if (!empty($entries)
            && $entries[0]['category'] === $entry['category']
            && $entries[0]['code'] === $entry['code']
            && $entries[0]['message'] === $entry['message']
        ) {
            $entries[0]['count'] = (int) $entries[0]['count'] + 1;
            $entries[0]['time']  = $entry['time'];
        } else {
            array_unshift($entries, $entry);
            $entries = array_slice($entries, 0, self::RECENT_ERRORS_MAX);
        }

        update_option(self::RECENT_ERRORS_OPTION, $entries, false);
    }

    /**
     * Get or create unique instance ID
     *
     * @return string UUID
     */
    private static function get_instance_id() {
        $instance_id = get_option('queryra_instance_id');

        if (empty($instance_id)) {
            $instance_id = wp_generate_uuid4();
            update_option('queryra_instance_id', $instance_id);
        }

        return $instance_id;
    }

    /**
     * Detect environment type
     *
     * @return string local|staging|production
     */
    private static function detect_environment() {
        $site_url = site_url();

        // Local indicators
        $local_patterns = array(
            'localhost',
            '127.0.0.1',
            '.local',
            '.test',
            '.dev',
            '.localhost',
            ':8888',  // MAMP
            ':8080',
            ':3000',
        );

        foreach ($local_patterns as $pattern) {
            if (strpos($site_url, $pattern) !== false) {
                return 'local';
            }
        }

        // Staging indicators
        $staging_patterns = array(
            'staging.',
            'stage.',
            'dev.',
            'test.',
            'demo.',
            '.wpengine.com',
            '.pantheonsite.io',
            '.kinsta.cloud',
        );

        foreach ($staging_patterns as $pattern) {
            if (strpos($site_url, $pattern) !== false) {
                return 'staging';
            }
        }

        return 'production';
    }

    /**
     * Count WooCommerce products
     *
     * @return int
     */
    private static function count_products() {
        if (!class_exists('WooCommerce')) {
            return 0;
        }
        $count = wp_count_posts('product');
        return isset($count->publish) ? (int) $count->publish : 0;
    }

    /**
     * Count published posts
     *
     * @return int
     */
    private static function count_posts() {
        $count = wp_count_posts('post');
        return isset($count->publish) ? (int) $count->publish : 0;
    }

    /**
     * Count published pages
     *
     * @return int
     */
    private static function count_pages() {
        $count = wp_count_posts('page');
        return isset($count->publish) ? (int) $count->publish : 0;
    }

    /**
     * Map of public custom post types → published-post count.
     *
     * Excludes the built-in/handled types (post, page, product) and
     * WordPress internal types. Lets us see across installs who relies on
     * custom post types and how much content lives there — i.e. real demand
     * for CPT indexing. Keys = which CPTs, count(keys) = how many types,
     * values = how many posts each.
     *
     * @return array slug => (int) published count
     */
    private static function count_custom_post_types() {
        $exclude = array('post', 'page', 'product', 'attachment', 'revision', 'nav_menu_item', 'wp_block');
        $result  = array();

        foreach (get_post_types(array('public' => true)) as $type) {
            if (in_array($type, $exclude, true)) {
                continue;
            }
            $count = wp_count_posts($type);
            $result[$type] = isset($count->publish) ? (int) $count->publish : 0;
        }

        return $result;
    }
}
