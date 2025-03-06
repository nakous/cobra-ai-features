<?php

namespace CobraAI\Features\AI;



use function CobraAI\{
    cobra_ai_db
};

class Perplexity extends AIProvider {
    /**
     * Get provider ID
     */
    public function get_id(): string {
        return 'perplexity';
    }

    /**
     * Get provider name
     */
    public function get_name(): string {
        return 'Perplexity';
    }

    /**
     * Get default configuration
     */
    protected function get_default_config(): array {
        return [
            'api_key' => '',
            'endpoint' => 'https://api.perplexity.ai',
            'model' => 'pplx-70b-online',
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'top_p' => 1
        ];
    }

    /**
     * Process request
     */
    public function process_request(string $prompt, array $options = []): array {
        // Validate options
        $options = $this->validate_options($options);

        // Prepare request data
        $data = [
            'model' => $options['model'] ?? $this->config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $this->format_prompt($prompt)]
            ],
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'top_p' => $options['top_p'] ?? $this->config['top_p']
        ];

        // Make request
        $response = $this->make_request(
            $this->get_endpoint_url('chat/completions'),
            $data
        );

        // Format response
        return $this->format_response([
            'content' => $response['choices'][0]['message']['content'],
            'model' => $response['model'],
            'usage' => $response['usage']
        ]);
    }

    /**
     * Get request headers
     */
    protected function get_request_headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * Get supported models
     */
    public function get_supported_models(): array {
        return [
            'pplx-70b-online' => [
                'name' => 'PPLX 70B Online',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat']
            ],
            'pplx-7b-online' => [
                'name' => 'PPLX 7B Online',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat']
            ],
            'pplx-70b-chat' => [
                'name' => 'PPLX 70B Chat',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat']
            ]
        ];
    }

    /**
     * Handle error response
     */
    protected function handle_error_response($response): void {
        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode($body, true);

        $error_message = isset($data['error']) 
            ? $data['error'] 
            : __('Unknown error occurred', 'cobra-ai');

        cobra_ai_db()->log('error', 'Perplexity API request failed', [
            'status' => $status,
            'error' => $error_message
        ]);

        throw new \Exception($error_message);
    }
}