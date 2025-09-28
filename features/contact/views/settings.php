<?php
/**
 * Contact Form Settings
 * 
 * @package CobraAI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = $this->get_settings();

// Check if reCAPTCHA feature is available
global $cobra_ai;
$recaptcha_feature = $cobra_ai->get_feature('recaptcha');
$recaptcha_available = $recaptcha_feature && method_exists($recaptcha_feature, 'is_ready');
?>

<div class="wrap">
    <h1><?php echo esc_html__('Contact Form Settings', 'cobra-ai'); ?></h1>

    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings saved successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">

        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Secondary menu', 'cobra-ai'); ?>">
            <a href="#fields-tab" class="nav-tab nav-tab-active" data-tab="fields-tab"><?php echo esc_html__('Form Fields', 'cobra-ai'); ?></a>
            <a href="#general-tab" class="nav-tab" data-tab="general-tab"><?php echo esc_html__('General', 'cobra-ai'); ?></a>
            <a href="#notifications-tab" class="nav-tab" data-tab="notifications-tab"><?php echo esc_html__('Notifications', 'cobra-ai'); ?></a>
            <a href="#messages-tab" class="nav-tab" data-tab="messages-tab"><?php echo esc_html__('Messages', 'cobra-ai'); ?></a>
            <a href="#styling-tab" class="nav-tab" data-tab="styling-tab"><?php echo esc_html__('Styling', 'cobra-ai'); ?></a>
        </nav>

        <div class="tab-content">
            <!-- Form Fields Settings -->
            <div id="fields-tab" class="tab-pane active">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Form Fields to Display', 'cobra-ai'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php echo esc_html__('Form Fields to Display', 'cobra-ai'); ?></legend>
                                
                                <label for="show_name">
                                    <input name="settings[fields][show_name]" type="checkbox" id="show_name" value="1" <?php checked($settings['fields']['show_name'] ?? true); ?>>
                                    <?php echo esc_html__('Name field', 'cobra-ai'); ?>
                                </label>
                                <br>
                                
                                <label for="show_email">
                                    <input name="settings[fields][show_email]" type="checkbox" id="show_email" value="1" <?php checked($settings['fields']['show_email'] ?? true); ?>>
                                    <?php echo esc_html__('Email field', 'cobra-ai'); ?>
                                </label>
                                <br>
                                
                                <label for="show_subject">
                                    <input name="settings[fields][show_subject]" type="checkbox" id="show_subject" value="1" <?php checked($settings['fields']['show_subject'] ?? true); ?>>
                                    <?php echo esc_html__('Subject field', 'cobra-ai'); ?>
                                </label>
                                <br>
                                
                                <label for="show_message">
                                    <input name="settings[fields][show_message]" type="checkbox" id="show_message" value="1" <?php checked($settings['fields']['show_message'] ?? true); ?>>
                                    <?php echo esc_html__('Message field', 'cobra-ai'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Field Labels', 'cobra-ai'); ?></th>
                        <td>
                            <p>
                                <label for="name_label"><?php echo esc_html__('Name Label:', 'cobra-ai'); ?></label>
                                <input name="settings[fields][name_label]" type="text" id="name_label" 
                                    value="<?php echo esc_attr($settings['fields']['name_label'] ?? __('Your Name', 'cobra-ai')); ?>" 
                                    class="regular-text">
                            </p>
                            
                            <p>
                                <label for="email_label"><?php echo esc_html__('Email Label:', 'cobra-ai'); ?></label>
                                <input name="settings[fields][email_label]" type="text" id="email_label" 
                                    value="<?php echo esc_attr($settings['fields']['email_label'] ?? __('Your Email', 'cobra-ai')); ?>" 
                                    class="regular-text">
                            </p>
                            
                            <p>
                                <label for="subject_label"><?php echo esc_html__('Subject Label:', 'cobra-ai'); ?></label>
                                <input name="settings[fields][subject_label]" type="text" id="subject_label" 
                                    value="<?php echo esc_attr($settings['fields']['subject_label'] ?? __('Subject', 'cobra-ai')); ?>" 
                                    class="regular-text">
                            </p>
                            
                            <p>
                                <label for="message_label"><?php echo esc_html__('Message Label:', 'cobra-ai'); ?></label>
                                <input name="settings[fields][message_label]" type="text" id="message_label" 
                                    value="<?php echo esc_attr($settings['fields']['message_label'] ?? __('Your Message', 'cobra-ai')); ?>" 
                                    class="regular-text">
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Subject Options', 'cobra-ai'); ?></th>
                        <td>
                            <textarea name="settings[subjects]" id="subjects" rows="5" class="large-text"><?php 
                                if (!empty($settings['subjects']) && is_array($settings['subjects'])) {
                                    echo esc_textarea(implode("\n", $settings['subjects']));
                                } else {
                                    echo "General Inquiry\nTechnical Support\nBilling Question\nFeedback";
                                }
                            ?></textarea>
                            <p class="description"><?php echo esc_html__('Enter one subject option per line. These will be shown in the dropdown.', 'cobra-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Button Text', 'cobra-ai'); ?></th>
                        <td>
                            <input name="settings[fields][submit_button_text]" type="text" id="submit_button_text" 
                                value="<?php echo esc_attr($settings['fields']['submit_button_text'] ?? __('Send Message', 'cobra-ai')); ?>" 
                                class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Autofill for Logged-in Users', 'cobra-ai'); ?></th>
                        <td>
                            <label for="autofill_for_logged_in">
                                <input name="settings[fields][autofill_for_logged_in]" type="checkbox" id="autofill_for_logged_in" 
                                    value="1" <?php checked($settings['fields']['autofill_for_logged_in'] ?? true); ?>>
                                <?php echo esc_html__('Automatically fill name and email for logged-in users', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- General Settings -->
            <div id="general-tab" class="tab-pane">
                <table class="form-table" role="presentation">
                    <!-- reCAPTCHA Setting -->
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable reCAPTCHA', 'cobra-ai'); ?></th>
                        <td>
                            <label for="use_recaptcha">
                                <input name="settings[general][use_recaptcha]" type="checkbox" id="use_recaptcha" 
                                    value="1" <?php checked($settings['general']['use_recaptcha'] ?? false); ?>
                                    <?php disabled(!$recaptcha_available); ?>>
                                <?php echo esc_html__('Protect form with Google reCAPTCHA', 'cobra-ai'); ?>
                            </label>
                            <p class="description">
                                <?php if ($recaptcha_available): ?>
                                    <?php echo esc_html__('This will add reCAPTCHA validation to prevent spam submissions.', 'cobra-ai'); ?>
                                <?php else: ?>
                                    <?php echo esc_html__('The reCAPTCHA feature is not active. Please activate and configure the reCAPTCHA feature in the Features page.', 'cobra-ai'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Notification Settings -->
            <div id="notifications-tab" class="tab-pane">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable Notifications', 'cobra-ai'); ?></th>
                        <td>
                            <label for="notifications_enabled">
                                <input name="settings[notifications][enabled]" type="checkbox" id="notifications_enabled" 
                                    value="1" <?php checked($settings['notifications']['enabled'] ?? true); ?>>
                                <?php echo esc_html__('Send email notifications for new submissions', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Notification Recipients', 'cobra-ai'); ?></th>
                        <td>
                            <input name="settings[notifications][recipients]" type="text" id="notification_recipients" 
                                value="<?php echo esc_attr($settings['notifications']['recipients'] ?? get_option('admin_email')); ?>" 
                                class="regular-text">
                            <p class="description"><?php echo esc_html__('Email addresses that will receive notifications. Separate multiple emails with commas.', 'cobra-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Notification Email Template', 'cobra-ai'); ?></th>
                        <td>
                            <textarea name="settings[notification_email_template]" id="notification_email_template" rows="10" class="large-text code"><?php 
                                if (!empty($settings['notification_email_template'])) {
                                    echo esc_textarea($settings['notification_email_template']);
                                } else {
                                    echo esc_textarea($this->get_default_notification_template());
                                }
                            ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('HTML template for notification emails. You can use these variables:', 'cobra-ai'); ?>
                                <br>
                                <code>{{site_name}}</code>, <code>{{name}}</code>, <code>{{email}}</code>, <code>{{subject}}</code>, <code>{{message}}</code>, <code>{{submission_link}}</code>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Reply Email Template', 'cobra-ai'); ?></th>
                        <td>
                            <textarea name="settings[reply_email_template]" id="reply_email_template" rows="10" class="large-text code"><?php 
                                if (!empty($settings['reply_email_template'])) {
                                    echo esc_textarea($settings['reply_email_template']);
                                } else {
                                    echo esc_textarea($this->get_default_reply_template());
                                }
                            ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('HTML template for reply emails. You can use these variables:', 'cobra-ai'); ?>
                                <br>
                                <code>{{name}}</code>, <code>{{subject}}</code>, <code>{{message}}</code>, <code>{{response}}</code>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Messages Settings -->
            <div id="messages-tab" class="tab-pane">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Success Message', 'cobra-ai'); ?></th>
                        <td>
                            <input name="settings[messages][success]" type="text" id="success_message" 
                                value="<?php echo esc_attr($settings['messages']['success'] ?? __('Thank you! Your message has been sent successfully.', 'cobra-ai')); ?>" 
                                class="large-text">
                            <p class="description"><?php echo esc_html__('Message displayed after successful form submission.', 'cobra-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Error Message', 'cobra-ai'); ?></th>
                        <td>
                            <input name="settings[messages][error]" type="text" id="error_message" 
                                value="<?php echo esc_attr($settings['messages']['error'] ?? __('Sorry, there was an error sending your message. Please try again later.', 'cobra-ai')); ?>" 
                                class="large-text">
                            <p class="description"><?php echo esc_html__('Message displayed when an error occurs during form submission.', 'cobra-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Required Fields Message', 'cobra-ai'); ?></th>
                        <td>
                            <input name="settings[messages][required_fields]" type="text" id="required_fields_message" 
                                value="<?php echo esc_attr($settings['messages']['required_fields'] ?? __('Please fill in all required fields.', 'cobra-ai')); ?>" 
                                class="large-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Invalid Email Message', 'cobra-ai'); ?></th>
                        <td>
                            <input name="settings[messages][invalid_email]" type="text" id="invalid_email_message" 
                                value="<?php echo esc_attr($settings['messages']['invalid_email'] ?? __('Please enter a valid email address.', 'cobra-ai')); ?>" 
                                class="large-text">
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Styling Settings -->
            <div id="styling-tab" class="tab-pane">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('CSS Class', 'cobra-ai'); ?></th>
                        <td>
                            <input name="settings[styling][css_class]" type="text" id="css_class" 
                                value="<?php echo esc_attr($settings['styling']['css_class'] ?? 'animated-form'); ?>" 
                                class="regular-text">
                            <p class="description"><?php echo esc_html__('Additional CSS class to add to the form.', 'cobra-ai'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php echo esc_html__('Include Default CSS', 'cobra-ai'); ?></th>
                        <td>
                            <label for="include_default_css">
                                <input name="settings[styling][include_default_css]" type="checkbox" id="include_default_css" 
                                    value="1" <?php checked($settings['styling']['include_default_css'] ?? true); ?>>
                                <?php echo esc_html__('Include default CSS styles for the form', 'cobra-ai'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Disable this if you want to style the form entirely with your theme\'s CSS.', 'cobra-ai'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
    
    <!-- Shortcode Info -->
    <div class="cobra-shortcode-info postbox">
        <h2 class="hndle"><?php echo esc_html__('Shortcode Usage', 'cobra-ai'); ?></h2>
        <div class="inside">
            <p><?php echo esc_html__('Use the following shortcode to display your contact form:', 'cobra-ai'); ?></p>
            <code>[cobra_contact_form]</code>
            
            <h3><?php echo esc_html__('Available Parameters', 'cobra-ai'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Parameter', 'cobra-ai'); ?></th>
                        <th><?php echo esc_html__('Description', 'cobra-ai'); ?></th>
                        <th><?php echo esc_html__('Example', 'cobra-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>title</code></td>
                        <td><?php echo esc_html__('Add a title above the form', 'cobra-ai'); ?></td>
                        <td><code>title="Contact Us"</code></td>
                    </tr>
                    <tr>
                        <td><code>subject_options</code></td>
                        <td><?php echo esc_html__('Custom subject options (comma-separated)', 'cobra-ai'); ?></td>
                        <td><code>subject_options="Question,Feedback,Support"</code></td>
                    </tr>
                    <tr>
                        <td><code>show_subject</code></td>
                        <td><?php echo esc_html__('Show or hide subject field', 'cobra-ai'); ?></td>
                        <td><code>show_subject="yes"</code> or <code>show_subject="no"</code></td>
                    </tr>
                    <tr>
                        <td><code>submit_button_text</code></td>
                        <td><?php echo esc_html__('Custom submit button text', 'cobra-ai'); ?></td>
                        <td><code>submit_button_text="Send Now"</code></td>
                    </tr>
                    <tr>
                        <td><code>success_message</code></td>
                        <td><?php echo esc_html__('Custom success message', 'cobra-ai'); ?></td>
                        <td><code>success_message="Thanks for contacting us!"</code></td>
                    </tr>
                    <tr>
                        <td><code>error_message</code></td>
                        <td><?php echo esc_html__('Custom error message', 'cobra-ai'); ?></td>
                        <td><code>error_message="Something went wrong. Try again."</code></td>
                    </tr>
                    <tr>
                        <td><code>redirect_url</code></td>
                        <td><?php echo esc_html__('URL to redirect after successful submission', 'cobra-ai'); ?></td>
                        <td><code>redirect_url="https://example.com/thank-you"</code></td>
                    </tr>
                </tbody>
            </table>
            
            <h3><?php echo esc_html__('Example Shortcode', 'cobra-ai'); ?></h3>
            <code>[cobra_contact_form title="Get in Touch" subject_options="General,Support,Billing" submit_button_text="Send Message"]</code>
        </div>
    </div>
</div>

<style>
.tab-content .tab-pane {
    display: none;
}
.tab-content .tab-pane.active {
    display: block;
}
.cobra-shortcode-info {
    margin-top: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        const target = $(this).data('tab');
        $('.tab-pane').removeClass('active');
        $('#' + target).addClass('active');
    });
    
    // Process textarea for subject options on form submit
    $('form').on('submit', function() {
        const subjects = $('#subjects').val().split('\n')
            .map(line => line.trim())
            .filter(line => line !== '');
            
        $('#subjects').val(subjects.join('\n'));
    });
});
</script>