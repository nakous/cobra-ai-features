<?php

namespace CobraAI;

defined('ABSPATH') || exit;

/**
 * API Management for all features
 */
class APIManager
{
    /**
     * Singleton instance
     */
    private static ?APIManager $instance = null;

    /**
     * API credentials storage
     */
    private array $credentials = [];
    
    /**
     * Database instance for logging
     */
    private ?Database $db = null;

    /**
     * Request cache
     */
    private array $request_cache = [];

    /**
     * Default request options
     */
    private array $default_options = [
        'timeout' => 30,
        'redirection' => 5,
        'httpversion' => '1.1',
        'verify_ssl' => true,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'sslverify' => true
    ];

    /**
     * Get singleton instance
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize API manager
     */
    private function __construct()
    {
        $this->db = Database::get_instance();
        $this->verify_dependencies();
    }

    /**
     * Verify required WordPress functions and extensions
     */
    private function verify_dependencies(): void
    {
        $required_functions = [
            'wp_remote_request',
            'wp_remote_get',
            'wp_remote_post',
            'wp_remote_head',
            'wp_parse_args',
            'wp_json_encode',
            'wp_remote_retrieve_response_code',
            'wp_remote_retrieve_body',
            'wp_remote_retrieve_headers'
        ];

        $missing_functions = array_filter($required_functions, function ($func) {
            return !function_exists($func);
        });

        if (!empty($missing_functions)) {
            throw new \Exception(sprintf(
                'Required WordPress functions missing: %s',
                implode(', ', $missing_functions)
            ));
        }

        // Verify cURL extension
        if (!extension_loaded('curl')) {
            throw new \Exception('cURL extension is required but not installed');
        }
    }

    /**
     * Set API credentials
     */
    public function set_credentials(string $api_id, array $credentials): void
    {
        $required_fields = ['type', 'key'];

        // Verify required fields
        foreach ($required_fields as $field) {
            if (!isset($credentials[$field])) {
                throw new \Exception("Missing required credential field: $field");
            }
        }

        $this->credentials[$api_id] = $credentials;
    }

    /**
     * Get API credentials
     */
    public function get_credentials(string $api_id): ?array
    {
        return $this->credentials[$api_id] ?? null;
    }

