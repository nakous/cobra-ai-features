<?php

namespace CobraAI\Features\StripeSubscriptions;

use CobraAI\FeatureBase;

use function CobraAI\{
    cobra_ai
};

class Feature extends FeatureBase
{
    protected string $feature_id = 'stripesubscriptions';
    protected string $name = 'Stripe Subscription';
    protected string $description = 'Manage subscriptions and recurring payments with Stripe';
    protected string $version = '1.1.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = true;
    protected bool $has_admin = true;
    protected array $requires = ['stripe']; // Requires base Stripe feature

    private $api;
    private $admin;

    private $customers;
    private $Subscriptions;
    private $plans;
    private $payments;
    private $webhook;
    private $stripe_feature;

    public function __construct()
    {
        parent::__construct();

        // Define feature tables

    }
    /**
     * Setup feature
     */
    protected function setup(): void
    {

        try {
            global $wpdb;
            $this->tables = [

                'stripe_subscriptions' => [
                    'name' => $wpdb->prefix . 'cobra_stripe_subscriptions',
                    'schema' => [
                        'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                        'subscription_id' => 'varchar(100) NOT NULL',
                        'customer_id' => 'varchar(100) NOT NULL',
                        'plan_id' => 'bigint(20) NOT NULL',
                        'user_id' => 'bigint(20) NOT NULL',
                        'status' => "enum('active','past_due','canceled','incomplete','incomplete_expired','trialing','unpaid','paused') NOT NULL",
                        'current_period_start' => 'datetime NOT NULL',
                        'current_period_end' => 'datetime NOT NULL',
                        'cancel_at_period_end' => 'tinyint(1) NOT NULL DEFAULT 0',
                        'cancel_reason' => 'text',
                        'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                        'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                        'PRIMARY KEY' => '(id)',
                        'UNIQUE KEY' => 'subscription_id (subscription_id)',
                        'KEY' => [
                            'customer_id' => '(customer_id)',
                            'plan_id' => '(plan_id)',
                            'user_id' => '(user_id)',
                            'status' => '(status)',
                            'created_at' => '(created_at)'
                        ]
                    ]
                ],
                'stripe_payments' => [
                    'name' => $wpdb->prefix . 'cobra_stripe_payments',
                    'schema' => [
                        'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                        'payment_id' => 'varchar(100) NOT NULL',
                        'subscription_id' => 'bigint(20) NOT NULL',
                        'invoice_id' => 'varchar(100)',
                        'amount' => 'decimal(10,2) NOT NULL',
                        'currency' => 'varchar(3) NOT NULL',
                        'status' => "enum('pending','succeeded','failed','refunded') NOT NULL",
                        'refunded' => 'tinyint(1) NOT NULL DEFAULT 0',
                        'refund_id' => 'varchar(100)',
                        'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                        'PRIMARY KEY' => '(id)',
                        'UNIQUE KEY' => 'payment_id (payment_id)',
                        'KEY' => [
                            'subscription_id' => '(subscription_id)',
                            'status' => '(status)',
                            'created_at' => '(created_at)'
                        ]
                    ]
                ],
                'stripe_disputes' => [
                    'name' => $wpdb->prefix . 'cobra_stripe_disputes',
                    'schema' => [
                        'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                        'dispute_id' => 'varchar(100) NOT NULL',
                        'payment_id' => 'bigint(20) NOT NULL',
                        'amount' => 'decimal(10,2) NOT NULL',
                        'currency' => 'varchar(3) NOT NULL',
                        'status' => "enum('warning_needs_response','warning_under_review','warning_closed','needs_response','under_review','won','lost') NOT NULL",
                        'reason' => 'varchar(100) NOT NULL',
                        'evidence_details' => 'text',
                        'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                        'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                        'PRIMARY KEY' => '(id)',
                        'UNIQUE KEY' => 'dispute_id (dispute_id)',
                        'KEY' => [
                            'payment_id' => '(payment_id)',
                            'status' => '(status)',
                            'created_at' => '(created_at)'
                        ]
                    ]
                ]
            ];
            if (class_exists('\CobraAI\Features\Stripe\Feature')) {
                $this->stripe_feature = new \CobraAI\Features\Stripe\Feature();
            } else {
                throw new \Exception('Stripe feature class does not exist');
            }

            // add_action('init', [$this, 'register_post_type']);
            // $this->stripe_feature = cobra_ai()->get_feature('stripe');
            if (!$this->stripe_feature) {
                throw new \Exception('Stripe feature is required but not active');
            }
            // Initialize components
            require_once __DIR__ . '/includes/API.php';
            require_once __DIR__ . '/includes/Admin.php';
            require_once __DIR__ . '/includes/Customers.php';

            require_once __DIR__ . '/includes/Subscriptions.php';
            require_once __DIR__ . '/includes/Plans.php';
            require_once __DIR__ . '/includes/Payments.php';
            require_once __DIR__ . '/includes/Webhooks.php';


            $this->api = new API($this);
            $this->admin = Admin::get_instance($this);
            $this->customers = new Customers($this);
            $this->Subscriptions = new Subscriptions($this);
            $this->plans = new Plans($this);
            $this->payments = new Payments($this);
            $this->webhook = new Webhooks($this);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to setup Stripe Subscription feature: ' . $e->getMessage());
        }
    }

