<?php
// /Admin/views/user-profile-credits.php
defined('ABSPATH') || exit;

// Get additional user credit data
$active_credits = array_filter($credit_history, function($credit) {
    return $credit->status === 'active';
});

$total_active = array_reduce($active_credits, function($carry, $credit) {
    return $carry + ($credit->credit - $credit->consumed);
}, 0);

$nearest_expiration = null;
foreach ($active_credits as $credit) {
    if ($credit->expiration_date) {
        if (!$nearest_expiration || strtotime($credit->expiration_date) < strtotime($nearest_expiration)) {
            $nearest_expiration = $credit->expiration_date;
        }
    }
}
?>

<div class="cobra-credits-section">
    <h2><?php _e('Credits Management', 'cobra-ai'); ?></h2>

    <?php if (current_user_can('manage_options')): ?>
        <div class="cobra-credits-actions">
            <a href="<?php echo esc_url(add_query_arg([
                'page' => $menu_slug,
                'action' => 'add',
                'user_id' => $user->ID
            ], admin_url('admin.php'))); ?>" class="button button-primary">
                <?php _e('Add Credits', 'cobra-ai'); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="cobra-credits-summary">
        <div class="cobra-credits-cards">
            <!-- Total Credits Card -->
            <div class="cobra-credit-card">
                <h3><?php _e('Total Credits', 'cobra-ai'); ?></h3>
                <div class="credit-amount">
                    <strong><?php echo number_format_i18n($total_credits, 2); ?></strong>
                    <span class="credit-symbol"><?php echo esc_html($settings['general']['credit_symbol']); ?></span>
                </div>
                <div class="credit-breakdown">
                    <div class="credit-detail">
                        <span class="label"><?php _e('Active:', 'cobra-ai'); ?></span>
                        <span class="value">
                            <?php echo number_format_i18n($total_active, 2); ?>
                            <?php echo esc_html($settings['general']['credit_symbol']); ?>
                        </span>
                    </div>
                    <?php if ($nearest_expiration): ?>
                        <div class="credit-detail">
                            <span class="label"><?php _e('Next Expiration:', 'cobra-ai'); ?></span>
                            <span class="value">
                                <?php echo get_date_from_gmt($nearest_expiration, get_option('date_format')); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Credit Types Breakdown -->
            <div class="cobra-credit-card">
                <h3><?php _e('Credits by Type', 'cobra-ai'); ?></h3>
                <div class="credit-type-breakdown">
                    <?php
                    $type_totals = [];
                    foreach ($active_credits as $credit) {
                        $type = $credit->credit_type;
                        if (!isset($type_totals[$type])) {
                            $type_totals[$type] = 0;
                        }
                        $type_totals[$type] += ($credit->credit - $credit->consumed);
                    }

                    foreach ($type_totals as $type => $amount): 
                        if ($amount > 0):
                    ?>
                        <div class="credit-type-row">
                            <span class="credit-type-label">
                                <?php echo esc_html($credit_types[$type]); ?>:
                            </span>
                            <span class="credit-type-amount">
                                <?php echo number_format_i18n($amount, 2); ?>
                                <?php echo esc_html($settings['general']['credit_symbol']); ?>
                            </span>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Credit History -->
    <?php if (!empty($credit_history)): ?>
        <div class="cobra-credits-history">
            <h3><?php _e('Credit History', 'cobra-ai'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Type', 'cobra-ai'); ?></th>
                        <th><?php _e('Amount', 'cobra-ai'); ?></th>
                        <th><?php _e('Consumed', 'cobra-ai'); ?></th>
                        <th><?php _e('Remaining', 'cobra-ai'); ?></th>
                        <th><?php _e('Status', 'cobra-ai'); ?></th>
                        <th><?php _e('Start Date', 'cobra-ai'); ?></th>
                        <th><?php _e('Expiration', 'cobra-ai'); ?></th>
                        <th><?php _e('Comment', 'cobra-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($credit_history as $credit): ?>
                        <tr>
                            <td>
                                <?php echo esc_html($credit_types[$credit->credit_type]); ?>
                            </td>
                            <td>
                                <?php echo number_format_i18n($credit->credit, 2); ?>
                                <?php echo esc_html($settings['general']['credit_symbol']); ?>
                            </td>
                            <td>
                                <?php echo number_format_i18n($credit->consumed, 2); ?>
                                <?php echo esc_html($settings['general']['credit_symbol']); ?>
                            </td>
                            <td>
                                <?php 
                                $remaining = $credit->credit - $credit->consumed;
                                echo number_format_i18n($remaining, 2);
                                echo ' ' . esc_html($settings['general']['credit_symbol']);
                                ?>
                            </td>
                            <td>
                                <span class="credit-status status-<?php echo esc_attr($credit->status); ?>">
                                    <?php echo esc_html(ucfirst($credit->status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo get_date_from_gmt($credit->start_date, get_option('date_format')); ?>
                            </td>
                            <td>
                                <?php 
                                echo $credit->expiration_date 
                                    ? get_date_from_gmt($credit->expiration_date, get_option('date_format'))
                                    : __('Never', 'cobra-ai');
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html($credit->comment); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (count($credit_history) >= 5): ?>
                <p class="description">
                    <a href="<?php echo esc_url(add_query_arg([
                        'page' => $menu_slug,
                        'user_id' => $user->ID
                    ], admin_url('admin.php'))); ?>">
                        <?php _e('View complete credit history', 'cobra-ai'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.cobra-credits-section {
    margin: 20px 0;
}

.cobra-credits-actions {
    margin-bottom: 20px;
}

.cobra-credits-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.cobra-credit-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.cobra-credit-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.credit-amount {
    font-size: 24px;
    margin-bottom: 15px;
}

.credit-amount strong {
    color: #2271b1;
}

.credit-symbol {
    font-size: 16px;
    color: #50575e;
    margin-left: 5px;
}

.credit-breakdown, .credit-type-breakdown {
    font-size: 13px;
}

.credit-detail, .credit-type-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f1;
}

.credit-detail:last-child, .credit-type-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.credit-type-label {
    color: #50575e;
}

.credit-type-amount {
    font-weight: 600;
}

.credit-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-active {
    background-color: #dff0d8;
    color: #3c763d;
}

.status-pending {
    background-color: #fcf8e3;
    color: #8a6d3b;
}

.status-expired {
    background-color: #f2dede;
    color: #a94442;
}

.status-deleted {
    background-color: #f5f5f5;
    color: #777;
}

.cobra-credits-history {
    margin-top: 30px;
}

.cobra-credits-history table {
    margin-top: 15px;
}

.cobra-credits-history td, .cobra-credits-history th {
    vertical-align: middle;
}
</style>