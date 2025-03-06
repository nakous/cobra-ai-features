<?php

namespace CobraAI\Features\AI;



class Gemini extends AIProvider {
    /**
     * Get provider ID
     */
    public function get_id(): string {
        return 'gemini';
    }

    /**
     * Get provider name
     */
    public function get_name(): string {
        return 'Gemini';
    }

    /**
     * Get default configuration
     */
    protected function get_default_config(): array {
        return [
            'api_key' => '',
            'endpoint' => 'https://generativelanguage.googleapis.com/v1',
            'model' => 'gemini-pro',
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
            'contents' => [
                ['parts' => [['text' => $this->format_prompt($prompt)]]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
                'temperature' => $options['temperature'] ?? $this->config['temperature'],
                'topP' => $options['top_p'] ?? $this->config['top_p']
            ]
        ];

        // Add API key to URL
        $url = $this->get_endpoint_url('models/' . ($options['model'] ?? $this->config['model']) . ':generateContent') . 
               '?key=' . $this->config['api_key'];

        // Make request
        $response = $this->make_request($url, $data);

        // Format response
        return $this->format_response([
            'content' => $response['candidates'][0]['content']['parts'][0]['text'],
            'model' => $options['model'] ?? $this->config['model'],
            'usage' => [
                'total_tokens' => $this->count_tokens($prompt) + 
                                $this->count_tokens($response['candidates'][0]['content']['parts'][0]['text'])
            ]
        ]);
    }

    /**
     * Get request headers
     */
    protected function get_request_headers(): array {
        return [
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * Get supported models
     */
    public function get_supported_models(): array {
        return [
            'gemini-pro' => [
                'name' => 'Gemini Pro',
                'max_tokens' => 2048,
                'capabilities' => ['text', 'chat']
            ],
            'gemini-pro-vision' => [
                'name' => 'Gemini Pro Vision',
                'max_tokens' => 2048,
                'capabilities' => ['text', 'chat', 'vision']
            ]
        ];
    }
}