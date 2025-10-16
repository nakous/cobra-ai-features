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

// Debug - ensure WordPress functions are available
if (!function_exists('get_option')) {
    wp_die('WordPress core functions are not available. Please reload the page.');
}

// Get current settings with defaults
$settings = cobra_ai_get_settings();

// Error handling
if (!is_array($settings)) {
    $settings = [];
}

// Helper function to safely get boolean value
function safe_bool($value, $default = false) {
    if (is_bool($value)) return $value;
    if (is_string($value)) return in_array(strtolower($value), ['1', 'true', 'yes', 'on']);
    if (is_numeric($value)) return (bool)$value;
    return $default;
}

// Helper function to safely get string value
function safe_string($value, $default = '') {
    if (is_string($value)) return $value;
    if (is_numeric($value)) return (string)$value;
    if (is_bool($value)) return $value ? '1' : '0';
    if (is_array($value)) return implode(',', $value);
    return $default;
}

// Helper function to safely get integer value
function safe_int($value, $default = 0, $min = null, $max = null) {
    $result = $default;
    
    if (is_int($value)) {
        $result = $value;
    } elseif (is_numeric($value)) {
        $result = (int)$value;
    } elseif (is_string($value) && is_numeric($value)) {
        $result = (int)$value;
    }
    
    // Apply min/max constraints if provided
    if ($min !== null && $result < $min) {
        $result = $min;
    }
    if ($max !== null && $result > $max) {
        $result = $max;
    }
    
    return $result;
}

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
    'wp_debug' => defined('WP_DEBUG') ? (WP_DEBUG ? 'Yes' : 'No') : 'No',
    'wp_debug_log' => defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'Yes' : 'No') : 'No',
    'cobra_ai_version' => defined('COBRA_AI_VERSION') ? COBRA_AI_VERSION : 'Unknown',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database_version' => $GLOBALS['wpdb']->db_version(),
    'active_plugins' => count(get_option('active_plugins', [])),
    'active_theme' => wp_get_theme()->get('Name'),
];

// Get database info
global $wpdb;
$database_info = [
    'tables_count' => $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}'"),
    'database_size' => $wpdb->get_var("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}'"),
    'last_backup' => get_option('cobra_ai_last_backup_date', __('Never', 'cobra-ai')),
    'backup_location' => get_option('cobra_ai_backup_location', wp_upload_dir()['basedir'] . '/cobra-ai-backups/'),
];
?>

