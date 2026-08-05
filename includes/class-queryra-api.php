<?php
/**
 * Queryra API Client
 *
 * Handles communication with Queryra API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Queryra_API {

    /**
     * API Key
     */
    private $api_key;

    /**
     * API URL
     */
    private $api_url;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('queryra_api_key', '');
        $this->api_url = get_option('queryra_api_url', 'https://queryra.com');
    }

    /**
     * Test API connection
     *
     * @return array Response with success status and message
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API key is required'
            );
        }

        $response = $this->request('GET', '/api/v1/status', $this->build_status_params());

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        return array(
            'success' => true,
            'message' => 'Connection successful',
            'data' => $response
        );
    }

    /**
     * Validate an arbitrary API key (without saving it).
     *
     * Reuses the existing test_connection() round-trip so the validation
     * path is identical to what the plugin uses internally. Stateless:
     * the current $this->api_key is preserved across the call.
     *
     * @param string $key Candidate API key to verify.
     * @return true|WP_Error true on success, WP_Error with details on failure.
     */
    public function validate_key($key) {
        if (class_exists('Queryra_Search')) {
            Queryra_Search::log('validate_key() ENTER — key_len=' . strlen((string)$key));
        }

        if (empty($key) || !is_string($key)) {
            if (class_exists('Queryra_Search')) {
                Queryra_Search::log('validate_key — empty/invalid key argument');
            }
            return new WP_Error('queryra_empty_key', __('API key is required.', 'queryra-ai-search'));
        }

        $previous_key  = $this->api_key;
        $this->api_key = trim($key);

        if (class_exists('Queryra_Search')) {
            Queryra_Search::log('validate_key — calling test_connection() against ' . $this->api_url);
        }
        $result = $this->test_connection();
        if (class_exists('Queryra_Search')) {
            Queryra_Search::log('validate_key — test_connection returned: success=' . (!empty($result['success']) ? 'true' : 'false') . ' msg="' . (isset($result['message']) ? $result['message'] : '') . '"');
        }

        // Restore previous state — never mutate due to a validation call.
        $this->api_key = $previous_key;

        if (!empty($result['success'])) {
            return true;
        }

        return new WP_Error(
            'queryra_invalid_key',
            isset($result['message']) ? $result['message'] : __('API key validation failed.', 'queryra-ai-search')
        );
    }

    /**
     * Sync records (bulk)
     *
     * @param array $records Array of records to sync
     * @return array Response
     */
    public function sync_records($records) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key is required');
        }

        if (empty($records)) {
            return new WP_Error('no_records', 'No records to sync');
        }

        $response = $this->request('POST', '/api/v1/records/bulk', array(
            'key' => $this->api_key
        ), array(
            'products' => $records
        ));

        return $response;
    }

    /**
     * Delete record
     *
     * @param string $record_id Record ID to delete
     * @return array Response
     */
    public function delete_record($record_id) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key is required');
        }

        $response = $this->request('DELETE', '/api/v1/records/' . $record_id, array(
            'key' => $this->api_key
        ));

        return $response;
    }

    /**
     * Make API request
     *
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param string $endpoint API endpoint
     * @param array $query_params Query parameters
     * @param array $body Request body (for POST)
     * @param int $timeout Request timeout in seconds. Default 30 suits
     *                     admin-initiated calls (bulk sync); latency-sensitive
     *                     paths (frontend search) must pass a short value —
     *                     a hung API call inside pre_get_posts holds a PHP
     *                     worker for the full duration.
     * @return mixed Response or WP_Error
     */
    private function request($method, $endpoint, $query_params = array(), $body = null, $timeout = 30) {
        // Build URL
        $url = rtrim($this->api_url, '/') . $endpoint;

        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        // Request args
        $args = array(
            'method' => $method,
            'timeout' => $timeout,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Queryra-WordPress/' . QUERYRA_VERSION
            )
        );

        // Add body for POST requests
        if ($method === 'POST' && !empty($body)) {
            $args['body'] = json_encode($body);
        }

        // Make request
        $response = wp_remote_request($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }

        // Get response code
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Parse JSON
        $data = json_decode($body, true);

        // Check for API errors
        if ($code >= 400) {
            // Try different error formats
            $message = 'API request failed';

            if (isset($data['detail'])) {
                $message = is_array($data['detail']) ? json_encode($data['detail']) : $data['detail'];
            } elseif (isset($data['errors']['api_error'][0]['message'])) {
                // FREE_PLAN_WINDOW_CLOSED format
                $message = $data['errors']['api_error'][0]['message'];
            } elseif (isset($data['message'])) {
                $message = $data['message'];
            }

            return new WP_Error('api_error', $message, array('status' => $code, 'data' => $data));
        }

        return $data;
    }

    /**
     * Get API stats (records count, limits, plan info)
     *
     * @return array Response
     */
    public function get_stats($timeout = 30) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key is required');
        }

        $response = $this->request('GET', '/api/v1/records/stats', array(
            'key' => $this->api_key
        ), null, $timeout);

        return $response;
    }

    /**
     * Get API status (search window availability for FREE plan)
     *
     * @return array Response
     */
    public function get_status($timeout = 30) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key is required');
        }

        $response = $this->request('GET', '/api/v1/status', $this->build_status_params(), null, $timeout);

        return $response;
    }

    /**
     * Build query parameters for /api/v1/status endpoint.
     *
     * Always sends: key, instance_id, plugin_type.
     * Additionally sends site_url for partner referrals (key contains :ref=CODE).
     *
     * @return array Query parameters
     */
    private function build_status_params() {
        $params = array(
            'key'         => $this->api_key,
            'instance_id' => get_option('queryra_instance_id', ''),
            'plugin_type' => 'wp',
        );

        // Send site_url only for partner referrals (key format: ABC123:ref=CODE)
        if (strpos($this->api_key, ':ref=') !== false) {
            $params['site_url'] = get_site_url();
        }

        // NOTE: the setup survey answer is deliberately NOT sent here. Survey
        // data is not stored on the site at all — it goes out once as the
        // `site_profile` analytics event (which carries instance_id, so the
        // backend attaches it to the right install) and that is the end of it.
        // Nothing to re-send on every status ping.

        return $params;
    }

    /**
     * Search records using Queryra AI
     *
     * @param string $query Search query
     * @param int $limit Maximum number of results (default: 20)
     * @return array Response with results
     */
    public function search($query, $limit = 20, $filters = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key is required');
        }

        if (empty($query)) {
            return new WP_Error('no_query', 'Search query is required');
        }

        $params = array(
            'key' => $this->api_key,
            'q' => $query,
            'limit' => $limit
        );

        // Optional scope filters, forwarded verbatim as filter_<dimension>=<value>
        // query parameters and applied by the API BEFORE retrieval.
        //
        // Deliberately a pass-through: the plugin sanitises the SHAPE and
        // nothing else. Which dimensions exist, and what they mean, is the
        // API's business — it owns the allow-list. Translating names here
        // (say, prefixing taxonomies) would put a copy of that vocabulary in
        // the plugin and quietly mangle any dimension the API adds later.
        // Site owners get the exact parameter to use from the filters
        // reference in the plugin settings, so they never type one from memory.
        //
        // We never build a query clause — only flat pairs — so there is
        // nothing to inject. sanitize_key() re-guards the shape in case a
        // caller (e.g. an integration) passes filters directly.
        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $dimension => $value) {
                $dimension = sanitize_key((string) $dimension);
                if ($dimension !== '' && is_scalar($value)) {
                    $params['filter_' . $dimension] = (string) $value;
                }
            }
        }

        // 5s timeout — search runs on the visitor-facing request path
        // (pre_get_posts, B2BKing live search). With the default 30s, a
        // slow API would hang every uncached search page and exhaust
        // PHP workers under modest traffic; 5s bounds the worst case
        // before the WordPress-search fallback kicks in.
        $response = $this->request('GET', '/api/v1/search', $params, null, 5);

        return $response;
    }

    /**
     * Get search analytics/stats
     *
     * @param string $period Time period: "7", "30", or "all"
     * @return array Response with search stats
     */
    public function get_search_stats($period = '30') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key is required');
        }

        $response = $this->request('GET', '/api/v1/records/search-stats', array(
            'key' => $this->api_key,
            'period' => $period
        ));

        return $response;
    }
}
