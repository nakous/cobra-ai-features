<?php

namespace CobraAI\Features\Credits;

/**
 * Bridge between the Credits feature and the StripePayments feature.
 *
 * Registers a "credits" product type in StripePayments, adds credit-specific
 * meta fields to the product editor, and delivers credits to the buyer when
 * an order is marked as paid.
 *
 * Activated only when both features are active and the admin has enabled
 * the "Stripe credit purchase" integration in Credits settings.
 */
class StripePaymentsBridge
{
    private $feature;

    public function __construct(\CobraAI\Features\Credits\Feature $feature)
    {
        $this->feature = $feature;
    }

    /**
     * Register all hooks into stripepayments extension points.
     */
    public function register(): void
    {
        // Register "credits" as a product type
        add_filter('cobra_stripepayments_product_types', [$this, 'add_product_type']);

        // Extra meta-box fields when product type = credits
        add_action('cobra_stripepayments_product_meta_fields', [$this, 'render_meta_fields']);

        // Persist those fields on save
        add_action('cobra_stripepayments_save_product_meta', [$this, 'save_meta_fields'], 10, 2);

        // Deliver credits when the order is paid
        add_action('cobra_ai_order_paid', [$this, 'deliver_credits'], 10, 2);
    }

    /* ----------------------------------------------------------
     * Product type registration
     * ---------------------------------------------------------- */

    public function add_product_type(array $types): array
    {
        $types['credits'] = __('Credits', 'cobra-ai');
        return $types;
    }

    /* ----------------------------------------------------------
     * Meta-box fields (admin product editor)
     * ---------------------------------------------------------- */

    public function render_meta_fields(\WP_Post $post): void
    {
        $credit_amount = get_post_meta($post->ID, '_credits_amount', true);
        $credit_type   = get_post_meta($post->ID, '_credits_type', true) ?: 'paid';
        $expiration_days = get_post_meta($post->ID, '_credits_expiration_days', true);

        $active_types = CreditType::get_active();
        if (empty($active_types)) {
            $active_types = CreditType::get_all();
        }
        ?>
        <div data-product-type="credits" style="display:none;">
            <hr>
            <h4><?php _e('Credits configuration', 'cobra-ai'); ?></h4>
            <p>
                <label for="_credits_amount"><strong><?php _e('Number of credits', 'cobra-ai'); ?></strong></label><br>
                <input type="number" id="_credits_amount" name="_credits_amount"
                       value="<?php echo esc_attr($credit_amount); ?>"
                       min="1" step="1" style="width:120px;" required>
                <span class="description"><?php _e('Amount of credits delivered after payment.', 'cobra-ai'); ?></span>
            </p>
            <p>
                <label for="_credits_type"><strong><?php _e('Credit type', 'cobra-ai'); ?></strong></label><br>
                <select id="_credits_type" name="_credits_type" style="width:200px;">
                    <?php foreach ($active_types as $type_id => $type_info): ?>
                        <option value="<?php echo esc_attr($type_id); ?>"
                                <?php selected($credit_type, $type_id); ?>>
                            <?php echo esc_html($type_info['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="_credits_expiration_days"><strong><?php _e('Expiration (days)', 'cobra-ai'); ?></strong></label><br>
                <input type="number" id="_credits_expiration_days" name="_credits_expiration_days"
                       value="<?php echo esc_attr($expiration_days); ?>"
                       min="0" step="1" style="width:120px;">
                <span class="description"><?php _e('0 or empty = uses the default duration of the credit type.', 'cobra-ai'); ?></span>
            </p>
        </div>
        <?php
    }

    /* ----------------------------------------------------------
     * Persist meta fields
     * ---------------------------------------------------------- */

    public function save_meta_fields(int $post_id, array $data): void
    {
        if (($data['_product_type'] ?? '') !== 'credits') {
            return;
        }

        $amount = absint($data['_credits_amount'] ?? 0);
        $type   = sanitize_key($data['_credits_type'] ?? 'paid');
        $days   = absint($data['_credits_expiration_days'] ?? 0);

        update_post_meta($post_id, '_credits_amount', $amount);
        update_post_meta($post_id, '_credits_type', $type);
        update_post_meta($post_id, '_credits_expiration_days', $days);
    }

    /* ----------------------------------------------------------
     * Deliver credits on successful payment
     * ---------------------------------------------------------- */

    /**
     * @param array  $order_data  Order fields from the cobra_stripe_orders row.
     * @param object $event       The Stripe event object (or null).
     */
    public function deliver_credits(array $order_data, $event = null): void
    {
        if (($order_data['product_type'] ?? '') !== 'credits') {
            return;
        }

        $product_id = (int) ($order_data['product_id'] ?? 0);
        $user_id    = (int) ($order_data['user_id'] ?? 0);

        if (!$product_id || !$user_id) {
            $this->feature->log('error', 'StripePaymentsBridge: missing product_id or user_id in order data');
            return;
        }

        $credit_amount = (float) get_post_meta($product_id, '_credits_amount', true);
        $credit_type   = get_post_meta($product_id, '_credits_type', true) ?: 'paid';
        $expiration_days = absint(get_post_meta($product_id, '_credits_expiration_days', true));

        if ($credit_amount <= 0) {
            $this->feature->log('error', sprintf(
                'StripePaymentsBridge: product #%d has no _credits_amount set',
                $product_id
            ));
            return;
        }

        // Calculate expiration date
        $expiration = null;
        if ($expiration_days > 0) {
            $expiration = date('Y-m-d H:i:s', strtotime("+{$expiration_days} days"));
        }

        $order_id = $order_data['id'] ?? 0;
        $comment  = sprintf(
            __('Stripe purchase — Order #%d (%s)', 'cobra-ai'),
            $order_id,
            get_the_title($product_id)
        );

        $manager = $this->feature->manager;
        if (!$manager) {
            $this->feature->log('error', 'StripePaymentsBridge: CreditManager not available');
            return;
        }

        $credit_id = $manager->add_credit($user_id, $credit_amount, $credit_type, [
            'comment'         => $comment,
            'expiration_date' => $expiration,
            'type_id'         => 'stripe_order_' . $order_id,
            'meta'            => [
                'source'     => 'stripepayments',
                'order_id'   => $order_id,
                'product_id' => $product_id,
            ],
        ]);

        if ($credit_id) {
            $this->feature->log('info', sprintf(
                'StripePaymentsBridge: delivered %.2f %s credits to user #%d (order #%d)',
                $credit_amount,
                $credit_type,
                $user_id,
                $order_id
            ));

            do_action('cobra_ai_credits_purchased', $credit_id, $user_id, $credit_amount, $order_data);
        } else {
            $this->feature->log('error', sprintf(
                'StripePaymentsBridge: failed to add credits for user #%d (order #%d)',
                $user_id,
                $order_id
            ));
        }
    }
}
