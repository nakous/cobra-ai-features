<?php

/**
 * Global Settings View for Cobra AI Features
 * 
 * @package CobraAI
 */

namespace CobraAI\Admin\Views;
use function CobraAI\{
    cobra_ai,
    cobra_ai_get_settings,
    cobra_ai_admin,
    cobra_ai_is_debug
};


defined('ABSPATH') || exit;

// Get current settings with defaults
$settings = cobra_ai_get_settings();
$setting_groups = cobra_ai_admin()->get_setting_groups();

// Get system info
$system_info = [
    'php_version' => PHP_VERSION,
    'wp_version' => get_bloginfo('version'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_input_vars' => ini_get('max_input_vars'),
    'display_errors' => ini_get('display_errors'),
];
?>

<div class="wrap cobra-ai-settings">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Cobra AI Settings', 'cobra-ai'); ?>
    </h1>

    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings updated successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cobra-ai-settings-form">
        <input type="hidden" name="action" value="cobra_ai_save_settings">
        <?php wp_nonce_field('cobra_ai_settings_nonce', 'cobra_ai_nonce'); ?>

        <!-- Settings Navigation -->
        <div class="cobra-ai-settings-nav">
            <ul class="nav-tabs">
                <?php foreach ($setting_groups as $group_id => $group_label): ?>
                    <li>
                        <a href="#<?php echo esc_attr($group_id); ?>" class="nav-tab" data-tab="<?php echo esc_attr($group_id); ?>">
                            <?php echo esc_html($group_label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <a href="#system" class="nav-tab" data-tab="system">
                        <?php echo esc_html__('System Info', 'cobra-ai'); ?>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Settings Content -->
        <div class="cobra-ai-settings-content">
            <!-- Core Settings -->
            <div id="core" class="settings-tab">
                <div class="settings-card">
                    <h2><?php echo esc_html__('Core Settings', 'cobra-ai'); ?></h2>

                    <div class="form-field">
                        <label for="core_environment">
                            <?php echo esc_html__('Environment', 'cobra-ai'); ?>
                        </label>
                        <select id="core_environment" name="settings[core][environment]">
                            <option value="production" <?php selected($settings['core']['environment'] ?? 'production', 'production'); ?>>
                                <?php echo esc_html__('Production', 'cobra-ai'); ?>
                            </option>
                            <option value="staging" <?php selected($settings['core']['environment'] ?? 'production', 'staging'); ?>>
                                <?php echo esc_html__('Staging', 'cobra-ai'); ?>
                            </option>
                            <option value="development" <?php selected($settings['core']['environment'] ?? 'production', 'development'); ?>>
                                <?php echo esc_html__('Development', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="core_data_handling">
                            <?php echo esc_html__('Data Handling', 'cobra-ai'); ?>
                        </label>
                        <select id="core_data_handling" name="settings[core][data_handling]">
                            <option value="strict" <?php selected($settings['core']['data_handling'] ?? 'strict', 'strict'); ?>>
                                <?php echo esc_html__('Strict', 'cobra-ai'); ?>
                            </option>
                            <option value="flexible" <?php selected($settings['core']['data_handling'] ?? 'strict', 'flexible'); ?>>
                                <?php echo esc_html__('Flexible', 'cobra-ai'); ?>
                            </option>
                            <option value="minimal" <?php selected($settings['core']['data_handling'] ?? 'strict', 'minimal'); ?>>
                                <?php echo esc_html__('Minimal', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div id="security" class="settings-tab">
                <div class="settings-card">
                    <h2><?php echo esc_html__('Security Settings', 'cobra-ai'); ?></h2>

                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox"
                                name="settings[security][enable_rate_limiting]"
                                value="1"
                                <?php checked($settings['security']['enable_rate_limiting'] ?? false); ?>>
                            <?php echo esc_html__('Enable Rate Limiting', 'cobra-ai'); ?>
                        </label>
                    </div>

                    <div class="form-field">
                        <label for="rate_limit_requests">
                            <?php echo esc_html__('Rate Limit (requests per hour)', 'cobra-ai'); ?>
                        </label>
                        <input type="number"
                            id="rate_limit_requests"
                            name="settings[security][rate_limit_requests]"
                            value="<?php echo esc_attr($settings['security']['rate_limit_requests'] ?? 60); ?>"
                            min="1"
                            max="1000">
                    </div>

                    <div class="form-field">
                        <label for="allowed_ips">
                            <?php echo esc_html__('Allowed IP Addresses', 'cobra-ai'); ?>
                        </label>
                        <textarea id="allowed_ips"
                            name="settings[security][allowed_ip_addresses]"
                            rows="4"
                            placeholder="<?php echo esc_attr__('One IP per line', 'cobra-ai'); ?>"><?php
                                                                                                    echo esc_textarea(implode("\n", $settings['security']['allowed_ip_addresses'] ?? []));
                                                                                                    ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Performance Settings -->
            <div id="performance" class="settings-tab">
                <div class="settings-card">
                    <h2><?php echo esc_html__('Performance Settings', 'cobra-ai'); ?></h2>

                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox"
                                name="settings[performance][enable_caching]"
                                value="1"
                                <?php checked($settings['performance']['enable_caching'] ?? true); ?>>
                            <?php echo esc_html__('Enable Caching', 'cobra-ai'); ?>
                        </label>
                    </div>

                    <div class="form-field">
                        <label for="cache_duration">
                            <?php echo esc_html__('Cache Duration (seconds)', 'cobra-ai'); ?>
                        </label>
                        <input type="number"
                            id="cache_duration"
                            name="settings[performance][cache_duration]"
                            value="<?php echo esc_attr($settings['performance']['cache_duration'] ?? 3600); ?>"
                            min="60"
                            max="86400">
                    </div>

                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox"
                                name="settings[performance][minify_output]"
                                value="1"
                                <?php checked($settings['performance']['minify_output'] ?? true); ?>>
                            <?php echo esc_html__('Minify Output', 'cobra-ai'); ?>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Logging Settings -->
            <div id="logging" class="settings-tab">
                <div class="settings-card">
                    <h2><?php echo esc_html__('Logging Settings', 'cobra-ai'); ?></h2>

                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox"
                                name="settings[logging][enable_logging]"
                                value="1"
                                <?php checked($settings['logging']['enable_logging'] ?? true); ?>>
                            <?php echo esc_html__('Enable Logging', 'cobra-ai'); ?>
                        </label>
                    </div>

                    <div class="form-field">
                        <label for="log_level">
                            <?php echo esc_html__('Log Level', 'cobra-ai'); ?>
                        </label>
                        <select id="log_level" name="settings[logging][log_level]">
                            <option value="error" <?php selected($settings['logging']['log_level'] ?? 'error', 'error'); ?>>
                                <?php echo esc_html__('Error', 'cobra-ai'); ?>
                            </option>
                            <option value="warning" <?php selected($settings['logging']['log_level'] ?? 'error', 'warning'); ?>>
                                <?php echo esc_html__('Warning', 'cobra-ai'); ?>
                            </option>
                            <option value="info" <?php selected($settings['logging']['log_level'] ?? 'error', 'info'); ?>>
                                <?php echo esc_html__('Info', 'cobra-ai'); ?>
                            </option>
                            <option value="debug" <?php selected($settings['logging']['log_level'] ?? 'error', 'debug'); ?>>
                                <?php echo esc_html__('Debug', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="max_log_age">
                            <?php echo esc_html__('Log Retention (days)', 'cobra-ai'); ?>
                        </label>
                        <input type="number"
                            id="max_log_age"
                            name="settings[logging][max_log_age]"
                            value="<?php echo esc_attr($settings['logging']['max_log_age'] ?? 30); ?>"
                            min="1"
                            max="365">
                    </div>
                </div>
            </div>

            <!-- Interface Settings -->
            <div id="interface" class="settings-tab">
                <div class="settings-card">
                    <h2><?php echo esc_html__('Interface Settings', 'cobra-ai'); ?></h2>

                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox"
                                name="settings[interface][enable_dark_mode]"
                                value="1"
                                <?php checked($settings['interface']['enable_dark_mode'] ?? false); ?>>
                            <?php echo esc_html__('Enable Dark Mode', 'cobra-ai'); ?>
                        </label>
                    </div>

                    <div class="form-field">
                        <label for="dashboard_layout">
                            <?php echo esc_html__('Dashboard Layout', 'cobra-ai'); ?>
                        </label>
                        <select id="dashboard_layout" name="settings[interface][dashboard_layout]">
                            <option value="standard" <?php selected($settings['interface']['dashboard_layout'] ?? 'standard', 'standard'); ?>>
                                <?php echo esc_html__('Standard', 'cobra-ai'); ?>
                            </option>
                            <option value="compact" <?php selected($settings['interface']['dashboard_layout'] ?? 'standard', 'compact'); ?>>
                                <?php echo esc_html__('Compact', 'cobra-ai'); ?>
                            </option>
                            <option value="detailed" <?php selected($settings['interface']['dashboard_layout'] ?? 'standard', 'detailed'); ?>>
                                <?php echo esc_html__('Detailed', 'cobra-ai'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div id="system" class="settings-tab">
                <div class="settings-card">
                    <h2><?php echo esc_html__('System Information', 'cobra-ai'); ?></h2>

                    <table class="widefat striped">
                        <tbody>
                            <?php foreach ($system_info as $key => $value): ?>
                                <tr>
                                    <td class="title">
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>
                                    </td>
                                    <td class="value">
                                        <?php echo esc_html($value); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="system-actions">
                        <button type="button" class="button" id="clear-cache">
                            <?php echo esc_html__('Clear Cache', 'cobra-ai'); ?>
                        </button>
                        <button type="button" class="button" id="clear-logs">
                            <?php echo esc_html__('Clear Logs', 'cobra-ai'); ?>
                        </button>
                        <button type="button" class="button" id="run-diagnostics">
                            <?php echo esc_html__('Run Diagnostics', 'cobra-ai'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="submit-wrapper">
            <button type="submit" class="button button-primary">
                <?php echo esc_html__('Save Settings', 'cobra-ai'); ?>
            </button>
            <button type="button" class="button" id="reset-settings">
                <?php echo esc_html__('Reset to Defaults', 'cobra-ai'); ?>
            </button>
        </div>
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        // Tab navigation
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');

            // Update active states
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show selected tab
            $('.settings-tab').hide();
            $('#' + tab).show();

            // Update URL hash
            window.location.hash = tab;
        });

    
        // Show initial tab based on URL hash or default to 'core'
        const initialTab = window.location.hash.substring(1) || 'core';
        $('.nav-tab[data-tab="' + initialTab + '"]').addClass('nav-tab-active');
        $('#' + initialTab).show().siblings('.settings-tab').hide();

        // Form handling
        $('#cobra-ai-settings-form').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $submitButton = $form.find('button[type="submit"]');

            // Show loading state
            $submitButton.prop('disabled', true)
                .html('<span class="spinner is-active"></span> <?php echo esc_js(__('Saving...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_save_settings',
                    nonce: '<?php echo wp_create_nonce('cobra-ai-settings'); ?>',
                    settings: $form.serializeArray()
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        const $notice = $(
                            '<div class="notice notice-success is-dismissible">' +
                            '<p><?php echo esc_js(__('Settings saved successfully.', 'cobra-ai')); ?></p>' +
                            '<button type="button" class="notice-dismiss">' +
                            '<span class="screen-reader-text"><?php echo esc_js(__('Dismiss this notice.', 'cobra-ai')); ?></span>' +
                            '</button>' +
                            '</div>'
                        );
                        $('.wrap.cobra-ai-settings > h1').after($notice);

                        // Reload if needed
                        if (response.data.reload) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        // Show error message
                        const $notice = $(
                            '<div class="notice notice-error is-dismissible">' +
                            '<p>' + response.data.message + '</p>' +
                            '<button type="button" class="notice-dismiss">' +
                            '<span class="screen-reader-text"><?php echo esc_js(__('Dismiss this notice.', 'cobra-ai')); ?></span>' +
                            '</button>' +
                            '</div>'
                        );
                        $('.wrap.cobra-ai-settings > h1').after($notice);
                    }
                },
                error: function() {
                    // Show error message
                    const $notice = $(
                        '<div class="notice notice-error is-dismissible">' +
                        '<p><?php echo esc_js(__('Failed to save settings. Please try again.', 'cobra-ai')); ?></p>' +
                        '<button type="button" class="notice-dismiss">' +
                        '<span class="screen-reader-text"><?php echo esc_js(__('Dismiss this notice.', 'cobra-ai')); ?></span>' +
                        '</button>' +
                        '</div>'
                    );
                    $('.wrap.cobra-ai-settings > h1').after($notice);
                },
                complete: function() {
                    // Reset button state
                    $submitButton.prop('disabled', false)
                        .html('<?php echo esc_js(__('Save Settings', 'cobra-ai')); ?>');
                }
            });
        });

        // Reset settings
        $('#reset-settings').on('click', function() {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to reset all settings to their default values?', 'cobra-ai')); ?>')) {
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true)
                .html('<span class="spinner is-active"></span> <?php echo esc_js(__('Resetting...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_reset_settings',
                    nonce: '<?php echo wp_create_nonce('cobra-ai-settings'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message);
                        $button.prop('disabled', false)
                            .text('<?php echo esc_js(__('Reset to Defaults', 'cobra-ai')); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Failed to reset settings. Please try again.', 'cobra-ai')); ?>');
                    $button.prop('disabled', false)
                        .text('<?php echo esc_js(__('Reset to Defaults', 'cobra-ai')); ?>');
                }
            });
        });

        // Clear cache
        $('#clear-cache').on('click', function() {
            const $button = $(this);
            $button.prop('disabled', true)
                .html('<span class="spinner is-active"></span> <?php echo esc_js(__('Clearing...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_clear_cache',
                    nonce: '<?php echo wp_create_nonce('cobra-ai-admin'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Cache cleared successfully.', 'cobra-ai')); ?>');
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Failed to clear cache. Please try again.', 'cobra-ai')); ?>');
                },
                complete: function() {
                    $button.prop('disabled', false)
                        .text('<?php echo esc_js(__('Clear Cache', 'cobra-ai')); ?>');
                }
            });
        });

        // Clear logs
        $('#clear-logs').on('click', function() {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to clear all logs?', 'cobra-ai')); ?>')) {
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true)
                .html('<span class="spinner is-active"></span> <?php echo esc_js(__('Clearing...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_clear_logs',
                    nonce: '<?php echo wp_create_nonce('cobra-ai-admin'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Logs cleared successfully.', 'cobra-ai')); ?>');
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Failed to clear logs. Please try again.', 'cobra-ai')); ?>');
                },
                complete: function() {
                    $button.prop('disabled', false)
                        .text('<?php echo esc_js(__('Clear Logs', 'cobra-ai')); ?>');
                }
            });
        });

        // Run diagnostics
        $('#run-diagnostics').on('click', function() {
            const $button = $(this);
            $button.prop('disabled', true)
                .html('<span class="spinner is-active"></span> <?php echo esc_js(__('Running...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_run_diagnostics',
                    nonce: '<?php echo wp_create_nonce('cobra-ai-admin'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Show results in a modal
                        const $modal = $(
                            '<div class="cobra-modal diagnostics-modal">' +
                            '<div class="cobra-modal-content">' +
                            '<div class="cobra-modal-header">' +
                            '<h2><?php echo esc_js(__('Diagnostic Results', 'cobra-ai')); ?></h2>' +
                            '<button type="button" class="close-modal">' +
                            '<span class="dashicons dashicons-no-alt"></span>' +
                            '</button>' +
                            '</div>' +
                            '<div class="cobra-modal-body">' +
                            response.data.html +
                            '</div>' +
                            '</div>' +
                            '</div>'
                        ).appendTo('body');

                        $modal.show();

                        // Handle modal close
                        $modal.find('.close-modal').on('click', function() {
                            $modal.remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Failed to run diagnostics. Please try again.', 'cobra-ai')); ?>');
                },
                complete: function() {
                    $button.prop('disabled', false)
                        .text('<?php echo esc_js(__('Run Diagnostics', 'cobra-ai')); ?>');
                }
            });
        });

        // Handle notice dismissal
        $(document).on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').slideUp(200, function() {
                $(this).remove();
            });
        });
    });
</script>