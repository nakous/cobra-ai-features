<?php
// views/settings.php
defined('ABSPATH') || exit;

// Display settings errors/notifications
$this->display_settings_errors();
?>

<div class="wrap">
    <h1><?php _e('AI Feature Settings', 'cobra-ai'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cobra-ai-settings-form">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">

        <!-- Settings navigation tabs -->
        <nav class="nav-tab-wrapper">
            <a href="#providers" class="nav-tab nav-tab-active"><?php _e('AI Providers', 'cobra-ai'); ?></a>
            <a href="#limits" class="nav-tab"><?php _e('Usage Limits', 'cobra-ai'); ?></a>
            <a href="#maintenance" class="nav-tab"><?php _e('Maintenance', 'cobra-ai'); ?></a>
            <a href="#display" class="nav-tab"><?php _e('Display', 'cobra-ai'); ?></a>
        </nav>

        <div class="tab-content">
            <!-- AI Providers Settings -->
            <div id="providers" class="tab-pane active">
                <?php
                $providers = [
                    'openai' => [
                        'name' => 'OpenAI',
                        'icon' => 'dashicons-share-alt',
                        'fields' => [
                            'api_key' => 'API Key',
                            'model' => 'Default Model',
                            'max_tokens' => 'Max Tokens',
                            'temperature' => 'Temperature',
                            'top_p' => 'Top P',
                            'frequency_penalty' => 'Frequency Penalty',
                            'presence_penalty' => 'Presence Penalty'
                        ]
                    ],
                    'claude' => [
                        'name' => 'Claude',
                        'icon' => 'dashicons-superhero',
                        'fields' => [
                            'api_key' => 'API Key',
                            'model' => 'Default Model',
                            'max_tokens' => 'Max Tokens',
                            'temperature' => 'Temperature',
                            'top_p' => 'Top P'
                        ]
                    ],
                    'gemini' => [
                        'name' => 'Gemini',
                        'icon' => 'dashicons-google',
                        'fields' => [
                            'api_key' => 'API Key',
                            'model' => 'Default Model',
                            'max_tokens' => 'Max Tokens',
                            'temperature' => 'Temperature',
                            'top_p' => 'Top P'
                        ]
                    ],
                    'perplexity' => [
                        'name' => 'Perplexity',
                        'icon' => 'dashicons-admin-network',
                        'fields' => [
                            'api_key' => 'API Key',
                            'model' => 'Default Model',
                            'max_tokens' => 'Max Tokens',
                            'temperature' => 'Temperature',
                            'top_p' => 'Top P'
                        ]
                    ]
                ];

                foreach ($providers as $provider_id => $provider):
                    $provider_settings = $settings['providers'][$provider_id] ?? [];
                    $is_active = !empty($provider_settings['active']);
                ?>
                    <div class="provider-card <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="provider-header">
                            <span class="dashicons <?php echo esc_attr($provider['icon']); ?>"></span>
                            <h3><?php echo esc_html($provider['name']); ?></h3>
                            <label class="toggle-switch">
                                <input type="checkbox"
                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][active]"
                                    value="1"
                                    <?php checked($is_active); ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="provider-content" <?php echo !$is_active ? 'style="display: none;"' : ''; ?>>
                            <table class="form-table">
                                <?php foreach ($provider['fields'] as $field_id => $field_label):
                                    $field_value = $provider_settings['config'][$field_id] ?? '';
                                    $is_sensitive = $field_id === 'api_key';
                                ?>
                                    <tr>
                                        <th scope="row">
                                            <label for="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>">
                                                <?php echo esc_html($field_label); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <?php if ($is_sensitive): ?>
                                                <input type="password"
                                                    id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="regular-text"
                                                    autocomplete="new-password">
                                                <button type="button" class="button toggle-password"
                                                    data-target="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </button>
                                            <?php elseif ($field_id === 'model'): ?>
                                                <select id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]">
                                                    <?php
                                                    $models = [];
                                                    switch ($provider_id) {
                                                        case 'openai':
                                                            $models = [
                                                                'gpt-5-2025-08-07' => 'GPT-5',
                                                                'gpt-5-mini-2025-08-07' => 'GPT-5 Mini',
                                                                'gpt-4o-mini' => 'GPT-4o Mini',
                                                                'gpt-4o' => 'GPT-4o',
                                                                'gpt-4' => 'GPT-4',
                                                                'gpt-4-turbo' => 'GPT-4 Turbo',
                                                                'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
                                                            ];
                                                            break;
                                                        case 'claude':
                                                            $models = [
                                                                'claude-3-opus-20240229' => 'Claude 3 Opus',
                                                                'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
                                                                'claude-2.1' => 'Claude 2.1'
                                                            ];
                                                            break;
                                                        case 'gemini':
                                                            $models = [
                                                                'gemini-pro' => 'Gemini Pro',
                                                                'gemini-pro-vision' => 'Gemini Pro Vision'
                                                            ];
                                                            break;
                                                        case 'perplexity':
                                                            $models = [
                                                                'pplx-70b-online' => 'PPLX 70B Online',
                                                                'pplx-7b-online' => 'PPLX 7B Online',
                                                                'pplx-70b-chat' => 'PPLX 70B Chat'
                                                            ];
                                                            break;
                                                    }
                                                    foreach ($models as $model_id => $model_name):
                                                    ?>
                                                        <option value="<?php echo esc_attr($model_id); ?>"
                                                            <?php selected($field_value, $model_id); ?>>
                                                            <?php echo esc_html($model_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif (in_array($field_id, ['temperature', 'top_p'])): ?>
                                                <input type="number"
                                                    id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="small-text"
                                                    step="0.1"
                                                    min="0"
                                                    max="1">
                                            <?php elseif (in_array($field_id, ['frequency_penalty', 'presence_penalty'])): ?>
                                                <input type="number"
                                                    id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="small-text"
                                                    step="0.1"
                                                    min="-2"
                                                    max="2">
                                            <?php else: ?>
                                                <input type="text"
                                                    id="<?php echo esc_attr("{$provider_id}_{$field_id}"); ?>"
                                                    name="settings[providers][<?php echo esc_attr($provider_id); ?>][config][<?php echo esc_attr($field_id); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                    class="regular-text">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <?php if ($is_active): ?>
                                <button type="button" class="button test-api-connection"
                                    data-provider="<?php echo esc_attr($provider_id); ?>">
                                    <?php _e('Test Connection', 'cobra-ai'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Usage Limits Settings -->
            <div id="limits" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Daily Request Limit', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <input type="number"
                                name="settings[limits][requests_per_day]"
                                value="<?php echo esc_attr($settings['limits']['requests_per_day']); ?>"
                                min="0"
                                step="1"
                                class="small-text">
                            <p class="description">
                                <?php _e('Maximum number of requests per user per day (0 for unlimited)', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Limit Message', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <textarea name="settings[limits][limit_message]"
                                rows="3"
                                class="large-text"><?php
                                                    echo esc_textarea($settings['limits']['limit_message']);
                                                    ?></textarea>
                            <p class="description">
                                <?php _e('Message to display when user reaches the daily limit', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Maintenance Settings -->
            <div id="maintenance" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Maintenance Mode', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="settings[maintenance][active]"
                                    value="1"
                                    <?php checked(!empty($settings['maintenance']['active'])); ?>>
                                <?php _e('Enable maintenance mode', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Maintenance Message', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <textarea name="settings[maintenance][message]"
                                rows="3"
                                class="large-text"><?php
                                                    echo esc_textarea($settings['maintenance']['message']);
                                                    ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Start Date', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <input type="datetime-local"
                                name="settings[maintenance][start_date]"
                                value="<?php echo isset($settings['maintenance']['start_date']) ? esc_attr($settings['maintenance']['start_date']) : ''; ?>"
                                class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('End Date', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <input type="datetime-local"
                                name="settings[maintenance][end_date]"
                                value="<?php echo isset($settings['maintenance']['end_date']) ? esc_attr($settings['maintenance']['end_date']) : ''; ?>"
                                class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Excluded Roles', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <?php
                            $excluded_roles = $settings['maintenance']['excluded_roles'] ?? [];
                            foreach (wp_roles()->roles as $role_id => $role):
                            ?>
                                <label class="role-checkbox">
                                    <input type="checkbox"
                                        name="settings[maintenance][excluded_roles][]"
                                        value="<?php echo esc_attr($role_id); ?>"
                                        <?php checked(in_array($role_id, $excluded_roles)); ?>>
                                    <?php echo esc_html($role['name']); ?>>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php _e('Selected roles will still have access during maintenance', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Display Settings -->
            <div id="display" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Profile Tracking', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="settings[display][show_in_profile]"
                                    value="1"
                                    <?php checked(!empty($settings['display']['show_in_profile'])); ?>>
                                <?php _e('Show tracking history in user profile', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('REST API', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="settings[display][enable_rest_api]"
                                    value="1"
                                    <?php checked(!empty($settings['display']['enable_rest_api'])); ?>>
                                <?php _e('Enable REST API endpoints', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<style>
    .provider-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        margin-bottom: 20px;
        padding: 20px;
    }

    .provider-card.active {
        border-color: #2271b1;
    }

    .provider-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }

    .provider-header .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
        margin-right: 10px;
    }

    .provider-header h3 {
        margin: 0;
        flex-grow: 1;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked+.slider {
        background-color: #2271b1;
    }

    input:checked+.slider:before {
        transform: translateX(26px);
    }

    .role-checkbox {
        display: block;
        margin-bottom: 5px;
    }

    .tab-content>.tab-pane {
        display: none;
    }

    .tab-content>.active {
        display: block;
    }

    .test-api-connection {
        margin-top: 10px !important;
    }

    /* Icon display on hover/focus */
    .form-table input[type="text"],
    .form-table input[type="password"],
    .form-table textarea {
        position: relative;
    }

    .toggle-password {
        margin-left: 5px;
    }

    .toggle-password .dashicons {
        width: 16px;
        height: 16px;
        font-size: 16px;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Tab navigation
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();

            // Update tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show content
            const target = $(this).attr('href').substring(1);
            $('.tab-pane').removeClass('active');
            $('#' + target).addClass('active');
        });

        // Provider toggle
        $('.toggle-switch input').on('change', function() {
            const card = $(this).closest('.provider-card');
            const content = card.find('.provider-content');

            if ($(this).is(':checked')) {
                card.addClass('active');
                content.slideDown();
            } else {
                card.removeClass('active');
                content.slideUp();
            }
        });

        // Password toggle
        $('.toggle-password').on('click', function() {
            const target = $('#' + $(this).data('target'));
            const icon = $(this).find('.dashicons');

            if (target.attr('type') === 'password') {
                target.attr('type', 'text');
                icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                target.attr('type', 'password');
                icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Test API connection
        $('.test-api-connection').on('click', function() {
            const provider = $(this).data('provider');
            const button = $(this);
            const originalText = button.text();

            button.prop('disabled', true).text('Testing...');

            // Get API key
            const apiKey = $('#' + provider + '_api_key').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_test_connection',
                    provider: provider, // 32
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce("cobra_ai_test_connection"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Connection successful!');
                    } else {
                        alert('Connection failed: ' + response.data);
                    }
                },
                error: function() {
                    alert('Connection test failed');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Icon visibility on hover/focus
        function toggleIcon(element, show) {
            const icon = element.next('.dashicons');
            if (icon.length) {
                icon.css('opacity', show ? 1 : 0);
            }
        }

        $('.form-table input[type="text"], .form-table textarea').each(function() {
            // Show icon on hover
            $(this).hover(
                function() {
                    toggleIcon($(this), true);
                },
                function() {
                    if (!$(this).is(':focus')) toggleIcon($(this), false);
                }
            );

            // Show icon on focus
            $(this).focus(function() {
                toggleIcon($(this), true);
            }).blur(function() {
                toggleIcon($(this), false);
            });
        });
    });
</script>