<?php
/**
 * SMTP Settings View
 *
 * @package CobraAI
 */

defined('ABSPATH') || exit;

// Get settings
$settings = $this->get_settings();

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

// Define tabs
$tabs = [
    'general' => __('General', 'cobra-ai'),
    'smtp' => __('SMTP Settings', 'cobra-ai'),
    'pop' => __('POP Settings', 'cobra-ai'),
    'testing' => __('Test Email', 'cobra-ai'),
];

// Check for settings update
$updated = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
?>

<div class="wrap cobra-ai-smtp-settings">
    <h1 class="wp-heading-inline"><?php echo esc_html__('SMTP Settings', 'cobra-ai'); ?></h1>

    <?php if ($updated): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings saved successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <?php $this->display_settings_errors(); ?>

    <div class="smtp-tabs-container">
        <!-- Navigation Tabs -->
        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_id => $tab_name): ?>
                <a href="#<?php echo esc_attr($tab_id); ?>" 
                data-tab="<?php echo esc_attr($tab_id); ?>"
                class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_name); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="smtp-settings-form">
            <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
            <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
            <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">
            <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
            <?php wp_nonce_field('cobra_ai_smtp_test', 'email-test-nonce'); ?>

            <!-- General Settings -->
            <div id="general" class="settings-tab <?php echo $current_tab === 'general' ? 'active-tab' : ''; ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable SMTP', 'cobra-ai'); ?></th>
                        <td>
                            <label for="email-service-enabled">
                                <input type="checkbox" 
                                    id="email-service-enabled" 
                                    name="settings[enabled]" 
                                    value="1" 
                                    <?php checked($settings['enabled']); ?>>
                                <?php echo esc_html__('Enable custom email settings', 'cobra-ai'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('When enabled, WordPress will use your custom SMTP settings for sending emails.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="from-email"><?php echo esc_html__('From Email', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="email" 
                                id="from-email" 
                                name="settings[from_email]" 
                                value="<?php echo esc_attr($settings['from_email']); ?>" 
                                class="regular-text">
                            <p class="description">
                                <?php echo esc_html__('The email address that emails are sent from.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="from-name"><?php echo esc_html__('From Name', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="text" 
                                id="from-name" 
                                name="settings[from_name]" 
                                value="<?php echo esc_attr($settings['from_name']); ?>" 
                                class="regular-text">
                            <p class="description">
                                <?php echo esc_html__('The name that emails are sent from.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SMTP Settings -->
            <div id="smtp" class="settings-tab <?php echo $current_tab === 'smtp' ? 'active-tab' : ''; ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable SMTP', 'cobra-ai'); ?></th>
                        <td>
                            <label for="smtp-enabled">
                                <input type="checkbox" 
                                    id="smtp-enabled" 
                                    name="settings[smtp][enabled]" 
                                    value="1" 
                                    <?php checked(!empty($settings['smtp']['enabled'])); ?>>
                                <?php echo esc_html__('Send emails using SMTP', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="smtp-setting">
                        <th scope="row"><label for="smtp-host"><?php echo esc_html__('SMTP Host', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="text" 
                                id="smtp-host" 
                                name="settings[smtp][host]" 
                                value="<?php echo esc_attr($settings['smtp']['host']); ?>" 
                                class="regular-text">
                            <p class="description">
                                <?php echo esc_html__('The SMTP server hostname (e.g., smtp.gmail.com).', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr class="smtp-setting">
                        <th scope="row"><label for="smtp-port"><?php echo esc_html__('SMTP Port', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="number" 
                                id="smtp-port" 
                                name="settings[smtp][port]" 
                                value="<?php echo esc_attr($settings['smtp']['port']); ?>" 
                                class="small-text">
                            <p class="description">
                                <?php echo esc_html__('The SMTP server port (common ports: 25, 465, 587).', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr class="smtp-setting">
                        <th scope="row"><?php echo esc_html__('Encryption', 'cobra-ai'); ?></th>
                        <td>
                            <select id="smtp-encryption" name="settings[smtp][encryption]">
                                <option value="" <?php selected($settings['smtp']['encryption'], ''); ?>>
                                    <?php echo esc_html__('None', 'cobra-ai'); ?>
                                </option>
                                <option value="tls" <?php selected($settings['smtp']['encryption'], 'tls'); ?>>
                                    <?php echo esc_html__('TLS', 'cobra-ai'); ?>
                                </option>
                                <option value="ssl" <?php selected($settings['smtp']['encryption'], 'ssl'); ?>>
                                    <?php echo esc_html__('SSL', 'cobra-ai'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('The encryption method used to connect to your SMTP server.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr class="smtp-setting">
                        <th scope="row"><?php echo esc_html__('Authentication', 'cobra-ai'); ?></th>
                        <td>
                            <label for="smtp-auth">
                                <input type="checkbox" 
                                    id="smtp-auth" 
                                    name="settings[smtp][auth]" 
                                    value="1" 
                                    <?php checked(!empty($settings['smtp']['auth'])); ?>>
                                <?php echo esc_html__('Use SMTP authentication', 'cobra-ai'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Most SMTP servers require authentication.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr class="smtp-auth-setting">
                        <th scope="row"><label for="smtp-username"><?php echo esc_html__('Username', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="text" 
                                id="smtp-username" 
                                name="settings[smtp][username]" 
                                value="<?php echo esc_attr($settings['smtp']['username']); ?>" 
                                class="regular-text">
                        </td>
                    </tr>
                    <tr class="smtp-auth-setting">
                        <th scope="row"><label for="smtp-password"><?php echo esc_html__('Password', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="password" 
                                id="smtp-password" 
                                name="settings[smtp][password]" 
                                value="<?php echo esc_attr($settings['smtp']['password']); ?>" 
                                class="regular-text">
                            <p class="description">
                                <?php echo esc_html__('Your password is stored securely.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- POP Settings -->
            <div id="pop" class="settings-tab <?php echo $current_tab === 'pop' ? 'active-tab' : ''; ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable POP before SMTP', 'cobra-ai'); ?></th>
                        <td>
                            <label for="pop-enabled">
                                <input type="checkbox" 
                                    id="pop-enabled" 
                                    name="settings[pop][enabled]" 
                                    value="1" 
                                    <?php checked(!empty($settings['pop']['enabled'])); ?>>
                                <?php echo esc_html__('Use POP before SMTP authentication', 'cobra-ai'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Some servers require POP authentication before sending via SMTP.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr class="pop-setting">
                        <th scope="row"><label for="pop-host"><?php echo esc_html__('POP Host', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="text" 
                                id="pop-host" 
                                name="settings[pop][host]" 
                                value="<?php echo esc_attr($settings['pop']['host']); ?>" 
                                class="regular-text">
                            <p class="description">
                                <?php echo esc_html__('The POP server hostname (e.g., pop.gmail.com).', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr class="pop-setting">
                        <th scope="row"><label for="pop-port"><?php echo esc_html__('POP Port', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="number" 
                                id="pop-port" 
                                name="settings[pop][port]" 
                                value="<?php echo esc_attr($settings['pop']['port']); ?>" 
                                class="small-text">
                            <p class="description">
                                <?php echo esc_html__('The POP server port (common ports: 110, 995).', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr class="pop-setting">
                        <th scope="row"><label for="pop-username"><?php echo esc_html__('Username', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="text" 
                                id="pop-username" 
                                name="settings[pop][username]" 
                                value="<?php echo esc_attr($settings['pop']['username']); ?>" 
                                class="regular-text">
                        </td>
                    </tr>
                    <tr class="pop-setting">
                        <th scope="row"><label for="pop-password"><?php echo esc_html__('Password', 'cobra-ai'); ?></label></th>
                        <td>
                            <input type="password" 
                                id="pop-password" 
                                name="settings[pop][password]" 
                                value="<?php echo esc_attr($settings['pop']['password']); ?>" 
                                class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Test Email Settings -->
            <div id="testing" class="settings-tab <?php echo $current_tab === 'testing' ? 'active-tab' : ''; ?>">
                <div class="test-email-container">
                    <h3><?php echo esc_html__('Send Test Email', 'cobra-ai'); ?></h3>
                    <p>
                        <?php echo esc_html__('Use this form to test your email settings.', 'cobra-ai'); ?>
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="test-email-recipient"><?php echo esc_html__('To Email', 'cobra-ai'); ?></label></th>
                            <td>
                                <input type="email" 
                                    id="test-email-recipient" 
                                    name="settings[testing][recipient]" 
                                    value="<?php echo esc_attr($settings['testing']['recipient']); ?>" 
                                    class="regular-text" 
                                    required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="test-email-subject"><?php echo esc_html__('Subject', 'cobra-ai'); ?></label></th>
                            <td>
                                <input type="text" 
                                    id="test-email-subject" 
                                    name="settings[testing][subject]" 
                                    value="<?php echo esc_attr($settings['testing']['subject']); ?>" 
                                    class="regular-text" 
                                    required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="test-email-message"><?php echo esc_html__('Message', 'cobra-ai'); ?></label></th>
                            <td>
                                <textarea id="test-email-message" 
                                    name="settings[testing][message]" 
                                    rows="5" 
                                    class="large-text" 
                                    required><?php echo esc_textarea($settings['testing']['message']); ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <div class="test-email-actions">
                        <button type="button" id="send-test-email" class="button button-primary">
                            <?php echo esc_html__('Send Test Email', 'cobra-ai'); ?>
                        </button>
                        <span id="test-email-spinner" class="spinner"></span>
                        <div id="test-email-result" class="hidden notice"></div>
                    </div>
                </div>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Get tab ID
        var tabId = $(this).data('tab');
        console.log('Tab clicked: ' + tabId);
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show selected tab content, hide others
        $('.settings-tab').removeClass('active-tab').hide();
        $('#' + tabId).addClass('active-tab').show();
        
        // Update hidden input for form submission
        $('input[name="tab"]').val(tabId);
        
        // Update URL without refreshing
        if (history.pushState) {
            var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=cobra-ai-smtp&tab=' + tabId;
            window.history.pushState({path: newUrl}, '', newUrl);
        }
    });
    
    // Toggle SMTP settings
    function toggleSmtpSettings() {
        var enabled = $('#smtp-enabled').is(':checked');
        $('.smtp-setting').toggle(enabled);
        toggleSmtpAuthSettings();
    }

    // Toggle SMTP authentication settings
    function toggleSmtpAuthSettings() {
        var enabled = $('#smtp-enabled').is(':checked') && $('#smtp-auth').is(':checked');
        $('.smtp-auth-setting').toggle(enabled);
    }

    // Toggle POP settings
    function togglePopSettings() {
        var enabled = $('#pop-enabled').is(':checked');
        $('.pop-setting').toggle(enabled);
    }

    // Initialize settings
    toggleSmtpSettings();
    togglePopSettings();

    // Bind change events
    $('#smtp-enabled').on('change', toggleSmtpSettings);
    $('#smtp-auth').on('change', toggleSmtpAuthSettings);
    $('#pop-enabled').on('change', togglePopSettings);
    
    // Test email functionality
    $('#send-test-email').on('click', function() {
        var $button = $(this);
        var $spinner = $('#test-email-spinner');
        var $result = $('#test-email-result');
        
        // Validate form inputs
        var recipient = $('#test-email-recipient').val();
        var subject = $('#test-email-subject').val();
        var message = $('#test-email-message').val();
        
        if (!recipient || !subject || !message) {
            $result.removeClass('hidden notice-success')
                   .addClass('notice-error')
                   .html('<p>Please fill in all fields.</p>')
                   .show();
            return;
        }
        
        // Show loading state
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.addClass('hidden');
        
        // Send test email
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_ai_test_smtp_email',
                nonce: $('#email-test-nonce').val(),
                recipient: recipient,
                subject: subject,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('hidden notice-error')
                           .addClass('notice-success')
                           .html('<p>' + response.data.message + '</p>');
                } else {
                    $result.removeClass('hidden notice-success')
                           .addClass('notice-error')
                           .html('<p>' + response.data.message + '</p>');
                }
            },
            error: function() {
                $result.removeClass('hidden notice-success')
                       .addClass('notice-error')
                       .html('<p>An error occurred while sending the test email.</p>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Handle initial tab based on URL
    var urlParams = new URLSearchParams(window.location.search);
    var currentTab = urlParams.get('tab');
    if (currentTab) {
        $('.nav-tab[data-tab="' + currentTab + '"]').trigger('click');
    } else {
        // Default to first tab
        $('.nav-tab:first').trigger('click');
    }
});
</script>