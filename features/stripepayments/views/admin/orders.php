<?php
/** @var array $result */
/** @var \CobraAI\Features\StripePayments\Feature $feature */
defined('ABSPATH') || exit;

$total_pages = max(1, (int) ceil($result['total'] / $result['per_page']));
$current_status = sanitize_key($_GET['status'] ?? '');
$statuses = [
    ''          => __('All', 'cobra-ai'),
    'pending'   => __('Pending', 'cobra-ai'),
    'paid'      => __('Paid', 'cobra-ai'),
    'failed'    => __('Failed', 'cobra-ai'),
    'refunded'  => __('Refunded', 'cobra-ai'),
    'cancelled' => __('Cancelled', 'cobra-ai'),
];

// Dashboard stats
global $wpdb;
$orders_table = $feature->get_table_name('stripe_orders');
$stats = $wpdb->get_row("
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_orders,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS total_revenue,
        SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END) AS total_refunded,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders
    FROM {$orders_table}
");
$stats_30d = $wpdb->get_row($wpdb->prepare("
    SELECT
        COUNT(*) AS orders_30d,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS revenue_30d
    FROM {$orders_table}
    WHERE created_at >= %s
", date('Y-m-d H:i:s', strtotime('-30 days'))));
$default_currency = $feature->get_settings()['currency_default'] ?? 'USD';
?>
<div class="wrap">
    <h1><?php _e('Stripe Payments — Orders', 'cobra-ai'); ?></h1>

    <!-- Dashboard Stats -->
    <div class="cobra-sp-dashboard" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:16px 0 24px;">
        <div class="cobra-sp-stat-card">
            <span class="cobra-sp-stat-icon dashicons dashicons-chart-bar"></span>
            <div class="cobra-sp-stat-body">
                <span class="cobra-sp-stat-value"><?php echo (int) ($stats->total_orders ?? 0); ?></span>
                <span class="cobra-sp-stat-label"><?php _e('Total orders', 'cobra-ai'); ?></span>
            </div>
        </div>
        <div class="cobra-sp-stat-card cobra-sp-stat-success">
            <span class="cobra-sp-stat-icon dashicons dashicons-yes-alt"></span>
            <div class="cobra-sp-stat-body">
                <span class="cobra-sp-stat-value"><?php echo (int) ($stats->paid_orders ?? 0); ?></span>
                <span class="cobra-sp-stat-label"><?php _e('Paid orders', 'cobra-ai'); ?></span>
            </div>
        </div>
        <div class="cobra-sp-stat-card cobra-sp-stat-revenue">
            <span class="cobra-sp-stat-icon dashicons dashicons-money-alt"></span>
            <div class="cobra-sp-stat-body">
                <span class="cobra-sp-stat-value"><?php echo esc_html(number_format((float) ($stats->total_revenue ?? 0), 2) . ' ' . $default_currency); ?></span>
                <span class="cobra-sp-stat-label"><?php _e('Total revenue', 'cobra-ai'); ?></span>
            </div>
        </div>
        <div class="cobra-sp-stat-card cobra-sp-stat-month">
            <span class="cobra-sp-stat-icon dashicons dashicons-calendar"></span>
            <div class="cobra-sp-stat-body">
                <span class="cobra-sp-stat-value"><?php echo esc_html(number_format((float) ($stats_30d->revenue_30d ?? 0), 2) . ' ' . $default_currency); ?></span>
                <span class="cobra-sp-stat-label"><?php _e('Revenue (30 days)', 'cobra-ai'); ?></span>
            </div>
        </div>
        <div class="cobra-sp-stat-card cobra-sp-stat-warning">
            <span class="cobra-sp-stat-icon dashicons dashicons-clock"></span>
            <div class="cobra-sp-stat-body">
                <span class="cobra-sp-stat-value"><?php echo (int) ($stats->pending_orders ?? 0); ?></span>
                <span class="cobra-sp-stat-label"><?php _e('Pending', 'cobra-ai'); ?></span>
            </div>
        </div>
    </div>

    <ul class="subsubsub">
        <?php foreach ($statuses as $slug => $label):
            $url = add_query_arg(['page' => 'cobra-stripe-payments', 'status' => $slug], admin_url('admin.php'));
            $sep = $slug === 'cancelled' ? '' : ' |';
            $class = $current_status === $slug ? ' class="current"' : '';
        ?>
            <li><a href="<?php echo esc_url($url); ?>"<?php echo $class; ?>><?php echo esc_html($label); ?></a><?php echo $sep; ?></li>
        <?php endforeach; ?>
    </ul>
    <br class="clear">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'cobra-ai'); ?></th>
                <th><?php _e('Date', 'cobra-ai'); ?></th>
                <th><?php _e('User', 'cobra-ai'); ?></th>
                <th><?php _e('Product', 'cobra-ai'); ?></th>
                <th><?php _e('Type', 'cobra-ai'); ?></th>
                <th><?php _e('Amount', 'cobra-ai'); ?></th>
                <th><?php _e('Status', 'cobra-ai'); ?></th>
                <th><?php _e('Stripe Session', 'cobra-ai'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($result['rows'])): ?>
            <tr><td colspan="8"><?php _e('No orders found.', 'cobra-ai'); ?></td></tr>
        <?php else: foreach ($result['rows'] as $row):
            $user = get_userdata((int) $row->user_id);
            $product = get_post((int) $row->product_id);
        ?>
            <tr>
                <td>#<?php echo (int) $row->id; ?></td>
                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row->created_at)); ?></td>
                <td><?php echo $user ? esc_html($user->display_name . ' (' . $user->user_email . ')') : '—'; ?></td>
                <td><?php echo $product ? esc_html($product->post_title) : '#' . (int) $row->product_id; ?></td>
                <td><code><?php echo esc_html($row->product_type); ?></code></td>
                <td><?php echo esc_html(number_format((float) $row->amount, 2) . ' ' . $row->currency); ?></td>
                <td>
                    <span class="cobra-status status-<?php echo esc_attr($row->status); ?>">
                        <?php echo esc_html($statuses[$row->status] ?? $row->status); ?>
                    </span>
                </td>
                <td><code style="font-size:11px;"><?php echo esc_html($row->session_id ?: '—'); ?></code></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="tablenav">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php printf(_n('%d order', '%d orders', $result['total'], 'cobra-ai'), $result['total']); ?></span>
            <?php
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $result['page'],
                'total' => $total_pages,
            ]);
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* Status badges */
.cobra-status { padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; }
.cobra-status.status-paid { background: #d4edda; color: #155724; }
.cobra-status.status-pending { background: #fff3cd; color: #856404; }
.cobra-status.status-failed { background: #f8d7da; color: #721c24; }
.cobra-status.status-refunded { background: #d1ecf1; color: #0c5460; }
.cobra-status.status-cancelled { background: #e2e3e5; color: #383d41; }

/* Dashboard stat cards */
.cobra-sp-stat-card {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 16px 18px;
    border-left: 4px solid #2271b1;
}
.cobra-sp-stat-card.cobra-sp-stat-success { border-left-color: #00a32a; }
.cobra-sp-stat-card.cobra-sp-stat-revenue { border-left-color: #635bff; }
.cobra-sp-stat-card.cobra-sp-stat-month { border-left-color: #dba617; }
.cobra-sp-stat-card.cobra-sp-stat-warning { border-left-color: #d63638; }
.cobra-sp-stat-icon {
    font-size: 28px;
    width: 28px;
    height: 28px;
    color: #50575e;
    opacity: .6;
}
.cobra-sp-stat-body { display: flex; flex-direction: column; }
.cobra-sp-stat-value { font-size: 20px; font-weight: 700; color: #1d2327; line-height: 1.2; }
.cobra-sp-stat-label { font-size: 12px; color: #646970; margin-top: 2px; }
</style>
