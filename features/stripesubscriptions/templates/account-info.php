<?php
// templates/shortcodes/account-info.php
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    return sprintf(
        '<p class="cobra-notice">%s <a href="%s">%s</a></p>',
        esc_html__('Please', 'cobra-ai'),
        esc_url(wp_login_url(get_permalink())),
        esc_html__('login to view your account', 'cobra-ai')
    );
}

// Get user info
$user = wp_get_current_user();
$subscription = $this->get_user_subscription($user->ID);

// Get account summary
$account_summary = $this->get_account_summary($user->ID);

// Start output buffer
ob_start();
?>

<div class="cobra-account-info">
    <div class="account-header">
        <h2><?php echo esc_html__('Account Information', 'cobra-ai'); ?></h2>
    </div>

    <div class="account-content">
        <!-- Personal Information -->
        <div class="account-section">
            <h3><?php echo esc_html__('Personal Information', 'cobra-ai'); ?></h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label"><?php echo esc_html__('Name:', 'cobra-ai'); ?></span>
                    <span class="value"><?php echo esc_html($user->display_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><?php echo esc_html__('Email:', 'cobra-ai'); ?></span>
                    <span class="value"><?php echo esc_html($user->user_email); ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><?php echo esc_html__('Member Since:', 'cobra-ai'); ?></span>
                    <span class="value">
                        <?php echo date_i18n(
                            get_option('date_format'),
                            strtotime($user->user_registered)
                        ); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Subscription Information -->
        <?php if ($subscription): ?>
            <div class="account-section">
                <h3><?php echo esc_html__('Current Subscription', 'cobra-ai'); ?></h3>
                <div class="subscription-summary">
                    <?php $plan = $this->get_plan($subscription->plan_id); ?>
                    <div class="subscription-status">
                        <span class="status-label"><?php echo esc_html__('Status:', 'cobra-ai'); ?></span>
                        <span class="status-badge status-<?php echo esc_attr($subscription->status); ?>">
                            <?php echo esc_html(ucfirst($subscription->status)); ?>
                        </span>
                        <?php if ($subscription->cancel_at_period_end): ?>
                            <span class="cancellation-notice">
                                <?php echo sprintf(
                                    esc_html__('(Cancels on %s)', 'cobra-ai'),
                                    date_i18n(
                                        get_option('date_format'),
                                        strtotime($subscription->current_period_end)
                                    )
                                ); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($plan): ?>
                        <div class="plan-info">
                            <div class="plan-name"><?php echo esc_html($plan->name); ?></div>
                            <div class="plan-price">
                                <?php echo sprintf(
                                    esc_html__('%s / %s', 'cobra-ai'),
                                    esc_html($this->format_price($plan->amount)),
                                    esc_html($plan->interval)
                                ); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Next Payment Information -->
                    <?php if ($subscription->status === 'active' && !$subscription->cancel_at_period_end): ?>
                        <div class="next-payment">
                            <span class="label"><?php echo esc_html__('Next Payment:', 'cobra-ai'); ?></span>
                            <span class="value">
                                <?php echo sprintf(
                                    esc_html__('%s on %s', 'cobra-ai'),
                                    esc_html($this->format_price($plan->amount)),
                                    date_i18n(
                                        get_option('date_format'),
                                        strtotime($subscription->current_period_end)
                                    )
                                ); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Payment Method -->
                    <?php if (!empty($subscription->card_last4)): ?>
                        <div class="payment-method">
                            <span class="label"><?php echo esc_html__('Payment Method:', 'cobra-ai'); ?></span>
                            <span class="value">
                                <?php echo sprintf(
                                    esc_html__('Card ending in %s (expires %s/%s)', 'cobra-ai'),
                                    esc_html($subscription->card_last4),
                                    esc_html($subscription->card_exp_month),
                                    esc_html($subscription->card_exp_year)
                                ); ?>
                                <button type="button" class="button-link update-payment">
                                    <?php echo esc_html__('Update', 'cobra-ai'); ?>
                                </button>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Usage Summary -->
        <?php if (!empty($account_summary)): ?>
            <div class="account-section">
                <h3><?php echo esc_html__('Usage Summary', 'cobra-ai'); ?></h3>
                <div class="usage-grid">
                    <div class="usage-item">
                        <span class="label"><?php echo esc_html__('Total Spent:', 'cobra-ai'); ?></span>
                        <span class="value">
                            <?php echo esc_html($this->format_price($account_summary['total_spent'])); ?>
                        </span>
                    </div>
                    <?php if (isset($account_summary['active_since'])): ?>
                        <div class="usage-item">
                            <span class="label"><?php echo esc_html__('Active Since:', 'cobra-ai'); ?></span>
                            <span class="value">
                                <?php echo date_i18n(
                                    get_option('date_format'),
                                    strtotime($account_summary['active_since'])
                                ); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($account_summary['total_days'])): ?>
                        <div class="usage-item">
                            <span class="label"><?php echo esc_html__('Total Days:', 'cobra-ai'); ?></span>
                            <span class="value">
                                <?php echo sprintf(
                                    _n('%s day', '%s days', $account_summary['total_days'], 'cobra-ai'),
                                    number_format_i18n($account_summary['total_days'])
                                ); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Account Actions -->
        <div class="account-actions">
            <?php if ($subscription && $subscription->status === 'active'): ?>
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
            <?php elseif (!$subscription): ?>
                <a href="<?php echo esc_url(get_permalink($this->get_settings('plans_page'))); ?>"
                    class="button subscribe-button">
                    <?php echo esc_html__('View Available Plans', 'cobra-ai'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include required modals
if ($subscription) {
    include dirname(__FILE__) . '/../modals/update-payment.php';
    include dirname(__FILE__) . '/../modals/cancel-subscription.php';
}
?>

<script>
    jQuery(document).ready(function($) {
        if (typeof initSubscriptionModals === 'function') {
            initSubscriptionModals();
        }
    });
</script>

<?php
return ob_get_clean();
