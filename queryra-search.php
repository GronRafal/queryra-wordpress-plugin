<?php
/**
 * Plugin Name: Queryra - AI Search
 * Plugin URI: https://github.com/GronRafal/queryra-wordpress-plugin
 * Description: AI-powered semantic search for your WordPress content. Automatically sends posts, pages, and custom post types to Queryra.
 * Version: 1.0.5
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
define('QUERYRA_VERSION', '1.0.5');
define('QUERYRA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QUERYRA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QUERYRA_PLUGIN_FILE', __FILE__);

// Include required files
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-api.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-sync.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-admin.php';
require_once QUERYRA_PLUGIN_DIR . 'includes/class-queryra-search.php';

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
        }

        // Initialize sync
        new Queryra_Sync();

        // Initialize search integration (AI-powered search)
        new Queryra_Search_Integration();
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
        if (!get_option('queryra_auto_sync')) {
            add_option('queryra_auto_sync', '1');
        }
        if (!get_option('queryra_ai_search')) {
            add_option('queryra_ai_search', '0'); // Disabled by default
        }
        if (!get_option('queryra_post_types')) {
            add_option('queryra_post_types', array('post', 'page'));
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize plugin
Queryra_Search::get_instance();
