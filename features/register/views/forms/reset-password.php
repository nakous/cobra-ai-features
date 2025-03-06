<?php
// Prevent direct access
defined('ABSPATH') || exit;

// Get reset key and user ID from URL
$reset_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
$user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;

// Verify reset key
$is_valid_key = $this->verify_reset_key($user_id, $reset_key);
?>

<div class="cobra-reset-password-wrapper">
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
            <p><?php _e('Your password has been reset successfully.', 'cobra-ai'); ?></p>
            <?php if ($this->get_page_url('login')): ?>
                <p>
                    <a href="<?php echo esc_url($this->get_page_url('login')); ?>" class="button">
                        <?php _e('Log in with your new password', 'cobra-ai'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

    <?php elseif (!$is_valid_key): ?>
        <div class="cobra-message error">
            <p><?php _e('This password reset link is invalid or has expired.', 'cobra-ai'); ?></p>
            <?php if ($this->get_page_url('forgot_password')): ?>
                <p>
                    <a href="<?php echo esc_url($this->get_page_url('forgot_password')); ?>">
                        <?php _e('Request a new password reset link', 'cobra-ai'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="cobra-form-header">
            <h2><?php _e('Reset Your Password', 'cobra-ai'); ?></h2>
            <p><?php _e('Please enter your new password below.', 'cobra-ai'); ?></p>
        </div>

        <form method="post" class="cobra-form" id="cobra-reset-password-form">
            <?php wp_nonce_field('cobra_reset_password', '_wpnonce'); ?>
            <input type="hidden" name="cobra_reset_password" value="1">
            <input type="hidden" name="key" value="<?php echo esc_attr($reset_key); ?>">
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">

            <div class="cobra-form-row">
                <label for="password">
                    <?php _e('New Password', 'cobra-ai'); ?>
                    <span class="required">*</span>
                </label>
                <div class="password-wrapper">
                    <input type="password" 
                           name="password" 
                           id="password" 
                           required
                           minlength="8"
                           autocomplete="new-password">
                    <button type="button" 
                            class="toggle-password" 
                            aria-label="<?php esc_attr_e('Toggle password visibility', 'cobra-ai'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
                <div class="password-strength-meter">
                    <div class="strength-bar"></div>
                    <span class="strength-text"></span>
                </div>
                <p class="description">
                    <?php _e('Password must be at least 8 characters long and include uppercase, lowercase, numbers, and special characters.', 'cobra-ai'); ?>
                </p>
            </div>

            <div class="cobra-form-row">
                <label for="confirm_password">
                    <?php _e('Confirm New Password', 'cobra-ai'); ?>
                    <span class="required">*</span>
                </label>
                <div class="password-wrapper">
                    <input type="password" 
                           name="confirm_password" 
                           id="confirm_password" 
                           required
                           autocomplete="new-password">
                    <button type="button" 
                            class="toggle-password" 
                            aria-label="<?php esc_attr_e('Toggle password visibility', 'cobra-ai'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
            </div>

            <?php if ($this->is_recaptcha_enabled()): ?>
                <div class="cobra-form-row recaptcha">
                    <?php $this->render_recaptcha('reset_password'); ?>
                </div>
            <?php endif; ?>

            <div class="cobra-form-row submit">
                <button type="submit" class="cobra-button">
                    <?php _e('Reset Password', 'cobra-ai'); ?>
                </button>
            </div>

            <div class="cobra-form-links">
                <?php if ($this->get_page_url('login')): ?>
                    <a href="<?php echo esc_url($this->get_page_url('login')); ?>">
                        <?php _e('Back to login', 'cobra-ai'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <div class="cobra-password-requirements">
            <h3><?php _e('Password Requirements', 'cobra-ai'); ?></h3>
            <ul>
                <li class="requirement" data-requirement="length">
                    <span class="dashicons"></span>
                    <?php _e('At least 8 characters long', 'cobra-ai'); ?>
                </li>
                <li class="requirement" data-requirement="uppercase">
                    <span class="dashicons"></span>
                    <?php _e('Contains uppercase letter', 'cobra-ai'); ?>
                </li>
                <li class="requirement" data-requirement="lowercase">
                    <span class="dashicons"></span>
                    <?php _e('Contains lowercase letter', 'cobra-ai'); ?>
                </li>
                <li class="requirement" data-requirement="number">
                    <span class="dashicons"></span>
                    <?php _e('Contains number', 'cobra-ai'); ?>
                </li>
                <li class="requirement" data-requirement="special">
                    <span class="dashicons"></span>
                    <?php _e('Contains special character', 'cobra-ai'); ?>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<style>
.cobra-reset-password-wrapper {
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
    margin-bottom: 20px;
}

.password-wrapper {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 0;
    color: #666;
    cursor: pointer;
}

.toggle-password:hover {
    color: #2271b1;
}

.password-strength-meter {
    margin-top: 5px;
    background: #f0f0f1;
    height: 4px;
    border-radius: 2px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0;
    transition: all 0.3s ease;
}

.strength-text {
    display: block;
    font-size: 12px;
    margin-top: 5px;
    color: #666;
}

.cobra-password-requirements {
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cobra-password-requirements h3 {
    margin: 0 0 15px;
    font-size: 16px;
    color: #1d2327;
}

.cobra-password-requirements ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.requirement {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    color: #666;
    font-size: 13px;
}

.requirement .dashicons {
    margin-right: 8px;
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.requirement.met {
    color: #00a32a;
}

.requirement.met .dashicons:before {
    content: "\f147"; /* Checkmark */
}

.requirement:not(.met) .dashicons:before {
    content: "\f335"; /* Cross */
    color: #cc1818;
}

/* Strength colors */
.strength-0 .strength-bar { width: 0; background: transparent; }
.strength-1 .strength-bar { width: 25%; background: #dc3232; }
.strength-2 .strength-bar { width: 50%; background: #dba617; }
.strength-3 .strength-bar { width: 75%; background: #7ad03a; }
.strength-4 .strength-bar { width: 100%; background: #00a32a; }

.strength-0 .strength-text { color: #dc3232; }
.strength-1 .strength-text { color: #dc3232; }
.strength-2 .strength-text { color: #dba617; }
.strength-3 .strength-text { color: #7ad03a; }
.strength-4 .strength-text { color: #00a32a; }

/* Responsive styles */
@media (max-width: 480px) {
    .cobra-reset-password-wrapper {
        padding: 10px;
    }

    .cobra-form,
    .cobra-password-requirements {
        padding: 15px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const form = $('#cobra-reset-password-form');
    const passwordInput = $('#password');
    const confirmInput = $('#confirm_password');
    const requirements = {
        length: /.{8,}/,
        uppercase: /[A-Z]/,
        lowercase: /[a-z]/,
        number: /[0-9]/,
        special: /[^A-Za-z0-9]/
    };

    // Password strength check
    function checkPasswordStrength(password) {
        let strength = 0;
        let meetsAll = true;

        // Check each requirement
        Object.entries(requirements).forEach(([key, regex]) => {
            const meetsRequirement = regex.test(password);
            $(`.requirement[data-requirement="${key}"]`).toggleClass('met', meetsRequirement);
            if (meetsRequirement) strength++;
            if (!meetsRequirement) meetsAll = false;
        });

        return { strength, meetsAll };
    }

    // Update password strength indicator
    function updateStrengthIndicator(password) {
        const { strength, meetsAll } = checkPasswordStrength(password);
        const strengthMeter = $('.password-strength-meter');
        const strengthText = $('.strength-text');

        // Remove previous strength classes
        strengthMeter.removeClass('strength-0 strength-1 strength-2 strength-3 strength-4');
        
        // Add new strength class
        strengthMeter.addClass(`strength-${strength}`);

        // Update strength text
        const strengthLabels = [
            cobraRegister.i18n.veryWeak,
            cobraRegister.i18n.weak,
            cobraRegister.i18n.medium,
            cobraRegister.i18n.strong,
            cobraRegister.i18n.veryStrong
        ];
        strengthText.text(strengthLabels[strength]);

        return meetsAll;
    }

    // Password input handler
    passwordInput.on('input', function() {
        updateStrengthIndicator($(this).val());
    });

    // Password confirmation handler
    confirmInput.on('input', function() {
        const passwordMatch = $(this).val() === passwordInput.val();
        $(this).toggleClass('error', !passwordMatch && $(this).val().length > 0);
    });

    // Password visibility toggle
    $('.toggle-password').on('click', function() {
        const input = $(this).siblings('input');
        const icon = $(this).find('.dashicons');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // Form submission
    form.on('submit', function(e) {
        const password = passwordInput.val();
        const confirm = confirmInput.val();
        const meetsRequirements = updateStrengthIndicator(password);

        if (!meetsRequirements) {
            e.preventDefault();
            alert(cobraRegister.i18n.passwordRequirements);
            return;
        }

        if (password !== confirm) {
            e.preventDefault();
            alert(cobraRegister.i18n.passwordMismatch);
            return;
        }

        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).addClass('loading');
    });
});
</script>