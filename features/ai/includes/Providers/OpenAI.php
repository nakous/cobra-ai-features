<?php

namespace CobraAI\Features\AI;



class OpenAI extends AIProvider {
    /**
     * Get provider ID
     */
    public function get_id(): string {
        return 'openai';
    }

    /**
     * Get provider name
     */
    public function get_name(): string {
        return 'OpenAI';
    }

    /**
     * Get default configuration
     */
    protected function get_default_config(): array {
        return [
            'api_key' => '',
            'endpoint' => 'https://api.openai.com/v1',
            'model' => 'gpt-4',
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            'stop_sequences' => []
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
            'top_p' => $options['top_p'] ?? $this->config['top_p'],
            'frequency_penalty' => $options['frequency_penalty'] ?? $this->config['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty'] ?? $this->config['presence_penalty'],
            'stream' => false
        ];

        if (!empty($options['stop_sequences'])) {
            $data['stop'] = $options['stop_sequences'];
        }

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
     * Get supported models
     */
    public function get_supported_models(): array {
        return [
            'gpt-4' => [
                'name' => 'GPT-4',
                'max_tokens' => 8192,
                'capabilities' => ['text', 'chat']
            ],
            'gpt-4-turbo' => [
                'name' => 'GPT-4 Turbo',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat']
            ],
            'gpt-3.5-turbo' => [
                'name' => 'GPT-3.5 Turbo',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat']
            ]
        ];
    }

    /**
     * Count tokens in text
     */
    public function count_tokens(string $text): int {
        // Implement GPT tokenization algorithm
        // This is a simplified version
        return (int)(strlen($text) / 4);
    }
}