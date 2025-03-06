<?php
// views/public/cancel.php
namespace CobraAI\Features\StripeSubscription\Views;

// Prevent direct access
defined('ABSPATH') || exit;

// Get subscription data
$subscription_id = sanitize_text_field($_GET['subscription'] ?? '');
$subscription = $this->admin->get_subscription_by_id($subscription_id);

if (!$subscription || $subscription->user_id !== get_current_user_id()) {
   // wp_die(__('Invalid subscription.', 'cobra-ai'));
}

$plan = $this->get_plan($subscription->plan_id);
?>

<div class="cobra-cancel-wrapper">
    <div class="cancel-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="64" height="64">
            <circle cx="12" cy="12" r="11" fill="#F44336"/>
            <path d="M8 8l8 8M8 16l8-8" stroke="#fff" stroke-width="2" fill="none"/>
        </svg>
    </div>

    <h1><?php echo esc_html__('Subscription Cancelled', 'cobra-ai'); ?></h1>
    
    <div class="cancel-details">
        <?php if ($subscription->cancel_at_period_end): ?>
            <p class="cancel-message">
                <?php echo sprintf(
                    esc_html__('Your subscription will remain active until %s.', 'cobra-ai'),
                    date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))
                ); ?>
            </p>
            <p class="access-notice">
                <?php echo esc_html__('You will continue to have access to all features until then.', 'cobra-ai'); ?>
            </p>
        <?php else: ?>
            <p class="cancel-message">
                <?php echo esc_html__('Your subscription has been cancelled immediately.', 'cobra-ai'); ?>
            </p>
            <p class="access-notice">
                <?php echo esc_html__('Your access has been revoked.', 'cobra-ai'); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="cancel-actions">
        <?php if ($subscription->cancel_at_period_end): ?>
            <button type="button" class="button resume-subscription"
                    data-id="<?php echo esc_attr($subscription->subscription_id); ?>"
                    data-nonce="<?php echo wp_create_nonce('resume_subscription_' . $subscription->id); ?>">
                <?php echo esc_html__('Resume Subscription', 'cobra-ai'); ?>
            </button>
        <?php else: ?>
            <a href="<?php echo esc_url(add_query_arg('plan', $plan->id, get_permalink($this->get_settings('checkout_page')))); ?>" 
               class="button resubscribe-button">
                <?php echo esc_html__('Resubscribe', 'cobra-ai'); ?>
            </a>
        <?php endif; ?>
        
        <a href="<?php echo esc_url(get_permalink($this->get_settings('plans_page'))); ?>" 
           class="button-link view-plans">
            <?php echo esc_html__('View Other Plans', 'cobra-ai'); ?>
        </a>
    </div>

    <div class="feedback-section">
        <h3><?php echo esc_html__('Help Us Improve', 'cobra-ai'); ?></h3>
        <p><?php echo esc_html__('Would you mind telling us why you cancelled?', 'cobra-ai'); ?></p>
        
        <form id="cancellation-feedback" class="feedback-form">
            <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->subscription_id); ?>">
            <?php wp_nonce_field('cancellation_feedback_' . $subscription->id); ?>
            
            <div class="feedback-options">
                <?php
                $feedback_options = [
                    'too_expensive' => __('Too expensive', 'cobra-ai'),
                    'not_using' => __('Not using enough', 'cobra-ai'),
                    'missing_features' => __('Missing features', 'cobra-ai'),
                    'found_alternative' => __('Found a better alternative', 'cobra-ai'),
                    'bugs_issues' => __('Bugs or technical issues', 'cobra-ai'),
                    'other' => __('Other reason', 'cobra-ai')
                ];
                
                foreach ($feedback_options as $value => $label):
                    ?>
                    <label class="feedback-option">
                        <input type="radio" name="cancel_reason" value="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="feedback-details" style="display: none;">
                <label for="cancel_details">
                    <?php echo esc_html__('Would you like to tell us more?', 'cobra-ai'); ?>
                </label>
                <textarea id="cancel_details" 
                         name="cancel_details" 
                         rows="3"
                         placeholder="<?php echo esc_attr__('Your feedback helps us improve our service...', 'cobra-ai'); ?>"></textarea>
            </div>

            <button type="submit" class="button">
                <?php echo esc_html__('Submit Feedback', 'cobra-ai'); ?>
            </button>
        </form>
    </div>

    <?php if ($this->get_settings('show_support_contact')): ?>
        <div class="support-contact"></div>
        <h3><?php echo esc_html__('Need Help?', 'cobra-ai'); ?></h3>
            <p>
                <?php echo sprintf(
                    esc_html__('If you have any questions or need assistance, please contact our support team at %s', 'cobra-ai'),
                    '<a href="mailto:' . esc_attr($this->get_settings('support_email')) . '">' . 
                    esc_html($this->get_settings('support_email')) . '</a>'
                ); ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide feedback details
    $('input[name="cancel_reason"]').on('change', function() {
        $('.feedback-details').slideDown();
    });

    // Handle feedback submission
    $('#cancellation-feedback').on('submit', async function(e) {
        e.preventDefault();
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        
        // Validate
        const reason = $form.find('input[name="cancel_reason"]:checked').val();
        if (!reason) {
            alert('<?php echo esc_js(__('Please select a reason for cancellation.', 'cobra-ai')); ?>');
            return;
        }

        $button.prop('disabled', true)
            .html('<?php echo esc_js(__('Submitting...', 'cobra-ai')); ?>');

        try {
            const response = await $.ajax({
                url: cobra_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_feedback',
                    subscription_id: $form.find('input[name="subscription_id"]').val(),
                    reason: reason,
                    details: $form.find('textarea[name="cancel_details"]').val(),
                    _ajax_nonce: $form.find('#_wpnonce').val()
                }
            });

            if (!response.success) {
                throw new Error(response.data.message || '<?php echo esc_js(__('Failed to submit feedback', 'cobra-ai')); ?>');
            }

            // Show success message
            $form.html('<p class="feedback-success"><?php echo esc_js(__('Thank you for your feedback!', 'cobra-ai')); ?></p>');

        } catch (error) {
            alert(error.message);
            $button.prop('disabled', false)
                .text('<?php echo esc_js(__('Submit Feedback', 'cobra-ai')); ?>');
        }
    });

    // Handle subscription resume
    $('.resume-subscription').on('click', async function() {
        const $button = $(this);
        const subscriptionId = $button.data('id');
        const nonce = $button.data('nonce');
        
        if (!confirm('<?php echo esc_js(__('Are you sure you want to resume your subscription?', 'cobra-ai')); ?>')) {
            return;
        }

        $button.prop('disabled', true)
            .html('<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

        try {
            const response = await $.ajax({
                url: cobra_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_resume',
                    subscription_id: subscriptionId,
                    _ajax_nonce: nonce
                }
            });

            if (!response.success) {
                throw new Error(response.data.message || '<?php echo esc_js(__('Failed to resume subscription', 'cobra-ai')); ?>');
            }

            // Redirect to account page
            window.location.href = '<?php echo esc_js(get_permalink(get_option('cobra_ai_account_page'))); ?>';

        } catch (error) {
            alert(error.message);
            $button.prop('disabled', false)
                .text('<?php echo esc_js(__('Resume Subscription', 'cobra-ai')); ?>');
        }
    });
});
</script>