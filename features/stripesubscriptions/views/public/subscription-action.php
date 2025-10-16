<?php
// Prevent direct access
if (!defined('ABSPATH')) exit;

// Variables passed from shortcode:
// $post_id, $post, $stripe_price_id, $plan_data, $current_user_id, $is_logged_in, $current_plan
?>

<div class="plan-action">
    <?php if (!$is_logged_in): ?>
        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="button login-to-subscribe">
            <?php echo esc_html__('Login to Subscribe', 'cobra-ai'); ?>
        </a>
        <p class="login-note">
            <?php echo esc_html__('Don\'t have an account?', 'cobra-ai'); ?>
            <a href="<?php echo esc_url(wp_registration_url()); ?>">
                <?php echo esc_html__('Sign up', 'cobra-ai'); ?>
            </a>
        </p>
    <?php elseif ($current_plan): ?>
        <div class="current-plan-notice">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php echo esc_html__('You\'re currently on this plan', 'cobra-ai'); ?>
        </div>
        <a href="<?php echo esc_url(get_permalink(get_option('cobra_ai_account_page'))); ?>" class="button secondary">
            <?php echo esc_html__('Manage Subscription', 'cobra-ai'); ?>
        </a>
    <?php else: ?>
        <button class="button subscribe-button"
            data-plan-id="<?php echo esc_attr($post_id); ?>"
            data-price-id="<?php echo esc_attr($stripe_price_id); ?>">
            <?php echo esc_html__('Subscribe Now', 'cobra-ai'); ?>
        </button>
        <?php if ($plan_data['trial_enabled']): ?>
            <p class="trial-note">
                <?php printf(
                    esc_html__('Start your %d-day free trial today', 'cobra-ai'),
                    absint($plan_data['trial_days'])
                ); ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($is_logged_in && !$current_plan): ?>
    <script>
        jQuery(document).ready(function($) {
            $('.subscribe-button').on('click', async function() {
                const button = $(this);
                const planId = button.data('plan-id');
                const priceId = button.data('price-id');

                // Visual feedback
                button.prop('disabled', true).addClass('processing')
                    .html('<span class="spinner"></span> <?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

                try {
                    const response = await $.ajax({
                        url: CobraSubscription.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'cobra_create_checkout_session',
                            plan_id: planId,
                            price_id: priceId,
                            nonce: CobraSubscription.nonce
                        }
                    });

                    if (!response.success) {
                        throw new Error(response.data.message || '<?php echo esc_js(__('Checkout failed', 'cobra-ai')); ?>');
                    }

                    // Redirect to checkout
                    window.location.href = response.data.checkout_url;

                } catch (error) {
                    console.error('Checkout error:', error);

                    // Show error to user
                    alert(error.message || '<?php echo esc_js(__('Failed to process subscription', 'cobra-ai')); ?>');

                    // Reset button
                    button.prop('disabled', false).removeClass('processing')
                        .text('<?php echo esc_js(__('Subscribe Now', 'cobra-ai')); ?>');
                }
            });
        });
    </script>
<?php endif; ?>