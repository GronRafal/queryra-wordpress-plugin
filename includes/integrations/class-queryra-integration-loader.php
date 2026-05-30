<?php
/**
 * Queryra Integration Loader
 *
 * Conditionally loads third-party plugin integrations. Each integration
 * lives in its own file and is loaded ONLY when the target plugin is
 * active — so the ~99% of sites without these plugins never parse a
 * single line of integration code.
 *
 * Integrations are intentionally kept out of Queryra core: they hook into
 * other plugins' internals (which can change between releases), so isolating
 * them keeps the core stable and makes each integration independently
 * removable.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Integration_Loader {

    /**
     * Load every integration whose target plugin is currently active.
     *
     * Called from the main plugin's init() which already runs on
     * plugins_loaded — by then all other plugins (B2BKing, etc.) are
     * loaded, so class/function existence checks are reliable.
     */
    public function load() {
        $dir = QUERYRA_PLUGIN_DIR . 'includes/integrations/';

        // B2BKing (B2B for WooCommerce) — bulk order form semantic search.
        if (function_exists('b2bking') || class_exists('B2bking')) {
            require_once $dir . 'class-queryra-b2bking.php';
            new Queryra_B2BKing();
        }

        // Future integrations:
        // if (defined('SOME_PLUGIN_VERSION')) { require_once $dir . 'class-queryra-someplugin.php'; new Queryra_SomePlugin(); }
    }
}
