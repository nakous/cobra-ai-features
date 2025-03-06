<?php
// Prevent direct access
defined('ABSPATH') || exit;
?>

<div class="cobra-forgot-password-wrapper">
    <?php 
    // Show errors if any
    if (!empty($errors->get_error_messages())): ?>
        <div class="cobra-message error">
            <?php foreach ($errors->get_error_messages() as $error): ?>
                <p><?php echo esc_html($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="cobra-message success">
            <p><?php _e('Password reset instructions have been sent to your email address.', 'cobra-ai'); ?></p>
            <?php if ($this->get_page_url('login')): ?>
                <p>
                    <a href="<?php echo esc_url($this->get_page_url('login')); ?>">
                        <?php _e('Return to login', 'cobra-ai'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <div class="cobra-form-header">
            <h2><?php _e('Reset Your Password', 'cobra-ai'); ?></h2>
            <p><?php _e('Enter your email address or username and we\'ll send you a link to reset your password.', 'cobra-ai'); ?></p>
        </div>

        <form method="post" class="cobra-form" id="cobra-forgot-password-form">
            <?php wp_nonce_field('cobra_forgot_password', '_wpnonce'); ?>
            <input type="hidden" name="cobra_forgot_password" value="1">

            <div class="cobra-form-row">
                <label for="user_login">
                    <?php _e('Email or Username', 'cobra-ai'); ?>
                    <span class="required">*</span>
                </label>
                <input type="text" 
                       name="user_login" 
                       id="user_login" 
                       value="<?php echo esc_attr($_POST['user_login'] ?? ''); ?>" 
                       required>
                <p class="description">
                    <?php _e('Enter the email address or username associated with your account.', 'cobra-ai'); ?>
                </p>
            </div>

            <?php if ($this->is_recaptcha_enabled()): ?>
                <div class="cobra-form-row recaptcha">
                    <?php $this->render_recaptcha('forgot_password'); ?>
                </div>
            <?php endif; ?>

            <div class="cobra-form-row submit">
                <button type="submit" class="cobra-button">
                    <?php _e('Send Reset Link', 'cobra-ai'); ?>
                </button>
            </div>

            <div class="cobra-form-links">
                <?php if ($this->get_page_url('login')): ?>
                    <a href="<?php echo esc_url($this->get_page_url('login')); ?>" class="login-link">
                        <?php _e('Back to login', 'cobra-ai'); ?>
                    </a>
                <?php endif; ?>

                <?php if ($this->get_page_url('register')): ?>
                    <a href="<?php echo esc_url($this->get_page_url('register')); ?>" class="register-link">
                        <?php _e('Create account', 'cobra-ai'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>

    <?php endif; ?>
</div>

<style>
.cobra-forgot-password-wrapper {
    max-width: 400px;
    margin: 0 auto;
    padding: 20px;
}

.cobra-form-header {
    text-align: center;
    margin-bottom: 30px;
}

.cobra-form-header h2 {
    margin-bottom: 10px;
    color: #1d2327;
}

.cobra-form-header p {
    color: #50575e;
    margin: 0;
}

.cobra-form {
    background: #fff;
    padding: 25px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cobra-form-row {
    margin-bottom: 20px;
}

.cobra-form-row:last-child {
    margin-bottom: 0;
}

.cobra-form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.cobra-form-row input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.cobra-form-row input[type="text"]:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

.cobra-button {
    background: #2271b1;
    border: none;
    color: #fff;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    width: 100%;
    transition: background-color 0.2s ease;
}

.cobra-button:hover {
    background: #135e96;
}

.cobra-button.loading {
    position: relative;
    color: transparent;
}

.cobra-button.loading::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 20px;
    height: 20px;
    border: 2px solid #fff;
    border-radius: 50%;
    border-right-color: transparent;
    transform: translate(-50%, -50%);
    animation: button-loading 0.8s linear infinite;
}

@keyframes button-loading {
    from {
        transform: translate(-50%, -50%) rotate(0deg);
    }
    to {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}

.cobra-form-links {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    font-size: 13px;
}

.cobra-form-links a {
    color: #2271b1;
    text-decoration: none;
    transition: color 0.2s ease;
}

.cobra-form-links a:hover {
    color: #135e96;
    text-decoration: underline;
}

.description {
    font-size: 12px;
    color: #646970;
    margin-top: 5px;
}

.required {
    color: #d63638;
    margin-left: 2px;
}

.recaptcha {
    display: flex;
    justify-content: center;
    margin: 20px 0;
}

.cobra-message {
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    border-left: 4px solid;
}

.cobra-message p:first-child {
    margin-top: 0;
}

.cobra-message p:last-child {
    margin-bottom: 0;
}

.cobra-message.error {
    background: #fbeaea;
    border-color: #d63638;
}

.cobra-message.success {
    background: #edfaef;
    border-color: #00a32a;
}

/* Responsive styles */
@media (max-width: 480px) {
    .cobra-forgot-password-wrapper {
        padding: 10px;
    }

    .cobra-form {
        padding: 15px;
    }

    .cobra-form-links {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const $form = $('#cobra-forgot-password-form');
    const $submitButton = $form.find('button[type="submit"]');
    
    $form.on('submit', function(e) {
        // Add loading state
        $submitButton.prop('disabled', true).addClass('loading');
        
        // Store original button text
        if (!$submitButton.data('original-text')) {
            $submitButton.data('original-text', $submitButton.text());
        }
    });
});
</script>