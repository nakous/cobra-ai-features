<?php
// Prevent direct access
defined('ABSPATH') || exit;
?>

<div class="cobra-register-wrapper">



    <?php
    // Show success message
    if ($success): ?>
        <div class="cobra-message success">
            <p><?php _e('Registration successful! Please check your email to verify your account.', 'cobra-ai'); ?></p>
        </div>
    <?php else: ?>
        <?php echo $this->render_error_messages(); ?>
        <form method="post" class="cobra-form" id="cobra-register-form" action="<?php echo esc_url($form_action); ?>">
            <?php wp_nonce_field('cobra_register', '_wpnonce'); ?>
            <input type="hidden" name="cobra_register" value="1">

            <?php
            // Required Fields Section
            $required_fields = [
                'username' => [
                    'label' => __('Username', 'cobra-ai'),
                    'type' => 'text',
                    'autocomplete' => 'username',
                    'pattern' => '[a-zA-Z0-9_-]{3,}',
                    'title' => __('Username must be at least 3 characters and may only contain letters, numbers, underscores and hyphens', 'cobra-ai')
                ],
                'email' => [
                    'label' => __('Email', 'cobra-ai'),
                    'type' => 'email',
                    'autocomplete' => 'email'
                ],
                'password' => [
                    'label' => __('Password', 'cobra-ai'),
                    'type' => 'password',
                    'autocomplete' => 'new-password'
                ],
                'confirm_password' => [
                    'label' => __('Confirm Password', 'cobra-ai'),
                    'type' => 'password',
                    'autocomplete' => 'new-password'
                ]
            ];

            foreach ($required_fields as $field_id => $field): ?>
                <div class="cobra-form-row">
                    <label for="<?php echo esc_attr($field_id); ?>">
                        <?php echo esc_html($field['label']); ?>
                        <span class="required">*</span>
                    </label>

                    <?php if ($field['type'] === 'password'): ?>
                        <div class="password-wrapper">
                            <input type="password"
                                name="<?php echo esc_attr($field_id); ?>"
                                id="<?php echo esc_attr($field_id); ?>"
                                required
                                value="<?php echo $this->get_field_value($field_id, 'register'); ?>"
                                autocomplete="<?php echo esc_attr($field['autocomplete']); ?>"
                                <?php if ($field_id === 'password'): ?>
                                minlength="8"
                                <?php endif; ?>>
                            <button type="button" class="toggle-password" aria-label="<?php esc_attr_e('Toggle password visibility', 'cobra-ai'); ?>">
                                <i class="eye-icon fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if ($field_id === 'password'): ?>
                            <div class="password-strength-meter">
                                <div class="strength-bar"></div>
                                <span class="strength-text"></span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <input type="<?php echo esc_attr($field['type']); ?>"
                            name="<?php echo esc_attr($field_id); ?>"
                            id="<?php echo esc_attr($field_id); ?>"
                            value="<?php echo $this->get_field_value($field_id, 'register'); ?>"
                            required
                            autocomplete="<?php echo esc_attr($field['autocomplete']); ?>"
                            <?php if (!empty($field['pattern'])): ?>
                            pattern="<?php echo esc_attr($field['pattern']); ?>"
                            title="<?php echo esc_attr($field['title']); ?>"
                            <?php endif; ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach;

            // Optional Fields
            foreach ($fields as $field_id => $field_config):
                // Skip required fields already handled
                if (isset($required_fields[$field_id])) {
                    continue;
                }

                // Skip disabled fields
                if (empty($field_config['enabled'])) {
                    continue;
                }
            ?>

                <div class="cobra-form-row">
                    <label for="<?php echo esc_attr($field_id); ?>">
                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $field_id))); ?>
                        <?php if (!empty($field_config['required'])): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>

                    <?php switch ($field_id):
                        case 'about': ?>
                            <textarea name="<?php echo esc_attr($field_id); ?>"
                                id="<?php echo esc_attr($field_id); ?>"
                                rows="4"
                                <?php echo !empty($field_config['required']) ? 'required' : ''; ?>>
                            <?php echo esc_textarea($this->get_field_value($field_id, 'register') ?? ''); ?>
                           
                        </textarea>
                        <?php break;

                        case 'country': ?>
                            <select name="<?php echo esc_attr($field_id); ?>"
                                id="<?php echo esc_attr($field_id); ?>"
                                <?php echo !empty($field_config['required']) ? 'required' : ''; ?>>
                                <option value=""><?php _e('Select Country', 'cobra-ai'); ?></option>
                                <?php foreach ($this->get_countries() as $code => $name): ?>
                                    <option value="<?php echo esc_attr($code); ?>"
                                        <?php selected($this->get_field_value($field_id, 'register') ?? '', $code); ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php break;

                        case 'phone': ?>
                            <input type="tel"
                                name="<?php echo esc_attr($field_id); ?>"
                                id="<?php echo esc_attr($field_id); ?>"
                                value="<?php echo esc_attr($this->get_field_value($field_id, 'register') ?? ''); ?>"
                                pattern="[0-9+\-\s()]+"
                                <?php echo !empty($field_config['required']) ? 'required' : ''; ?>>
                        <?php break;

                        default: ?>
                            <input type="text"
                                name="<?php echo esc_attr($field_id); ?>"
                                id="<?php echo esc_attr($field_id); ?>"
                                value="<?php echo esc_attr($this->get_field_value($field_id, 'register') ?? ''); ?>"
                                <?php echo !empty($field_config['required']) ? 'required' : ''; ?>>
                    <?php endswitch; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($this->is_recaptcha_enabled()): ?>
                <div class="cobra-form-row recaptcha">
                    <?php $this->render_recaptcha('register'); ?>
                </div>
            <?php endif; ?>

            <?php
            // Privacy Policy Agreement
            $privacy_url =  get_privacy_policy_url();
            if ($privacy_url): ?>
                <div class="cobra-form-row privacy-agreement">
                    <label>
                        <input type="checkbox"
                            name="privacy_agreement"
                            value="1"
                            required>
                        <?php printf(
                            __('I have read and agree to the %s', 'cobra-ai'),
                            '<a href="' . esc_url($privacy_url) . '" target="_blank">' .
                                __('Privacy Policy', 'cobra-ai') .
                                '</a>'
                        ); ?>
                        <span class="required">*</span>
                    </label>
                </div>
            <?php endif; ?>

            <div class="cobra-form-row submit">
                <button type="submit" class="cobra-button">
                    <?php _e('Register', 'cobra-ai'); ?>
                </button>
            </div>

            <?php if ($this->get_page_url('login')): ?>
                <div class="cobra-form-links">
                    <p>
                        <?php printf(
                            __('Already have an account? %s', 'cobra-ai'),
                            '<a href="' . esc_url($this->get_page_url('login')) . '">' .
                                __('Login here', 'cobra-ai') .
                                '</a>'
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </form>

    <?php endif; // End success check 
    ?>
</div>

<style>
    .cobra-register-wrapper {
        max-width: 600px;
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
    .cobra-form-row input[type="email"],
    .cobra-form-row input[type="password"],
    .cobra-form-row input[type="tel"],
    .cobra-form-row select,
    .cobra-form-row textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .cobra-form-row input:focus,
    .cobra-form-row select:focus,
    .cobra-form-row textarea:focus {
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

    .password-strength-meter {
        margin-top: 5px;
        background: #f0f0f1;
        height: 4px;
        border-radius: 2px;
    }

    .strength-bar {
        height: 100%;
        width: 0;
        background: #dc3232;
        border-radius: 2px;
        transition: all 0.3s;
    }

    .strength-text {
        display: block;
        font-size: 12px;
        margin-top: 5px;
        color: #666;
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
        text-align: center;
        margin-top: 15px;
    }

    .cobra-form-links a {
        color: #2271b1;
        text-decoration: none;
    }

    .cobra-form-links a:hover {
        color: #135e96;
        text-decoration: underline;
    }

    .privacy-agreement {
        font-size: 13px;
    }

    .privacy-agreement a {
        color: #2271b1;
        text-decoration: none;
    }

    .privacy-agreement a:hover {
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
        margin: 20px 0;
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
        .cobra-register-wrapper {
            padding: 10px;
        }

        .cobra-form {
            padding: 15px;
        }
    }
</style>