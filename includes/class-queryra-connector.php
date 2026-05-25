<?php
/**
 * Queryra Connectors API Integration (WordPress 7.0+)
 *
 * Registers Queryra as a WordPress 7.0 connector so it appears in
 * Settings → Connectors alongside OpenAI, Anthropic, and Google.
 *
 * Connectors API is currently designed for LLM providers
 * (type: 'ai_provider'). Queryra is a semantic search service rather
 * than a generative AI provider, so we register with type='ai_provider'
 * as the closest existing fit. If WP core later adds a dedicated
 * 'search_provider' type, we'll switch.
 *
 * The connector's `setting_name` points at the SAME wp_options entry
 * (`queryra_api_key`) the rest of the plugin already reads/writes.
 * No duplicate storage, no sync logic — single source of truth.
 *
 * This file is loaded ONLY when the connector registry exists in core
 * (conditional require_once in the main plugin file).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Connector {

    const CONNECTOR_ID = 'queryra';

    public function __construct() {
        add_action('wp_connectors_init', array($this, 'register'));
    }

    /**
     * Register the Queryra connector with the global registry.
     *
     * @param object $registry The WP_Connector_Registry passed by core.
     *                         Typed loosely because the class doesn't exist
     *                         on WP < 7.0 — PHP would fatal-error on a
     *                         strict type hint at file load.
     */
    public function register($registry) {
        if (!is_object($registry) || !method_exists($registry, 'register')) {
            return;
        }
        if (method_exists($registry, 'is_registered') && $registry->is_registered(self::CONNECTOR_ID)) {
            return;
        }

        $registry->register(self::CONNECTOR_ID, array(
            'name'        => __('Queryra AI Search', 'queryra-ai-search'),
            'description' => __( 'AI semantic search connector for WordPress and WooCommerce. Finds posts, pages, and products by meaning, intent, and natural language.', 'queryra-ai-search' ),
            'logo_url'    => QUERYRA_PLUGIN_URL . 'assets/icon-128x128.png',
            // Use a dedicated 'search_connector' type instead of 'ai_provider'.
            // The Connectors API is designed to support additional types beyond
            // ai_provider (see make.wordpress.org Connectors API dev note),
            // and the WP/ai validation pipeline that produces the misleading
            // "It was not possible to connect" error is type-scoped: it runs
            // for ai_provider connectors by querying the PHP AI Client provider
            // registry (listModelMetadata → sendListModelsRequest), which Queryra
            // (a semantic search service, not a generative LLM) cannot satisfy.
            // Declaring our own type lets WP/ai recognize that this is not an
            // LLM provider and skip the LLM-specific credential check.
            'type'        => 'search_connector',
            'authentication' => array(
                'method'          => 'api_key',
                'credentials_url' => 'https://queryra.com/dashboard/api-keys',
                // Point at our existing option so the Connectors UI and the
                // Queryra Settings tab share a single source of truth.
                'setting_name'    => 'queryra_api_key',
                // Best-practice fallback sources: env var + PHP constant.
                // Useful for Docker / k8s / CI deployments where secrets
                // are injected via environment instead of stored in the
                // database. Resolution priority is env → constant → option.
                'env_var_name'    => 'QUERYRA_API_KEY',
                'constant_name'   => 'QUERYRA_API_KEY',
            ),
            'plugin' => array(
                'file' => QUERYRA_PLUGIN_BASENAME,
            ),
            // Provider metadata to give the Connectors screen context.
            'meta' => array(
                'website'   => 'https://queryra.com',
                'docs_url'  => 'https://queryra.com/docs',
                'signup_url'=> 'https://queryra.com/signup',
                'category'  => 'search',
            ),
        ));
    }
}
