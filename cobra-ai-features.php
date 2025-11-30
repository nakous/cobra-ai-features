<?php

/**
 * Plugin Name: Cobra AI Features
 * Description: Modular AI-powered features for WordPress
 * Version: 2.0.0
 * Author: Nakous Mustapha
 * Author URI: https://onlevelup.com
 * Text Domain: cobra-ai
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

namespace CobraAI;

// Prevent direct access
defined('ABSPATH') || exit;

// Composer autoloader
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

/**
 * Main plugin class
 */
final class CobraAI
{
    /**
     * Plugin version
     */
    const VERSION = '2.0.0';
    
    /**
     * Core components
     */
    public Database $db;
    public APIManager $api;
    public Admin $admin;
    
    /**
     * Plugin instance
     */
    private static ?CobraAI $instance = null;
    
    /**
     * Plugin components container
     */
    private array $container = [];
    
    /**
     * Loaded features cache
     */
    private array $features = [];

    /**
     * Get plugin instance
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        try {
            $this->define_constants();
            
            if (!$this->check_requirements()) {
                return;
            }
            
            $this->include_files();
            $this->init_database();
            $this->init_container();
            $this->init_hooks();
            
        } catch (\Exception $e) {
            $this->log_error('Plugin initialization failed', $e);
        }
    }

    /**
     * Define plugin constants
     */
    private function define_constants(): void
    {
        define('COBRA_AI_VERSION', self::VERSION);
        define('COBRA_AI_FILE', __FILE__);
        define('COBRA_AI_PATH', plugin_dir_path(__FILE__));
        define('COBRA_AI_URL', plugin_dir_url(__FILE__));
        define('COBRA_AI_INCLUDES', COBRA_AI_PATH . 'includes/');
        define('COBRA_AI_ADMIN', COBRA_AI_PATH . 'admin/');
        define('COBRA_AI_FEATURES_DIR', COBRA_AI_PATH . 'features/');
        define('COBRA_AI_ASSETS', COBRA_AI_URL . 'assets/');
    }

    /**
     * Check plugin requirements
     */
    private function check_requirements(): bool
    {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return false;
        }

        // Vérifier la version WordPress de manière sûre
        $wp_version = null;
        if (function_exists('get_bloginfo')) {
            $wp_version = \get_bloginfo('version');
        } elseif (isset($GLOBALS['wp_version'])) {
            $wp_version = $GLOBALS['wp_version'];
        }
        
        if ($wp_version && version_compare($wp_version, '5.8', '<')) {
            add_action('admin_notices', [$this, 'wp_version_notice']);
            return false;
        }

