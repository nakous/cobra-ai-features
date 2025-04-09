<?php

/**
 * Plugin Name: Cobra AI Features
 * Description: Modular AI-powered features for WordPress
 * Version: 1.0.0
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
    const VERSION = '1.0.0';
    public Database  $db;
    public APIManager $api;     // Make API manager accessible
    public Admin $admin;
    // public Loader $loader;
    private array  $features = [];
    /**
     * Plugin instance
     */
    private static ?CobraAI $instance = null;

    /**
     * Plugin components
     */
    private $container = [];

    /**
     * Get plugin instance
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        // cobra_ai_db()->log('info', 'Initializing plugin -0--instance');
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {

        $this->define_constants();

        if (!$this->check_requirements()) {
            return;
        }

        $this->include_files();
        $this->init_database();

        $this->init_container();
        $this->init_hooks();
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

        if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
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
            // 'Loader.php',
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
        // echo "Cobra AI Features loaded";
        // Plugin lifecycle hooks
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
            
            load_plugin_textdomain('cobra-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');
            do_action('cobra_ai_loaded');
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }

    /**
     * Initialize database separately
     */
    private function init_database(): void
    {
        try {
            $this->db = Database::get_instance();
            $this->db->install_or_upgrade();
        } catch (\Exception $e) {
            // Simple error logging to error_log since DB isn't ready
            error_log('Cobra AI Database Init Error: ' . $e->getMessage());
        }
    }

    /**
     * Initialize dependency container
     */
    private function init_container(): void
    {
        try {
            // Initialize core components
            $this->container['db'] = $this->db;

            $this->container['api'] = $this->api = APIManager::get_instance();
            // $this->container['loader'] = $this->loader = Loader::get_instance();

            // if (is_admin()) {
                $this->container['admin'] = $this->admin = Admin::get_instance();
            // }

            $this->container['features'] = $this->features;

            do_action('cobra_ai_init_container', $this->container);
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }

    /**
     * Initialize features
     */
    public function init_features(): void
    {

        try {
            $active_features = get_option('cobra_ai_enabled_features', []);
            $feature_dirs = glob(COBRA_AI_FEATURES_DIR . '*', GLOB_ONLYDIR);

            foreach ($feature_dirs as $dir) {
                $feature_id = basename($dir);
                if (in_array($feature_id, $active_features)) {

                    $this->load_feature($feature_id);
                }
            }

            do_action('cobra_ai_features_loaded');
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }

    /**
     * Load individual feature
     */
    private function load_feature(string $feature_id): void
    {
        try {
            // Convert feature-id to PascalCase for namespace
            $namespace = str_replace(' ', '', ucwords(str_replace('-', ' ', $feature_id))); // hello-world -> HelloWorld
            $class_name = 'CobraAI\\Features\\' . $namespace . '\\Feature';
            $namespace = strtolower($namespace); // Use kebab-case for directory
            $feature_dir = COBRA_AI_FEATURES_DIR . $namespace; // Use PascalCase for directory
            $class_file = $feature_dir . '/Feature.php';

            if (!is_dir($feature_dir) || !file_exists($class_file)) {
                throw new \Exception("Feature not found: {$class_file}");
            }

            // Include the file
            require_once $class_file;

            if (class_exists($class_name)) {
                $feature = new $class_name();
                $this->container['features'][$feature_id] = $feature;

                if (method_exists($feature, 'init') && $feature->is_feature_active($feature_id)) {
                
                    $feature->init();
                }
            } else {
                throw new \Exception("Feature class not found: {$class_name}");
            }
        } catch (\Exception $e) {
            error_log('Failed to load feature: ' . $e->getMessage());
        }
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
            $this->handle_error($e);
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
     * Error handling
     */
    private function handle_error(\Throwable $error): void
    {
        if (isset($this->container['db'])) {
            $this->container['db']->log('error', $error->getMessage(), [
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString()
            ]);
        }

        if (WP_DEBUG) {
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
    public function get_feature(string $feature_id)
    {
        // Check if feature exists in container
        if (isset($this->container['features'][$feature_id])) {
            return $this->container['features'][$feature_id];
        }

        // Convert kebab-case to PascalCase
        $namespace = str_replace(' ', '', ucwords(str_replace('-', ' ', $feature_id)));
        $class_name = 'CobraAI\\Features\\' . $namespace . '\\Feature';
        $namespace = strtolower($namespace);
        $feature_dir = COBRA_AI_FEATURES_DIR . $namespace;
        $class_file = $feature_dir . '/Feature.php';

        if (file_exists($class_file)) {
            require_once $class_file;

            if (class_exists($class_name)) {
                $this->container['features'][$feature_id] = new $class_name();
                return $this->container['features'][$feature_id];
            }
        }else{
            // error_log("Feature not found: {$class_file}");
        }

        return null;
    }

    /**
     * Get all available features
     *
     * @param bool $include_inactive Whether to include inactive features
     * @return array<string, FeatureBase> Array of feature instances
     */
    public function get_features(bool $include_inactive = true): array
    {
        try {
            // Get feature directories
            $feature_dirs = glob(COBRA_AI_FEATURES_DIR . '*', GLOB_ONLYDIR);
            // print_r($feature_dirs);
            $active_features = get_option('cobra_ai_enabled_features', []);
            // error_log(print_r($feature_dirs, true));
            foreach ($feature_dirs as $dir) {
                $feature_id = basename($dir);
                // print_r($feature_id) ; echo  "<br>";
                // Skip if feature is already loaded
                if (isset($this->container['features'][$feature_id])) {
                    continue;
                }
                // print_r($feature_id) ; echo  "-----2<br>";
                // Skip inactive features if not requested
                if (!$include_inactive && !in_array($feature_id, $active_features)) {
                    continue;
                }
                // print_r($feature_id) ; echo  "********3<br>";
                // Try to load the feature
                $feature = $this->get_feature($feature_id);
                // var_dump($feature);
                if ($feature) {
                    $this->container['features'][$feature_id] = $feature;
                }
            }
//  print_r($this->container['features']);
            return $this->container['features'];
        } catch (\Exception $e) {
            $this->db->log('error', 'Failed to get features', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
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

// // Initialize the plugin
global $cobra_ai;
$cobra_ai = CobraAI::instance();
// echo "Cobra AI Features loaded";
// add_action('rest_api_init', function () {
//     $endpoints = rest_get_server()->get_routes();
//     foreach ($endpoints as $route => $handlers) {
//         echo "<h3>Route: " . esc_html($route) . "</h3>";
//         foreach ($handlers as $handler) {
//             if (isset($handler['permission_callback'])) {
//                 echo "<p><strong>Permissions:</strong> " . (is_callable($handler['permission_callback']) ? 'Has Callback' : 'None') . "</p>";
//             }
//         }
//     }
// });
// add_action('wp', function() {
//     if (is_404()) {
//         global $wp_query, $wp_rewrite;
//         error_log('404 Debug Info:');
//         error_log('Query: ' . print_r($wp_query->query, true));
//         error_log('Request: ' . print_r($wp_query->request, true));
//         error_log('Rewrite Rules: ' . print_r($wp_rewrite->rules, true));
//     }
// });

 
