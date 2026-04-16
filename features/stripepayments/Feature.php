<?php

namespace CobraAI\Features\StripePayments;

use CobraAI\FeatureBase;

class Feature extends FeatureBase
{
    protected string $feature_id = 'stripepayments';
    protected string $name = 'Stripe Payments';
    protected string $description = 'One-time payments for generic products via Stripe Checkout';
    protected string $version = '1.0.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = false;
    protected bool $has_admin = true;
    protected array $requires = ['stripe'];

    private ?Orders $orders = null;
    private ?Checkout $checkout = null;
    private ?Webhooks $webhooks = null;
    private ?Emails $emails = null;
    private ?Admin $admin = null;
    private $stripe_feature = null;

        public function __construct()
    {
        parent::__construct();

        // Define feature tables

    }
    protected function setup(): void
    {
        try {
            global $wpdb;

            $this->tables = [
                'stripe_orders' => [
                    'name' => $wpdb->prefix . 'cobra_stripe_orders',
                    'schema' => [
                        'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                        'user_id' => 'bigint(20) NOT NULL',
                        'product_id' => 'bigint(20) NOT NULL',
                        'product_type' => 'varchar(50) NOT NULL DEFAULT "standard"',
                        'session_id' => 'varchar(255) DEFAULT NULL',
                        'payment_intent_id' => 'varchar(255) DEFAULT NULL',
                        'customer_id' => 'varchar(100) DEFAULT NULL',
                        'amount' => 'decimal(10,2) NOT NULL',
                        'currency' => 'varchar(3) NOT NULL DEFAULT "USD"',
                        'status' => "enum('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending'",
                        'metadata' => 'longtext',
                        'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                        'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                        'PRIMARY KEY' => '(id)',
                        'UNIQUE KEY' => 'session_id (session_id)',
                        'KEY' => [
                            'user_id' => '(user_id)',
                            'product_id' => '(product_id)',
                            'product_type' => '(product_type)',
                            'status' => '(status)',
                            'created_at' => '(created_at)',
                        ],
                    ],
                ],
            ];

            require_once __DIR__ . '/includes/Orders.php';
            require_once __DIR__ . '/includes/Checkout.php';
            require_once __DIR__ . '/includes/Webhooks.php';
            require_once __DIR__ . '/includes/Emails.php';
            require_once __DIR__ . '/includes/Admin.php';

            $this->orders   = $this->orders   ?? new Orders($this);
            $this->checkout = $this->checkout ?? new Checkout($this);
            $this->webhooks = $this->webhooks ?? new Webhooks($this);
            $this->emails   = $this->emails   ?? new Emails($this);
            $this->admin    = $this->admin    ?? new Admin($this);
        } catch (\Exception $e) {
            $this->log('error', 'Failed to setup Stripe Payments feature: ' . $e->getMessage());
        }
    }

    protected function init_hooks(): void
    {
        parent::init_hooks();

        // AJAX: create checkout session
        add_action('wp_ajax_cobra_stripepayments_checkout', [$this->checkout, 'ajax_create_session']);
        add_action('wp_ajax_nopriv_cobra_stripepayments_checkout', [$this->checkout, 'ajax_logged_out']);

        // Webhook events (dispatched by the stripe core feature via StripeEvents::dispatch)
        add_action('cobra_ai_stripe_checkout_session_completed', [$this->webhooks, 'handle_session_completed']);
        add_action('cobra_ai_stripe_checkout_session_expired',   [$this->webhooks, 'handle_session_expired']);
        add_action('cobra_ai_stripe_payment_intent_succeeded',   [$this->webhooks, 'handle_payment_succeeded']);
        add_action('cobra_ai_stripe_payment_intent_payment_failed', [$this->webhooks, 'handle_payment_failed']);
        add_action('cobra_ai_stripe_charge_refunded',            [$this->webhooks, 'handle_charge_refunded']);

        // Email notifications
        $this->emails->init();

        // AJAX: create success/cancel pages from settings
        add_action('wp_ajax_cobra_sp_create_page', [$this, 'ajax_create_page']);
    }

