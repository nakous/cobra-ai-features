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
                        'description' => __('Page containing the [user_login] shortcode.', 'cobra-ai'),
                        'shortcode' => '[user_login]'
                    ],
                    'register' => [
                        'label' => __('Registration Page', 'cobra-ai'),
                        'description' => __('Page containing the [user_register] shortcode.', 'cobra-ai'),
                        'shortcode' => '[user_register]'
                    ],
                    'forgot_password' => [
                        'label' => __('Forgot Password Page', 'cobra-ai'),
                        'description' => __('Page containing the [user_forgot_password] shortcode.', 'cobra-ai'),
                        'shortcode' => '[user_forgot_password]'
                    ],
                    'reset_password' => [
                        'label' => __('Reset Password Page', 'cobra-ai'),
                        'description' => __('Page containing the [user_reset_password] shortcode.', 'cobra-ai'),
                        'shortcode' => '[user_reset_password]'
                    ],
                    'account' => [
                        'label' => __('Account Page', 'cobra-ai'),
                        'description' => __('Page containing the [user_account] shortcode.', 'cobra-ai'),
                        'shortcode' => '[user_account]'
                    ]
                ];

                foreach ($page_fields as $field => $config): ?>
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
                            <?php if (empty($settings['pages'][$field])): ?>
                                <button type="button"
                                    class="button create-page"
                                    data-page="<?php echo esc_attr($field); ?>"
                                    data-title="<?php echo esc_attr($config['label']); ?>"
                                    data-shortcode="<?php echo esc_attr($config['shortcode']); ?>">
                                    <?php _e('Create Page', 'cobra-ai'); ?>
                                </button>
                            <?php else: ?>
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
                // Define URL fields
                $url_fields = [
                    'after_login' => [
                        'label' => __('Redirect After Login', 'cobra-ai'),
                        'description' => __('Where to redirect users after successful login. Leave empty to use the Account page.', 'cobra-ai')
                    ],
                    'after_logout' => [
                        'label' => __('Redirect After Logout', 'cobra-ai'),
                        'description' => __('Where to redirect users after logout. Leave empty to use the Login page.', 'cobra-ai')
                    ],
                    'policy' => [
                        'label' => __('Privacy Policy URL', 'cobra-ai'),
                        'description' => __('URL to your privacy policy page.', 'cobra-ai')
                    ]
                ];

                foreach ($url_fields as $field => $config): ?>
                    <tr>
                        <th scope="row">
                            <label for="url_<?php echo esc_attr($field); ?>">
                                <?php echo esc_html($config['label']); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url"
                                name="settings[redirects][<?php echo esc_attr($field); ?>]"
                                id="url_<?php echo esc_attr($field); ?>"
                                value="<?php echo esc_url($settings['redirects'][$field] ?? ''); ?>"
                                class="regular-text">
                            <p class="description">
                                <?php echo esc_html($config['description']); ?>
                            </p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <script>
                jQuery(document).ready(function($) {
                    // Handle page creation
                    $('.create-page').on('click', function() {
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

                    // Handle page selection changes
                    $('select[name^="settings[pages]"]').on('change', function() {
                        var select = $(this);
                        var container = select.parent();
                        var pageId = select.val();

                        container.find('.button').remove();

                        if (pageId) {
                            container.append(
                                '<a href="<?php echo admin_url('post.php?action=edit&post='); ?>' + pageId +
                                '" class="button" target="_blank"><?php esc_js(_e('Edit Page', 'cobra-ai')); ?></a> ' +
                                '<a href="<?php echo home_url('?p='); ?>' + pageId +
                                '" class="button" target="_blank"><?php esc_js(_e('View Page', 'cobra-ai')); ?></a>'
                            );
                        } else {
                            container.append(
                                '<button type="button" class="button create-page" ' +
                                'data-page="' + select.attr('id').replace('page_', '') + '" ' +
                                'data-title="' + select.find('option:first').text().replace('-- Select ', '').replace(' --', '') + '" ' +
                                'data-shortcode="' + select.closest('tr').find('code').text() + '">' +
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
            </style>
        <?php elseif ($current_tab === 'emails'): ?>
            <!-- Email Settings -->
            <div class="email-templates">
                <h3><?php _e('Global Email Template', 'cobra-ai'); ?></h3>
                <?php
                wp_editor(
                    $settings['emails']['global_template'],
                    'global_template',
                    [
                        'textarea_name' => 'settings[emails][global_template]',
                        'textarea_rows' => 15,
                        'media_buttons' => false
                    ]
                );
                ?>
                <p class="description">
                    <?php _e('Available variables: {site_name}, {site_url}, {header}, {content}, {footer}', 'cobra-ai'); ?>
                </p>

                <h3><?php _e('Verification Email', 'cobra-ai'); ?></h3>
                <?php
                wp_editor(
                    $settings['emails']['verification'],
                    'verification_email',
                    [
                        'textarea_name' => 'settings[emails][verification]',
                        'textarea_rows' => 15,
                        'media_buttons' => false
                    ]
                );
                ?>
                <p class="description">
                    <?php _e('Available variables: {user_name}, {verification_link}, {expiry_time}', 'cobra-ai'); ?>
                </p>

                <h3><?php _e('Confirmation Email', 'cobra-ai'); ?></h3>
                <?php
                wp_editor(
                    $settings['emails']['confirmation'],
                    'confirmation_email',
                    [
                        'textarea_name' => 'settings[emails][confirmation]',
                        'textarea_rows' => 15,
                        'media_buttons' => false
                    ]
                );
                ?>
                <p class="description">
                    <?php _e('Available variables: {user_name}, {login_link}', 'cobra-ai'); ?>
                </p>
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
            console.log('changed');
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