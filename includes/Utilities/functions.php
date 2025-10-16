<?php

namespace CobraAI;

defined('ABSPATH') || exit;

/**
 * Get plugin settings
 */
function cobra_ai_get_settings(?string $key = null, $default = null)
{
    $settings = get_option('cobra_ai_settings', []);

    if ($key === null) {
        return $settings;
    }

    return cobra_ai_get_array_value($settings, $key, $default);
}

/**
 * Update plugin settings
 */
function cobra_ai_update_settings(string $key, $value): bool
{
    $settings = get_option('cobra_ai_settings', []);
    cobra_ai_set_array_value($settings, $key, $value);
    return update_option('cobra_ai_settings', $settings);
}

/**
 * Get value from array using dot notation
 */
function cobra_ai_get_array_value(array $array, string $key, $default = null)
{
    if (strpos($key, '.') === false) {
        return $array[$key] ?? $default;
    }

    foreach (explode('.', $key) as $segment) {
        if (!is_array($array) || !array_key_exists($segment, $array)) {
            return $default;
        }
        $array = $array[$segment];
    }

    return $array;
}

/**
 * Set array value using dot notation
 */
function cobra_ai_set_array_value(array &$array, string $key, $value): void
{
    $keys = explode('.', $key);
    $current = &$array;

    foreach ($keys as $segment) {
        if (!isset($current[$segment]) || !is_array($current[$segment])) {
            $current[$segment] = [];
        }
        $current = &$current[$segment];
    }

    $current = $value;
}

/**
 * Format size in bytes to human readable
 */
function cobra_ai_format_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Format time duration
 */
function cobra_ai_format_duration(int $seconds): string
{
    $units = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    ];

    foreach ($units as $unit => $value) {
        if ($seconds >= $value) {
            $count = floor($seconds / $value);
            return $count . ' ' . $unit . ($count > 1 ? 's' : '');
        }
    }

    return '0 seconds';
}

/**
 * Generate unique ID
 */
function cobra_ai_generate_id(string $prefix = ''): string
{
    return uniqid($prefix . '_', true);
}

/**
 * Check if request is AJAX
 */
function cobra_ai_is_ajax(): bool
{
    return defined('DOING_AJAX') && DOING_AJAX;
}

/**
 * Safe JSON encode
 */
function cobra_ai_json_encode($data): string
{
    return wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Safe JSON decode
 */
function cobra_ai_json_decode(string $json, bool $assoc = true)
{
    $data = json_decode($json, $assoc);

    if (JSON_ERROR_NONE !== json_last_error()) {
        return null;
    }

    return $data;
}

/**
 * Get template part
 */
function cobra_ai_get_template(string $template, array $args = []): void
{
    $template_path = COBRA_AI_PATH . 'templates/' . $template . '.php';

    if (!file_exists($template_path)) {
        return;
    }

    extract($args);
    include $template_path;
}

/**
 * Check if debug mode is enabled
 */
function cobra_ai_is_debug(): bool
{
    return defined('WP_DEBUG') && WP_DEBUG;
}

/**
 * Log message to debug.log
 */
function cobra_ai_log(string $message, array $context = []): void
{
    if (!cobra_ai_is_debug()) {
        return;
    }

    $formatted = sprintf(
        '[Cobra AI] %s - %s',
        date('Y-m-d H:i:s'),
        $message
    );

    if (!empty($context)) {
        $formatted .= ' ' . cobra_ai_json_encode($context);
    }

   
}

/**
 * Verify nonce
 */
function cobra_ai_verify_nonce(string $nonce, string $action): bool
{
    return wp_verify_nonce($nonce, $action) !== false;
}

/**
 * Get current user capability
 */
function cobra_ai_current_user_can(string $capability): bool
{
    return current_user_can($capability);
}

/**
 * Clean directory
 */
function cobra_ai_clean_dir(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }

    $files = glob($dir . '/*');
    foreach ($files as $file) {
        is_dir($file) ? cobra_ai_clean_dir($file) : unlink($file);
    }

    return true;
}

/**
 * Get plugin info
 */
function cobra_ai_get_plugin_info(): array
{
    return [
        'version' => COBRA_AI_VERSION,
        'db_version' => get_option('cobra_ai_db_version'),
        'installed' => get_option('cobra_ai_installed'),
        'active_features' => get_option('cobra_ai_enabled_features', [])
    ];
}

/**
 * Check if feature is active
 */
function cobra_ai_is_feature_active(string $feature_id): bool
{
    $active_features = get_option('cobra_ai_enabled_features', []);
    return in_array($feature_id, $active_features);
}

/**
 * Get feature settings
 */
function cobra_ai_get_feature_settings(string $feature_id, ?string $key = null, $default = null)
{
    $settings = get_option('cobra_ai_' . $feature_id . '_options', []);

    if ($key === null) {
        return $settings;
    }

    return cobra_ai_get_array_value($settings, $key, $default);
}

function cobra_ai_admin(): ?\CobraAI\Admin {
    return cobra_ai()->admin;
}