        return true;
    }

    /**
     * Include required files
     */
    private function include_files(): void
    {
        $files = [
            'FeatureBase.php',
            'Database.php',
            'Admin.php',
            'APIManager.php',
            'utilities/functions.php',
            'utilities/Validator.php'
        ];

        foreach ($files as $file) {
            require_once COBRA_AI_INCLUDES . $file;
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // WordPress hooks
        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_action('init', [$this, 'init_features'],0);
    }

    /**
     * Initialize plugin components
     */
    public function init_plugin(): void
    {
        try {
            // Load translations
            $this->load_translations();
            
            do_action('cobra_ai_loaded');
        } catch (\Exception $e) {
            $this->log_error('Plugin initialization failed', $e);
        }
    }
    
    /**
     * Load plugin translations
     */
    private function load_translations(): void
    {
        // Force unload to clear cache
        unload_textdomain('cobra-ai');
        
        // Get current locale
        $locale = get_locale();
        
        // Apply filters for custom locale if needed
        $locale = apply_filters('plugin_locale', $locale, 'cobra-ai');
        
        // Build paths to translation files
        $mofile = COBRA_AI_PATH . 'languages/cobra-ai-' . $locale . '.mo';
        $mofile_local = COBRA_AI_PATH . 'languages/' . $locale . '.mo';
        
        // Try to load translation file (with text domain prefix first)
        if (file_exists($mofile)) {
            load_textdomain('cobra-ai', $mofile);
        } elseif (file_exists($mofile_local)) {
            // Fallback to file without prefix
            load_textdomain('cobra-ai', $mofile_local);
        }
        
        // Also use WordPress standard function for global languages directory
        load_plugin_textdomain('cobra-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Initialize database
     */
    private function init_database(): void
    {
        try {
            $this->db = Database::get_instance();
            $this->db->install_or_upgrade();
        } catch (\Exception $e) {
            // Use simple error_log since DB isn't ready yet
            $this->log_error('Database initialization failed', $e, true);
        }
    }

    /**
     * Initialize dependency container
     */
    private function init_container(): void
    {
        try {
            // Core components
            $this->container['db'] = $this->db;
            $this->container['api'] = $this->api = APIManager::get_instance();
            $this->container['admin'] = $this->admin = Admin::get_instance();
            $this->container['features'] = &$this->features;
            
            do_action('cobra_ai_init_container', $this->container);
        } catch (\Exception $e) {
            $this->log_error('Container initialization failed', $e);
        }
    }

    /**
     * Initialize active features
     */
    public function init_features(): void
    {
        try {
            $active_features = get_option('cobra_ai_enabled_features', []);
            
            if (empty($active_features)) {
                return;
            }
            
            foreach ($active_features as $feature_id) {
                $this->load_feature($feature_id);
            }
            
            do_action('cobra_ai_features_loaded');
        } catch (\Exception $e) {
            $this->log_error('Features initialization failed', $e);
        }
    }

    /**
     * Load individual feature
     */
    private function load_feature(string $feature_id): ?FeatureBase
    {
        // Return cached feature if exists
        if (isset($this->features[$feature_id])) {
            return $this->features[$feature_id];
        }
        
        try {
            $class_info = $this->get_feature_class_info($feature_id);
            
            if (!file_exists($class_info['file'])) {
                $exception = new \Exception("Feature file not found: {$class_info['file']} (feature_id: {$feature_id})");
                $this->log_error("Feature file not found", $exception);
                return null; // Retourner null au lieu de lever une exception
            }
            
            require_once $class_info['file'];
            
            if (!class_exists($class_info['class'])) {
                throw new \Exception("Feature class not found: {$class_info['class']}");
            }
            
            $feature = new $class_info['class']();
            $this->features[$feature_id] = $feature;
            $this->container['features'][$feature_id] = $feature;
            
            if (method_exists($feature, 'init') && $feature->is_feature_active($feature_id)) {
                $feature->init();
            }
            
            return $feature;
            
        } catch (\Exception $e) {
            $this->log_error("Failed to load feature: {$feature_id}", $e);
            return null;
        }
    }
    
    /**
     * Get feature class information
     */
    private function get_feature_class_info(string $feature_id): array
    {
        $namespace = str_replace(' ', '', ucwords(str_replace('-', ' ', $feature_id)));
        $class_name = 'CobraAI\\Features\\' . $namespace . '\\Feature';
        $feature_dir = COBRA_AI_FEATURES_DIR . strtolower($namespace);
        $class_file = $feature_dir . '/Feature.php';
        
        return [
            'class' => $class_name,
            'file' => $class_file,
            'dir' => $feature_dir,
            'namespace' => $namespace
        ];
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        try {
            if (!get_option('cobra_ai_activated')) {
                $this->db ??= Database::get_instance();
                $this->db->install_or_upgrade();
                $this->set_default_options();

                update_option('cobra_ai_activated', true);
                update_option('cobra_ai_version', self::VERSION);

                do_action('cobra_ai_activated');
                flush_rewrite_rules();
            }
        } catch (\Exception $e) {
            $this->log_error('Plugin activation failed', $e);
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {

        do_action('cobra_ai_deactivate');
        flush_rewrite_rules();
    }

    /**
     * Unified error logging
     */
    private function log_error(string $message, \Throwable $error, bool $force_error_log = false): void
    {
        $error_data = [
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString()
        ];
        
        // Use database logging if available, otherwise fallback to error_log
        if (!$force_error_log && isset($this->db) && $this->db instanceof Database) {
            $this->db->log('error', $message, $error_data);
        } else {
            $formatted_message = sprintf(
                '[Cobra AI] %s - %s: %s in %s:%d',
                date('Y-m-d H:i:s'),
                $message,
                $error->getMessage(),
                basename($error->getFile()),
                $error->getLine()
            );
            error_log($formatted_message);
        }
        
        // Re-throw in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG && !$force_error_log) {
            throw $error;
        }
    }

    /**
     * Admin notices
     */
    public function php_version_notice(): void
    {
        $message = sprintf(
            __('Cobra AI Features requires PHP version %s or higher. You are running version %s.', 'cobra-ai'),
            '7.4',
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

    public function wp_version_notice(): void
    {
        $message = sprintf(
            __('Cobra AI Features requires WordPress version %s or higher. You are running version %s.', 'cobra-ai'),
            '5.8',
            $GLOBALS['wp_version']
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Set default plugin options
     */
    private function set_default_options(): void
    {
        $default_settings = [
            'core' => [
                'environment' => 'production',
                'version' => self::VERSION,
            ],
            'security' => [
                'enable_rate_limiting' => true,
                'rate_limit_requests' => 60,
                'rate_limit_period' => 3600,
            ],
            'performance' => [
                'enable_caching' => true,
                'cache_duration' => 3600,
                'minify_output' => true,
            ],
            'logging' => [
                'enable_logging' => true,
                'log_level' => 'error',
                'max_log_age' => 30,
            ],
        ];

        update_option('cobra_ai_settings', $default_settings);
        update_option('cobra_ai_enabled_features', []);
    }
    /**
     * Get a specific feature instance
     *
     * @param string $feature_id The feature identifier
     * @return FeatureBase|null The feature instance or null if not found
     */
    /**
     * Get feature instance
     */
    public function get_feature(string $feature_id): ?FeatureBase
    {
        // Return cached feature
        if (isset($this->features[$feature_id])) {
            return $this->features[$feature_id];
        }
        
        // Try to load feature on-demand
        return $this->load_feature($feature_id);
    }

    /**
     * Get all available features
     */
    public function get_features(bool $include_inactive = true): array
    {
        try {
            $feature_dirs = glob(COBRA_AI_FEATURES_DIR . '*', GLOB_ONLYDIR);
            
            if (empty($feature_dirs)) {
                return $this->features;
            }
            
            $active_features = $include_inactive ? [] : get_option('cobra_ai_enabled_features', []);
            
            foreach ($feature_dirs as $dir) {
                $feature_id = basename($dir);
                
                // Skip if already loaded
                if (isset($this->features[$feature_id])) {
                    continue;
                }
                
                // Skip inactive features if not requested
                if (!$include_inactive && !in_array($feature_id, $active_features)) {
                    continue;
                }
                
                $this->get_feature($feature_id);
            }
            
            return $this->features;
            
        } catch (\Exception $e) {
            $this->log_error('Failed to get features list', $e);
            return $this->features;
        }
    }
    /**
     * Get component from container
     */
    public function get(string $component)
    {
        return $this->container[$component] ?? null;
    }

    /**
     * Set component in container
     */
    public function set(string $component, $instance): void
    {
        $this->container[$component] = $instance;
    }

    /**
     * Check if component exists
     */
    public function has(string $component): bool
    {
        return isset($this->container[$component]);
    }
}

/** 
 * Main instance of plugin
 */
function cobra_ai(): CobraAI
{
    global $cobra_ai;
    return $cobra_ai;
}

// Initialize the plugin
global $cobra_ai;
$cobra_ai = CobraAI::instance();

 
