<?php

namespace CobraAI\Features\AI;

use function CobraAI\{
    cobra_ai_db
};

abstract class AIProvider
{
    /**
     * Provider configuration
     */
    protected $config;

    /**
     * Default configuration
     */
    protected $defaults = [
        'max_tokens' => 2048,
        'temperature' => 0.7,
        'top_p' => 1,
        'frequency_penalty' => 0,
        'presence_penalty' => 0,
        'stop_sequences' => []
    ];

    /**
     * Provider capabilities
     */
    protected $capabilities = [
        'text' => true,
        'images' => false,
        'chat' => false,
        'functions' => false,
        'stream' => false
    ];

    /**
     * Request retry settings
     */
    protected $retry_attempts = 3;
    protected $retry_delay = 1000; // milliseconds

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = wp_parse_args($config, $this->get_default_config());
        $this->validate_config();
    }

    /**
     * Get provider ID
     */
    abstract public function get_id(): string;

    /**
     * Get provider name
     */
    abstract public function get_name(): string;

    /**
     * Process request
     * 
     * @param string $prompt The user prompt
     * @param array $options Request options
     * @return array Response data
     */
    abstract public function process_request(string $prompt, array $options = []): array;

    /**
     * Get default configuration
     */
    abstract protected function get_default_config(): array;

    /**
     * Validate provider configuration
     * 
     * @throws \Exception if configuration is invalid
     */
    protected function validate_config(): void
    {
        if (empty($this->config['api_key'])) {
            throw new \Exception(__('API key is required', 'cobra-ai'));
        }
    }

    /**
     * Get provider capabilities
     */
    public function get_capabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Check if provider has capability
     */
    public function has_capability(string $capability): bool
    {
        return !empty($this->capabilities[$capability]);
    }

    /**
     * Validate request options
     */
    public function validate_options(array $options): array
    {
        // Validate max tokens
        if (isset($options['max_tokens'])) {
            $options['max_tokens'] = min(
                max((int)$options['max_tokens'], 1),
                $this->config['max_tokens']
            );
        }

        // Validate temperature
        if (isset($options['temperature'])) {
            $options['temperature'] = min(
                max((float)$options['temperature'], 0),
                1
            );
        }

        // Validate top_p
        if (isset($options['top_p'])) {
            $options['top_p'] = min(
                max((float)$options['top_p'], 0),
                1
            );
        }

        // Validate penalties
        foreach (['frequency_penalty', 'presence_penalty'] as $penalty) {
            if (isset($options[$penalty])) {
                $options[$penalty] = min(
                    max((float)$options[$penalty], -2),
                    2
                );
            }
        }

        return $options;
    }

    /**
     * Make HTTP request
     */
    protected function make_request(string $url, array $data = [], string $method = 'POST'): array
    {
        $args = [
            'method' => $method,
            'headers' => $this->get_request_headers(),
            'timeout' => 30,
        ];

        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        // Make request with retries
        $attempt = 1;
        while ($attempt <= $this->retry_attempts) {
            $response = wp_remote_request($url, $args);

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $status = wp_remote_retrieve_response_code($response);

                if ($status === 200) {
                    return json_decode($body, true);
                }

                // Check if error is retryable
                if (!$this->is_retryable_error($status)) {
                    $this->handle_error_response($response);
                }
            }

            if ($attempt < $this->retry_attempts) {
                usleep($this->retry_delay * 1000 * $attempt);
            }

            $attempt++;
        }

        throw new \Exception(__('Request failed after multiple retries', 'cobra-ai'));
    }

    /**
     * Get request headers
     */
    protected function get_request_headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    /**
     * Check if error is retryable
     */
    protected function is_retryable_error(int $status_code): bool
    {
        return in_array($status_code, [408, 429, 500, 502, 503, 504]);
    }

    /**
     * Handle error response
     */
    protected function handle_error_response($response): void
    {
        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode($body, true);

        $error_message = isset($data['error']['message'])
            ? $data['error']['message']
            : __('Unknown error occurred', 'cobra-ai');

        cobra_ai_db()->log('error', 'API request failed', [
            'provider' => $this->get_id(),
            'status' => $status,
            'error' => $error_message
        ]);

        throw new \Exception($error_message);
    }

    /**
     * Count tokens in text
     */
    public function count_tokens(string $text): int
    {
        // Basic token counting - override in provider classes for accurate counting
        return (int)(str_word_count($text) * 1.3);
    }

    /**
     * Format prompt
     */
    protected function format_prompt($prompt): string|array
    {
        if (is_array($prompt)) {
            return $prompt;
        }
        return trim($prompt);
    }

    /**
     * Format response
     */
    protected function format_response(array $response): array
    {
        return [
            'content' => $response['content'] ?? '',
            'tokens' => $response['usage']['total_tokens'] ?? 0,
            'meta' => [
                'model' => $response['model'] ?? '',
                'provider' => $this->get_id(),
                'created' => $response['created'] ?? time()
            ]
        ];
    }

    /**
     * Get rate limits
     */
    public function get_rate_limits(): array
    {
        return [
            'requests_per_minute' => 60,
            'tokens_per_minute' => 40000,
            'concurrent_requests' => 5
        ];
    }

    /**
     * Get supported models
     */
    public function get_supported_models(): array
    {
        return [];
    }

    /**
     * Get model information
     */
    public function get_model_info(string $model): ?array
    {
        return null;
    }

    /**
     * Check if model is supported
     */
    public function is_model_supported(string $model): bool
    {
        return in_array($model, array_keys($this->get_supported_models()));
    }

    /**
     * Get provider endpoint URL
     */
    protected function get_endpoint_url(string $endpoint): string
    {
        $base_url = rtrim($this->config['endpoint'] ?? '', '/');
        return $base_url . '/' . ltrim($endpoint, '/');
    }

    /**
     * Get config value
     */
    protected function get_config(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set config value
     */
    protected function set_config(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Check if streaming is supported and enabled
     */
    protected function can_stream(): bool
    {
        return $this->has_capability('stream') &&
            !empty($this->config['enable_streaming']);
    }
}
