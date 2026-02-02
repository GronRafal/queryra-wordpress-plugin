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
     * Track an event
     *
     * @param string $event Event name
     */
    public static function track($event) {
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
            'timestamp'          => gmdate('c'),
        );

        // Send non-blocking request
        wp_remote_post(self::API_ENDPOINT, array(
            'body'     => wp_json_encode($payload),
            'headers'  => array('Content-Type' => 'application/json'),
            'timeout'  => 5,
            'blocking' => false,
        ));

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Queryra Analytics: ' . $event);
        }
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
}
