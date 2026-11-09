<?php

namespace CobraAI\Features\AI;

class OpenAI extends AIProvider
{
    /**
     * OpenAI supports text, vision, image generation, audio transcription and TTS
     */
    protected $capabilities = [
        'text'             => true,
        'images'           => true,
        'chat'             => true,
        'functions'        => true,
        'stream'           => false,
        'image_generation' => true,
        'audio'            => true,
        'tts'              => true,
    ];

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
            // Chat
            'api_key'           => '',
            'endpoint'          => 'https://api.openai.com/v1',
            'model'             => 'gpt-5.4-mini',
            'max_tokens'        => 2048,
            'temperature'       => 0.7,
            'top_p'             => 1,
            'frequency_penalty' => 0,
            'presence_penalty'  => 0,
            'stop_sequences'    => [],
            // Image generation
            'image_model'           => 'dall-e-3',
            'image_size'            => '1024x1024',
            'image_quality'         => 'standard',
            'image_style'           => 'vivid',
            'image_response_format' => 'url',
            // Audio transcription
            'audio_model'           => 'gpt-4o-transcribe',
            'audio_language'        => '',
            'audio_response_format' => 'json',
            // Text-to-speech
            'tts_model'  => 'gpt-4o-mini-tts',
            'tts_voice'  => 'alloy',
            'tts_speed'  => 1.0,
            'tts_format' => 'mp3',
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
        // log $data for debugging php error_log(print_r($data, true));
        // error_log(print_r($data, true));
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
     * Get supported chat/text models
     */
    public function get_supported_models(): array
    {
        return [
            'gpt-5.4' => [
                'name'         => 'GPT-5.4',
                'max_tokens'   => 128000,
                'context'      => 1050000,
                'capabilities' => ['text', 'chat', 'vision', 'reasoning'],
            ],
            'gpt-5.4-mini' => [
                'name'         => 'GPT-5.4 Mini',
                'max_tokens'   => 128000,
                'context'      => 400000,
                'capabilities' => ['text', 'chat', 'vision', 'reasoning'],
            ],
            'gpt-5.4-nano' => [
                'name'         => 'GPT-5.4 Nano',
                'max_tokens'   => 128000,
                'context'      => 400000,
                'capabilities' => ['text', 'chat', 'vision'],
            ],
            'gpt-4o' => [
                'name'         => 'GPT-4o',
                'max_tokens'   => 16384,
                'context'      => 128000,
                'capabilities' => ['text', 'chat', 'vision'],
            ],
            'gpt-4o-mini' => [
                'name'         => 'GPT-4o Mini',
                'max_tokens'   => 16384,
                'context'      => 128000,
                'capabilities' => ['text', 'chat', 'vision'],
            ],
            'gpt-4-turbo' => [
                'name'         => 'GPT-4 Turbo',
                'max_tokens'   => 4096,
                'context'      => 128000,
                'capabilities' => ['text', 'chat', 'vision'],
            ],
            'gpt-4' => [
                'name'         => 'GPT-4',
                'max_tokens'   => 8192,
                'context'      => 8192,
                'capabilities' => ['text', 'chat'],
            ],
            'gpt-3.5-turbo' => [
                'name'         => 'GPT-3.5 Turbo',
                'max_tokens'   => 4096,
                'context'      => 16384,
                'capabilities' => ['text', 'chat'],
            ],
        ];
    }

    /**
     * Get image generation models
     */
    public function get_image_models(): array
    {
        return [
            'gpt-image-2' => [
                'name'  => 'GPT Image 2',
                'sizes' => ['1024x1024', '1536x1024', '1024x1536', 'auto'],
            ],
            'dall-e-3' => [
                'name'    => 'DALL-E 3',
                'sizes'   => ['1024x1024', '1792x1024', '1024x1792'],
                'quality' => ['standard', 'hd'],
                'style'   => ['vivid', 'natural'],
            ],
            'dall-e-2' => [
                'name'  => 'DALL-E 2',
                'sizes' => ['256x256', '512x512', '1024x1024'],
            ],
        ];
    }

    /**
     * Get audio transcription models
     */
    public function get_audio_models(): array
    {
        return [
            'gpt-4o-transcribe' => [
                'name' => 'GPT-4o Transcribe',
            ],
            'gpt-4o-mini-transcribe' => [
                'name' => 'GPT-4o Mini Transcribe',
            ],
            'whisper-1' => [
                'name' => 'Whisper 1 (legacy)',
            ],
        ];
    }