    /**
     * Verify API key
     */
    public function verify_key(string $api_key, string $test_endpoint = ''): array
    {
        try {
            if (empty($api_key)) {
                throw new \Exception(__('API key cannot be empty', 'cobra-ai'));
            }

            // If no test endpoint provided, use a default verification endpoint
            if (empty($test_endpoint)) {
                $settings = get_option('cobra_ai_settings', []);
                $test_endpoint = $settings['api']['test_endpoint'] ?? '';

                if (empty($test_endpoint)) {
                    throw new \Exception(__('No test endpoint configured', 'cobra-ai'));
                }
            }

            // Make verification request
            $response = $this->request($test_endpoint, 'GET', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key
                ]
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            return [
                'success' => true,
                'message' => __('API key verified successfully', 'cobra-ai')
            ];
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'API key verification failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Make API request
     */
    public function request(string $endpoint, string $method = 'GET', array $args = [], ?string $api_id = null): mixed
    {
        try {
            // Verify endpoint
            if (empty($endpoint)) {
                throw new \Exception('Endpoint cannot be empty');
            }

            // Prepare request options
            $options = $this->prepare_request_options($endpoint, $method, $args, $api_id);

            // Make request
            $response = wp_remote_request($endpoint, $options);

            // Handle response
            return $this->handle_response($response);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'API request failed', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Make GET request
     */
    public function get(string $endpoint, array $args = [], ?string $api_id = null): mixed
    {
        return $this->request($endpoint, 'GET', $args, $api_id);
    }

    /**
     * Make POST request
     */
    public function post(string $endpoint, array $args = [], ?string $api_id = null): mixed
    {
        return $this->request($endpoint, 'POST', $args, $api_id);
    }

    /**
     * Make PUT request
     */
    public function put(string $endpoint, array $args = [], ?string $api_id = null): mixed
    {
        return $this->request($endpoint, 'PUT', $args, $api_id);
    }

    /**
     * Make DELETE request
     */
    public function delete(string $endpoint, array $args = [], ?string $api_id = null): mixed
    {
        return $this->request($endpoint, 'DELETE', $args, $api_id);
    }

    /**
     * Prepare request options
     */
    private function prepare_request_options(string $endpoint, string $method, array $args, ?string $api_id): array
    {
        $options = $this->default_options;

        // Set method
        $options['method'] = strtoupper($method);

        // Add body if needed
        if (!empty($args['body'])) {
            $options['body'] = is_array($args['body']) ? wp_json_encode($args['body']) : $args['body'];
        }

        // Add query parameters
        if (!empty($args['query'])) {
            $endpoint = add_query_arg($args['query'], $endpoint);
        }

        // Add credentials if provided
        if ($api_id && isset($this->credentials[$api_id])) {
            $options = $this->add_credentials($options, $api_id);
        }

        // Merge custom headers
        if (!empty($args['headers'])) {
            $options['headers'] = array_merge($options['headers'], $args['headers']);
        }

        // Override any default options
        foreach ($args as $key => $value) {
            if (!in_array($key, ['body', 'query', 'headers'])) {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * Add credentials to request
     */
    private function add_credentials(array $options, string $api_id): array
    {
        $credentials = $this->get_credentials($api_id);

        if (!$credentials) {
            return $options;
        }

        switch ($credentials['type']) {
            case 'bearer':
                $options['headers']['Authorization'] = 'Bearer ' . $credentials['key'];
                break;

            case 'basic':
                $options['headers']['Authorization'] = 'Basic ' . base64_encode(
                    $credentials['username'] . ':' . $credentials['password']
                );
                break;

            case 'api_key':
                if ($credentials['in'] === 'header') {
                    $options['headers'][$credentials['key']] = $credentials['value'];
                } else {
                    $options['query'][$credentials['key']] = $credentials['value'];
                }
                break;
        }

        return $options;
    }

    /**
     * Handle API response
     */
    private function handle_response($response): mixed
    {
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle error responses
        if ($status >= 400) {
            $error_data = json_decode($body, true);
            $error_message = $error_data['message'] ?? 'Unknown error occurred';

            throw new \Exception($error_message, $status);
        }

        // Parse JSON response
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Cache API response
     */
    public function cache_response(string $cache_key, $response, int $expiration = 3600): bool
    {
        return set_transient($cache_key, $response, $expiration);
    }

    /**
     * Get cached response
     */
    public function get_cached_response(string $cache_key)
    {
        return get_transient($cache_key);
    }

    /**
     * Clear API cache
     */
    public function clear_cache(string $cache_key): bool
    {
        return delete_transient($cache_key);
    }

    /**
     * Test API connection
     */
    public function test_connection(string $endpoint, ?string $api_id = null): array
    {
        try {
            $response = $this->get($endpoint, [], $api_id);

            return [
                'success' => true,
                'message' => __('Connection successful', 'cobra-ai'),
                'data' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get API rate limits status
     */
    public function get_rate_limits(?string $api_id = null): array
    {
        $limits = [
            'remaining' => null,
            'limit' => null,
            'reset' => null
        ];

        try {
            $credentials = $api_id ? $this->get_credentials($api_id) : null;
            if (!$credentials) {
                return $limits;
            }

            // Get rate limit endpoint from settings
            $settings = get_option('cobra_ai_settings', []);
            $rate_limit_endpoint = $settings['api']['rate_limit_endpoint'] ?? '';

            if (empty($rate_limit_endpoint)) {
                return $limits;
            }

            $response = $this->get($rate_limit_endpoint, [], $api_id);

            return [
                'remaining' => $response['remaining'] ?? null,
                'limit' => $response['limit'] ?? null,
                'reset' => $response['reset'] ?? null
            ];
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to get rate limits', [
                'api_id' => $api_id,
                'error' => $e->getMessage()
            ]);

            return $limits;
        }
    }
}

// Initialize API Manager
function cobra_ai_api(): APIManager
{
    return APIManager::get_instance();
}
