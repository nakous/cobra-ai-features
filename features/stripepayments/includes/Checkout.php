<?php

namespace CobraAI\Features\StripePayments;

use Stripe\Checkout\Session as StripeCheckoutSession;

class Checkout
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        $this->feature = $feature;
    }

    /**
     * AJAX: create Stripe Checkout session in 'payment' mode.
     */
    public function ajax_create_session(): void
    {
        check_ajax_referer('cobra-stripepayments-nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'cobra-ai')]);
        }

        $product_id = absint($_POST['product_id'] ?? 0);
        $product    = get_post($product_id);
        if (!$product || $product->post_type !== 'stripe_product' || $product->post_status !== 'publish') {
            wp_send_json_error(['message' => __('Product not found.', 'cobra-ai')]);
        }

        try {
            $result = $this->create_session($product_id, get_current_user_id());
            wp_send_json_success($result);
        } catch (\Exception $e) {
            $this->feature->log('error', 'Checkout session creation failed: ' . $e->getMessage(), [
                'product_id' => $product_id,
            ]);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_logged_out(): void
    {
        wp_send_json_error([
            'message' => __('Please log in to make a purchase.', 'cobra-ai'),
            'redirect' => wp_login_url(wp_get_referer() ?: home_url()),
        ]);
    }

    /**
     * Core: build the Stripe Checkout session for a product and persist the pending order.
     */
    public function create_session(int $product_id, int $user_id): array
    {
        $stripe_feature = $this->feature->get_stripe_feature();
        if (!$stripe_feature || !$stripe_feature->has_api_keys()) {
            throw new \Exception(__('Stripe is not configured.', 'cobra-ai'));
        }

        // Make sure the Stripe SDK is configured with the right key for the current mode.
        $stripe_feature->init_stripe();

        $amount   = (float) get_post_meta($product_id, '_price_amount', true);
        $currency = strtolower(get_post_meta($product_id, '_price_currency', true) ?: $this->feature->get_settings('currency_default') ?: 'usd');
        $product_type = get_post_meta($product_id, '_product_type', true) ?: 'standard';
        $stripe_price_id = get_post_meta($product_id, '_stripe_price_id', true);

        if ($amount <= 0 && empty($stripe_price_id)) {
            throw new \Exception(__('Product price is not set.', 'cobra-ai'));
        }

        $settings = $this->feature->get_settings();
        $success_url = $this->build_url((int) ($settings['success_page'] ?? 0), [
            'session_id' => '{CHECKOUT_SESSION_ID}',
            'product_id' => $product_id,
        ]);
        $cancel_url = $this->build_url((int) ($settings['cancel_page'] ?? 0), [
            'product_id' => $product_id,
        ]);

        $line_item = $stripe_price_id
            ? ['price' => $stripe_price_id, 'quantity' => 1]
            : [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => get_the_title($product_id),
                        'description' => wp_strip_all_tags(get_post_field('post_content', $product_id)) ?: null,
                    ],
                    'unit_amount' => (int) round($amount * 100),
                ],
                'quantity' => 1,
            ];

        $session_args = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [$line_item],
            'success_url' => $success_url,
            'cancel_url'  => $cancel_url,
            'client_reference_id' => $user_id . '_' . $product_id,
            'metadata' => [
                'user_id' => $user_id,
                'product_id' => $product_id,
                'product_type' => $product_type,
            ],
        ];

        $user = get_userdata($user_id);
        if ($user && !empty($user->user_email)) {
            $session_args['customer_email'] = $user->user_email;
        }

        $session_args = apply_filters('cobra_stripepayments_checkout_args', $session_args, $product_id, $user_id);

        $session = StripeCheckoutSession::create($session_args);

        // Persist the pending order immediately so the webhook can find it later.
        $this->feature->get_orders()->create_pending([
            'user_id' => $user_id,
            'product_id' => $product_id,
            'product_type' => $product_type,
            'session_id' => $session->id,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'metadata' => ['session_url' => $session->url],
        ]);

        return [
            'session_id' => $session->id,
            'checkout_url' => $session->url,
        ];
    }

    private function build_url(int $page_id, array $args = []): string
    {
        $base = $page_id ? get_permalink($page_id) : home_url('/');
        return add_query_arg($args, $base ?: home_url('/'));
    }
}
