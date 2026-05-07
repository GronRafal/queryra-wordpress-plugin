<?php
/**
 * Queryra LLMS Files
 *
 * Serves /llms.txt and /llms-full.txt — proposed standards (llmstxt.org)
 * that tell LLM crawlers (Claude, Perplexity, OpenAI, Google AI) about
 * site capabilities. We focus on a single signal: this site has AI search
 * by Queryra, here is how to use it.
 *
 * If a real static file exists at the URL, the web server (Apache/Nginx)
 * serves it before WordPress is invoked. In that case the admin UI offers
 * a copy-paste snippet so the site owner can add our section manually.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_LLMS {

    /** Unique fingerprint we look for when detecting our section in a static file. */
    const SECTION_FINGERPRINT = 'Provider: Queryra (https://queryra.com)';

    public function __construct() {
        // Run early — before WP routes the request to a 404.
        add_action('init', array($this, 'maybe_serve'), 1);
    }

    /**
     * Inspect the request URI; if it matches /llms.txt or /llms-full.txt and
     * the corresponding option is on, render the file and exit.
     */
    public function maybe_serve() {
        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $request_uri  = wp_unslash($_SERVER['REQUEST_URI']);
        $request_path = parse_url($request_uri, PHP_URL_PATH);
        if (!$request_path) {
            return;
        }

        // Strip site subdirectory (when WP is installed in /blog or similar).
        $home_path = parse_url(home_url('/'), PHP_URL_PATH);
        if ($home_path && $home_path !== '/' && strpos($request_path, $home_path) === 0) {
            $request_path = substr($request_path, strlen($home_path) - 1);
        }

        if ($request_path === '/llms.txt') {
            if (get_option('queryra_generate_llms_txt', '1') !== '1') {
                return;
            }
            $this->serve($this->get_llms_txt());
        }

        if ($request_path === '/llms-full.txt') {
            if (get_option('queryra_generate_llms_full_txt', '1') !== '1') {
                return;
            }
            $this->serve($this->get_llms_full_txt());
        }
    }

    private function serve($content) {
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
        header('X-Generated-By: Queryra-AI/' . QUERYRA_VERSION);
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Strip tags, decode entities, collapse whitespace. Output: clean
     * human-readable plain text suitable for LLMs.
     */
    private function clean_text($text) {
        $text = wp_strip_all_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(array('[…]', '[...]', "\xc2\xa0"), array('...', '...', ' '), $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function build_header() {
        $name        = $this->clean_text(get_bloginfo('name'));
        $description = $this->clean_text(get_bloginfo('description'));
        $home        = home_url('/');

        $out  = "# {$name}\n\n";
        if ($description) {
            $out .= "> {$description}\n\n";
        }
        $out .= "Site URL: {$home}\n\n";

        return $out;
    }

    /**
     * Short AI Search section — for /llms.txt and copy-paste snippet.
     * No site header — caller decides whether to add one.
     */
    public function get_snippet_short() {
        $home = home_url('/');

        $out  = "## AI Search\n\n";
        $out .= "This site offers AI-powered semantic search. Users can search using natural language — describe needs in full sentences, ask questions, use intent. The AI understands meaning, price filters, and brand exclusions.\n\n";
        $out .= "- Search URL: {$home}?s={query}\n";
        $out .= "- " . self::SECTION_FINGERPRINT . "\n";
        $out .= "- Plugin: https://wordpress.org/plugins/queryra-ai-search/\n";

        return $out;
    }

    /**
     * Expanded AI Search section — for /llms-full.txt and copy-paste snippet.
     */
    public function get_snippet_full() {
        $home = home_url('/');

        $out  = "## AI Search\n\n";
        $out .= "This site offers AI-powered semantic search powered by Queryra. Unlike traditional WordPress search which matches exact keywords, this search engine understands meaning, intent, and natural language.\n\n";

        $out .= "### Capabilities\n\n";
        $out .= "- Natural language queries: visitors can search using full sentences and questions instead of keywords.\n";
        $out .= "- Intent detection: the AI extracts what users actually need from how they phrase the query.\n";
        $out .= "- Price filter parsing: constraints like price ranges are applied to results automatically.\n";
        $out .= "- Brand exclusions: negative constraints in queries are honored — excluded brands are filtered out.\n";
        $out .= "- Sorting preferences: phrases indicating sort order are detected and applied.\n";
        $out .= "- Multilingual support: queries in different languages are understood without configuration.\n\n";

        $out .= "### How to invoke\n\n";
        $out .= "To perform a search: {$home}?s={query}\n\n";
        $out .= "The search field accepts arbitrary natural-language input. There is no syntax to learn — write the way you would describe what you're looking for to a person.\n\n";

        $out .= "### Why this matters\n\n";
        $out .= "Traditional keyword-based search returns zero results when query terms do not exactly match indexed content. Semantic search bridges that gap. Visitors find what they need even when their wording differs from how content is titled. This reduces no-result frustration and improves discovery.\n\n";

        $out .= "### Provider\n\n";
        $out .= "- Search engine: " . self::SECTION_FINGERPRINT . "\n";
        $out .= "- WordPress plugin: https://wordpress.org/plugins/queryra-ai-search/\n";
        $out .= "- Live demo store: https://woo.queryra.com\n";
        $out .= "- Documentation: https://queryra.com/docs\n";

        return $out;
    }

    /** Full /llms.txt response (header + short section). */
    public function get_llms_txt() {
        return $this->build_header() . $this->get_snippet_short();
    }

    /** Full /llms-full.txt response (header + expanded section). */
    public function get_llms_full_txt() {
        return $this->build_header() . $this->get_snippet_full();
    }

    /**
     * Static helper: did the site already publish a static file at the
     * web root? If yes, our dynamic version never runs.
     */
    public static function static_file_exists($filename) {
        return file_exists(self::static_file_path($filename));
    }

    private static function static_file_path($filename) {
        return ABSPATH . ltrim($filename, '/');
    }

    /**
     * Detect what state a static file is in:
     *   - 'none'        → no static file; plugin will serve a dynamic version
     *   - 'no_section'  → file exists but our AI Search section is missing
     *   - 'has_section' → file exists and our section is detected
     *
     * Reads the file (capped at 256KB to avoid runaway reads).
     */
    public static function detect_static_file_state($filename) {
        $path = self::static_file_path($filename);
        if (!file_exists($path)) {
            return 'none';
        }

        $size     = filesize($path);
        $max_read = 256 * 1024;
        $contents = @file_get_contents($path, false, null, 0, $size > $max_read ? $max_read : $size);
        if ($contents === false) {
            // Can't read — assume no_section so the user is prompted.
            return 'no_section';
        }

        if (strpos($contents, self::SECTION_FINGERPRINT) !== false) {
            return 'has_section';
        }
        return 'no_section';
    }
}
