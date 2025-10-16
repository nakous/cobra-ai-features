<?php
/**
 * Shortcode template: Plan List
 * 
 * Available variables:
 * @var array $atts Shortcode attributes
 * @var array $plans Available plans
 */

if (!defined('ABSPATH')) exit;

// Extract attributes with defaults
$columns = absint($atts['columns'] ?? 3);
$show_trial = filter_var($atts['show_trial'] ?? true, FILTER_VALIDATE_BOOLEAN);
$show_features = filter_var($atts['show_features'] ?? true, FILTER_VALIDATE_BOOLEAN);
$highlight = sanitize_text_field($atts['highlight'] ?? '');
$currency = sanitize_text_field($atts['currency'] ?? 'USD');
$show_checkout = filter_var($atts['show_checkout'] ?? true, FILTER_VALIDATE_BOOLEAN);

// Get plans
$plans = $this->plans->get_plans([
    'status' => 'active',
    'public' => true,
    'currency' => $currency
]);

if (empty($plans)) {
    return '<div class="cobra-notice">' . esc_html__('No subscription plans available.', 'cobra-ai') . '</div>';
}

// Generate grid classes
$grid_class = 'cobra-plans-grid';
switch ($columns) {
    case 1:
        $grid_class .= ' cobra-grid-1';
        break;
    case 2:
        $grid_class .= ' cobra-grid-2';
        break;
    case 4:
        $grid_class .= ' cobra-grid-4';
        break;
    default:
        $grid_class .= ' cobra-grid-3';
}
?>

<div class="cobra-subscription-plans">
    <div class="<?php echo esc_attr($grid_class); ?>">
        <?php foreach ($plans as $plan): ?>
            <?php
            $is_highlighted = !empty($highlight) && $plan->id == $highlight;
            $plan_class = 'cobra-plan-card';
            if ($is_highlighted) {
                $plan_class .= ' cobra-plan-highlighted';
            }
            
            // Check user subscription status
            $user_subscribed = false;
            if (is_user_logged_in()) {
                $user_subscription = $this->Subscriptions->get_user_subscription(get_current_user_id(), $plan->id);
                $user_subscribed = $user_subscription && in_array($user_subscription->status, ['active', 'trialing']);
            }
            ?>
            
            <div class="<?php echo esc_attr($plan_class); ?>" data-plan-id="<?php echo esc_attr($plan->id); ?>">
                <?php if ($is_highlighted): ?>
                    <div class="cobra-plan-badge">
                        <?php esc_html_e('Popular', 'cobra-ai'); ?>
                    </div>
                <?php endif; ?>
                
                <div class="cobra-plan-header">
                    <h3 class="cobra-plan-name"><?php echo esc_html($plan->name); ?></h3>
                    <div class="cobra-plan-price">
                        <span class="cobra-price-amount">
                            <?php echo esc_html(strtoupper($plan->currency)); ?> 
                            <?php echo esc_html(number_format($plan->amount / 100, 2)); ?>
                        </span>
                        <span class="cobra-price-interval">
                            / <?php echo esc_html($plan->interval); ?>
                        </span>
                    </div>
                    
                    <?php if ($show_trial && !empty($plan->trial_days) && $plan->trial_days > 0): ?>
                        <div class="cobra-trial-period">
                            <?php printf(
                                esc_html__('%d days free trial', 'cobra-ai'), 
                                absint($plan->trial_days)
                            ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($plan->description)): ?>
                    <div class="cobra-plan-description">
                        <?php echo wp_kses_post(wpautop($plan->description)); ?>
                    </div>
                <?php endif; ?>

                <?php if ($show_features && !empty($plan->features)): ?>
                    <div class="cobra-plan-features">
                        <ul class="cobra-features-list">
                            <?php foreach ($plan->features as $feature): ?>
                                <li class="cobra-feature-item">
                                    <i class="cobra-icon cobra-icon-check" aria-hidden="true"></i>
                                    <?php echo esc_html($feature); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="cobra-plan-footer">
                    <?php if ($user_subscribed): ?>
                        <button class="cobra-btn cobra-btn-success cobra-btn-block" disabled>
                            <i class="cobra-icon cobra-icon-check"></i>
                            <?php esc_html_e('Current Plan', 'cobra-ai'); ?>
                        </button>
                    <?php elseif ($show_checkout): ?>
                        <?php if (is_user_logged_in()): ?>
                            <a href="#" 
                               class="cobra-btn cobra-btn-primary cobra-btn-block cobra-plan-checkout-btn" 
                               data-plan-id="<?php echo esc_attr($plan->id); ?>">
                                <?php esc_html_e('Get Started', 'cobra-ai'); ?>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" 
                               class="cobra-btn cobra-btn-primary cobra-btn-block">
                                <?php esc_html_e('Login to Subscribe', 'cobra-ai'); ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($show_checkout && is_user_logged_in()): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkoutButtons = document.querySelectorAll('.cobra-plan-checkout-btn');
    
    checkoutButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const planId = this.getAttribute('data-plan-id');
            if (typeof CobraStripeCheckout !== 'undefined') {
                CobraStripeCheckout.showModal(planId);
            } else {
                // Fallback: redirect to checkout page
                window.location.href = `<?php echo esc_url(add_query_arg('plan', '', get_permalink())); ?>${planId}`;
            }
        });
    });
});
</script>
<?php endif; ?>