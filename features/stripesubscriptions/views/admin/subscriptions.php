<?php
// views/admin/subscriptions.php
namespace CobraAI\Features\StripeSubscription\Views;

// Prevent direct access
defined('ABSPATH') || exit;

// Get current filters
$current_status = sanitize_text_field($_GET['status'] ?? '');
$search_query = sanitize_text_field($_GET['s'] ?? '');

// Get analytics data
$analytics = $this->feature->get_payments()->get_payment_analytics();

// Get active subscriptions with pagination
$per_page = $this->feature->get_subscriptions()->get_items_per_page('subscriptions_per_page', 20);
$current_page = $this->feature->get_subscriptions()->get_pagenum();
$subscriptions = $this->feature->get_subscriptions()->get_subscriptions([
    'per_page' => $per_page,
    'page' => $current_page,
    'status' => $current_status,
    'search' => $search_query
]);

// Get status counts
$status_counts = $this->get_subscription_status_counts();
?>

<div class="wrap cobra-stripe-subscriptions">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Subscriptions', 'cobra-ai'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-stripe-plans')); ?>"
            class="page-title-action">
            <?php echo esc_html__('Manage Plans', 'cobra-ai'); ?>
        </a>

        <button type="button" class="page-title-action sync-subscriptions">
            <?php echo esc_html__('Sync from Stripe', 'cobra-ai'); ?>
        </button>


        <script>
            jQuery(document).ready(function($) {
                $('.sync-subscriptions').on('click', function() {
                    const button = $(this);

                    if (button.prop('disabled')) {
                        return;
                    }

                    // Show confirmation dialog
                    if (!confirm('<?php echo esc_js(__('Are you sure you want to sync subscriptions from Stripe?', 'cobra-ai')); ?>')) {
                        return;
                    }

                    // Disable button and show loading state
                    button.prop('disabled', true)
                        .html('<?php echo esc_js(__('Syncing...', 'cobra-ai')); ?>');

                    // Make AJAX call
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cobra_subscription_sync',
                            nonce: '<?php echo wp_create_nonce('cobra-stripe-nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                location.reload();
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Sync failed', 'cobra-ai')); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('Ajax request failed', 'cobra-ai')); ?>');
                        },
                        complete: function() {
                            button.prop('disabled', false)
                                .html('<?php echo esc_js(__('Sync from Stripe', 'cobra-ai')); ?>');
                        }
                    });
                });
            });
        </script>
    </h1>

    <!-- Analytics Overview -->
    <div class="cobra-analytics-grid">
        <div class="analytics-card">
            <h3><?php echo esc_html__('Active Subscriptions', 'cobra-ai'); ?></h3>
            <div class="analytics-value">
                <?php echo esc_html($status_counts['active'] ?? 0); ?>
            </div>
        </div>

        <div class="analytics-card">
            <h3><?php echo esc_html__('Monthly Revenue', 'cobra-ai'); ?></h3>
            <div class="analytics-value">
                <?php echo esc_html(
                    $this->feature->format_price($analytics['total_revenue'] ?? 0)
                ); ?>
            </div>
        </div>

        <div class="analytics-card">
            <h3><?php echo esc_html__('Trial Subscriptions', 'cobra-ai'); ?></h3>
            <div class="analytics-value">
                <?php echo esc_html($status_counts['trialing'] ?? 0); ?>
            </div>
        </div>

        <div class="analytics-card">
            <h3><?php echo esc_html__('Past Due', 'cobra-ai'); ?></h3>
            <div class="analytics-value warning">
                <?php echo esc_html($status_counts['past_due'] ?? 0); ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="cobra-filters">
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">

            <!-- Status Filter -->
            <select name="status">
                <option value=""><?php echo esc_html__('All Statuses', 'cobra-ai'); ?></option>
                <?php foreach ($status_counts as $status => $count): ?>
                    <option value="<?php echo esc_attr($status); ?>"
                        <?php selected($current_status, $status); ?>>
                        <?php echo esc_html(ucfirst($status) . " ($count)"); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Search -->
            <input type="search"
                name="s"
                value="<?php echo esc_attr($search_query); ?>"
                placeholder="<?php echo esc_attr__('Search subscriptions...', 'cobra-ai'); ?>">

            <?php submit_button(__('Filter', 'cobra-ai'), 'secondary', 'filter', false); ?>

            <?php if ($current_status || $search_query): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $_GET['page'])); ?>"
                    class="button-secondary">
                    <?php echo esc_html__('Clear', 'cobra-ai'); ?>
                </a>
            <?php endif; ?>
        </form>

        <!-- Export Button -->
        <div class="cobra-export">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="cobra_export_subscriptions">
                <?php wp_nonce_field('cobra_export_subscriptions'); ?>
                <button type="submit" class="button">
                    <?php echo esc_html__('Export CSV', 'cobra-ai'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Subscriptions Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('Customer', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Plan', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Amount', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Start Date', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Next Payment', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Actions', 'cobra-ai'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($subscriptions)): ?>
                <?php foreach ($subscriptions as $subscription):
                    $user = get_user_by('id', $subscription->user_id);
                    $plan = $this->feature->get_plans()->get_plan($subscription->plan_id);
                    print_r($plan);
                ?>
                    <tr>
                        <td>
                            <?php if ($user): ?>
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
                                        <?php echo esc_html($user->display_name); ?>
                                    </a>
                                </strong>
                                <br>
                                <small><?php echo esc_html($user->user_email); ?></small>
                            <?php else: ?>
                                <?php echo esc_html__('User not found', 'cobra-ai'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($plan): ?>
                                <strong>
                                    <a href="<?php echo esc_url(admin_url('/wp-admin/post.php?post=' . $plan['id'] . '&action=edit')); ?>">

                                        <?php echo esc_html(get_the_title($plan['id'])); ?>
                                    </a>
                                </strong>
                            <?php else: ?>
                                <?php echo esc_html__('Plan not found', 'cobra-ai'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="subscription-status status-<?php echo esc_attr($subscription->status); ?>">
                                <?php echo esc_html(ucfirst($subscription->status)); ?>
                            </span>
                            <?php if ($subscription->cancel_at_period_end): ?>
                                <br>
                                <small class="canceling">
                                    <?php echo esc_html__('Cancels at period end', 'cobra-ai'); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($plan): ?>
                                <?php echo esc_html($this->feature->format_price($plan['amount'], $plan['currency'])); ?>
                                <small><?php echo esc_html('/' . $plan['billing_interval']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date_i18n(
                                get_option('date_format'),
                                strtotime($subscription->created_at)
                            ); ?>
                        </td>
                        <td>
                            <?php if ($subscription->status === 'active' && !$subscription->cancel_at_period_end): ?>
                                <?php echo date_i18n(
                                    get_option('date_format'),
                                    strtotime($subscription->current_period_end)
                                ); ?>
                            <?php else: ?>
                                <?php echo esc_html__('N/A', 'cobra-ai'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo esc_url(add_query_arg([
                                                    'view' => 'subscription',
                                                    'id' => $subscription->id
                                                ])); ?>" class="button button-small">
                                        <?php echo esc_html__('View', 'cobra-ai'); ?>
                                    </a>
                                </span>
                                <?php if ($subscription->status === 'active' && !$subscription->cancel_at_period_end): ?>
                                    <span class="cancel">
                                        <button type="button"
                                            class="button button-small cancel-subscription"
                                            data-id="<?php echo esc_attr($subscription->subscription_id); ?>"
                                            data-nonce="<?php echo wp_create_nonce('cancel_subscription_' . $subscription->id); ?>">
                                            <?php echo esc_html__('Cancel', 'cobra-ai'); ?>
                                        </button>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <?php echo esc_html__('No subscriptions found.', 'cobra-ai'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    if ($this->feature->get_subscriptions()->get_pagination_args()['total_pages'] > 1):
        echo '<div class="tablenav bottom"><div class="tablenav-pages">';
        $this->pagination_links();
        echo '</div></div>';
    endif;
    ?>
</div>

<?php include __DIR__ . '/partials/modal-cancel.php'; ?>