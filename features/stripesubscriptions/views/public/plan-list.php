<?php
// views/public/plan-list.php


// Prevent direct access
defined('ABSPATH') || exit;

// Get active plans
$plans = $this->get_plans()->get_plans(['status' => true, 'public' => true]);


// Get current subscription if user is logged in
$current_subscription = is_user_logged_in() ? $this->get_subscriptions()->get_user_subscription(get_current_user_id()) : null;
?>


<div class="cobra-plans-wrapper">
    <div class="plans-grid">
        <?php foreach ($plans as $plan):
            $price = get_post_meta($plan->ID, '_price_amount', true);
            $currency = get_post_meta($plan->ID, '_price_currency', true);
            $interval = get_post_meta($plan->ID, '_billing_interval', true);
            $is_current = $current_subscription && $current_subscription->plan_id === $plan->ID;
        ?>
            <div class="plan-card <?php echo $is_current ? 'current-plan' : ''; ?>">
                <?php if ($is_current): ?>
                    <div class="current-plan-badge"><?php esc_html_e('Current Plan', 'cobra-ai'); ?></div>
                <?php endif; ?>

                <div class="plan-header">
                    <h3><?php echo esc_html($plan->post_title); ?></h3>
                    <div class="plan-price">
                        <span class="amount"><?php echo esc_html($currency . $price); ?></span>
                        <span class="interval">/ <?php echo esc_html($interval); ?></span>
                    </div>
                    <?php echo wp_kses_post($plan->post_content); ?>
                </div>

                <div class="plan-actions">

                    <a href="<?php echo esc_url(get_permalink($plan->ID)); ?>" class="button">
                        <?php esc_html_e('View Plan', 'cobra-ai'); ?>
                    </a>

                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>