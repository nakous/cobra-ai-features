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
        'text'             => true,
        'images'           => false,
        'chat'             => false,
        'functions'        => false,
        'stream'           => false,
        'image_generation' => false,
        'audio'            => false,
        'tts'              => false,
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
     * Process text/chat request
     *
     * @param string|array $prompt The user prompt
     * @param array        $options Request options
     * @return array Response data
     */
    abstract public function process_request(string|array $prompt, array $options = []): array;

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
     * Get supported request types for this provider
     */
    public function get_request_types(): array
    {
        $types = ['text'];
        if ($this->has_capability('image_generation')) {
            $types[] = 'image';
        }
        if ($this->has_capability('audio')) {
            $types[] = 'audio';
        }
        if ($this->has_capability('tts')) {
            $types[] = 'tts';
        }
        return $types;
    }

    /**
     * Generate image — override in provider subclass if supported
     *
     * @param string $prompt Image description
     * @param array  $options Generation options
     * @return array Response with 'content' (image URL) and 'meta'
     * @throws \BadMethodCallException
     */
    public function generate_image(string $prompt, array $options = []): array
    {
        throw new \BadMethodCallException(
            sprintf(__('Provider %s does not support image generation', 'cobra-ai'), $this->get_name())
        );
    }

    /**
     * Transcribe audio — override in provider subclass if supported
     *
     * @param string|array $audio_input File path, URL, or $_FILES entry
     * @param array        $options Transcription options
     * @return array Response with 'content' (transcript text) and 'meta'
     * @throws \BadMethodCallException
     */
    public function transcribe_audio(string|array $audio_input, array $options = []): array
    {
        throw new \BadMethodCallException(
            sprintf(__('Provider %s does not support audio transcription', 'cobra-ai'), $this->get_name())
        );
    }

    /**
     * Synthesize speech (TTS) — override in provider subclass if supported
     *
     * @param string $text  Text to convert to speech
     * @param array  $options TTS options
     * @return array Response with 'content' (audio URL) and 'meta'
     * @throws \BadMethodCallException
     */
    public function synthesize_speech(string $text, array $options = []): array
    {
        throw new \BadMethodCallException(
            sprintf(__('Provider %s does not support text-to-speech', 'cobra-ai'), $this->get_name())
        );
    }

    /**
     * Get image generation models — override in subclass
     */
    public function get_image_models(): array
    {
        return [];
    }

    /**
     * Get audio transcription models — override in subclass
     */
    public function get_audio_models(): array
    {
        return [];
    }

    /**
     * Get TTS models — override in subclass
     */
    public function get_tts_models(): array
    {
        return [];
    }

    /**
     * Make a multipart/form-data request (required for audio file uploads)
     *
     * @param string $url        API endpoint URL
     * @param array  $fields     Non-file form fields
     * @param string $file_path  Absolute path to the local file to upload
     * @param string $file_field Form field name for the file (default: 'file')
     * @return array Decoded JSON response
     * @throws \Exception on cURL or API error
     */
    protected function make_multipart_request(
        string $url,
        array  $fields,
        string $file_path,
        string $file_field = 'file'
    ): array {
        if (!function_exists('curl_init')) {
            throw new \Exception(__('cURL is required for audio file uploads', 'cobra-ai'));
        }

        $headers = $this->get_request_headers();
        unset($headers['Content-Type']); // cURL sets this automatically for multipart

        $curl_headers = [];
        foreach ($headers as $key => $value) {
            $curl_headers[] = "{$key}: {$value}";
        }

        $post_fields               = $fields;
        $post_fields[$file_field]  = new \CURLFile($file_path);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_HTTPHEADER     => $curl_headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        $data = json_decode($body, true);

        if ($status !== 200) {
            $error_message = $data['error']['message'] ?? __('Unknown error occurred', 'cobra-ai');
            cobra_ai_db()->log('error', 'Multipart API request failed', [
                'provider' => $this->get_id(),
                'status'   => $status,
                'error'    => $error_message,
            ]);
            throw new \Exception($error_message);
        }

        return $data;
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
