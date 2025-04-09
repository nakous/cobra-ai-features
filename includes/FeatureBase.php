<?php

namespace CobraAI;

defined('ABSPATH') || exit;

/**
 * Base class for all features
 */
abstract class FeatureBase
{
    /**
     * Feature properties
     */
    protected $feature_id = '';
    protected $name = '';
    protected $description = '';
    protected $version = '1.0.0';
    protected $author = '';
    protected $requires = [];
    protected $min_wp_version = '5.8';
    protected $min_php_version = '7.4';
    protected $has_settings = false;
    protected $has_admin = false;

    /**
     * Feature paths
     */
    protected $path;
    protected $url;
    protected $assets_url;
    protected $templates_path;

    /**
     * Database tables
     */
    protected $tables = [];
    /**
     * Get table
     */
    public function get_table(string $table_name): ?array
    {
        return $this->tables[$table_name] ?? null;
    }

    /**
     * Get table name
     */
    public function get_table_name(string $table_name): ?string
    {
        return $this->tables[$table_name]['name'] ?? null;
    }
    /**
     * Get all tables
     */
    public function get_tables(): array
    {
        return $this->tables;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        // Set feature ID if not explicitly defined
        if (empty($this->feature_id)) {
            $this->feature_id = $this->generate_feature_id();
        }

        // Set feature paths
        $this->setup_paths();

        // Initialize feature
        // setup if this feature is active 

        if ($this->is_feature_active($this->feature_id)) {
            $this->setup();
        }
    }

    /**
     * Generate feature ID from class name
     */
    protected function generate_feature_id(): string
    {
        $class_name = (new \ReflectionClass($this))->getShortName();
        return strtolower(
            str_replace(
                ['CobraAI_Feature_', '_'],
                ['', '-'],
                $class_name
            )
        );
    }

    /**
     * Setup feature paths
     */
    protected function setup_paths(): void
    {
        $this->path = COBRA_AI_FEATURES_DIR . $this->feature_id . '/';
        $this->url = COBRA_AI_URL . 'features/' . $this->feature_id . '/';
        $this->assets_url = $this->url . 'assets/';
        $this->templates_path = $this->path . 'templates/';
    }

    /**
     * Setup feature - must be implemented by child classes
     */
    abstract protected function setup(): void;

