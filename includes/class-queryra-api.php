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

        $response = $this->request('GET', '/api/v1/status', array(
            'key' => $this->api_key
        ));

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
     * @return mixed Response or WP_Error
     */
    private function request($method, $endpoint, $query_params = array(), $body = null) {
        // Build URL
        $url = rtrim($this->api_url, '/') . $endpoint;

        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        // Request args
        $args = array(
            'method' => $method,
            'timeout' => 30,
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
            $message = isset($data['detail']) ? $data['detail'] : 'API request failed';
            return new WP_Error('api_error', $message, array('status' => $code));
        }

        return $data;
    }

    /**
     * Get API stats
     *
     * @return array Response
     */
    public function get_stats() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key is required');
        }

        $response = $this->request('GET', '/api/v1/records/stats', array(
            'key' => $this->api_key
        ));

        return $response;
    }
}
