<?php
/**
 * Shortcode template: Checkout
 * 
 * Available variables:
 * @var array $atts Shortcode attributes
 * @var string $plan_id Plan ID
 * @var array $plan Plan data
 * @var string $nonce Nonce for security
 */

if (!defined('ABSPATH')) exit;

// Extract attributes
$plan_id = sanitize_text_field($atts['plan_id'] ?? '');
$redirect_success = esc_url($atts['success_url'] ?? '');
$redirect_cancel = esc_url($atts['cancel_url'] ?? '');
$button_text = sanitize_text_field($atts['button_text'] ?? __('Subscribe Now', 'cobra-ai'));

if (empty($plan_id)) {
    return '<div class="cobra-error">' . __('Plan ID is required.', 'cobra-ai') . '</div>';
}

// Get plan details
$plan = $this->plans->get_plan($plan_id);
if (!$plan) {
    return '<div class="cobra-error">' . __('Plan not found.', 'cobra-ai') . '</div>';
}

// Check if user is logged in
if (!is_user_logged_in()) {
    return '<div class="cobra-notice">
        <p>' . __('You must be logged in to subscribe.', 'cobra-ai') . '</p>
        <a href="' . wp_login_url(get_permalink()) . '" class="cobra-btn cobra-btn-primary">' . __('Login', 'cobra-ai') . '</a>
    </div>';
}

$user_id = get_current_user_id();
$existing_subscription = $this->Subscriptions->get_user_subscription($user_id, $plan_id);

if ($existing_subscription && in_array($existing_subscription->status, ['active', 'trialing'])) {
    return '<div class="cobra-notice">' . __('You already have an active subscription to this plan.', 'cobra-ai') . '</div>';
}

// Generate nonce
$nonce = wp_create_nonce('cobra_checkout_' . $plan_id);
?>

<div class="cobra-subscription-checkout" data-plan-id="<?php echo esc_attr($plan_id); ?>">
    <div class="cobra-plan-summary">
        <h3><?php echo esc_html($plan->name); ?></h3>
        <div class="cobra-plan-price">
            <span class="currency"><?php echo esc_html(strtoupper($plan->currency)); ?></span>
            <span class="amount"><?php echo esc_html(number_format($plan->amount / 100, 2)); ?></span>
            <span class="interval">/ <?php echo esc_html($plan->interval); ?></span>
        </div>
        <?php if (!empty($plan->description)): ?>
            <div class="cobra-plan-description">
                <?php echo wp_kses_post($plan->description); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($plan->trial_days) && $plan->trial_days > 0): ?>
            <div class="cobra-trial-info">
                <strong><?php printf(__('%d days free trial', 'cobra-ai'), $plan->trial_days); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <form id="cobra-checkout-form" class="cobra-checkout-form">
        <div class="cobra-form-group">
            <label for="payment-element"><?php _e('Payment Method', 'cobra-ai'); ?></label>
            <div id="payment-element" class="cobra-stripe-element">
                <!-- Stripe Elements will be inserted here -->
            </div>
        </div>

        <div class="cobra-form-actions">
            <button type="submit" id="cobra-checkout-submit" class="cobra-btn cobra-btn-primary cobra-btn-lg">
                <span class="button-text"><?php echo esc_html($button_text); ?></span>
                <span class="loading-spinner" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i> <?php _e('Processing...', 'cobra-ai'); ?>
                </span>
            </button>
        </div>

        <div id="cobra-payment-message" class="cobra-message" style="display: none;"></div>

        <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
        <input type="hidden" name="success_url" value="<?php echo esc_attr($redirect_success); ?>">
        <input type="hidden" name="cancel_url" value="<?php echo esc_attr($redirect_cancel); ?>">
        <input type="hidden" name="action" value="cobra_create_checkout_session">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
        <?php wp_nonce_field('cobra_checkout', 'cobra_checkout_nonce'); ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof CobraStripeCheckout !== 'undefined') {
        CobraStripeCheckout.init({
            form: '#cobra-checkout-form',
            submitButton: '#cobra-checkout-submit',
            messageContainer: '#cobra-payment-message'
        });
    }
});
</script>