    /**
     * AJAX handler to auto-create the success or cancel page with the appropriate shortcode.
     */
    public function ajax_create_page(): void
    {
        check_ajax_referer('cobra_sp_create_page', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'cobra-ai')]);
        }

        $type = sanitize_key($_POST['page_type'] ?? '');
        if (!in_array($type, ['success', 'cancel'], true)) {
            wp_send_json_error(['message' => __('Invalid page type.', 'cobra-ai')]);
        }

        $pages_config = [
            'success' => [
                'title'     => __('Payment successful', 'cobra-ai'),
                'shortcode' => '[stripe_payment_success]',
                'setting'   => 'success_page',
            ],
            'cancel' => [
                'title'     => __('Payment cancelled', 'cobra-ai'),
                'shortcode' => '[stripe_payment_cancel]',
                'setting'   => 'cancel_page',
            ],
        ];

        $config = $pages_config[$type];

        $page_id = wp_insert_post([
            'post_title'   => $config['title'],
            'post_content' => $config['shortcode'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);

        if (is_wp_error($page_id)) {
            wp_send_json_error(['message' => $page_id->get_error_message()]);
        }

        // Save in feature settings
        $settings = $this->get_settings();
        $settings[$config['setting']] = $page_id;
        $this->update_settings($settings);

        wp_send_json_success([
            'page_id'  => $page_id,
            'edit_url' => get_edit_post_link($page_id, 'raw'),
            'title'    => get_the_title($page_id),
        ]);
    }

    protected function register_shortcodes(): void
    {
        add_shortcode('stripe_product',         [$this, 'render_product_shortcode']);
        add_shortcode('stripe_products',        [$this, 'render_products_shortcode']);
        add_shortcode('stripe_payment_success', [$this, 'render_success_shortcode']);
        add_shortcode('stripe_payment_cancel',  [$this, 'render_cancel_shortcode']);
    }

    public function render_success_shortcode($atts = []): string
    {
        $session_id = sanitize_text_field($_GET['session_id'] ?? '');
        $order = null;
        if ($session_id && $this->orders) {
            $order = $this->orders->get_by_session($session_id);
            if ($order && is_user_logged_in() && (int) $order->user_id !== get_current_user_id()) {
                $order = null;
            }
        }
        ob_start();
        $feature = $this;
        include $this->path . 'views/public/success.php';
        return ob_get_clean();
    }

    public function render_cancel_shortcode($atts = []): string
    {
        ob_start();
        include $this->path . 'views/public/cancel.php';
        return ob_get_clean();
    }

    public function render_product_shortcode($atts = []): string
    {
        $atts = shortcode_atts(['id' => 0], $atts);
        $product_id = absint($atts['id']);
        if (!$product_id || get_post_type($product_id) !== 'stripe_product') {
            return '';
        }
        ob_start();
        $product = get_post($product_id);
        $feature = $this;
        include $this->path . 'views/public/product-card.php';
        return ob_get_clean();
    }

    public function render_products_shortcode($atts = []): string
    {
        $atts = shortcode_atts([
            'type' => '',
            'limit' => 12,
            'columns' => 3,
        ], $atts);

        $meta_query = [];
        if (!empty($atts['type'])) {
            $meta_query[] = [
                'key' => '_product_type',
                'value' => sanitize_key($atts['type']),
            ];
        }

        $products = get_posts([
            'post_type' => 'stripe_product',
            'posts_per_page' => max(1, (int) $atts['limit']),
            'post_status' => 'publish',
            'meta_query' => $meta_query,
        ]);

        ob_start();
        $columns = max(1, (int) $atts['columns']);
        $feature = $this;
        include $this->path . 'views/public/product-list.php';
        return ob_get_clean();
    }

    protected function get_feature_default_options(): array
    {
        return [
            'success_page' => 0,
            'cancel_page'  => 0,
            'email_notifications' => true,
            'currency_default' => 'USD',
        ];
    }

    protected function validate_settings(array $settings): array
    {
        $settings['success_page'] = absint($settings['success_page'] ?? 0);
        $settings['cancel_page']  = absint($settings['cancel_page'] ?? 0);
        $settings['email_notifications'] = !empty($settings['email_notifications']);
        $settings['currency_default'] = strtoupper(substr(sanitize_text_field($settings['currency_default'] ?? 'USD'), 0, 3));
        return $settings;
    }

    /**
     * Registered product types. Other features extend via the filter.
     * Each entry: type_slug => human label.
     */
    public function get_product_types(): array
    {
        $types = ['standard' => __('Standard product', 'cobra-ai')];
        return (array) apply_filters('cobra_stripepayments_product_types', $types);
    }

    public function get_orders(): ?Orders
    {
        return $this->orders;
    }

    public function get_checkout(): ?Checkout
    {
        return $this->checkout;
    }

    public function get_webhooks(): ?Webhooks
    {
        return $this->webhooks;
    }

    public function get_admin_handler(): ?Admin
    {
        return $this->admin;
    }

    /**
     * Lazy resolve the core Stripe feature from the registry.
     */
    public function get_stripe_feature(): ?\CobraAI\Features\Stripe\Feature
    {
        if ($this->stripe_feature === null) {
            $registry = $GLOBALS['cobra_ai'] ?? null;
            if ($registry instanceof \CobraAI\CobraAI) {
                $feature = $registry->get_feature('stripe');
                if ($feature instanceof \CobraAI\Features\Stripe\Feature) {
                    $this->stripe_feature = $feature;
                }
            }
        }
        return $this->stripe_feature;
    }
}
