<?php
// TODO : delete this file
namespace CobraAI;

defined('ABSPATH') || exit;

/**
 * Resource Loader and Autoloader Class 
 */
class Loader
{
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Loaded components registry
     */
    private $loaded_components = [];

    /**
     * Required files registry
     */
    private $required_files = [];

    /**
     * Loading errors
     */
    private $errors = [];


    /**
     * Core files to load
     */
    private $core_files = [
        'FeatureBase.php' => COBRA_AI_INCLUDES,
        'Database.php' => COBRA_AI_INCLUDES,
        'APIManager.php' => COBRA_AI_INCLUDES,
        'Admin.php' => COBRA_AI_INCLUDES,
        'Loader.php' => COBRA_AI_INCLUDES,
        'utilities/functions.php' => COBRA_AI_INCLUDES,
        'Utilities/Validator.php' => COBRA_AI_INCLUDES
    ];

    /**
     * Required PHP extensions
     */
    private $required_extensions = [
        'json',
        'curl',
        'mbstring',
        'mysqli'
    ];

    /**
     * Required WordPress functions
     */
    private $required_functions = [
        'wp_remote_request',
        'wp_remote_get',
        'wp_remote_post',
        'wp_parse_args',
        'wp_json_encode',
        'add_action',
        'add_filter',
        'apply_filters',
        'do_action'
    ];

