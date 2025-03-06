<?php
// views/admin/payments.php
namespace CobraAI\Features\StripeSubscription\Views;
defined('ABSPATH') || exit;

// Get current filters
$period = $_GET['period'] ?? '30_days';
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Payment Management', 'cobra-ai'); ?></h1>

    <!-- Stats Cards -->
    <div class="cobra-analytics-grid">
        <div class="analytics-card">
            <h3><?php echo esc_html__('Total Revenue', 'cobra-ai'); ?></h3>
            <div class="analytics-value success" data-currency="$">
                <?php echo esc_html(number_format($analytics['total_revenue'] ?? 0, 2)); ?>
            </div>
        </div>

        <div class="analytics-card">
            <h3><?php echo esc_html__('Successful Payments', 'cobra-ai'); ?></h3>
            <div class="analytics-value">
                <?php echo esc_html($analytics['successful_payments'] ?? 0); ?>
            </div>
        </div>

        <div class="analytics-card">
            <h3><?php echo esc_html__('Failed Payments', 'cobra-ai'); ?></h3>
            <div class="analytics-value warning">
                <?php echo esc_html($analytics['failed_payments'] ?? 0); ?>
            </div>
        </div>

        <div class="analytics-card">
            <h3><?php echo esc_html__('Refunded Amount', 'cobra-ai'); ?></h3>
            <div class="analytics-value" data-currency="$">
                <?php echo esc_html(number_format($analytics['refunded_amount'] ?? 0, 2)); ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class=" top">
        <form method="get" class="cobra-filters">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <input type="hidden" name="view" value="payments">
            
            <!-- Period Filter -->
            <select name="period" class="period-filter">
                <option value="7_days" <?php selected($period, '7_days'); ?>>
                    <?php echo esc_html__('Last 7 Days', 'cobra-ai'); ?>
                </option>
                <option value="30_days" <?php selected($period, '30_days'); ?>>
                    <?php echo esc_html__('Last 30 Days', 'cobra-ai'); ?>
                </option>
                <option value="90_days" <?php selected($period, '90_days'); ?>>
                    <?php echo esc_html__('Last 90 Days', 'cobra-ai'); ?>
                </option>
                <option value="custom" <?php selected($period, 'custom'); ?>>
                    <?php echo esc_html__('Custom Range', 'cobra-ai'); ?>
                </option>
            </select>

            <div class="custom-date-range" <?php echo $period !== 'custom' ? 'style="display:none;"' : ''; ?>>
                <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                <span>-</span>
                <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            </div>

            <button type="submit" class="button"><?php echo esc_html__('Apply', 'cobra-ai'); ?></button>
        </form>
    </div>

    <!-- Payments Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('Payment ID', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Amount', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Subscription', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Date', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Actions', 'cobra-ai'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo esc_html($payment->payment_id); ?></td>
                    <td>
                        <?php echo esc_html(
                            $this->feature->format_price($payment->amount, $payment->currency)
                        ); ?>
                    </td>
                    <td>
                        <span class="payment-status status-<?php echo esc_attr($payment->status); ?>">
                            <?php echo esc_html(ucfirst($payment->status)); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($payment->subscription_id): ?>
                            <a href="<?php echo esc_url(add_query_arg([
                                'page' => 'cobra-stripe-subscriptions',
                                'subscription' => $payment->subscription_id
                            ], admin_url('admin.php'))); ?>">
                                <?php echo esc_html($payment->subscription_id); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html(
                            date_i18n(
                                get_option('date_format') . ' ' . get_option('time_format'),
                                strtotime($payment->created_at)
                            )
                        ); ?>
                    </td>
                    <td>
                        <?php if ($payment->status === 'succeeded'): ?>
                            <button type="button" 
                                    class="button refund-payment" 
                                    data-payment-id="<?php echo esc_attr($payment->payment_id); ?>"
                                    data-amount="<?php echo esc_attr($payment->amount); ?>">
                                <?php echo esc_html__('Refund', 'cobra-ai'); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(
                        esc_html__('%s items', 'cobra-ai'),
                        number_format_i18n($total_items)
                    ); ?>
                </span>
                <span class="pagination-links">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ]);
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle period filter
    $('.period-filter').on('change', function() {
        $('.custom-date-range').toggle($(this).val() === 'custom');
    });

    // Handle refund button
    $('.refund-payment').on('click', function() {
        const button = $(this);
        const paymentId = button.data('payment-id');
        const amount = button.data('amount');

        if (!confirm('<?php echo esc_js(__('Are you sure you want to refund this payment?', 'cobra-ai')); ?>')) {
            return;
        }

        button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_subscription_refund',
                payment_id: paymentId,
                amount: amount,
                _ajax_nonce: '<?php echo wp_create_nonce('cobra_subscription_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Refund failed', 'cobra-ai')); ?>');
                    button.prop('disabled', false).text('<?php echo esc_js(__('Refund', 'cobra-ai')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Request failed', 'cobra-ai')); ?>');
                button.prop('disabled', false).text('<?php echo esc_js(__('Refund', 'cobra-ai')); ?>');
            }
        });
    });
});
</script>

 