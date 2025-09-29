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

        // Load providers
        foreach (glob($this->path . 'includes/Providers/*.php') as $provider_file) {
            require_once $provider_file;
        }
    }

    /**
     * Initialize hooks
     */
    protected function init_hooks(): void {
        parent::init_hooks();
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
                        'api_key' => '',
                        'endpoint' => 'https://api.openai.com/v1',
                        'model' => 'gpt-4',
                        'max_tokens' => 2048,
                        'temperature' => 0.7,
                        'top_p' => 1,
                        'frequency_penalty' => 0,
                        'presence_penalty' => 0,
                        'stop_sequences' => []
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
        $errors = [];

        // Validate providers
        if (empty($settings['providers'])) {
            $errors[] = __('At least one provider must be configured', 'cobra-ai');
        }

        foreach ($settings['providers'] as $provider => $config) {
            if (!empty($config['active']) && empty($config['config']['api_key'])) {
                $errors[] = sprintf(
                    __('API key is required for %s provider', 'cobra-ai'),
                    $config['name']
                );
            }
        }

        // Validate limits
        if ($settings['limits']['requests_per_day'] < 1) {
            $errors[] = __('Daily request limit must be greater than 0', 'cobra-ai');
        }

        // Validate maintenance
        if (!empty($settings['maintenance']['active'])) {
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
            return $this->get_settings(); // Return current settings
        }

        delete_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors');
        return $settings;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        // Get settings
        $settings = $this->get_settings();
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
    }

    /**
     * Check REST API permissions
     */
    public function rest_check_permission(): bool {
        return is_user_logged_in();
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
     * @param string $provider The AI provider to use
     * @param string|array $prompt The prompt to process (string or array)
     * @param array $options Additional options
     * @return mixed
     */
    public function process_request(string $provider, $prompt, array $options = []) {
        return $this->manager->process_request($provider, $prompt, $options);
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
    public function get_manager(): AIManager {
        return $this->manager;
    }

    
}