    /**
     * Get TTS models
     */
    public function get_tts_models(): array
    {
        return [
            'gpt-4o-mini-tts' => [
                'name'   => 'GPT-4o Mini TTS',
                'voices' => ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'],
            ],
            'tts-1' => [
                'name'   => 'TTS-1',
                'voices' => ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'],
            ],
            'tts-1-hd' => [
                'name'   => 'TTS-1 HD',
                'voices' => ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'],
            ],
        ];
    }

    /**
     * Generate an image via DALL-E or GPT Image 2.
     * The result is downloaded and saved to the WordPress media library.
     *
     * @param string $prompt Image description
     * @param array  $options Override config values (image_model, image_size, etc.)
     * @return array Response with 'content' (media URL), 'tokens', 'meta'
     */
    public function generate_image(string $prompt, array $options = []): array
    {
        $model           = $options['image_model']           ?? $this->config['image_model']           ?? 'dall-e-3';
        $size            = $options['image_size']            ?? $this->config['image_size']            ?? '1024x1024';
        $response_format = $options['image_response_format'] ?? $this->config['image_response_format'] ?? 'url';

        $data = [
            'model'           => $model,
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => $size,
            'response_format' => $response_format,
        ];

        // dall-e-3 specific options
        if ($model === 'dall-e-3') {
            $data['quality'] = $options['image_quality'] ?? $this->config['image_quality'] ?? 'standard';
            $data['style']   = $options['image_style']   ?? $this->config['image_style']   ?? 'vivid';
        }

        $response   = $this->make_request($this->get_endpoint_url('images/generations'), $data);
        $image_data = $response['data'][0];
        $media_url  = $this->save_image_to_media_library($image_data, $prompt, $response_format);

        return [
            'content' => $media_url,
            'tokens'  => 0,
            'meta'    => [
                'model'    => $model,
                'provider' => $this->get_id(),
                'created'  => $response['created'] ?? time(),
                'size'     => $size,
                'type'     => 'image',
            ],
        ];
    }

    /**
     * Save an image returned by OpenAI to the WordPress media library.
     *
     * @param array  $image_data      Single item from response['data']
     * @param string $prompt          Used as attachment title (truncated)
     * @param string $response_format 'url' or 'b64_json'
     * @return string Permanent WordPress attachment URL
     */
    private function save_image_to_media_library(array $image_data, string $prompt, string $response_format): string
    {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $title = sanitize_text_field(mb_substr($prompt, 0, 100));

        if ($response_format === 'url' && !empty($image_data['url'])) {
            $attachment_id = media_sideload_image($image_data['url'], 0, $title, 'id');
            if (is_wp_error($attachment_id)) {
                throw new \Exception($attachment_id->get_error_message());
            }
            return (string) wp_get_attachment_url($attachment_id);
        }

        if (!empty($image_data['b64_json'])) {
            $binary   = base64_decode($image_data['b64_json'], true);
            $filename = 'ai-image-' . time() . '.png';
            $upload   = wp_upload_bits($filename, null, $binary);
            if (!empty($upload['error'])) {
                throw new \Exception($upload['error']);
            }
            $attachment = [
                'post_title'     => $title,
                'post_mime_type' => 'image/png',
                'post_status'    => 'inherit',
                'post_content'   => '',
            ];
            $attachment_id = wp_insert_attachment($attachment, $upload['file']);
            $metadata      = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $metadata);
            return (string) wp_get_attachment_url($attachment_id);
        }

