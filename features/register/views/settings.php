<?php

// Prevent direct access
defined('ABSPATH') || exit;

// Get current settings and status
$settings = $this->get_settings();
$current_tab = $_GET['tab'] ?? 'general';

// Check if reCAPTCHA is available
$recaptcha_available = $this->is_recaptcha_available();

?>

<div class="wrap">
    <h1><?php echo esc_html($this->name . ' ' . __('Settings', 'cobra-ai')); ?></h1>
    <?php
    // Display validation errors
    $this->display_settings_errors();
    ?>
    <h2 class="nav-tab-wrapper">
        <a href="?page=cobra-ai-<?php echo esc_attr($this->feature_id); ?>&tab=general"
            class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'cobra-ai'); ?>
        </a>
        <a href="?page=cobra-ai-<?php echo esc_attr($this->feature_id); ?>&tab=pages"
            class="nav-tab <?php echo $current_tab === 'pages' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Pages', 'cobra-ai'); ?>
        </a>
        <a href="?page=cobra-ai-<?php echo esc_attr($this->feature_id); ?>&tab=emails"
            class="nav-tab <?php echo $current_tab === 'emails' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Emails', 'cobra-ai'); ?>
        </a>
        <a href="?page=cobra-ai-<?php echo esc_attr($this->feature_id); ?>&tab=fields"
            class="nav-tab <?php echo $current_tab === 'fields' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Fields', 'cobra-ai'); ?>
        </a>
    </h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">
        <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">


        <?php if ($current_tab === 'general'): ?>
            <!-- General Settings -->
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Disable Admin Menu', 'cobra-ai'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                name="settings[general][disable_admin_menu]"
                                value="1"
                                <?php checked(isset($settings['general']['disable_admin_menu']) && $settings['general']['disable_admin_menu']); ?>>
                            <?php _e('Hide admin menu for non-admin users', 'cobra-ai'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Use reCAPTCHA', 'cobra-ai'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                name="settings[general][use_recaptcha]"
                                value="1"
                                <?php checked(isset($settings['general']['use_recaptcha']) && $settings['general']['use_recaptcha']); ?>
                                <?php disabled(!$recaptcha_available); ?>>
                            <?php _e('Enable reCAPTCHA on forms', 'cobra-ai'); ?>
                        </label>
                        <?php if (!$recaptcha_available): ?>
                            <p class="description">
                                <?php _e('reCAPTCHA feature is not available or not properly configured.', 'cobra-ai'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Default Role', 'cobra-ai'); ?></th>
                    <td>
                        <select name="settings[general][default_role]">
                            <?php
                            $roles = get_editable_roles();
                            foreach ($roles as $role => $details) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($role),
                                    selected($settings['general']['default_role'], $role, false),
                                    esc_html($details['name'])
                                );
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>


        <?php elseif ($current_tab === 'pages'): ?>
            <!-- Pages Settings -->
            <table class="form-table">
                <!-- Pages Selection -->
                <?php
                // Get all pages
                $pages = get_pages([
                    'sort_column' => 'post_title',
                    'sort_order' => 'ASC',
                ]);

                // Define page fields
                $page_fields = [
                    'login' => [
                        'label' => __('Login Page', 'cobra-ai'),
                        'description' => __('Page containing the [cobra_login] shortcode.', 'cobra-ai'),
                        'shortcode' => '[cobra_login]'
                    ],
                    'register' => [
                        'label' => __('Registration Page', 'cobra-ai'),
                        'description' => __('Page containing the [cobra_register] shortcode.', 'cobra-ai'),
                        'shortcode' => '[cobra_register]'
                    ],
                    'forgot_password' => [
                        'label' => __('Forgot Password Page', 'cobra-ai'),
                        'description' => __('Page containing the [cobra_forgot_password] shortcode.', 'cobra-ai'),
                        'shortcode' => '[cobra_forgot_password]'
                    ],
                    'reset_password' => [
                        'label' => __('Reset Password Page', 'cobra-ai'),
                        'description' => __('Page containing the [cobra_reset_password] shortcode.', 'cobra-ai'),
                        'shortcode' => '[cobra_reset_password]'
                    ],
                    'account' => [
                        'label' => __('Account Page', 'cobra-ai'),
                        'description' => __('Page containing the [cobra_account] shortcode.', 'cobra-ai'),
                        'shortcode' => '[cobra_account]'
                    ]
                ];

                // Function to check if page exists and has correct content
                $check_page_status = function($page_id, $shortcode) {
                    if (empty($page_id)) {
                        return ['exists' => false, 'has_shortcode' => false];
                    }
                    
                    $page = get_post($page_id);
                    if (!$page || $page->post_status !== 'publish') {
                        return ['exists' => false, 'has_shortcode' => false];
                    }
                    
                    $has_shortcode = strpos($page->post_content, $shortcode) !== false;
                    return ['exists' => true, 'has_shortcode' => $has_shortcode, 'page' => $page];
                };

                foreach ($page_fields as $field => $config): 
                    $page_status = $check_page_status($settings['pages'][$field] ?? '', $config['shortcode']);
                    
                    // Reset setting if page doesn't exist
                    if (!empty($settings['pages'][$field]) && !$page_status['exists']) {
                        $settings['pages'][$field] = '';
                        // Note: Settings will be updated when user saves the form
                    }
                ?>
                    <tr>
                        <th scope="row">
                            <label for="page_<?php echo esc_attr($field); ?>">
                                <?php echo esc_html($config['label']); ?>
                            </label>
                        </th>
                        <td>
                            <select name="settings[pages][<?php echo esc_attr($field); ?>]"
                                id="page_<?php echo esc_attr($field); ?>"
                                class="regular-text">
                                <option value=""><?php _e('-- Select Page --', 'cobra-ai'); ?></option>
                                <?php foreach ($pages as $page): ?>
                                    <option value="<?php echo esc_attr($page->ID); ?>"
                                        <?php selected($settings['pages'][$field] ?? '', $page->ID); ?>>
                                        <?php echo esc_html($page->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php
                                echo esc_html($config['description']);
                                echo ' ';
                                printf(
                                    __('Shortcode: %s', 'cobra-ai'),
                                    '<code>' . esc_html($config['shortcode']) . '</code>'
                                );
                                ?>
                            </p>
                            <?php if (empty($settings['pages'][$field]) || !$page_status['exists']): ?>
                                <button type="button"
                                    class="button create-page"
                                    data-page="<?php echo esc_attr($field); ?>"
                                    data-title="<?php echo esc_attr($config['label']); ?>"
                                    data-shortcode="<?php echo esc_attr($config['shortcode']); ?>">
                                    <?php _e('Create Page', 'cobra-ai'); ?>
                                </button>
                                <?php if (!empty($settings['pages'][$field]) && !$page_status['exists']): ?>
                                    <p class="description" style="color: #d63638;">
                                        <strong><?php _e('Warning:', 'cobra-ai'); ?></strong> 
                                        <?php _e('The selected page no longer exists and has been reset.', 'cobra-ai'); ?>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!$page_status['has_shortcode']): ?>
                                    <p class="description" style="color: #d63638;">
                                        <strong><?php _e('Warning:', 'cobra-ai'); ?></strong> 
                                        <?php printf(__('The page does not contain the required shortcode %s', 'cobra-ai'), '<code>' . esc_html($config['shortcode']) . '</code>'); ?>
                                    </p>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(get_edit_post_link($settings['pages'][$field])); ?>"
                                    class="button"
                                    target="_blank">
                                    <?php _e('Edit Page', 'cobra-ai'); ?>
                                </a>
                                <a href="<?php echo esc_url(get_permalink($settings['pages'][$field])); ?>"
                                    class="button"
                                    target="_blank">
                                    <?php _e('View Page', 'cobra-ai'); ?>
                                </a>
                                <button type="button"
                                    class="button button-secondary reset-page"
                                    data-field="<?php echo esc_attr($field); ?>"
                                    title="<?php _e('Reset page selection', 'cobra-ai'); ?>">
                                    <?php _e('Reset', 'cobra-ai'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- URLs -->
                <tr>
                    <th scope="row" colspan="2">
                        <h3 class="title"><?php _e('Redirects & URLs', 'cobra-ai'); ?></h3>
                    </th>
                </tr>

                <?php
                // Define redirect fields with page selectors
                $redirect_fields = [
                    'after_login' => [
                        'label' => __('Redirect After Login', 'cobra-ai'),
                        'description' => __('Where to redirect users after successful login. Leave empty to use the Account page.', 'cobra-ai'),
                        'type' => 'page_or_url'
                    ],
                    'after_logout' => [
                        'label' => __('Redirect After Logout', 'cobra-ai'),
                        'description' => __('Where to redirect users after logout. Leave empty to use the Login page.', 'cobra-ai'),
                        'type' => 'page_or_url'
                    ],
                    'policy' => [
                        'label' => __('Privacy Policy Page', 'cobra-ai'),
                        'description' => __('Select or specify your privacy policy page.', 'cobra-ai'),
                        'type' => 'page_or_url',
                        'default_search' => 'privacy policy'
                    ]
                ];

                foreach ($redirect_fields as $field => $config):
                    $current_value = $settings['redirects'][$field] ?? '';
                    $selected_page_id = '';
                    $custom_url = $current_value;
                    
                    // Check if current value is a page ID or URL
                    // A page ID should be numeric and the page should exist
                    if (is_numeric($current_value) && get_post($current_value) && get_post_status($current_value) === 'publish') {
                        $selected_page_id = $current_value;
                        $custom_url = '';
                    } elseif (!empty($current_value) && (filter_var($current_value, FILTER_VALIDATE_URL) || strpos($current_value, '/') !== false)) {
                        // It's a URL (either full URL or relative path)
                        $selected_page_id = '';
                        $custom_url = $current_value;
                    }
                    
                    // For policy field, try to find default privacy policy page only if no current value
                    if ($field === 'policy' && empty($current_value)) {
                        $privacy_pages = get_pages([
                            'meta_key' => '_wp_page_template',
                            'meta_value' => 'page-privacy.php',
                            'number' => 1
                        ]);
                        if (empty($privacy_pages)) {
                            $privacy_pages = get_posts([
                                'post_type' => 'page',
                                'post_status' => 'publish',
                                's' => 'privacy policy',
                                'posts_per_page' => 1
                            ]);
                        }
                        if (!empty($privacy_pages)) {
                            $selected_page_id = $privacy_pages[0]->ID;
                            $custom_url = '';
                        }
                    }
                ?>
                    <tr>
                        <th scope="row">
                            <label for="redirect_<?php echo esc_attr($field); ?>">
                                <?php echo esc_html($config['label']); ?>
                            </label>
                        </th>
                        <td>
                            <div class="redirect-field-container">
                                <!-- Page Selector -->
                                <div class="page-selector" style="margin-bottom: 10px;">
                                    <label>
                                        <input type="radio" 
                                               name="redirect_type_<?php echo esc_attr($field); ?>" 
                                               value="page" 
                                               <?php checked(!empty($selected_page_id)); ?>
                                               class="redirect-type-radio">
                                        <?php _e('Select a page:', 'cobra-ai'); ?>
                                    </label>
                                    <select name="settings[redirects][<?php echo esc_attr($field); ?>]_page"
                                            id="redirect_page_<?php echo esc_attr($field); ?>"
                                            class="regular-text redirect-page-select"
                                            style="margin-left: 10px;"
                                            <?php disabled(empty($selected_page_id)); ?>>
                                        <option value=""><?php _e('-- Select Page --', 'cobra-ai'); ?></option>
                                        <?php foreach ($pages as $page): ?>
                                            <option value="<?php echo esc_attr($page->ID); ?>"
                                                <?php selected($selected_page_id, $page->ID); ?>>
                                                <?php echo esc_html($page->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Custom URL -->
                                <div class="url-selector">
                                    <label>
                                        <input type="radio" 
                                               name="redirect_type_<?php echo esc_attr($field); ?>" 
                                               value="url" 
                                               <?php checked(empty($selected_page_id) && !empty($custom_url)); ?>
                                               class="redirect-type-radio">
                                        <?php _e('Or enter custom URL:', 'cobra-ai'); ?>
                                    </label>
                                    <input type="url"
                                           name="settings[redirects][<?php echo esc_attr($field); ?>]_url"
                                           id="redirect_url_<?php echo esc_attr($field); ?>"
                                           value="<?php echo esc_url($custom_url); ?>"
                                           class="regular-text redirect-url-input"
                                           style="margin-left: 10px;"
                                           placeholder="https://example.com/page"
                                           <?php disabled(!empty($selected_page_id) && empty($custom_url)); ?>>
                                </div>
                                
                                <!-- Hidden field for final value -->
                                <input type="hidden" 
                                       name="settings[redirects][<?php echo esc_attr($field); ?>]" 
                                       id="final_redirect_<?php echo esc_attr($field); ?>"
                                       value="<?php echo esc_attr($current_value); ?>">
                            </div>
                            <p class="description">
                                <?php echo esc_html($config['description']); ?>
                            </p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <script>
                jQuery(document).ready(function($) {
                    // Handle page creation - Use event delegation for dynamically created buttons
                    $(document).on('click', '.create-page', function() {
                        var button = $(this);
                        var pageData = button.data();

                        button.prop('disabled', true).text('<?php esc_js(_e('Creating...', 'cobra-ai')); ?>');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'cobra_create_page',
                                page_type: pageData.page,
                                page_title: pageData.title,
                                page_content: pageData.shortcode,
                                nonce: '<?php echo wp_create_nonce('cobra_create_page'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert(response.data.message || '<?php esc_js(_e('Failed to create page.', 'cobra-ai')); ?>');
                                    button.prop('disabled', false)
                                        .text('<?php esc_js(_e('Create Page', 'cobra-ai')); ?>');
                                }
                            },
                            error: function() {
                                alert('<?php esc_js(_e('Failed to create page.', 'cobra-ai')); ?>');
                                button.prop('disabled', false)
                                    .text('<?php esc_js(_e('Create Page', 'cobra-ai')); ?>');
                            }
                        });
                    });

                    // Handle page reset - Use event delegation for dynamically created buttons
                    $(document).on('click', '.reset-page', function() {
                        var button = $(this);
                        var field = button.data('field');
                        var container = button.parent();
                        var select = container.find('select[name="settings[pages][' + field + ']"]');
                        
                        if (confirm('<?php esc_js(_e('Are you sure you want to reset this page selection?', 'cobra-ai')); ?>')) {
                            // Reset select value
                            select.val('');
                            
                            // Trigger change event to update interface
                            select.trigger('change');
                            
                            // Show success message
                            var successMsg = $('<p class="description" style="color: #00a32a;"><strong><?php esc_js(_e('Page setting reset successfully.', 'cobra-ai')); ?></strong></p>');
                            container.append(successMsg);
                            setTimeout(function() {
                                successMsg.fadeOut();
                            }, 3000);
                        }
                    });

                    // Handle page selection changes
                    $('select[name^="settings[pages]"]').on('change', function() {
                        var select = $(this);
                        var container = select.parent();
                        var pageId = select.val();

                        container.find('.button').remove();
                        container.find('.description[style*="color"]').remove();

                        if (pageId) {
                            container.append(
                                '<a href="<?php echo admin_url('post.php?action=edit&post='); ?>' + pageId +
                                '" class="button" target="_blank"><?php esc_js(_e('Edit Page', 'cobra-ai')); ?></a> ' +
                                '<a href="<?php echo home_url('?p='); ?>' + pageId +
                                '" class="button" target="_blank"><?php esc_js(_e('View Page', 'cobra-ai')); ?></a> ' +
                                '<button type="button" class="button button-secondary reset-page" ' +
                                'data-field="' + select.attr('id').replace('page_', '') + '" ' +
                                'title="<?php esc_js(_e('Reset page selection', 'cobra-ai')); ?>">' +
                                '<?php esc_js(_e('Reset', 'cobra-ai')); ?></button>'
                            );
                        } else {
                            var fieldName = select.attr('id').replace('page_', '');
                            var shortcode = select.closest('tr').find('code').text();
                            var pageLabel = select.closest('tr').find('th label').text();
                            
                            container.append(
                                '<button type="button" class="button create-page" ' +
                                'data-page="' + fieldName + '" ' +
                                'data-title="' + pageLabel + '" ' +
                                'data-shortcode="' + shortcode + '">' +
                                '<?php esc_js(_e('Create Page', 'cobra-ai')); ?></button>'
                            );
                        }
                    });
                });
            </script>

            <style>
                .form-table th.title {
                    padding-left: 0;
                }

                .form-table .button {
                    margin-left: 10px;
                }

                .form-table code {
                    background: #f0f0f1;
                    padding: 2px 6px;
                }

                .redirect-field-container {
                    max-width: 600px;
                }

                .redirect-field-container .page-selector,
                .redirect-field-container .url-selector {
                    display: flex;
                    align-items: center;
                    margin-bottom: 8px;
                }

                .redirect-field-container label {
                    display: flex;
                    align-items: center;
                    margin-right: 10px;
                    font-weight: normal;
                }

                .redirect-field-container input[type="radio"] {
                    margin-right: 5px;
                }

                .redirect-field-container select,
                .redirect-field-container input[type="url"] {
                    min-width: 300px;
                }

                .redirect-field-container select:disabled,
                .redirect-field-container input:disabled {
                    opacity: 0.6;
                }
            </style>
        <?php elseif ($current_tab === 'emails'): ?>
            <!-- Email Settings -->
            <div class="email-templates">
                <h3><?php _e('Global Email Template', 'cobra-ai'); ?></h3>
                <?php
                // wp_editor(
                //     $settings['emails']['global_template'],
                //     'global_template',
                //     [
                //         'textarea_name' => 'settings[emails][global_template]',
                //         'textarea_rows' => 15,
                //         'media_buttons' => false,
                //         'tinymce' => [
                //             'valid_elements' => '*[*]', // Allow all HTML elements and attributes
                //             'extended_valid_elements' => '*[*]' // Allow extended elements
                //         ],
                //         'quicktags' => true // Enable HTML mode
                //     ]
                // );
                ?>
                <textarea name="settings[emails][global_template]" rows="15" cols="80"><?php echo esc_textarea($settings['emails']['global_template']); ?></textarea>

                <p class="description">
                    <?php _e('Available variables: {site_name}, {site_url}, {header}, {content}, {footer}', 'cobra-ai'); ?>
                </p>

                <h3><?php _e('Verification Email', 'cobra-ai'); ?></h3>
                <?php
                // wp_editor(
                //     $settings['emails']['verification'],
                //     'verification_email',
                //     [
                //         'textarea_name' => 'settings[emails][verification]',
                //         'textarea_rows' => 15,
                //         'media_buttons' => false
                //     ]
                // );
                ?>
                <textarea name="settings[emails][verification]" rows="15" cols="80"><?php echo esc_textarea($settings['emails']['verification']); ?></textarea>
                <p class="description">
                    <?php _e('Available variables: {user_name}, {verification_link}, {expiry_time}', 'cobra-ai'); ?>
                </p>

                <h3><?php _e('Confirmation Email', 'cobra-ai'); ?></h3>
                <?php
                // wp_editor(
                //     $settings['emails']['confirmation'],
                //     'confirmation_email',
                //     [
                //         'textarea_name' => 'settings[emails][confirmation]',
                //         'textarea_rows' => 15,
                //         'media_buttons' => false
                //     ]
                // );
                ?>
                <textarea name="settings[emails][confirmation]" rows="15" cols="80"><?php echo esc_textarea($settings['emails']['confirmation']); ?></textarea>
                <p class="description">
                    <?php _e('Available variables: {user_name}, {login_link}', 'cobra-ai'); ?>
                </p>

                <h3><?php _e('Email footer', 'cobra-ai'); ?></h3>
                <div>
                <?php
                $footer = $this->email->get_email_footer();
                echo $footer;
                ?>
                </div>
            </div>

        <?php elseif ($current_tab === 'fields'): ?>
            <!-- Fields Settings -->
            <table class="form-table">
                <?php
                $fields = [
                    'username' => __('Username', 'cobra-ai'),
                    'email' => __('Email', 'cobra-ai'),
                    'password' => __('Password', 'cobra-ai'),
                    'confirm_password' => __('Confirm Password', 'cobra-ai'),
                    'first_name' => __('First Name', 'cobra-ai'),
                    'last_name' => __('Last Name', 'cobra-ai'),
                    'phone' => __('Phone', 'cobra-ai'),
                    'address' => __('Address', 'cobra-ai'),
                    'city' => __('City', 'cobra-ai'),
                    'state' => __('State', 'cobra-ai'),
                    'zip' => __('ZIP Code', 'cobra-ai'),
                    'country' => __('Country', 'cobra-ai'),
                    'company' => __('Company', 'cobra-ai'),
                    'website' => __('Website', 'cobra-ai'),
                    'about' => __('About', 'cobra-ai'),
                    'avatar' => __('Avatar', 'cobra-ai')
                ];

                foreach ($fields as $field => $label):
                    $is_required_field = in_array($field, ['username', 'email', 'password', 'confirm_password']);
                ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox"
                                        name="settings[fields][<?php echo esc_attr($field); ?>][enabled]"
                                        value="1"
                                        <?php checked($settings['fields'][$field]['enabled'] ?? false); ?>
                                        <?php disabled($is_required_field); ?>>
                                    <?php _e('Enable', 'cobra-ai'); ?>
                                </label>
                                &nbsp;&nbsp;
                                <label>
                                    <input type="checkbox"
                                        name="settings[fields][<?php echo esc_attr($field); ?>][required]"
                                        value="1"
                                        <?php checked($settings['fields'][$field]['required'] ?? false); ?>
                                        <?php disabled($is_required_field); ?>>
                                    <?php _e('Required', 'cobra-ai'); ?>
                                </label>
                                <?php if ($is_required_field): ?>
                                    <p class="description">
                                        <?php _e('This field is mandatory and cannot be disabled.', 'cobra-ai'); ?>
                                    </p>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php submit_button(); ?>
    </form>
