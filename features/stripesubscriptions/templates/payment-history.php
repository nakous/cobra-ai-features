<?php
// templates/shortcodes/payment-history.php
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    return sprintf(
        '<p class="cobra-notice">%s <a href="%s">%s</a></p>',
        esc_html__('Please', 'cobra-ai'),
        esc_url(wp_login_url(get_permalink())),
        esc_html__('login to view your payment history', 'cobra-ai')
    );
}

// Get user's subscription
$subscription = $this->get_user_subscription(get_current_user_id());
if (!$subscription) {
    return '<p class="cobra-notice">' . esc_html__('No subscription found.', 'cobra-ai') . '</p>';
}

// Get payment history with pagination
$per_page = absint($atts['per_page'] ?? 10);
$current_page = max(1, get_query_var('paged'));
$payments = $this->get_subscription_payments($subscription->id, [
    'per_page' => $per_page,
    'page' => $current_page
]);

// Get total payments for pagination
$total_payments = $this->get_subscription_payment_count($subscription->id);
$total_pages = ceil($total_payments / $per_page);

// Start output buffer
ob_start();
?>

<div class="cobra-payment-history">
    <h2><?php echo esc_html__('Payment History', 'cobra-ai'); ?></h2>

    <?php if (empty($payments)): ?>
        <p class="cobra-notice">
            <?php echo esc_html__('No payment history available.', 'cobra-ai'); ?>
        </p>
    <?php else: ?>
        <table class="payment-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Date', 'cobra-ai'); ?></th>
                    <th><?php echo esc_html__('Description', 'cobra-ai'); ?></th>
                    <th><?php echo esc_html__('Amount', 'cobra-ai'); ?></th>
                    <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                    <th><?php echo esc_html__('Invoice', 'cobra-ai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="payment-date">
                            <?php echo date_i18n(
                                get_option('date_format'),
                                strtotime($payment->created_at)
                            ); ?>
                        </td>
                        <td class="payment-description">
                            <?php if ($payment->refunded): ?>
                                <?php echo esc_html__('Refund', 'cobra-ai'); ?>
                            <?php else: ?>
                                <?php echo esc_html__('Subscription Payment', 'cobra-ai'); ?>
                            <?php endif; ?>
                        </td>
                        <td class="payment-amount">
                            <?php echo esc_html($this->format_price($payment->amount)); ?>
                        </td>
                        <td class="payment-status">
                            <span class="status-badge status-<?php echo esc_attr($payment->status); ?>">
                                <?php echo esc_html(ucfirst($payment->status)); ?>
                            </span>
                        </td>
                        <td class="payment-invoice">
                            <?php if ($payment->invoice_id && $payment->status === 'succeeded'): ?>
                                <a href="#" class="view-invoice" 
                                   data-id="<?php echo esc_attr($payment->invoice_id); ?>"
                                   data-nonce="<?php echo wp_create_nonce('view_invoice_' . $payment->id); ?>">
                                    <?php echo esc_html__('View Invoice', 'cobra-ai'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="cobra-pagination">
                <?php
                echo paginate_links([
                    'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                    'format' => '?paged=%#%',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo; ' . __('Previous', 'cobra-ai'),
                    'next_text' => __('Next', 'cobra-ai') . ' &raquo;',
                ]);
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle invoice view
    $('.view-invoice').on('click', async function(e) {
        e.preventDefault();
        const invoiceId = $(this).data('id');
        const nonce = $(this).data('nonce');
        
        try {
            const response = await $.ajax({
                url: cobra_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_get_invoice',
                    invoice_id: invoiceId,
                    _ajax_nonce: nonce
                }
            });

            if (response.success) {
                // Open invoice in new window/tab
                window.open(response.data.url, '_blank');
            } else {
                throw new Error(response.data.message || '<?php echo esc_js(__('Failed to retrieve invoice', 'cobra-ai')); ?>');
            }
        } catch (error) {
            alert(error.message);
        }
    });
});
</script>

<?php
return ob_get_clean();