<?php
/**
 * Contact form template
 * 
 * @package CobraAI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Generate a unique ID for the form
$form_id = 'cobra-contact-form-' . uniqid();

// Prepare CSS classes
$form_classes = 'cobra-contact-form';
if (!empty($settings['styling']['css_class'])) {
    $form_classes .= ' ' . $settings['styling']['css_class'];
}

// Determine if form fields should be shown
$show_name = $show_name ?? true;
$show_email = $show_email ?? true;
$show_subject = $show_subject ?? true;
$show_message = $show_message ?? true;

// Get labels from settings
$name_label = $settings['fields']['name_label'] ?? __('Your Name', 'cobra-ai');
$email_label = $settings['fields']['email_label'] ?? __('Your Email', 'cobra-ai');
$subject_label = $settings['fields']['subject_label'] ?? __('Subject', 'cobra-ai');
$message_label = $settings['fields']['message_label'] ?? __('Your Message', 'cobra-ai');
$submit_button_text = $settings['fields']['submit_button_text'] ?? __('Send Message', 'cobra-ai');

// Default messages
$success_message = $atts['success_message'] ?? $settings['messages']['success'] ?? __('Thank you! Your message has been sent successfully.', 'cobra-ai');
$error_message = $atts['error_message'] ?? $settings['messages']['error'] ?? __('Sorry, there was an error sending your message. Please try again later.', 'cobra-ai');

// Include default CSS if enabled
if ($settings['styling']['include_default_css'] ?? true) {
    echo '<style>
        .cobra-contact-form {
            max-width: 100%;
            margin-bottom: 2rem;
        }
        .cobra-contact-form .form-group {
            margin-bottom: 1rem;
        }
        .cobra-contact-form .form-control {
            display: block;
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .cobra-contact-form .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .cobra-contact-form select.form-control {
            height: auto;
        }
        .cobra-contact-form textarea.form-control {
            min-height: 150px;
        }
        .cobra-contact-form .btn {
            display: inline-block;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            cursor: pointer;
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }
        .cobra-contact-form .btn:hover {
            color: #fff;
            background-color: #0069d9;
            border-color: #0062cc;
        }
        .cobra-contact-form .btn:focus {
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.5);
        }
        .cobra-contact-form .alert {
            position: relative;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }
        .cobra-contact-form .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .cobra-contact-form .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        /* Animated form styles */
        .animated-form .form-control {
            transition: all 0.3s ease-in-out;
        }
        .animated-form .form-control:focus {
            transform: translateY(-3px);
        }
        .animated-form .btn {
            transition: all 0.3s ease-in-out;
        }
        .animated-form .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>';
}
?>

<?php if (!empty($atts['title'])): ?>
    <h3 class="cobra-contact-form-title"><?php echo esc_html($atts['title']); ?></h3>
<?php endif; ?>

<div class="cobra-contact-form-container">
    <div id="<?php echo esc_attr($form_id); ?>-messages" class="cobra-contact-form-messages" style="display: none;"></div>
    
    <form id="<?php echo esc_attr($form_id); ?>" class="<?php echo esc_attr($form_classes); ?>" method="post">
        <?php if ($show_subject && $show_subject !== 'no'): ?>
            <div class="form-group">
                <select name="subject" class="form-control" required>
                    <option value=""><?php echo esc_html($subject_label); ?></option>
                    <?php foreach ($subject_options as $option): ?>
                        <option value="<?php echo esc_attr(strtolower(str_replace(' ', '-', $option))); ?>">
                            <?php echo esc_html($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($show_name): ?>
            <div class="form-group">
                <?php if (is_user_logged_in() && ($settings['fields']['autofill_for_logged_in'] ?? true)): ?>
                    <input type="text" name="name" placeholder="<?php echo esc_attr($name_label); ?>" 
                        value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" 
                        class="form-control" readonly>
                <?php else: ?>
                    <input type="text" name="name" placeholder="<?php echo esc_attr($name_label); ?>" 
                        class="form-control" required>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_email): ?>
            <div class="form-group">
                <?php if (is_user_logged_in() && ($settings['fields']['autofill_for_logged_in'] ?? true)): ?>
                    <input type="email" name="email" 
                        value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" 
                        class="form-control" readonly>
                <?php else: ?>
                    <input type="email" name="email" placeholder="<?php echo esc_attr($email_label); ?>" 
                        class="form-control" required>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_message): ?>
            <div class="form-group">
                <textarea name="message" placeholder="<?php echo esc_attr($message_label); ?>" 
                    class="form-control" required></textarea>
            </div>
        <?php endif; ?>

        <?php
        // Add reCAPTCHA if enabled
        if ($this->is_recaptcha_enabled()): ?>
            <div class="form-group recaptcha">
                <?php
                $action = 'cobra_contact_form_submit';
                global $cobra_ai;
                $recaptcha = $cobra_ai->get_feature('recaptcha');
                if ($recaptcha && method_exists($recaptcha, 'render_recaptcha')) {
                    $recaptcha->render_recaptcha($action);
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <?php wp_nonce_field('cobra_contact_form_submit', 'cobra_contact_nonce'); ?>
            <input type="hidden" name="action" value="cobra_contact_submit">
            <button type="submit" class="btn cobra-contact-submit">
                <?php echo esc_html($submit_button_text); ?>
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </form>
</div>

<script>
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $form = $('#<?php echo esc_js($form_id); ?>');
        const $messages = $('#<?php echo esc_js($form_id); ?>-messages');
        
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Clear previous messages
            $messages.empty().hide();
            
            // Disable submit button
            const $submitBtn = $form.find('.cobra-contact-submit');
            const submitBtnText = $submitBtn.html();
            $submitBtn.html('<i class="fas fa-circle-notch fa-spin"></i> <?php echo esc_js(__('Sending...', 'cobra-ai')); ?>').prop('disabled', true);
            
            // Collect form data
            const formData = new FormData($form[0]);
            
            // Add nonce
            formData.append('nonce', '<?php echo wp_create_nonce('cobra_contact_form_submit'); ?>');
            
            // Submit form via AJAX
            $.ajax({
                url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $messages.html('<div class="alert alert-success">' + response.data.message + '</div>').fadeIn();
                        
                        // Reset form
                        $form[0].reset();
                        
                        // Reset reCAPTCHA if present
                        if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.reset === 'function') {
                            grecaptcha.reset();
                        }
                        
                        <?php if (!empty($atts['redirect_url'])): ?>
                        // Redirect if URL provided
                        setTimeout(function() {
                            window.location.href = '<?php echo esc_js($atts['redirect_url']); ?>';
                        }, 2000);
                        <?php endif; ?>
                    } else {
                        // Show error message
                        $messages.html('<div class="alert alert-danger">' + response.data.message + '</div>').fadeIn();
                    }
                },
                error: function() {
                    // Show general error message
                    $messages.html('<div class="alert alert-danger"><?php echo esc_js($error_message); ?></div>').fadeIn();
                },
                complete: function() {
                    // Re-enable submit button
                    $submitBtn.html(submitBtnText).prop('disabled', false);
                }
            });
        });
    });
})(jQuery);
</script>