    /**
     * Get singleton instance
     */
    public static function get_instance(): self
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
        $this->verify_environment();
        $this->register_autoloader();
    }

    /**
     * Verify environment requirements
     */
    private function verify_environment(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $this->add_error('php_version', sprintf(
                'PHP version 7.4 or higher is required. Current version is %s',
                PHP_VERSION
            ));
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.8', '<')) {
            $this->add_error('wp_version', sprintf(
                'WordPress version 5.8 or higher is required. Current version is %s',
                $wp_version
            ));
        }

        // Check PHP extensions
        foreach ($this->required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $this->add_error('extension_' . $extension, sprintf(
                    'Required PHP extension missing: %s',
                    $extension
                ));
            }
        }

        // Check WordPress functions
        foreach ($this->required_functions as $function) {
            if (!function_exists($function)) {
                $this->add_error('function_' . $function, sprintf(
                    'Required WordPress function missing: %s',
                    $function
                ));
            }
        }

        // Check if any errors occurred
        if (!empty($this->errors)) {
            $this->handle_environment_errors();
        }
    }

    /**
     * Register autoloader
     */
    private function register_autoloader(): void
    {
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * Autoload callback
     */
    public function autoload(string $class): void
    {
        // Only handle our namespace
        if (strpos($class, 'CobraAI\\') !== 0) {
            return;
        }

        try {
            $path = '';

            // Handle Features namespace separately
            if (strpos($class, 'CobraAI\\Features\\') === 0) {
                // Remove the base namespace
                $relative_class = substr($class, strlen('CobraAI\\Features\\'));

                // Split by namespace separator to get feature name and class
                $parts = explode('\\', $relative_class);

                // Convert HelloWorld to hello-world
                $feature_dir = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $parts[0]));

                // Build the path to the feature class
                $path = COBRA_AI_FEATURES_DIR . $feature_dir . '/Feature.php';
            } else {
                // Handle core classes
                $relative_class = substr($class, strlen('CobraAI\\'));
                $path = COBRA_AI_INCLUDES . str_replace('\\', '/', $relative_class) . '.php';
            }

            // Double check file exists
            if (!$this->verify_file($path)) {
                throw new \Exception(sprintf(
                    'Class file not found: %s',
                    $path
                ));
            }

            require_once $path;

            // Verify class was loaded
            if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
                throw new \Exception(sprintf(
                    'Class/Interface/Trait not found in file: %s',
                    $class
                ));
            }

            $this->loaded_components[$class] = $path;
        } catch (\Exception $e) {
            $this->add_error('autoload_' . $class, $e->getMessage());
        }
    }

    /**
     * Convert class name to file path
     */
    private function class_to_path(string $class): string
    {
        // If it's a feature class
        if (strpos($class, 'CobraAI\\Features\\') === 0) {
            $relative_class = substr($class, strlen('CobraAI\\Features\\'));

            // Split namespace and class name
            $parts = explode('\\', $relative_class);

            if (count($parts) === 2 && $parts[1] === 'Feature') {
                // Convert namespace to feature directory name
                $feature_dir = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $parts[0]));
                return COBRA_AI_FEATURES_DIR . $feature_dir . '/Feature.php';
            }
        }

        // For other classes, use standard PSR-4 autoloading
        $relative_class = substr($class, strlen('CobraAI\\'));
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);
        return COBRA_AI_PATH . 'includes/' . $file . '.php';
    }

    /**
     * Load core files
     */
    public function load_core_files(): void
    {
        foreach ($this->core_files as $file => $dir) {
            $path = $dir . $file;

            try {
                // Verify file
                if (!$this->verify_file($path)) {
                    throw new \Exception(sprintf(
                        'Core file not found: %s',
                        $path
                    ));
                }

                // Load file
                require_once $path;

                // Track loaded file
                $this->required_files[$file] = $path;
            } catch (\Exception $e) {
                $this->add_error('core_file_' . $file, $e->getMessage());
                $this->handle_core_file_error($file, $e);
            }
        }
    }

    /**
     * Verify file exists and is readable
     */
    private function verify_file(string $path): bool
    {
        // Double check using both file_exists and is_readable
        return file_exists($path) && is_readable($path) &&
            @is_file($path) && @filesize($path) > 0;
    }

    /**
     * Add error
     */
    private function add_error(string $code, string $message): void
    {
        $this->errors[$code] = $message;

        // Log error
        if (function_exists('cobra_ai_db')) {
            cobra_ai_db()->log('error', $message, [
                'code' => $code,
                'component' => 'loader'
            ]);
        }
    }

    /**
     * Handle environment errors
     */
    private function handle_environment_errors(): void
    {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . esc_html__('Cobra AI cannot be loaded due to system requirements:', 'cobra-ai') . '</strong></p>';
            echo '<ul>';
            foreach ($this->errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        });

        // throw new \Exception('Environment verification failed');
    }

    /**
     * Handle autoload error
     */
    private function handle_autoload_error(string $class, \Exception $error): void
    {
        add_action('admin_notices', function () use ($class, $error) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(
                    __('Failed to load Cobra AI component: %s. Error: %s', 'cobra-ai'),
                    $class,
                    $error->getMessage()
                ))
            );
        });
    }

    /**
     * Handle core file error
     */
    private function handle_core_file_error(string $file, \Exception $error): void
    {
        add_action('admin_notices', function () use ($file, $error) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(
                    __('Failed to load Cobra AI core file: %s. Error: %s', 'cobra-ai'),
                    $file,
                    $error->getMessage()
                ))
            );
        });
    }

    /**
     * Get loaded components
     */
    public function get_loaded_components(): array
    {
        return $this->loaded_components;
    }

    /**
     * Get required files
     */
    public function get_required_files(): array
    {
        return $this->required_files;
    }

    /**
     * Get loading errors
     */
    public function get_errors(): array
    {
        return $this->errors;
    }

    /**
     * Check if component is loaded
     */
    public function is_component_loaded(string $class): bool
    {
        return isset($this->loaded_components[$class]);
    }

    /**
     * Check if file is required
     */
    public function is_file_required(string $file): bool
    {
        return isset($this->required_files[$file]);
    }

    /**
     * Reset loader state
     * Useful for testing and debugging
     */
    public function reset(): void
    {
        $this->loaded_components = [];
        $this->required_files = [];
        $this->errors = [];
    }

    /**
     * Verify all components are loaded
     */
    public function verify_loading(): bool
    {
        $missing_components = [];

        // Check core files
        foreach ($this->core_files as $file => $dir) {
            if (!$this->is_file_required($file)) {
                $missing_components[] = $file;
            }
        }

        // Check for errors
        if (!empty($missing_components)) {
            $this->add_error('missing_components', sprintf(
                'Missing required components: %s',
                implode(', ', $missing_components)
            ));
            return false;
        }

        return true;
    }
}

// Initialize loader
function cobra_ai_loader(): Loader
{
    return Loader::get_instance();
}
