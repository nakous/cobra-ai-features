<?php

namespace CobraAI\Features\AI;

class AIAdmin
{
    /**
     * Feature instance
     */
    private $feature;

    /**
     * Admin menu hooks
     */
    private $menu_slug = 'cobra-ai-trackings';
    private $capability = 'manage_options';
    private $parent_slug = 'cobra-ai-dashboard';

    /**
     * Constructor
     */
    public function __construct($feature)
    {
        global $cobra_ai;
        $this->feature = $feature ?? $cobra_ai->get_feature('ai');

        if (!$this->feature) {
            throw new \Exception('AI Feature not initialized');
        }

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'add_menu_items']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Users list modifications
        add_filter('manage_users_columns', [$this, 'add_tracking_column']);
        add_filter('manage_users_custom_column', [$this, 'tracking_column_content'], 10, 3);
        add_filter('user_row_actions', [$this, 'add_tracking_actions'], 10, 2);

        // User profile
        add_action('show_user_profile', [$this, 'add_tracking_section']);
        add_action('edit_user_profile', [$this, 'add_tracking_section']);

        // AJAX handlers
        add_action('wp_ajax_cobra_ai_get_tracking_details', [$this, 'ajax_get_tracking_details']);
        add_action('wp_ajax_cobra_ai_delete_tracking', [$this, 'ajax_delete_tracking']);
        add_action('wp_ajax_cobra_ai_test_connection', [$this, 'handle_test_connection']);
    }

    /**
     * Add menu items
     */
    public function add_menu_items(): void
    {
        add_submenu_page(
            $this->parent_slug,
            __('AI Trackings', 'cobra-ai'),
            __('AI Trackings', 'cobra-ai'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_trackings_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook): void
    {
        // Only load on our pages
        if (strpos($hook, 'cobra-ai') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'cobra-ai-admin',
            $this->feature->get_url() . 'assets/css/admin.css',
            [],
            // $this->feature->version
        );

        // // JavaScript
        wp_enqueue_script(
            'cobra-ai-admin',
            $this->feature->get_url() . 'assets/js/admin.js',
            ['jquery'],
            // $this->feature->version,
            true
        );

        // Localize script
        wp_localize_script('cobra-ai-' . $this->feature->get_feature_id(), 'cobraAI', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cobra_ai_admin'),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this tracking?', 'cobra-ai'),
                'loading' => __('Loading...', 'cobra-ai'),
                'error' => __('An error occurred', 'cobra-ai')
            ]
        ]);
    }

    /**
     * Render trackings page
     */
    public function render_trackings_page(): void
    {
        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'view':
                $this->render_tracking_details();
                break;
            case 'user':
                $this->render_user_trackings();
                break;
            default:
                $this->render_trackings_list();
                break;
        }
    }

    /**
     * Render trackings list
     */
    private function render_trackings_list(): void
    {
        $tracking_table = new Class_Tracking_List_Table();
        $tracking_table->prepare_items();

        // Get filters
        $ai_provider = isset($_GET['ai_provider']) ? sanitize_text_field($_GET['ai_provider']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $response_type = isset($_GET['response_type']) ? sanitize_text_field($_GET['response_type']) : '';

        include $this->feature->get_path() . 'views/admin/trackings.php';
    }

    /**
     * Render user trackings
     */
    private function render_user_trackings(): void
    {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $user = get_user_by('id', $user_id);

        if (!$user) {
            wp_die(__('Invalid user', 'cobra-ai'));
        }

        $tracking_table = new Class_Tracking_List_Table(['user_id' => $user_id]);
        $tracking_table->prepare_items();

        include $this->feature->get_path() . 'views/admin/user-trackings.php';
    }

    /**
     * Render tracking details
     */
    private function render_tracking_details(): void
    {
        $tracking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $tracking = $this->feature->get_tracking($tracking_id);

        if (!$tracking) {
            wp_die(__('Tracking not found', 'cobra-ai'));
        }

        include $this->feature->get_path() . 'views/profile/tracking-details.php';
    }

    /**
     * Add tracking column to users list
     */
    public function add_tracking_column($columns): array
    {
        $columns['ai_trackings'] = __('AI Requests', 'cobra-ai');
        return $columns;
    }

    /**
     * Add content to tracking column
     */
    public function tracking_column_content($value, $column_name, $user_id): string
    {
        if ($column_name !== 'ai_trackings') {
            return $value;
        }

        // Get tracking counts
        $total = $this->feature->tracking->get_user_tracking_count($user_id);
        $today = $this->feature->tracking->get_user_tracking_count($user_id, ['period' => 'today']);

        // Build stats display
        $stats = sprintf(
            __('Total: %d<br>Today: %d', 'cobra-ai'),
            $total,
            $today
        );

        // Add action link
        $actions = sprintf(
            '<div class="row-actions"><a href="%s">%s</a></div>',
            esc_url(add_query_arg([
                'page' => $this->menu_slug,
                'action' => 'user',
                'user_id' => $user_id
            ], admin_url('admin.php'))),
            __('View History', 'cobra-ai')
        );

        return $stats . $actions;
    }

    /**
     * Add tracking actions to user row
     */
    public function add_tracking_actions($actions, $user): array
    {
        $actions['ai_trackings'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(add_query_arg([
                'page' => $this->menu_slug,
                'action' => 'user',
                'user_id' => $user->ID
            ], admin_url('admin.php'))),
            __('AI History', 'cobra-ai')
        );

        return $actions;
    }

    /**
     * Add tracking section to user profile
     */
    public function add_tracking_section($user): void
    {
        if (!current_user_can($this->capability) && $user->ID !== get_current_user_id()) {
            return;
        }

        // Get settings
        $settings = $this->feature->get_settings();
        if (empty($settings['display']['show_in_profile'])) {
            return;
        }

        // Get tracking data
        $trackings = $this->feature->get_user_trackings($user->ID, ['limit' => 5]);
        $total = $this->feature->tracking->get_user_tracking_count($user->ID);
        $providers = $this->feature->get_active_providers();

        include $this->feature->get_path() . 'views/admin/user-profile-trackings.php';
    }

    /**
     * AJAX: Get tracking details
     */
    public function ajax_get_tracking_details(): void
    {
        check_ajax_referer('cobra_ai_admin', 'nonce', false);

        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Permission denied', 'cobra-ai'));
        }

        $tracking_id = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
        $tracking = $this->feature->get_tracking($tracking_id);

        if (!$tracking) {
            wp_send_json_error(__('Tracking not found', 'cobra-ai'));
        }
        $prompt = "";
        $response = "";
        if ($this->is_json($tracking->prompt)) {
            $decoded_prompt = json_decode($tracking->prompt, true);
            $fields = ['image', 'user', 'system'];
            $prompt_parts = [];

            foreach ($fields as $field) {
                if (isset($decoded_prompt[$field])) {
                    if ($field === 'image') {
                        $prompt_parts[] = sprintf(' <img src="%s" alt="Image" style="max-width: 400px; max-height: 300px;" />', esc_url($decoded_prompt[$field]));
                    } else if ($field === 'user') {
                        // For other fields, just display the text
                        $prompt_parts[] = sprintf(' %s',  esc_html($decoded_prompt[$field]));
                    }
                }
            }

            $prompt = implode('<br>', $prompt_parts);
        } else {
            $prompt = $tracking->prompt ?? '';
        }
        // Format tracking data
        $data = [
            'id' => $tracking->id,
            'user' => get_userdata($tracking->user_id),
            'prompt' => $prompt ?? '',
            'response' => $tracking->response,
            'created_at' => get_date_from_gmt($tracking->created_at),
            'ai_provider' => $tracking->ai_provider,
            'consumed' => $tracking->consumed,
            'status' => $tracking->status,
            'response_type' => $tracking->response_type,
            'meta_data' => json_decode($tracking->meta_data, true)
        ];

        wp_send_json_success($data);
    }
    private function is_json($string): bool
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    /**
     * AJAX: Delete tracking
     */
    public function ajax_delete_tracking(): void
    {
        check_ajax_referer('cobra_ai_admin', 'nonce', false);

        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Permission denied', 'cobra-ai'));
        }

        $tracking_id = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
        $deleted = $this->feature->tracking->delete_tracking($tracking_id);

        if (!$deleted) {
            wp_send_json_error(__('Failed to delete tracking', 'cobra-ai'));
        }

        wp_send_json_success();
    }

    /**
     * Get admin notices
     */
    public function get_admin_notices(): array
    {
        $notices = [];
        $settings = $this->feature->get_settings();

        // Check maintenance mode
        if (!empty($settings['maintenance']['active'])) {
            $notices[] = [
                'type' => 'warning',
                'message' => sprintf(
                    __('AI features are currently in maintenance mode. Message: %s', 'cobra-ai'),
                    $settings['maintenance']['message']
                )
            ];
        }

        // Check provider configurations
        foreach ($settings['providers'] as $provider => $config) {
            if ($config['active'] && empty($config['config']['api_key'])) {
                $notices[] = [
                    'type' => 'error',
                    'message' => sprintf(
                        __('API key not configured for %s provider', 'cobra-ai'),
                        $config['name']
                    )
                ];
            }
        }

        return $notices;
    }
    /**
     * Get active providers for display
     */
    private function get_providers_for_display(): array
    {
        $providers = $this->feature->get_active_providers();

        if (empty($providers)) {
            return [
                'openai' => 'OpenAI',
                'claude' => 'Claude',
                'gemini' => 'Gemini',
                'perplexity' => 'Perplexity'
            ];
        }

        return array_map(function ($provider) {
            return $provider->get_name();
        }, $providers);
    }


    /**
     * Handle test connection AJAX request
     */
    public function handle_test_connection(): void
    {
        try {


            // Verify nonce
            check_ajax_referer('cobra_ai_test_connection', 'nonce', false);


            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Permission denied', 'cobra-ai'));
            }

            // Get request data
            $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
            $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

            if (empty($provider) || empty($api_key)) {
                throw new \Exception(__('Missing required parameters', 'cobra-ai'));
            }

            // Test connection based on provider
            $result = $this->test_provider_connection($provider, $api_key);

            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                throw new \Exception($result['message']);
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Test provider connection
     */
    private function test_provider_connection(string $provider, string $api_key): array
    {
        switch ($provider) {
            case 'openai':
                return $this->test_openai_connection($api_key);
            case 'claude':
                return $this->test_claude_connection($api_key);
            case 'gemini':
                return $this->test_gemini_connection($api_key);
            case 'perplexity':
                return $this->test_perplexity_connection($api_key);
            default:
                return [
                    'success' => false,
                    'message' => sprintf(__('Unsupported provider: %s', 'cobra-ai'), $provider)
                ];
        }
    }

    /**
     * Test OpenAI connection
     */
    private function test_openai_connection(string $api_key): array
    {
        try {
            $response = wp_remote_get('https://api.openai.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status !== 200) {
                $error = $body['error']['message'] ?? __('Unknown error occurred', 'cobra-ai');
                throw new \Exception($error);
            }

            return [
                'success' => true,
                'message' => __('Successfully connected to OpenAI API', 'cobra-ai')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('OpenAI connection failed: %s', 'cobra-ai'), $e->getMessage())
            ];
        }
    }

    /**
     * Test Claude connection
     */
    private function test_claude_connection(string $api_key): array
    {
        try {
            $response = wp_remote_get('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2024-03-01',
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $status = wp_remote_retrieve_response_code($response);

            // Claude API returns 404 on this endpoint but that's okay for key validation
            if ($status === 401) {
                throw new \Exception(__('Invalid API key', 'cobra-ai'));
            }

            return [
                'success' => true,
                'message' => __('Successfully connected to Claude API', 'cobra-ai')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Claude connection failed: %s', 'cobra-ai'), $e->getMessage())
            ];
        }
    }

    /**
     * Test Gemini connection
     */
    private function test_gemini_connection(string $api_key): array
    {
        try {
            $url = add_query_arg([
                'key' => $api_key
            ], 'https://generativelanguage.googleapis.com/v1/models');

            $response = wp_remote_get($url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status !== 200) {
                $error = $body['error']['message'] ?? __('Unknown error occurred', 'cobra-ai');
                throw new \Exception($error);
            }

            return [
                'success' => true,
                'message' => __('Successfully connected to Gemini API', 'cobra-ai')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Gemini connection failed: %s', 'cobra-ai'), $e->getMessage())
            ];
        }
    }

    /**
     * Test Perplexity connection
     */
    private function test_perplexity_connection(string $api_key): array
    {
        try {
            $response = wp_remote_get('https://api.perplexity.ai/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status !== 200) {
                $error = $body['error'] ?? __('Unknown error occurred', 'cobra-ai');
                throw new \Exception($error);
            }

            return [
                'success' => true,
                'message' => __('Successfully connected to Perplexity API', 'cobra-ai')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Perplexity connection failed: %s', 'cobra-ai'), $e->getMessage())
            ];
        }
    }
}
