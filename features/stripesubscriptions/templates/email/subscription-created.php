<?php
/**
 * Email template: Subscription Created
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
    <title><?php printf(__('Welcome to %s', 'cobra-ai'), get_bloginfo('name')); ?></title>
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
            border-bottom: 2px solid #007cba;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 200px;
            height: auto;
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
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
        .detail-value {
            color: #212529;
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

        <h2><?php printf(__('Welcome to %s, %s!', 'cobra-ai'), esc_html($plan->name), esc_html($user->display_name)); ?></h2>

        <p><?php _e('Thank you for subscribing! Your subscription has been activated and you now have access to all the features included in your plan.', 'cobra-ai'); ?></p>

        <div class="subscription-details">
            <h3><?php _e('Subscription Details', 'cobra-ai'); ?></h3>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Plan:', 'cobra-ai'); ?></span>
                <span class="detail-value"><?php echo esc_html($plan->name); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Price:', 'cobra-ai'); ?></span>
                <span class="detail-value">
                    <?php echo esc_html(strtoupper($plan->currency)); ?> 
                    <?php echo esc_html(number_format($plan->amount / 100, 2)); ?>
                    / <?php echo esc_html($plan->interval); ?>
                </span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Status:', 'cobra-ai'); ?></span>
                <span class="detail-value"><?php echo esc_html(ucfirst($subscription->status)); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Next Billing Date:', 'cobra-ai'); ?></span>
                <span class="detail-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))); ?></span>
            </div>
            
            <?php if (!empty($subscription->trial_end) && strtotime($subscription->trial_end) > time()): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Trial Ends:', 'cobra-ai'); ?></span>
                    <span class="detail-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->trial_end))); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center;">
            <a href="<?php echo esc_url(home_url('/account')); ?>" class="cta-button">
                <?php _e('Manage Subscription', 'cobra-ai'); ?>
            </a>
        </div>

        <p><?php _e('If you have any questions about your subscription, please don\'t hesitate to contact our support team.', 'cobra-ai'); ?></p>

        <div class="footer">
            <p><?php printf(__('This email was sent from %s', 'cobra-ai'), '<strong>' . get_bloginfo('name') . '</strong>'); ?></p>
            <p><?php printf(__('You are receiving this because you subscribed to %s', 'cobra-ai'), esc_html($plan->name)); ?></p>
        </div>
    </div>
</body>
</html>