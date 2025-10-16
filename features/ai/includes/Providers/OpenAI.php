<?php

namespace CobraAI\Features\AI;

class OpenAI extends AIProvider
{
    /**
     * Get provider ID
     */
    public function get_id(): string
    {
        return 'openai';
    }

    /**
     * Get provider name
     */
    public function get_name(): string
    {
        return 'OpenAI';
    }

    /**
     * Get default configuration
     */
    protected function get_default_config(): array
    {
        return [
            'api_key' => '',
            'endpoint' => 'https://api.openai.com/v1',
            'model' => 'gpt-4',
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => .0,
            'stop_sequences' => []
        ];
    }

    /**
     * Process request with support for images and separate system/user prompts
     * 
     * @param string|array $prompt The prompt or array of prompts (system, user)
     * @param array $options Additional options including image
     * @return array Response from OpenAI
     */
    public function process_request($prompt, array $options = []): array
    {
        // Validate options
        $options = $this->validate_options($options);

        // Prepare messages array
        $messages = [];
        if (is_array($prompt)) {
            // Handle different prompt structures
            if (isset($prompt['system'])) {
                // Add system message if provided
                $messages[] = [
                    'role' => 'system',
                    'content' => $this->format_prompt($prompt['system'])
                ];
            }
            if (isset($prompt['user'])) {
                // Add user message if provided
                $messages[] = [
                    'role' => 'user',
                    'content' =>   trim($prompt['user']) 
                ];
            }

            if (isset($prompt['image'])) {
                // Add image if provided
                $messages[] = [
                    'role' => 'user',
                    'content' =>  [
                        [
                            'type' => 'image_url',
                            'image_url' => $this->prepare_image_url($prompt['image'])
                            // "type" => "input_image",
                            // "image_url" => $this->prepare_image_url($prompt['image'])
                        ],
                    ]
                ];
            }
        }


        // Prepare request data
        $data = [
            'model' => $options['model'] ?? $this->config['model'],
            'messages' => $messages,
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->config['max_tokens']),
            'temperature' => (float) ($options['temperature'] ?? $this->config['temperature']),
            'top_p' => (float) ($options['top_p'] ?? $this->config['top_p']),
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
     * Prepare image URL for API consumption
     * 
     * @param string|array $image Image as URL, base64 or file path
     * @return array The prepared image URL structure
     */
    protected function prepare_image_url($image): array|string
    {
        // If already a URL, return it directly
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return ['url' => $image];
        }

        // If it's a base64 image
        if (strpos($image, 'data:image') === 0) {
            return   $image;
        }

        // If it's a file path
        if (file_exists($image) && is_readable($image)) {
            $mime_type = mime_content_type($image);
            $data = base64_encode(file_get_contents($image));
            return "data:{$mime_type};base64,{$data}";
        }

        // If it's already base64 encoded without the header
        if (base64_encode(base64_decode($image, true)) === $image) {
            // Try to determine mime type or default to png
            return   "data:image/png;base64,{$image}";
        }

        // If we get here, we can't process the image
        throw new \InvalidArgumentException('Invalid image format. Must be URL, base64 data, or file path.');
    }

    /**
     * Get supported models
     */
    public function get_supported_models(): array
    {
        return [
            'gpt-5-2025-08-07' => [
                'name' => 'GPT-5',
                'max_tokens' => 16384,
                'capabilities' => ['text', 'chat', 'vision', 'reasoning']
            ],
            'gpt-5-mini-2025-08-07' => [
                'name' => 'GPT-5 Mini',
                'max_tokens' => 8192,
                'capabilities' => ['text', 'chat', 'reasoning']
            ],
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
            'gpt-4-vision-preview' => [
                'name' => 'GPT-4 Vision',
                'max_tokens' => 4096,
                'capabilities' => ['text', 'chat', 'vision']
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
    public function count_tokens(string $text): int
    {
        // Implement GPT tokenization algorithm
        // This is a simplified version
        return (int)(strlen($text) / 4);
    }
}
