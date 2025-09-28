<?php
/**
 * Email template: Subscription Cancelled
 * 
 * Available variables:
 * @var object $subscription Subscription data
 * @var object $plan Plan data
 * @var object $user User data
 */

if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php printf(__('Subscription Cancelled - %s', 'cobra-ai'), get_bloginfo('name')); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .email-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .cancellation-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .subscription-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .cta-button {
            display: inline-block;
            background-color: #007cba;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <?php if (has_custom_logo()): ?>
                <img src="<?php echo esc_url(wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full')); ?>" alt="<?php bloginfo('name'); ?>" class="logo">
            <?php else: ?>
                <h1><?php bloginfo('name'); ?></h1>
            <?php endif; ?>
        </div>

        <h2><?php printf(__('Subscription Cancelled - %s', 'cobra-ai'), esc_html($user->display_name)); ?></h2>

        <div class="cancellation-notice">
            <p><strong><?php _e('Your subscription has been cancelled.', 'cobra-ai'); ?></strong></p>
            
            <?php if ($subscription->cancel_at_period_end): ?>
                <p><?php printf(
                    __('You will continue to have access to %s until %s.', 'cobra-ai'),
                    esc_html($plan->name),
                    esc_html(date_i18n(get_option('date_format'), strtotime($subscription->current_period_end)))
                ); ?></p>
            <?php else: ?>
                <p><?php _e('Your access has been terminated immediately.', 'cobra-ai'); ?></p>
            <?php endif; ?>
        </div>

        <div class="subscription-details">
            <h3><?php _e('Cancelled Subscription Details', 'cobra-ai'); ?></h3>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Plan:', 'cobra-ai'); ?></span>
                <span class="detail-value"><?php echo esc_html($plan->name); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Cancellation Date:', 'cobra-ai'); ?></span>
                <span class="detail-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->updated_at))); ?></span>
            </div>
            
            <?php if ($subscription->cancel_at_period_end): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Access Until:', 'cobra-ai'); ?></span>
                    <span class="detail-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($subscription->cancel_reason)): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Reason:', 'cobra-ai'); ?></span>
                    <span class="detail-value"><?php echo esc_html($subscription->cancel_reason); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <p><?php _e('We\'re sorry to see you go! If you have any feedback about your experience or if there\'s anything we could have done better, please let us know.', 'cobra-ai'); ?></p>

        <div style="text-align: center;">
            <a href="<?php echo esc_url(home_url('/plans')); ?>" class="cta-button">
                <?php _e('View Plans', 'cobra-ai'); ?>
            </a>
            
            <a href="<?php echo esc_url(home_url('/contact')); ?>" class="cta-button" style="background-color: #6c757d; margin-left: 10px;">
                <?php _e('Contact Support', 'cobra-ai'); ?>
            </a>
        </div>

        <p><?php _e('Thank you for being a valued customer. You can resubscribe at any time.', 'cobra-ai'); ?></p>

        <div class="footer">
            <p><?php printf(__('This email was sent from %s', 'cobra-ai'), '<strong>' . get_bloginfo('name') . '</strong>'); ?></p>
        </div>
    </div>
</body>
</html>