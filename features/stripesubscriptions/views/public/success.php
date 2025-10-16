<?php
namespace CobraAI\Features\StripeSubscription\Views;

// Prevent direct access
defined('ABSPATH') || exit;

// Format subscription status
$status = $session->subscription->status;
$status_label = ucfirst($status);

// Format price
$price = $this->format_price($plan->amount, $plan->currency);

// Format trial end if applicable
$trial_end = $session->subscription->trial_end 
    ? date_i18n(get_option('date_format'), $session->subscription->trial_end)
    : null;
?>

<div class="cobra-success-wrapper">
    <div class="success-header">
        <div class="success-icon">
            <svg viewBox="0 0 24 24" width="48" height="48">
                <circle cx="12" cy="12" r="11" fill="#4CAF50"/>
                <path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none"/>
            </svg>
        </div>
        <h1><?php echo esc_html__('Subscription Activated!', 'cobra-ai'); ?></h1>
        <p class="success-message">
            <?php echo esc_html__('Thank you! Your subscription has been successfully activated.', 'cobra-ai'); ?>
        </p>
    </div>

    <div class="subscription-details">
        <div class="plan-header">
            <h2><?php echo esc_html($plan->name); ?></h2>
            <div class="plan-price">
                <span class="amount"><?php echo esc_html($price); ?></span>
                <span class="interval">
                    / <?php echo esc_html($plan->interval); ?>
                </span>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-row">
                <span class="detail-label"><?php echo esc_html__('Status', 'cobra-ai'); ?></span>
                <span class="detail-value status-badge status-<?php echo esc_attr($status); ?>">
                    <?php echo esc_html($status_label); ?>
                </span>
            </div>

            <?php if ($trial_end): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php echo esc_html__('Trial Ends', 'cobra-ai'); ?></span>
                    <span class="detail-value">
                        <?php echo esc_html($trial_end); ?>
                    </span>
                </div>
                <div class="trial-notice">
                    <span class="trial-icon">🎁</span>
                    <?php printf(
                        esc_html__('Your %d-day free trial has started. You can cancel anytime during this period.', 'cobra-ai'),
                        $plan->trial_days
                    ); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($plan->features)): ?>
            <div class="plan-features">
                <h3><?php echo esc_html__('Your Plan Includes', 'cobra-ai'); ?></h3>
                <ul>
                    <?php foreach ($plan->features as $feature): ?>
                        <li>
                            <span class="feature-check">✓</span>
                            <?php echo esc_html($feature); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="success-actions">
        <?php
        $account_page = $this->get_settings('account_page');
        if ($account_page):
        ?>
            <a href="<?php echo esc_url(get_permalink($account_page)); ?>" 
               class="button primary-button">
                <?php echo esc_html__('View My Subscription', 'cobra-ai'); ?>
            </a>
        <?php endif; ?>

        <a href="<?php echo esc_url(home_url()); ?>" 
           class="button secondary-button">
            <?php echo esc_html__('Return to Homepage', 'cobra-ai'); ?>
        </a>
    </div>

    <div class="confirmation-notice">
        <span class="notice-icon">📧</span>
        <div>
            <?php printf(
                esc_html__('A confirmation email has been sent to %s', 'cobra-ai'),
                '<strong>' . esc_html(wp_get_current_user()->user_email) . '</strong>'
            ); ?>
        </div>
    </div>
</div>
 