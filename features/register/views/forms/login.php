<?php
// Prevent direct access
defined('ABSPATH') || exit;
?>

<?php
// Hook for third-party login integrations (e.g., Google Auth)
do_action('cobra_before_login_form');
?>

 
<div class="cobra-login-wrapper">
    <?php
    // Show message if account just verified
    if (isset($_GET['verified']) && $_GET['verified'] === '1'): ?>
        <div class="cobra-message success">
            <p><?php _e('Your email has been verified successfully. You can now login.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>
    <?php
    // Show message if account is not verified because of verification link expired
    if (isset($_GET['verified']) && $_GET['verified'] === '0'): ?>
        <div class="cobra-message error">
            <p><?php _e('The verification link has expired. Please login to resend the verification email.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>
    <?php echo $this->render_error_messages(); ?>
    <form method="post" class="cobra-form" id="cobra-login-form" action="<?php echo esc_url($form_action); ?>">
        <?php wp_nonce_field('cobra_login', '_wpnonce'); ?>
        <input type="hidden" name="cobra_login" value="1">

        <div class="cobra-form-row">
            <label for="username">
                <?php _e('Username or Email', 'cobra-ai'); ?>
                <span class="required">*</span>
            </label>
            <input type="text"
                name="username"
                id="username"
                value="<?php echo $this->get_field_value('username', 'login'); ?>"
                required
                autocomplete="username">
        </div>

        <div class="cobra-form-row">
            <label for="password">
                <?php _e('Password', 'cobra-ai'); ?>
                <span class="required">*</span>
            </label>
            <div class="password-wrapper">
                <input type="password"
                    name="password"
                    id="password"
                    required
                    autocomplete="current-password">
                <button type="button" class="toggle-password" aria-label="<?php esc_attr_e('Toggle password visibility', 'cobra-ai'); ?>">
                    <i class="eye-icon fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div class="cobra-form-row remember-me">
            <label>
                <input type="checkbox" name="remember" value="1" <?php checked($this->get_field_value('remember', 'login'), '1'); ?>>
                <?php _e('Remember me', 'cobra-ai'); ?>
            </label>
        </div>

        <?php
        // Add reCAPTCHA if enabled
        if ($this->is_recaptcha_enabled()): ?>
            <div class="cobra-form-row recaptcha">
                <?php $this->render_recaptcha('cobra_login'); ?>
            </div>
        <?php endif; ?>

        <div class="cobra-form-row submit">
            <button type="submit" class="cobra-button">
                <?php _e('Login', 'cobra-ai'); ?>
            </button>
        </div>

        <div class="cobra-form-links">
            <?php if ($this->get_page_url('register')): ?>
                <a href="<?php echo esc_url($this->get_page_url('register')); ?>" class="register-link">
                    <?php _e('Create account', 'cobra-ai'); ?>
                </a>
            <?php endif; ?>

            <?php if ($this->get_page_url('forgot_password')): ?>
                <a href="<?php echo esc_url($this->get_page_url('forgot_password')); ?>" class="forgot-password-link">
                    <?php _e('Forgot password?', 'cobra-ai'); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($this->get_page_url('policy')): ?>
            <div class="cobra-form-policy">
                <p>
                    <?php printf(
                        __('By logging in, you agree to our %s', 'cobra-ai'),
                        '<a href="' . esc_url($this->get_page_url('policy')) . '">' .
                            __('Privacy Policy', 'cobra-ai') .
                            '</a>'
                    ); ?>
                </p>
            </div>
        <?php endif; ?>
    </form>
</div>

<style>
    .cobra-login-wrapper {
        max-width: 400px;
        margin: 0 auto;
        padding: 20px;
    }

    .cobra-form {
        background: #fff;
        padding: 25px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .cobra-form-row {
        margin-bottom: 20px;
    }

    .cobra-form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }

    .cobra-form-row input[type="text"],
    .cobra-form-row input[type="password"] {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .cobra-form-row input[type="text"]:focus,
    .cobra-form-row input[type="password"]:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
        outline: none;
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

    .remember-me {
        display: flex;
        align-items: center;
    }

    .remember-me input[type="checkbox"] {
        margin-right: 8px;
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
    }

    .cobra-button:hover {
        background: #135e96;
    }

    .cobra-form-links {
        display: flex;
        justify-content: space-between;
        margin-top: 15px;
    }

    .cobra-form-links a {
        color: #2271b1;
        text-decoration: none;
        font-size: 13px;
    }

    .cobra-form-links a:hover {
        color: #135e96;
        text-decoration: underline;
    }

    .cobra-form-policy {
        margin-top: 20px;
        text-align: center;
        font-size: 12px;
        color: #666;
    }

    .cobra-form-policy a {
        color: #2271b1;
        text-decoration: none;
    }

    .cobra-form-policy a:hover {
        text-decoration: underline;
    }

    .cobra-message {
        padding: 12px 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        border-left: 4px solid;
    }

    .cobra-message.error {
        background: #fbeaea;
        border-color: #dc3232;
    }

    .cobra-message.success {
        background: #edfaef;
        border-color: #46b450;
    }

    .required {
        color: #dc3232;
        margin-left: 2px;
    }

    .recaptcha {
        display: flex;
        justify-content: center;
    }

    /* Loading state */
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

    /* Responsive styles */
    @media (max-width: 480px) {
        .cobra-login-wrapper {
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
        // Password visibility toggle
     

        // Form submission
        // $('#cobra-login-form-sss').on('submit', function(e) {

        //     e.preventDefault();
        //     const $form = $(this);
        //     const $button = $form.find('button[type="submit"]');
        //     // Add loading state
        //     $button.addClass('loading');

        //     // // Store button text
        //     $button.data('original-text', $button.text());
        //     const formData = new FormData();
        //     formData.append('action', 'cobra_process_login');
        //     formData.append('username', $('#username').val());
        //     formData.append('password', $('#password').val());
        //     formData.append('remember', $('input[name="remember"]').is(':checked'));
        //     formData.append('_wpnonce', $('input[name="_wpnonce"]').val());

        //     // Add reCAPTCHA
        //     if (typeof grecaptcha !== 'undefined') {
        //         formData.append('g-recaptcha-response', grecaptcha.getResponse());
        //     }
        //     const ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
        //     $.ajax({
        //         url: ajaxurl,
        //         type: 'POST',
        //         data: formData,
        //         processData: false,
        //         contentType: false,
        //         success: function(response) {
        //             if (response.success) {
        //                 window.location.href = response.data.redirect_url;
        //             } else {
        //                 $('.cobra-message.error').remove();
        //                 $form.prepend(`<div class="cobra-message error"><p>${response.data.message}</p></div>`);
        //                 if (typeof grecaptcha !== 'undefined') {
        //                     grecaptcha.reset();
        //                 }
        //             }
        //         },
        //         error: function() {
        //             $('.cobra-message.error').remove();
        //             $form.prepend(`<div class="cobra-message error"><p><?php _e('An error occurred. Please try again.', 'cobra-ai'); ?></p></div>`);
        //             if (typeof grecaptcha !== 'undefined') {
        //                 grecaptcha.reset();
        //             }
        //         },
        //         complete: function() {
        //             $button.removeClass('loading');
        //         }
        //     });
        // });
    });
</script>