    /**
     * Initialize hooks
     */
    protected function init_hooks(): void
    {
        // log this 
        // $this->log('info', 'Initializing Stripe Subscription feature');

        parent::init_hooks();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets_stripe_var']);
        // Shortcodes
        // $this->log('info', 'Initializing Stripe Subscription feature');
        // add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'],0);

        // add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        // AJAX handlers
        // add_action('wp_ajax_cobra_subscription_checkout', [$this->api, 'handle_checkout']);
        // add_action('wp_ajax_cobra_subscription_cancel', [$this->api, 'handle_cancellation']);
        add_action('wp_ajax_cobra_create_checkout_session', [$this, 'handle_create_checkout_session']);
        add_action('wp_ajax_nopriv_cobra_create_checkout_session', [$this, 'handle_logged_out_user']);
        
        // Subscription management AJAX handlers
        add_action('wp_ajax_cobra_cancel_subscription', [$this, 'handle_cancel_subscription']);
        add_action('wp_ajax_cobra_resume_subscription', [$this, 'handle_resume_subscription']);
        add_action('wp_ajax_cobra_update_payment_method', [$this, 'handle_update_payment_method']);
        
        // Page creation AJAX handler
        add_action('wp_ajax_cobra_create_stripe_page', [$this, 'handle_create_page']);

        // Webhook events
        add_action('cobra_ai_stripe_customer_subscription_created', [$this->webhook, 'handle_subscription_created']);
        add_action('cobra_ai_stripe_customer_subscription_updated', [$this->webhook, 'handle_subscription_updated']);
        add_action('cobra_ai_stripe_customer_subscription_deleted', [$this->webhook, 'handle_subscription_deleted']);
        // handle_trial_ending
        add_action('cobra_ai_stripe_customer_subscription_trial_ending', [$this->webhook, 'handle_trial_ending']);


        add_action('cobra_ai_stripe_invoice_payment_succeeded', [$this->webhook, 'handle_invoice_paid']);
        add_action('cobra_ai_stripe_invoice_payment_failed', [$this->webhook, 'handle_invoice_failed']);
        // handle_upcoming_invoice
        add_action('cobra_ai_stripe_invoice_upcoming', [$this->webhook, 'handle_upcoming_invoice']);

        // Refund and dispute events
        add_action('cobra_ai_stripe_charge_refunded', [$this->payments, 'handle_refund_event']);
        add_action('cobra_ai_stripe_charge_dispute_created', [$this->payments, 'handle_dispute_created']);
        add_action('cobra_ai_stripe_charge_dispute_updated', [$this->webhook, 'handle_dispute_updated']);
        add_action('cobra_ai_stripe_charge_dispute_closed', [$this->webhook, 'handle_dispute_closed']);
        // add_filter('the_content', [$this, 'filter_plan_content']);
        add_action('cobra_register_profile_tab', [$this, 'cobra_subscription_account_custom_tab']);
        add_action('cobra_register_profile_tab_content', [$this, 'cobra_subscription_account_custom_tab_content'], 10, 2);
    }