    /**
     * Initialize feature
     */
    public function init(): bool
    {

        try {
            // Check dependencies
            if (!$this->check_dependencies()) {
                return false;
            }

            // Check system requirements
            if (!$this->check_requirements()) {
                return false;
            }

            // Initialize components
            $this->init_hooks();
            $this->register_shortcodes();

            if ($this->has_admin) {
                $this->init_admin();
            }

            // if ($this->has_settings) {
            //     $this->register_settings();
            // }

            // Log initialization

            return true;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', "Feature initialization failed: {$this->feature_id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Initialize hooks
     */
    protected function init_hooks(): void
    {

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX handlers
        add_action('wp_ajax_cobra_ai_' . $this->feature_id, [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_cobra_ai_' . $this->feature_id, [$this, 'handle_public_ajax']);
    }

    /**
     * Initialize admin functionality
     */
    protected function init_admin(): void
    {
        if (!is_admin()) {
            return;
        }

        // Add menu items
        // add_action('admin_menu', [$this, 'add_menu_items']);

        // Register admin hooks
        // do_action('cobra_ai_feature_admin_init_' . $this->feature_id, $this);
    }

    /**
     * Register settings
     */
    // protected function register_settings(): void
    // {
    //     if ($this->has_settings) {
    //         register_setting(
    //             'cobra_ai_' . $this->feature_id . '_settings',
    //             'cobra_ai_' . $this->feature_id . '_options',
    //             [
    //                 'type' => 'array',
    //                 'sanitize_callback' => [$this, 'sanitize_settings'],
    //                 'default' => $this->get_feature_default_options()
    //             ]
    //         );
    //     }
    // }
    // validate_settings abstract method
    protected function validate_settings(array $settings): array
    {
        // Add validation logic here
        return $settings;
    }

    /**
     * Sanitize settings before saving
     * 
     * @param array $settings Settings array to sanitize
     * @return array Sanitized settings
     */
    public function sanitize_settings(array $settings): array
    {
        try {
            // Allow features to validate settings through their validate_settings method
            if (method_exists($this, 'validate_settings')) {
                $settings = $this->validate_settings($settings);
            }

            // Basic sanitization for common setting types
            foreach ($settings as $key => $value) {
                if (empty($value)) {
                    continue;
                }

                if (is_string($value)) {
                    if ($this->is_html_allowed_field($key)) {
                        // Allow HTML for specific fields
                        $settings[$key] = wp_unslash($value);
                    } elseif (is_email($value)) {
                        // Sanitize email fields
                        $settings[$key] = sanitize_email($value);
                    } else {
                        // Sanitize general text fields
                        $settings[$key] = sanitize_text_field($value);
                    }
                } elseif (is_numeric($value)) {
                    // Convert numeric strings to proper type
                    $settings[$key] = strpos($value, '.') !== false ? (float)$value : (int)$value;
                } elseif (is_array($value)) {
                    // Recursively sanitize nested arrays
                    $settings[$key] = $this->sanitize_settings($value);
                }
            }

            // Allow features to perform additional sanitization
            $settings = apply_filters(
                'cobra_ai_feature_sanitize_settings_' . $this->get_feature_id(),
                $settings
            );

            return $settings;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', sprintf(
                'Failed to sanitize settings for feature %s: %s',
                $this->get_feature_id(),
                $e->getMessage()
            ));

            // Return original settings if sanitization fails
            return $settings;
        }
    }
    /**
     * Register shortcodes - override in child class if needed
     */
    protected function register_shortcodes(): void {}

    /**
     * Check dependencies
     */
    public function check_dependencies(): bool
    {
        if (empty($this->requires)) {
            return true;
        }

        $active_features = get_option('cobra_ai_enabled_features', []);

        foreach ($this->requires as $required_feature) {
            if (!in_array($required_feature, $active_features)) {
                $this->add_admin_notice(
                    sprintf(
                        __('The %1$s feature requires the %2$s feature to be active.', 'cobra-ai'),
                        $this->name,
                        $required_feature
                    ),
                    'error'
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Check system requirements
     */
    protected function check_requirements(): bool
    {
        if (version_compare(PHP_VERSION, $this->min_php_version, '<')) {
            $this->add_admin_notice(
                sprintf(
                    __('The %1$s feature requires PHP version %2$s or higher.', 'cobra-ai'),
                    $this->name,
                    $this->min_php_version
                ),
                'error'
            );
            return false;
        }

        if (version_compare($GLOBALS['wp_version'], $this->min_wp_version, '<')) {
            $this->add_admin_notice(
                sprintf(
                    __('The %1$s feature requires WordPress version %2$s or higher.', 'cobra-ai'),
                    $this->name,
                    $this->min_wp_version
                ),
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Feature activation
     */
    public function activate(): bool
    {
        try {
            // Install database tables
            $this->setup();
            if (!empty($this->tables)) {
                cobra_ai_db()->register_feature_tables($this->feature_id, $this->tables);
                cobra_ai_db()->install_feature_tables($this->feature_id);
            }

            // Set default options
            $this->set_default_options();

            do_action('cobra_ai_feature_activated_' . $this->feature_id, $this);

            cobra_ai_db()->log('info', "Feature activated: {$this->feature_id}");
            return true;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', "Feature activation failed: {$this->feature_id}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    /**
     * Set default feature options
     * 
     * @return bool True if options were set, false otherwise
     */
    protected function set_default_options(): bool
    {
        try {
            // Get existing options
            $existing_options = get_option('cobra_ai_' . $this->feature_id . '_options', []);

            // If options already exist, don't override
            if (!empty($existing_options)) {
                return true;
            }

            // Default feature settings structure
            $default_options = $this->get_feature_default_options();

            // Allow other plugins/themes to modify defaults
            $default_options = apply_filters(
                'cobra_ai_feature_default_options_' . $this->feature_id,
                $default_options
            );

            // Update options
            $success = update_option(
                'cobra_ai_' . $this->feature_id . '_options',
                $default_options,
                'no' // Don't autoload as these can be large
            );

            if ($success) {
                // Log successful initialization
                cobra_ai_db()->log('info', sprintf(
                    'Default options set for feature: %s',
                    $this->feature_id
                ));

                // Trigger action for other plugins
                do_action('cobra_ai_feature_options_initialized_' . $this->feature_id, $default_options);
            }

            return $success;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', sprintf(
                'Failed to set default options for feature: %s. Error: %s',
                $this->feature_id,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Get feature-specific default options
     * Override this method in your feature class to provide custom defaults
     * 
     * @return array Feature-specific default options
     */
    protected function get_feature_default_options(): array
    {
        return [];
    }
    /**
     * Feature deactivation
     */
    public function deactivate(): bool
    {
        try {
            do_action('cobra_ai_feature_deactivated_' . $this->feature_id, $this);
            cobra_ai_db()->log('info', "Feature deactivated: {$this->feature_id}");
            return true;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', "Feature deactivation failed: {$this->feature_id}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Feature uninstall
     */
    public function uninstall(): bool
    {
        try {
            // Remove database tables
            if (!empty($this->tables)) {
                cobra_ai_db()->uninstall_feature_tables($this->feature_id);
            }

            // Remove options
            delete_option('cobra_ai_' . $this->feature_id . '_options');

            do_action('cobra_ai_feature_uninstalled_' . $this->feature_id, $this);

            cobra_ai_db()->log('info', "Feature uninstalled: {$this->feature_id}");
            return true;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', "Feature uninstallation failed: {$this->feature_id}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_assets(): void
    {

        if (file_exists($this->path . 'assets/css/public.css')) {
            wp_enqueue_style(
                'cobra-ai-' . $this->feature_id,
                $this->assets_url . 'css/public.css',
                [],
                $this->version
            );
        }

        // JavaScript
        if (file_exists($this->path . 'assets/js/public.js')) {
            wp_enqueue_script(
                'cobra-ai-' . $this->feature_id,
                $this->assets_url . 'js/public.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script('cobra-ai-' . $this->feature_id, 'cobraAI' . ucfirst($this->feature_id), [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cobra-ai-' . $this->feature_id),
                'i18n' => $this->get_js_translations()
            ]);
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook): void
    {
        if (strpos($hook, 'cobra-ai-' . $this->feature_id) === false) {
            return;
        }

        // CSS
        if (file_exists($this->path . 'assets/css/admin.css')) {
            wp_enqueue_style(
                'cobra-ai-' . $this->feature_id . '-admin',
                $this->assets_url . 'css/admin.css',
                [],
                $this->version
            );
        }

        // JavaScript
        if (file_exists($this->path . 'assets/js/admin.js')) {
            wp_enqueue_script(
                'cobra-ai-' . $this->feature_id . '-admin',
                $this->assets_url . 'js/admin.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script('cobra-ai-' . $this->feature_id . '-admin', 'cobraAIAdmin' . ucfirst($this->feature_id), [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cobra-ai-admin-' . $this->feature_id),
                'i18n' => $this->get_js_translations()
            ]);
        }
    }

    /**
     * Get feature information
     */
    public function get_info(): array
    {
        return [
            'id' => $this->feature_id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'requires' => $this->requires,
            'has_settings' => $this->has_settings,
            'has_admin' => $this->has_admin,
        ];
    }

    /**
     * Get feature settings
     *
     * @param string|null $key Specific setting key to get
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed
     */
    public  function get_settings(?string $key = null, $default = null)
    {
        $settings = get_option('cobra_ai_' . $this->get_feature_id() . '_options', []);

        if ($key === null) {
            return wp_parse_args($settings, $this->get_feature_default_options());
        }

        // if key has '.' in it, explode it and get the all values
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $settings;
            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return $default;
                }
            }
            return $value;
        }
        return $settings[$key] ?? $this->get_feature_default_options()[$key] ?? $default;
    }

    /**
     * Update feature settings
     */
    public function update_settings(array $settings): bool
    {
        try {

            // Merge with defaults
            $settings = wp_parse_args($settings, $this->get_feature_default_options());
            // print_r($settings);
            // Allow features to validate settings
            if (method_exists($this, 'validate_settings')) {
                $settings = $this->validate_settings($settings);
            }

            // Update the settings
            $updated = update_option(
                'cobra_ai_' . $this->get_feature_id() . '_options',
                $settings
            );

            if ($updated) {

                do_action('cobra_ai_feature_settings_updated_' . $this->get_feature_id(), $settings);
            }

            return $updated;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', sprintf(
                'Failed to update settings for feature %s: %s',
                $this->get_feature_id(),
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Get translations for JavaScript
     */
    protected function get_js_translations(): array
    {
        return [
            'error' => __('An error occurred', 'cobra-ai'),
            'success' => __('Operation completed successfully', 'cobra-ai'),
            'confirm' => __('Are you sure?', 'cobra-ai'),
            'cancel' => __('Cancel', 'cobra-ai'),
            'ok' => __('OK', 'cobra-ai'),
        ];
    }

    /**
     * Add admin notice
     */
    protected function add_admin_notice(string $message, string $type = 'info'): void
    {
        add_action('admin_notices', function () use ($message, $type) {
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }

    /**
     * Load template
     */
    protected function load_template(string $template, array $args = []): void
    {
        $template_file = $this->templates_path . $template . '.php';

        if (!file_exists($template_file)) {
            return;
        }

        extract($args);
        include $template_file;
    }

    /**
     * Handle AJAX requests - override in child class
     */
    public function handle_ajax(): void
    {
        wp_send_json_error('Not implemented');
    }

    /**
     * Handle public AJAX requests - override in child class
     */
    public function handle_public_ajax(): void
    {
        wp_send_json_error('Not implemented');
    }

    /**
     * Check if feature has settings
     */
    public function has_settings(): bool
    {
        return $this->has_settings;
    }

    public function get_feature_id(): string
    {
        if (empty($this->feature_id)) {
            // Generate feature ID from class name if not set
            $class_name = (new \ReflectionClass($this))->getShortName();
            $this->feature_id = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name));
        }
        return $this->feature_id;
    }

    /**
     * Get feature health status
     *
     * @return array Status information
     */
    public function get_health_status(): array
    {
        try {
            $status = [
                'status' => 'healthy', // Default status
                'message' => '',
                'warnings' => [],
                'checks' => [
                    'dependencies' => true,
                    'system' => true,
                    'database' => true,
                    'files' => true,
                ]
            ];

            // Check dependencies - returns bool in current implementation
            if (!$this->check_dependencies()) {
                $status['checks']['dependencies'] = false;
                $status['warnings'][] = __('One or more required features are not active', 'cobra-ai');

                // Add specific dependencies information
                if (!empty($this->requires)) {
                    $missing = [];
                    foreach ($this->requires as $required_feature) {
                        if (!$this->is_feature_active($required_feature)) {
                            $missing[] = $required_feature;
                        }
                    }
                    if (!empty($missing)) {
                        $status['warnings'][] = sprintf(
                            __('Missing dependencies: %s', 'cobra-ai'),
                            implode(', ', $missing)
                        );
                    }
                }
            }

            // Check system requirements
            if (!$this->check_requirements()) {
                $status['checks']['system'] = false;
                $status['warnings'][] = __('System requirements not met', 'cobra-ai');
            }

            // Check database tables if feature has them
            if (!empty($this->tables)) {
                $missing_tables = $this->check_tables();
                if (!empty($missing_tables)) {
                    $status['checks']['database'] = false;
                    $status['warnings'][] = sprintf(
                        __('Missing database tables: %s', 'cobra-ai'),
                        implode(', ', $missing_tables)
                    );
                }
            }

            // Check required files
            $missing_files = $this->check_files();
            if (!empty($missing_files)) {
                $status['checks']['files'] = false;
                $status['warnings'][] = sprintf(
                    __('Missing required files: %s', 'cobra-ai'),
                    implode(', ', $missing_files)
                );
            }

            // Set overall status
            if (!empty($status['warnings'])) {
                $status['status'] = 'warning';
                $status['message'] = __('Feature has warnings that need attention', 'cobra-ai');
            } else {
                $status['message'] = __('Feature is working properly', 'cobra-ai');
            }

            // Allow features to add their own health checks
            $custom_checks = $this->get_custom_health_checks();
            if (!empty($custom_checks)) {
                $status = array_merge($status, $custom_checks);
            }

            return $status;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'warnings' => [$e->getMessage()],
                'checks' => [
                    'dependencies' => false,
                    'system' => false,
                    'database' => false,
                    'files' => false,
                ]
            ];
        }
    }

    /**
     * Check if a feature is active
     */
    public function is_feature_active(string $feature_id): bool
    {
        $active_features = get_option('cobra_ai_enabled_features', []);
        return  in_array($feature_id, $active_features);
    }

    /**
     * Check if required database tables exist
     */
    protected function check_tables(): array
    {
        global $wpdb;
        $missing_tables = [];

        foreach ($this->tables as $table => $info) {
            $table_name = $info['name'];
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                $missing_tables[] = $table;
            }
        }

        return $missing_tables;
    }

    /**
     * Check if required files exist
     */
    protected function check_files(): array
    {
        $missing_files = [];
        $required_files = $this->get_required_files();

        foreach ($required_files as $file) {
            if (!file_exists($this->path . $file)) {
                $missing_files[] = $file;
            }
        }

        return $missing_files;
    }

    /**
     * Get list of required files
     * Override in child class if needed
     */
    protected function get_required_files(): array
    {
        return [
            'assets/css/public.css',
            'assets/js/public.js'
        ];
    }

    /**
     * Get custom health checks
     * Override in child class to add feature-specific checks
     */
    protected function get_custom_health_checks(): array
    {
        return [];
    }

    /**
     * display settings error validation message
     */
    public function display_settings_errors()
    {
        // Get any validation errors
        $validation_errors = get_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors', []);

        if (!empty($validation_errors)) {
?>
            <div class="notice notice-error is-dismissible">
                <p><strong><?php echo esc_html__('Please correct the following:', 'cobra-ai'); ?></strong></p>
                <ul>
                    <?php foreach ($validation_errors as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
<?php
        }
    }

    public function render_settings(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();


        include $this->path . 'views/settings.php';
    }

    /*
    * get path
    */
    public function get_path(): string
    {
        return $this->path;
    }

    /**
     * Get URL
     */
    public function get_url(): string
    {
        return $this->url;
    }
    // public log 
    public function log(string $level, string $message, array $context = []): void
    {
        cobra_ai_db()->log($level, $message, $context);
    }


    /**
     * is html allowed field
     */
    protected function is_html_allowed_field(string $field): bool
    {
        return false;
    }
}
