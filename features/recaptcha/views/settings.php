<?php
// Prevent direct access
defined('ABSPATH') || exit;

// Get current settings and status
$settings = $this->get_settings();
$current_version = $settings['version'] ?? 'v2';
?>

<div class="wrap cobra-recaptcha-settings">
    <h1><?php echo esc_html($this->name . ' ' . __('Settings', 'cobra-ai')); ?></h1>

    <?php
    // Display validation errors
    $this->display_settings_errors();
    ?>

    <?php
    // Display success message
    if (isset($_GET['settings-updated']) && empty($validation_errors)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings updated successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">

        <!-- reCAPTCHA Version -->
        <div class="card">
            <h2><?php echo esc_html__('reCAPTCHA Version', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Version', 'cobra-ai'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="settings[version]" value="v2"
                                    <?php checked($current_version, 'v2'); ?>>
                                <?php echo esc_html__('reCAPTCHA v2 Checkbox', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="settings[version]" value="invisible"
                                    <?php checked($current_version, 'invisible'); ?>>
                                <?php echo esc_html__('Invisible reCAPTCHA', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="settings[version]" value="v3"
                                    <?php checked($current_version, 'v3'); ?>>
                                <?php echo esc_html__('reCAPTCHA v3', 'cobra-ai'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Select the reCAPTCHA version you want to use.', 'cobra-ai'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <!-- API Keys -->
        <div class="card">
            <h2><?php echo esc_html__('API Keys', 'cobra-ai'); ?></h2>
            <p class="description">
                <?php
                printf(
                    __('Get your API keys from %s', 'cobra-ai'),
                    '<a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a>'
                );
                ?>
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="site_key"><?php echo esc_html__('Site Key', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="site_key" name="settings[site_key]"
                            value="<?php echo esc_attr($settings['site_key'] ?? ''); ?>"
                            class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="secret_key"><?php echo esc_html__('Secret Key', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="secret_key" name="settings[secret_key]"
                            value="<?php echo esc_attr($settings['secret_key'] ?? ''); ?>"
                            class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Appearance Settings -->
        <div class="card" id="v2-settings" <?php echo $current_version !== 'v2' ? 'style="display:none;"' : ''; ?>>
            <h2><?php echo esc_html__('Appearance Settings', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Theme', 'cobra-ai'); ?></th>
                    <td>
                        <select name="settings[theme]">
                            <option value="light" <?php selected($settings['theme'] ?? 'light', 'light'); ?>>
                                <?php echo esc_html__('Light', 'cobra-ai'); ?>
                            </option>
                            <option value="dark" <?php selected($settings['theme'] ?? 'light', 'dark'); ?>>
                                <?php echo esc_html__('Dark', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Size', 'cobra-ai'); ?></th>
                    <td>
                        <select name="settings[size]">
                            <option value="normal" <?php selected($settings['size'] ?? 'normal', 'normal'); ?>>
                                <?php echo esc_html__('Normal', 'cobra-ai'); ?>
                            </option>
                            <option value="compact" <?php selected($settings['size'] ?? 'normal', 'compact'); ?>>
                                <?php echo esc_html__('Compact', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- V3 Settings -->
        <div class="card" id="v3-settings" <?php echo $current_version !== 'v3' ? 'style="display:none;"' : ''; ?>>
            <h2><?php echo esc_html__('V3 Settings', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="score_threshold"><?php echo esc_html__('Score Threshold', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="score_threshold" name="settings[score_threshold]"
                            value="<?php echo esc_attr($settings['score_threshold'] ?? '0.5'); ?>"
                            class="small-text" step="0.1" min="0" max="1">
                        <p class="description">
                            <?php echo esc_html__('Set the minimum score threshold (0.0 - 1.0). Higher values are more strict.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Badge Position -->
        <div class="card" id="badge-settings" <?php echo $current_version === 'v2' ? 'style="display:none;"' : ''; ?>>
            <h2><?php echo esc_html__('Badge Position', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Position', 'cobra-ai'); ?></th>
                    <td>
                        <select name="settings[badge_position]">
                            <option value="bottomright" <?php selected($settings['badge_position'] ?? 'bottomright', 'bottomright'); ?>>
                                <?php echo esc_html__('Bottom Right', 'cobra-ai'); ?>
                            </option>
                            <option value="bottomleft" <?php selected($settings['badge_position'] ?? 'bottomright', 'bottomleft'); ?>>
                                <?php echo esc_html__('Bottom Left', 'cobra-ai'); ?>
                            </option>
                            <option value="inline" <?php selected($settings['badge_position'] ?? 'bottomright', 'inline'); ?>>
                                <?php echo esc_html__('Inline', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Form Settings -->
        <div class="card">
            <h2><?php echo esc_html__('Form Protection', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Protected Forms', 'cobra-ai'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="settings[enabled_forms][login]" value="1"
                                    <?php checked($settings['enabled_forms']['login'] ?? false); ?>>
                                <?php echo esc_html__('Login Form', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="settings[enabled_forms][register]" value="1"
                                    <?php checked($settings['enabled_forms']['register'] ?? false); ?>>
                                <?php echo esc_html__('Registration Form', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="settings[enabled_forms][lostpassword]" value="1"
                                    <?php checked($settings['enabled_forms']['lostpassword'] ?? false); ?>>
                                <?php echo esc_html__('Lost Password Form', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="settings[enabled_forms][comments]" value="1"
                                    <?php checked($settings['enabled_forms']['comments'] ?? false); ?>>
                                <?php echo esc_html__('Comments Form', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="settings[enabled_forms][protected_posts]" value="1"
                                    <?php checked($settings['enabled_forms']['protected_posts'] ?? false); ?>>
                                <?php echo esc_html__('Protected Posts Form', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="settings[enabled_forms][contact_form]" value="1"
                                    <?php checked($settings['enabled_forms']['contact_form'] ?? false); ?>>
                                <?php echo esc_html__('Contact Form', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="settings[enabled_forms][testimonials]" value="1"
                                    <?php checked($settings['enabled_forms']['testimonials'] ?? false); ?>>
                                <?php echo esc_html__('Testimonials Form', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="settings[enabled_forms][custom_form]" value="1"
                                    <?php checked($settings['enabled_forms']['custom_form'] ?? false); ?>>
                                <?php echo esc_html__('Custom Form', 'cobra-ai'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr id="custom-form-settings" <?php echo empty($settings['enabled_forms']['custom_form']) ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="custom_form_selectors"><?php echo esc_html__('Custom Form Selectors', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <textarea id="custom_form_selectors" name="settings[custom_form_selectors]"
                            class="large-text code" rows="4"><?php echo esc_textarea($settings['custom_form_selectors'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('Enter CSS selectors for custom forms (one per line). Example: #my-form, .custom-form', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced Settings -->
        <div class="card">
            <h2><?php echo esc_html__('Advanced Settings', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="disable_submit"><?php echo esc_html__('Submit Button', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="disable_submit" name="settings[disable_submit]" value="1"
                                <?php checked($settings['disable_submit'] ?? true); ?>>
                            <?php echo esc_html__('Disable submit button until reCAPTCHA is completed', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="allowlisted_ips"><?php echo esc_html__('Allowlisted IPs', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <textarea id="allowlisted_ips" name="settings[allowlisted_ips]"
                            class="large-text code" rows="4"><?php
                                                                echo esc_textarea(is_array($settings['allowlisted_ips'] ?? '')
                                                                    ? implode("\n", $settings['allowlisted_ips'])
                                                                    : ($settings['allowlisted_ips'] ?? ''));
                                                                ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('Enter IP addresses to bypass reCAPTCHA (one per line)', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="language"><?php echo esc_html__('Language', 'cobra-ai'); ?></label>
                    </th>
                    <td>
                        <select id="language" name="settings[language]">
                            <?php
                            $languages = [
                                'ar' => 'Arabic',
                                'de' => 'German',
                                'en' => 'English',
                                'es' => 'Spanish',
                                'fr' => 'French',
                                'it' => 'Italian',
                                'ja' => 'Japanese',
                                'ko' => 'Korean',
                                'nl' => 'Dutch',
                                'pt' => 'Portuguese',
                                'ru' => 'Russian',
                                'zh' => 'Chinese'
                            ];
                            foreach ($languages as $code => $name) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($code),
                                    selected($settings['language'] ?? 'en', $code, false),
                                    esc_html($name)
                                );
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Select the language for the reCAPTCHA interface', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Error Messages -->
        <div class="card">
            <h2><?php echo esc_html__('Error Messages', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <?php
                $error_types = [
                    'missing_input_secret' => __('Missing Secret Key', 'cobra-ai'),
                    'invalid_input_secret' => __('Invalid Secret Key', 'cobra-ai'),
                    'missing_input_response' => __('Missing Response', 'cobra-ai'),
                    'invalid_input_response' => __('Invalid Response', 'cobra-ai'),
                    'bad_request' => __('Bad Request', 'cobra-ai'),
                    'timeout_or_duplicate' => __('Timeout or Duplicate', 'cobra-ai')
                ];

                foreach ($error_types as $type => $label) :
                ?>
                    <tr>
                        <th scope="row">
                            <label for="error_<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></label>
                        </th>
                        <td>
                            <input type="text" id="error_<?php echo esc_attr($type); ?>"
                                name="settings[error_messages][<?php echo esc_attr($type); ?>]"
                                value="<?php echo esc_attr($settings['error_messages'][$type] ?? ''); ?>"
                                class="large-text">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Test reCAPTCHA -->
        <div class="card">
            <h2><?php echo esc_html__('Test reCAPTCHA', 'cobra-ai'); ?></h2>
            <p><?php echo esc_html__('Use this form to test your reCAPTCHA configuration:', 'cobra-ai'); ?></p>
            <div id="recaptcha-test-form">
                <div class="recaptcha-wrapper"></div>
                <p>
                    <button type="button" class="button" id="test-recaptcha">
                        <?php echo esc_html__('Test reCAPTCHA', 'cobra-ai'); ?>
                    </button>
                </p>
                <div id="test-results" style="display:none;">
                    <h4><?php echo esc_html__('Test Results:', 'cobra-ai'); ?></h4>
                    <pre></pre>
                </div>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        // Toggle settings based on reCAPTCHA version
        $('input[name="settings[version]"]').on('change', function() {
            const version = $(this).val();
            $('#v2-settings').toggle(version === 'v2');
            $('#v3-settings').toggle(version === 'v3');
            $('#badge-settings').toggle(version !== 'v2');
        });

        // Toggle custom form settings
        $('input[name="settings[enabled_forms][custom_form]"]').on('change', function() {
            $('#custom-form-settings').toggle(this.checked);
        });

        // Test reCAPTCHA functionality
        $('#test-recaptcha').on('click', function() {
            const siteKey = $('#site_key').val();
            const secretKey = $('#secret_key').val();
            const version = $('input[name="settings[version]"]:checked').val();

            if (!siteKey || !secretKey) {
                alert('<?php echo esc_js(__('Please enter both site key and secret key first.', 'cobra-ai')); ?>');
                return;
            }

            const $button = $(this);
            const $results = $('#test-results');

            $button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_test_recaptcha',
                    nonce: '<?php echo wp_create_nonce('test-recaptcha'); ?>',
                    site_key: siteKey,
                    secret_key: secretKey,
                    version: version
                },
                success: function(response) {
                    // $results.show().find('pre').text(JSON.stringify(response, null, 2));
                    if (response.success) {
                        $results.show().find('pre').html(
                            formatTestResults(response.data.details)
                        );
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('Test failed. Please try again.', 'cobra-ai')); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Test failed. Please try again.', 'cobra-ai')); ?>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('<?php echo esc_js(__('Test reCAPTCHA', 'cobra-ai')); ?>');
                }
            });
        });
    });

    // Format test results for display
    function formatTestResults(results) {
        let html = '<div class="test-results-' + results.status + '">';

        // Add status indicator
        html += '<p><strong><?php echo esc_js(__('Status', 'cobra-ai')); ?>:</strong> ' +
            results.status.toUpperCase() + '</p>';

        // Add check results
        html += '<p><strong><?php echo esc_js(__('Checks', 'cobra-ai')); ?>:</strong></p>';
        html += '<ul>';
        for (let check in results.checks) {
            html += '<li>' + formatCheckName(check) + ': ' +
                (results.checks[check] ? '✅' : '❌') + '</li>';
        }
        html += '</ul>';

        // Add messages
        if (results.messages.length > 0) {
            html += '<p><strong><?php echo esc_js(__('Messages', 'cobra-ai')); ?>:</strong></p>';
            html += '<ul>';
            results.messages.forEach(function(message) {
                html += '<li>' + message + '</li>';
            });
            html += '</ul>';
        }

        html += '</div>';
        return html;
    }

    // Format check names for display
    function formatCheckName(check) {
        return check.replace(/_/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());
    }
</script>