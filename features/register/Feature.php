<?php

namespace CobraAI\Features\Register;

use CobraAI\FeatureBase;

class Feature extends FeatureBase
{
    protected $feature_id = 'register';
    protected $name = 'User Registration';
    protected $description = 'Enhanced user registration with email verification and admin approval';
    protected $version = '1.0.0';
    protected $author = 'Your Name';
    protected $has_settings = true;
    protected $has_admin = true;

    /**
     * User registration handler
     */
    public $registration;

    /**
     * Email handler
     */

    public $email;

    /**
     * Shortcode handler
     */
    public $shortcode;

    /**
     * Setup feature
     */
    protected function setup(): void
    {
        global $wpdb;

        // Define feature tables
        $this->tables = [
            'verification_tokens' => [
                'name' => $wpdb->prefix . 'cobra_verification_tokens',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'user_id' => 'bigint(20) NOT NULL',
                    'token' => 'varchar(255) NOT NULL',
                    'type' => "enum('email_verify','password_reset') NOT NULL",
                    'expires_at' => 'datetime NOT NULL',
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'PRIMARY KEY' => '(id)',
                    'UNIQUE KEY' => 'token (token)',
                    'KEY' => [
                        'user_id' => '(user_id)',
                        'type' => '(type)',
                        'expires_at' => '(expires_at)'
                    ]
                ]
            ]
        ];

        // Load required files
        require_once __DIR__ . '/includes/UserRegistrationHandler.php';
        require_once __DIR__ . '/includes/EmailHandler.php';
        require_once __DIR__ . '/includes/ShortcodeHandler.php';

        // Initialize components
        $this->init_components();
        // $this->register_shortcodes();

        // Add hooks
        // add_action('init', [$this, 'init_hooks']);
        // add_action('admin_init', [$this, 'init_admin']);
        // add_filter('manage_users_columns', [$this, 'add_user_columns']);
        // add_filter('manage_users_custom_column', [$this, 'manage_user_columns'], 10, 3);
        // add_action('show_user_profile', [$this, 'add_custom_user_fields']);
        // add_action('edit_user_profile', [$this, 'add_custom_user_fields']);
        // add_action('personal_options_update', [$this, 'save_custom_user_fields']);
        // add_action('edit_user_profile_update', [$this, 'save_custom_user_fields']);
        // add_action('wp_ajax_cobra_create_page', [$this, 'handle_create_page']);
        // // resend_verification
        // add_action('wp_ajax_cobra_resend_verification', [$this->registration, 'handle_resend_verification']);

