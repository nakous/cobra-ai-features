<?php

namespace CobraAI\Features\StripeSubscriptions;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\PaymentMethod;
use Stripe\PaymentIntent;
use Stripe\Price;
use Stripe\Product;
use Stripe\Exception\ApiErrorException;

class API
{
    private $feature;
    private $stripe_api;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
        $this->stripe_api = $feature->get_stripe_feature()->get_api();
    }

    /**
     * Create a new subscription
     */
    public function create_subscription(array $data): array
    {
        try {
            $this->validate_subscription_data($data);

            // Get or create customer
            $customer = $this->get_or_create_customer($data);

            // Attach payment method to customer
            if (!empty($data['payment_method'])) {
                $this->attach_payment_method($customer->id, $data['payment_method']);
            }

            // Create subscription
            $subscription_data = [
                'customer' => $customer->id,
                'items' => [['price' => $data['price_id']]],
                'expand' => ['latest_invoice.payment_intent'],
            ];

            // Add trial if enabled
            if ($this->feature->get_settings('enable_trial')) {
                $trial_days = $this->feature->get_settings('trial_days', 14);
                $subscription_data['trial_period_days'] = $trial_days;
            }

            // Allow data modification
            $subscription_data = apply_filters('cobra_ai_subscription_args', $subscription_data, $data);

            $subscription = Subscription::create($subscription_data);

            // Store subscription in database
            $this->store_subscription($subscription, $data['user_id']);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null,
            ];
        } catch (ApiErrorException $e) {
            $this->log_error('Subscription creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancel_subscription(string $subscription_id, bool $immediately = false): array
    {
        try {
            $subscription = Subscription::retrieve($subscription_id);

            if ($immediately) {
                $subscription->cancel();
            } else {
                $subscription->cancel_at_period_end = true;
                $subscription->save();
            }

            // Update local subscription record
            $this->update_subscription_status($subscription);

            return [
                'success' => true,
                'subscription' => $subscription
            ];
        } catch (ApiErrorException $e) {
            $this->log_error('Subscription cancellation failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription_id
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update subscription payment method
     */
    public function update_payment_method(string $subscription_id, string $payment_method_id): array
    {
        try {
            $subscription = Subscription::retrieve($subscription_id);

            // Attach payment method to customer
            $this->attach_payment_method($subscription->customer, $payment_method_id);

            // Update subscription default payment method
            $subscription->default_payment_method = $payment_method_id;
            $subscription->save();

            return [
                'success' => true,
                'subscription' => $subscription
            ];
        } catch (ApiErrorException $e) {
            $this->log_error('Payment method update failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription_id
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a new price for a plan
     */
    public function create_price(array $data): array
    {
        try {
            // Create or update product
            $product = $this->create_or_update_product($data);

            // Create price
            $price_data = [
                'product' => $product->id,
                'unit_amount' => $data['price'] * 100, // Convert to cents
                'currency' => $data['currency'],
                'recurring' => [
                    'interval' => $data['interval'],
                    'interval_count' => $data['interval_count']
                ]
            ];

            $price = Price::create($price_data);

            return [
                'success' => true,
                'price' => $price,
                'product' => $product
            ];
        } catch (ApiErrorException $e) {
            $this->log_error('Price creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create or update Stripe product
     */
    private function create_or_update_product(array $data): Product
    {
        $product_data = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'metadata' => [
                'plan_id' => $data['plan_id']
            ]
        ];

        if (!empty($data['product_id'])) {
            $product = Product::update($data['product_id'], $product_data);
        } else {
            $product = Product::create($product_data);
        }

        return $product;
    }

    /**
     * Get or create customer
     */
    private function get_or_create_customer(array $data): Customer
    {
        global $wpdb;

        // Check if customer already exists
        $customer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_id FROM {$this->feature->get_table('stripe_subscriptions')} 
             WHERE user_id = %d LIMIT 1",
            $data['user_id']
        ));

        if ($customer_id) {
            return Customer::retrieve($customer_id);
        }

        // Get user data
        $user = get_userdata($data['user_id']);

        // Create new customer
        return Customer::create([
            'email' => $user->user_email,
            'name' => $user->display_name,
            'metadata' => [
                'user_id' => $user->ID
            ]
        ]);
    }

    /**
     * Attach payment method to customer
     */
    private function attach_payment_method(string $customer_id, string $payment_method_id): void
    {
        $payment_method = PaymentMethod::retrieve($payment_method_id);
        $payment_method->attach(['customer' => $customer_id]);

        // Set as default payment method
        Customer::update($customer_id, [
            'invoice_settings' => [
                'default_payment_method' => $payment_method_id
            ]
        ]);
    }

    /**
     * Store subscription in database
     */
    private function store_subscription(Subscription $subscription, int $user_id): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->feature->get_table('stripe_subscriptions'),
            [
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer,
                'plan_id' => $subscription->items->data[0]->price->id,
                'user_id' => $user_id,
                'status' => $subscription->status,
                'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'cancel_at_period_end' => $subscription->cancel_at_period_end
            ]
        );
    }

    /**
     * Update subscription status in database
     */
    private function update_subscription_status(Subscription $subscription): void
    {
        global $wpdb;

        $wpdb->update(
            $this->feature->get_table('stripe_subscriptions'),
            [
                'status' => $subscription->status,
                'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'updated_at' => current_time('mysql')
            ],
            ['subscription_id' => $subscription->id]
        );
    }

    /**
     * Validate subscription data
     */
    private function validate_subscription_data(array $data): void
    {
        $required = ['user_id', 'price_id'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!empty($data['payment_method'])) {
            if (!preg_match('/^pm_/', $data['payment_method'])) {
                throw new \InvalidArgumentException('Invalid payment method ID');
            }
        }
    }

    /**
     * Handle checkout AJAX request
     */
    public function handle_checkout(): void
    {
        try {
            // Verify nonce
            check_ajax_referer('cobra_ai_stripe_checkout');

            // Verify user
            if (!is_user_logged_in()) {
                throw new \Exception('User must be logged in');
            }

            $data = [
                'user_id' => get_current_user_id(),
                'price_id' => sanitize_text_field($_POST['price_id']),
                'payment_method' => sanitize_text_field($_POST['payment_method'])
            ];

            $result = $this->create_subscription($data);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle cancellation AJAX request
     */
    public function handle_cancellation(): void
    {
        try {
            // Verify nonce
            check_ajax_referer('cobra_ai_stripe_cancel');

            // Verify user
            if (!is_user_logged_in()) {
                throw new \Exception('User must be logged in');
            }

            // Get subscription
            $subscription_id = sanitize_text_field($_POST['subscription_id']);

            // Verify ownership
            $this->verify_subscription_ownership($subscription_id, get_current_user_id());

            // Process cancellation
            $immediately = !empty($_POST['immediately']);
            $result = $this->cancel_subscription($subscription_id, $immediately);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verify subscription ownership
     */
    private function verify_subscription_ownership(string $subscription_id, int $user_id): void
    {
        global $wpdb;

        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->feature->get_table('stripe_subscriptions')} 
             WHERE subscription_id = %s AND user_id = %d",
            $subscription_id,
            $user_id
        ));

        if (!$subscription) {
            throw new \Exception('Subscription not found or access denied');
        }
    }

    /**
     * Log error
     */
    private function log_error(string $message, array $context = []): void
    {
        $this->feature->log('error', $message, $context);
    }

    // Get all invoices for subscription
    public function get_invoices($stripe_subscription_id)
    {
        $invoices = \Stripe\Invoice::all([
            'subscription' => $stripe_subscription_id,
            'limit' => 100
        ]);

        return $invoices;
    }

    public function get_payment_intent($payment_intent)
    {
        $intent = PaymentIntent::retrieve($payment_intent);
        return $intent;
    }
}
