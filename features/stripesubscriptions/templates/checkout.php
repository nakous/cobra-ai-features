<?php
// templates/shortcodes/checkout.php
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    return sprintf(
        '<p class="cobra-notice">%s <a href="%s">%s</a></p>',
        esc_html__('Please', 'cobra-ai'),
        esc_url(wp_login_url(get_permalink())),
        esc_html__('login to continue', 'cobra-ai')
    );
}

// Get plan ID from URL or shortcode attributes
$plan_id = sanitize_text_field($_GET['plan'] ?? $atts['plan'] ?? '');
if (empty($plan_id)) {
    return '<p class="cobra-notice">' . esc_html__('No plan selected.', 'cobra-ai') . '</p>';
}

// Get plan details
$plan = $this->get_plan($plan_id);
if (!$plan || $plan->status !== 'active') {
    return '<p class="cobra-notice">' . esc_html__('Selected plan is not available.', 'cobra-ai') . '</p>';
}

// Check if user already has an active subscription
$current_subscription = $this->get_user_subscription(get_current_user_id());
if ($current_subscription && $current_subscription->status === 'active') {
    return sprintf(
        '<p class="cobra-notice">%s <a href="%s">%s</a></p>',
        esc_html__('You already have an active subscription.', 'cobra-ai'),
        esc_url(get_permalink($this->get_settings('account_page'))),
        esc_html__('View your subscription', 'cobra-ai')
    );
}

// Get Stripe public key
$stripe_key = $this->get_stripe_feature()->get_api()->get_publishable_key();

// Start output buffer
ob_start();
?>

<div class="cobra-checkout">
    <div class="checkout-header">
        <h2><?php echo esc_html__('Complete Your Subscription', 'cobra-ai'); ?></h2>
    </div>

    <div class="checkout-content">
        <!-- Plan Summary -->
        <div class="plan-summary">
            <h3><?php echo esc_html($plan->name); ?></h3>
            <?php if (!empty($plan->description)): ?>
                <p class="plan-description"><?php echo wp_kses_post($plan->description); ?></p>
            <?php endif; ?>

            <div class="plan-details">
                <div class="price-details">
                    <span class="amount"><?php echo esc_html($this->format_price($plan->amount)); ?></span>
                    <span class="interval">/ <?php echo esc_html($plan->interval); ?></span>
                </div>

                <?php if ($this->get_settings('enable_trial')): ?>
                    <div class="trial-info">
                        <?php echo sprintf(
                            esc_html__('Includes %d-day free trial', 'cobra-ai'),
                            $this->get_settings('trial_days', 14)
                        ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Form -->
        <form id="cobra-payment-form" class="payment-form">
            <?php wp_nonce_field('cobra_checkout_' . $plan_id); ?>
            <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">

            <div class="form-row">
                <label for="card-element">
                    <?php echo esc_html__('Credit or debit card', 'cobra-ai'); ?>
                </label>
                <div id="card-element"></div>
                <div id="card-errors" role="alert"></div>
            </div>

            <?php if ($this->get_settings('enable_trial')): ?>
                <div class="trial-notice">
                    <p>
                        <?php echo sprintf(
                            esc_html__('Your card will not be charged during the %d-day trial period. You can cancel anytime.', 'cobra-ai'),
                            $this->get_settings('trial_days', 14)
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <button type="submit" class="cobra-submit">
                <?php echo esc_html__('Subscribe Now', 'cobra-ai'); ?>
            </button>
        </form>

        <div class="secure-checkout">
            <span class="secure-icon">🔒</span>
            <?php echo esc_html__('Secure checkout powered by Stripe', 'cobra-ai'); ?>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
jQuery(document).ready(function($) {
    // Initialize Stripe
    const stripe = Stripe('<?php echo esc_js($stripe_key); ?>');
    const elements = stripe.elements();

    // Create card element
    const cardElement = elements.create('card');
    cardElement.mount('#card-element');

    // Handle form submission
    const form = document.getElementById('cobra-payment-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('button');
        submitButton.disabled = true;
        submitButton.textContent = '<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>';

        try {
            // Create payment method
            const { paymentMethod, error } = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement
            });

            if (error) {
                throw new Error(error.message);
            }

            // Process subscription
            const response = await $.ajax({
                url: cobra_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_process',
                    payment_method: paymentMethod.id,
                    plan_id: '<?php echo esc_js($plan_id); ?>',
                    _wpnonce: '<?php echo wp_create_nonce('cobra_checkout_' . $plan_id); ?>'
                }
            });

            if (!response.success) {
                throw new Error(response.data.message || '<?php echo esc_js(__('Subscription creation failed', 'cobra-ai')); ?>');
            }

            // Handle subscription activation if needed
            if (response.data.requires_action) {
                const { error: confirmError } = await stripe.confirmCardPayment(response.data.client_secret);
                if (confirmError) {
                    throw new Error(confirmError.message);
                }
            }

            // Redirect to success page
            window.location.href = response.data.redirect;

        } catch (error) {
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = error.message;
            submitButton.disabled = false;
            submitButton.textContent = '<?php echo esc_js(__('Try Again', 'cobra-ai')); ?>';
        }
    });
});
</script>

<?php
return ob_get_clean();