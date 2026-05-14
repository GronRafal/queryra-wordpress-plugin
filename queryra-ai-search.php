<?php
/**
 * Plugin Name: AI Search for WooCommerce – Semantic Search
 * Plugin URI: https://github.com/GronRafal/queryra-wordpress-plugin
 * Description: AI-powered semantic search for your WordPress content. Automatically sends posts, pages, and custom post types to Queryra.
 * Version: 1.3.0
 * Author: Queryra
 * Author URI: https://queryra.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: queryra-ai-search
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('QUERYRA_VERSION', '1.3.0');
define('QUERYRA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QUERYRA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QUERYRA_PLUGIN_FILE', __FILE__);
define('QUERYRA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-api.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-sync.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-admin.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-search.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-setup-wizard.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-deactivation-survey.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-analytics.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-llms.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-postmeta.php';

/**
 * Main Plugin Class
 */
class Queryra_Search {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize admin
        if (is_admin()) {
            new Queryra_Admin();
            new Queryra_Setup_Wizard();
            new Queryra_Deactivation_Survey();
        }

        // Run upgrade routine if version changed
        $this->maybe_upgrade();

        // Initialize sync
        new Queryra_Sync();

        // Initialize search integration (AI-powered search)
        new Queryra_Search_Integration();

        // Serve /llms.txt and /llms-full.txt for AI crawlers
        new Queryra_LLMS();

        // Frontend fingerprint: enqueue stylesheet + generator meta tag
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_head', array($this, 'output_generator_meta'), 1);
    }

    /**
     * Enqueue a minimal frontend stylesheet so the plugin is discoverable
     * by WordPress ecosystem crawlers (themesinfo, WPHive, Wappalyzer).
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'queryra-ai-search',
            QUERYRA_PLUGIN_URL . 'css/queryra.css',
            array(),
            QUERYRA_VERSION
        );
    }

    /**
     * Output generator meta tag so detection tools (BuiltWith, Wappalyzer)
     * can fingerprint the plugin and its version.
     */
    public function output_generator_meta() {
        echo '<meta name="generator" content="Queryra AI Search ' . esc_attr(QUERYRA_VERSION) . '" />' . "\n";
    }

    /**
     * Run one-time upgrade tasks when plugin version changes.
     */
    private function maybe_upgrade() {
        $stored_version = get_option('queryra_plugin_version', '0');

        if (version_compare($stored_version, QUERYRA_VERSION, '>=')) {
            return;
        }

        // 1.1.6: ping status to register instance_id and plugin_type
        if (version_compare($stored_version, '1.1.6', '<') && get_option('queryra_api_key')) {
            $api = new Queryra_API();
            $api->get_status();
        }

        update_option('queryra_plugin_version', QUERYRA_VERSION);
    }

    /**
     * Activation
     */
    public function activate() {
        // Set default options
        if (!get_option('queryra_api_key')) {
            add_option('queryra_api_key', '');
        }
        if (!get_option('queryra_api_url')) {
            add_option('queryra_api_url', 'https://queryra.com');
        }
        if (!get_option('queryra_enabled')) {
            add_option('queryra_enabled', '1'); // Enabled by default
        }
        if (!get_option('queryra_auto_sync')) {
            add_option('queryra_auto_sync', '1');
        }
        if (!get_option('queryra_ai_search')) {
            add_option('queryra_ai_search', '0'); // Disabled by default
        }
        if (!get_option('queryra_post_types')) {
            add_option('queryra_post_types', array('post', 'page'));
        }

        // Set transient for first-time activation redirect to wizard
        set_transient('queryra_activation_redirect', true, 30);

        // Flush rewrite rules
        flush_rewrite_rules();

        // Track activation (anonymous)
        Queryra_Analytics::track('plugin_activated');
    }

    /**
     * Deactivation
     */
    public function deactivate() {
        // Track deactivation (anonymous)
        Queryra_Analytics::track('plugin_deactivated');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize plugin
Queryra_Search::get_instance();
