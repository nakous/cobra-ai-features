<?php
// views/admin/plans.php
namespace CobraAI\Features\StripeSubscription\Views;

// Prevent direct access
defined('ABSPATH') || exit;
?>

<div class="wrap cobra-stripe-plans">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Subscription Plans', 'cobra-ai'); ?>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=stripe_plan')); ?>" class="page-title-action">
            <?php echo esc_html__('Add New Plan', 'cobra-ai'); ?>
        </a>
        <button type="button" class="page-title-action" id="sync-plans">
            <?php echo esc_html__('Sync with Stripe', 'cobra-ai'); ?>
        </button>
    </h1>

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
                placeholder="<?php echo esc_attr__('Search plans...', 'cobra-ai'); ?>">

            <?php submit_button(__('Filter', 'cobra-ai'), 'secondary', 'filter', false); ?>

            <?php if ($current_status || $search_query): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $_GET['page'])); ?>"
                    class="button-secondary">
                    <?php echo esc_html__('Clear', 'cobra-ai'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Plans Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('Name', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Price', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Interval', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Active Subscribers', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                <th><?php echo esc_html__('Actions', 'cobra-ai'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($plans)): ?>
                <?php foreach ($plans as $plan):
                 
                    $subscriber_count = $this->feature->get_subscriptions()->get_subscriber_count($plan->ID);
                    $plan_meta = $this->feature->get_plans()->get_plan($plan->ID);
                  
                ?>
                    <tr>
                        <td class="column-name">
                            <strong>
                                <a href="#" class="edit-plan" data-id="<?php echo esc_attr($plan->ID); ?>">
                                    <?php echo esc_html($plan->post_title); ?>
                                </a>
                            </strong>
                            <?php if (!empty($plan->post_content)): ?>
                                <br>
                                <small class="description">
                                    <?php echo wp_kses_post($plan->post_content); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td class="column-price">
                            <?php

                            echo esc_html(
                                $this->feature->format_price($plan_meta['amount'] ?? 0) .
                                    ' ' . strtoupper($plan_meta['price_currency'] ?? 'USD')
                            ); ?>
                        </td>
                        <td class="column-interval">
                            <?php echo esc_html(
                                sprintf(
                                    __('Every %d %s', 'cobra-ai'),
                                    $plan_meta['interval_count'],
                                    $plan_meta['billing_interval']
                                )
                            ); ?>
                        </td>
                        <td class="column-subscribers">
                            <a href="<?php echo esc_url(add_query_arg([
                                            'page' => 'cobra-stripe-subscriptions',
                                            'plan' => $plan->id
                                        ], admin_url('admin.php'))); ?>">
                                <?php echo esc_html($subscriber_count); ?>
                            </a>
                        </td>
                        <td class="column-status">
                            <span class="plan-status status-<?php echo esc_attr($plan_meta['status']); ?>">
                                <?php echo esc_html(ucfirst($plan_meta['status']) ? 'Active' : 'Archived'); ?>
                            </span>
                        </td>
                        <td class="column-actions">
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $plan->ID . '&action=edit&post_type=stripe_plan')); ?>" class="edit-plan-link"> 
                                        <?php echo esc_html__('Edit', 'cobra-ai'); ?>
                                    </a> |
                                </span>
                                <?php if ($plan_meta['status'] === '1'): ?>
                                    <span class="archive">
                                        <a href="#"
                                            class="archive-plan"
                                            data-id="<?php echo esc_attr($plan->ID); ?>"
                                            data-nonce="<?php echo wp_create_nonce('archive_plan_' . $plan->ID); ?>">
                                            <?php echo esc_html__('Archive', 'cobra-ai'); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <?php echo esc_html__('No plans found.', 'cobra-ai'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

 
<script>
    jQuery(document).ready(function($) {
        let currentPlanId = null;
 
        // Save plan
        // $('#save-plan').on('click', function() {
        //     const $button = $(this);
        //     const $form = $('#plan-form');

        //     // Validate form
        //     if (!$form[0].checkValidity()) {
        //         $form[0].reportValidity();
        //         return;
        //     }

        //     // Disable button and show loading
        //     $button.prop('disabled', true)
        //         .html('<span class="spinner is-active"></span> <?php echo esc_js(__('Saving...', 'cobra-ai')); ?>');

        //     // Collect form data
        //     const formData = {
        //         action: 'cobra_subscription_create_plan',
        //         plan_id: currentPlanId,
        //         name: $('#plan_name').val(),
        //         description: $('#plan_description').val(),
        //         price: $('#plan_amount').val(),
        //         currency: $('#plan_currency').val(),
        //         interval: $('#plan_interval').val(),
        //         interval_count: $('#plan_interval_count').val(),
        //         trial_enabled: $('#plan_trial_enabled').is(':checked'),
        //         trial_days: $('#plan_trial_days').val(),
        //         features: [],
        //         public: $('#plan_public').is(':checked'),
        //         _ajax_nonce: $('#_wpnonce').val()
        //     };

        //     // Collect features
        //     $('.feature-item input').each(function() {
        //         if ($(this).val()) {
        //             formData.features.push($(this).val());
        //         }
        //     });

        //     // Save plan
        //     $.ajax({
        //         url: ajaxurl,
        //         type: 'POST',
        //         data: formData,
        //         success: function(response) {
        //             if (response.success) {
        //                 location.reload();
        //             } else {
        //                 alert(response.data.message || '<?php echo esc_js(__('Failed to save plan', 'cobra-ai')); ?>');
        //                 $button.prop('disabled', false)
        //                     .text('<?php echo esc_js(__('Save Plan', 'cobra-ai')); ?>');
        //             }
        //         },
        //         error: function() {
        //             alert('<?php echo esc_js(__('Failed to process request', 'cobra-ai')); ?>');
        //             $button.prop('disabled', false)
        //                 .text('<?php echo esc_js(__('Save Plan', 'cobra-ai')); ?>');
        //         }
        //     });
        // });

        // Archive plan
        $('.archive-plan').on('click', function(e) {
            e.preventDefault();
            const planId = $(this).data('id');
            const nonce = $(this).data('nonce');

            if (!confirm('<?php echo esc_js(__('Are you sure you want to archive this plan? Existing subscriptions will not be affected.', 'cobra-ai')); ?>')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_archive_plan',
                    plan_id: planId,
                    _ajax_nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('Failed to archive plan', 'cobra-ai')); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Failed to process request', 'cobra-ai')); ?>');
                }
            });
        });

        // Sync plans
        $('#sync-plans').on('click', function() {
            const $button = $(this);

            $button.prop('disabled', true)
                .html('<span class="spinner is-active"></span> <?php echo esc_js(__('Syncing...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_sync_plans',
                    _ajax_nonce: '<?php echo wp_create_nonce('cobra_sync_plans'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('Failed to sync plans', 'cobra-ai')); ?>');
                        $button.prop('disabled', false)
                            .text('<?php echo esc_js(__('Sync with Stripe', 'cobra-ai')); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Failed to process request', 'cobra-ai')); ?>');
                    $button.prop('disabled', false)
                        .text('<?php echo esc_js(__('Sync with Stripe', 'cobra-ai')); ?>');
                }
            });
        });

    });
</script>