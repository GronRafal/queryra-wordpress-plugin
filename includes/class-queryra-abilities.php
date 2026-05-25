<?php
/**
 * Queryra Abilities API Integration (WordPress 7.0+)
 *
 * Registers Queryra's semantic search as a discoverable WordPress 7.0
 * "ability" via wp_register_ability() inside the wp_abilities_api_init
 * hook. AI agents, chatbots, and other plugins running on WP 7.0+ can
 * then discover and invoke semantic search programmatically.
 *
 * This file is loaded ONLY when wp_register_ability() exists in core
 * (conditional require_once in the main plugin file). Still defensive —
 * every method re-checks function_exists() in case of partial WordPress
 * upgrades or feature flag environments.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Abilities {

    /** Ability identifier exposed to WP 7.0 AI agents. */
    const ABILITY_NAME  = 'queryra/semantic-search';
    const CATEGORY_SLUG = 'queryra/search';

    /**
     * API client, lazily instantiated on first execute.
     * @var Queryra_API|null
     */
    private $api = null;

    public function __construct() {
        add_action('wp_abilities_api_categories_init', array($this, 'register_category'));
        add_action('wp_abilities_api_init',            array($this, 'register_ability'));
    }

    /**
     * Register the `queryra/search` category so abilities group cleanly
     * in WP 7.0 admin and any client-side listing UIs.
     */
    public function register_category() {
        if (!function_exists('wp_register_ability_category')) {
            return;
        }

        wp_register_ability_category(self::CATEGORY_SLUG, array(
            'label'       => __('Queryra Search', 'queryra-ai-search'),
            'description' => __('AI semantic search abilities provided by Queryra.', 'queryra-ai-search'),
        ));
    }

    /**
     * Register the `queryra/semantic-search` ability.
     *
     * AI agents discover this via the Abilities API REST endpoint or
     * client-side store and can invoke it with a natural-language query.
     */
    public function register_ability() {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        wp_register_ability(self::ABILITY_NAME, array(
            'label'               => __('Semantic Product & Content Search', 'queryra-ai-search'),
            'description'         => __( 'Search WordPress posts, pages, custom post types, and WooCommerce products using natural language. Returns relevance-ranked results based on semantic meaning, not keyword matching. Understands intent, price filters, and brand exclusions.', 'queryra-ai-search' ),
            'category'            => self::CATEGORY_SLUG,
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'query' => array(
                        'type'        => 'string',
                        'description' => __('Natural-language search query.', 'queryra-ai-search'),
                        'minLength'   => 1,
                    ),
                    'limit' => array(
                        'type'        => 'integer',
                        'description' => __('Maximum number of results to return.', 'queryra-ai-search'),
                        'minimum'     => 1,
                        'maximum'     => 100,
                        'default'     => 10,
                    ),
                    'post_type' => array(
                        'type'        => 'string',
                        'description' => __('Optional filter: limit results to a specific post type (post, page, product, etc.).', 'queryra-ai-search'),
                    ),
                ),
                'required'             => array('query'),
                'additionalProperties' => false,
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'results' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'id'        => array('type' => 'integer'),
                                'title'     => array('type' => 'string'),
                                'url'       => array('type' => 'string', 'format' => 'uri'),
                                'excerpt'   => array('type' => 'string'),
                                'post_type' => array('type' => 'string'),
                                'score'     => array('type' => 'number'),
                            ),
                        ),
                    ),
                    'total' => array(
                        'type'        => 'integer',
                        'description' => __('Total number of results returned.', 'queryra-ai-search'),
                    ),
                    'query' => array(
                        'type'        => 'string',
                        'description' => __('The original query string (echoed back for confirmation).', 'queryra-ai-search'),
                    ),
                ),
            ),
            'execute_callback'    => array($this, 'execute'),
            'permission_callback' => array($this, 'check_permission'),
            'meta'                => array(
                'annotations' => array(
                    'readonly'      => true,
                    'idempotent'    => true,
                    'destructive'   => false,
                ),
                'show_in_rest' => true,
            ),
        ));
    }

    /**
     * Permission gate. Semantic search is a public, read-only capability —
     * any caller (including unauthenticated AI agents) may invoke it,
     * provided the plugin is configured and AI search is enabled.
     */
    public function check_permission($input = null) {
        if (empty(get_option('queryra_api_key'))) {
            return new WP_Error(
                'queryra_not_configured',
                __('Queryra API key is not configured.', 'queryra-ai-search')
            );
        }
        if (get_option('queryra_ai_search') !== '1') {
            return new WP_Error(
                'queryra_disabled',
                __('Queryra AI search is disabled in plugin settings.', 'queryra-ai-search')
            );
        }
        return true;
    }

    /**
     * Execute the search. Reuses the same Queryra_API::search() the
     * frontend search interception uses — single code path keeps results
     * consistent regardless of caller (theme search vs. AI agent).
     */
    public function execute($input = null) {
        $query     = isset($input['query']) ? trim((string) $input['query']) : '';
        $limit     = isset($input['limit']) ? (int) $input['limit'] : 10;
        $post_type = isset($input['post_type']) ? sanitize_key($input['post_type']) : '';

        if ($query === '') {
            return new WP_Error('queryra_no_query', __('A query string is required.', 'queryra-ai-search'));
        }
        if ($limit < 1)   { $limit = 10; }
        if ($limit > 100) { $limit = 100; }

        if ($this->api === null) {
            $this->api = new Queryra_API();
        }

        $api_response = $this->api->search($query, $limit);
        if (is_wp_error($api_response)) {
            return $api_response;
        }

        $raw_results = isset($api_response['results']) && is_array($api_response['results'])
            ? $api_response['results']
            : array();

        $formatted = array();
        foreach ($raw_results as $row) {
            if (empty($row['id']) || strpos($row['id'], 'wp-') !== 0) {
                continue;
            }
            $post_id = (int) substr($row['id'], 3);
            if ($post_id <= 0) {
                continue;
            }
            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') {
                continue;
            }
            // Optional post_type filter (applied client-side; API returns all)
            if ($post_type !== '' && $post->post_type !== $post_type) {
                continue;
            }

            $formatted[] = array(
                'id'        => $post_id,
                'title'     => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
                'url'       => get_permalink($post),
                'excerpt'   => wp_strip_all_tags(get_the_excerpt($post)),
                'post_type' => $post->post_type,
                'score'     => isset($row['score']) ? (float) $row['score'] : 0.0,
            );
        }

        return array(
            'results' => $formatted,
            'total'   => count($formatted),
            'query'   => $query,
        );
    }
}