        // $this->shortcode->short_add_action();
    }

    /**
     * Get default options
     */
    protected function get_feature_default_options(): array
    {
        return [
            'general' => [
                'disable_admin_menu' => true,
                'use_recaptcha' => true,
                'default_role' => 'pending',
            ],
            'pages' => [
                'login' => '',
                'register' => '',
                'forgot_password' => '',
                'reset_password' => '',
                'account' => '',
                'logout' => '',
                'policy' => '',
            ],
            'redirects' => [
                'after_login' => 'account',
                'after_logout' => 'login',
            ],
            'emails' => [
                'global_template' => $this->get_default_email_template(),
                'verification' => $this->get_default_verification_email(),
                'confirmation' => $this->get_default_confirmation_email(),
            ],
            'fields' => [
                'username' => ['enabled' => true, 'required' => true],
                'email' => ['enabled' => true, 'required' => true],
                'password' => ['enabled' => true, 'required' => true],
                'confirm_password' => ['enabled' => true, 'required' => true],
                'first_name' => ['enabled' => true, 'required' => false],
                'last_name' => ['enabled' => true, 'required' => false],
                'phone' => ['enabled' => true, 'required' => false],
                'address' => ['enabled' => true, 'required' => false],
                'city' => ['enabled' => true, 'required' => false],
                'state' => ['enabled' => true, 'required' => false],
                'zip' => ['enabled' => true, 'required' => false],
                'country' => ['enabled' => true, 'required' => false],
                'company' => ['enabled' => true, 'required' => false],
                'website' => ['enabled' => true, 'required' => false],
                'about' => ['enabled' => true, 'required' => false],
                'avatar' => ['enabled' => true, 'required' => false],
            ],
        ];
    }

    /**
     * Initialize components
     */
    private function init_components(): void
    {
        // Initialize handlers
        $this->registration = new UserRegistrationHandler($this);
        $this->email = new EmailHandler($this);
        $this->shortcode = new ShortcodeHandler($this);
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes(): void
    {
        add_shortcode('cobra_login', [$this->shortcode, 'login_form']);
        add_shortcode('cobra_register', [$this->shortcode, 'register_form']);
        add_shortcode('cobra_forgot_password', [$this->shortcode, 'forgot_password_form']);
        add_shortcode('cobra_reset_password', [$this->shortcode, 'reset_password_form']);
        add_shortcode('cobra_account', [$this->shortcode, 'account_form']);
        add_shortcode('cobra_logout', [$this->shortcode, 'logout_link']);
        add_shortcode('cobra_confirm_registration', [$this->shortcode, 'confirm_registration']);
    }

    /**
     * Initialize hooks
     */
    public function init_hooks(): void
    {
        parent::init_hooks();
        add_filter('manage_users_columns', [$this, 'add_user_columns']);
        add_filter('manage_users_custom_column', [$this, 'manage_user_columns'], 10, 3);
        add_filter('manage_users_sortable_columns', [$this, 'sort_user_table_by_created_at']);
        add_action('pre_get_users', [$this, 'default_sort_pre_get_users']);
        add_action('show_user_profile', [$this, 'add_custom_user_fields']);
        add_action('edit_user_profile', [$this, 'add_custom_user_fields']);
        add_action('personal_options_update', [$this, 'save_custom_user_fields']);
        add_action('edit_user_profile_update', [$this, 'save_custom_user_fields']);
        add_action('wp_ajax_cobra_create_page', [$this, 'handle_create_page']);
        // resend_verification
        add_action('wp_ajax_cobra_resend_verification', [$this->registration, 'handle_resend_verification']);
        // User management
        add_action('user_register', [$this->registration, 'handle_registration']);
        add_action('wp_logout', [$this->registration, 'handle_logout']);

        // Email verification
        add_action('template_redirect', [$this->registration, 'handle_email_verification']);

        // Custom roles
        add_role('pending', 'Pending', []);

        $this->shortcode->short_add_action();
    }

    /**
     * Initialize admin
     */
    public function init_admin(): void
    {
        $settings = $this->get_settings();

        // Use proper array access with default values
        $disable_admin = $settings['general']['disable_admin_menu'] ?? false;

        if ($disable_admin) {
            $this->disable_admin_menu();
        }
    }

    /**
     * Add user columns
     */
    public function add_user_columns($columns): array
    {
        $columns['verified'] = __('Email Verified', 'cobra-ai');
        // created_at
        $columns['created_at'] = __('Created At', 'cobra-ai');
        // $columns['status'] = __('Status', 'cobra-ai');
        return $columns;
    }

    /**
     * Manage user columns
     */
    public function manage_user_columns($value, $column_name, $user_id): string
    {
        switch ($column_name) {
            case 'verified':
                return get_user_meta($user_id, '_email_verified', true) ? '✅' : '❌';

                // case 'status':
                //     $user = get_user_by('id', $user_id);
                //     return !empty($user->roles) ? ucfirst($user->roles[0]) : 'None';
            case 'created_at':
                $user = get_user_by('id', $user_id);
                return !empty($user->user_registered) ?  $user->user_registered : 'None';

            default:
                return $value;
        }
    }
    public function sort_user_table_by_created_at($sortable): array
    {
        // Add custom column to sortable array
        $sortable['created_at'] = 'user_registered';
        return $sortable;
    }

    public function default_sort_pre_get_users(   $q ): void {

        // Only touch the Users screen in wp‑admin (not frontend queries).
        if ( ! is_admin() ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'users' !== $screen->id ) {
            return;
        }
    
        $orderby = $q->get( 'orderby' );
    
        // If the user clicked our column header, $_GET['orderby']=user_registered
        // already (see 1️⃣); nothing to translate.
        // But "old" URLs bookmarked with orderby=created_at will still work:
        if ( empty( $_REQUEST['orderby'] ) ) {
            $q->set( 'orderby', 'user_registered' ); // newest ...
            $q->set( 'order',   'DESC' );            // ... first
        }
    
        /* Back‑compat for bookmarked links that still use ?orderby=created_at */
        if ( 'created_at' === $q->get( 'orderby' ) ) {
            $q->set( 'orderby', 'user_registered' );
        }
    }

    /**
     * Add custom user fields
     */
    public function add_custom_user_fields($user): void
    {
        require_once __DIR__ . '/views/user-fields.php';
    }
    public function  get_field_label($field): string
    {
        $labels = [
            'username' => __('Username', 'cobra-ai'),
            'email' => __('Email', 'cobra-ai'),
            'password' => __('Password', 'cobra-ai'),
            'confirm_password' => __('Confirm Password', 'cobra-ai'),
            'first_name' => __('First Name', 'cobra-ai'),
            'last_name' => __('Last Name', 'cobra-ai'),
            'phone' => __('Phone', 'cobra-ai'),
            'address' => __('Address', 'cobra-ai'),
            'city' => __('City', 'cobra-ai'),
            'state' => __('State', 'cobra-ai'),
            'zip' => __('Zip', 'cobra-ai'),
            'country' => __('Country', 'cobra-ai'),
            'company' => __('Company', 'cobra-ai'),
            'website' => __('Website', 'cobra-ai'),
            'about' => __('About', 'cobra-ai'),
            'avatar' => __('Avatar', 'cobra-ai'),
        ];

        return $labels[$field] ?? '';
    }
    /**
     * Save custom user fields
     */
    public function save_custom_user_fields($user_id): void
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $fields = $this->get_settings('fields');
        foreach ($fields as $field => $config) {
            if ($config['enabled'] && isset($_POST[$field])) {
                update_user_meta($user_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Disable admin menu for non-admin users
     */
    private function disable_admin_menu(): void
    {
        if (!current_user_can('manage_options')) {
            add_filter('show_admin_bar', '__return_false');
            // add_action('admin_init', function () {
            //     wp_redirect(home_url());
            //     exit;
            // });
        }
    }

    /**
     * Get default email template
     */
    private function get_default_email_template(): string
    {
        return file_get_contents(__DIR__ . '/templates/email/global.html');
    }

    /**
     * Get default verification email
     */
    private function get_default_verification_email(): string
    {
        return file_get_contents(__DIR__ . '/templates/email/verification.html');
    }

    /**
     * Get default confirmation email
     */
    private function get_default_confirmation_email(): string
    {
        return file_get_contents(__DIR__ . '/templates/email/confirmation.html');
    }

    /**
     * Validate settings
     */
    protected function validate_settings(array $settings): array
    {
        // Get default settings
        $defaults = $this->get_feature_default_options();

        // Handle general settings
        if (isset($settings['general'])) {
            // print_r($settings['general']);
            // exit;
            // Start with the defaults
            $validated_general = $defaults['general'];

            // Set boolean values properly from form input
            $validated_general['disable_admin_menu'] = isset($settings['general']['disable_admin_menu']) && $settings['general']['disable_admin_menu'];
            $validated_general['use_recaptcha'] = isset($settings['general']['use_recaptcha']) && $settings['general']['use_recaptcha'];

            // Handle default role
            if (!empty($settings['general']['default_role'])) {
                $valid_roles = array_keys(get_editable_roles());
                $validated_general['default_role'] = in_array($settings['general']['default_role'], $valid_roles)
                    ? $settings['general']['default_role']
                    : $defaults['general']['default_role'];
            }

            // Replace the general settings
            $settings['general'] = $validated_general;
        }

        // Validate pages settings
        if (isset($settings['pages'])) {
            $settings['pages'] = wp_parse_args($settings['pages'], $defaults['pages']);

            // Ensure all page IDs are integers
            foreach ($settings['pages'] as $key => $value) {
                if ($value) {
                    $settings['pages'][$key] = absint($value);
                }
            }
        }

        // Validate redirects
        if (isset($settings['redirects'])) {
            $settings['redirects'] = wp_parse_args($settings['redirects'], $defaults['redirects']);

            // Ensure valid URLs
            foreach ($settings['redirects'] as $key => $url) {
                if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
                    $settings['redirects'][$key] = $defaults['redirects'][$key];
                }
            }
        }

        // Validate email settings
        if (isset($settings['emails'])) {
            $settings['emails'] = wp_parse_args($settings['emails'], $defaults['emails']);

            // Additional email template validation if needed
        }

        if (isset($settings['fields'])) {
            // Start with a fresh fields array
            $validated_fields = [];

            // Get all possible fields from defaults
            $all_fields = array_keys($defaults['fields']);

            // Process each field
            foreach ($all_fields as $field_key) {
                // Handle required system fields
                if (in_array($field_key, ['username', 'email', 'password', 'confirm_password'])) {
                    $validated_fields[$field_key] = [
                        'enabled' => true,
                        'required' => true
                    ];
                    continue;
                }

                // Handle optional fields
                $validated_fields[$field_key] = [
                    'enabled' => isset($settings['fields'][$field_key]['enabled']) && $settings['fields'][$field_key]['enabled'],
                    'required' => isset($settings['fields'][$field_key]['required']) && $settings['fields'][$field_key]['required']
                ];
            }

            // Replace the fields settings with validated ones
            $settings['fields'] = $validated_fields;
        }
        return $settings;
    }
    /**
     * Get reCAPTCHA feature
     */
    protected function get_recaptcha_feature()
    {
        global $cobra_ai;
        return $cobra_ai ? $cobra_ai->get_feature('recaptcha') : null;
    }
    /**
     * Check if reCAPTCHA is available and configured
     */
    public function is_recaptcha_available(): bool
    {
        $recaptcha = $this->get_recaptcha_feature();
        return $recaptcha && $recaptcha->is_ready();
    }


    /**
     * Add this method to your Feature class
     */
    public function handle_create_page(): void
    {
        try {
            // Verify nonce
            if (!check_ajax_referer('cobra_create_page', 'nonce', false)) {
                throw new \Exception(__('Invalid security token.', 'cobra-ai'));
            }

            // Verify permissions
            if (!current_user_can('publish_pages')) {
                throw new \Exception(__('You do not have permission to create pages.', 'cobra-ai'));
            }

            // Get and validate data
            $page_type = sanitize_key($_POST['page_type'] ?? '');
            $page_title = sanitize_text_field($_POST['page_title'] ?? '');
            $page_content = sanitize_text_field($_POST['page_content'] ?? '');

            if (!$page_type || !$page_title || !$page_content) {
                throw new \Exception(__('Missing required data.', 'cobra-ai'));
            }

            // Create page
            $page_id = wp_insert_post([
                'post_title' => $page_title,
                'post_content' => $page_content,
                'post_status' => 'publish',
                'post_type' => 'page'
            ]);

            if (is_wp_error($page_id)) {
                throw new \Exception($page_id->get_error_message());
            }

            // Update settings
            $settings = $this->get_settings();
            $settings['pages'][$page_type] = $page_id;
            $this->update_settings($settings, 'pages');

            wp_send_json_success([
                'message' => __('Page created successfully.', 'cobra-ai'),
                'page_id' => $page_id
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    /**
     * Update settings
     */
    public function update_settings(array $new_settings, string $tab = 'general'): bool
    {
        try {
            // Get current settings
            $current_settings = $this->get_settings();

            // Get current tab being updated
            $current_tab = $_POST['tab'] ?? $tab;

            // Prepare settings to update
            $settings_to_update = $current_settings;
            // print_r($settings_to_update);
            // echo "settings_to_update" .  $current_tab;
            // exit;
            // Update only the current tab's settings
            switch ($current_tab) {
                case 'general':

                    $settings_to_update['general'] = isset($new_settings['general']) ?
                        $new_settings['general'] :
                        $current_settings['general'];
                    break;

                case 'pages':
                    $settings_to_update['pages'] = wp_parse_args(
                        $new_settings['pages'] ?? [],
                        $current_settings['pages'] ?? []
                    );
                    $settings_to_update['redirects'] = wp_parse_args(
                        $new_settings['redirects'] ?? [],
                        $current_settings['redirects'] ?? []
                    );
                    break;

                case 'emails':
                    $settings_to_update['emails'] = wp_parse_args(
                        $new_settings['emails'] ?? [],
                        $current_settings['emails'] ?? []
                    );
                    break;

                case 'fields':
                    $settings_to_update['fields'] = isset($new_settings['fields']) ?
                        $new_settings['fields'] :
                        $current_settings['fields'];
                    break;
            }

            // Validate settings
            $settings_to_update = $this->validate_settings($settings_to_update);

            // Update the settings
            $updated = update_option(
                'cobra_ai_' . $this->get_feature_id() . '_options',
                $settings_to_update
            );

            if ($updated) {
                do_action('cobra_ai_feature_settings_updated_' . $this->get_feature_id(), $settings_to_update);
            }

            return $updated;
        } catch (\Exception $e) {
            error_log(sprintf(
                'Failed to update settings for feature %s: %s',
                $this->get_feature_id(),
                $e->getMessage()
            ));
            throw $e;
        }
    }
    protected function get_js_translations(): array
    {
        return   [
            'passwordTooShort' => __('Password must be at least 8 characters', 'cobra-ai'),
            'passwordNeedsUpper' => __('Add uppercase letter', 'cobra-ai'),
            'passwordNeedsLower' => __('Add lowercase letter', 'cobra-ai'),
            'passwordNeedsNumber' => __('Add number', 'cobra-ai'),
            'passwordNeedsSpecial' => __('Add special character', 'cobra-ai'),
            'passwordTooLong' => __('Password must be less than 60 characters', 'cobra-ai'),
            'veryWeak' => __('Very Weak', 'cobra-ai'),
            'weak' => __('Weak', 'cobra-ai'),
            'medium' => __('Medium', 'cobra-ai'),
            'strong' => __('Strong', 'cobra-ai'),
            'veryStrong' => __('Very Strong', 'cobra-ai'),
            'passwordsMatch' => __('Passwords match', 'cobra-ai'),
            'passwordsMismatch' => __('Passwords do not match', 'cobra-ai'),
            'available' => __('Available', 'cobra-ai'),
            'checkFailed' => __('Check failed', 'cobra-ai'),
            'hidePassword' => __('Hide password', 'cobra-ai'),
            'showPassword' => __('Show password', 'cobra-ai'),
            'fieldRequired' => __('This field is required', 'cobra-ai'),
            'passwordTooWeak' => __('Password is too weak', 'cobra-ai'),
            'passwordRequirements' => __('Password does not meet the requirements', 'cobra-ai'),
            'passwordMismatch' => __('Passwords do not match', 'cobra-ai')
        ];
    }

    protected function is_html_allowed_field(string $key): bool
    {
        $allowed_html_fields = ['global_template', 'verification', 'confirmation'];
        // log this

        error_log('is_html_allowed_field: ' . $key);

        return in_array($key, $allowed_html_fields, true);
    }
}