</div>

<style>
    .email-templates h3 {
        margin: 20px 0 10px;
    }

    .email-templates .wp-editor-container {
        margin-bottom: 15px;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Handle field dependencies
        $('input[name^="settings[fields]"][name$="[enabled]"]').on('change', function() {
            var $fieldset = $(this).closest('fieldset');
            var $required = $fieldset.find('input[name$="[required]"]');
            if (this.checked) {
                $required.prop('disabled', false);
            } else {
                $required.prop('disabled', true).prop('checked', false);
            }
        });

        // Initial state setup
        $('input[name^="settings[fields]"][name$="[enabled]"]').each(function() {
            var $fieldset = $(this).closest('fieldset');
            var $required = $fieldset.find('input[name$="[required]"]');

            if (!this.checked) {
                $required.prop('disabled', true).prop('checked', false);
            }
        });

        // Handle redirect field radio buttons and final value calculation
        $('.redirect-type-radio').on('change', function() {
            var fieldContainer = $(this).closest('.redirect-field-container');
            var pageSelect = fieldContainer.find('.redirect-page-select');
            var urlInput = fieldContainer.find('.redirect-url-input');
            var hiddenInput = fieldContainer.find('input[type="hidden"]');
            
            if ($(this).val() === 'page') {
                pageSelect.prop('disabled', false);
                urlInput.prop('disabled', true).val('');
                
                // Update final value with selected page ID
                var pageId = pageSelect.val();
                hiddenInput.val(pageId);
            } else {
                pageSelect.prop('disabled', true);
                urlInput.prop('disabled', false);
                
                // Update final value with URL
                var url = urlInput.val();
                hiddenInput.val(url);
            }
        });

        // Handle page select changes
        $('.redirect-page-select').on('change', function() {
            var fieldContainer = $(this).closest('.redirect-field-container');
            var hiddenInput = fieldContainer.find('input[type="hidden"]');
            var pageRadio = fieldContainer.find('input[name$="[page]"]:radio');
            
            // Make sure page radio is selected when page is chosen
            pageRadio.prop('checked', true);
            hiddenInput.val($(this).val());
            
            // Disable URL input when page is selected
            var urlInput = fieldContainer.find('.redirect-url-input');
            urlInput.prop('disabled', true);
        });

        // Handle URL input changes
        $('.redirect-url-input').on('input', function() {
            var fieldContainer = $(this).closest('.redirect-field-container');
            var hiddenInput = fieldContainer.find('input[type="hidden"]');
            var urlRadio = fieldContainer.find('input[value="url"]:radio');
            
            // Make sure URL radio is selected when URL is entered
            urlRadio.prop('checked', true);
            hiddenInput.val($(this).val());
            
            // Disable page select when URL is entered
            var pageSelect = fieldContainer.find('.redirect-page-select');
            pageSelect.prop('disabled', true);
        });

        // Initialize redirect fields on page load
        $('.redirect-field-container').each(function() {
            var container = $(this);
            var pageRadio = container.find('input[value="page"]:radio');
            var urlRadio = container.find('input[value="url"]:radio');
            var pageSelect = container.find('.redirect-page-select');
            var urlInput = container.find('.redirect-url-input');
            
            if (pageRadio.is(':checked')) {
                pageSelect.prop('disabled', false);
                urlInput.prop('disabled', true);
            } else if (urlRadio.is(':checked')) {
                urlInput.prop('disabled', false);
                pageSelect.prop('disabled', true);
            }
        });

        // Save the form data before submitting
        $('form').on('submit', function() {
            localStorage.setItem('lastTab', '<?php echo esc_js($current_tab); ?>');
        });

        // Restore the last active tab
        var lastTab = localStorage.getItem('lastTab');
        if (lastTab) {
            $('.nav-tab[href*="tab=' + lastTab + '"]').addClass('nav-tab-active').siblings().removeClass('nav-tab-active');
            localStorage.removeItem('lastTab');
        }
    });
</script>