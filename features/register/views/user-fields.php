<?php
// Prevent direct access
defined('ABSPATH') || exit;

// Get field settings
$settings = $this->get_settings();
$fields = $settings['fields'] ?? [];

// Get user meta data
$user_meta = get_user_meta($user->ID);

// Verify email status
$email_verified = get_user_meta($user->ID, '_email_verified', true);
$registration_date = get_user_meta($user->ID, '_registration_date', true);
?>

<h2><?php _e('Additional Information', 'cobra-ai'); ?></h2>
<table class="form-table">
    <!-- Verification Status -->
    <tr>
        <th>
            <label><?php _e('Email Verification', 'cobra-ai'); ?></label>
        </th>
        <td>
            <?php if ($email_verified): ?>
                <span class="verification-status verified">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Verified', 'cobra-ai'); ?>
                </span>
            <?php else: ?>
                <span class="verification-status unverified">
                    <span class="dashicons dashicons-no"></span>
                    <?php _e('Not Verified', 'cobra-ai'); ?>
                </span>
                <?php if (current_user_can('edit_users')): ?>
                    <button type="button" class="button send-verification-email" 
                            data-user-id="<?php echo esc_attr($user->ID); ?>">
                        <?php _e('Resend Verification Email', 'cobra-ai'); ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Registration Date -->
    <tr>
        <th>
            <label><?php _e('Registration Date', 'cobra-ai'); ?></label>
        </th>
        <td>
            <?php 
            if ($registration_date) {
                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration_date)));
            } else {
                _e('Unknown', 'cobra-ai');
            }
            ?>
        </td>
    </tr>

    <!-- Custom Fields -->
    <?php foreach ($fields as $field_key => $field_config):
        // Skip fields that are part of the default WordPress profile
        if (in_array($field_key, ['username', 'email', 'password', 'confirm_password', 'first_name', 'last_name'])) {
            continue;
        }

        // Skip disabled fields
        if (empty($field_config['enabled'])) {
            continue;
        }

        // Get field value
        $field_value = $user_meta[$field_key][0] ?? '';
        ?>
        <tr>
            <th>
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo esc_html($this->get_field_label($field_key)); ?>
                </label>
            </th>
            <td>
                <?php
                switch ($field_key):
                    case 'about':
                        ?>
                        <textarea name="<?php echo esc_attr($field_key); ?>" 
                                id="<?php echo esc_attr($field_key); ?>" 
                                rows="5" 
                                cols="30" 
                                class="regular-text">
                            <?php echo esc_textarea($field_value); ?>
                        </textarea>
                        <?php
                        break;

                    case 'avatar':
                        ?>
                        <div class="avatar-container">
                            <?php
                            $avatar_url = $field_value ? wp_get_attachment_url($field_value) : '';
                            if ($avatar_url): ?>
                                <img src="<?php echo esc_url($avatar_url); ?>" 
                                     alt="<?php echo esc_attr__('User Avatar', 'cobra-ai'); ?>" 
                                     class="current-avatar" />
                            <?php endif; ?>
                            <input type="hidden" name="<?php echo esc_attr($field_key); ?>" 
                                   value="<?php echo esc_attr($field_value); ?>" />
                            <button type="button" class="button upload-avatar">
                                <?php echo $avatar_url ? esc_html__('Change Avatar', 'cobra-ai') : esc_html__('Upload Avatar', 'cobra-ai'); ?>
                            </button>
                            <?php if ($avatar_url): ?>
                                <button type="button" class="button remove-avatar">
                                    <?php esc_html_e('Remove Avatar', 'cobra-ai'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;

                    case 'country':
                        ?>
                        <select name="<?php echo esc_attr($field_key); ?>" 
                                id="<?php echo esc_attr($field_key); ?>" 
                                class="regular-text">
                            <option value=""><?php _e('Select Country', 'cobra-ai'); ?></option>
                            <?php foreach ($this->get_countries() as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>" 
                                        <?php selected($field_value, $code); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        break;

                    case 'phone':
                        ?>
                        <input type="tel" 
                               name="<?php echo esc_attr($field_key); ?>" 
                               id="<?php echo esc_attr($field_key); ?>" 
                               value="<?php echo esc_attr($field_value); ?>" 
                               class="regular-text" 
                               pattern="[0-9\+\-\(\)\s]*" />
                        <?php
                        break;

                    case 'website':
                        ?>
                        <input type="url" 
                               name="<?php echo esc_attr($field_key); ?>" 
                               id="<?php echo esc_attr($field_key); ?>" 
                               value="<?php echo esc_attr($field_value); ?>" 
                               class="regular-text" />
                        <?php
                        break;

                    default:
                        ?>
                        <input type="text" 
                               name="<?php echo esc_attr($field_key); ?>" 
                               id="<?php echo esc_attr($field_key); ?>" 
                               value="<?php echo esc_attr($field_value); ?>" 
                               class="regular-text" />
                <?php endswitch; ?>

                <?php if (!empty($field_config['description'])): ?>
                    <p class="description"><?php echo esc_html($field_config['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
 
<style>
.verification-status {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 3px;
    margin-right: 10px;
}

.verification-status .dashicons {
    margin-right: 5px;
}

.verification-status.verified {
    background-color: #edfaef;
    color: #2fb344;
}

.verification-status.unverified {
    background-color: #fbeaea;
    color: #dc3232;
}

.avatar-container {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.current-avatar {
    max-width: 150px;
    height: auto;
    border-radius: 50%;
}

.upload-avatar,
.remove-avatar {
    margin-top: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Avatar upload handling
    $('.upload-avatar').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var container = button.closest('.avatar-container');
        var input = container.find('input[type="hidden"]');
        var frame = wp.media({
            title: '<?php echo esc_js(__('Select or Upload Avatar', 'cobra-ai')); ?>',
            button: {
                text: '<?php echo esc_js(__('Use as Avatar', 'cobra-ai')); ?>'
            },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            input.val(attachment.id);
            
            if (container.find('.current-avatar').length) {
                container.find('.current-avatar').attr('src', attachment.url);
            } else {
                container.prepend('<img src="' + attachment.url + '" class="current-avatar" />');
            }

            button.text('<?php echo esc_js(__('Change Avatar', 'cobra-ai')); ?>');
            
            if (!container.find('.remove-avatar').length) {
                container.append('<button type="button" class="button remove-avatar"><?php echo esc_js(__('Remove Avatar', 'cobra-ai')); ?></button>');
            }
        });

        frame.open();
    });

    // Avatar removal
    $(document).on('click', '.remove-avatar', function(e) {
        e.preventDefault();
        
        var container = $(this).closest('.avatar-container');
        container.find('input[type="hidden"]').val('');
        container.find('.current-avatar').remove();
        container.find('.upload-avatar').text('<?php echo esc_js(__('Upload Avatar', 'cobra-ai')); ?>');
        $(this).remove();
    });

    // Resend verification email
    $('.send-verification-email').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var userId = button.data('user-id');
        
        button.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_resend_verification',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('cobra_resend_verification'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Verification email sent successfully.', 'cobra-ai')); ?>');
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Failed to send verification email.', 'cobra-ai')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Failed to send verification email.', 'cobra-ai')); ?>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});
</script>