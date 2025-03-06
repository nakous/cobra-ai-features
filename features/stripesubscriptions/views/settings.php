<?php
// views/admin/settings.php
namespace CobraAI\Features\StripeSubscription\Views;

// Prevent direct access
defined('ABSPATH') || exit;

// Get current settings
$settings = $this->get_settings();

// Get pages for dropdowns
$pages = get_pages(['post_status' => 'publish']);
?>

<div class="wrap cobra-stripe-settings">
    <h1><?php echo esc_html__('Subscription Settings', 'cobra-ai'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">

        <!-- General Settings -->
        <div class="card">
            <h2><?php echo esc_html__('General Settings', 'cobra-ai'); ?></h2>
            
            <table class="form-table">
                <!-- Page Settings -->
                <tr>
                    <th scope="row">
                        <label for="checkout_page">
                            <?php echo esc_html__('Checkout Page', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="checkout_page" name="settings[checkout_page]" class="regular-text">
                            <option value=""><?php echo esc_html__('Select a page...', 'cobra-ai'); ?></option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" 
                                        <?php selected($settings['checkout_page'] ?? '', $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('The page where your checkout form will be displayed.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="success_page">
                            <?php echo esc_html__('Success Page', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="success_page" name="settings[success_page]" class="regular-text">
                            <option value=""><?php echo esc_html__('Select a page...', 'cobra-ai'); ?></option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" 
                                        <?php selected($settings['success_page'] ?? '', $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('The page users will be redirected to after successful subscription.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="cancel_page">
                            <?php echo esc_html__('Cancel Page', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="cancel_page" name="settings[cancel_page]" class="regular-text">
                            <option value=""><?php echo esc_html__('Select a page...', 'cobra-ai'); ?></option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" 
                                        <?php selected($settings['cancel_page'] ?? '', $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('The page users will see if they cancel their subscription.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Trial Settings -->
        <div class="card">
            <h2><?php echo esc_html__('Trial Settings', 'cobra-ai'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Trial Period', 'cobra-ai'); ?>
                    </th>
                    <td>
                        <label for="enable_trial">
                            <input type="checkbox" 
                                   id="enable_trial" 
                                   name="settings[enable_trial]" 
                                   value="1" 
                                   <?php checked($settings['enable_trial'] ?? false); ?>>
                            <?php echo esc_html__('Enable trial period for subscriptions', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>

                <tr class="trial-options" <?php echo empty($settings['enable_trial']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="trial_days">
                            <?php echo esc_html__('Trial Days', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="trial_days" 
                               name="settings[trial_days]" 
                               value="<?php echo esc_attr($settings['trial_days'] ?? 14); ?>" 
                               min="1" 
                               max="365" 
                               class="small-text">
                        <p class="description">
                            <?php echo esc_html__('Number of days for the trial period.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Cancellation Settings -->
        <div class="card">
            <h2><?php echo esc_html__('Cancellation Settings', 'cobra-ai'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Allow Cancellation', 'cobra-ai'); ?>
                    </th>
                    <td>
                        <label for="allow_cancellation">
                            <input type="checkbox" 
                                   id="allow_cancellation" 
                                   name="settings[allow_cancellation]" 
                                   value="1" 
                                   <?php checked($settings['allow_cancellation'] ?? true); ?>>
                            <?php echo esc_html__('Allow users to cancel their subscription', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>

                <tr class="cancellation-options" <?php echo empty($settings['allow_cancellation']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="cancellation_behavior">
                            <?php echo esc_html__('Cancellation Behavior', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="cancellation_behavior" 
                                name="settings[cancellation_behavior]" 
                                class="regular-text">
                            <option value="end_of_period" 
                                    <?php selected($settings['cancellation_behavior'] ?? 'end_of_period', 'end_of_period'); ?>>
                                <?php echo esc_html__('Cancel at end of billing period', 'cobra-ai'); ?>
                            </option>
                            <option value="immediate" 
                                    <?php selected($settings['cancellation_behavior'] ?? 'end_of_period', 'immediate'); ?>>
                                <?php echo esc_html__('Cancel immediately', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Email Settings -->
        <div class="card">
            <h2><?php echo esc_html__('Email Notifications', 'cobra-ai'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Email Notifications', 'cobra-ai'); ?>
                    </th>
                    <td>
                        <label for="email_notifications">
                            <input type="checkbox" 
                                   id="email_notifications" 
                                   name="settings[email_notifications]" 
                                   value="1" 
                                   <?php checked($settings['email_notifications'] ?? true); ?>>
                            <?php echo esc_html__('Enable email notifications', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>

                <tr class="email-options" <?php echo empty($settings['email_notifications']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <?php echo esc_html__('Notification Events', 'cobra-ai'); ?>
                    </th>
                    <td>
                        <?php
                        $notification_events = [
                            'subscription_created' => __('New subscription', 'cobra-ai'),
                            'subscription_cancelled' => __('Subscription cancelled', 'cobra-ai'),
                            'payment_succeeded' => __('Payment successful', 'cobra-ai'),
                            'payment_failed' => __('Payment failed', 'cobra-ai'),
                            'trial_ending' => __('Trial period ending', 'cobra-ai'),
                            'subscription_renewed' => __('Subscription renewed', 'cobra-ai')
                        ];
                        
                        foreach ($notification_events as $event => $label):
                            ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" 
                                       name="settings[notification_events][]" 
                                       value="<?php echo esc_attr($event); ?>" 
                                       <?php checked(in_array($event, $settings['notification_events'] ?? [])); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced Settings -->
        <div class="card">
            <h2><?php echo esc_html__('Advanced Settings', 'cobra-ai'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Webhooks', 'cobra-ai'); ?>
                    </th>
                    <td>
                        <label for="enable_webhooks">
                            <input type="checkbox" 
                                   id="enable_webhooks" 
                                   name="settings[enable_webhooks]" 
                                   value="1" 
                                   <?php checked($settings['enable_webhooks'] ?? true); ?>>
                            <?php echo esc_html__('Enable webhook processing', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="webhook_url">
                            <?php echo esc_html__('Webhook URL', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <code><?php echo esc_url(rest_url('cobra-ai/v1/stripe/webhook')); ?></code>
                        <p class="description">
                            <?php echo esc_html__('Add this URL in your Stripe webhook settings.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="webhook_secret">
                            <?php echo esc_html__('Webhook Secret', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="password" 
                               id="webhook_secret" 
                               name="settings[webhook_secret]" 
                               value="<?php echo esc_attr($settings['webhook_secret'] ?? ''); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Enter your webhook signing secret from Stripe.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Trial period toggle
    $('#enable_trial').on('change', function() {
        $('.trial-options').toggle($(this).is(':checked'));
    });

    // Cancellation options toggle
    $('#allow_cancellation').on('change', function() {
        $('.cancellation-options').toggle($(this).is(':checked'));
    });

    // Email notifications toggle
    $('#email_notifications').on('change', function() {
        $('.email-options').toggle($(this).is(':checked'));
    });

    // Copy webhook URL
    $('.copy-webhook').on('click', function(e) {
        e.preventDefault();
        const $this = $(this);
        const $url = $this.prev('code');
        
        navigator.clipboard.writeText($url.text()).then(function() {
            $this.text('<?php echo esc_js(__('Copied!', 'cobra-ai')); ?>');
            setTimeout(function() {
                $this.text('<?php echo esc_js(__('Copy', 'cobra-ai')); ?>');
            }, 2000);
        });
    });
});
</script>