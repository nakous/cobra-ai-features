<?php

namespace CobraAI\Features\Stripe;

use CobraAI\FeatureBase;
use Stripe\Stripe;



class Feature extends FeatureBase
{
    protected string $feature_id = 'stripe';
    protected string $name = 'Stripe Integration';
    protected string $description = 'Core Stripe integration with API and webhook management';
    protected string $version = '1.1.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = true;
    protected bool $has_admin = true;

    private $api;
    private $webhook;
    private $events;

    public function __construct()
    {
        parent::__construct();
        global $wpdb;
        $this->tables = [
            'stripe_logs' => [
                'name' => $wpdb->prefix . 'cobra_stripe_logs',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'event_type' => 'varchar(100) NOT NULL',
                    'event_id' => 'varchar(100)',
                    'data' => 'longtext',
                    'is_live' => 'tinyint(1) DEFAULT 0',
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'event_type' => '(event_type)',
                        'event_id' => '(event_id)',
                        'created_at' => '(created_at)'
                    ]
                ]
            ],
            'stripe_webhooks' => [
                'name' => $wpdb->prefix . 'cobra_stripe_webhooks',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'webhook_id' => 'varchar(100) NOT NULL',
                    'secret' => 'varchar(255) NOT NULL',
                    'is_live' => 'tinyint(1) DEFAULT 0',
                    'events' => 'text NOT NULL',
                    'status' => "enum('active','inactive') DEFAULT 'active'",
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'UNIQUE KEY' => 'webhook_id (webhook_id)'
                ]
            ]
        ];
    }

    /**
     * Setup feature
     */
    protected function setup(): void
    {
        require_once __DIR__ . '/includes/StripeAPI.php';
        require_once __DIR__ . '/includes/StripeWebhook.php';
        require_once __DIR__ . '/includes/StripeEvents.php';

        $this->api = new StripeAPI($this);
        $this->webhook = new StripeWebhook($this);
        $this->events = new StripeEvents($this);
    }

    protected function init_hooks(): void
    {
        parent::init_hooks();

        // Register actions and filters
        add_action('init', [$this, 'init_stripe']);
        add_action('rest_api_init', [$this, 'register_endpoints']);

        // add col to users table of stripe_user_id
        add_filter('manage_users_columns', [$this, 'add_user_table_columns_stripe_user_id']);
        add_filter('manage_users_custom_column', [$this, 'add_user_table_column_data_stripe_user_id'], 10, 3);
    }
    public function add_user_table_columns_stripe_user_id($columns)
    {
        $columns['stripe_user_id'] = 'Stripe ID';
        return $columns;
    }

    public function add_user_table_column_data_stripe_user_id($value, $column_name, $user_id): string
    {
        $stripe_user_id = get_user_meta($user_id, '_stripe_customer_id', true);
        if ( $column_name == 'stripe_user_id') {
            return $stripe_user_id ? $stripe_user_id : 'N/A';
        }
        return $value;
    }
    /**
     * Initialize Stripe with API keys
     */
    public function init_stripe(): void
    {
        try {
            $settings = $this->get_settings();
            $is_live = $settings['mode'] === 'live';
            $api_key = $is_live ? $settings['live_secret_key'] : $settings['test_secret_key'];

            if (empty($api_key)) {
                throw new \Exception('Stripe API key not configured');
            }

            Stripe::setApiKey($api_key);
            Stripe::setAppInfo(
                $settings['app_name'] ?? 'CobraAI Stripe',
                $settings['app_version'] ?? $this->version,
                $settings['app_url'] ?? 'https://example.com',
                $settings['partner_id'] ?? 'pp_partner_1234'  // Replace with your Stripe partner ID if you have one
            );
        } catch (\Exception $e) {
            $this->log_error('Failed to initialize Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Register REST API endpoints
     */
    public function register_endpoints(): void
    {

        register_rest_route('cobra-ai/v1', '/stripe/webhook', [
            'methods' => 'POST',
            'callback' => [$this->webhook, 'handle_webhook'],
            'permission_callback' => '__return_true'
        ]);
    }


    /**
     * Get default settings
     */
    protected function get_feature_default_options(): array
    {
        return [
            'mode' => 'test',
            'test_publishable_key' => '',
            'test_secret_key' => '',
            'live_publishable_key' => '',
            'live_secret_key' => '',
            'webhook_secret' => '',
            'debug_mode' => false,
            'log_retention_days' => 30,
            'app_name' => '',
            'app_version' => $this->version,
            'app_url' => '',
            'partner_id' => ''
        ];
    }

    /**
     * Render settings page
     */
    public function render_settings(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();

        // Get webhook status
        $webhook_status = $this->webhook->get_status();

        include __DIR__ . '/views/settings.php';
    }

    /**
     * Log error
     */
    public function log_error(string $message, array $context = []): void
    {
        if (!$this->get_settings('debug_mode')) {
            return;
        }
        error_log('[Stripe] ' . $message . ' ' . json_encode($context));
    }

    /**
     * Get API instance
     */
    public function get_api(): StripeAPI
    {
        return $this->api;
    }

    /**
     * Get webhook instance
     */
    public function get_webhook(): StripeWebhook
    {
        return $this->webhook;
    }

    /**
     * Get events instance
     */
    public function get_events(): StripeEvents
    {
        return $this->events;
    }
    /**
     * Get logs table name
     */
    public function get_logs_table(): string
    {
        return $this->get_table_name('stripe_logs');
    }

    /**
     * Get webhooks table name
     */
    public function get_webhooks_table(): string
    {
        return $this->get_table_name('stripe_webhooks');
    }

    /**
     * Clean old logs
     */
    public function cleanup_logs(int $days = 30): bool
    {
        global $wpdb;

        $logs_table = $this->get_logs_table();

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$logs_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        ) !== false;
    }
    /**
     * Validate settings before saving
     */
    protected function validate_settings(array $settings): array
    {
        echo "<br>validate_settings stripe";
        $errors = [];
        $validation_option = 'cobra_ai_' . $this->get_feature_id() . '_validation_errors';
        try {
            // Validate mode
            $settings['mode'] = in_array($settings['mode'], ['test', 'live']) ? $settings['mode'] : 'test';

            // Remove empty values from validation
            foreach ($settings as $key => $value) {
                if ($value === '') {
                    $settings[$key] = null;
                }
            }


            // Validate API keys only if they are set and not empty
            if ($settings['mode'] === 'live') {
                if (!empty($settings['live_secret_key'])) {
                    if (!preg_match('/^sk_live_/', $settings['live_secret_key'])) {
                        $settings['live_secret_key'] = ''; // Clear invalid value
                        $errors['live_secret_key'] =   __('Live secret key should start with sk_live_', 'cobra-ai');
                    }
                }
                if (!empty($settings['live_publishable_key'])) {
                    if (!preg_match('/^pk_live_/', $settings['live_publishable_key'])) {
                        $settings['live_publishable_key'] = ''; // Clear invalid value
                        $errors['live_publishable_key'] =   __('Live publishable key should start with pk_live_', 'cobra-ai');
                    }
                }
            } else {
                if (!empty($settings['test_secret_key'])) {
                    if (!preg_match('/^sk_test_/', $settings['test_secret_key'])) {
                        $settings['test_secret_key'] = ''; // Clear invalid value
                        $errors['test_secret_key'] =   __('Test secret  key should start with sk_test_', 'cobra-ai');
                    }
                }
                if (!empty($settings['test_publishable_key'])) {
                    if (!preg_match('/^pk_test_/', $settings['test_publishable_key'])) {
                        $settings['test_publishable_key'] = ''; // Clear invalid value
                        $errors['test_publishable_key'] =   __('Test publishable key should start with pk_test_', 'cobra-ai');
                    }
                }
            }

            // Validate webhook secret if provided
            if (!empty($settings['webhook_secret'])) {
                if (!preg_match('/^whsec_/', $settings['webhook_secret'])) {
                    $settings['webhook_secret'] = ''; // Clear invalid value
                    $errors['webhook_secret'] =   __('Webhook secret should start with whsec_', 'cobra-ai');
                }
            }

            // Validate numeric values with defaults
            $settings['log_retention_days'] = !empty($settings['log_retention_days'])
                ? absint($settings['log_retention_days'])
                : 30;

            if ($settings['log_retention_days'] < 1) {
                $settings['log_retention_days'] = 30;
                $errors['log_retention_days'] =   __('Log retention days should be a positive number', 'cobra-ai');
            }

            // Validate boolean values
            $settings['debug_mode'] = !empty($settings['debug_mode']);
            // print_r($errors);
            // Store validation errors if any
            if (!empty($errors)) {
                $updated = update_option($validation_option, $errors);

                if (!$updated) {
                    // Log the failure to update validation errors
                    error_log(sprintf(
                        'Failed to update validation errors for %s. Errors: %s',
                        $this->get_feature_id(),
                        json_encode($errors)
                    ));
                }
            } else {
                // Clear any existing validation errors
                delete_option($validation_option);
            }

            // Log validation results for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Stripe settings validation completed. Errors: %s, Settings: %s',
                    json_encode($errors),
                    json_encode($settings)
                ));
            }

            // Return validated settings
            return wp_parse_args($settings, $this->get_feature_default_options());
        } catch (\Exception $e) {
            // Log the error
            error_log(sprintf(
                'Error validating Stripe settings: %s',
                $e->getMessage()
            ));

            // Add system error to validation errors
            $errors['system'] = __('System error occurred while validating settings', 'cobra-ai');
            update_option($validation_option, $errors);

            // Return original settings if validation fails
            return wp_parse_args($settings, $this->get_feature_default_options());
        }
    }
    /**
     * Check if API keys are configured
     */
    public function has_api_keys(): bool
    {
        $settings = $this->get_settings();
        $mode = $settings['mode'];

        if ($mode === 'live') {
            return !empty($settings['live_secret_key']) && !empty($settings['live_publishable_key']);
        }

        return !empty($settings['test_secret_key']) && !empty($settings['test_publishable_key']);
    }

    /**
     * Get current mode
     */
    public function get_mode(): string
    {
        return $this->get_settings('mode', 'test');
    }

    /**
     * Check if in test mode
     */
    public function is_test_mode(): bool
    {
        return $this->get_mode() === 'test';
    }
}