    function cobra_subscription_account_custom_tab()
    {
        // Check if the user is logged in
        if (!is_user_logged_in()) {
            return;
        }

?>
        <li>
            <a href="#subscription" data-tab="subscription">
                <?php _e('My subscription', 'cobra-ai'); ?>
            </a>
        </li>
    <?php
    }
    function cobra_subscription_account_custom_tab_content()
    {
        // Check if the user is logged in
        if (!is_user_logged_in()) {
            return;
        }

        

        // Display the content for the subscription tab
        echo '<div class="cobra-tab-content" id="subscription-content">';
        
        echo do_shortcode('[stripe_subscription_details]');
        echo '</div>';
    }
    protected function register_shortcodes(): void
    {
        // Register shortcodes
        add_shortcode('stripe_checkout', [$this, 'render_checkout_shortcode']);
        add_shortcode('stripe_success', [$this, 'render_success_shortcode']);
        add_shortcode('stripe_cancel', [$this, 'render_cancel_shortcode']);
        add_shortcode('stripe_plans', [$this, 'render_plans_shortcode']);
        add_shortcode('stripe_subscription_details', [$this, 'render_subscription_details_shortcode']);

        add_shortcode('stripe_action_subscription', [$this, 'shortcode_action_subscription']);
    }
    /**
     * Enqueue public assets with optimized loading
     */
    public function enqueue_assets_stripe_var($hook): void
    {
        global $post;
        if (!$post) return;

        $shortcodes = ['stripe_plans', 'stripe_checkout', 'stripe_success', 'stripe_cancel', 'stripe_subscription_details'];
        
        // Check if post contains any of our shortcodes or is a subscription-related page
        $should_enqueue = false;
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode) || get_post_type($post) === 'stripe_plan') {
                $should_enqueue = true;
                break;
            }
        }

        // Also enqueue on account pages or pages with subscription content
        if (!$should_enqueue && (
            strpos($post->post_content, 'cobra-subscription') !== false ||
            is_page('account') ||
            is_user_logged_in() && strpos($hook, 'account') !== false
        )) {
            $should_enqueue = true;
        }

        if ($should_enqueue) {
            // Enqueue main public CSS
            wp_enqueue_style(
                'cobra-stripe-subscriptions-public',
                $this->assets_url . 'css/public.css',
                [],
                $this->version
            );

            // Enqueue additional subscription styles
            wp_enqueue_style(
                'cobra-stripe-subscriptions-styles',
                $this->assets_url . 'css/subscription-styles.css',
                ['cobra-stripe-subscriptions-public'],
                $this->version
            );

            // Enqueue public JavaScript
            wp_enqueue_script(
                'cobra-stripe-subscriptions-public',
                $this->assets_url . 'js/public.js',
                ['jquery'],
                $this->version,
                true
            );

            // Enqueue subscription manager
            wp_enqueue_script(
                'cobra-stripe-subscriptions-manager',
                $this->assets_url . 'js/subscription-manager.js',
                ['jquery', 'cobra-stripe-subscriptions-public'],
                $this->version,
                true
            );

            // Localize script with all necessary data
            wp_localize_script('cobra-stripe-subscriptions-manager', 'cobra_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'checkout_url' => get_permalink($this->get_settings('checkout_page')),
                'success_url' => get_permalink($this->get_settings('success_page')),
                'cancel_url' => get_permalink($this->get_settings('cancel_page')),
                'account_url' => get_permalink($this->get_settings('account_page')),
                'nonce' => wp_create_nonce('cobra-stripe-nonce'),
                'is_logged_in' => is_user_logged_in(),
                'stripe_key' => $this->get_stripe_feature()?->get_public_key() ?? '',
                'i18n' => [
                    'processing' => __('Processing...', 'cobra-ai'),
                    'confirm_cancel' => __('Are you sure you want to cancel your subscription?', 'cobra-ai'),
                    'confirm_resume' => __('Are you sure you want to resume your subscription?', 'cobra-ai'),
                    'cancel_success' => __('Subscription cancelled successfully', 'cobra-ai'),
                    'resume_success' => __('Subscription resumed successfully', 'cobra-ai'),
                    'update_success' => __('Payment method updated successfully', 'cobra-ai'),
                    'error' => __('An error occurred. Please try again.', 'cobra-ai'),
                ]
            ]);

            // Also localize for backward compatibility
            wp_localize_script('cobra-stripe-subscriptions-public', 'CobraSubscription', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'checkout_url' => get_permalink($this->get_settings('checkout_page')),
                'success_url' => get_permalink($this->get_settings('success_page')),
                'cancel_url' => get_permalink($this->get_settings('cancel_page')),
                'account_url' => get_permalink($this->get_settings('account_page')),
                'nonce' => wp_create_nonce('cobra-stripe-nonce'),
                'is_logged_in' => is_user_logged_in()
            ]);
        }
    }

    /**
     * Get default settings
     */
    protected function get_feature_default_options(): array
    {
        return [
            'checkout_page' => '',
            'success_page' => '',
            'cancel_page' => '',
            'enable_trial' => false,
            'trial_days' => 14,
            'allow_cancellation' => true,
            'cancellation_behavior' => 'end_of_period', // or 'immediate'
            'enable_webhooks' => true,
            'email_notifications' => true,
        ];
    }

    /**
     * Get API instance
     */
    public function get_api(): API
    {
        return $this->api;
    }

    /**
     * Get admin instance
     */
    public function get_admin(): Admin
    {
        return $this->admin;
    }

    /**
     * Get customers instance
     */
    public function get_customers(): Customers
    {
        return $this->customers;
    }

    public function get_subscriptions(): Subscriptions
    {
        return $this->Subscriptions;
    }

    /**
     * Get plans instance
     */
    public function get_plans(): Plans
    {
        return $this->plans;
    }

    /**
     * Get payments instance
     */
    public function get_payments(): Payments
    {
        return $this->payments;
    }

    /**
     * Get webhook instance
     */
    public function get_webhook(): Webhooks
    {
        return $this->webhook;
    }

    /**
     * Get Stripe feature instance
     */
    public function get_stripe_feature(): ?\CobraAI\Features\Stripe\Feature
    {
        return $this->stripe_feature;
    }

    /**
     * Validate settings
     */
    protected function validate_settings(array $settings): array
    {
        $settings = parent::validate_settings($settings);

        // Validate page IDs
        $settings['checkout_page'] = absint($settings['checkout_page']);
        $settings['success_page'] = absint($settings['success_page']);
        $settings['cancel_page'] = absint($settings['cancel_page']);

        // Validate trial days
        if (isset($settings['trial_days'])) {
            $settings['trial_days'] = max(0, absint($settings['trial_days']));
        }

        // Validate boolean values
        $settings['enable_trial'] = !empty($settings['enable_trial']);
        $settings['allow_cancellation'] = !empty($settings['allow_cancellation']);
        $settings['enable_webhooks'] = !empty($settings['enable_webhooks']);
        $settings['email_notifications'] = !empty($settings['email_notifications']);

        // Validate cancellation behavior
        if (!in_array($settings['cancellation_behavior'], ['end_of_period', 'immediate'])) {
            $settings['cancellation_behavior'] = 'end_of_period';
        }

        return $settings;
    }


    /**
     * Format plan price
     */
    public function format_price(float $amount, string $currency = 'USD'): string
    {

        $symbol = $this->format_currency_symbol($currency);
        return $symbol . number_format($amount, 2);
    }
    protected function format_currency_symbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            // Add more currencies as needed
        ];

        return $symbols[$currency] ?? $currency;
    }

    private function format_currency(float $amount, string $currency = 'USD'): string
    {
        return number_format($amount, 2) . ' ' . strtoupper($currency);
    }
    public function render_success_shortcode($atts = []): string
    {
        try {
            // Get session ID from URL
            $session_id = sanitize_text_field($_GET['session_id'] ?? '');
            $plan_id = absint($_GET['plan_id'] ?? 0);

            if (empty($session_id)) {
                return $this->render_error(__('Invalid checkout session.', 'cobra-ai'));
            }

            // Get Stripe session
            $stripe = $this->get_stripe_feature()->get_api();
            $session = \Stripe\Checkout\Session::retrieve([
                'id' => $session_id,
                'expand' => ['subscription', 'subscription.latest_invoice']
            ]);

            // Verify session belongs to current user
            if ($session->client_reference_id !== get_current_user_id() . '_' . $plan_id) {
                return $this->render_error(__('Invalid session data.', 'cobra-ai'));
            }

            // Get subscription details
            if (!$session->subscription) {
                return $this->render_error(__('No subscription found.', 'cobra-ai'));
            }

            // Get plan details
            $plan_post = get_post($plan_id);
            if (!$plan_post) {
                return $this->render_error(__('Plan not found.', 'cobra-ai'));
            }

            // Format plan data
            $plan = (object) [
                'id' => $plan_id,
                'name' => $plan_post->post_title,
                'description' => $plan_post->post_content,
                'amount' => get_post_meta($plan_id, '_price_amount', true),
                'currency' => get_post_meta($plan_id, '_price_currency', true),
                'interval' => get_post_meta($plan_id, '_billing_interval', true),
                'features' => get_post_meta($plan_id, '_features', true) ?: [],
                'trial_days' => get_post_meta($plan_id, '_trial_days', true)
            ];

            // Start output buffer
            ob_start();

            // Include success template
            include $this->path . 'views/public/success.php';

            return ob_get_clean();
        } catch (\Exception $e) {
            $this->log('error', 'Error displaying success page', [
                'error' => $e->getMessage(),
                'session_id' => $_GET['session_id'] ?? null
            ]);
            return $this->render_error($e->getMessage());
        }
    }

    /**
     * Render error message
     */
    private function render_error(string $message): string
    {
        ob_start();
    ?>
        <div class="cobra-error-message">
            <div class="error-icon">⚠️</div>
            <h2><?php echo esc_html__('Oops! Something went wrong', 'cobra-ai'); ?></h2>
            <p><?php echo esc_html($message); ?></p>
            <a href="<?php echo esc_url(home_url()); ?>" class="button">
                <?php echo esc_html__('Return Home', 'cobra-ai'); ?>
            </a>
        </div>
    <?php
        return ob_get_clean();
    }
    /**
     * Render cancel shortcode
     */
    public function render_cancel_shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'subscription' => '',
        ], $atts);

        ob_start();
        include __DIR__ . '/views/public/cancel.php'; 
        return ob_get_clean();
    }

    /**
     * Render plans shortcode
     */
    public function render_plans_shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'columns' => 3,
            'show_trial' => true,
            'show_features' => true,
            'highlight' => '',
            'currency' => 'USD'
        ], $atts);

        ob_start();
        include __DIR__ . '/views/public/plan-list.php';
        return ob_get_clean();
    }

    /**
     * Render subscription details shortcode
     */
    public function render_subscription_details_shortcode($atts = []): string
    {
        if (!is_user_logged_in()) {
            return sprintf(
                '<p class="cobra-notice">%s <a href="%s">%s</a></p>',
                esc_html__('Please', 'cobra-ai'),
                esc_url(wp_login_url(get_permalink())),
                esc_html__('login to view your subscription', 'cobra-ai')
            );
        }

        $atts = shortcode_atts([
            'show_payment_history' => true,
        ], $atts);

        ob_start();

        include __DIR__ . '/templates/subscription-details.php';
        return ob_get_clean();
    }


    public function getVesrion()
    {
        return $this->version;
    }
    /**
     * Filter subscription plan content
     */
    public function filter_plan_content($content)
    {
        // Only modify subscription plan content
        if (!is_singular('stripe_plan')) {
            return $content;
        }

        $post_id = get_the_ID();
        $price_amount = get_post_meta($post_id, '_price_amount', true);
        $currency = get_post_meta($post_id, '_price_currency', true);
        $billing_interval = get_post_meta($post_id, '_billing_interval', true);
        $interval_count = get_post_meta($post_id, '_interval_count', true);
        $features = get_post_meta($post_id, '_features', true);
        $trial_enabled = get_post_meta($post_id, '_trial_enabled', true);
        $trial_days = get_post_meta($post_id, '_trial_days', true);
        $stripe_price_id = get_post_meta($post_id, '_stripe_price_id', true);

        // Check if user has this plan
        $current_plan = false;
        if (is_user_logged_in()) {
            $user_subscription = $this->get_subscriptions()->get_user_subscription(get_current_user_id());
            $current_plan = $user_subscription && $user_subscription->plan_id === $post_id;
        }

        // Start building custom content
        ob_start();
    ?>
        <div class="cobra-plan-single">
            <div class="plan-header">
                <div class="plan-price-box">
                    <span class="currency"><?php echo esc_html($this->format_currency_symbol($currency)); ?></span>
                    <span class="amount"><?php echo esc_html(number_format($price_amount, 2)); ?></span>
                    <span class="interval">
                        <?php
                        if ($interval_count > 1) {
                            printf(
                                esc_html__('every %d %ss', 'cobra-ai'),
                                $interval_count,
                                $billing_interval
                            );
                        } else {
                            printf(
                                esc_html__('per %s', 'cobra-ai'),
                                $billing_interval
                            );
                        }
                        ?>
                    </span>
                </div>

                <?php if ($trial_enabled): ?>
                    <div class="plan-trial-badge">
                        <?php printf(
                            esc_html__('%d-day free trial', 'cobra-ai'),
                            $trial_days
                        ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="plan-description">
                <?php echo wp_kses_post($content); ?>
            </div>

            <?php if (!empty($features)): ?>
                <div class="plan-features">
                    <h3><?php echo esc_html__('What\'s Included', 'cobra-ai'); ?></h3>
                    <ul>
                        <?php foreach ($features as $feature): ?>
                            <li>
                                <span class="feature-icon">✓</span>
                                <?php echo esc_html($feature); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="plan-action">
                <?php if (!is_user_logged_in()): ?>
                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="button login-to-subscribe">
                        <?php echo esc_html__('Login to Subscribe', 'cobra-ai'); ?>
                    </a>
                    <p class="login-note">
                        <?php echo esc_html__('Don\'t have an account?', 'cobra-ai'); ?>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>">
                            <?php echo esc_html__('Sign up', 'cobra-ai'); ?>
                        </a>
                    </p>
                <?php elseif ($current_plan): ?>
                    <div class="current-plan-notice">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo esc_html__('You\'re currently on this plan', 'cobra-ai'); ?>
                    </div>
                    <a href="<?php echo esc_url(get_permalink(get_option('cobra_ai_account_page'))); ?>" class="button secondary">
                        <?php echo esc_html__('Manage Subscription', 'cobra-ai'); ?>
                    </a>
                <?php else: ?>
                    <button class="button subscribe-button"
                        data-plan-id="<?php echo esc_attr($post_id); ?>"
                        data-price-id="<?php echo esc_attr($stripe_price_id); ?>">
                        <?php echo esc_html__('Subscribe Now', 'cobra-ai'); ?>
                    </button>
                    <?php if ($trial_enabled): ?>
                        <p class="trial-note">
                            <?php printf(
                                esc_html__('Start your %d-day free trial today', 'cobra-ai'),
                                $trial_days
                            ); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (is_user_logged_in() && !$current_plan): ?>
            <script>
                jQuery(document).ready(function($) {
                    $('.subscribe-button').on('click', async function() {
                        const button = $(this);
                        const planId = button.data('plan-id');
                        const priceId = button.data('price-id');

                        button.prop('disabled', true)
                            .html('<span class="spinner"></span> <?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

                        try {
                            const response = await $.ajax({
                                url: CobraSubscription.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'cobra_create_checkout_session',
                                    plan_id: planId,
                                    price_id: priceId,
                                    nonce: CobraSubscription.nonce
                                }
                            });

                            if (!response.success) {
                                throw new Error(response.data.message);
                            }

                            window.location.href = response.data.checkout_url;

                        } catch (error) {
                            console.error('Checkout error:', error);
                            alert(error.message || '<?php echo esc_js(__('Failed to process subscription', 'cobra-ai')); ?>');

                            button.prop('disabled', false)
                                .text('<?php echo esc_js(__('Subscribe Now', 'cobra-ai')); ?>');
                        }
                    });
                });
            </script>
        <?php endif;

        return ob_get_clean();
    }
    /**
     * Shortcode for displaying subscription plan action buttons
     * 
     * @param array $atts Shortcode attributes
     * @return string Formatted HTML for subscription action
     */
    public function shortcode_action_subscription($atts)
    {
        // Extract attributes with defaults
        $attributes = shortcode_atts(array(
            'id' => get_the_ID(), // Default to current post ID if not specified
        ), $atts);

        $post_id = absint($attributes['id']);

        // Verify post exists and is the correct type
        $post = get_post($post_id);
        if (!$post || get_post_type($post) !== 'stripe_plan') {
            return '<p>' . esc_html__('Invalid subscription plan.', 'cobra-ai') . '</p>';
        }
        $stripe_price_id = get_post_meta($post_id, '_stripe_price_id', true);

        // Get plan details
        $plan_data = $this->get_plans()->get_plan($post_id);

        // Check if user has this plan
        $current_user_id = get_current_user_id();
        $is_logged_in = is_user_logged_in();
        $current_plan = false;

        if ($is_logged_in) {
            $user_subscription = $this->get_subscriptions()->get_user_subscription($current_user_id);
            $current_plan = $user_subscription && $user_subscription->plan_id === $post_id;
        }

        // Build the output
        ob_start();
        ?>
        <div class="plan-action">
            <?php if (!$is_logged_in): ?>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="button login-to-subscribe">
                    <?php echo esc_html__('Login to Subscribe', 'cobra-ai'); ?>
                </a>
                <p class="login-note">
                    <?php echo esc_html__('Don\'t have an account?', 'cobra-ai'); ?>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>">
                        <?php echo esc_html__('Sign up', 'cobra-ai'); ?>
                    </a>
                </p>
            <?php elseif ($current_plan): ?>
                <div class="current-plan-notice">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php echo esc_html__('You\'re currently on this plan', 'cobra-ai'); ?>
                </div>
                <a href="<?php echo esc_url(get_permalink(get_option('cobra_ai_account_page'))); ?>" class="button secondary">
                    <?php echo esc_html__('Manage Subscription', 'cobra-ai'); ?>
                </a>
            <?php else: ?>
                <button class="button subscribe-button"
                    data-plan-id="<?php echo esc_attr($post_id); ?>"
                    data-price-id="<?php echo esc_attr($stripe_price_id); ?>">
                    <?php echo esc_html__('Subscribe Now', 'cobra-ai'); ?>
                </button>
                <?php if ($plan_data['trial_enabled']): ?>
                    <p class="trial-note">
                        <?php printf(
                            esc_html__('Start your %d-day free trial today', 'cobra-ai'),
                            absint($plan_data['trial_days'])
                        ); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($is_logged_in && !$current_plan): ?>
            <script>
                jQuery(document).ready(function($) {
                    $('.subscribe-button').on('click', async function() {
                        const button = $(this);
                        const planId = button.data('plan-id');
                        const priceId = button.data('price-id');

                        // Visual feedback
                        button.prop('disabled', true).addClass('processing')
                            .html('<span class="spinner"></span> <?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

                        try {
                            const response = await $.ajax({
                                url: CobraSubscription.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'cobra_create_checkout_session',
                                    plan_id: planId,
                                    price_id: priceId,
                                    nonce: CobraSubscription.nonce
                                }
                            });

                            if (!response.success) {
                                throw new Error(response.data.message || '<?php echo esc_js(__('Checkout failed', 'cobra-ai')); ?>');
                            }

                            // Redirect to checkout
                            window.location.href = response.data.checkout_url;

                        } catch (error) {
                            console.error('Checkout error:', error);

                            // Show error to user
                            alert(error.message || '<?php echo esc_js(__('Failed to process subscription', 'cobra-ai')); ?>');

                            // Reset button
                            button.prop('disabled', false).removeClass('processing')
                                .text('<?php echo esc_js(__('Subscribe Now', 'cobra-ai')); ?>');
                        }
                    });
                });
            </script>
        <?php endif; ?>
<?php

        return ob_get_clean();
    }
    public function handle_create_checkout_session(): void
    {
        try {
            // Verify nonce
            check_ajax_referer('cobra-stripe-nonce', 'nonce');

            // Check user authentication
            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in to subscribe.', 'cobra-ai'));
            }

            // Get and validate plan data
            $plan_id = absint($_POST['plan_id']);
            $price_id = sanitize_text_field($_POST['price_id']);

            if (!$plan_id || !$price_id) {
                throw new \Exception(__('Invalid plan data provided.', 'cobra-ai'));
            }

            // Get plan details
            $plan = get_post($plan_id);
            if (!$plan || $plan->post_type !== 'stripe_plan') {
                throw new \Exception(__('Invalid subscription plan.', 'cobra-ai'));
            }

            // Get current user
            $user = wp_get_current_user();

            // Initialize Stripe
            $stripe = $this->get_stripe_feature()->get_api();

            // Get or create Stripe Customer
            $stripe_customer_id = get_user_meta($user->ID, '_stripe_customer_id', true);
            if (!$stripe_customer_id) {
                $customer = \Stripe\Customer::create([
                    'email' => $user->user_email,
                    'name' => $user->display_name,
                    'metadata' => [
                        'user_id' => $user->ID,
                        'wordpress_user' => $user->user_login
                    ]
                ]);
                $stripe_customer_id = $customer->id;
                update_user_meta($user->ID, '_stripe_customer_id', $stripe_customer_id);
            }

            // Build success and cancel URLs
            $success_url = add_query_arg([
                'session_id' => '{CHECKOUT_SESSION_ID}',
                'plan_id' => $plan_id
            ], get_permalink($this->get_settings('success_page')));

            $cancel_url = get_permalink($plan_id);

            // Get trial period settings
            $trial_enabled = get_post_meta($plan_id, '_trial_enabled', true);
            $trial_days = absint(get_post_meta($plan_id, '_trial_days', true));

            // Create Stripe Checkout Session
            $session_params = [
                'customer' => $stripe_customer_id,
                'line_items' => [[
                    'price' => $price_id,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'customer_update' => [
                    'address' => 'auto',
                    'name' => 'auto'
                ],
                'client_reference_id' => $user->ID . '_' . $plan_id,
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => $user->ID,
                        'plan_id' => $plan_id,
                        'plan_name' => $plan->post_title
                    ]
                ]
            ];

            // Add trial period if enabled
            if ($trial_enabled && $trial_days > 0) {
                $session_params['subscription_data']['trial_period_days'] = $trial_days;
            }

            // Create the session
            $session = \Stripe\Checkout\Session::create($session_params);

            // Log session creation
            $this->log('info', 'Checkout session created', [
                'user_id' => $user->ID,
                'plan_id' => $plan_id,
                'session_id' => $session->id
            ]);

            // Return session URL
            wp_send_json_success([
                'checkout_url' => $session->url
            ]);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to create checkout session', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id(),
                'plan_id' => $_POST['plan_id'] ?? null
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle requests from logged-out users
     */
    public function handle_logged_out_user(): void
    {
        wp_send_json_error([
            'message' => __('Please log in to subscribe.', 'cobra-ai'),
            'code' => 'login_required'
        ]);
    }

    /**
     * Handle subscription cancellation
     */
    public function handle_cancel_subscription(): void
    {
        try {
            // Check if user is logged in
            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in to manage subscriptions.', 'cobra-ai'));
            }

            // Verify nonce
            check_ajax_referer('cobra-stripe-nonce', 'nonce');

            // Get subscription ID
            $subscription_id = sanitize_text_field($_POST['subscription_id'] ?? '');
            if (empty($subscription_id)) {
                throw new \Exception(__('Invalid subscription ID.', 'cobra-ai'));
            }

            // Get user's subscription from database
            $user_subscription = $this->get_subscriptions()->get_user_subscription(get_current_user_id());
            if (!$user_subscription || $user_subscription->subscription_id !== $subscription_id) {
                throw new \Exception(__('Subscription not found or does not belong to you.', 'cobra-ai'));
            }

            // Initialize Stripe
            $stripe = $this->get_stripe_feature()->get_api();

            // Get cancellation type (immediate or at period end)
            $cancel_immediately = !empty($_POST['cancel_immediately']);
            $cancel_reason = sanitize_text_field($_POST['cancel_reason'] ?? '');

            if ($cancel_immediately) {
                // Cancel immediately
                $subscription = \Stripe\Subscription::update($subscription_id, [
                    'cancel_at_period_end' => false,
                    'metadata' => [
                        'cancelled_by' => 'user',
                        'cancel_reason' => $cancel_reason
                    ]
                ]);
                \Stripe\Subscription::cancel($subscription_id);
                
                $message = __('Your subscription has been cancelled immediately.', 'cobra-ai');
            } else {
                // Cancel at period end
                $subscription = \Stripe\Subscription::update($subscription_id, [
                    'cancel_at_period_end' => true,
                    'metadata' => [
                        'cancelled_by' => 'user',
                        'cancel_reason' => $cancel_reason
                    ]
                ]);
                
                $period_end = date_i18n(get_option('date_format'), $subscription->current_period_end);
                $message = sprintf(
                    __('Your subscription will be cancelled at the end of the current billing period (%s).', 'cobra-ai'),
                    $period_end
                );
            }

            // Update local database
            $this->get_subscriptions()->update_subscription($user_subscription->id, [
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'cancel_reason' => $cancel_reason,
                'status' => $subscription->status
            ]);

            // Log the cancellation
            $this->log('info', 'Subscription cancelled by user', [
                'user_id' => get_current_user_id(),
                'subscription_id' => $subscription_id,
                'cancel_immediately' => $cancel_immediately,
                'cancel_reason' => $cancel_reason
            ]);

            wp_send_json_success([
                'message' => $message,
                'cancelled' => true,
                'cancel_at_period_end' => $subscription->cancel_at_period_end
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id(),
                'subscription_id' => $_POST['subscription_id'] ?? null
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle subscription resumption
     */
    public function handle_resume_subscription(): void
    {
        try {
            // Check if user is logged in
            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in to manage subscriptions.', 'cobra-ai'));
            }

            // Verify nonce
            check_ajax_referer('cobra-stripe-nonce', 'nonce');

            // Get subscription ID
            $subscription_id = sanitize_text_field($_POST['subscription_id'] ?? '');
            if (empty($subscription_id)) {
                throw new \Exception(__('Invalid subscription ID.', 'cobra-ai'));
            }

            // Get user's subscription from database
            $user_subscription = $this->get_subscriptions()->get_user_subscription(get_current_user_id());
            if (!$user_subscription || $user_subscription->subscription_id !== $subscription_id) {
                throw new \Exception(__('Subscription not found or does not belong to you.', 'cobra-ai'));
            }

            // Initialize Stripe
            $stripe = $this->get_stripe_feature()->get_api();

            // Resume subscription (remove cancel_at_period_end)
            $subscription = \Stripe\Subscription::update($subscription_id, [
                'cancel_at_period_end' => false,
                'metadata' => [
                    'resumed_by' => 'user',
                    'resumed_at' => date('Y-m-d H:i:s')
                ]
            ]);

            // Update local database
            $this->get_subscriptions()->update_subscription($user_subscription->id, [
                'cancel_at_period_end' => false,
                'cancel_reason' => null,
                'status' => $subscription->status
            ]);

            // Log the resumption
            $this->log('info', 'Subscription resumed by user', [
                'user_id' => get_current_user_id(),
                'subscription_id' => $subscription_id
            ]);

            wp_send_json_success([
                'message' => __('Your subscription has been resumed successfully.', 'cobra-ai'),
                'cancelled' => false
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Failed to resume subscription', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id(),
                'subscription_id' => $_POST['subscription_id'] ?? null
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle payment method update
     */
    public function handle_update_payment_method(): void
    {
        try {
            // Check if user is logged in
            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in to update payment methods.', 'cobra-ai'));
            }

            // Verify nonce
            check_ajax_referer('cobra-stripe-nonce', 'nonce');

            // Get current user
            $user = wp_get_current_user();
            
            // Get Stripe customer ID
            $stripe_customer_id = get_user_meta($user->ID, '_stripe_customer_id', true);
            if (!$stripe_customer_id) {
                throw new \Exception(__('No customer record found.', 'cobra-ai'));
            }

            // Initialize Stripe
            $stripe = $this->get_stripe_feature()->get_api();

            // Create billing portal session for payment method update
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $stripe_customer_id,
                'return_url' => get_permalink($this->get_settings('account_page'))
            ]);

            wp_send_json_success([
                'portal_url' => $session->url
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Failed to create billing portal session', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle AJAX page creation
     */
    public function handle_create_page(): void
    {
        try {
            // Verify nonce
            if (!check_ajax_referer('cobra_create_stripe_page', 'nonce', false)) {
                throw new \Exception(__('Invalid security token.', 'cobra-ai'));
            }

            // Verify permissions
            if (!current_user_can('publish_pages')) {
                throw new \Exception(__('You do not have permission to create pages.', 'cobra-ai'));
            }

            // Get and validate data
            $page_type = sanitize_key($_POST['page_type'] ?? '');
            $page_title = sanitize_text_field($_POST['page_title'] ?? '');
            $page_content = wp_kses_post($_POST['page_content'] ?? '');

            if (!$page_type || !$page_title || !$page_content) {
                throw new \Exception(__('Missing required data.', 'cobra-ai'));
            }

            // Create page
            $page_id = wp_insert_post([
                'post_title' => $page_title,
                'post_content' => $page_content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id(),
                'meta_input' => [
                    '_wp_page_template' => 'default'
                ]
            ]);

            if (is_wp_error($page_id)) {
                throw new \Exception($page_id->get_error_message());
            }

            // Update settings
            $settings = $this->get_settings();
            $settings[$page_type] = $page_id;
            $this->update_settings($settings);

            // Log the page creation
            $this->log('info', 'Stripe page created automatically', [
                'page_type' => $page_type,
                'page_id' => $page_id,
                'page_title' => $page_title,
                'user_id' => get_current_user_id()
            ]);

            wp_send_json_success([
                'message' => __('Page created successfully.', 'cobra-ai'),
                'page_id' => $page_id,
                'page_title' => $page_title,
                'edit_url' => admin_url('post.php?post=' . $page_id . '&action=edit'),
                'view_url' => get_permalink($page_id)
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
}
