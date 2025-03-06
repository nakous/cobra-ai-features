<?php
// templates/shortcodes/subscription-details.php
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    return sprintf(
        '<p class="cobra-notice">%s <a href="%s">%s</a></p>',
        esc_html__('Please', 'cobra-ai'),
        esc_url(wp_login_url(get_permalink())),
        esc_html__('login to view your subscription', 'cobra-ai')
    );
}

// Get user's subscription
$subscription = $this->get_user_subscription(get_current_user_id());
if (!$subscription) {
    return sprintf(
        '<p class="cobra-notice">%s <a href="%s">%s</a></p>',
        esc_html__('You don\'t have an active subscription.', 'cobra-ai'),
        esc_url(get_permalink($this->get_settings('plans_page'))),
        esc_html__('View available plans', 'cobra-ai')
    );
}

// Get plan details
$plan = $this->get_plan($subscription->plan_id);

// Start output buffer
ob_start();
?>

<div class="cobra-subscription-details">
    <div class="subscription-header">
        <h2><?php echo esc_html__('Subscription Details', 'cobra-ai'); ?></h2>
        <span class="status-badge status-<?php echo esc_attr($subscription->status); ?>">
            <?php echo esc_html(ucfirst($subscription->status)); ?>
        </span>
    </div>

    <div class="subscription-content">
        <!-- Plan Info -->
        <div class="plan-info">
            <h3><?php echo esc_html($plan->name); ?></h3>
            <?php if (!empty($plan->description)): ?>
                <p class="plan-description"><?php echo wp_kses_post($plan->description); ?></p>
            <?php endif; ?>

            <div class="plan-price">
                <?php echo sprintf(
                    esc_html__('%s / %s', 'cobra-ai'),
                    esc_html($this->format_price($plan->amount)),
                    esc_html($plan->interval)
                ); ?>
            </div>

            <?php if ($subscription->cancel_at_period_end): ?>
                <div class="cancellation-notice">
                    <?php echo sprintf(
                        esc_html__('Your subscription will end on %s', 'cobra-ai'),
                        date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
                    ); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Billing Info -->
        <div class="billing-info">
            <h3><?php echo esc_html__('Billing Information', 'cobra-ai'); ?></h3>
            
            <div class="billing-details">
                <div class="billing-row">
                    <span class="label"><?php echo esc_html__('Next Payment:', 'cobra-ai'); ?></span>
                    <span class="value">
                        <?php if (!$subscription->cancel_at_period_end): ?>
                            <?php echo date_i18n(
                                get_option('date_format'),
                                strtotime($subscription->current_period_end)
                            ); ?>
                        <?php else: ?>
                            <?php echo esc_html__('None (Subscription ending)', 'cobra-ai'); ?>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="billing-row">
                    <span class="label"><?php echo esc_html__('Payment Method:', 'cobra-ai'); ?></span>
                    <span class="value">
                        <?php if (!empty($subscription->card_last4)): ?>
                            <span class="card-info">
                                <?php echo sprintf(
                                    esc_html__('Card ending in %s (expires %s/%s)', 'cobra-ai'),
                                    esc_html($subscription->card_last4),
                                    esc_html($subscription->card_exp_month),
                                    esc_html($subscription->card_exp_year)
                                ); ?>
                            </span>
                            <button type="button" class="button-link update-payment">
                                <?php echo esc_html__('Update', 'cobra-ai'); ?>
                            </button>
                        <?php else: ?>
                            <?php echo esc_html__('No payment method on file', 'cobra-ai'); ?>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($subscription->trial_end): ?>
                    <div class="billing-row">
                        <span class="label"><?php echo esc_html__('Trial Period:', 'cobra-ai'); ?></span>
                        <span class="value">
                            <?php echo sprintf(
                                esc_html__('Ends on %s', 'cobra-ai'),
                                date_i18n(get_option('date_format'), strtotime($subscription->trial_end))
                            ); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subscription Features -->
        <?php if (!empty($plan->features)): ?>
            <div class="subscription-features">
                <h3><?php echo esc_html__('Your Benefits', 'cobra-ai'); ?></h3>
                <ul class="feature-list">
                    <?php foreach (json_decode($plan->features) as $feature): ?>
                        <li>
                            <span class="feature-check">✓</span>
                            <?php echo esc_html($feature); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Subscription Actions -->
        <div class="subscription-actions">
            <?php if ($subscription->status === 'active'): ?>
                <?php if (!$subscription->cancel_at_period_end): ?>
                    <button type="button" class="button cancel-subscription"
                            data-id="<?php echo esc_attr($subscription->subscription_id); ?>"
                            data-nonce="<?php echo wp_create_nonce('cancel_subscription_' . $subscription->id); ?>">
                        <?php echo esc_html__('Cancel Subscription', 'cobra-ai'); ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="button resume-subscription"
                            data-id="<?php echo esc_attr($subscription->subscription_id); ?>"
                            data-nonce="<?php echo wp_create_nonce('resume_subscription_' . $subscription->id); ?>">
                        <?php echo esc_html__('Resume Subscription', 'cobra-ai'); ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Include modals
include dirname(__FILE__) . '/../modals/update-payment.php';
include dirname(__FILE__) . '/../modals/cancel-subscription.php';
?>

<script>
jQuery(document).ready(function($) {
    // Initialize modals and handlers
    initSubscriptionModals();
    initPaymentUpdate();
    initSubscriptionCancel();
});
</script>

<?php
return ob_get_clean();