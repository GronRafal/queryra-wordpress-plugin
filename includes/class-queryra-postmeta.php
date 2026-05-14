<?php
/**
 * Queryra Postmeta Content Extractor
 *
 * Extracts visible text content from page builders that store data in
 * wp_postmeta instead of post_content (Elementor, Breakdance, Oxygen,
 * Beaver Builder).
 *
 * Plain Gutenberg blocks, WPBakery, and Divi do not need this — their
 * text lives inside post_content as HTML or shortcodes and is already
 * captured by wp_strip_all_tags() in Queryra_Sync::build_record().
 *
 * Architecture:
 *   - One static entry point: get_content_for($post_id)
 *   - Detects which builder is in use by checking known meta keys
 *   - Each builder has a dedicated extractor (different storage formats)
 *   - Extractors return plain text; we strip CSS values, IDs, and
 *     other technical noise before returning
 *   - A developer filter (queryra_indexable_meta_content) lets sites
 *     plug in custom integrations without waiting for plugin updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_Postmeta {

    /**
     * Whitelisted keys per builder — only values under these keys are
     * extracted as visible content. Everything else (CSS, classes, IDs,
     * widget settings) is ignored.
     */
    const ELEMENTOR_TEXT_KEYS  = array('title', 'text', 'editor', 'content', 'caption', 'description', 'heading', 'button_text', 'subtitle');
    const BREAKDANCE_TEXT_KEYS = array('text', 'title', 'content', 'heading', 'subtitle', 'caption', 'button_text', 'description');
    const OXYGEN_TEXT_KEYS     = array('text', 'content', 'heading', 'title', 'button_text');

    /**
     * Main entry point. Returns a single string with all visible text
     * extracted from postmeta for the given post.
     */
    public static function get_content_for($post_id) {
        $parts = array();

        $elementor = self::extract_elementor($post_id);
        if ($elementor !== '') {
            $parts[] = $elementor;
        }

        $breakdance = self::extract_breakdance($post_id);
        if ($breakdance !== '') {
            $parts[] = $breakdance;
        }

        $beaver = self::extract_beaver_builder($post_id);
        if ($beaver !== '') {
            $parts[] = $beaver;
        }

        $oxygen = self::extract_oxygen($post_id);
        if ($oxygen !== '') {
            $parts[] = $oxygen;
        }

        $acf = self::extract_acf($post_id);
        if ($acf !== '') {
            $parts[] = $acf;
        }

        $metabox = self::extract_metabox($post_id);
        if ($metabox !== '') {
            $parts[] = $metabox;
        }

        $combined = implode("\n", $parts);

        /**
         * Filter: queryra_indexable_meta_content
         *
         * Lets developers add their own extracted content from any custom
         * field plugin (ACF, Meta Box, Pods, etc.) or unsupported builder.
         *
         * @param string $combined Text already extracted by built-in parsers.
         * @param int    $post_id  Post ID being processed.
         */
        $combined = apply_filters('queryra_indexable_meta_content', $combined, $post_id);

        return self::clean_text($combined);
    }

    /* ─────────────────────────────────────────────────────────────────
     * Elementor — stores entire page as JSON in `_elementor_data`.
     * Structure: array of sections → columns → widgets → settings.
     * Visible text lives under specific settings keys like `title`,
     * `editor`, `text`, etc.
     * ───────────────────────────────────────────────────────────────── */
    private static function extract_elementor($post_id) {
        $raw = get_post_meta($post_id, '_elementor_data', true);
        if (empty($raw)) {
            return '';
        }

        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            return '';
        }

        $texts = array();
        self::walk_keyed_extract($data, self::ELEMENTOR_TEXT_KEYS, $texts);
        return implode("\n", array_filter($texts));
    }

    /* ─────────────────────────────────────────────────────────────────
     * Breakdance — stores tree as JSON in `_breakdance_data`.
     * Similar nested structure to Elementor; same extraction strategy.
     * ───────────────────────────────────────────────────────────────── */
    private static function extract_breakdance($post_id) {
        $raw = get_post_meta($post_id, '_breakdance_data', true);
        if (empty($raw)) {
            return '';
        }

        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            return '';
        }

        $texts = array();
        self::walk_keyed_extract($data, self::BREAKDANCE_TEXT_KEYS, $texts);
        return implode("\n", array_filter($texts));
    }

    /* ─────────────────────────────────────────────────────────────────
     * Beaver Builder — `_fl_builder_data` is a serialized PHP array of
     * objects keyed by node ID. Each node has a `settings` property
     * (stdClass) containing visible text under various property names.
     * ───────────────────────────────────────────────────────────────── */
    private static function extract_beaver_builder($post_id) {
        $raw = get_post_meta($post_id, '_fl_builder_data', true);
        if (empty($raw)) {
            return '';
        }

        // WordPress auto-unserializes; fall back to maybe_unserialize for safety.
        $data = is_string($raw) ? maybe_unserialize($raw) : $raw;
        if (!is_array($data) && !is_object($data)) {
            return '';
        }

        $texts = array();
        // Convert to array for uniform walking.
        $data_array = json_decode(wp_json_encode($data), true);
        if (!is_array($data_array)) {
            return '';
        }

        // Beaver uses arbitrary text property names per module; do a generic
        // visible-text walk rather than a key whitelist.
        self::walk_generic_extract($data_array, $texts);
        return implode("\n", array_filter($texts));
    }

    /* ─────────────────────────────────────────────────────────────────
     * Oxygen — two storage formats depending on version:
     *   - Legacy: `_ct_builder_shortcodes` (shortcode string)
     *   - Modern: `_ct_builder_json_v2` (JSON tree, similar to Elementor)
     * We try both and combine.
     * ───────────────────────────────────────────────────────────────── */
    private static function extract_oxygen($post_id) {
        $parts = array();

        // Legacy: shortcode dump
        $shortcodes = get_post_meta($post_id, '_ct_builder_shortcodes', true);
        if (!empty($shortcodes) && is_string($shortcodes)) {
            // Strip shortcode brackets/attrs, keep inner text
            $stripped = preg_replace('/\[[^\]]+\]/', ' ', $shortcodes);
            $parts[] = wp_strip_all_tags($stripped);
        }

        // Modern: JSON tree
        $json = get_post_meta($post_id, '_ct_builder_json_v2', true);
        if (!empty($json)) {
            $data = is_string($json) ? json_decode($json, true) : $json;
            if (is_array($data)) {
                $texts = array();
                self::walk_keyed_extract($data, self::OXYGEN_TEXT_KEYS, $texts);
                $parts[] = implode("\n", array_filter($texts));
            }
        }

        return implode("\n", array_filter($parts));
    }

    /* ─────────────────────────────────────────────────────────────────
     * ACF (Advanced Custom Fields) — uses ACF's own get_fields() API
     * which returns a clean associative array of [field_name => value]
     * skipping internal _field_* keys. Works with both ACF Free and Pro.
     *
     * Field types we index implicitly (via is_visible_text filter):
     *   - text, textarea, wysiwyg, email, url (when readable)
     * Field types skipped automatically by the filter:
     *   - image (URL), file (URL), gallery (array of URLs), date, true_false,
     *     select option values (usually < 3 chars or numeric), radio, etc.
     * Nested types (repeater, group, flexible_content) are walked recursively.
     * ───────────────────────────────────────────────────────────────── */
    private static function extract_acf($post_id) {
        if (!function_exists('get_fields')) {
            return ''; // ACF not active
        }

        $values = get_fields($post_id);
        if (!is_array($values) || empty($values)) {
            return '';
        }

        $texts = array();
        self::walk_generic_extract($values, $texts);
        return implode("\n", array_filter($texts));
    }

    /* ─────────────────────────────────────────────────────────────────
     * Meta Box — popular alternative to ACF (~600k active installs).
     * Stores field values in wp_postmeta under user-defined keys, so we
     * cannot detect them by key. Instead, we read the field registry via
     * the `rwmb_meta_boxes` filter and iterate registered text fields.
     * Group fields (Meta Box's "group" type) are walked recursively.
     * ───────────────────────────────────────────────────────────────── */
    private static function extract_metabox($post_id) {
        if (!function_exists('rwmb_meta')) {
            return ''; // Meta Box not active
        }

        $post_type = get_post_type($post_id);
        if (!$post_type) {
            return '';
        }

        $meta_boxes = apply_filters('rwmb_meta_boxes', array());
        if (empty($meta_boxes) || !is_array($meta_boxes)) {
            return '';
        }

        $text_types = array('text', 'textarea', 'wysiwyg', 'email', 'url', 'heading');
        $texts      = array();

        foreach ($meta_boxes as $meta_box) {
            // Filter to current post type
            $box_post_types = isset($meta_box['post_types']) ? (array) $meta_box['post_types'] : array('post');
            if (!in_array($post_type, $box_post_types, true)) {
                continue;
            }

            if (empty($meta_box['fields']) || !is_array($meta_box['fields'])) {
                continue;
            }

            foreach ($meta_box['fields'] as $field) {
                self::collect_metabox_field($field, $post_id, $text_types, $texts);
            }
        }

        return implode("\n", array_filter($texts));
    }

    private static function collect_metabox_field($field, $post_id, $text_types, &$texts) {
        if (empty($field['id']) || empty($field['type'])) {
            return;
        }

        $type = $field['type'];
        $id   = $field['id'];

        // Group: recurse into sub-fields
        if ($type === 'group' && !empty($field['fields']) && is_array($field['fields'])) {
            $group_value = rwmb_meta($id, '', $post_id);
            if (!is_array($group_value)) {
                return;
            }
            // Clone groups return array of items; single group returns flat array
            $items = (isset($group_value[0]) && is_array($group_value[0])) ? $group_value : array($group_value);

            foreach ($items as $item) {
                if (!is_array($item)) continue;
                foreach ($field['fields'] as $subfield) {
                    if (empty($subfield['id']) || empty($subfield['type'])) continue;
                    if (!in_array($subfield['type'], $text_types, true)) continue;
                    if (!isset($item[$subfield['id']])) continue;

                    $value = $item[$subfield['id']];
                    if (is_string($value)) {
                        $clean = wp_strip_all_tags($value);
                        if (self::is_visible_text($clean)) {
                            $texts[] = $clean;
                        }
                    }
                }
            }
            return;
        }

        // Text-bearing fields
        if (!in_array($type, $text_types, true)) {
            return;
        }

        $value = rwmb_meta($id, '', $post_id);

        if (is_string($value)) {
            $clean = wp_strip_all_tags($value);
            if (self::is_visible_text($clean)) {
                $texts[] = $clean;
            }
        } elseif (is_array($value)) {
            // Cloneable text fields return an array of values
            foreach ($value as $v) {
                if (!is_string($v)) continue;
                $clean = wp_strip_all_tags($v);
                if (self::is_visible_text($clean)) {
                    $texts[] = $clean;
                }
            }
        }
    }

    /* ─────────────────────────────────────────────────────────────────
     * Walkers — two strategies:
     *
     * 1. walk_keyed_extract — for builders with known text-bearing keys
     *    (Elementor, Breakdance, Oxygen). Precise; no false positives.
     *
     * 2. walk_generic_extract — for builders with arbitrary structure
     *    (Beaver Builder). Looser; relies on noise filtering downstream.
     * ───────────────────────────────────────────────────────────────── */

    private static function walk_keyed_extract($data, $keys, &$out) {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                self::walk_keyed_extract($value, $keys, $out);
                continue;
            }

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            // Skip keys starting with underscore (internal settings: _padding, _margin, etc.)
            if (is_string($key) && substr($key, 0, 1) === '_') {
                continue;
            }

            if (in_array($key, $keys, true) && self::is_visible_text($value)) {
                $out[] = wp_strip_all_tags($value);
            }
        }
    }

    private static function walk_generic_extract($data, &$out) {
        array_walk_recursive($data, function($value, $key) use (&$out) {
            if (!is_string($value) || trim($value) === '') {
                return;
            }
            if (is_string($key) && substr($key, 0, 1) === '_') {
                return;
            }
            if (!self::is_visible_text($value)) {
                return;
            }
            $out[] = wp_strip_all_tags($value);
        });
    }

    /**
     * Decides whether a string looks like visible human-readable content
     * (vs. a CSS value, hex color, ID, class name, or other technical noise).
     */
    private static function is_visible_text($value) {
        $value = trim($value);
        $len   = strlen($value);

        if ($len < 3) {
            return false;
        }

        // Pure number, optionally with CSS unit
        if (preg_match('/^\d+(\.\d+)?(px|em|rem|%|vh|vw|pt|deg|s|ms)?$/i', $value)) {
            return false;
        }

        // Hex colors (#fff, #ffffff, #ffffff80)
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) {
            return false;
        }

        // CSS color functions
        if (preg_match('/^(rgba?|hsla?|var)\s*\(/i', $value)) {
            return false;
        }

        // Likely a CSS selector or class chain
        if (preg_match('/^[.#][\w\-]+(\s+[.#][\w\-]+)*$/', $value)) {
            return false;
        }

        // Single boolean-ish strings
        if (in_array(strtolower($value), array('yes', 'no', 'true', 'false', 'on', 'off', 'auto', 'inherit', 'none', 'default'), true)) {
            return false;
        }

        // Pure URL (image, file, page link in ACF fields — these are not content text)
        if (preg_match('/^https?:\/\/\S+$/i', $value)) {
            return false;
        }

        // Date/time formats (YYYY-MM-DD, HH:MM, timestamps)
        if (preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            return false;
        }

        // No letters at all → not human text
        if (!preg_match('/[a-zA-Z\x{00C0}-\x{017F}\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}]/u', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Final cleanup: normalize whitespace, dedupe newlines.
     */
    private static function clean_text($text) {
        if ($text === '' || $text === null) {
            return '';
        }
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
