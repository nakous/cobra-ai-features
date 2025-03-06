<?php
// views/public/partials/header.php
namespace CobraAI\Features\StripeSubscription\Views;

// Prevent direct access
defined('ABSPATH') || exit;
?>

<header class="cobra-subscription-header">
    <nav class="subscription-nav">
        <?php if (is_user_logged_in()): ?>
            <?php 
            $current_subscription = $this->get_user_subscription(get_current_user_id());
            $account_page = get_permalink(get_option('cobra_ai_account_page'));
            $plans_page = get_permalink(get_option('cobra_ai_plans_page'));
            ?>
            
            <ul class="nav-items">
                <li>
                    <a href="<?php echo esc_url($plans_page); ?>" 
                       class="<?php echo is_page($plans_page) ? 'active' : ''; ?>">
                        <?php echo esc_html__('Plans', 'cobra-ai'); ?>
                    </a>
                </li>

                <?php if ($current_subscription): ?>
                    <li>
                        <a href="<?php echo esc_url($account_page); ?>"
                           class="<?php echo is_page($account_page) ? 'active' : ''; ?>">
                            <?php echo esc_html__('My Subscription', 'cobra-ai'); ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="nav-account">
                <span class="user-name">
                    <?php echo esc_html(wp_get_current_user()->display_name); ?>
                </span>
                <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="logout-link">
                    <?php echo esc_html__('Logout', 'cobra-ai'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="nav-auth">
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="login-link">
                    <?php echo esc_html__('Login', 'cobra-ai'); ?>
                </a>
                <?php if (get_option('users_can_register')): ?>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="register-link">
                        <?php echo esc_html__('Register', 'cobra-ai'); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </nav>
</header>