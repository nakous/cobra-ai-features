<?php
// views/public/checkout.php
namespace CobraAI\Features\StripeSubscription\Views;

// Prevent direct access
defined('ABSPATH') || exit;

// Get Stripe public key
$stripe_key = $this->get_stripe_feature()->get_api()->get_publishable_key();
?>

<div class="cobra-checkout-wrapper">
    <div class="cobra-checkout-header">
        <h2><?php echo esc_html__('Complete Your Subscription', 'cobra-ai'); ?></h2>
    </div>

    <div class="cobra-checkout-content">
        <!-- Plan Summary -->
        <div class="plan-summary">
            <h3><?php echo esc_html($plan->name); ?></h3>
            <?php if (!empty($plan->description)): ?>
                <p class="plan-description"><?php echo wp_kses_post($plan->description); ?></p>
            <?php endif; ?>

            <div class="plan-price">
                <span class="amount">
                    <?php echo esc_html($this->format_currency($plan->amount)); ?>
                </span>
                <span class="interval">
                    / <?php echo esc_html($plan->interval); ?>
                </span>
            </div>

            <?php if (!empty($plan->features)): ?>
                <div class="plan-features">
                    <h4><?php echo esc_html__('Included Features:', 'cobra-ai'); ?></h4>
                    <ul>
                        <?php foreach ($plan->features as $feature): ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- Checkout Form -->
        <form id="payment-form" class="cobra-checkout-form">
            <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan->id); ?>">
            <?php wp_nonce_field('cobra_checkout_' . $plan->id); ?>

            <div class="form-row">
                <label for="card-element">
                    <?php echo esc_html__('Credit or debit card', 'cobra-ai'); ?>
                </label>
                <div id="card-element">
                    <!-- Stripe Card Element will be inserted here -->
                </div>
                <div id="card-errors" role="alert"></div>
            </div>

            <?php if ($this->get_settings('enable_trial')): ?>
                <div class="trial-notice">
                    <p>
                        <?php echo sprintf(
                            esc_html__('Your subscription will start with a %d-day free trial.', 'cobra-ai'),
                            $this->get_settings('trial_days', 14)
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <button type="submit" class="cobra-checkout-button">
                <?php echo sprintf(
                    esc_html__('Subscribe for %s', 'cobra-ai'),
                    $this->format_currency($plan->amount) . ' / ' . $plan->interval
                ); ?>
            </button>
        </form>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
jQuery(document).ready(function($) {
    // Initialize Stripe
    const stripe = Stripe('<?php echo esc_js($stripe_key); ?>');
    const elements = stripe.elements();

    // Create card element
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                fontSmoothing: 'antialiased',
                '::placeholder': {
                    color: '#aab7c4'
                }
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        }
    });

    // Mount card element
    cardElement.mount('#card-element');

    // Handle form submission
    const form = document.getElementById('payment-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>';

        try {
            // Create payment method
            const { paymentMethod, error } = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement
            });

            if (error) {
                throw error;
            }

            // Create subscription
            const response = await $.ajax({
                url: cobra_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_checkout',
                    payment_method: paymentMethod.id,
                    plan_id: form.querySelector('input[name="plan_id"]').value,
                    _ajax_nonce: form.querySelector('#_wpnonce').value
                }
            });

            if (!response.success) {
                throw new Error(response.data.message || '<?php echo esc_js(__('Subscription creation failed', 'cobra-ai')); ?>');
            }

            // Handle subscription activation
            if (response.data.client_secret) {
                const { error: confirmError } = await stripe.confirmCardPayment(response.data.client_secret);
                if (confirmError) {
                    throw confirmError;
                }
            }

            // Redirect to success page
            window.location.href = response.data.redirect;

        } catch (error) {
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = error.message;
            submitButton.disabled = false;
            submitButton.innerHTML = '<?php echo esc_js(__('Try Again', 'cobra-ai')); ?>';
        }
    });
});
</script>