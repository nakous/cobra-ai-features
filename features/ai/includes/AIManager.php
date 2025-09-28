<?php

namespace CobraAI\Features\AI;

use function CobraAI\{
    cobra_ai_db
};

class AIManager
{
    /**
     * Feature instance
     */
    private $feature;

    /**
     * Providers registry
     */
    private $providers = [];

    /**
     * Constructor
     */
    public function __construct($feature)
    {
        $this->feature = $feature;
        $this->init_providers();
    }

    /**
     * Initialize providers
     */
    private function init_providers(): void
    {
        try {
            $settings = $this->feature->get_settings();

            // Register core providers
            $core_providers = [
                'openai' => 'OpenAI',
                'claude' => 'Claude',
                'gemini' => 'Gemini',
                'perplexity' => 'Perplexity'
            ];

            foreach ($core_providers as $id => $class) {
                if (!empty($settings['providers'][$id]['active'])) {
                    $provider_class = "CobraAI\\Features\\AI\\{$class}";
                    if (class_exists($provider_class)) {
                        $this->providers[$id] = new $provider_class($settings['providers'][$id]['config']);
                    }
                }
            }


            // Allow additional providers to be registered
            $this->providers = apply_filters('cobra_ai_providers', $this->providers);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to initialize AI providers: ' . $e->getMessage());
            $this->providers = [];
        }
    }

    /**
     * Process AI request
     *
     * @param string $provider Provider ID
     * @param string $prompt User prompt
     * @param array $options Additional options
     * @return array Response data
     * @throws \Exception
     */
    public function process_request(string $provider, string|array  $prompt, array $options = []): array
    {
        try {
            // Check if provider exists and is active
            if (!$this->provider_exists($provider)) {
                throw new \Exception(__('Invalid or inactive AI provider', 'cobra-ai'));
            }

            // Get user ID
            $user_id = get_current_user_id();
            if (!$user_id) {
                throw new \Exception(__('User not logged in', 'cobra-ai'));
            }

            // Check maintenance mode
            if (!$this->check_maintenance_mode($user_id)) {
                throw new \Exception($this->get_maintenance_message());
            }

            // Check user limits
            if (!$this->can_make_request($user_id, $provider)) {
                throw new \Exception($this->get_limit_message());
            }

            // Get provider instance
            $provider_instance = $this->providers[$provider];

            // Prepare tracking data
            $tracking_data = [
                'user_id' => $user_id,
                'prompt' => is_array($prompt) ? json_encode($prompt) : $prompt,
                'ai_provider' => $provider,
                'ip' => $this->get_client_ip(),
                'meta_data' => [
                    'options' => $options,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]
            ];

            // Start tracking
            $tracking_id = $this->feature->tracking->create_tracking($tracking_data);

            // Fire before request action
            do_action('cobra_ai_before_request', $provider, $prompt, $user_id, $tracking_id);

            // Process request through provider
            $start_time = microtime(true);
            $response = $provider_instance->process_request($prompt, $options);
            $duration = microtime(true) - $start_time;

            // Update tracking with response
            $this->feature->tracking->update_tracking($tracking_id, [
                'response' => $response['content'],
                'consumed' => $response['tokens'] ?? 0,
                'status' => 'completed',
                'meta_data' => array_merge($tracking_data['meta_data'], [
                    'duration' => $duration,
                    'response_meta' => $response['meta'] ?? null
                ])
            ]);

            // Fire after response action
            do_action('cobra_ai_after_response', $provider, $response, $tracking_id);

            return [
                'success' => true,
                'tracking_id' => $tracking_id,
                'content' => $response['content'],
                'meta' => array_merge(
                    $response['meta'] ?? [],
                    ['duration' => round($duration, 2)]
                )
            ];
        } catch (\Exception $e) {
            // Log error
            cobra_ai_db()->log('error', 'AI request failed: ' . $e->getMessage(), [
                'provider' => $provider,
                'user_id' => $user_id ?? null,
                'prompt' => $prompt
            ]);

            // Update tracking if exists
            if (!empty($tracking_id)) {
                $this->feature->tracking->update_tracking($tracking_id, [
                    'status' => 'failed',
                    'meta_data' => [
                        'error' => $e->getMessage()
                    ]
                ]);
            }

            // Fire error action
            do_action('cobra_ai_request_failed', $provider, $e, $user_id ?? null);

            throw $e;
        }
    }

