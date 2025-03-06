<?php
namespace CobraAI\Utilities;

defined('ABSPATH') || exit;

/**
 * Validation utility class
 */
class Validator {
    /**
     * Validate settings array
     */
    public static function validate_settings(array $settings): array {
        $validated = [];
        
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $validated[$key] = self::validate_settings($value);
            } else {
                $validated[$key] = self::validate_value($value, $key);
            }
        }

        return $validated;
    }

    /**
     * Validate individual setting value
     */
    public static function validate_value($value, string $key) {
        // Detect and validate value based on key pattern
        if (strpos($key, 'email') !== false) {
            return self::validate_email($value);
        }
        
        if (strpos($key, 'url') !== false) {
            return self::validate_url($value);
        }
        
        if (strpos($key, 'color') !== false) {
            return self::validate_color($value);
        }

        return self::sanitize_value($value);
    }

    /**
     * Validate feature configuration
     */
    public static function validate_feature_config(array $config): array {
        $required_fields = ['id', 'name', 'version'];
        
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        return [
            'id' => sanitize_key($config['id']),
            'name' => sanitize_text_field($config['name']),
            'version' => self::validate_version($config['version']),
            'description' => sanitize_text_field($config['description'] ?? ''),
            'author' => sanitize_text_field($config['author'] ?? ''),
            'requires' => self::validate_dependencies($config['requires'] ?? []),
            'settings' => self::validate_settings($config['settings'] ?? [])
        ];
    }

    /**
     * Validate dependencies array
     */
    public static function validate_dependencies(array $dependencies): array {
        $validated = [];
        
        foreach ($dependencies as $dep) {
            if (is_array($dep)) {
                if (!isset($dep['id'])) {
                    continue;
                }
                $validated[] = [
                    'id' => sanitize_key($dep['id']),
                    'version' => self::validate_version($dep['version'] ?? ''),
                    'optional' => (bool) ($dep['optional'] ?? false)
                ];
            } else {
                $validated[] = sanitize_key($dep);
            }
        }

        return $validated;
    }

    /**
     * Validate version string
     */
    public static function validate_version(string $version): string {
        return preg_replace('/[^0-9.]/', '', $version);
    }

    /**
     * Validate email
     */
    public static function validate_email($email): string {
        $email = sanitize_email($email);
        return is_email($email) ? $email : '';
    }

    /**
     * Validate URL
     */
    public static function validate_url($url): string {
        return esc_url_raw($url);
    }

    /**
     * Validate color
     */
    public static function validate_color($color): string {
        if (preg_match('/^#[a-f0-9]{6}$/i', $color)) {
            return $color;
        }
        return '';
    }

    /**
     * Sanitize generic value
     */
    public static function sanitize_value($value) {
        if (is_numeric($value)) {
            return is_float($value) ? (float) $value : (int) $value;
        }
        
        if (is_bool($value)) {
            return (bool) $value;
        }
        
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        
        return $value;
    }

    /**
     * Validate API response
     */
    public static function validate_api_response($response): bool {
        if (is_wp_error($response)) {
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        return $status >= 200 && $status < 300;
    }

    /**
     * Validate file path
     */
    public static function validate_path(string $path): bool {
        return (
            !empty($path) &&
            file_exists($path) &&
            is_readable($path) &&
            !preg_match('/\.\.\//', $path)
        );
    }
}