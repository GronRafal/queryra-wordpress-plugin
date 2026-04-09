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

            // Sync when post becomes sticky/unsticky (margin changes!)
            add_action('post_stuck', array($this, 'sync_post_sticky_change'));
            add_action('post_unstuck', array($this, 'sync_post_sticky_change'));
        }

        // AJAX handlers for manual sync
        add_action('wp_ajax_queryra_sync_all', array($this, 'ajax_sync_all'));
        add_action('wp_ajax_queryra_test_connection', array($this, 'ajax_test_connection'));

        // Batched sync AJAX handlers
        add_action('wp_ajax_queryra_get_sync_info', array($this, 'ajax_get_sync_info'));
        add_action('wp_ajax_queryra_sync_batch', array($this, 'ajax_sync_batch'));
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
     * Sync post when sticky status changes
     * WordPress triggers this when post becomes sticky/unsticky
     *
     * @param int $post_id Post ID
     */
    public function sync_post_sticky_change($post_id) {
        $post = get_post($post_id);
        if (!$post) {
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

        // Sync this post with updated margin
        $this->sync_posts(array($post_id));
    }

    /**
     * Delete post from Queryra
     *
     * @param int $post_id Post ID
     */
    public function delete_post_on_delete($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

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

        // Get categories and tags as arrays
        $categories_arr = wp_get_post_categories($post->ID, array('fields' => 'names'));
        $tags_arr = wp_get_post_tags($post->ID, array('fields' => 'names'));

        // Description: excerpt + full content + attributes (for semantic search)
        // Categories, tags, SKU, brand go as separate fields for parser filtering
        $description_parts = array();

        if (!empty($excerpt)) {
            $description_parts[] = $excerpt;
        }

        if (!empty($content)) {
            $description_parts[] = $content;
        }

        // WooCommerce Product Enhancement
        $price = 0.0;
        $stock = 0;
        $sku = '';
        $brand = '';
        if ($post->post_type === 'product' && class_exists('WooCommerce')) {
            $product = wc_get_product($post->ID);

            if ($product) {
                $price = (float) $product->get_price();
                $stock = (int) $product->get_stock_quantity();
                $sku = $product->get_sku() ?: '';

                // Short Description
                $short_desc = $product->get_short_description();
                if (!empty($short_desc)) {
                    $description_parts[] = wp_strip_all_tags($short_desc);
                }

                // Product Categories (taxonomy: product_cat) - override post categories
                $product_cats = get_the_terms($post->ID, 'product_cat');
                if ($product_cats && !is_wp_error($product_cats)) {
                    $categories_arr = wp_list_pluck($product_cats, 'name');
                }

                // Product Tags (taxonomy: product_tag) - override post tags
                $product_tags = get_the_terms($post->ID, 'product_tag');
                if ($product_tags && !is_wp_error($product_tags)) {
                    $tags_arr = wp_list_pluck($product_tags, 'name');
                }

                // Product Attributes (Color, Size, Material, etc.) - keep in description
                $attributes = $product->get_attributes();
                if (!empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        if (is_a($attribute, 'WC_Product_Attribute')) {
                            $name = wc_attribute_label($attribute->get_name());
                            $values = $product->get_attribute($attribute->get_name());
                            if (!empty($values)) {
                                $description_parts[] = $name . ": " . $values;
                            }
                        }
                    }
                }

                // Brand: check common brand taxonomies, then product attribute
                $brand = $this->get_product_brand($post->ID, $product);
            }
        }

        $full_description = implode("\n\n", $description_parts);

        // Get featured image
        $image_url = get_the_post_thumbnail_url($post->ID, 'medium');

        // Calculate margin based on post importance
        $margin = 0.5;
        if (is_sticky($post->ID)) {
            $margin = 1.0;
        } elseif ($post->post_type === 'product' && class_exists('WooCommerce')) {
            $product = isset($product) ? $product : wc_get_product($post->ID);
            if ($product && $product->is_featured()) {
                $margin = 1.0;
            }
        }

        // Build record with all API fields
        $record = array(
            'id'         => 'wp-' . $post->ID,
            'name'       => $post->post_title,
            'description'=> $full_description,
            'type'       => $post->post_type,
            'platform'   => 'wordpress',
            'price'      => $price,
            'url'        => get_permalink($post->ID),
            'image_url'  => $image_url ?: '',
            'stock'      => $stock,
            'margin'     => $margin,
            'sku'        => $sku,
            'categories' => !empty($categories_arr) ? implode(', ', $categories_arr) : '',
            'tags'       => !empty($tags_arr) ? implode(', ', $tags_arr) : '',
            'brand'      => $brand,
        );

        return $record;
    }

    /**
     * Get product brand from taxonomy or attribute
     */
    private function get_product_brand($post_id, $product) {
        // Check common brand taxonomies (WooCommerce Brands, YITH, Perfect Brands)
        $brand_taxonomies = array('product_brand', 'yith_product_brand', 'pwb-brand');
        foreach ($brand_taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $terms = get_the_terms($post_id, $taxonomy);
                if ($terms && !is_wp_error($terms)) {
                    return $terms[0]->name;
                }
            }
        }

        // Check product attribute named "brand" or "marka"
        $brand_value = $product->get_attribute('brand');
        if (!empty($brand_value)) {
            return $brand_value;
        }
        $brand_value = $product->get_attribute('marka');
        if (!empty($brand_value)) {
            return $brand_value;
        }

        return '';
    }

    /**
     * AJAX: Get sync info for batched import
     *
     * Returns plan limits, total post count, and batch size
     * so the frontend can plan the batched import.
     */
    public function ajax_get_sync_info() {
        check_ajax_referer('queryra_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        // Get plan limits from API
        $stats = $this->api->get_stats();
        if (is_wp_error($stats)) {
            wp_send_json_error(array('message' => $stats->get_error_message()));
        }

        $record_limit = isset($stats['record_limit']) ? (int) $stats['record_limit'] : 100;
        $total_records = isset($stats['total_records']) ? (int) $stats['total_records'] : 0;
        $plan = isset($stats['plan']) ? $stats['plan'] : 'free';

        // Count published WordPress posts
        $post_types = get_option('queryra_post_types', array('post', 'page'));
        $wp_query = new WP_Query(array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ));
        $total_wp_posts = (int) $wp_query->found_posts;

        // How many we'll actually send (respect plan limit)
        $will_sync = ($record_limit > 0) ? min($total_wp_posts, $record_limit) : $total_wp_posts;

        // Cache stats for search integration
        update_option('queryra_cached_stats', $stats);

        wp_send_json_success(array(
            'total_wp_posts'  => $total_wp_posts,
            'record_limit'    => $record_limit,
            'current_records' => $total_records,
            'will_sync'       => $will_sync,
            'batch_size'      => 50,
            'plan'            => $plan,
        ));
    }

    /**
     * AJAX: Sync a single batch of posts
     *
     * Fetches a page of post IDs and syncs them to Queryra.
     * Called repeatedly by the frontend for batched import.
     */
    public function ajax_sync_batch() {
        check_ajax_referer('queryra_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $offset     = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 50;
        $batch_size = min($batch_size, 100);

        $post_types = get_option('queryra_post_types', array('post', 'page'));

        $post_ids = get_posts(array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ));

        if (empty($post_ids)) {
            wp_send_json_success(array(
                'synced'  => 0,
                'message' => 'No more posts to sync',
            ));
            return;
        }

        $result = $this->sync_posts($post_ids);

        if ($result['success']) {
            wp_send_json_success(array(
                'synced'  => count($post_ids),
                'message' => $result['message'],
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
            ));
        }
    }

    /**
     * AJAX: Sync all posts (legacy - kept for backward compatibility)
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

        // Track sync start
        Queryra_Analytics::track('sync_started');

        // Sync posts
        $result = $this->sync_posts($post_ids);

        if ($result['success']) {
            // Track sync completion
            Queryra_Analytics::track('sync_completed');
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
