<?php

namespace CobraAI\Features\AI;

use CobraAI\FeatureBase;


class Feature extends FeatureBase {
    /**
     * Feature properties
     */
    protected string $feature_id = 'ai';
    protected string $name = 'AI Integration';
    protected string $description = 'Integrate multiple AI providers with tracking and management';
    protected string $version = '1.1.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = true;
    protected bool $has_admin = true;

    /**
     * Feature components
     */
    private $admin;
    private $manager;
    public $tracking;

    // constracteur
    public function __construct() {
        parent::__construct();
        global $wpdb;
        $this->tables = [
            'trackings' => [
                'name' =>  $wpdb->prefix .'cobra_ai_trackings',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'user_id' => 'bigint(20) NOT NULL',
                    'prompt' => 'text NOT NULL',
                    'ai_provider' => 'varchar(50) NOT NULL',
                    'response' => 'longtext',
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'consumed' => 'int NOT NULL DEFAULT 0',
                    'status' => "varchar(20) NOT NULL DEFAULT 'completed'",
                    'ip' => 'varchar(45)',
                    'meta_data' => 'longtext',
                    'response_type' => "varchar(20) NOT NULL DEFAULT 'text'",
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'user_id' => '(user_id)',
                        'ai_provider' => '(ai_provider)',
                        'created_at' => '(created_at)',
                        'status' => '(status)',
                        'response_type' => '(response_type)'
                    ]
                ]
            ]
        ];
    }

    /**
     * Setup feature
     */
    protected function setup(): void {
       
        // Load required files
        require_once $this->path . 'includes/AIAdmin.php';
        require_once $this->path . 'includes/AIManager.php';
        require_once $this->path . 'includes/AIProvider.php';
        require_once $this->path . 'includes/AITracking.php';
        require_once $this->path . 'includes/Class_Tracking_List_Table.php';

        // Load providers (static list — avoids filesystem glob on every request)
        require_once $this->path . 'includes/Providers/Claude.php';
        require_once $this->path . 'includes/Providers/OpenAI.php';
        require_once $this->path . 'includes/Providers/Gemini.php';
        require_once $this->path . 'includes/Providers/Perplexity.php';
    }

    /**
     * Initialize hooks
     */
    protected function init_hooks(): void {
        parent::init_hooks();
        // Migrate legacy model IDs if needed
        $this->maybe_migrate_model_ids();

       // Initialize components
       $this->manager = new AIManager($this);
       $this->tracking = new AITracking($this);

       if (is_admin()) {
           $this->admin = new AIAdmin($this);
       }

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Get feature default options
     */
    protected function get_feature_default_options(): array {
        return [
            'providers' => [
                'openai' => [
                    'active' => true,
                    'name' => 'OpenAI',
                    'config' => [
                        'api_key'          => '',
                        'endpoint'         => 'https://api.openai.com/v1',
                        'model'            => 'gpt-5.4-mini',
                        'max_tokens'       => 2048,
                        'temperature'      => 0.7,
                        'top_p'            => 1,
                        'frequency_penalty' => 0,
                        'presence_penalty' => 0,
                        'stop_sequences'   => [],
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
                    ]
                ],
                'claude' => [
                    'active' => false,
                    'name' => 'Claude',
                    'config' => [
                        'api_key' => '',
                        'endpoint' => 'https://api.anthropic.com/v1',
                        'model' => 'claude-3-opus-20240229',
                        'max_tokens' => 2048,
                        'temperature' => 0.7,
                        'top_p' => 1
                    ]
                ],
                'gemini' => [
                    'active' => false,
                    'name' => 'Gemini',
                    'config' => [
                        'api_key' => '',
                        'endpoint' => 'https://generativelanguage.googleapis.com/v1',
                        'model' => 'gemini-pro',
                        'max_tokens' => 2048,
                        'temperature' => 0.7,
                        'top_p' => 1
                    ]
                ],
                'perplexity' => [
                    'active' => false,
                    'name' => 'Perplexity',
                    'config' => [
                        'api_key' => '',
                        'model' => 'pplx-70b-online',
                        'max_tokens' => 2048,
                        'temperature' => 0.7,
                        'top_p' => 1
                    ]
                ]
            ],
            'limits' => [
                'requests_per_day' => 100,
                'limit_message' => __('You have reached your daily request limit.', 'cobra-ai')
            ],
            'maintenance' => [
                'active' => false,
                'message' => __('System is under maintenance.', 'cobra-ai'),
                'start_date' => null,
                'end_date' => null,
                'excluded_roles' => ['administrator']
            ],
            'display' => [
                'show_in_profile' => true,
                'enable_rest_api' => true
            ]
        ];
    }

    /**
     * Validate settings
     */
    protected function validate_settings(array $settings): array {
        // Prevent infinite loops by checking if we're already validating
        static $validating = false;
        if ($validating) {
            return $settings;
        }
        
        $validating = true;
        $errors = [];

        // Validate providers
        if (empty($settings['providers']) || !is_array($settings['providers'])) {
            $errors[] = __('At least one provider must be configured', 'cobra-ai');
        } else {
            foreach ($settings['providers'] as $provider => $config) {
                if (!empty($config['active']) && empty($config['config']['api_key'])) {
                    $errors[] = sprintf(
                        __('API key is required for %s provider', 'cobra-ai'),
                        $config['name'] ?? $provider
                    );
                }
            }
        }

        // Validate limits
        if (isset($settings['limits']) && is_array($settings['limits'])) {
            if (isset($settings['limits']['requests_per_day']) && $settings['limits']['requests_per_day'] < 1) {
                $errors[] = __('Daily request limit must be greater than 0', 'cobra-ai');
            }
        }

        // Validate maintenance
        if (isset($settings['maintenance']) && is_array($settings['maintenance']) && !empty($settings['maintenance']['active'])) {
            if (empty($settings['maintenance']['message'])) {
                $errors[] = __('Maintenance message is required when maintenance is active', 'cobra-ai');
            }

            if (!empty($settings['maintenance']['start_date']) && 
                !empty($settings['maintenance']['end_date']) && 
                strtotime($settings['maintenance']['start_date']) > strtotime($settings['maintenance']['end_date'])) {
                $errors[] = __('Maintenance end date must be after start date', 'cobra-ai');
            }
        }

        // Store validation errors if any
        if (!empty($errors)) {
            update_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors', $errors);
            // DON'T call get_option or wp_parse_args here - just return the input settings
            $validating = false;
            return $settings;
        }

        delete_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors');

        // ── OpenAI-specific field validation ─────────────────────────────────
        $cfg = &$settings['providers']['openai']['config'];
        if (is_array($cfg)) {
            // Image size per model
            $valid_image_sizes = [
                'gpt-image-2' => ['1024x1024', '1536x1024', '1024x1536', 'auto'],
                'dall-e-3'    => ['1024x1024', '1792x1024', '1024x1792'],
                'dall-e-2'    => ['256x256', '512x512', '1024x1024'],
            ];
            $img_model = $cfg['image_model'] ?? 'dall-e-3';
            if (!array_key_exists($img_model, $valid_image_sizes)) {
                $cfg['image_model'] = 'dall-e-3';
                $img_model = 'dall-e-3';
            }
            $allowed_sizes = $valid_image_sizes[$img_model];
            if (!empty($cfg['image_size']) && !in_array($cfg['image_size'], $allowed_sizes, true)) {
                $cfg['image_size'] = $allowed_sizes[0];
            }
            // Image quality / style
            if (!empty($cfg['image_quality']) && !in_array($cfg['image_quality'], ['standard', 'hd'], true)) {
                $cfg['image_quality'] = 'standard';
            }
            if (!empty($cfg['image_style']) && !in_array($cfg['image_style'], ['vivid', 'natural'], true)) {
                $cfg['image_style'] = 'vivid';
            }
            if (!empty($cfg['image_response_format'])
                && !in_array($cfg['image_response_format'], ['url', 'b64_json'], true)) {
                $cfg['image_response_format'] = 'url';
            }
            // Audio model
            if (!empty($cfg['audio_model'])
                && !in_array($cfg['audio_model'], ['gpt-4o-transcribe', 'gpt-4o-mini-transcribe', 'whisper-1'], true)) {
                $cfg['audio_model'] = 'gpt-4o-transcribe';
            }
            // Audio language (ISO 639-1 or empty)
            if (!empty($cfg['audio_language'])
                && !preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $cfg['audio_language'])) {
                $cfg['audio_language'] = '';
            }
            // Audio response format
            if (!empty($cfg['audio_response_format'])
                && !in_array($cfg['audio_response_format'], ['json', 'text', 'srt', 'verbose_json', 'vtt'], true)) {
                $cfg['audio_response_format'] = 'json';
            }
            // TTS model
            if (!empty($cfg['tts_model'])
                && !in_array($cfg['tts_model'], ['gpt-4o-mini-tts', 'tts-1', 'tts-1-hd'], true)) {
                $cfg['tts_model'] = 'gpt-4o-mini-tts';
            }
            // TTS voice
            if (!empty($cfg['tts_voice'])
                && !in_array($cfg['tts_voice'], ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'], true)) {
                $cfg['tts_voice'] = 'alloy';
            }
            // TTS speed 0.25 – 4.0
            if (isset($cfg['tts_speed'])) {
                $cfg['tts_speed'] = max(0.25, min(4.0, (float) $cfg['tts_speed']));
            }
            // TTS format
            if (!empty($cfg['tts_format'])
                && !in_array($cfg['tts_format'], ['mp3', 'opus', 'aac', 'flac', 'wav', 'pcm'], true)) {
                $cfg['tts_format'] = 'mp3';
            }
        }

        $validating = false;
        return $settings; // Return validated settings
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        // Get settings directly from database to prevent validation loops
        $saved_settings = get_option('cobra_ai_' . $this->get_feature_id() . '_options', []);
        $settings = wp_parse_args($saved_settings, $this->get_feature_default_options());
        
        if (empty($settings['display']['enable_rest_api'])) {
            return;
        }

        register_rest_route('cobra-ai/v1', '/trackings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_get_trackings'],
                'permission_callback' => [$this, 'rest_check_permission']
            ]
        ]);

        register_rest_route('cobra-ai/v1', '/trackings/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_get_tracking'],
                'permission_callback' => [$this, 'rest_check_permission']
            ]
        ]);

        register_rest_route('cobra-ai/v1', '/request', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'rest_process_request'],
                'permission_callback' => [$this, 'rest_check_permission']
            ]
        ]);

        register_rest_route('cobra-ai/v1', '/providers', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_get_providers'],
                'permission_callback' => [$this, 'rest_check_permission']
            ]
        ]);

        // Image generation
        register_rest_route('cobra-ai/v1', '/image', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_generate_image'],
                'permission_callback' => [$this, 'rest_check_permission'],
                'args' => [
                    'provider' => ['required' => false, 'default' => 'openai', 'sanitize_callback' => 'sanitize_text_field'],
                    'prompt'   => ['required' => true,  'sanitize_callback' => 'sanitize_textarea_field'],
                ],
            ]
        ]);

        // Audio transcription (STT)
        register_rest_route('cobra-ai/v1', '/audio/transcribe', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_transcribe_audio'],
                'permission_callback' => [$this, 'rest_check_permission'],
                'args' => [
                    'provider' => ['required' => false, 'default' => 'openai', 'sanitize_callback' => 'sanitize_text_field'],
                    'audio_url' => ['required' => false, 'sanitize_callback' => 'esc_url_raw'],
                ],
            ]
        ]);

        // Text-to-speech
        register_rest_route('cobra-ai/v1', '/audio/speech', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_synthesize_speech'],
                'permission_callback' => [$this, 'rest_check_permission'],
                'args' => [
                    'provider' => ['required' => false, 'default' => 'openai', 'sanitize_callback' => 'sanitize_text_field'],
                    'text'     => ['required' => true,  'sanitize_callback' => 'sanitize_textarea_field'],
                ],
            ]
        ]);
    }

    /**
     * Check REST API permissions
     */
    public function rest_check_permission(): bool {
        return is_user_logged_in();
    }

    // -------------------------------------------------------------------------
    // REST callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /cobra-ai/v1/trackings
     */
    public function rest_get_trackings(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = (int) get_current_user_id();
        $args = [
            'provider'      => sanitize_text_field($request->get_param('provider') ?? ''),
            'status'        => sanitize_text_field($request->get_param('status')   ?? ''),
            'response_type' => sanitize_text_field($request->get_param('type')     ?? ''),
            'limit'         => min(100, max(1, (int) ($request->get_param('limit')  ?? 20))),
            'offset'        => max(0, (int) ($request->get_param('offset') ?? 0)),
            'period'        => sanitize_text_field($request->get_param('period') ?? ''),
        ];
        $items = $this->tracking->get_user_trackings($user_id, $args);
        $total = $this->tracking->get_user_tracking_count($user_id, $args);
        return new \WP_REST_Response(['items' => $items, 'total' => $total], 200);
    }

    /**
     * GET /cobra-ai/v1/trackings/{id}
     */
    public function rest_get_tracking(\WP_REST_Request $request): \WP_REST_Response {
        $user_id    = (int) get_current_user_id();
        $tracking_id = (int) $request->get_param('id');
        $item = $this->tracking->get_tracking($tracking_id);
        if (!$item) {
            return new \WP_REST_Response(['message' => __('Tracking not found', 'cobra-ai')], 404);
        }
        // Non-admins can only see their own entries
        if (!current_user_can('manage_options') && (int) $item->user_id !== $user_id) {
            return new \WP_REST_Response(['message' => __('Forbidden', 'cobra-ai')], 403);
        }
        return new \WP_REST_Response($item, 200);
    }

    /**
     * POST /cobra-ai/v1/request  (text / chat)
     */
    public function rest_process_request(\WP_REST_Request $request): \WP_REST_Response {
        $provider = sanitize_text_field($request->get_param('provider') ?? 'openai');
        $prompt   = $request->get_param('prompt');
        $options  = (array) ($request->get_param('options') ?? []);
        if (empty($prompt)) {
            return new \WP_REST_Response(['message' => __('prompt is required', 'cobra-ai')], 400);
        }
        try {
            $result = $this->process_request($provider, $prompt, $options, 'text');
            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /cobra-ai/v1/providers
     */
    public function rest_get_providers(\WP_REST_Request $request): \WP_REST_Response {
        $raw = $this->get_active_providers();
        $out = [];
        foreach ($raw as $id => $instance) {
            $out[$id] = [
                'id'           => $id,
                'name'         => $instance->get_name(),
                'capabilities' => $instance->get_request_types(),
                'models'       => array_keys($instance->get_supported_models()),
            ];
        }
        return new \WP_REST_Response($out, 200);
    }

    /**
     * POST /cobra-ai/v1/image
     * Body: { provider?, prompt, options? }
     */
    public function rest_generate_image(\WP_REST_Request $request): \WP_REST_Response {
        $provider = sanitize_text_field($request->get_param('provider') ?? 'openai');
        $prompt   = sanitize_textarea_field($request->get_param('prompt') ?? '');
        $options  = (array) ($request->get_param('options') ?? []);
        if (empty($prompt)) {
            return new \WP_REST_Response(['message' => __('prompt is required', 'cobra-ai')], 400);
        }
        try {
            $result = $this->process_request($provider, $prompt, $options, 'image');
            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /cobra-ai/v1/audio/transcribe
     * Accepts multipart/form-data with `file` field OR JSON body with `audio_url`.
     */
    public function rest_transcribe_audio(\WP_REST_Request $request): \WP_REST_Response {
        $provider = sanitize_text_field($request->get_param('provider') ?? 'openai');
        $options  = (array) ($request->get_param('options') ?? []);

        // Resolve audio input: uploaded file OR URL
        $files = $request->get_file_params();
        if (!empty($files['file'])) {
            $audio_input = $files['file']; // $_FILES-style array
        } else {
            $audio_url = esc_url_raw($request->get_param('audio_url') ?? '');
            if (empty($audio_url)) {
                return new \WP_REST_Response(
                    ['message' => __('Provide either a file upload or an audio_url', 'cobra-ai')], 400
                );
            }
            $audio_input = $audio_url;
        }

        try {
            $result = $this->process_request($provider, $audio_input, $options, 'audio');
            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /cobra-ai/v1/audio/speech
     * Body: { provider?, text, options? }
     */
    public function rest_synthesize_speech(\WP_REST_Request $request): \WP_REST_Response {
        $provider = sanitize_text_field($request->get_param('provider') ?? 'openai');
        $text     = sanitize_textarea_field($request->get_param('text') ?? '');
        $options  = (array) ($request->get_param('options') ?? []);
        if (empty($text)) {
            return new \WP_REST_Response(['message' => __('text is required', 'cobra-ai')], 400);
        }
        try {
            $result = $this->process_request($provider, $text, $options, 'tts');
            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user trackings
     */
    public function get_user_trackings(int $user_id, array $args = []): array {
        return $this->tracking->get_user_trackings($user_id, $args);
    }

    /**
     * Get tracking details
     */
    public function get_tracking(int $tracking_id) {
        return $this->tracking->get_tracking($tracking_id);
    }

    /**
     * Process AI request
     *
     * @param string       $provider     The AI provider to use
     * @param string|array $prompt       The prompt (or audio input for 'audio' type)
     * @param array        $options      Additional options
     * @param string       $request_type Request type: 'text' | 'image' | 'audio' | 'tts'
     * @return mixed
     */
    public function process_request(string $provider, string|array $prompt, array $options = [], string $request_type = 'text') {
        return $this->manager->process_request($provider, $prompt, $options, $request_type);
    }

    /**
     * Migrate legacy/invalid model IDs saved in the database
     */
    private function maybe_migrate_model_ids(): void {
        $saved = get_option('cobra_ai_' . $this->get_feature_id() . '_options', []);
        if (empty($saved['providers']['openai']['config']['model'])) {
            return;
        }
        $legacy_map = [
            'gpt-5-2025-08-07'     => 'gpt-5.4',
            'gpt-5-mini-2025-08-07' => 'gpt-5.4-mini',
            'gpt-4-vision-preview' => 'gpt-4o',
        ];
        $current = $saved['providers']['openai']['config']['model'];
        if (isset($legacy_map[$current])) {
            $saved['providers']['openai']['config']['model'] = $legacy_map[$current];
            update_option('cobra_ai_' . $this->get_feature_id() . '_options', $saved);
        }
    }

    /**
     * Get active providers
     */
    public function get_active_providers(): array {
        return $this->manager->get_active_providers();
    }

    /**
     * Check if user can make requests
     */
    public function can_make_request(int $user_id, string $provider): bool {
        return $this->manager->can_make_request($user_id, $provider);
    }
    /**
     * Get manager
     */
    public function get_manager(): AIManager {
        return $this->manager;
    }

    /**
     * Get default options (public method for external access)
     */
    public function get_default_options(): array {
        return $this->get_feature_default_options();
    }

    /**
     * Override render_settings to prevent infinite loop
     */
    public function render_settings(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get settings directly from database to prevent validation loops
        $saved_settings = get_option('cobra_ai_' . $this->get_feature_id() . '_options', []);
        $settings = wp_parse_args($saved_settings, $this->get_feature_default_options());

        include $this->path . 'views/settings.php';
    }

    
}