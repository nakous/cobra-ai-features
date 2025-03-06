<?php
/**
 * Uninstall script for Cobra AI Features
 * 
 * This script runs when the plugin is uninstalled via the WordPress admin.
 * It handles the cleanup of all plugin-related data from the database.
 *
 * @package CobraAI
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin constants if not already defined
if (!defined('COBRA_AI_PATH')) {
    define('COBRA_AI_PATH', plugin_dir_path(__FILE__));
}

// Load necessary files for uninstallation
if (file_exists(COBRA_AI_PATH . 'includes/Database.php')) {
    require_once COBRA_AI_PATH . 'includes/Database.php';
}

/**
 * Main uninstall class
 */
class CobraAI_Uninstaller {
    /**
     * Core plugin tables
     */
    private $core_tables = [
        'cobra_system_logs',
        'cobra_feature_registry',
        'cobra_feature_dependencies',
        'cobra_analytics'
    ];

    /**
     * Core plugin options
     */
    private $core_options = [
        'cobra_ai_settings',
        'cobra_ai_enabled_features',
        'cobra_ai_db_version',
        'cobra_ai_installed',
        'cobra_ai_activated',
        'cobra_ai_last_backup'
    ];

    /**
     * User meta keys
     */
    private $user_meta_keys = [
        'cobra_ai_dismissed_notices',
        'cobra_ai_user_preferences'
    ];

    /**
     * Run uninstallation
     */
    public function run() {
        // Check if we should keep settings
        $keep_settings = get_option('cobra_ai_preserve_settings', false);

        // Get active features before removal
        $active_features = get_option('cobra_ai_enabled_features', []);

        try {
            // Begin uninstallation
            $this->log_uninstall_start();

            // Clean up feature data
            $this->uninstall_features($active_features);

            // Remove core tables
            $this->remove_core_tables();

            // Remove options
            $this->remove_options($keep_settings);

            // Remove user meta
            $this->remove_user_meta();

            // Remove transients
            $this->remove_transients();

            // Remove files if necessary
            $this->cleanup_files();

            // Log successful uninstallation
            $this->log_uninstall_complete();

        } catch (\Exception $e) {
            // Log uninstall error
            $this->log_uninstall_error($e);
        }
    }

    /**
     * Uninstall features
     */
    private function uninstall_features(array $features) {
        global $wpdb;

        foreach ($features as $feature_id) {
            try {
                // Get feature tables
                $feature_tables = $wpdb->get_col($wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $wpdb->esc_like($wpdb->prefix . 'cobra_' . $feature_id) . '%'
                ));

                // Remove feature tables
                foreach ($feature_tables as $table) {
                    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
                }

                // Remove feature options
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE %s",
                    'cobra_ai_' . $feature_id . '%'
                ));

                // Trigger feature-specific cleanup
                do_action('cobra_ai_feature_uninstall_' . $feature_id);

            } catch (\Exception $e) {
                $this->log_error(sprintf(
                    'Failed to uninstall feature %s: %s',
                    $feature_id,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Remove core tables
     */
    private function remove_core_tables() {
        global $wpdb;

        foreach ($this->core_tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}{$table}`");
        }
    }

    /**
     * Remove plugin options
     */
    private function remove_options($keep_settings) {
        if (!$keep_settings) {
            foreach ($this->core_options as $option) {
                delete_option($option);
            }
        }
    }

    /**
     * Remove user meta
     */
    private function remove_user_meta() {
        global $wpdb;

        foreach ($this->user_meta_keys as $meta_key) {
            $wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key]);
        }
    }

    /**
     * Remove transients
     */
    private function remove_transients() {
        global $wpdb;

        // Delete transients
        $wpdb->query(
            "DELETE FROM `{$wpdb->options}` 
            WHERE `option_name` LIKE '%_transient_cobra_ai_%' 
            OR `option_name` LIKE '%_transient_timeout_cobra_ai_%'"
        );
    }

    /**
     * Cleanup files
     */
    private function cleanup_files() {
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/cobra-ai';

        // Remove plugin uploads directory if it exists
        if (is_dir($plugin_dir)) {
            $this->remove_directory($plugin_dir);
        }
    }

    /**
     * Recursively remove directory
     */
    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Log uninstall start
     */
    private function log_uninstall_start() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Starting Cobra AI uninstallation');
        }
    }

    /**
     * Log uninstall complete
     */
    private function log_uninstall_complete() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Cobra AI uninstallation completed successfully');
        }
    }

    /**
     * Log uninstall error
     */
    private function log_uninstall_error(\Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Cobra AI uninstallation error: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Log general error
     */
    private function log_error(string $message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Cobra AI: ' . $message);
        }
    }
}

// Run uninstaller
$uninstaller = new CobraAI_Uninstaller();
$uninstaller->run();