    /**
     * Check if provider exists and is active
     */
    public function provider_exists(string $provider): bool
    {
        return isset($this->providers[$provider]);
    }

    /**
     * Get active providers
     */
    public function get_active_providers(): array
    {
        return $this->providers;
    }

    /**
     * Check maintenance mode
     */
    public function check_maintenance_mode(int $user_id): bool
    {
        $settings = $this->feature->get_settings();

        if (empty($settings['maintenance']['active'])) {
            return true;
        }

        // Check excluded roles
        $user = get_userdata($user_id);
        $excluded_roles = $settings['maintenance']['excluded_roles'] ?? [];

        if (!empty($user->roles)) {
            $intersect = array_intersect($user->roles, $excluded_roles);
            if (!empty($intersect)) {
                return true;
            }
        }

        // Check maintenance schedule
        $start_date = $settings['maintenance']['start_date'];
        $end_date = $settings['maintenance']['end_date'];
        $current_time = current_time('mysql');

        if ($start_date && strtotime($current_time) < strtotime($start_date)) {
            return true;
        }

        if ($end_date && strtotime($current_time) > strtotime($end_date)) {
            return true;
        }

        return false;
    }

    /**
     * Get maintenance message
     */
    private function get_maintenance_message(): string
    {
        $settings = $this->feature->get_settings();
        return $settings['maintenance']['message'] ?? __('System is under maintenance.', 'cobra-ai');
    }

    /**
     * Check if user can make request
     */
    public function can_make_request(int $user_id, string $provider): bool
    {
        $settings = $this->feature->get_settings();
        $daily_limit = $settings['limits']['requests_per_day'] ?? 0;

        if ($daily_limit <= 0) {
            return true;
        }

        // Get today's request count
        $count = $this->feature->tracking->get_user_tracking_count($user_id, [
            'period' => 'today',
            'provider' => $provider,
            'status' => 'completed'
        ]);

        return $count < $daily_limit;
    }

    /**
     * Get limit message
     */
    private function get_limit_message(): string
    {
        $settings = $this->feature->get_settings();
        return $settings['limits']['limit_message'] ?? __('You have reached your daily request limit.', 'cobra-ai');
    }

    /**
     * Get client IP
     */
    private function get_client_ip(): string
    {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Reset user daily limit
     */
    public function reset_user_limit(int $user_id): void
    {
        delete_user_meta($user_id, '_cobra_ai_daily_requests');
    }

    /**
     * Get provider instance
     */
    public function get_provider(string $provider)
    {
        return $this->providers[$provider] ?? null;
    }

    /**
     * Get provider config
     */
    public function get_provider_config(string $provider): array
    {
        $settings = $this->feature->get_settings();
        return $settings['providers'][$provider]['config'] ?? [];
    }

    /**
     * Update provider config
     */
    public function update_provider_config(string $provider, array $config): bool
    {
        try {
            $settings = $this->feature->get_settings();

            if (!isset($settings['providers'][$provider])) {
                return false;
            }

            $settings['providers'][$provider]['config'] = array_merge(
                $settings['providers'][$provider]['config'],
                $config
            );

            return $this->feature->update_settings($settings);
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', 'Failed to update provider config: ' . $e->getMessage(), [
                'provider' => $provider,
                'config' => $config
            ]);
            return false;
        }
    }

    /**
     * Get provider capabilities
     */
    public function get_provider_capabilities(string $provider): array
    {
        $provider_instance = $this->get_provider($provider);
        return $provider_instance ? $provider_instance->get_capabilities() : [];
    }

    /**
     * Validate provider options
     */
    public function validate_provider_options(string $provider, array $options): array
    {
        $provider_instance = $this->get_provider($provider);
        return $provider_instance ? $provider_instance->validate_options($options) : $options;
    }
}