        throw new \Exception(__('No image data received from OpenAI API', 'cobra-ai'));
    }

    /**
     * Transcribe audio using OpenAI Whisper / GPT-4o Transcribe.
     * Accepts three input formats:
     *  - array  : $_FILES entry (e.g. ['tmp_name' => '...', 'name' => '...'])
     *  - string : absolute local file path
     *  - string : remote URL (downloaded to a temp file first)
     *
     * @param string|array $audio_input Audio source
     * @param array        $options     Override config values (audio_model, audio_language, etc.)
     * @return array Response with 'content' (transcript), 'tokens', 'meta'
     */
    public function transcribe_audio(string|array $audio_input, array $options = []): array
    {
        $temp_file = null;
        $cleanup   = false;

        if (is_array($audio_input) && isset($audio_input['tmp_name'])) {
            // $_FILES upload
            if (!is_uploaded_file($audio_input['tmp_name'])) {
                throw new \Exception(__('Invalid uploaded file', 'cobra-ai'));
            }
            $temp_file = $audio_input['tmp_name'];
        } elseif (is_string($audio_input) && filter_var($audio_input, FILTER_VALIDATE_URL)) {
            // Remote URL — download to a temporary file
            $temp_file = $this->download_to_temp($audio_input);
            $cleanup   = true;
        } elseif (is_string($audio_input) && file_exists($audio_input) && is_readable($audio_input)) {
            // Local file path
            $temp_file = $audio_input;
        } else {
            throw new \InvalidArgumentException(
                __('Invalid audio input. Provide a $_FILES array, an absolute file path, or a URL.', 'cobra-ai')
            );
        }

        $model = $options['audio_model']           ?? $this->config['audio_model']           ?? 'gpt-4o-transcribe';
        $lang  = $options['audio_language']        ?? $this->config['audio_language']        ?? '';
        $fmt   = $options['audio_response_format'] ?? $this->config['audio_response_format'] ?? 'json';

        $fields = [
            'model'           => $model,
            'response_format' => $fmt,
        ];
        if (!empty($lang)) {
            $fields['language'] = $lang;
        }

        try {
            $response = $this->make_multipart_request(
                $this->get_endpoint_url('audio/transcriptions'),
                $fields,
                $temp_file
            );
        } finally {
            if ($cleanup && $temp_file && file_exists($temp_file)) {
                @unlink($temp_file);
            }
        }

        $text = $response['text'] ?? '';

        return [
            'content' => $text,
            'tokens'  => $this->count_tokens($text),
            'meta'    => [
                'model'    => $model,
                'provider' => $this->get_id(),
                'created'  => time(),
                'type'     => 'audio',
            ],
        ];
    }

    /**
     * Download a remote URL to a system temp file and return its path.
     */
    private function download_to_temp(string $url): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'cobra_ai_audio_');
        $resp = wp_remote_get($url, ['timeout' => 60]);
        if (is_wp_error($resp)) {
            throw new \Exception($resp->get_error_message());
        }
        file_put_contents($temp, wp_remote_retrieve_body($resp));
        return $temp;
    }

    /**
     * Synthesize speech from text using OpenAI TTS.
     * The audio file is saved to /wp-content/uploads/cobra-ai/tts/ and its URL is returned.
     *
     * @param string $text    Text to convert to speech (max ~2000 tokens)
     * @param array  $options Override config values (tts_model, tts_voice, tts_speed, tts_format)
     * @return array Response with 'content' (audio URL), 'tokens', 'meta'
     */
    public function synthesize_speech(string $text, array $options = []): array
    {
        $model  = $options['tts_model']  ?? $this->config['tts_model']  ?? 'gpt-4o-mini-tts';
        $voice  = $options['tts_voice']  ?? $this->config['tts_voice']  ?? 'alloy';
        $speed  = (float) ($options['tts_speed']  ?? $this->config['tts_speed']  ?? 1.0);
        $format = $options['tts_format'] ?? $this->config['tts_format'] ?? 'mp3';

        $data = [
            'model'           => $model,
            'input'           => $text,
            'voice'           => $voice,
            'speed'           => $speed,
            'response_format' => $format,
        ];

        // TTS returns binary audio, not JSON — bypass make_request()
        $response = wp_remote_post($this->get_endpoint_url('audio/speech'), [
            'method'  => 'POST',
            'headers' => $this->get_request_headers(),
            'body'    => json_encode($data),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            $err = json_decode(wp_remote_retrieve_body($response), true);
            throw new \Exception($err['error']['message'] ?? __('TTS request failed', 'cobra-ai'));
        }

        $audio_binary = wp_remote_retrieve_body($response);
        $audio_url    = $this->save_audio_to_uploads($audio_binary, $format);

        return [
            'content' => $audio_url,
            'tokens'  => $this->count_tokens($text),
            'meta'    => [
                'model'    => $model,
                'provider' => $this->get_id(),
                'created'  => time(),
                'voice'    => $voice,
                'format'   => $format,
                'type'     => 'tts',
            ],
        ];
    }

    /**
     * Save binary audio to /wp-content/uploads/cobra-ai/tts/ and return its URL.
     */
    private function save_audio_to_uploads(string $binary, string $format): string
    {
        $upload_dir = wp_upload_dir();
        $tts_dir    = $upload_dir['basedir'] . '/cobra-ai/tts';

        if (!wp_mkdir_p($tts_dir)) {
            throw new \Exception(__('Could not create TTS upload directory', 'cobra-ai'));
        }

        $filename  = 'tts-' . time() . '-' . wp_generate_password(8, false) . '.' . $format;
        $file_path = $tts_dir . '/' . $filename;

        if (file_put_contents($file_path, $binary) === false) {
            throw new \Exception(__('Could not save TTS audio file', 'cobra-ai'));
        }

        return $upload_dir['baseurl'] . '/cobra-ai/tts/' . $filename;
    }

    /**
     * Count tokens (simplified approximation for GPT models)
     */
    public function count_tokens(string $text): int
    {
        return (int) (strlen($text) / 4);
    }
}
