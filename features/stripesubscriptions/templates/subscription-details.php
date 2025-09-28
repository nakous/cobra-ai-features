<?php
// templates/shortcodes/subscription-details.php
if (!defined('ABSPATH')) exit;

// echo '<h2>' . esc_html__('Subscription Details', 'cobra-ai') . '</h2>';
// Get user's subscription
$subscription = $this->get_subscriptions()->get_user_subscription(get_current_user_id());
if (!$subscription) {
    echo '<p>' . esc_html__('No active subscription found.', 'cobra-ai') . '</p>';
    return;
}

// Get plan details
$plan = $this->get_plans()->get_plan($subscription->plan_id);

$payments = $this->get_payments()->get_subscription_payments($subscription->id);

// Start output buffer

?>

<div class="cobra-subscription-details">
    <div class="subscription-header">
        <div class="row">
            <div class="col-md-8">
                <h2><?php echo esc_html__('Subscription Details', 'cobra-ai'); ?></h2>
            </div>
            <div class="col-md-4 text-end">
                <span class="status-badge status-<?php echo esc_attr($subscription->status); ?>">
                    <?php echo esc_html(ucfirst($subscription->status)); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                        <circle cx="12" cy="12" r="11" fill="#<?php echo esc_attr($subscription->status === 'active' ? '4CAF50' : 'F44336'); ?>" />
                        <path d="M8 8l8 8M8 16l8-8" stroke="#fff" stroke-width="2" fill="none" />
                    </svg>
                </span>
            </div>
        </div>
    </div>

    <div class="subscription-content">
        <!-- Plan Info -->
        <?php
        if ($plan) : ?>
            <div class="plan-info">
                <div class="row">
                    <div class="col-md-8">
                        <h3><?php echo esc_html($plan['title']); ?></h3>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="plan-price m-0 ">
                            <?php echo   $this->format_price($plan['amount'], trim($plan['currency'])); ?>
                        </div>
                    </div>
                </div>


                <?php if (!empty($plan['description'])): ?>
                    <p class="plan-description"><?php echo $plan['description']; ?></p>
                <?php endif; ?>



                <?php if ($subscription->cancel_at_period_end): ?>
                    <div class="cancellation-notice">
                        <?php echo sprintf(
                            esc_html__('Your subscription will end on %s', 'cobra-ai'),
                            date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
                        ); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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

                <?php /* if ($subscription->trial_days): ?>
                    <div class="billing-row">
                        <span class="label"><?php echo esc_html__('Trial Period:', 'cobra-ai'); ?></span>
                        <span class="value">
                            <?php echo sprintf(
                                esc_html__('Ends on %s', 'cobra-ai'),
                                date_i18n(get_option('date_format'), strtotime($subscription->trial_end))
                            ); ?>
                        </span>
                    </div>
                <?php endif; */  ?>
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

        <!-- TODO : Subscription Actions -->
        <!-- <div class="subscription-actions">
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
        </div> -->
    </div>
    <!-- Payments Tab -->
    <div id="payments" class="tab-content pt-3">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Date', 'cobra-ai'); ?></th>
                    <th><?php echo esc_html__('Amount', 'cobra-ai'); ?></th>
                    <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                    <th><?php echo esc_html__('Invoice', 'cobra-ai'); ?></th>
                    <!-- <th><?php echo esc_html__('Actions', 'cobra-ai'); ?></th> -->
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <?php echo esc_html(
                                    date_i18n(
                                        get_option('date_format'),
                                        strtotime($payment->created_at)
                                    )
                                ); ?>
                            </td>
                            <td>
                                <?php echo esc_html($this->format_price($payment->amount, $payment->currency)); ?>
                            </td>
                            <td>
                                <span class="payment-status status-<?php echo esc_attr($payment->status); ?>">
                                    <?php echo esc_html(ucfirst($payment->status)); ?>
                                </span>
                            </td>
                            <td>
                                <!-- <?php if ($payment->invoice_id): ?>
                                        <a href="#" class="view-invoice" data-id="<?php echo esc_attr($payment->invoice_id); ?>">
                                            <?php echo esc_html__('View Invoice', 'cobra-ai'); ?>
                                        </a>
                                    <?php endif; ?> -->
                                --
                            </td>
                            <!-- <td>
                                    <?php if ($payment->status === 'succeeded' && !$payment->refunded): ?>
                                        <button type="button" 
                                                class="button-secondary refund-payment"
                                                data-id="<?php echo esc_attr($payment->payment_id); ?>"
                                                data-amount="<?php echo esc_attr($payment->amount); ?>"
                                                data-nonce="<?php echo wp_create_nonce('refund_payment_' . $payment->id); ?>">
                                            <?php echo esc_html__('Refund', 'cobra-ai'); ?>
                                        </button>
                                    <?php elseif ($payment->refunded): ?>
                                        <span class="refunded">
                                            <?php echo esc_html__('Refunded', 'cobra-ai'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td > -->
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <?php echo esc_html__('No payments found', 'cobra-ai'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
// Include modals
// include dirname(__FILE__) . '/../modals/update-payment.php';
// include dirname(__FILE__) . '/../modals/cancel-subscription.php';
?>



<?php
