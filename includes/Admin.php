<?php

namespace CobraAI;

defined('ABSPATH') || exit;

/**
 * Admin functionality handler
 */
class Admin
{
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Menu settings
     */
    private $menu_slug = 'cobra-ai-dashboard';
    private $capability = 'manage_options';
    private $menu_position = 30;
    private $menu_icon = 'dashicons-randomize';

    /**
     * Active features
     */
    // private $features = [];

    /**
     * Admin notices
     */
    private $notices = [];

    /**
     * Get singleton instance
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            // log instanses
             
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
        $this->cleanup_feature_list();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void
    {
        // Menu and pages
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);

        // Assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_cobra_ai_toggle_feature', [$this, 'handle_toggle_feature']);
        add_action('wp_ajax_cobra_ai_save_settings', [$this, 'handle_save_settings']);
        add_action('wp_ajax_cobra_ai_verify_api_key', [$this, 'handle_verify_api_key']);
        add_action('wp_ajax_cobra_ai_load_feature_help', [$this, 'handle_load_feature_help']);
        add_action('wp_ajax_cobra_ai_dismiss_notice', [$this, 'handle_dismiss_notice']);

        // Notices
        add_action('admin_notices', [$this, 'display_notices']);

        // Plugin action links
        add_filter('plugin_action_links_' . plugin_basename(COBRA_AI_FILE), [$this, 'add_plugin_action_links']);

        // Feature management
        add_action('cobra_ai_feature_activated', [$this, 'handle_feature_activation']);
        add_action('cobra_ai_feature_deactivated', [$this, 'handle_feature_deactivation']);
        add_filter('sanitize_option_cobra_ai_enabled_features', [$this, 'sanitize_enabled_features']);
        add_filter('sanitize_option_cobra_ai_settings', [$this, 'sanitize_global_settings']);
        add_action('admin_post_cobra_ai_save_feature_settings', [$this, 'handle_save_feature_settings']);
    }

    /**
     * Handle saving feature settings
     */
    public function handle_save_feature_settings(): void
    {

        // Verify permissions
        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'cobra-ai'));
        }

        // Get feature ID
        $feature_id = sanitize_text_field($_POST['feature_id'] ?? '');
        if (empty($feature_id)) {
            wp_die(__('No feature specified.', 'cobra-ai'));
        }

        // Verify nonce
        check_admin_referer("cobra_ai_feature_settings_{$feature_id}");

        // Get feature
        $feature = cobra_ai()->get_feature($feature_id);
        if (!$feature) {
            wp_redirect(admin_url('admin.php?page=cobra-ai-features'));
            exit;
        }

        // Sanitize and save settings
        $settings = isset($_POST['settings']) ? (array)$_POST['settings'] : [];
        $sanitized = $feature->sanitize_settings($settings);



        if ($feature->update_settings($sanitized)) {
            $redirect_url = add_query_arg([
                'page' => 'cobra-ai-' . $feature_id . (isset($_POST['tab']) ? '&tab=' . $_POST['tab'] : ''),
                'settings-updated' => 'true'
            ], admin_url('admin.php'));
        } else {
            $redirect_url = add_query_arg([
                'page' => 'cobra-ai-' . $feature_id . (isset($_POST['tab']) ? '&tab=' . $_POST['tab'] : ''),
                'error' => 'save-failed'
            ], admin_url('admin.php'));
        }

        wp_redirect($redirect_url);
        exit;
    }
    /**
     * Add admin menu pages
     */
    public function add_menu_pages(): void
    {
        // Main menu
        add_menu_page(
            __('Cobra AI Features', 'cobra-ai'),
            __('Cobra AI', 'cobra-ai'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_dashboard_page'],
            $this->menu_icon,
            $this->menu_position
        );

        // Dashboard submenu
        add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'cobra-ai'),
            __('Dashboard', 'cobra-ai'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_dashboard_page']
        );

        // Features submenu
        add_submenu_page(
            $this->menu_slug,
            __('Features', 'cobra-ai'),
            __('Features', 'cobra-ai'),
            $this->capability,
            'cobra-ai-features',
            [$this, 'render_features_page']
        );

        // Settings submenu
        add_submenu_page(
            $this->menu_slug,
            __('Settings', 'cobra-ai'),
            __('Settings', 'cobra-ai'),
            $this->capability,
            'cobra-ai-settings',
            [$this, 'render_settings_page']
        );

        // Add feature-specific menu items
        $this->add_feature_menu_items();
    }

    /**
     * Add feature menu items
     */
    private function add_feature_menu_items(): void
    {
        $active_features = get_option('cobra_ai_enabled_features', []);
        $features = cobra_ai()->get_features();

        foreach ($features as $feature_id => $feature) {
            if (
                !in_array($feature_id, $active_features) ||
                !method_exists($feature, 'has_settings') ||
                !$feature->has_settings()
            ) {
                continue;
            }

            $info = $feature->get_info();
            add_submenu_page(
                $this->menu_slug,
                $info['name'] . ' ' . __('Settings', 'cobra-ai'),
                $info['name'],
                $this->capability,
                'cobra-ai-' . $feature_id,
                function () use ($feature) {
                    // Check if feature has render_settings method
                    if (method_exists($feature, 'render_settings')) {
                        $feature->render_settings();
                    } else {
                        // Default settings rendering
                        $this->render_default_settings($feature);
                    }
                }
            );
        }
    }

    /**
     * Render default settings page for features
     */
    private function render_default_settings($feature): void
    {
        $settings = $feature->get_settings();
        $info = $feature->get_info();
?>
        <div class="wrap">
            <h1><?php echo esc_html($info['name'] . ' ' . __('Settings', 'cobra-ai')); ?></h1>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('cobra_ai_feature_settings_' . $feature->get_feature_id()); ?>
                <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
                <input type="hidden" name="feature_id" value="<?php echo esc_attr($feature->get_feature_id()); ?>">

                <table class="form-table">
                    <?php foreach ($settings as $key => $value): ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>
                                </label>
                            </th>
                            <td>
                                <?php if (is_bool($value)): ?>
                                    <input type="checkbox"
                                        id="<?php echo esc_attr($key); ?>"
                                        name="settings[<?php echo esc_attr($key); ?>]"
                                        value="1"
                                        <?php checked($value); ?>>
                                <?php else: ?>
                                    <input type="text"
                                        id="<?php echo esc_attr($key); ?>"
                                        name="settings[<?php echo esc_attr($key); ?>]"
                                        value="<?php echo esc_attr($value); ?>"
                                        class="regular-text">
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }
    /**
     * Register plugin settings
     */
    public function register_settings(): void
    {
        // Global settings
        register_setting(
            'cobra_ai_settings',
            'cobra_ai_settings',
            // [
            //     'type' => 'array',
            //     'sanitize_callback' => [$this, 'sanitize_settings']
            // ]
        );

        // Feature settings
        register_setting(
            'cobra_ai_features',
            'cobra_ai_enabled_features',
            // [
            //     'type' => 'array',
            //     'sanitize_callback' => [$this, 'sanitize_feature_list']
            // ]
        );
    }
    /**
     * Sanitize enabled features list
     * This needs to be public as it's used as a filter callback
     */
    public function sanitize_enabled_features($features): array
    {
        if (!is_array($features)) {
            return [];
        }

        return array_map('sanitize_key', $features);
    }

    /**
     * Sanitize global settings
     * This needs to be public as it's used as a filter callback
     */
    public function sanitize_global_settings($settings): array
    {
        if (!is_array($settings)) {
            return [];
        }

        return $this->sanitize_settings_recursive($settings);
    }

    /**
     * Recursive settings sanitization
     */
    private function sanitize_settings_recursive(array $settings): array
    {
        $sanitized = [];

        foreach ($settings as $key => $value) {
            // Sanitize the key
            $key = sanitize_key($key);

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_settings_recursive($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Utility method to sanitize any input array
     * This can remain private as it's not used as a callback
     */
    private function sanitize_array($array): array
    {
        if (!is_array($array)) {
            return [];
        }

        return array_map('sanitize_text_field', $array);
    }
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets(string $hook): void
    {
        // Only load on plugin pages
        if (strpos($hook, 'cobra-ai') === false) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'cobra-ai-admin',
            COBRA_AI_ASSETS . 'css/admin.css',
            [],
            COBRA_AI_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'cobra-ai-admin',
            COBRA_AI_ASSETS . 'js/admin.js',
            ['jquery'],
            COBRA_AI_VERSION,
            true
        );

        // Localize script
        wp_localize_script('cobra-ai-admin', 'cobraAIAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cobra-ai-admin'),
            'i18n' => $this->get_js_translations()
        ]);
    }

    /**
     * Render admin pages
     */
    public function render_dashboard_page(): void
    {
        $active_features = get_option('cobra_ai_enabled_features', []);
        $settings = get_option('cobra_ai_settings', []);

        include COBRA_AI_ADMIN . 'views/dashboard.php';
    }

    public function render_features_page(): void
    {
        $available_features = cobra_ai()->get_features();
        $active_features = get_option('cobra_ai_enabled_features', []);

        include COBRA_AI_ADMIN . 'views/features.php';
    }

    public function render_settings_page(): void
    {
        $settings = get_option('cobra_ai_settings', []);

        include COBRA_AI_ADMIN . 'views/settings.php';
    }

    /**
     * Handle AJAX actions
     */
    public function handle_toggle_feature(): void
    {
        $this->verify_ajax_nonce('cobra-ai-admin');

        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Insufficient permissions', 'cobra-ai'));
        }

        $feature_id = sanitize_text_field($_POST['feature_id']);
        $enabled = filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN);

        try {
            if ($enabled) {
                $this->activate_feature($feature_id);
            } else {
                $this->deactivate_feature($feature_id);
            }

            wp_send_json_success([
                'reload' => true,
                'message' => $enabled
                    ? __('Feature enabled successfully', 'cobra-ai')
                    : __('Feature disabled successfully', 'cobra-ai')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function handle_save_settings(): void
    {


        $this->verify_ajax_nonce('cobra-ai-admin');

        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Insufficient permissions', 'cobra-ai'));
        }

        $settings = $this->sanitize_settings($_POST['settings']);

        if (update_option('cobra_ai_settings', $settings)) {
            wp_send_json_success(__('Settings saved successfully', 'cobra-ai'));
        } else {
            wp_send_json_error(__('Failed to save settings', 'cobra-ai'));
        }
    }

    public function handle_verify_api_key(): void
    {
        $this->verify_ajax_nonce('cobra-ai-admin');

        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Insufficient permissions', 'cobra-ai'));
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        try {
            // Use the API manager through the main class instance
            $result = cobra_ai()->api->verify_key($api_key);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => __('API key verified successfully', 'cobra-ai')
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['message'] ?? __('API key verification failed', 'cobra-ai')
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function handle_load_feature_help(): void
    {
        $this->verify_ajax_nonce('cobra-ai-help');

        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Insufficient permissions', 'cobra-ai'));
        }

        $feature_id = sanitize_key($_POST['feature']);
        $feature = cobra_ai()->get_feature($feature_id);

        if (!$feature) {
            wp_send_json_error(__('Feature not found', 'cobra-ai'));
        }

        $help_file = COBRA_AI_FEATURES_DIR . "{$feature_id}/assets/help.html";

        if (!file_exists($help_file)) {
            wp_send_json_error(__('Help content not found', 'cobra-ai'));
        }

        $content = file_get_contents($help_file);
        $info = $feature->get_info();

        wp_send_json_success([
            'title' => sprintf(__('%s Documentation', 'cobra-ai'), $info['name']),
            'content' => $content
        ]);
    }

    /**
     * Activate feature
     */
    private function activate_feature(string $feature_id): bool
    {
        try {
            $feature = cobra_ai()->get_feature($feature_id);

            if (!$feature) {
                throw new \Exception(__('Feature not found', 'cobra-ai'));
            }

            if (!$feature->check_dependencies()) {
                throw new \Exception(__('Feature dependencies not met', 'cobra-ai'));
            }

            $active_features = get_option('cobra_ai_enabled_features', []);

            // Convert to array if it's not already
            if (!is_array($active_features)) {
                $active_features = [];
            }

            // Remove any existing instances of this feature (cleanup duplicates)
            $active_features = array_values(array_unique(array_filter($active_features)));

            // Only add if not already active
            if (!in_array($feature_id, $active_features)) {
                $active_features[] = $feature_id;

                // Sanitize before updating
                $active_features = $this->sanitize_enabled_features($active_features);

                if (!update_option('cobra_ai_enabled_features', $active_features)) {
                    throw new \Exception(__('Failed to update active features list', 'cobra-ai'));
                }

                if (!$feature->activate()) {
                    // Rollback if activation fails
                    $active_features = array_diff($active_features, [$feature_id]);
                    update_option('cobra_ai_enabled_features', $active_features);
                    throw new \Exception(__('Feature activation failed', 'cobra-ai'));
                }

                do_action('cobra_ai_feature_activated', $feature_id);
            }

            return true;
        } catch (\Exception $e) {
            cobra_ai_db()->log('error', sprintf(
                'Failed to activate feature %s: %s',
                $feature_id,
                $e->getMessage()
            ));
            throw $e;
        }
    }

    private function deactivate_feature(string $feature_id): bool
    {
        $feature = cobra_ai()->get_feature($feature_id);

        if (!$feature) {
            throw new \Exception(__('Feature not found', 'cobra-ai'));
        }

        $active_features = get_option('cobra_ai_enabled_features', []);

        if (in_array($feature_id, $active_features)) {
            $active_features = array_diff($active_features, [$feature_id]);
            update_option('cobra_ai_enabled_features', $active_features);

            if (!$feature->deactivate()) {
                throw new \Exception(__('Feature deactivation failed', 'cobra-ai'));
            }

            do_action('cobra_ai_feature_deactivated', $feature_id);
        }

        return true;
    }
    /**
     * Cleanup feature list (utility method)
     */
    private function cleanup_feature_list(): void
    {
        $active_features = get_option('cobra_ai_enabled_features', []);

        if (!is_array($active_features)) {
            $active_features = [];
        }

        // Remove duplicates and reindex
        $active_features = array_values(array_unique(array_filter($active_features)));
        // clean feature if not exist in available features
        $available_features = $this->get_available_features_id();
        $active_features = array_intersect($active_features, $available_features);
        // Update the cleaned list
        update_option('cobra_ai_enabled_features', $active_features);
    }
    /**
     * Utility methods
     */
    private function verify_ajax_nonce(string $action): void
    {
        if (!check_ajax_referer($action, 'nonce', false)) {
            wp_send_json_error(__('Invalid security token', 'cobra-ai'));
        }
    }

    private function sanitize_settings($settings): array
    {


        if (!is_array($settings)) {
            return [];
        }


        $sanitized = [];
        foreach ($settings as $key => $value) {
            //  if null or empty, skip
            if (empty($value)) {
                continue;
            }
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_settings($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    private function sanitize_feature_list($features): array
    {
        if (!is_array($features)) {
            return [];
        }

        return array_map('sanitize_key', $features);
    }

    private function get_js_translations(): array
    {
        return [
            'confirm_deactivate' => __('Are you sure you want to deactivate this feature?', 'cobra-ai'),
            'saving' => __('Saving...', 'cobra-ai'),
            'saved' => __('Saved successfully', 'cobra-ai'),
            'error' => __('An error occurred', 'cobra-ai'),
            'verify_key' => __('Verifying API key...', 'cobra-ai'),
            'key_valid' => __('API key is valid', 'cobra-ai'),
            'key_invalid' => __('API key is invalid', 'cobra-ai')
        ];
    }

    /**
     * Plugin action links
     */
    public function add_plugin_action_links(array $links): array
    {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=' . $this->menu_slug) . '">' .
                __('Dashboard', 'cobra-ai') .
                '</a>',
            '<a href="' . admin_url('admin.php?page=cobra-ai-settings') . '">' .
                __('Settings', 'cobra-ai') .
                '</a>'
        ];

        return array_merge($plugin_links, $links);
    }

    /**
     * Notice management
     */
    public function add_notice(string $message, string $type = 'info', bool $dismissible = true): void
    {
        $this->notices[] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible
        ];
    }

    public function display_notices(): void
    {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'cobra-ai') === false) {
            return;
        }

        foreach ($this->notices as $notice) {
            $dismissible = $notice['dismissible'] ? 'is-dismissible' : '';
            printf(
                '<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
                esc_attr($notice['type']),
                esc_attr($dismissible),
                wp_kses_post($notice['message'])
            );
        }

        // Display system notices
        $this->display_system_notices();
    }

    /**
     * Display system-level notices
     */
    private function display_system_notices(): void
    {
        $settings = get_option('cobra_ai_settings', []);

        // Check core requirements
        if (!$this->check_system_requirements()) {
            $this->add_notice(
                __('System requirements not met. Please check the system status page.', 'cobra-ai'),
                'error',
                false
            );
        }

        // Check for required settings
        if (empty($settings['core']['initialized'])) {
            $this->add_notice(
                sprintf(
                    __('Please complete the initial setup on the %1$ssettings page%2$s.', 'cobra-ai'),
                    '<a href="' . admin_url('admin.php?page=cobra-ai-settings') . '">',
                    '</a>'
                ),
                'warning',
                false
            );
        }

        // Display update notices
        $this->display_update_notices();
    }

    /**
     * Display update-related notices
     */
    private function display_update_notices(): void
    {
        $current_version = get_option('cobra_ai_version');

        if (version_compare($current_version, COBRA_AI_VERSION, '<')) {
            $this->add_notice(
                sprintf(
                    __('Cobra AI has been updated to version %s. Please review the changelog for important updates.', 'cobra-ai'),
                    COBRA_AI_VERSION
                ),
                'info',
                true
            );
        }
    }

    /**
     * Handle notice dismissal
     */
    public function handle_dismiss_notice(): void
    {
        $this->verify_ajax_nonce('cobra-ai-admin');

        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Insufficient permissions', 'cobra-ai'));
        }

        $notice_id = sanitize_key($_POST['notice_id']);
        $user_id = get_current_user_id();

        update_user_meta(
            $user_id,
            'cobra_ai_dismissed_notice_' . $notice_id,
            time()
        );

        wp_send_json_success();
    }

    /**
     * Feature activation/deactivation handlers
     */
    public function handle_feature_activation(string $feature_id): void
    {
        try {
            $feature = cobra_ai()->get_feature($feature_id);
            if (!$feature) {
                throw new \Exception(__('Feature not found', 'cobra-ai'));
            }

            // Run activation tasks
            $this->run_feature_activation_tasks($feature);

            // Add success notice
            $info = $feature->get_info();
            $this->add_notice(
                sprintf(
                    __('Feature "%s" has been activated successfully.', 'cobra-ai'),
                    $info['name']
                ),
                'success'
            );

            // Log activation
            cobra_ai_db()->log('info', sprintf(
                'Feature activated: %s',
                $feature_id
            ));
        } catch (\Exception $e) {
            $this->add_notice(
                sprintf(
                    __('Failed to activate feature: %s', 'cobra-ai'),
                    $e->getMessage()
                ),
                'error'
            );
        }
    }

    public function handle_feature_deactivation(string $feature_id): void
    {
        try {
            $feature = cobra_ai()->get_feature($feature_id);
            if (!$feature) {
                throw new \Exception(__('Feature not found', 'cobra-ai'));
            }

            // Run deactivation tasks
            $this->run_feature_deactivation_tasks($feature);

            // Add success notice
            $info = $feature->get_info();
            $this->add_notice(
                sprintf(
                    __('Feature "%s" has been deactivated successfully.', 'cobra-ai'),
                    $info['name']
                ),
                'success'
            );

            // Log deactivation
            cobra_ai_db()->log('info', sprintf(
                'Feature deactivated: %s',
                $feature_id
            ));
        } catch (\Exception $e) {
            $this->add_notice(
                sprintf(
                    __('Failed to deactivate feature: %s', 'cobra-ai'),
                    $e->getMessage()
                ),
                'error'
            );
        }
    }

    /**
     * Feature activation tasks
     */
    private function run_feature_activation_tasks(FeatureBase $feature): void
    {
        $info = $feature->get_info();

        // Check for dependent features
        if (!empty($info['requires'])) {
            foreach ($info['requires'] as $required_feature) {
                if (!$this->is_feature_active($required_feature)) {
                    throw new \Exception(
                        sprintf(
                            __('Required feature "%s" is not active.', 'cobra-ai'),
                            $required_feature
                        )
                    );
                }
            }
        }

        // Install database tables
        cobra_ai_db()->install_feature_tables($feature->get_feature_id());

        // Register feature hooks
        do_action('cobra_ai_feature_after_activation_' . $feature->get_feature_id(), $feature);
    }

    /**
     * Feature deactivation tasks
     */
    private function run_feature_deactivation_tasks(FeatureBase $feature): void
    {
        // Check for dependent features
        $dependent_features = $this->get_dependent_features($feature->get_feature_id());
        if (!empty($dependent_features)) {
            throw new \Exception(
                sprintf(
                    __('Cannot deactivate: The following features depend on this feature: %s', 'cobra-ai'),
                    implode(', ', $dependent_features)
                )
            );
        }

        // Cleanup if needed
        do_action('cobra_ai_feature_before_deactivation_' . $feature->get_feature_id(), $feature);
    }

    /**
     * Get features that depend on a specific feature
     */
    private function get_dependent_features(string $feature_id): array
    {
        $dependent_features = [];
        $all_features = cobra_ai()->get_features();

        foreach ($all_features as $f_id => $feature) {
            $info = $feature->get_info();
            if (!empty($info['requires']) && in_array($feature_id, $info['requires'])) {
                $dependent_features[] = $info['name'];
            }
        }

        return $dependent_features;
    }

    /**
     * Check if a feature is active
     */
    private function is_feature_active(string $feature_id): bool
    {
        $active_features = get_option('cobra_ai_enabled_features', []);
        return in_array($feature_id, $active_features);
    }

    /**
     * Check system requirements
     */
    private function check_system_requirements(): bool
    {
        // PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            return false;
        }

        // WordPress version
        if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
            return false;
        }

        // Required PHP extensions
        $required_extensions = ['json', 'curl', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                return false;
            }
        }

        // Required WordPress functions
        $required_functions = ['wp_remote_request', 'wp_remote_get', 'wp_remote_post'];
        foreach ($required_functions as $func) {
            if (!function_exists($func)) {
                return false;
            }
        }

        return true;
    }
    public function get_setting_groups(): array
    {
        return [
            'core' => __('Core Settings', 'cobra-ai'),
            'security' => __('Security', 'cobra-ai'),
            'performance' => __('Performance', 'cobra-ai'),
            'logging' => __('Logging', 'cobra-ai'),
            'access' => __('Access Control', 'cobra-ai'),
            'integrations' => __('Integrations', 'cobra-ai'),
            'database' => __('Database', 'cobra-ai'),
            'notifications' => __('Notifications', 'cobra-ai'),
            'debug' => __('Debugging', 'cobra-ai'),
            'features' => __('Feature Management', 'cobra-ai'),
            'interface' => __('Interface', 'cobra-ai'),
            'system' => __('System', 'cobra-ai'),
        ];
    }
    /**
     * Get all available features
     */
    public function get_available_features(): array
    {
        try {
            $features = [];
            $feature_dirs = glob(COBRA_AI_FEATURES_DIR . '*', GLOB_ONLYDIR);
            // print_r($feature_dirs );
            foreach ($feature_dirs as $dir) {
                // Convert directory name to feature ID (kebab-case)
                $feature_id = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', basename($dir)));
                $file = $dir . '/Feature.php';

                if (file_exists($file)) {
                    // Get namespace from directory name (PascalCase)
                    $namespace = basename($dir); // Already in PascalCase
                    $class_name = 'CobraAI\\Features\\' . $namespace . '\\Feature';

                    if (!class_exists($class_name)) {
                        require_once $file;
                    }

                    if (class_exists($class_name)) {
                        $feature = new $class_name();
                        $features[$feature_id] = $feature->get_info();
                    }
                }
            }

            return $features;
        } catch (\Exception $e) {
            error_log('Error getting features: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get features id array for available features
     */
    public function get_available_features_id(): array
    {
        $features = $this->get_available_features();
        return array_keys($features);
    }
     
}
