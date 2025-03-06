<?php
// views/public/account.php
namespace CobraAI\Features\StripeSubscription\Views;

// Prevent direct access
defined('ABSPATH') || exit;

// Get user's subscription
$subscription = $this->get_user_subscription(get_current_user_id());
if (!$subscription) {
    wp_die(__('No active subscription found.', 'cobra-ai'));
}

$plan = $this->get_plan($subscription->plan_id);
$payment_history = $this->get_subscription_payments($subscription->id);
?>

<div class="cobra-account-wrapper">
    <!-- Subscription Overview -->
    <div class="subscription-overview">
        <h2><?php echo esc_html__('My Subscription', 'cobra-ai'); ?></h2>

        <div class="subscription-status">
            <span class="status-label">
                <?php echo esc_html__('Status:', 'cobra-ai'); ?>
            </span>
            <span class="status-badge status-<?php echo esc_attr($subscription->status); ?>">
                <?php echo esc_html(ucfirst($subscription->status)); ?>
            </span>

            <?php if ($subscription->cancel_at_period_end): ?>
                <span class="canceling-notice">
                    <?php echo esc_html__('(Cancels at period end)', 'cobra-ai'); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="plan-details">
            <h3><?php echo esc_html($plan->name); ?></h3>
            <div class="plan-price">
                <?php echo esc_html(
                    sprintf(
                        __('%s / %s', 'cobra-ai'),
                        $this->format_currency($plan->amount),
                        $plan->interval
                    )
                ); ?>
            </div>

            <?php if (!empty($plan->features)): ?>
                <div class="plan-features">
                    <h4><?php echo esc_html__('Your Benefits:', 'cobra-ai'); ?></h4>
                    <ul>
                        <?php foreach ($plan->features as $feature): ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div class="billing-details">
            <h3><?php echo esc_html__('Billing Information', 'cobra-ai'); ?></h3>
            
            <div class="billing-grid">
                <div class="billing-item">
                    <label><?php echo esc_html__('Next Payment:', 'cobra-ai'); ?></label>
                    <span>
                        <?php if (!$subscription->cancel_at_period_end): ?>
                            <?php echo esc_html(
                                date_i18n(
                                    get_option('date_format'),
                                    strtotime($subscription->current_period_end)
                                )
                            ); ?>
                        <?php else: ?>
                            <?php echo esc_html__('None (Subscription ending)', 'cobra-ai'); ?>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="billing-item">
                    <label><?php echo esc_html__('Payment Method:', 'cobra-ai'); ?></label>
                    <span class="payment-method">
                        <i class="card-icon"></i>
                        •••• <?php echo esc_html($subscription->card_last4); ?>
                        <span class="card-expiry">
                        <?php echo sprintf(
                            esc_html__('Expires %s/%s', 'cobra-ai'),
                            $subscription->card_exp_month,
                            $subscription->card_exp_year
                        ); ?>
                    </span>
                </span>
                <button type="button" class="button-link update-payment-method">
                    <?php echo esc_html__('Update', 'cobra-ai'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Subscription Actions -->
    <div class="subscription-actions">
        <?php if ($subscription->status === 'active' && !$subscription->cancel_at_period_end): ?>
            <button type="button" class="button cancel-subscription"
                    data-id="<?php echo esc_attr($subscription->subscription_id); ?>"
                    data-nonce="<?php echo wp_create_nonce('cancel_subscription_' . $subscription->id); ?>">
                <?php echo esc_html__('Cancel Subscription', 'cobra-ai'); ?>
            </button>
        <?php elseif ($subscription->status === 'active' && $subscription->cancel_at_period_end): ?>
            <button type="button" class="button resume-subscription"
                    data-id="<?php echo esc_attr($subscription->subscription_id); ?>"
                    data-nonce="<?php echo wp_create_nonce('resume_subscription_' . $subscription->id); ?>">
                <?php echo esc_html__('Resume Subscription', 'cobra-ai'); ?>
            </button>
            <p class="cancellation-notice">
                <?php echo sprintf(
                    esc_html__('Your subscription will end on %s', 'cobra-ai'),
                    date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
                ); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Payment History -->
<div class="payment-history">
    <h3><?php echo esc_html__('Payment History', 'cobra-ai'); ?></h3>
    
    <table class="payment-table">
        <thead>
            <tr>
                <th><?php echo esc_html__('Date', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Amount', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Invoice', 'cobra-ai'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($payment_history)): ?>
                <?php foreach ($payment_history as $payment): ?>
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
                            <?php echo esc_html($this->format_currency($payment->amount)); ?>
                        </td>
                        <td>
                            <span class="payment-status status-<?php echo esc_attr($payment->status); ?>">
                                <?php echo esc_html(ucfirst($payment->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($payment->invoice_id && $payment->status === 'succeeded'): ?>
                                <a href="#" class="view-invoice" data-id="<?php echo esc_attr($payment->invoice_id); ?>">
                                    <?php echo esc_html__('View Invoice', 'cobra-ai'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">
                        <?php echo esc_html__('No payment history available.', 'cobra-ai'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<!-- Update Payment Method Modal -->
<div id="update-payment-modal" class="cobra-modal">
<div class="cobra-modal-content">
    <div class="cobra-modal-header">
        <h2><?php echo esc_html__('Update Payment Method', 'cobra-ai'); ?></h2>
        <button type="button" class="close-modal">×</button>
    </div>
    <div class="cobra-modal-body">
        <form id="payment-update-form">
            <input type="hidden" name="subscription_id" 
                   value="<?php echo esc_attr($subscription->subscription_id); ?>">
            <?php wp_nonce_field('update_payment_' . $subscription->id); ?>

            <div class="form-row">
                <label for="card-element">
                    <?php echo esc_html__('New Card Details', 'cobra-ai'); ?>
                </label>
                <div id="card-element"></div>
                <div id="card-errors" role="alert"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('Update Card', 'cobra-ai'); ?>
                </button>
                <button type="button" class="button button-secondary close-modal">
                    <?php echo esc_html__('Cancel', 'cobra-ai'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- Cancellation Modal -->
<div id="cancel-subscription-modal" class="cobra-modal">
<div class="cobra-modal-content">
    <div class="cobra-modal-header">
        <h2><?php echo esc_html__('Cancel Subscription', 'cobra-ai'); ?></h2>
        <button type="button" class="close-modal">×</button>
    </div>
    <div class="cobra-modal-body">
        <p><?php echo esc_html__('Are you sure you want to cancel your subscription?', 'cobra-ai'); ?></p>
        
        <?php if ($this->get_settings('cancellation_behavior') === 'end_of_period'): ?>
            <p>
                <?php echo sprintf(
                    esc_html__('Your subscription will remain active until the end of the current billing period (%s).', 'cobra-ai'),
                    date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
                ); ?>
            </p>
        <?php else: ?>
            <p>
                <?php echo esc_html__('Your subscription will be cancelled immediately. No refunds will be issued for the current period.', 'cobra-ai'); ?>
            </p>
        <?php endif; ?>

        <div class="modal-actions">
            <button type="button" class="button button-primary" id="confirm-cancel">
                <?php echo esc_html__('Yes, Cancel Subscription', 'cobra-ai'); ?>
            </button>
            <button type="button" class="button button-secondary close-modal">
                <?php echo esc_html__('No, Keep Subscription', 'cobra-ai'); ?>
            </button>
        </div>
    </div>
</div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
jQuery(document).ready(function($) {
// Initialize Stripe
const stripe = Stripe('<?php echo esc_js($this->get_stripe_feature()->get_api()->get_publishable_key()); ?>');
const elements = stripe.elements();
let cardElement = null;

// Handle payment method update
$('.update-payment-method').on('click', function() {
    // Create card element if not exists
    if (!cardElement) {
        cardElement = elements.create('card');
        cardElement.mount('#card-element');
    }
    $('#update-payment-modal').show();
});

// Handle payment update form submission
$('#payment-update-form').on('submit', async function(e) {
    e.preventDefault();
    const $form = $(this);
    const $button = $form.find('button[type="submit"]');
    
    $button.prop('disabled', true)
        .html('<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

    try {
        // Create payment method
        const { paymentMethod, error } = await stripe.createPaymentMethod({
            type: 'card',
            card: cardElement
        });

        if (error) {
            throw error;
        }

        // Update subscription payment method
        const response = await $.ajax({
            url: cobra_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'cobra_subscription_update_payment',
                subscription_id: $form.find('input[name="subscription_id"]').val(),
                payment_method: paymentMethod.id,
                _ajax_nonce: $form.find('#_wpnonce').val()
            }
        });

        if (!response.success) {
            throw new Error(response.data.message || '<?php echo esc_js(__('Failed to update payment method', 'cobra-ai')); ?>');
        }

        location.reload();

    } catch (error) {
        const errorElement = document.getElementById('card-errors');
        errorElement.textContent = error.message;
        $button.prop('disabled', false)
            .text('<?php echo esc_js(__('Try Again', 'cobra-ai')); ?>');
    }
});

// Handle subscription cancellation
$('.cancel-subscription').on('click', function() {
    $('#cancel-subscription-modal').show();
});

// Handle cancellation confirmation
$('#confirm-cancel').on('click', async function() {
    const $button = $(this);
    const subscriptionId = $('.cancel-subscription').data('id');
    const nonce = $('.cancel-subscription').data('nonce');
    
    $button.prop('disabled', true)
        .html('<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

    try {
        const response = await $.ajax({
            url: cobra_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'cobra_subscription_cancel',
                subscription_id: subscriptionId,
                _ajax_nonce: nonce
            }
        });

        if (!response.success) {
            throw new Error(response.data.message || '<?php echo esc_js(__('Failed to cancel subscription', 'cobra-ai')); ?>');
        }

        location.reload();

    } catch (error) {
        alert(error.message);
        $button.prop('disabled', false)
            .text('<?php echo esc_js(__('Try Again', 'cobra-ai')); ?>');
    }
});

// Handle subscription resume
$('.resume-subscription').on('click', async function() {
    const $button = $(this);
    const subscriptionId = $button.data('id');
    const nonce = $button.data('nonce');
    
    if (!confirm('<?php echo esc_js(__('Are you sure you want to resume your subscription?', 'cobra-ai')); ?>')) {
        return;
    }

    $button.prop('disabled', true)
        .html('<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

    try {
        const response = await $.ajax({
            url: cobra_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'cobra_subscription_resume',
                subscription_id: subscriptionId,
                _ajax_nonce: nonce
            }
        });

        if (!response.success) {
            throw new Error(response.data.message || '<?php echo esc_js(__('Failed to resume subscription', 'cobra-ai')); ?>');
        }

        location.reload();

    } catch (error) {
        alert(error.message);
        $button.prop('disabled', false)
            .text('<?php echo esc_js(__('Try Again', 'cobra-ai')); ?>');
    }
});

// Handle invoice view
$('.view-invoice').on('click', async function(e) {
    e.preventDefault();
    const invoiceId = $(this).data('id');
    
    try {
        const response = await $.ajax({
            url: cobra_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'cobra_subscription_get_invoice',
                invoice_id: invoiceId,
                _ajax_nonce: '<?php echo wp_create_nonce('get_invoice'); ?>'
            }
        });

        if (!response.success) {
            throw new Error(response.data.message || '<?php echo esc_js(__('Failed to retrieve invoice', 'cobra-ai')); ?>');
        }

        // Open invoice in new window
        window.open(response.data.url, '_blank');

    } catch (error) {
        alert(error.message);
    }
});

// Close modals
$('.close-modal').on('click', function() {
    $(this).closest('.cobra-modal').hide();
});
});
</script>