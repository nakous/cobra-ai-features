<?php
/**
 * Email template: Payment Failed
 * 
 * Available variables:
 * @var object $subscription Subscription data
 * @var object $plan Plan data
 * @var object $user User data
 * @var object $invoice Invoice data
 */

if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php printf(__('Payment Failed - %s', 'cobra-ai'), get_bloginfo('name')); ?></title>
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
        .alert {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
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
            background-color: #dc3545;
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

        <h2><?php printf(__('Payment Failed - %s', 'cobra-ai'), esc_html($user->display_name)); ?></h2>

        <div class="alert">
            <p><strong><?php _e('We were unable to process your payment.', 'cobra-ai'); ?></strong></p>
            <p><?php _e('Your subscription is currently past due. Please update your payment method to continue your service.', 'cobra-ai'); ?></p>
        </div>

        <div class="subscription-details">
            <h3><?php _e('Subscription Details', 'cobra-ai'); ?></h3>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Plan:', 'cobra-ai'); ?></span>
                <span class="detail-value"><?php echo esc_html($plan->name); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Amount Due:', 'cobra-ai'); ?></span>
                <span class="detail-value">
                    <?php echo esc_html(strtoupper($invoice->currency)); ?> 
                    <?php echo esc_html(number_format($invoice->amount_due / 100, 2)); ?>
                </span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Due Date:', 'cobra-ai'); ?></span>
                <span class="detail-value"><?php echo esc_html(date_i18n(get_option('date_format'), $invoice->due_date)); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label"><?php _e('Status:', 'cobra-ai'); ?></span>
                <span class="detail-value"><?php echo esc_html(ucfirst($subscription->status)); ?></span>
            </div>
        </div>

        <p><?php _e('To avoid interruption of your service, please update your payment method as soon as possible.', 'cobra-ai'); ?></p>

        <div style="text-align: center;">
            <a href="<?php echo esc_url(home_url('/account')); ?>" class="cta-button">
                <?php _e('Update Payment Method', 'cobra-ai'); ?>
            </a>
        </div>

        <h3><?php _e('What happens next?', 'cobra-ai'); ?></h3>
        <ul>
            <li><?php _e('We will retry your payment automatically over the next few days.', 'cobra-ai'); ?></li>
            <li><?php _e('You can update your payment method in your account settings.', 'cobra-ai'); ?></li>
            <li><?php _e('If payment is not successful within 7 days, your subscription may be cancelled.', 'cobra-ai'); ?></li>
        </ul>

        <p><?php _e('If you have any questions or need assistance, please contact our support team.', 'cobra-ai'); ?></p>

        <div class="footer">
            <p><?php printf(__('This email was sent from %s', 'cobra-ai'), '<strong>' . get_bloginfo('name') . '</strong>'); ?></p>
            <p><?php _e('You are receiving this because your payment failed for an active subscription.', 'cobra-ai'); ?></p>
        </div>
    </div>
</body>
</html>