<div class="wrap cobra-ai-settings">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Cobra AI Global Settings', 'cobra-ai'); ?>
        <span class="cobra-ai-version">v<?php echo esc_html($system_info['cobra_ai_version']); ?></span>
    </h1>

    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings updated successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'save-failed'): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('Failed to save settings. Please try again.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cobra-ai-settings-form">
        <input type="hidden" name="action" value="cobra_ai_save_settings">
        <input type="hidden" name="current_tab" id="current-tab" value="<?php echo esc_attr(isset($_GET['tab']) ? $_GET['tab'] : 'core'); ?>">
        <?php wp_nonce_field('cobra_ai_settings_nonce', 'cobra_ai_nonce'); ?>

        <!-- Settings Navigation -->
        <div class="cobra-ai-settings-nav">
            <ul class="nav-tabs">
                <li>
                    <a href="#core" class="nav-tab" data-tab="core">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php echo esc_html__('Core Settings', 'cobra-ai'); ?>
                    </a>
                </li>
                <li>
                    <a href="#security" class="nav-tab" data-tab="security">
                        <span class="dashicons dashicons-shield"></span>
                        <?php echo esc_html__('Security, Performance & Logging', 'cobra-ai'); ?>
                    </a>
                </li>
                <li>
                    <a href="#database" class="nav-tab" data-tab="database">
                        <span class="dashicons dashicons-database"></span>
                        <?php echo esc_html__('Database Backup', 'cobra-ai'); ?>
                    </a>
                </li>
                <li>
                    <a href="#system" class="nav-tab" data-tab="system">
                        <span class="dashicons dashicons-info"></span>
                        <?php echo esc_html__('System Info', 'cobra-ai'); ?>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Settings Content -->
        <div class="cobra-ai-settings-content">
            
            <!-- Core Settings Tab -->
            <div id="core" class="settings-tab">
                <div class="settings-card">
                    <h2>
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php echo esc_html__('Core Configuration', 'cobra-ai'); ?>
                    </h2>
                    <p class="description"><?php echo esc_html__('Configure the basic settings for Cobra AI plugin operation.', 'cobra-ai'); ?></p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="core_environment"><?php echo esc_html__('Environment Mode', 'cobra-ai'); ?></label>
                            </th>
                            <td>
                                <select id="core_environment" name="settings[core][environment]" class="regular-text">
                                    <option value="production" <?php selected(safe_string($settings['core']['environment'] ?? 'production'), 'production'); ?>>
                                        <?php echo esc_html__('Production', 'cobra-ai'); ?>
                                    </option>
                                    <option value="staging" <?php selected(safe_string($settings['core']['environment'] ?? 'production'), 'staging'); ?>>
                                        <?php echo esc_html__('Staging', 'cobra-ai'); ?>
                                    </option>
                                    <option value="development" <?php selected(safe_string($settings['core']['environment'] ?? 'production'), 'development'); ?>>
                                        <?php echo esc_html__('Development', 'cobra-ai'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php echo esc_html__('Choose the environment mode for appropriate error handling and logging.', 'cobra-ai'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="core_data_handling"><?php echo esc_html__('Data Handling Mode', 'cobra-ai'); ?></label>
                            </th>
                            <td>
                                <select id="core_data_handling" name="settings[core][data_handling]" class="regular-text">
                                    <option value="strict" <?php selected(safe_string($settings['core']['data_handling'] ?? 'strict'), 'strict'); ?>>
                                        <?php echo esc_html__('Strict - Maximum validation', 'cobra-ai'); ?>
                                    </option>
                                    <option value="balanced" <?php selected(safe_string($settings['core']['data_handling'] ?? 'strict'), 'balanced'); ?>>
                                        <?php echo esc_html__('Balanced - Standard validation', 'cobra-ai'); ?>
                                    </option>
                                    <option value="flexible" <?php selected(safe_string($settings['core']['data_handling'] ?? 'strict'), 'flexible'); ?>>
                                        <?php echo esc_html__('Flexible - Minimal validation', 'cobra-ai'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php echo esc_html__('Controls how strictly data is validated and processed.', 'cobra-ai'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="core_timezone"><?php echo esc_html__('Plugin Timezone', 'cobra-ai'); ?></label>
                            </th>
                            <td>
                                <select id="core_timezone" name="settings[core][timezone]" class="regular-text">
                                    <option value="wp_default" <?php selected(safe_string($settings['core']['timezone'] ?? 'wp_default'), 'wp_default'); ?>>
                                        <?php echo esc_html__('Use WordPress Timezone', 'cobra-ai'); ?>
                                    </option>
                                    <option value="utc" <?php selected(safe_string($settings['core']['timezone'] ?? 'wp_default'), 'utc'); ?>>
                                        <?php echo esc_html__('UTC', 'cobra-ai'); ?>
                                    </option>
                                    <option value="server" <?php selected(safe_string($settings['core']['timezone'] ?? 'wp_default'), 'server'); ?>>
                                        <?php echo esc_html__('Server Timezone', 'cobra-ai'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="core_cleanup"><?php echo esc_html__('Automatic Cleanup', 'cobra-ai'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="settings[core][auto_cleanup]" value="1" <?php checked(safe_bool($settings['core']['auto_cleanup'] ?? true)); ?>>
                                        <?php echo esc_html__('Enable automatic cleanup of old data', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="number" name="settings[core][cleanup_days]" value="<?php echo esc_attr(safe_int($settings['core']['cleanup_days'] ?? 90, 90, 1, 365)); ?>" min="1" max="365" class="small-text">
                                        <?php echo esc_html__('Delete data older than (days)', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="core_features_auto_update"><?php echo esc_html__('Feature Management', 'cobra-ai'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="settings[core][features_auto_update]" value="1" <?php checked(safe_bool($settings['core']['features_auto_update'] ?? false)); ?>>
                                        <?php echo esc_html__('Auto-update feature configurations', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="settings[core][features_isolation]" value="1" <?php checked(safe_bool($settings['core']['features_isolation'] ?? true)); ?>>
                                        <?php echo esc_html__('Enable feature isolation (prevents conflicts)', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Security, Performance & Logging Tab -->
            <div id="security" class="settings-tab">
                <!-- Security Section -->
                <div class="settings-card">
                    <h2>
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php echo esc_html__('Security Settings', 'cobra-ai'); ?>
                    </h2>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Rate Limiting', 'cobra-ai'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="settings[security][enable_rate_limiting]" value="1" <?php checked(safe_bool($settings['security']['enable_rate_limiting'] ?? true)); ?>>
                                        <?php echo esc_html__('Enable rate limiting protection', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="number" name="settings[security][rate_limit_requests]" value="<?php echo esc_attr(safe_int($settings['security']['rate_limit_requests'] ?? 60, 60, 1, 1000)); ?>" min="1" max="1000" class="small-text">
                                        <?php echo esc_html__('Max requests per hour per user', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="number" name="settings[security][rate_limit_period]" value="<?php echo esc_attr(safe_int($settings['security']['rate_limit_period'] ?? 3600, 3600, 60, 86400)); ?>" min="60" max="86400" class="small-text">
                                        <?php echo esc_html__('Rate limit period (seconds)', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="allowed_ips"><?php echo esc_html__('IP Access Control', 'cobra-ai'); ?></label>
                            </th>
                            <td>
                                <textarea id="allowed_ips" name="settings[security][allowed_ip_addresses]" rows="4" class="large-text" placeholder="<?php echo esc_attr__('Leave empty to allow all IPs, or enter one IP per line', 'cobra-ai'); ?>"><?php 
                                    $allowed_ips = $settings['security']['allowed_ip_addresses'] ?? [];
                                    if (is_array($allowed_ips)) {
                                        echo esc_textarea(implode("\n", $allowed_ips));
                                    } elseif (is_string($allowed_ips)) {
                                        echo esc_textarea($allowed_ips);
                                    }
                                ?></textarea>
                                <p class="description"><?php echo esc_html__('Restrict access to specific IP addresses. Leave empty to allow all.', 'cobra-ai'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Authentication', 'cobra-ai'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="settings[security][require_authentication]" value="1" <?php checked(safe_bool($settings['security']['require_authentication'] ?? true)); ?>>
                                        <?php echo esc_html__('Require user authentication for API access', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="settings[security][api_key_authentication]" value="1" <?php checked(safe_bool($settings['security']['api_key_authentication'] ?? false)); ?>>
                                        <?php echo esc_html__('Enable API key authentication', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Performance Section -->
                <div class="settings-card">
                    <h2>
                        <span class="dashicons dashicons-performance"></span>
                        <?php echo esc_html__('Performance Settings', 'cobra-ai'); ?>
                    </h2>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Caching', 'cobra-ai'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="settings[performance][enable_caching]" value="1" <?php checked(safe_bool($settings['performance']['enable_caching'] ?? true)); ?>>
                                        <?php echo esc_html__('Enable response caching', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="number" name="settings[performance][cache_duration]" value="<?php echo esc_attr(safe_int($settings['performance']['cache_duration'] ?? 3600, 3600, 60, 86400)); ?>" min="60" max="86400" class="small-text">
                                        <?php echo esc_html__('Cache duration (seconds)', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="settings[performance][cache_compression]" value="1" <?php checked(safe_bool($settings['performance']['cache_compression'] ?? true)); ?>>
                                        <?php echo esc_html__('Enable cache compression', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Optimization', 'cobra-ai'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="settings[performance][minify_output]" value="1" <?php checked(safe_bool($settings['performance']['minify_output'] ?? true)); ?>>
                                        <?php echo esc_html__('Minify JSON output', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="settings[performance][lazy_loading]" value="1" <?php checked(safe_bool($settings['performance']['lazy_loading'] ?? true)); ?>>
                                        <?php echo esc_html__('Enable lazy loading for features', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="number" name="settings[performance][max_execution_time]" value="<?php echo esc_attr(safe_int($settings['performance']['max_execution_time'] ?? 30, 30, 5, 300)); ?>" min="5" max="300" class="small-text">
                                        <?php echo esc_html__('Max execution time (seconds)', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Resource Limits', 'cobra-ai'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="number" name="settings[performance][memory_limit]" value="<?php echo esc_attr(safe_int($settings['performance']['memory_limit'] ?? 256, 256, 64, 1024)); ?>" min="64" max="1024" class="small-text">
                                        <?php echo esc_html__('Memory limit (MB)', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="number" name="settings[performance][max_concurrent_requests]" value="<?php echo esc_attr(safe_int($settings['performance']['max_concurrent_requests'] ?? 10, 10, 1, 50)); ?>" min="1" max="50" class="small-text">
                                        <?php echo esc_html__('Max concurrent requests', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Logging Section -->
                <div class="settings-card">
                    <h2>
                        <span class="dashicons dashicons-list-view"></span>
                        <?php echo esc_html__('Logging Settings', 'cobra-ai'); ?>
                    </h2>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php echo esc_html__('General Logging', 'cobra-ai'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="settings[logging][enable_logging]" value="1" <?php checked(safe_bool($settings['logging']['enable_logging'] ?? true)); ?>>
                                        <?php echo esc_html__('Enable system logging', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <select name="settings[logging][log_level]" class="regular-text">
                                            <option value="error" <?php selected(safe_string($settings['logging']['log_level'] ?? 'error'), 'error'); ?>>
                                                <?php echo esc_html__('Error - Critical issues only', 'cobra-ai'); ?>
                                            </option>
                                            <option value="warning" <?php selected(safe_string($settings['logging']['log_level'] ?? 'error'), 'warning'); ?>>
                                                <?php echo esc_html__('Warning - Errors and warnings', 'cobra-ai'); ?>
                                            </option>
                                            <option value="info" <?php selected(safe_string($settings['logging']['log_level'] ?? 'error'), 'info'); ?>>
                                                <?php echo esc_html__('Info - General information', 'cobra-ai'); ?>
                                            </option>
                                            <option value="debug" <?php selected(safe_string($settings['logging']['log_level'] ?? 'error'), 'debug'); ?>>
                                                <?php echo esc_html__('Debug - Everything (development only)', 'cobra-ai'); ?>
                                            </option>
                                        </select>
                                        <?php echo esc_html__('Logging level', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Log Management', 'cobra-ai'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="number" name="settings[logging][max_log_age]" value="<?php echo esc_attr(safe_int($settings['logging']['max_log_age'] ?? 30, 30, 1, 365)); ?>" min="1" max="365" class="small-text">
                                        <?php echo esc_html__('Keep logs for (days)', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="number" name="settings[logging][max_log_size]" value="<?php echo esc_attr(safe_int($settings['logging']['max_log_size'] ?? 10, 10, 1, 100)); ?>" min="1" max="100" class="small-text">
                                        <?php echo esc_html__('Max log file size (MB)', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="settings[logging][log_user_actions]" value="1" <?php checked(safe_bool($settings['logging']['log_user_actions'] ?? true)); ?>>
                                        <?php echo esc_html__('Log user actions', 'cobra-ai'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="settings[logging][log_api_requests]" value="1" <?php checked(safe_bool($settings['logging']['log_api_requests'] ?? true)); ?>>
                                        <?php echo esc_html__('Log API requests', 'cobra-ai'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Database Backup Tab -->
            <div id="database" class="settings-tab">
                <div class="settings-card">
                    <h2>
                        <span class="dashicons dashicons-database-add"></span>
                        <?php echo esc_html__('Database Backup Management', 'cobra-ai'); ?>
                    </h2>
                    <p class="description"><?php echo esc_html__('Backup and restore your Cobra AI plugin data and configurations.', 'cobra-ai'); ?></p>

                    <!-- Backup Information -->
                    <div class="backup-info">
                        <h3><?php echo esc_html__('Backup Information', 'cobra-ai'); ?></h3>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Last Backup', 'cobra-ai'); ?></th>
                                <td>
                                    <strong><?php echo esc_html($database_info['last_backup']); ?></strong>
                                    <?php if ($database_info['last_backup'] !== __('Never', 'cobra-ai')): ?>
                                        <span class="description">(<?php echo esc_html(human_time_diff(strtotime($database_info['last_backup']))); ?> ago)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Backup Location', 'cobra-ai'); ?></th>
                                <td>
                                    <code><?php echo esc_html($database_info['backup_location']); ?></code>
                                    <p class="description"><?php echo esc_html__('Default backup directory. Make sure this location is writable.', 'cobra-ai'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Database Size', 'cobra-ai'); ?></th>
                                <td>
                                    <strong><?php echo esc_html($database_info['database_size']); ?> MB</strong>
                                    <span class="description">(<?php echo esc_html($database_info['tables_count']); ?> tables)</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Backup Settings -->
                    <div class="backup-settings">
                        <h3><?php echo esc_html__('Backup Settings', 'cobra-ai'); ?></h3>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php echo esc_html__('Automatic Backup', 'cobra-ai'); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="settings[backup][auto_backup]" value="1" <?php checked(safe_bool($settings['backup']['auto_backup'] ?? false)); ?>>
                                            <?php echo esc_html__('Enable automatic daily backups', 'cobra-ai'); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <select name="settings[backup][backup_time]" class="regular-text">
                                                <option value="02:00" <?php selected(safe_string($settings['backup']['backup_time'] ?? '02:00'), '02:00'); ?>>02:00</option>
                                                <option value="03:00" <?php selected(safe_string($settings['backup']['backup_time'] ?? '02:00'), '03:00'); ?>>03:00</option>
                                                <option value="04:00" <?php selected(safe_string($settings['backup']['backup_time'] ?? '02:00'), '04:00'); ?>>04:00</option>
                                                <option value="05:00" <?php selected(safe_string($settings['backup']['backup_time'] ?? '02:00'), '05:00'); ?>>05:00</option>
                                            </select>
                                            <?php echo esc_html__('Backup time (24h format)', 'cobra-ai'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Backup Retention', 'cobra-ai'); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="number" name="settings[backup][retention_days]" value="<?php echo esc_attr(safe_int($settings['backup']['retention_days'] ?? 30, 30, 1, 365)); ?>" min="1" max="365" class="small-text">
                                            <?php echo esc_html__('Keep backups for (days)', 'cobra-ai'); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="number" name="settings[backup][max_backups]" value="<?php echo esc_attr(safe_int($settings['backup']['max_backups'] ?? 10, 10, 1, 50)); ?>" min="1" max="50" class="small-text">
                                            <?php echo esc_html__('Maximum number of backups to keep', 'cobra-ai'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Backup Options', 'cobra-ai'); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="settings[backup][include_uploads]" value="1" <?php checked(safe_bool($settings['backup']['include_uploads'] ?? false)); ?>>
                                            <?php echo esc_html__('Include uploaded files', 'cobra-ai'); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="settings[backup][compress_backup]" value="1" <?php checked(safe_bool($settings['backup']['compress_backup'] ?? true)); ?>>
                                            <?php echo esc_html__('Compress backup files', 'cobra-ai'); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox" name="settings[backup][email_notification]" value="1" <?php checked(safe_bool($settings['backup']['email_notification'] ?? false)); ?>>
                                            <?php echo esc_html__('Email notification on backup completion', 'cobra-ai'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__('Backup Location', 'cobra-ai'); ?></th>
                                <td>
                                    <input type="text" name="settings[backup][custom_location]" value="<?php echo esc_attr(safe_string($settings['backup']['custom_location'] ?? '')); ?>" class="large-text" placeholder="<?php echo esc_attr($database_info['backup_location']); ?>">
                                    <p class="description"><?php echo esc_html__('Custom backup directory (leave empty to use default location)', 'cobra-ai'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Backup Actions -->
                    <div class="backup-actions">
                        <h3><?php echo esc_html__('Backup Actions', 'cobra-ai'); ?></h3>
                        <div class="backup-buttons">
                            <button type="button" class="button button-primary" id="create-backup" data-loading-text="<?php echo esc_attr__('Creating backup...', 'cobra-ai'); ?>">
                                <span class="dashicons dashicons-database-export"></span>
                                <?php echo esc_html__('Create Backup Now', 'cobra-ai'); ?>
                            </button>
                            <button type="button" class="button" id="download-backup">
                                <span class="dashicons dashicons-download"></span>
                                <?php echo esc_html__('Download Latest Backup', 'cobra-ai'); ?>
                            </button>
                            <button type="button" class="button" id="restore-backup">
                                <span class="dashicons dashicons-database-import"></span>
                                <?php echo esc_html__('Restore from Backup', 'cobra-ai'); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="cleanup-old-backups">
                                <span class="dashicons dashicons-trash"></span>
                                <?php echo esc_html__('Cleanup Old Backups', 'cobra-ai'); ?>
                            </button>
                        </div>
                        
                        <!-- Upload Backup for Restore -->
                        <div class="restore-upload" style="margin-top: 20px;">
                            <h4><?php echo esc_html__('Restore from File', 'cobra-ai'); ?></h4>
                            <input type="file" id="backup-file" accept=".sql,.zip" class="regular-text">
                            <button type="button" class="button" id="upload-restore">
                                <?php echo esc_html__('Upload & Restore', 'cobra-ai'); ?>
                            </button>
                            <p class="description"><?php echo esc_html__('Upload a backup file (.sql or .zip) to restore your data.', 'cobra-ai'); ?></p>
                        </div>
                    </div>

                    <!-- Backup History -->
                    <div class="backup-history">
                        <h3><?php echo esc_html__('Backup History', 'cobra-ai'); ?></h3>
                        <div id="backup-history-table">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Date', 'cobra-ai'); ?></th>
                                        <th><?php echo esc_html__('Size', 'cobra-ai'); ?></th>
                                        <th><?php echo esc_html__('Type', 'cobra-ai'); ?></th>
                                        <th><?php echo esc_html__('Status', 'cobra-ai'); ?></th>
                                        <th><?php echo esc_html__('Actions', 'cobra-ai'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="backup-list">
                                    <tr>
                                        <td colspan="5" class="no-backups">
                                            <?php echo esc_html__('No backups found. Create your first backup above.', 'cobra-ai'); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Info Tab -->
            <div id="system" class="settings-tab">
                <div class="settings-card">
                    <h2>
                        <span class="dashicons dashicons-info"></span>
                        <?php echo esc_html__('System Information', 'cobra-ai'); ?>
                    </h2>
                    <p class="description"><?php echo esc_html__('Detailed information about your server environment and Cobra AI plugin status.', 'cobra-ai'); ?></p>

                    <!-- WordPress & PHP Info -->
                    <div class="system-section">
                        <h3><?php echo esc_html__('WordPress & PHP Environment', 'cobra-ai'); ?></h3>
                        <table class="widefat striped system-info-table">
                            <tbody>
                                <tr>
                                    <td class="label"><?php echo esc_html__('WordPress Version', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['wp_version']); ?></td>
                                    <td class="status">
                                        <?php if (version_compare($system_info['wp_version'], '5.8', '>=')): ?>
                                            <span class="status-good">✓</span>
                                        <?php else: ?>
                                            <span class="status-warning">⚠</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('PHP Version', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['php_version']); ?></td>
                                    <td class="status">
                                        <?php if (version_compare($system_info['php_version'], '7.4', '>=')): ?>
                                            <span class="status-good">✓</span>
                                        <?php else: ?>
                                            <span class="status-error">✗</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Memory Limit', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['memory_limit']); ?></td>
                                    <td class="status">
                                        <?php 
                                        $memory_mb = (int)$system_info['memory_limit'];
                                        if ($memory_mb >= 256): ?>
                                            <span class="status-good">✓</span>
                                        <?php elseif ($memory_mb >= 128): ?>
                                            <span class="status-warning">⚠</span>
                                        <?php else: ?>
                                            <span class="status-error">✗</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Max Execution Time', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['max_execution_time']); ?>s</td>
                                    <td class="status">
                                        <?php if ((int)$system_info['max_execution_time'] >= 30): ?>
                                            <span class="status-good">✓</span>
                                        <?php else: ?>
                                            <span class="status-warning">⚠</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Upload Max Filesize', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['upload_max_filesize']); ?></td>
                                    <td class="status"><span class="status-info">ℹ</span></td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Post Max Size', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['post_max_size']); ?></td>
                                    <td class="status"><span class="status-info">ℹ</span></td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Max Input Vars', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['max_input_vars']); ?></td>
                                    <td class="status">
                                        <?php if ((int)$system_info['max_input_vars'] >= 1000): ?>
                                            <span class="status-good">✓</span>
                                        <?php else: ?>
                                            <span class="status-warning">⚠</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Server & Database Info -->
                    <div class="system-section">
                        <h3><?php echo esc_html__('Server & Database', 'cobra-ai'); ?></h3>
                        <table class="widefat striped system-info-table">
                            <tbody>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Server Software', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['server_software']); ?></td>
                                    <td class="status"><span class="status-info">ℹ</span></td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Database Version', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['database_version']); ?></td>
                                    <td class="status"><span class="status-good">✓</span></td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Active Plugins', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['active_plugins']); ?></td>
                                    <td class="status"><span class="status-info">ℹ</span></td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Active Theme', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['active_theme']); ?></td>
                                    <td class="status"><span class="status-info">ℹ</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Debug Information -->
                    <div class="system-section">
                        <h3><?php echo esc_html__('Debug & Development', 'cobra-ai'); ?></h3>
                        <table class="widefat striped system-info-table">
                            <tbody>
                                <tr>
                                    <td class="label"><?php echo esc_html__('WP Debug', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['wp_debug']); ?></td>
                                    <td class="status">
                                        <?php if ($system_info['wp_debug'] === 'Yes'): ?>
                                            <span class="status-warning">⚠</span>
                                        <?php else: ?>
                                            <span class="status-good">✓</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('WP Debug Log', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['wp_debug_log']); ?></td>
                                    <td class="status"><span class="status-info">ℹ</span></td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Display Errors', 'cobra-ai'); ?></td>
                                    <td class="value"><?php echo esc_html($system_info['display_errors'] ?: 'Off'); ?></td>
                                    <td class="status">
                                        <?php if ($system_info['display_errors']): ?>
                                            <span class="status-warning">⚠</span>
                                        <?php else: ?>
                                            <span class="status-good">✓</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo esc_html__('Cobra AI Version', 'cobra-ai'); ?></td>
                                    <td class="value">v<?php echo esc_html($system_info['cobra_ai_version']); ?></td>
                                    <td class="status"><span class="status-good">✓</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- System Actions -->
                    <div class="system-actions">
                        <h3><?php echo esc_html__('System Actions', 'cobra-ai'); ?></h3>
                        <div class="action-buttons">
                            <button type="button" class="button" id="clear-cache">
                                <span class="dashicons dashicons-trash"></span>
                                <?php echo esc_html__('Clear Cache', 'cobra-ai'); ?>
                            </button>
                            <button type="button" class="button" id="clear-logs">
                                <span class="dashicons dashicons-media-text"></span>
                                <?php echo esc_html__('Clear Logs', 'cobra-ai'); ?>
                            </button>
                            <button type="button" class="button" id="run-diagnostics">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php echo esc_html__('Run Diagnostics', 'cobra-ai'); ?>
                            </button>
                            <button type="button" class="button" id="download-system-info">
                                <span class="dashicons dashicons-download"></span>
                                <?php echo esc_html__('Download System Info', 'cobra-ai'); ?>
                            </button>
                            <button type="button" class="button" id="test-email">
                                <span class="dashicons dashicons-email"></span>
                                <?php echo esc_html__('Test Email', 'cobra-ai'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Diagnostic Results -->
                    <div class="diagnostic-results" id="diagnostic-results" style="display: none;">
                        <h3><?php echo esc_html__('Diagnostic Results', 'cobra-ai'); ?></h3>
                        <div class="diagnostic-content">
                            <!-- Results will be populated via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="cobra-ai-settings-footer">
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('Save Settings', 'cobra-ai'); ?>">
                <button type="button" class="button button-secondary" id="reset-settings" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset all settings to default values?', 'cobra-ai')); ?>')">
                    <?php echo esc_html__('Reset to Defaults', 'cobra-ai'); ?>
                </button>
            </p>
        </div>
    </form>
</div>

<!-- Loading Overlay -->
<div id="cobra-ai-loading" class="cobra-ai-loading" style="display: none;">
    <div class="loading-content">
        <span class="spinner is-active"></span>
        <p><?php echo esc_html__('Processing...', 'cobra-ai'); ?></p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';

    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('tab');
        
        // Update tab appearance
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show/hide content
        $('.settings-tab').removeClass('active').hide();
        $('#' + target).addClass('active').show();
        
        // Update hidden field for form submission
        $('#current-tab').val(target);
        
        // Update URL hash
        window.location.hash = '#' + target;
    });

    // Initialize active tab from URL parameter or hash
    var activeTab = '<?php echo isset($_GET['tab']) ? esc_js($_GET['tab']) : ''; ?>' || 
                    window.location.hash.replace('#', '') || 
                    'core';
    
    // Trigger click on the active tab
    $('.nav-tab[data-tab="' + activeTab + '"]').trigger('click');
    
    // If no tab found, default to core
    if (!$('.nav-tab.nav-tab-active').length) {
        $('.nav-tab[data-tab="core"]').trigger('click');
    }

    // Loading functions
    function showLoading(message) {
        $('#cobra-ai-loading p').text(message || '<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');
        $('#cobra-ai-loading').show();
    }

    function hideLoading() {
        $('#cobra-ai-loading').hide();
    }

    // System Actions
    $('#clear-cache').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to clear the cache?', 'cobra-ai')); ?>')) {
            showLoading('<?php echo esc_js(__('Clearing cache...', 'cobra-ai')); ?>');
            
            $.post(ajaxurl, {
                action: 'cobra_ai_clear_cache',
                nonce: '<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'
            }).done(function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Cache cleared successfully.', 'cobra-ai')); ?>');
                } else {
                    alert('<?php echo esc_js(__('Error clearing cache.', 'cobra-ai')); ?>');
                }
            }).always(function() {
                hideLoading();
            });
        }
    });

    $('#clear-logs').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to clear all logs?', 'cobra-ai')); ?>')) {
            showLoading('<?php echo esc_js(__('Clearing logs...', 'cobra-ai')); ?>');
            
            $.post(ajaxurl, {
                action: 'cobra_ai_clear_logs',
                nonce: '<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'
            }).done(function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Logs cleared successfully.', 'cobra-ai')); ?>');
                } else {
                    alert('<?php echo esc_js(__('Error clearing logs.', 'cobra-ai')); ?>');
                }
            }).always(function() {
                hideLoading();
            });
        }
    });

    $('#run-diagnostics').on('click', function() {
        showLoading('<?php echo esc_js(__('Running diagnostics...', 'cobra-ai')); ?>');
        
        $.post(ajaxurl, {
            action: 'cobra_ai_run_diagnostics',
            nonce: '<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'
        }).done(function(response) {
            if (response.success) {
                $('#diagnostic-results .diagnostic-content').html(response.data.html);
                $('#diagnostic-results').show();
            } else {
                alert('<?php echo esc_js(__('Error running diagnostics.', 'cobra-ai')); ?>');
            }
        }).always(function() {
            hideLoading();
        });
    });

    // Backup Actions
    $('#create-backup').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        var loadingText = $button.data('loading-text');
        
        $button.prop('disabled', true).text(loadingText);
        
        $.post(ajaxurl, {
            action: 'cobra_ai_create_backup',
            nonce: '<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'
        }).done(function(response) {
            if (response.success) {
                alert('<?php echo esc_js(__('Backup created successfully.', 'cobra-ai')); ?>');
                loadBackupHistory();
            } else {
                alert('<?php echo esc_js(__('Error creating backup: ', 'cobra-ai')); ?>' + (response.data || ''));
            }
        }).always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });

    $('#download-backup').on('click', function() {
        window.open(ajaxurl + '?action=cobra_ai_download_backup&nonce=' + encodeURIComponent('<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'), '_blank');
    });

    $('#cleanup-old-backups').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to delete old backup files?', 'cobra-ai')); ?>')) {
            showLoading('<?php echo esc_js(__('Cleaning up old backups...', 'cobra-ai')); ?>');
            
            $.post(ajaxurl, {
                action: 'cobra_ai_cleanup_backups',
                nonce: '<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'
            }).done(function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Old backups cleaned up successfully.', 'cobra-ai')); ?>');
                    loadBackupHistory();
                } else {
                    alert('<?php echo esc_js(__('Error cleaning up backups.', 'cobra-ai')); ?>');
                }
            }).always(function() {
                hideLoading();
            });
        }
    });

    // Load backup history
    function loadBackupHistory() {
        $.post(ajaxurl, {
            action: 'cobra_ai_get_backup_history',
            nonce: '<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'
        }).done(function(response) {
            if (response.success && response.data.html) {
                $('#backup-list').html(response.data.html);
            }
        });
    }

    // Load backup history on page load
    loadBackupHistory();

    // Download system info
    $('#download-system-info').on('click', function() {
        window.open(ajaxurl + '?action=cobra_ai_download_system_info&nonce=' + encodeURIComponent('<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'), '_blank');
    });

    // Test email
    $('#test-email').on('click', function() {
        var email = prompt('<?php echo esc_js(__('Enter email address to send test email:', 'cobra-ai')); ?>');
        if (email) {
            showLoading('<?php echo esc_js(__('Sending test email...', 'cobra-ai')); ?>');
            
            $.post(ajaxurl, {
                action: 'cobra_ai_test_email',
                email: email,
                nonce: '<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'
            }).done(function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Test email sent successfully.', 'cobra-ai')); ?>');
                } else {
                    alert('<?php echo esc_js(__('Error sending test email: ', 'cobra-ai')); ?>' + (response.data || ''));
                }
            }).always(function() {
                hideLoading();
            });
        }
    });

    // Reset settings
    $('#reset-settings').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to reset all settings to default values? This cannot be undone.', 'cobra-ai')); ?>')) {
            showLoading('<?php echo esc_js(__('Resetting settings...', 'cobra-ai')); ?>');
            
            $.post(ajaxurl, {
                action: 'cobra_ai_reset_settings',
                nonce: '<?php echo wp_create_nonce('cobra_ai_admin_nonce'); ?>'
            }).done(function(response) {
                if (response.success) {
                    alert('<?php echo esc_js(__('Settings reset successfully. Page will reload.', 'cobra-ai')); ?>');
                    window.location.reload();
                } else {
                    alert('<?php echo esc_js(__('Error resetting settings.', 'cobra-ai')); ?>');
                }
            }).always(function() {
                hideLoading();
            });
        }
    });

    // Form validation and submission
    $('#cobra-ai-settings-form').on('submit', function(e) {
        showLoading('<?php echo esc_js(__('Saving settings...', 'cobra-ai')); ?>');
        
        // Allow normal form submission to admin-post.php
        // No e.preventDefault() - let the form submit normally
        
        // Hide loading after a delay in case of redirect
        setTimeout(function() {
            hideLoading();
        }, 5000);
    });
});
</script>