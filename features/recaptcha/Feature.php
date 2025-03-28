<?php

namespace CobraAI\Features\Recaptcha;

use CobraAI\FeatureBase;
use CobraAI\Features\Recaptcha\Includes\RecaptchaHandler;

class Feature extends FeatureBase
{
    protected $feature_id = 'recaptcha';
    protected $name = 'Google reCAPTCHA';
    protected $description = 'Add Google reCAPTCHA protection to various WordPress forms';
    protected $version = '1.0.0';
    protected $author = 'Your Name';
    protected $has_settings = true;
    protected $has_admin = true;
    /**
     * Class instances
     */
    private $handler;
    /**
     * Setup feature
     */
    protected function setup(): void
    {
        global $wpdb;

        // Define feature tables
        $this->tables = [
            // 'recaptcha_logs' => [
            //     'name' => $wpdb->prefix . 'cobra_recaptcha_logs',
            //     'schema' => [
            //         'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
            //         'form_type' => 'varchar(50) NOT NULL',
            //         'ip_address' => 'varchar(45) NOT NULL',
            //         'verification_status' => 'tinyint(1) DEFAULT 0',
            //         'error_code' => 'varchar(50)',
            //         'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
            //         'PRIMARY KEY' => '(id)',
            //         'KEY' => [
            //             'form_type' => '(form_type)',
            //             'ip_address' => '(ip_address)',
            //             'created_at' => '(created_at)'
            //         ]
            //     ]
            // ]
        ];
       
        // Initialize components
        require_once __DIR__ . '/includes/RecaptchaHandler.php';
        require_once __DIR__ . '/includes/RecaptchaVerifier.php';
        // Initialize handler
        $this->handler = new RecaptchaHandler($this);
        // Register actions for different forms
        add_action('login_form', [$this, 'add_to_login_form']);
        add_action('register_form', [$this, 'add_to_register_form']);
        add_action('lostpassword_form', [$this, 'add_to_lostpassword_form']);
        add_action('comment_form', [$this, 'add_to_comment_form']);
        add_action('password_protected_form', [$this, 'add_to_protected_post_form']);

        // Form validation hooks
        add_filter('registration_errors', [$this, 'validate_registration'], 10, 3);
        add_filter('authenticate', [$this, 'validate_login'], 30, 3);
        add_action('lostpassword_post', [$this, 'validate_lostpassword']);
        add_filter('preprocess_comment', [$this, 'validate_comment']);
        add_filter('password_protected_auth', [$this, 'validate_protected_post']);

        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_recaptcha']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_recaptcha']);

        // AJAX handlers for verification
        add_action('wp_ajax_verify_recaptcha', [$this, 'verify_recaptcha']);
        add_action('wp_ajax_nopriv_verify_recaptcha', [$this, 'verify_recaptcha']);

  
        // Add AJAX handlers
        add_action('wp_ajax_cobra_ai_test_recaptcha', [$this, 'handle_test_recaptcha']);
        if (is_admin()) {
            add_action('admin_notices', [$this, 'display_status_notices'], 10);
        }
    }

    /**
     * Get default feature options
     */
    protected function get_feature_default_options(): array
    {
        return [
            'version' => 'v2',  // v2, v3, invisible
            'site_key' => '',
            'secret_key' => '',
            'theme' => 'light', // light, dark
            'size' => 'normal', // normal, compact
            'badge_position' => 'bottomright', // bottomright, bottomleft, inline
            'score_threshold' => 0.5, // for v3
            'language' => 'en',
            'disable_submit' => true,
            'allowlisted_ips' => [],
            'enabled_forms' => [
                'login' => true,
                'register' => true,
                'lostpassword' => true,
                'comments' => true,
                'protected_posts' => true,
                'contact_form' => true,
                'testimonials' => true,
                'custom_form' => false
            ],
            'custom_form_selectors' => '',
            'error_messages' => [
                'missing_input_secret' => 'The secret key is missing.',
                'invalid_input_secret' => 'The secret key is invalid or malformed.',
                'missing_input_response' => 'Please complete the reCAPTCHA.',
                'invalid_input_response' => 'The reCAPTCHA response is invalid or expired.',
                'bad_request' => 'The request is invalid or malformed.',
                'timeout_or_duplicate' => 'The response is no longer valid: either is too old or has been used previously.'
            ]
        ];
    }

    /**
     * Validate settings before saving
     */
    protected function validate_settings(array $settings): array
    {
       
        $errors = [];
        // $settings can't be null
        if ($settings === null) {
            $settings = [];
        }
        // Validate reCAPTCHA version
        if (!in_array($settings['version'], ['v2', 'v3', 'invisible'])) {
            $errors['version'] = __('Invalid reCAPTCHA version.', 'cobra-ai');
            $settings['version'] = 'v2';
        }

        // Validate site key format
        if (!empty($settings['site_key'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $settings['site_key'])) {
                $errors['site_key'] = __('Invalid site key format.', 'cobra-ai');
                $settings['site_key'] = '';
            }
        }

        // Validate secret key format
        if (!empty($settings['secret_key'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $settings['secret_key'])) {
                $errors['secret_key'] = __('Invalid secret key format.', 'cobra-ai');
                $settings['secret_key'] = '';
            }
        }

        // Validate theme
        if (!in_array($settings['theme'], ['light', 'dark'])) {
            $errors['theme'] = __('Invalid theme selection.', 'cobra-ai');
            $settings['theme'] = 'light';
        }

        // Validate size
        if (!in_array($settings['size'], ['normal', 'compact'])) {
            $errors['size'] = __('Invalid size selection.', 'cobra-ai');
            $settings['size'] = 'normal';
        }

        // Validate badge position
        if (!in_array($settings['badge_position'], ['bottomright', 'bottomleft', 'inline'])) {
            $errors['badge_position'] = __('Invalid badge position.', 'cobra-ai');
            $settings['badge_position'] = 'bottomright';
        }

        // Validate score threshold
        if ($settings['version'] === 'v3') {
            $settings['score_threshold'] = floatval($settings['score_threshold']);
            if ($settings['score_threshold'] < 0 || $settings['score_threshold'] > 1) {
                $errors['score_threshold'] = __('Score threshold must be between 0 and 1.', 'cobra-ai');
                $settings['score_threshold'] = 0.5;
            }
        }

        // Validate IP addresses
        if (!empty($settings['allowlisted_ips'])) {
            $valid_ips = [];
            $ips = is_array($settings['allowlisted_ips'])
                ? $settings['allowlisted_ips']
                : explode("\n", $settings['allowlisted_ips']);

            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $valid_ips[] = $ip;
                }
            }
            $settings['allowlisted_ips'] = $valid_ips;
        }

        // Validate enabled forms
        $valid_forms = [
            'login',
            'register',
            'lostpassword',
            'comments',
            'protected_posts',
            'contact_form',
            'testimonials',
            'custom_form'
        ];
        foreach ($settings['enabled_forms'] as $form => $enabled) {
            if (!in_array($form, $valid_forms)) {
                unset($settings['enabled_forms'][$form]);
            }
            $settings['enabled_forms'][$form] = (bool) $enabled;
        }
        // Store validation errors
        if (!empty($errors)) {
           
            
            update_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors', $errors);
        } else {
           
            delete_option('cobra_ai_' . $this->get_feature_id() . '_validation_errors');
        }

        return $settings;
    }

    /**
     * Add reCAPTCHA to login form
     */
    public function add_to_login_form(): void
    {
        $settings = $this->get_settings();
        if (!isset($settings['enabled_forms']['login']) || !$settings['enabled_forms']['login'] || $this->is_ip_allowlisted()) {
            return;
        }

        $this->render_recaptcha('login');
    }

    /**
     * Add reCAPTCHA to registration form
     */
    public function add_to_register_form(): void
    {
        $settings = $this->get_settings();
        if (!$settings['enabled_forms']['register'] || $this->is_ip_allowlisted()) {
            return;
        }

        $this->render_recaptcha('register');
    }

    /**
     * Add reCAPTCHA to lost password form
     */
    public function add_to_lostpassword_form(): void
    {
        $settings = $this->get_settings();
        if (!$settings['enabled_forms']['lostpassword'] || $this->is_ip_allowlisted()) {
            return;
        }

        $this->render_recaptcha('lostpassword');
    }

    /**
     * Add reCAPTCHA to comments form
     */
    public function add_to_comment_form(): void
    {
        $settings = $this->get_settings();
        if (!$settings['enabled_forms']['comments'] || $this->is_ip_allowlisted()) {
            return;
        }

        $this->render_recaptcha('comments');
    }

    /**
     * Add reCAPTCHA to protected post form
     */
    public function add_to_protected_post_form(): void
    {
        $settings = $this->get_settings();
        if (!$settings['enabled_forms']['protected_posts'] || $this->is_ip_allowlisted()) {
            return;
        }

        $this->render_recaptcha('protected_posts');
    }
    /**
     * Validate registration form
     */
    public function validate_registration($errors, $sanitized_user_login, $user_email): \WP_Error
    {
        if (!$this->is_ready() || !$this->get_settings('enabled_forms')['register']) {
            return $errors;
        }

        try {
            if (!$this->verify_recaptcha_response('register')) {
                $errors->add(
                    'recaptcha_error',
                    $this->get_error_message('invalid_input_response')
                );
            }
        } catch (\Exception $e) {
            $errors->add('recaptcha_error', $e->getMessage());
        }

        return $errors;
    }

    /**
     * Validate login form
     */
    public function validate_login($user, $username, $password)
    {
        
        if (!$this->is_ready() || !isset($this->get_settings('enabled_forms')['login']) || !$this->get_settings('enabled_forms')['login']) {
            return $user;
        }

        try {
            // Skip verification for already authenticated users
            if ($user instanceof \WP_User) {
                return $user;
            }

            if (!$this->verify_recaptcha_response('login')) {
                return new \WP_Error(
                    'recaptcha_error',
                    $this->get_error_message('invalid_input_response')
                );
            }

            return $user;
        } catch (\Exception $e) {
            return new \WP_Error('recaptcha_error', $e->getMessage());
        }
    }

    /**
     * Validate lost password form
     */
    public function validate_lostpassword($errors)
    {
        if (!$this->is_ready() || !$this->get_settings('enabled_forms')['lostpassword']) {
            return;
        }

        try {
            if (!$this->verify_recaptcha_response('lostpassword')) {
                if (!is_wp_error($errors)) {
                    $errors = new \WP_Error();
                }
                $errors->add(
                    'recaptcha_error',
                    $this->get_error_message('invalid_input_response')
                );
                wp_die($errors);
            }
        } catch (\Exception $e) {
            if (!is_wp_error($errors)) {
                $errors = new \WP_Error();
            }
            $errors->add('recaptcha_error', $e->getMessage());
            wp_die($errors);
        }
    }

    /**
     * Validate comment form
     */
    public function validate_comment($commentdata)
    {
        if (!$this->is_ready() || !$this->get_settings('enabled_forms')['comments']) {
            return $commentdata;
        }

        try {
            // Skip verification for logged-in users if configured
            if (is_user_logged_in() && !$this->get_settings('verify_logged_in')) {
                return $commentdata;
            }

            if (!$this->verify_recaptcha_response('comment')) {
                wp_die(
                    $this->get_error_message('invalid_input_response'),
                    __('Comment Submission Failed', 'cobra-ai'),
                    ['response' => 403, 'back_link' => true]
                );
            }

            return $commentdata;
        } catch (\Exception $e) {
            wp_die(
                $e->getMessage(),
                __('Comment Submission Failed', 'cobra-ai'),
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    /**
     * Validate protected post form
     */
    public function validate_protected_post($access)
    {
        if (!$this->is_ready() || !$this->get_settings('enabled_forms')['protected_posts']) {
            return $access;
        }

        try {
            if (!$this->verify_recaptcha_response('protected_post')) {
                wp_die(
                    $this->get_error_message('invalid_input_response'),
                    __('Access Denied', 'cobra-ai'),
                    ['response' => 403, 'back_link' => true]
                );
            }

            return $access;
        } catch (\Exception $e) {
            wp_die(
                $e->getMessage(),
                __('Access Denied', 'cobra-ai'),
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    /**
     * Verify reCAPTCHA response
     */
    private function verify_recaptcha_response(string $action): bool
    {
        // Check if IP is allowlisted
        if ($this->is_ip_allowlisted()) {
            return true;
        }
     
        // Get reCAPTCHA response
        $response = $_POST['g-recaptcha-response'] ?? '';
        if (empty($response)) {
            throw new \Exception($this->get_error_message('missing_input_response'));
        }

        // Verify through the handler
        return $this->handler->verify_response($response, $action);
    }

    public function verify_response($response, $action): bool
    {
         // Check if IP is allowlisted
         if ($this->is_ip_allowlisted()) {
            return true;
        } 
        if (empty($response)) {
            throw new \Exception($this->get_error_message('missing_input_response'));
        }
    
        return $this->handler->verify_response($response, $action);

    }

    

    /**
     * Get error message
     */
    private function get_error_message(string $code): string
    {
        $settings = $this->get_settings();
        $messages = $settings['error_messages'] ?? [];

        $default_messages = [
            'missing_input_secret' => __('The secret key is missing.', 'cobra-ai'),
            'invalid_input_secret' => __('The secret key is invalid or malformed.', 'cobra-ai'),
            'missing_input_response' => __('Please complete the reCAPTCHA.', 'cobra-ai'),
            'invalid_input_response' => __('The reCAPTCHA response is invalid or expired.', 'cobra-ai'),
            'bad_request' => __('The request is invalid or malformed.', 'cobra-ai'),
            'timeout_or_duplicate' => __('The response is no longer valid: either is too old or has been used previously.', 'cobra-ai')
        ];

        return $messages[$code] ?? $default_messages[$code] ?? __('An error occurred.', 'cobra-ai');
    }
    /**
     * Check if IP is allowlisted
     */
    private function is_ip_allowlisted(): bool
    {
        $settings = $this->get_settings();
        $allowlisted_ips = $settings['allowlisted_ips'] ?? [];

        if (empty($allowlisted_ips)) {
            return false;
        }

        $client_ip = $this->get_client_ip();

        // Convert string to array if necessary
        if (is_string($allowlisted_ips)) {
            $allowlisted_ips = array_filter(array_map('trim', explode("\n", $allowlisted_ips)));
        }

        return in_array($client_ip, $allowlisted_ips);
    }

    /**
     * Get client IP address
     */
    protected function get_client_ip(): string
    {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Enqueue reCAPTCHA scripts
     */
    public function enqueue_recaptcha(): void
    {
        $settings = $this->get_settings();

        if (empty($settings['site_key']) || $this->is_ip_allowlisted()) {
            return;
        }

        // Enqueue Google reCAPTCHA API
        $url = 'https://www.google.com/recaptcha/api.js';
        $params = [];

        if ($settings['version'] === 'v3') {
            $params['render'] = $settings['site_key'];
        }

        if (!empty($settings['language'])) {
            $params['hl'] = $settings['language'];
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        wp_enqueue_script(
            'google-recaptcha',
            $url,
            [],
            null,
            true
        );

        // Enqueue our custom script
        wp_enqueue_script(
            'cobra-recaptcha',
            $this->assets_url . 'js/recaptcha.js',
            ['jquery', 'google-recaptcha'],
            $this->version,
            true
        );

        wp_localize_script('cobra-recaptcha', 'cobraRecaptcha', [
            'version' => $settings['version'],
            'siteKey' => $settings['site_key'],
            'theme' => $settings['theme'],
            'size' => $settings['size'],
            'badge' => $settings['badge_position'],
            'disableSubmit' => $settings['disable_submit'],
            'scoreThreshold' => $settings['score_threshold'],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cobra-recaptcha-verify')
        ]);
    }

    /**
     * Render reCAPTCHA
     */
    public function render_recaptcha(string $form_type): void
    {
        $settings = $this->get_settings();
        if ($settings['version'] === 'v2') {
            echo sprintf(
                '<div class="cobra-recaptcha" data-form="%s" data-sitekey="%s" data-theme="%s" data-size="%s"></div>',
                esc_attr($form_type),
                esc_attr($settings['site_key']),
                esc_attr($settings['theme']),
                esc_attr($settings['size'])
            );
        }

        if ($settings['version'] === 'v3') {
            echo sprintf(
                '<div class="cobra-recaptcha" data-form="%s" data-sitekey="%s" data-version="%s" data-threshold="%s"></div>',
                esc_attr($form_type),
                esc_attr($settings['site_key']),
                esc_attr($settings['version']),
                esc_attr($settings['score_threshold'])
            );
        }

        // invisible reCAPTCHA
        if ($settings['version'] === 'invisible') {
            echo sprintf(
                '<button class="g-recaptcha cobra-recaptcha" data-form="%s" data-sitekey="%s" data-size="%s" data-badge="%s" data-callback="onCobraRecaptchaSubmit"></button>',
                esc_attr($form_type),
                esc_attr($settings['site_key']),
                esc_attr($settings['size']),
                esc_attr($settings['badge_position'])
            );
        }
    }


    /**
     * Add these methods to your Feature class
     */

    /**
     * Get reCAPTCHA status
     * 
     * @return array Status information
     */
    public function get_status(): array
    {
        $settings = $this->get_settings();
        $status = [
            'is_active' => true,
            'is_configured' => false,
            'is_ready' => false,
            'messages' => [],
            'warnings' => [],
            'errors' => []
        ];

        // Check if feature is active
        if (!$this->is_active()) {
            $status['is_active'] = false;
            $status['messages'][] = __('reCAPTCHA is currently deactivated.', 'cobra-ai');
            return $status;
        }

        // Check version setting
        if (empty($settings['version'])) {
            $status['errors'][] = __('reCAPTCHA version is not selected.', 'cobra-ai');
        } elseif (!in_array($settings['version'], ['v2', 'v3', 'invisible'])) {
            $status['errors'][] = __('Invalid reCAPTCHA version selected.', 'cobra-ai');
        }

        // Check API keys
        if (empty($settings['site_key'])) {
            $status['errors'][] = __('Site key is not configured.', 'cobra-ai');
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $settings['site_key'])) {
            $status['errors'][] = __('Site key format is invalid.', 'cobra-ai');
        }

        if (empty($settings['secret_key'])) {
            $status['errors'][] = __('Secret key is not configured.', 'cobra-ai');
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $settings['secret_key'])) {
            $status['errors'][] = __('Secret key format is invalid.', 'cobra-ai');
        }

        // Check if any forms are enabled
        if (empty($settings['enabled_forms']) || !is_array($settings['enabled_forms'])) {
            $status['warnings'][] = __('No forms are selected for protection.', 'cobra-ai');
        } else {
       
            $enabled_count = count(array_filter($settings['enabled_forms']));
            if ($enabled_count === 0) {
                $status['warnings'][] = __('No forms are selected for protection.', 'cobra-ai');
            } else {
                $status['messages'][] = sprintf(
                    _n(
                        '%d form is protected by reCAPTCHA.',
                        '%d forms are protected by reCAPTCHA.',
                        $enabled_count,
                        'cobra-ai'
                    ),
                    $enabled_count
                );
            }
        }

        // Set configuration status
        $status['is_configured'] = empty($status['errors']);

        // Additional checks for v3
        if ($settings['version'] === 'v3') {
            if (!isset($settings['score_threshold'])) {
                $status['warnings'][] = __('Score threshold is not set for v3, using default (0.5).', 'cobra-ai');
            }
        }

        // Check logging status
        if (!empty($settings['enable_logging'])) {
            $status['messages'][] = __('Logging is enabled.', 'cobra-ai');

            // Check if logs table exists
            if (!$this->check_logs_table()) {
                $status['warnings'][] = __('Logs table is missing.', 'cobra-ai');
            }
        }

        // Set ready status
        $status['is_ready'] = $status['is_configured'] && empty($status['warnings']);

        return $status;
    }

    /**
     * Check if reCAPTCHA is properly configured and ready
     * 
     * @return bool
     */
    public function is_ready(): bool
    {
        $status = $this->get_status();
        return $status['is_ready'];
    }

  

    /**
     * Display status notices in admin
     */
    public function display_status_notices(): void
    {
       
         
 
        $status = $this->get_status();
   
        // Show errors
        if (!empty($status['errors'])) {
?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php echo esc_html__('reCAPTCHA Configuration Errors:', 'cobra-ai'); ?></strong>
                </p>
                <ul>
                    <?php foreach ($status['errors'] as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-recaptcha')); ?>"
                        class="button button-secondary">
                        <?php echo esc_html__('Configure reCAPTCHA', 'cobra-ai'); ?>
                    </a>
                </p>
            </div>
        <?php
        }

        // Show warnings
        /* if (!empty($status['warnings'])) {
        ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php echo esc_html__('reCAPTCHA Warnings:', 'cobra-ai'); ?></strong>
                </p>
                <ul>
                    <?php foreach ($status['warnings'] as $warning): ?>
                        <li><?php echo esc_html($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php
        }
        */

        // Show success message if everything is configured correctly
       /* if ($status['is_ready']) {
        ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php echo esc_html__('reCAPTCHA is properly configured and active.', 'cobra-ai'); ?></strong>
                </p>
                <?php if (!empty($status['messages'])): ?>
                    <ul>
                        <?php foreach ($status['messages'] as $message): ?>
                            <li><?php echo esc_html($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
<?php
        }*/
    }

    /**
     * Check if feature is active
     */
    public function is_active(): bool
    {
        $active_features = get_option('cobra_ai_enabled_features', []);
        return in_array($this->feature_id, $active_features);
    }
//  TODO: delete this function
    /**
     * Check if logs table exists
     */
    private function check_logs_table(): bool
    {
        global $wpdb;
        $table_name = $this->get_logs_table();

        if (empty($table_name)) {
            return false;
        }

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
//  TODO: delete this function
    /**
     * Get logs table name
     */
    public function get_logs_table(): string
    {
        return $this->tables['recaptcha_logs']['name'] ?? '';
    }

    public function handle_test_recaptcha(): void
    {
        try {
            // Verify nonce
            if (!check_ajax_referer('test-recaptcha', 'nonce', false)) {
                throw new \Exception(__('Invalid security token.', 'cobra-ai'));
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('You do not have permission to perform this action.', 'cobra-ai'));
            }

            // Get and validate parameters
            $site_key = sanitize_text_field($_POST['site_key'] ?? '');
            $secret_key = sanitize_text_field($_POST['secret_key'] ?? '');
            $version = sanitize_text_field($_POST['version'] ?? '');

            if (empty($site_key) || empty($secret_key)) {
                throw new \Exception(__('Both site key and secret key are required.', 'cobra-ai'));
            }

            // Validate keys format
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $site_key)) {
                throw new \Exception(__('Invalid site key format.', 'cobra-ai'));
            }

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $secret_key)) {
                throw new \Exception(__('Invalid secret key format.', 'cobra-ai'));
            }

            // Test the configuration
            $test_result = $this->test_recaptcha_configuration($site_key, $secret_key, $version);

            wp_send_json_success([
                'message' => __('Configuration test completed.', 'cobra-ai'),
                'details' => $test_result
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    /**
     * Test reCAPTCHA configuration
     */
    private function test_recaptcha_configuration(string $site_key, string $secret_key, string $version): array
    {
        $results = [
            'status' => 'unknown',
            'checks' => [],
            'messages' => []
        ];

        // 1. Validate version
        if (!in_array($version, ['v2', 'v3', 'invisible'])) {
            $results['checks']['version'] = false;
            $results['messages'][] = __('Invalid reCAPTCHA version selected.', 'cobra-ai');
        } else {
            $results['checks']['version'] = true;
        }

        // 2. Validate site key format
        $site_key_prefix = $version === 'v3' ? 'v3' : 'v2';
        // if (strpos($site_key, $site_key_prefix) === false) {
        //     $results['checks']['site_key_format'] = false;
        //     $results['messages'][] = sprintf(
        //         __('Site key does not match the expected format for reCAPTCHA %s.', 'cobra-ai'),
        //         $version
        //     );
        // } else {
            $results['checks']['site_key_format'] = true;
        // }

        // 3. Test API connection
        try {
            $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => $secret_key,
                    'response' => 'test',
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                ]
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(__('Invalid response from reCAPTCHA server.', 'cobra-ai'));
            }

            // We expect this test to fail since we're sending an invalid token
            // But we should get specific error codes back
            if (isset($result['error-codes']) && is_array($result['error-codes'])) {
                $results['checks']['api_connection'] = true;
                $results['checks']['secret_key_valid'] = !in_array('invalid-input-secret', $result['error-codes']);

                if (!$results['checks']['secret_key_valid']) {
                    $results['messages'][] = __('The secret key appears to be invalid.', 'cobra-ai');
                }
            } else {
                $results['checks']['api_connection'] = false;
                $results['messages'][] = __('Unexpected response from reCAPTCHA server.', 'cobra-ai');
            }
        } catch (\Exception $e) {
            $results['checks']['api_connection'] = false;
            $results['messages'][] = sprintf(
                __('API connection test failed: %s', 'cobra-ai'),
                $e->getMessage()
            );
        }

        // Set overall status
        if (!empty(array_filter($results['messages']))) {
            $results['status'] = 'error';
        } elseif (array_product($results['checks']) === 1) {
            $results['status'] = 'success';
            $results['messages'][] = __('All configuration checks passed successfully.', 'cobra-ai');
        } else {
            $results['status'] = 'warning';
        }

        return $results;
    }
}
