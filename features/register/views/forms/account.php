<?php
//'views/forms/account.php'
// Prevent direct access
defined('ABSPATH') || exit;

// Get current user
$current_user = wp_get_current_user();
?>

<div class="cobra-account-wrapper">
    <?php
    // Show messages if any
    if (!empty($errors->get_error_messages())): ?>
        <div class="cobra-message error">
            <?php foreach ($errors->get_error_messages() as $error): ?>
                <p><?php echo esc_html($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    // show message if the account is not verified

    if (!get_user_meta($user->ID, '_email_verified', true)): ?>
        <div class="cobra-message error">
            <p><?php _e('Your account is not verified. Please check your email for verification link.', 'cobra-ai'); ?></p>
            <!-- resend new verification link  -->
            <p><?php _e('If you did not receive the email, click the button below to resend the verification email.', 'cobra-ai'); ?></p>
            <button type="button" class="cobra-button send-verification-email"
                data-user-id="<?php echo esc_attr($user->ID); ?>">
                <?php _e('Resend Verification Email', 'cobra-ai'); ?>
            </button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="cobra-message success">
            <p><?php _e('Your profile has been updated successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Account Navigation -->
    <div class="cobra-account-nav">
        <ul class="cobra-tabs">
            <!-- add a hook for a dynanamic tabs, other feature can use this hook to display tabs -->

            <li>
                <a href="#profile" class="active" data-tab="profile">
                    <?php _e('Profile', 'cobra-ai'); ?>
                </a>
            </li>
            <?php
            // Display dynamic tabs added by other plugins
            do_action('cobra_register_profile_tab');
            ?>
            <li>
                <a href="#password" data-tab="password">
                    <?php _e('Change Password', 'cobra-ai'); ?>
                </a>
            </li>
            <?php if (!empty($settings['fields']['avatar']['enabled'])): ?>
                <li>
                    <a href="#avatar" data-tab="avatar">
                        <?php _e('Profile Picture', 'cobra-ai'); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li>
                <a href="#privacy" data-tab="privacy">
                    <?php _e('Privacy', 'cobra-ai'); ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Profile Tab -->
    <div class="cobra-tab-content active" id="profile-content">
        <form method="post" class="cobra-form" id="profile-form">
            <?php wp_nonce_field('cobra_update_profile', '_profile_nonce'); ?>
            <input type="hidden" name="cobra_update_account" value="update_profile">

            <?php
            // Display enabled fields
            foreach ($fields as $field_key => $field_config):
                // Skip password and avatar fields
                if (in_array($field_key, ['password', 'confirm_password', 'avatar'])) {
                    continue;
                }

                if (!$field_config['enabled']) {
                    continue;
                }

                // $field_value = $field_key === 'email'
                //     ? $current_user->user_email
                //     : get_user_meta($current_user->ID, $field_key, true);
                // if $field_key === username, get the username from the user object
                if ($field_key === 'username') {
                    $field_value = $current_user->user_login;
                } elseif ($field_key === 'email') {
                    $field_value = $current_user->user_email;
                } else {
                    $field_value = get_user_meta($current_user->ID, $field_key, true);
                }
            ?>
                <div class="cobra-form-row">
                    <label for="<?php echo esc_attr($field_key); ?>">
                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $field_key))); ?>
                        <?php if (!empty($field_config['required'])): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>

                    <?php if ($field_key === 'about'): ?>
                        <textarea name="<?php echo esc_attr($field_key); ?>"
                            id="<?php echo esc_attr($field_key); ?>"
                            rows="5"
                            <?php echo !empty($field_config['required']) ? 'required' : ''; ?>>
                            <?php echo esc_textarea($field_value); ?>
                        </textarea>

                    <?php elseif ($field_key === 'country'): ?>
                        <select name="<?php echo esc_attr($field_key); ?>"
                            id="<?php echo esc_attr($field_key); ?>"
                            <?php echo !empty($field_config['required']) ? 'required' : ''; ?>>
                            <option value=""><?php _e('Select Country', 'cobra-ai'); ?></option>
                            <?php
                            foreach ($this->get_countries() as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>"
                                    <?php selected($field_value, $code); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    <?php else: ?>
                        <input type="<?php echo $field_key === 'email' ? 'email' : 'text'; ?>"
                            name="<?php echo esc_attr($field_key); ?>"
                            id="<?php echo esc_attr($field_key); ?>"
                            value="<?php echo esc_attr($field_value); ?>"
                            <?php echo !empty($field_config['required']) ? 'required' : ''; ?>
                            <?php echo $field_key === 'email' || $field_key === 'username' ? 'readonly' : ''; ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="cobra-form-row">
                <button type="submit" class="cobra-button">
                    <?php _e('Update Profile', 'cobra-ai'); ?>
                </button>
                <!-- Logout btn -->
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>"
                    class="cobra-button button-secondary">
                    <?php _e('Logout', 'cobra-ai'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Password Tab -->
    <div class="cobra-tab-content" id="password-content">
        <form method="post" class="cobra-form" id="password-form">
            <?php wp_nonce_field('cobra_change_password', '_password_nonce'); ?>
            <input type="hidden" name="cobra_change_password" value="change_password">

            <div class="cobra-form-row">
                <label for="current_password">
                    <?php _e('Current Password', 'cobra-ai'); ?>
                    <span class="required">*</span>
                </label>
                <input type="password"
                    name="current_password"
                    id="current_password"
                    required>
            </div>

            <div class="cobra-form-row">
                <label for="new_password">
                    <?php _e('New Password', 'cobra-ai'); ?>
                    <span class="required">*</span>
                </label>
                <input type="password"
                    name="new_password"
                    id="new_password"
                    required
                    minlength="8">
                <div class="password-strength"></div>
            </div>

            <div class="cobra-form-row">
                <label for="confirm_password">
                    <?php _e('Confirm New Password', 'cobra-ai'); ?>
                    <span class="required">*</span>
                </label>
                <input type="password"
                    name="confirm_password"
                    id="confirm_password"
                    required>
            </div>

            <div class="cobra-form-row">
                <button type="submit" class="cobra-button">
                    <?php _e('Change Password', 'cobra-ai'); ?>
                </button>

            </div>
        </form>
    </div>
    <!-- add a hook for a dynanamic content tabs, other feature can use this hook to display tabs -->
    <!-- Avatar Tab -->
    <?php if (!empty($settings['fields']['avatar']['enabled'])): ?>
        <div class="cobra-tab-content" id="avatar-content">
            <form method="post" class="cobra-form" id="avatar-form" enctype="multipart/form-data">
                <?php wp_nonce_field('cobra_update_avatar', '_avatar_nonce'); ?>
                <input type="hidden" name="cobra_action" value="update_avatar">

                <div class="cobra-avatar-preview">
                    <?php
                    $avatar_id = get_user_meta($current_user->ID, 'avatar', true);
                    if ($avatar_id): ?>
                        <img src="<?php echo esc_url(wp_get_attachment_url($avatar_id)); ?>"
                            alt="<?php echo esc_attr($current_user->display_name); ?>"
                            class="current-avatar">
                    <?php else: ?>
                        <?php echo get_avatar($current_user->ID, 150); ?>
                    <?php endif; ?>
                </div>

                <div class="cobra-form-row">
                    <label for="avatar">
                        <?php _e('Upload New Picture', 'cobra-ai'); ?>
                    </label>
                    <input type="file"
                        name="avatar"
                        id="avatar"
                        accept="image/*">
                    <p class="description">
                        <?php _e('Maximum file size: 2MB. Allowed types: JPG, PNG, GIF', 'cobra-ai'); ?>
                    </p>
                </div>

                <?php if ($avatar_id): ?>
                    <div class="cobra-form-row">
                        <label>
                            <input type="checkbox" name="remove_avatar" value="1">
                            <?php _e('Remove current picture', 'cobra-ai'); ?>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="cobra-form-row">
                    <button type="submit" class="cobra-button">
                        <?php _e('Update Picture', 'cobra-ai'); ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Privacy Tab -->
    <div class="cobra-tab-content" id="privacy-content">
        <form method="post" class="cobra-form" id="privacy-form">
            <?php wp_nonce_field('cobra_update_privacy', '_privacy_nonce'); ?>
            <input type="hidden" name="cobra_action" value="update_privacy">

            <div class="cobra-form-row">
                <label>
                    <input type="checkbox"
                        name="profile_visibility"
                        value="public"
                        <?php checked(get_user_meta($current_user->ID, 'profile_visibility', true), 'public'); ?>>
                    <?php _e('Make my profile public', 'cobra-ai'); ?>
                </label>
                <p class="description">
                    <?php _e('Allow other users to view your profile information.', 'cobra-ai'); ?>
                </p>
            </div>

            <div class="cobra-form-row">
                <label>
                    <input type="checkbox"
                        name="email_notifications"
                        value="1"
                        <?php checked(get_user_meta($current_user->ID, 'email_notifications', true)); ?>>
                    <?php _e('Receive email notifications', 'cobra-ai'); ?>
                </label>
                <p class="description">
                    <?php _e('Receive updates and notifications via email.', 'cobra-ai'); ?>
                </p>
            </div>

            <div class="cobra-form-row">
                <button type="submit" class="cobra-button">
                    <?php _e('Update Privacy Settings', 'cobra-ai'); ?>
                </button>
            </div>
        </form>

        <div class="cobra-gdpr-section">
            <h3><?php _e('Your Privacy Rights', 'cobra-ai'); ?></h3>
            <p><?php _e('You have the right to:', 'cobra-ai'); ?></p>
            <ul>
                <li><?php _e('Request a copy of your data', 'cobra-ai'); ?></li>
                <li><?php _e('Request data correction', 'cobra-ai'); ?></li>
                <li><?php _e('Request data deletion', 'cobra-ai'); ?></li>
            </ul>

            <div class="cobra-form-row">
                <a href="<?php echo esc_url(get_privacy_policy_url()); ?>"
                    class="cobra-button"
                    target="_blank">
                    <?php _e('View Privacy Policy', 'cobra-ai'); ?>
                </a>
                <button type="button"
                    class="cobra-button button-secondary export-data">
                    <?php _e('Export My Data', 'cobra-ai'); ?>
                </button>
                <button type="button"
                    class="cobra-button button-danger delete-account">
                    <?php _e('Delete Account', 'cobra-ai'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php

    do_action('cobra_register_profile_tab_content');
    ?>

</div>

<style>
    .cobra-account-wrapper {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }

    .cobra-tabs {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0 !important;
        border-bottom: 1px solid #ddd;
    }

    .cobra-tabs li {
        margin: 0 !important;
    }

    .cobra-tabs a {
        display: block;
        padding: 10px 20px;
        text-decoration: none;
        color: #666;
        border: 1px solid transparent;
        margin-bottom: -1px;
    }

    .cobra-tabs a.active {
        color: #000;
        border-color: #ddd #ddd #fff;
        background: #fff;
    }

    .cobra-tab-content {
        display: none;
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-top: 0;
    }

    .cobra-tab-content.active {
        display: block;
    }

    .cobra-form-row {
        margin-bottom: 15px;
    }

    .cobra-form-row label {
        display: block;
        margin-bottom: 5px;
    }

    .cobra-form-row input[type="text"],
    .cobra-form-row input[type="email"],
    .cobra-form-row input[type="password"],
    .cobra-form-row select,
    .cobra-form-row textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .cobra-button {
        background: #2271b1;
        border: none;
        color: #fff;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
    }

    .cobra-button:hover {
        background: #135e96;
    }

    .cobra-button.button-secondary {
        background: #f0f0f1;
        color: #2c3338;
    }

    .cobra-button.button-danger {
        background: #dc3232;
    }

    .cobra-message {
        padding: 10px 15px;
        margin-bottom: 20px;
        border-left: 4px solid #00a32a;
        background: #fff;
    }

    .cobra-message.error {
        border-color: #dc3232;
    }

    .required {
        color: #dc3232;
    }

    .cobra-avatar-preview {
        text-align: center;
        margin-bottom: 20px;
    }

    .cobra-avatar-preview img {
        max-width: 150px;
        height: auto;
        border-radius: 50%;
    }

    .description {
        font-size: 13px;
        color: #666;
        margin-top: 5px;
    }

    .password-strength {
        height: 4px;
        margin-top: 5px;
        background: #eee;
        transition: all 0.3s;
    }

    .gdpr-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Tab handling
        $('.cobra-tabs a').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');

            // Update active tab
            $('.cobra-tabs a').removeClass('active');
            $(this).addClass('active');

            // Show selected content
            $('.cobra-tab-content').removeClass('active');
            $('#' + tab + '-content').addClass('active');

            // Update URL hash without scrolling
            history.pushState(null, null, $(this).attr('href'));
        });

        // Handle initial tab from URL hash
        if (window.location.hash) {
            $('.cobra-tabs a[href="' + window.location.hash + '"]').trigger('click');
        }

        // Password strength meter
        $('#new_password').on('input', function() {
            var password = $(this).val();
            var strength = checkPasswordStrength(password);
            var $meter = $('.password-strength');

            // Update strength meter
            $meter.css('width', strength.score * 25 + '%');

            // Update color based on strength
            var colors = ['#dc3232', '#dc3232', '#dba617', '#00a32a', '#00a32a'];
            $meter.css('background-color', colors[strength.score]);

            // Show strength message
            $(this).next('.description').remove();
            if (password.length > 0) {
                $(this).after('<p class="description">' + strength.message + '</p>');
            }
        });

        // Password confirmation
        $('#confirm_password').on('input', function() {
            var password = $('#new_password').val();
            var confirm = $(this).val();

            $(this).next('.description').remove();
            if (confirm.length > 0 && password !== confirm) {
                $(this).after('<p class="description error">' +
                    '<?php echo esc_js(__('Passwords do not match', 'cobra-ai')); ?>' +
                    '</p>');
            }
        });

        // Avatar preview
        $('#avatar').on('change', function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('.cobra-avatar-preview img').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

        // Handle avatar removal
        $('input[name="remove_avatar"]').on('change', function() {
            if (this.checked) {
                $('#avatar').prop('disabled', true);
            } else {
                $('#avatar').prop('disabled', false);
            }
        });

        // Export data
        $('.export-data').on('click', function() {
            if (confirm('<?php echo esc_js(__('Download a copy of your personal data?', 'cobra-ai')); ?>')) {
                $.ajax({
                    url: cobraAIRegister.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cobra_export_data',
                        nonce: cobraAIRegister.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.download_url;
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Failed to generate export. Please try again.', 'cobra-ai')); ?>');
                    }
                });
            }
        });

        // Delete account
        $('.delete-account').on('click', function() {
            if (confirm('<?php echo esc_js(__('Are you sure you want to delete your account? This action cannot be undone.', 'cobra-ai')); ?>')) {
                var password = prompt('<?php echo esc_js(__('Please enter your password to confirm account deletion:', 'cobra-ai')); ?>');

                if (password) {
                    $.ajax({
                        url: cobraAIRegister.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'cobra_delete_account',
                            password: password,
                            nonce: cobraAIRegister.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                window.location.href = response.data.redirect_url;
                            } else {
                                alert(response.data.message);
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('Failed to delete account. Please try again.', 'cobra-ai')); ?>');
                        }
                    });
                }
            }
        });

        // Form validation
        $('.cobra-form').on('submit', function(e) {
            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"]');

            // Disable submit button
            $submitButton.prop('disabled', true);

            // Clear previous messages
            $('.cobra-message').remove();

            // Add loading state
            $submitButton.prepend('<span class="spinner"></span>');
        });

        // Password strength checker
        function checkPasswordStrength(password) {
            var score = 0;
            var message = '';

            // Length check
            if (password.length < 8) {
                message = '<?php echo esc_js(__('Password is too short', 'cobra-ai')); ?>';
            } else {
                score++;

                // Complexity checks
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;

                // Set message based on score
                var messages = [
                    '<?php echo esc_js(__('Very Weak', 'cobra-ai')); ?>',
                    '<?php echo esc_js(__('Weak', 'cobra-ai')); ?>',
                    '<?php echo esc_js(__('Medium', 'cobra-ai')); ?>',
                    '<?php echo esc_js(__('Strong', 'cobra-ai')); ?>',
                    '<?php echo esc_js(__('Very Strong', 'cobra-ai')); ?>'
                ];
                message = messages[score];
            }

            return {
                score: score,
                message: message
            };
        }

        // Handle form errors
        $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
            $('.cobra-form button[type="submit"]').prop('disabled', false)
                .find('.spinner').remove();

            alert('<?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?>');
        });

        // Resend verification email
        $('.send-verification-email').on('click', function() {
            var userId = $(this).data('user-id');

            if (confirm('<?php echo esc_js(__('Resend verification email?', 'cobra-ai')); ?>')) {
                $.ajax({
                    url: cobraAIRegister.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cobra_resend_verification',
                        user_id: userId,
                        nonce: cobraAIRegister.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php echo esc_js(__('Verification email has been sent.', 'cobra-ai')); ?>');
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Failed to resend verification email. Please try again.', 'cobra-ai')); ?>');
                    }
                });
            }
        });
    });
</script>