<?php
/**
 * Queryra Sync
 *
 * Handles post synchronization with Queryra
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Sync {

    /**
     * API client
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new Queryra_API();

        // Auto-sync on post save/update
        if (get_option('queryra_auto_sync', '1') === '1') {
            add_action('save_post', array($this, 'sync_post_on_save'), 10, 3);
            add_action('before_delete_post', array($this, 'delete_post_on_delete'));
        }

        // AJAX handlers for manual sync
        add_action('wp_ajax_queryra_sync_all', array($this, 'ajax_sync_all'));
        add_action('wp_ajax_queryra_test_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Sync post on save
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function sync_post_on_save($post_id, $post, $update) {
        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Check if post type should be synced
        $post_types = get_option('queryra_post_types', array('post', 'page'));
        if (!in_array($post->post_type, $post_types)) {
            return;
        }

        // Only sync published posts
        if ($post->post_status !== 'publish') {
            return;
        }

        // Sync this post
        $this->sync_posts(array($post_id));
    }

    /**
     * Delete post from Queryra
     *
     * @param int $post_id Post ID
     */
    public function delete_post_on_delete($post_id) {
        $post = get_post($post_id);

        // Check if post type should be synced
        $post_types = get_option('queryra_post_types', array('post', 'page'));
        if (!in_array($post->post_type, $post_types)) {
            return;
        }

        // Delete from Queryra
        $record_id = 'wp-' . $post_id;
        $this->api->delete_record($record_id);
    }

    /**
     * Sync posts to Queryra
     *
     * @param array $post_ids Array of post IDs to sync
     * @return array Result with success status and message
     */
    public function sync_posts($post_ids) {
        if (empty($post_ids)) {
            return array(
                'success' => false,
                'message' => 'No posts to send'
            );
        }

        $records = array();

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);

            if (!$post || $post->post_status !== 'publish') {
                continue;
            }

            // Build record
            $record = $this->build_record($post);
            if ($record) {
                $records[] = $record;
            }
        }

        if (empty($records)) {
            return array(
                'success' => false,
                'message' => 'No valid posts to send'
            );
        }

        // Send to API
        $response = $this->api->sync_records($records);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        return array(
            'success' => true,
            'message' => sprintf('Successfully sent %d posts to Queryra', count($records)),
            'data' => $response
        );
    }

    /**
     * Build record from post
     *
     * @param WP_Post $post Post object
     * @return array Record data
     */
    private function build_record($post) {
        // Get post content (strip HTML tags)
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post->ID) : wp_trim_words($content, 30);

        // Get categories and tags
        $categories = wp_get_post_categories($post->ID, array('fields' => 'names'));
        $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));

        // Build rich description with all content for better AI search
        // Include: excerpt, full content, categories, and tags
        $description_parts = array();

        // Add excerpt if different from content start
        if (!empty($excerpt)) {
            $description_parts[] = $excerpt;
        }

        // Add full content
        if (!empty($content)) {
            $description_parts[] = $content;
        }

        // Add categories
        if (!empty($categories)) {
            $description_parts[] = "Categories: " . implode(', ', $categories);
        }

        // Add tags
        if (!empty($tags)) {
            $description_parts[] = "Tags: " . implode(', ', $tags);
        }

        // Combine all parts with double line breaks
        $full_description = implode("\n\n", $description_parts);

        // Get featured image
        $image_url = get_the_post_thumbnail_url($post->ID, 'medium');

        // Calculate margin based on post importance
        // Sticky posts (featured) = 100%, normal posts = 50%
        $margin = is_sticky($post->ID) ? 1.0 : 0.5;

        // Build record - only fields that backend expects
        $record = array(
            'id' => 'wp-' . $post->ID,
            'name' => $post->post_title,
            'description' => $full_description,
            'price' => 0.0,
            'url' => get_permalink($post->ID),
            'image_url' => $image_url ?: '',
            'margin' => $margin
        );

        return $record;
    }

    /**
     * AJAX: Sync all posts
     */
    public function ajax_sync_all() {
        check_ajax_referer('queryra_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        // Get all published posts
        $post_types = get_option('queryra_post_types', array('post', 'page'));

        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $post_ids = get_posts($args);

        if (empty($post_ids)) {
            wp_send_json_error(array('message' => 'No posts found'));
        }

        // Sync posts
        $result = $this->sync_posts($post_ids);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('queryra_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $result = $this->api->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
