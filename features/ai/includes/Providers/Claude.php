<?php

namespace CobraAI\Features\AI;
 

class Claude extends AIProvider {
    /**
     * Get provider ID
     */
    public function get_id(): string {
        return 'claude';
    }

    /**
     * Get provider name
     */
    public function get_name(): string {
        return 'Claude';
    }

    /**
     * Get default configuration
     */
    protected function get_default_config(): array {
        return [
            'api_key' => '',
            'endpoint' => 'https://api.anthropic.com/v1',
            'model' => 'claude-3-opus-20240229',
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
            'prompt' => $this->format_prompt($prompt),
            'max_tokens_to_sample' => $options['max_tokens'] ?? $this->config['max_tokens'],
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'top_p' => $options['top_p'] ?? $this->config['top_p'],
            'stream' => false
        ];

        // Make request
        $response = $this->make_request(
            $this->get_endpoint_url('messages'),
            $data
        );

        // Format response
        return $this->format_response([
            'content' => $response['content'][0]['text'],
            'model' => $response['model'],
            'usage' => [
                'total_tokens' => $response['usage']['output_tokens'] + $response['usage']['input_tokens']
            ]
        ]);
    }

    /**
     * Get request headers
     */
    protected function get_request_headers(): array {
        return [
            'x-api-key' => $this->config['api_key'],
            'anthropic-version' => '2024-03-01',
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * Get supported models
     */
    public function get_supported_models(): array {
        return [
            'claude-3-opus-20240229' => [
                'name' => 'Claude 3 Opus',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat']
            ],
            'claude-3-sonnet-20240229' => [
                'name' => 'Claude 3 Sonnet',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat']
            ],
            'claude-2.1' => [
                'name' => 'Claude 2.1',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat']
            ]
        ];
    }
}