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

            // Remove records when a post leaves `publish` (trash, draft,
            // private, pending). before_delete_post only covers permanent
            // deletion — without this hook a trashed product stays in the
            // remote index (consuming the plan's record quota and surfacing
            // in API results) until the trash is emptied, which on
            // keep-in-trash sites is never.
            add_action('transition_post_status', array($this, 'sync_post_status_transition'), 10, 3);

            // Sync when post becomes sticky/unsticky (margin changes!)
            add_action('post_stuck', array($this, 'sync_post_sticky_change'));
            add_action('post_unstuck', array($this, 'sync_post_sticky_change'));
        }

        // AJAX handlers for manual sync
        add_action('wp_ajax_queryra_test_connection', array($this, 'ajax_test_connection'));

        // Batched sync AJAX handlers
        add_action('wp_ajax_queryra_get_sync_info', array($this, 'ajax_get_sync_info'));
        add_action('wp_ajax_queryra_sync_batch', array($this, 'ajax_sync_batch'));

        // Client-side error reporter — JS posts here when the AJAX request to
        // queryra_sync_batch itself fails (timeout, 500, 502, 504, payload
        // limit, etc.). We forward to analytics so we can diagnose without
        // asking the customer for debug logs.
        add_action('wp_ajax_queryra_report_client_error', array($this, 'ajax_report_client_error'));
    }

    /**
     * Sync post on save
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function sync_post_on_save($post_id, $post, $update) {
        // No API key = plugin not connected yet. Bail before doing any
        // record-building work, and before the failure reporting below —
        // otherwise every save on a keyless install logs an "API key is
        // required" issue and fires telemetry for a non-problem.
        if (!get_option('queryra_api_key')) {
            return;
        }

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
        $result = $this->sync_posts(array($post_id));

        // Emit telemetry + local issue log on failure — this path is
        // otherwise completely silent: the editor saves a product, the
        // index never gets it, and nobody knows until search results
        // look stale. No admin notice here (yet) — just visibility.
        if (empty($result['success'])) {
            $this->report_auto_sync_failure('auto_save', $post, $result['message']);
        }
    }

    /**
     * Sync post when sticky status changes
     * WordPress triggers this when post becomes sticky/unsticky
     *
     * @param int $post_id Post ID
     */
    public function sync_post_sticky_change($post_id) {
        // Not connected yet — see sync_post_on_save().
        if (!get_option('queryra_api_key')) {
            return;
        }

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
        $result = $this->sync_posts(array($post_id));

        if (empty($result['success'])) {
            $this->report_auto_sync_failure('sticky_change', $post, $result['message']);
        }
    }

    /**
     * Delete post from Queryra
     *
     * @param int $post_id Post ID
     */
    public function delete_post_on_delete($post_id) {
        // Not connected yet — see sync_post_on_save().
        if (!get_option('queryra_api_key')) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        // Check if post type should be synced
        $post_types = get_option('queryra_post_types', array('post', 'page'));
        if (!in_array($post->post_type, $post_types)) {
            return;
        }

        $this->delete_remote_record($post, 'auto_delete');
    }

    /**
     * Remove a record from Queryra when its post leaves `publish`
     * (trashed, reverted to draft, made private, unpublished).
     *
     * Counterpart to sync_post_on_save(): that hook only pushes published
     * posts, so a publish→trash/draft transition would otherwise leave a
     * stale record in the remote index. Re-publishing later re-syncs the
     * post via save_post, so deleting here loses nothing.
     *
     * @param string  $new_status New post status
     * @param string  $old_status Old post status
     * @param WP_Post $post       Post object
     */
    public function sync_post_status_transition($new_status, $old_status, $post) {
        // Only act when a post stops being publicly visible.
        if ($old_status !== 'publish' || $new_status === 'publish') {
            return;
        }

        // Not connected yet — see sync_post_on_save().
        if (!get_option('queryra_api_key')) {
            return;
        }

        $post_types = get_option('queryra_post_types', array('post', 'page'));
        if (!in_array($post->post_type, $post_types)) {
            return;
        }

        $this->delete_remote_record($post, 'unpublish');
    }

    /**
     * Delete a post's record from the remote index, with shared error
     * handling for both deletion paths (permanent delete / unpublish).
     *
     * @param WP_Post $post    Post whose record should be removed
     * @param string  $context auto_delete|unpublish (for error reporting)
     */
    private function delete_remote_record($post, $context) {
        $record_id = 'wp-' . $post->ID;
        $response = $this->api->delete_record($record_id);

        if (is_wp_error($response)) {
            // Skip 404s — WordPress/WooCommerce routinely deletes transient
            // posts that were never synced (cart/session leftovers, drafts,
            // auto-generated orders), so "record not found" is expected
            // noise, not a malfunction. Reporting it would flood the channel
            // and the user's issue log with non-problems.
            $error_data = $response->get_error_data();
            if (is_array($error_data) && isset($error_data['status']) && (int) $error_data['status'] === 404) {
                return;
            }

            // Real failure (timeout, 5xx, auth) — the record stays in the
            // index as a ghost result. Same silent-path problem as save.
            $this->report_auto_sync_failure($context, $post, $response->get_error_message());
        }
    }

    /**
     * Report a failed automatic sync (save / sticky change / delete).
     *
     * Shared by the hook-driven sync paths, which previously discarded
     * all errors. Event name `error_reported` matches the batch-import
     * and search handlers — meta.category ("sync") and meta.context
     * narrow the surface. Bounded metadata, no PII, no post content.
     *
     * @param string  $context auto_save|sticky_change|auto_delete
     * @param WP_Post $post    The post being synced/deleted
     * @param string  $message Error message from the API layer
     */
    private function report_auto_sync_failure($context, $post, $message) {
        if (!class_exists('Queryra_Analytics')) {
            return;
        }

        Queryra_Analytics::report_error(array(
            'category'  => 'sync',
            'source'    => 'server',
            'context'   => $context,
            'post_type' => $post->post_type,
            'error'     => mb_substr((string) $message, 0, 200),
        ));
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

        // Prefetch all postmeta for this batch in a single query. Without this,
        // each call to get_post_meta() during build_record() (post content,
        // builder data, product fields, brand taxonomy meta) would issue its
        // own SQL query — at 50K records that means hundreds of thousands of
        // queries and a brutal bulk-import slowdown.
        update_meta_cache('post', $post_ids);

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

        // Get categories and tags as arrays
        $categories_arr = wp_get_post_categories($post->ID, array('fields' => 'names'));
        $tags_arr = wp_get_post_tags($post->ID, array('fields' => 'names'));

        // Description: manual excerpt (if any) + full content + attributes (for semantic search).
        // We deliberately do NOT use the auto-generated excerpt (wp_trim_words of $content),
        // because that would duplicate the first 30 words of content — biasing the embedding
        // and wasting payload bytes. A manual excerpt is included only if it is not already
        // present inside $content (avoids double-counting when the author copy-pasted).
        $description_parts = array();

        if (has_excerpt($post->ID)) {
            $excerpt = wp_strip_all_tags(get_the_excerpt($post->ID));
            if (!empty($excerpt) && ($content === '' || stripos($content, $excerpt) === false)) {
                $description_parts[] = $excerpt;
            }
        }

        if (!empty($content)) {
            $description_parts[] = $content;
        }

        // Page builder content stored outside post_content (Elementor, Breakdance,
        // Oxygen, Beaver Builder) plus any developer-supplied custom content via the
        // queryra_indexable_meta_content filter.
        $builder_content = Queryra_Postmeta::get_content_for($post->ID);
        if (!empty($builder_content) && ($content === '' || stripos($content, $builder_content) === false)) {
            $description_parts[] = $builder_content;
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

                // Short Description — include only if not already substring of $content
                // (overlap between long and short description is common in WooCommerce).
                $short_desc = $product->get_short_description();
                if (!empty($short_desc)) {
                    $short_desc_clean = wp_strip_all_tags($short_desc);
                    if (!empty($short_desc_clean) && ($content === '' || stripos($content, $short_desc_clean) === false)) {
                        $description_parts[] = $short_desc_clean;
                    }
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

        // Custom taxonomies — every public taxonomy on this post type that
        // is not already covered by a dedicated field (categories/tags/brand)
        // or is a WordPress system taxonomy. Sent as a separate `taxonomies`
        // field (slug => "term1, term2") so the API can index them with the
        // signal weight it wants, rather than mixed into description text.
        $custom_taxonomies = $this->collect_custom_taxonomies($post);

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
            // Cast to object so an empty map serializes as JSON {} (object),
            // not [] (array). The API validates `taxonomies` as an object;
            // an empty PHP array would JSON-encode as [] and trigger a 422
            // on every record without custom taxonomies.
            'taxonomies' => (object) $custom_taxonomies,
        );

        return $record;
    }

    /**
     * Collect terms from every public custom taxonomy registered for this
     * post type, excluding ones we already report under dedicated fields
     * (categories/tags/brand) and WordPress system taxonomies.
     *
     * Returns map: slug => "term1, term2"
     *
     * Developers can override the list via the `queryra_indexable_taxonomies`
     * filter (e.g., to remove a private internal taxonomy).
     */
    private function collect_custom_taxonomies($post) {
        $skip = array(
            // Already reported via dedicated fields
            'category', 'post_tag',
            'product_cat', 'product_tag',
            'product_brand', 'yith_product_brand', 'pwb-brand',
            // WordPress system / non-content
            'post_format', 'nav_menu', 'link_category',
        );

        $all_taxonomies = get_object_taxonomies($post->post_type, 'objects');
        if (empty($all_taxonomies)) {
            return array();
        }

        $indexable = array();
        foreach ($all_taxonomies as $tax_name => $tax_object) {
            if (empty($tax_object->public)) {
                continue;
            }
            if (in_array($tax_name, $skip, true)) {
                continue;
            }
            $indexable[$tax_name] = $tax_object;
        }

        /**
         * Filter: queryra_indexable_taxonomies
         *
         * Adjust which custom taxonomies are sent to Queryra.
         *
         * @param array  $indexable Map of slug => taxonomy object.
         * @param WP_Post $post     Post being indexed.
         */
        $indexable = apply_filters('queryra_indexable_taxonomies', $indexable, $post);

        $result = array();
        foreach ($indexable as $tax_name => $tax_object) {
            $terms = get_the_terms($post->ID, $tax_name);
            if (!$terms || is_wp_error($terms)) {
                continue;
            }
            $term_names = wp_list_pluck($terms, 'name');
            if (empty($term_names)) {
                continue;
            }
            $result[$tax_name] = implode(', ', $term_names);
        }

        return $result;
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
        update_option('queryra_cached_stats', $stats, false);

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
            // ID as tiebreaker: with hundreds of posts sharing one
            // post_date (CSV imports), bare date ordering is unstable
            // across queries — offset pagination could then skip some
            // posts entirely and sync others twice.
            'orderby'        => array('date' => 'DESC', 'ID' => 'DESC'),
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
            // Emit server-side telemetry for the failure so we get attribution
            // even when the customer cannot share their debug.log. Bounded
            // metadata (counts + truncated message) — no PII, no post content.
            //
            // Event name `error_reported` is a generic plugin error channel —
            // meta.category narrows the kind of error ("sync" here), meta.source
            // ("server" vs "client") narrows who emitted it. One event type
            // keeps the backend API contract minimal and lets us add new
            // failure surfaces later (search errors, key validation, etc.)
            // without coordinating a backend change every time.
            if (class_exists('Queryra_Analytics')) {
                Queryra_Analytics::report_error(array(
                    'category'    => 'sync',
                    'source'      => 'server',
                    'offset'      => $offset,
                    'batch_size'  => $batch_size,
                    'post_count'  => count($post_ids),
                    'post_types'  => array_values($post_types),
                    'error'       => mb_substr((string) $result['message'], 0, 200),
                ));
            }

            wp_send_json_error(array(
                'message' => $result['message'],
            ));
        }
    }

    /**
     * AJAX: Report a client-side import error.
     *
     * Called by wizard.js / admin.js when the browser request to
     * queryra_sync_batch fails outright (HTTP error, timeout, network drop,
     * payload limit). Forwards a small bounded telemetry event so we can
     * see error patterns server-side instead of relying on customers to
     * share debug.log.
     *
     * Honours the QUERYRA_DISABLE_ANALYTICS opt-out (handled by
     * Queryra_Analytics::track), so users who disabled analytics also
     * disable error telemetry.
     */
    public function ajax_report_client_error() {
        check_ajax_referer('queryra_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above by check_ajax_referer.
        $context      = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : 'unknown';
        $http_status  = isset($_POST['http_status']) ? absint($_POST['http_status']) : 0;
        $error_text   = isset($_POST['error_text']) ? sanitize_text_field(wp_unslash($_POST['error_text'])) : '';
        $batch_offset = isset($_POST['batch_offset']) ? absint($_POST['batch_offset']) : 0;
        $batch_size   = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 0;
        // phpcs:enable

        if (class_exists('Queryra_Analytics')) {
            // Shared event name with the server-side handler — meta.category
            // ("sync" here) and meta.source ("client") narrow the failure
            // surface. One event type → one backend record schema → one set
            // of dashboards. Reusable for any future client-side error
            // (search failure, key check failure, etc.) — just call with a
            // different category.
            Queryra_Analytics::report_error(array(
                'category'     => 'sync',
                'source'       => 'client',
                'context'      => $context,
                'http_status'  => $http_status,
                'error_text'   => mb_substr($error_text, 0, 300),
                'batch_offset' => $batch_offset,
                'batch_size'   => $batch_size,
            ));
        }

        wp_send_json_success();
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
