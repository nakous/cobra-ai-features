<?php
// Prevent direct access
defined('ABSPATH') || exit;
?>
 
<div class="wrap cobra-subscription-details">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Subscription Details', 'cobra-ai'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-stripe-subscriptions')); ?>" 
           class="page-title-action">
            <?php echo esc_html__('Back to Subscriptions', 'cobra-ai'); ?>
            </a>
    </h1>

    <!-- Subscription Overview -->
    <div class="cobra-overview-grid">
        <div class="overview-card status-card">
            <h3><?php echo esc_html__('Status', 'cobra-ai'); ?></h3>
            <div class="subscription-status status-<?php echo esc_attr($subscription->status); ?>">
                <?php echo esc_html(ucfirst($subscription->status)); ?>
                <?php if ($subscription->cancel_at_period_end): ?>
                    <span class="canceling">
                        <?php echo esc_html__('(Cancels at period end)', 'cobra-ai'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="overview-card revenue-card">
            <h3><?php echo esc_html__('Total Revenue', 'cobra-ai'); ?></h3>
            <div class="amount">
                <?php echo esc_html( $this->feature->format_price($analytics['total_revenue'] ?? 0)); ?>
            </div>
        </div>

        <div class="overview-card period-card">
            <h3><?php echo esc_html__('Current Period', 'cobra-ai'); ?></h3>
            <div class="period">
                <?php echo esc_html(
                    sprintf(
                        __('%s to %s', 'cobra-ai'),
                        date_i18n(get_option('date_format'), strtotime($subscription->current_period_start)),
                        date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
                    )
                ); ?>
            </div>
        </div>

        <div class="overview-card created-card">
            <h3><?php echo esc_html__('Created', 'cobra-ai'); ?></h3>
            <div class="date">
                <?php echo esc_html(
                    date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        strtotime($subscription->created_at)
                    )
                ); ?>
            </div>
        </div>
    </div>

    <!-- Details Tabs -->
    <div class="cobra-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#details" class="nav-tab nav-tab-active">
                <?php echo esc_html__('Details', 'cobra-ai'); ?>
            </a>
            <a href="#payments" class="nav-tab">
                <?php echo esc_html__('Payments', 'cobra-ai'); ?>
            </a>
            <?php if (!empty($subscription->customer_id)): ?>
                <a href="#customer" class="nav-tab">
                    <?php echo esc_html__('Customer', 'cobra-ai'); ?>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Details Tab -->
        <div id="details" class="tab-content active">
            <div class="cobra-details-grid">
                <!-- Plan Details -->
                <div class="details-card">
                    <h3><?php echo esc_html__('Plan Details', 'cobra-ai'); ?></h3>
                    <?php if ($plan): ?>
                        <table class="form-table">
                            <tr>
                                <th><?php echo esc_html__('Name', 'cobra-ai'); ?></th>
                                <td><?php echo esc_html($plan['title']); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Price', 'cobra-ai'); ?></th>
                                <td>
                                    <?php echo esc_html(
                                        sprintf(
                                            __('%s / %s', 'cobra-ai'),
                                            $this->feature->format_price($plan['amount']),
                                            $plan['billing_interval']
                                        )
                                    ); ?>
                                </td>
                            </tr>
                            <?php if (!empty($plan['description'])): ?>
                                <tr>
                                    <th><?php echo esc_html__('Description', 'cobra-ai'); ?></th>
                                    <td><?php echo wp_kses_post($plan['description']); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    <?php else: ?>
                        <p class="description">
                            <?php echo esc_html__('Plan not found', 'cobra-ai'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Billing Details -->
                <div class="details-card">
                    <h3><?php echo esc_html__('Billing Details', 'cobra-ai'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                            <td>
                                <span class="subscription-status status-<?php echo esc_attr($subscription->status); ?>">
                                    <?php echo esc_html(ucfirst($subscription->status)); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__('Period Start', 'cobra-ai'); ?></th>
                            <td>
                                <?php echo esc_html(
                                    date_i18n(
                                        get_option('date_format'),
                                        strtotime($subscription->current_period_start)
                                    )
                                ); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__('Period End', 'cobra-ai'); ?></th>
                            <td>
                                <?php echo esc_html(
                                    date_i18n(
                                        get_option('date_format'),
                                        strtotime($subscription->current_period_end)
                                    )
                                ); ?>
                            </td>
                        </tr>
                        <?php if ($subscription->cancel_at_period_end): ?>
                            <tr>
                                <th><?php echo esc_html__('Cancellation', 'cobra-ai'); ?></th>
                                <td>
                                    <?php echo esc_html__('Will cancel at period end', 'cobra-ai'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Actions -->
            <div class="subscription-actions">
                <?php if ($subscription->status === 'active' && !$subscription->cancel_at_period_end): ?>
                    <button type="button" 
                            class="button cancel-subscription"
                            data-id="<?php echo esc_attr($subscription->subscription_id); ?>"
                            data-nonce="<?php echo wp_create_nonce('cobra_subscription_admin'); ?>">
                        <?php echo esc_html__('Cancel Subscription', 'cobra-ai'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payments Tab -->
        <div id="payments" class="tab-content">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Date', 'cobra-ai'); ?></th>
                        <th><?php echo esc_html__('Amount', 'cobra-ai'); ?></th>
                        <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                        <th><?php echo esc_html__('Invoice', 'cobra-ai'); ?></th>
                        <th><?php echo esc_html__('Actions', 'cobra-ai'); ?></th>
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
                                    <?php echo esc_html($this->feature->format_price($payment->amount, $payment->currency)); ?>
                                </td>
                                <td>
                                    <span class="payment-status status-<?php echo esc_attr($payment->status); ?>">
                                        <?php echo esc_html(ucfirst($payment->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment->invoice_id): ?>
                                        <a href="#" class="view-invoice" data-id="<?php echo esc_attr($payment->invoice_id); ?>">
                                            <?php echo esc_html__('View Invoice', 'cobra-ai'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment->status === 'succeeded' && !$payment->refunded): ?>
                                        <button type="button" 
                                                class="button-secondary refund-payment"
                                                data-id="<?php echo esc_attr($payment->payment_id); ?>"
                                                data-amount="<?php echo esc_attr($payment->amount); ?>"
                                                data-nonce="<?php echo wp_create_nonce('cobra_subscription_admin'); ?>">
                                            <?php echo esc_html__('Refund', 'cobra-ai'); ?>
                                        </button>
                                    <?php elseif ($payment->refunded): ?>
                                        <span class="refunded">
                                            <?php echo esc_html__('Refunded', 'cobra-ai'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
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

        <!-- Customer Tab -->
        <?php if (!empty($subscription->customer_id)): ?>
            <div id="customer" class="tab-content">
                <?php if ($user): ?>
                    <div class="customer-details">
                        <h3><?php echo esc_html__('Customer Information', 'cobra-ai'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><?php echo esc_html__('Name', 'cobra-ai'); ?></th>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
                                        <?php echo esc_html($user->display_name); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Email', 'cobra-ai'); ?></th>
                                <td><?php echo esc_html($user->user_email); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Member Since', 'cobra-ai'); ?></th>
                                <td>
                                    <?php echo esc_html(
                                        date_i18n(
                                            get_option('date_format'),
                                            strtotime($user->user_registered)
                                        )
                                    ); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="description">
                        <?php echo esc_html__('Customer not found', 'cobra-ai'); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modals -->
<?php require dirname(__FILE__) . '/partials/modal-cancel.php'; ?>
<?php require dirname(__FILE__) . '/partials/modal-refund.php'; ?>

<script>
jQuery(document).ready(function($) {
    // Tab handling
    $('.cobra-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        const target = $(this).attr('href');
        
        // Update tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Update content
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });

    // Initialize modals and actions
    initCancellationModal();
    initRefundModal();
    
    // Invoice viewer (if needed)
    function initInvoiceViewer() {
        $('.view-invoice').on('click', function(e) {
            e.preventDefault();
            const invoiceId = $(this).data('id');
            // TODO: Implement invoice viewing
            alert('Invoice viewer coming soon. Invoice ID: ' + invoiceId);
        });
    }
    initInvoiceViewer();
});
</script>