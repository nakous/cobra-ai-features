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

        <!-- Subscription Actions -->
        <div class="subscription-actions">
            <h3><?php echo esc_html__('Manage Your Subscription', 'cobra-ai'); ?></h3>
            
            <?php if ($subscription->status === 'active'): ?>
                <?php if (!$subscription->cancel_at_period_end): ?>
                    <div class="action-group">
                        <button type="button" class="button button-secondary cancel-subscription"
                            data-subscription-id="<?php echo esc_attr($subscription->subscription_id); ?>"
                            data-period-end="<?php echo esc_attr(date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))); ?>">
                            <?php echo esc_html__('Cancel Subscription', 'cobra-ai'); ?>
                        </button>
                        <p class="action-description">
                            <?php echo esc_html__('You can cancel anytime. Your subscription will remain active until the end of your current billing period.', 'cobra-ai'); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="action-group">
                        <button type="button" class="button button-primary resume-subscription"
                            data-subscription-id="<?php echo esc_attr($subscription->subscription_id); ?>">
                            <?php echo esc_html__('Resume Subscription', 'cobra-ai'); ?>
                        </button>
                        <p class="action-description">
                            <?php echo esc_html__('Resume your subscription to continue enjoying all benefits.', 'cobra-ai'); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="action-group">
                    <button type="button" class="button update-payment-method">
                        <?php echo esc_html__('Update Payment Method', 'cobra-ai'); ?>
                    </button>
                    <p class="action-description">
                        <?php echo esc_html__('Update your credit card or payment information.', 'cobra-ai'); ?>
                    </p>
                </div>
            <?php elseif ($subscription->status === 'past_due'): ?>
                <div class="action-group">
                    <button type="button" class="button button-primary update-payment-method">
                        <?php echo esc_html__('Update Payment Method', 'cobra-ai'); ?>
                    </button>
                    <p class="action-description" style="color: #d63638;">
                        <?php echo esc_html__('Your payment failed. Please update your payment method to resume service.', 'cobra-ai'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
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

<!-- Cancellation Modal -->
<div id="cancel-subscription-modal" class="cobra-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo esc_html__('Cancel Subscription', 'cobra-ai'); ?></h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <p><?php echo esc_html__('Are you sure you want to cancel your subscription?', 'cobra-ai'); ?></p>
            
            <div class="cancel-options">
                <label class="radio-option">
                    <input type="radio" name="cancel_type" value="end_period" checked>
                    <span><?php echo esc_html__('Cancel at the end of billing period', 'cobra-ai'); ?></span>
                    <small class="period-end-date"></small>
                </label>
                <label class="radio-option">
                    <input type="radio" name="cancel_type" value="immediately">
                    <span><?php echo esc_html__('Cancel immediately', 'cobra-ai'); ?></span>
                    <small><?php echo esc_html__('You will lose access right away and no refund will be provided.', 'cobra-ai'); ?></small>
                </label>
            </div>
            
            <div class="cancel-reason">
                <label for="cancel_reason"><?php echo esc_html__('Reason for cancellation (optional):', 'cobra-ai'); ?></label>
                <select id="cancel_reason" name="cancel_reason">
                    <option value=""><?php echo esc_html__('Select a reason', 'cobra-ai'); ?></option>
                    <option value="too_expensive"><?php echo esc_html__('Too expensive', 'cobra-ai'); ?></option>
                    <option value="not_using"><?php echo esc_html__('Not using enough', 'cobra-ai'); ?></option>
                    <option value="missing_features"><?php echo esc_html__('Missing features', 'cobra-ai'); ?></option>
                    <option value="technical_issues"><?php echo esc_html__('Technical issues', 'cobra-ai'); ?></option>
                    <option value="found_alternative"><?php echo esc_html__('Found an alternative', 'cobra-ai'); ?></option>
                    <option value="other"><?php echo esc_html__('Other', 'cobra-ai'); ?></option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button cancel-modal"><?php echo esc_html__('Keep Subscription', 'cobra-ai'); ?></button>
            <button type="button" class="button button-primary confirm-cancel"><?php echo esc_html__('Cancel Subscription', 'cobra-ai'); ?></button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle cancel subscription
    $('.cancel-subscription').on('click', function() {
        const subscriptionId = $(this).data('subscription-id');
        const periodEnd = $(this).data('period-end');
        
        $('#cancel-subscription-modal').data('subscription-id', subscriptionId).show();
        $('.period-end-date').text('(<?php echo esc_js(__('Access until', 'cobra-ai')); ?> ' + periodEnd + ')');
    });

    // Handle resume subscription
    $('.resume-subscription').on('click', function() {
        const subscriptionId = $(this).data('subscription-id');
        const button = $(this);
        
        if (!confirm('<?php echo esc_js(__('Are you sure you want to resume your subscription?', 'cobra-ai')); ?>')) {
            return;
        }
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_resume_subscription',
                subscription_id: subscriptionId,
                nonce: '<?php echo wp_create_nonce('cobra-stripe-nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Failed to resume subscription', 'cobra-ai')); ?>');
                    button.prop('disabled', false).text('<?php echo esc_js(__('Resume Subscription', 'cobra-ai')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?>');
                button.prop('disabled', false).text('<?php echo esc_js(__('Resume Subscription', 'cobra-ai')); ?>');
            }
        });
    });

    // Handle update payment method
    $('.update-payment-method').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('<?php echo esc_js(__('Opening...', 'cobra-ai')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_update_payment_method',
                nonce: '<?php echo wp_create_nonce('cobra-stripe-nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    window.open(response.data.portal_url, '_blank');
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Failed to open payment portal', 'cobra-ai')); ?>');
                }
                button.prop('disabled', false).text('<?php echo esc_js(__('Update Payment Method', 'cobra-ai')); ?>');
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?>');
                button.prop('disabled', false).text('<?php echo esc_js(__('Update Payment Method', 'cobra-ai')); ?>');
            }
        });
    });

    // Modal functionality
    $('.close-modal, .cancel-modal').on('click', function() {
        $('#cancel-subscription-modal').hide();
    });

    // Handle cancel confirmation
    $('.confirm-cancel').on('click', function() {
        const modal = $('#cancel-subscription-modal');
        const subscriptionId = modal.data('subscription-id');
        const cancelType = $('input[name="cancel_type"]:checked').val();
        const cancelReason = $('#cancel_reason').val();
        const button = $(this);
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Cancelling...', 'cobra-ai')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_cancel_subscription',
                subscription_id: subscriptionId,
                cancel_immediately: cancelType === 'immediately',
                cancel_reason: cancelReason,
                nonce: '<?php echo wp_create_nonce('cobra-stripe-nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    modal.hide();
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Failed to cancel subscription', 'cobra-ai')); ?>');
                    button.prop('disabled', false).text('<?php echo esc_js(__('Cancel Subscription', 'cobra-ai')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?>');
                button.prop('disabled', false).text('<?php echo esc_js(__('Cancel Subscription', 'cobra-ai')); ?>');
            }
        });
    });
});
</script>

<style>
.subscription-actions {
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.subscription-actions h3 {
    margin: 0 0 15px 0;
    color: #333;
}

.action-group {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.action-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.action-description {
    margin: 8px 0 0 0;
    color: #666;
    font-size: 14px;
}

.cobra-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 0;
    border-radius: 8px;
    min-width: 400px;
    max-width: 500px;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #333;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    color: #999;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
}

.cancel-options {
    margin: 15px 0;
}

.radio-option {
    display: block;
    margin-bottom: 15px;
    cursor: pointer;
}

.radio-option input {
    margin-right: 8px;
}

.radio-option small {
    display: block;
    margin-left: 20px;
    color: #666;
    margin-top: 5px;
}

.cancel-reason {
    margin-top: 20px;
}

.cancel-reason label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.cancel-reason select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e9ecef;
    text-align: right;
}

.modal-footer .button {
    margin-left: 10px;
}
</style>

<?php
