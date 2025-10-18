<?php

namespace CobraAI\Features\Register;

use CobraAI\cobra_ai;

class ShortcodeHandler
{
    /**
     * Parent feature instance
     */
    private $feature;
    private $session_started = false;
    /**
     * Constructor
     */
    public function __construct($feature)
    {
        $this->feature = $feature;
    }

    public function short_add_action()
    {
        add_action('plugins_loaded', [$this, 'init_session'], 1);
        add_action('init', [$this, 'register_form_actions'], 10);
        $this->session_started = true;
        @session_start();
        // Handle form submission
        add_action('admin_post_nopriv_cobra_login_action', [$this, 'handle_login_submission']);
        add_action('admin_post_cobra_login_action', [$this, 'handle_login_submission']);

        //cobra_check_availability_action
        add_action('wp_ajax_cobra_check_availability', [$this, 'handle_check_availability_submission']);
        add_action('wp_ajax_nopriv_cobra_check_availability', [$this, 'handle_check_availability_submission']);

        add_action('admin_post_nopriv_cobra_register_action', [$this, 'handle_registration_submission']);
        add_action('admin_post_cobra_register_action', [$this, 'handle_registration_submission']);
    }
    public function init_session(): void
    {
        if (PHP_SESSION_NONE === session_status() && !$this->session_started) {
            $this->session_started = true;
            @session_start();
        }
    }
    /**
     * Register form actions
     */
    public function register_form_actions(): void
    {
        if (isset($_POST['cobra_login'])) {
            do_action('admin_post_' . ($this->is_user_logged_in() ? '' : 'nopriv_') . 'cobra_login_action');
        }

        if (isset($_POST['cobra_register'])) {
            do_action('admin_post_' . ($this->is_user_logged_in() ? '' : 'nopriv_') . 'cobra_register_action');
        }
    }
    /**
     * Check availability mail or username
     * type : email or username
     * value: value to check
     * @return json wp
     */
    public function handle_check_availability_submission(): void
    {
        // Verify nonce
        if (!check_ajax_referer('cobra-ai-register', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Invalid request.', 'cobra-ai')
            ]);
            return;
        }

        // Get type and value
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';

        // Validate inputs
        if (empty($type) || empty($value)) {
            wp_send_json_error([
                'message' => __('Missing required parameters.', 'cobra-ai')
            ]);
            return;
        }

        // Check availability based on type
        switch ($type) {
            case 'email':
                // Validate email format
                if (!is_email($value)) {
                    wp_send_json_error([
                        'message' => __('Invalid email format.', 'cobra-ai')
                    ]);
                    return;
                }

                // Check if email exists
                if (email_exists($value)) {
                    wp_send_json_error([
                        'message' => __('This email is already registered.', 'cobra-ai')
                    ]);
                    return;
                }

                wp_send_json_success([
                    'message' => __('Email is available.', 'cobra-ai')
                ]);
                break;

            case 'username':
                // Validate username format
                if (!validate_username($value)) {
                    wp_send_json_error([
                        'message' => __('Invalid username format.', 'cobra-ai')
                    ]);
                    return;
                }

                // Check if username exists
                if (username_exists($value)) {
                    wp_send_json_error([
                        'message' => __('This username is already taken.', 'cobra-ai')
                    ]);
                    return;
                }

                wp_send_json_success([
                    'message' => __('Username is available.', 'cobra-ai')
                ]);
                break;

            default:
                wp_send_json_error([
                    'message' => __('Invalid check type.', 'cobra-ai')
                ]);
                break;
        }
    }
    /**
     * Handle login form submission
     */
    public function handle_login_submission(): void
    {
        // Verify nonce
        if (!check_ajax_referer('cobra_login', '_wpnonce', false)) {
            $this->set_error_message(__('Invalid request.', 'cobra-ai'));
            $this->redirect_back();
        }

        // Verify reCAPTCHA if needed
        if ($this->should_verify_recaptcha()) {
            $recaptcha_response = $this->verify_recaptcha('cobra_login');
            if (is_wp_error($recaptcha_response)) {
                $this->set_error_message(__('reCAPTCHA verification failed.', 'cobra-ai'));
                $this->redirect_back();
            }
        }
        $user_login = sanitize_text_field($_POST['username']);

        // Determine if input is an email
        $is_email = is_email($user_login);

        // If it's an email, we need to get the username associated with that email
        if ($is_email) {
            $user = get_user_by('email', $user_login);
            if ($user) {
                // If we found a user with that email, use their username for login
                $user_login = $user->user_login;
            }
        }
        // Process login
        $credentials = [
            'user_login'    => $user_login,
            'user_password' => $_POST['password'],
            'remember'      => isset($_POST['remember'])
        ];
        // Process login
        // $credentials = [
        //     'user_login'    => sanitize_user($_POST['username']),
        //     'user_password' => $_POST['password'],
        //     'remember'      => isset($_POST['remember'])
        // ];

        // $user = wp_signon($credentials, is_ssl());
        $user = wp_signon($credentials);

        if (is_wp_error($user)) {
            $this->set_error_message($user->get_error_message());
            $this->redirect_back();
        }


        // if (!get_user_meta($user->ID, '_email_verified', true)) {
        //     wp_logout();
        //     $this->set_error_message(__('Please verify your email address before logging in.', 'cobra-ai'));
        //     $this->redirect_back();
        // }

        // Successful login
        $this->redirect_after_login();
    }
    /**
     * Login form shortcode
     */
    public function login_form($atts = []): string
    {

        if (is_user_logged_in()) {
            return $this->get_logged_in_message();
        }

        ob_start();
        $is_feature_authGoogle_enabled = $this->feature->is_feature_active('authgoogle');
        // Include the form template with action URL
        $form_action = admin_url('admin-post.php');
        include $this->feature->get_path() . 'views/forms/login.php';
        unset($_SESSION['cobra_form_data_login']);
        return ob_get_clean();
    }


    /**
     * Handle registration form submission
     */
    public function handle_registration_submission(): void
    {
        // Verify nonce
        if (!check_ajax_referer('cobra_register', '_wpnonce', false)) {
            $this->set_error_message(__('Invalid request.', 'cobra-ai'));
            $this->redirect_back();
        }

        // Verify reCAPTCHA if needed
        if ($this->should_verify_recaptcha()) {
            $recaptcha_response = $this->verify_recaptcha('register');
            if (is_wp_error($recaptcha_response)) {
                $this->set_error_message(__('reCAPTCHA verification failed.', 'cobra-ai'));
                $this->redirect_back();
            }
        }

        // Process registration
        $errors = $this->process_registration();

        if ($errors[0]->has_errors()) {
            $this->set_error_message($errors[0]->get_error_messages());
            $this->redirect_back();
        }

        // Successful registration
        // $this->set_success_message(__('Registration successful. Please check your email for verification instructions.', 'cobra-ai'));
        // $this->redirect_to_page('login');
        wp_safe_redirect($this->get_redirect_url('after_login'));
        exit;
    }
    /**
     * Process registration form
     */
    private function process_registration(): array
    {
        $errors = new \WP_Error();


        try {
            // Get form data
            $user_data = $this->get_registration_data();

            // Create user
            $registration_handler = new UserRegistrationHandler($this->feature);
            $user_id = $registration_handler->register_user($user_data);

            return [new \WP_Error(), true];
        } catch (\Exception $e) {
            $errors->add('registration_failed', $e->getMessage());
            return [$errors, false];
        }
    }
    /**
     * Register form shortcode
     */
    public function register_form($atts = []): string
    {
        // Get current user
        $current_user = wp_get_current_user();

        // If user is logged in, show appropriate message
        if (is_user_logged_in()) {
            if (current_user_can('edit_users')) {
                // Admin message
                return sprintf(
                    '<div class="cobra-message info">%s</div>',
                    esc_html__('You are logged in as an administrator and can view this form for testing purposes.', 'cobra-ai')
                );
            } else {
                // Regular user message with account link
                return sprintf(
                    '<div class="cobra-message info">%s <a href="%s">%s</a></div>',
                    esc_html__('You are already registered.', 'cobra-ai'),
                    esc_url($this->get_page_url('account')),
                    esc_html__('View your account', 'cobra-ai')
                );
            }
        }

        // Process registration form submission
        $errors = new \WP_Error();
        $success = false;
        // if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cobra_register'])) {
        //     list($errors, $success) = $this->process_registration();
        // }

        // Get enabled fields
        $fields = $this->get_form_fields();
        $is_feature_authGoogle_enabled = $this->feature->is_feature_active('authgoogle');
        // Load and return the form template
        ob_start();
        $form_action = admin_url('admin-post.php');
        include $this->feature->get_path() . 'views/forms/register.php';
        unset($_SESSION['cobra_form_data_register']);
        return ob_get_clean();
    }

    /**
     * Forgot password form shortcode
     */
    public function forgot_password_form($atts = []): string
    {
        // If user is logged in, show message
        if (is_user_logged_in()) {
            if (current_user_can('edit_users')) {
                return sprintf(
                    '<div class="cobra-message info">%s</div>',
                    esc_html__('You are logged in as an administrator and can view this form for testing purposes.', 'cobra-ai')
                );
            } else {
                return sprintf(
                    '<div class="cobra-message info">%s <a href="%s">%s</a></div>',
                    esc_html__('You are already logged in.', 'cobra-ai'),
                    esc_url($this->get_page_url('account')),
                    esc_html__('View your account', 'cobra-ai')
                );
            }
        }

        $errors = new \WP_Error();
        $success = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cobra_forgot_password'])) {
            list($errors, $success) = $this->process_forgot_password();
        }

        ob_start();
        include $this->feature->get_path() . 'views/forms/forgot-password.php';
        return ob_get_clean();
    }


    /**
     * Reset password form shortcode
     */
    public function reset_password_form($atts = []): string
    {
        // if (is_user_logged_in()) {
        //     wp_redirect($this->get_page_url('account'));
        //     exit;
        // }



        $errors = new \WP_Error();
        $success = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cobra_reset_password'])) {
            // Verify reset key
            $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
            $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

            if (!$user_id || !$key || !$this->verify_reset_key($user_id, $key)) {
                return '<p class="error">' . __('Invalid or expired password reset link.', 'cobra-ai') . '</p>';
            }
            list($errors, $success) = $this->process_reset_password($user_id, $key);
        }

        ob_start();
        include $this->feature->get_path() . 'views/forms/reset-password.php';
        return ob_get_clean();
    }

    /**
     * Account form shortcode
     */
    public function account_form($atts = []): string
    {
        // If user is not logged in, show login message
        if (!is_user_logged_in()) {
            return sprintf(
                '<div class="cobra-message info">%s <a href="%s" class="cobra-login-link">%s</a></div>',
                esc_html__('You need to be logged in to access your account dashboard.', 'cobra-ai'),
                esc_url($this->get_page_url('login')),
                esc_html__('Sign in now', 'cobra-ai')
            );
        }

        $user = wp_get_current_user();
        $errors = new \WP_Error();
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cobra_update_account'])) {
            list($errors, $success) = $this->process_account_update($user->ID);
        }

        // change_password
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'] ?? '';

            $user = wp_get_current_user();
            $user_id = $user->ID;
            $user = get_user_by('id', $user_id);
            if (!$user || !wp_check_password($current_password, $user->user_pass, $user_id)) {
                $errors->add('invalid_password', __('Current password is incorrect.', 'cobra-ai'));
            }
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($new_password)) {
                $errors->add('empty_password', __('Please enter your new password.', 'cobra-ai'));
            } elseif ($new_password !== $confirm_password) {
                $errors->add('password_mismatch', __('Passwords do not match.', 'cobra-ai'));
            } else {
                wp_set_password($new_password, $user->ID);
                $success = true;
            }
        }

        // Get enabled fields
        $fields = $this->get_form_fields();

        ob_start();
        include $this->feature->get_path() . 'views/forms/account.php';
        return ob_get_clean();
    }

    /**
     * Logout link shortcode
     */
    public function logout_link($atts = []): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $atts = shortcode_atts([
            'text' => __('Logout', 'cobra-ai'),
            'class' => 'cobra-logout-link'
        ], $atts);

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            wp_logout_url($this->get_page_url('login')),
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );
    }

    /**
     * Confirm registration shortcode
     */
    public function confirm_registration($atts = []): string
    {
        $message = '';

        if (isset($_GET['verified']) && $_GET['verified'] === '1') {
            $message = '<div class="cobra-message success">' .
                __('Your email has been verified successfully. You can now login.', 'cobra-ai') .
                '</div>';
        }

        return $message;
    }



    /**
     * Process forgot password form
     */
    private function process_forgot_password(): array
    {
        $errors = new \WP_Error();

        // Verify nonce
        if (!check_ajax_referer('cobra_forgot_password', '_wpnonce', false)) {
            $errors->add('invalid_nonce', __('Invalid request.', 'cobra-ai'));
            return [$errors, false];
        }

        // Verify reCAPTCHA if enabled
        if ($this->should_verify_recaptcha()) {
            $recaptcha_response = $this->verify_recaptcha('forgot_password');
            if (is_wp_error($recaptcha_response)) {
                return [$recaptcha_response, false];
            }
        }

        $user_login = sanitize_text_field($_POST['user_login']);

        if (empty($user_login)) {
            $errors->add('empty_username', __('Please enter your email address or username.', 'cobra-ai'));
            return [$errors, false];
        }

        if (is_email($user_login)) {
            $user = get_user_by('email', $user_login);
        } else {
            $user = get_user_by('login', $user_login);
        }

        if (!$user) {
            $errors->add('invalid_email', __('There is no user registered with that email address.', 'cobra-ai'));
            return [$errors, false];
        }

        // Generate reset key and send email
        $key = wp_generate_password(20, false);
        update_user_meta($user->ID, '_password_reset_key', $key);
        update_user_meta($user->ID, '_password_reset_expiry', time() + (24 * HOUR_IN_SECONDS));

        $email_handler = new EmailHandler($this->feature);
        if (!$email_handler->send_password_reset_email($user->ID, $key)) {
            $errors->add('email_failed', __('Unable to send password reset email.', 'cobra-ai'));
            return [$errors, false];
        }

        return [new \WP_Error(), true];
    }

    /**
     * Process reset password form
     */
    private function process_reset_password(int $user_id, string $key): array
    {
        $errors = new \WP_Error();

        // Verify nonce
        if (!check_ajax_referer('cobra_reset_password', '_wpnonce', false)) {
            $errors->add('invalid_nonce', __('Invalid request.', 'cobra-ai'));
            return [$errors, false];
        }

        // Verify reCAPTCHA if enabled
        if ($this->should_verify_recaptcha()) {
            $recaptcha_response = $this->verify_recaptcha('reset_password');
            if (is_wp_error($recaptcha_response)) {
                return [$recaptcha_response, false];
            }
        }

        // Verify passwords match
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password)) {
            $errors->add('empty_password', __('Please enter your new password.', 'cobra-ai'));
            return [$errors, false];
        }

        if ($password !== $confirm_password) {
            $errors->add('password_mismatch', __('Passwords do not match.', 'cobra-ai'));
            return [$errors, false];
        }

        // Update password
        reset_password(get_user_by('id', $user_id), $password);

        // Clear reset key
        delete_user_meta($user_id, '_password_reset_key');
        delete_user_meta($user_id, '_password_reset_expiry');

        return [new \WP_Error(), true];
    }

    /**
     * Process account update
     */
    private function process_account_update(int $user_id): array
    {
        $errors = new \WP_Error();

        // Verify nonce
        if (!check_ajax_referer('cobra_update_profile', '_profile_nonce', false)) {
            $errors->add('invalid_nonce', __('Invalid request.', 'cobra-ai'));
            return [$errors, false];
        }

        try {
            $user_data = [];
            $fields = $this->get_form_fields();

            foreach ($fields as $field => $config) {
                if ($config['enabled'] && isset($_POST[$field])) {
                    $user_data[$field] = sanitize_text_field($_POST[$field]);
                }
            }

            // Update user data
            $user_data['ID'] = $user_id;
            wp_update_user($user_data);

            // Update custom fields
            foreach ($user_data as $key => $value) {
                if (!in_array($key, ['ID', 'user_login', 'user_email', 'user_pass'])) {
                    update_user_meta($user_id, $key, $value);
                }
            }

            return [new \WP_Error(), true];
        } catch (\Exception $e) {
            $errors->add('update_failed', $e->getMessage());
            return [$errors, false];
        }
    }

    /**
     * Get registration form data
     */
    private function get_registration_data(): array
    {
        $data = [];
        $fields = $this->get_form_fields();

        foreach ($fields as $field => $config) {
            if ($config['enabled']) {
                $data[$field] = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            }
        }

        return $data;
    }

    /**
     * Get form fields
     */
    private function get_form_fields(): array
    {
        $settings = $this->feature->get_settings();
        return $settings['fields'] ?? [];
    }

    /**
     * Get page URL
     */
    private function get_page_url(string $page): string
    {
        $settings = $this->feature->get_settings();
        $page_id = $settings['pages'][$page] ?? 0;

        return $page_id ? get_permalink($page_id) : home_url();
    }

    /**
     * Get redirect URL
     */
    private function get_redirect_url(string $type): string
    {
        $settings = $this->feature->get_settings();
        $url = $settings['redirects'][$type] ?? '';

        return !empty($url) ? $url : home_url();
    }

    /**
     * Should verify reCAPTCHA
     */
    private function should_verify_recaptcha(): bool
    {
        $settings = $this->feature->get_settings();
        return !empty($settings['general']['use_recaptcha']);
    }

    /**
     * Verify reCAPTCHA
     */
    private function verify_recaptcha(string $action): ?\WP_Error
    {
        if (!$this->should_verify_recaptcha()) {
            return null;
        }
        global $cobra_ai;
        $recaptcha = $cobra_ai->get_feature('recaptcha');
        if (!$recaptcha || !$recaptcha->is_ready()) {
            return null;
        }

        $response = $_POST['g-recaptcha-response'] ?? '';
        if (!$response) {
            return new \WP_Error(
                'recaptcha_required',
                __('Please complete the reCAPTCHA verification.', 'cobra-ai')
            );
        }

        try {

            $result = $recaptcha->verify_response($response, $action);
            if (!$result) {
                return new \WP_Error(
                    'recaptcha_failed',
                    __('reCAPTCHA verification failed. Please try again.', 'cobra-ai')
                );
            }
        } catch (\Exception $e) {

            return new \WP_Error(
                'recaptcha_error',
                $e->getMessage()
            );
        }

        return null;
    }
    /**
     * Render reCAPTCHA
     */
    protected function render_recaptcha(string $action): void
    {
        if (!$this->is_recaptcha_enabled()) {
            return;
        }

        try {
            // Get reCAPTCHA feature
            global $cobra_ai;
            $recaptcha = $cobra_ai->get_feature('recaptcha');
            if ($recaptcha && method_exists($recaptcha, 'render_recaptcha')) {
                $recaptcha->render_recaptcha($action);
            }
        } catch (\Exception $e) {
            error_log('reCAPTCHA render error: ' . $e->getMessage());
        }
    }
    /**
     * Verify reset key
     */
    private function verify_reset_key(int $user_id, string $key): bool
    {
        $stored_key = get_user_meta($user_id, '_password_reset_key', true);
        $expiry = get_user_meta($user_id, '_password_reset_expiry', true);

        if (empty($stored_key) || empty($expiry)) {
            return false;
        }

        if ($expiry < time()) {
            delete_user_meta($user_id, '_password_reset_key');
            delete_user_meta($user_id, '_password_reset_expiry');
            return false;
        }

        return hash_equals($stored_key, $key);
    }



    /**
     * Get success message HTML
     */
    private function get_success_message(string $message): string
    {
        return sprintf(
            '<div class="cobra-message success"><p>%s</p></div>',
            esc_html($message)
        );
    }

    /**
     * Add reCAPTCHA if enabled
     */
    private function add_recaptcha(string $form_type): string
    {
        if (!$this->should_verify_recaptcha()) {
            return '';
        }
        global $cobra_ai;
        $recaptcha = $cobra_ai->get_feature('recaptcha');
        if (!$recaptcha || !$recaptcha->is_ready()) {
            return '';
        }

        ob_start();
        $recaptcha->render($form_type);
        return ob_get_clean();
    }

    /**
     * Add field HTML
     */
    private function add_field(array $field): string
    {
        ob_start();
?>
        <div class="cobra-form-field">
            <label for="<?php echo esc_attr($field['id']); ?>">
                <?php echo esc_html($field['label']); ?>
                <?php if (!empty($field['required'])): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>

            <?php if ($field['type'] === 'textarea'): ?>
                <textarea id="<?php echo esc_attr($field['id']); ?>"
                    name="<?php echo esc_attr($field['name']); ?>"
                    class="<?php echo esc_attr($field['class'] ?? ''); ?>"
                    <?php echo !empty($field['required']) ? 'required' : ''; ?>
                    <?php echo !empty($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : ''; ?>><?php echo esc_textarea($field['value'] ?? ''); ?></textarea>

            <?php elseif ($field['type'] === 'select'): ?>
                <select id="<?php echo esc_attr($field['id']); ?>"
                    name="<?php echo esc_attr($field['name']); ?>"
                    class="<?php echo esc_attr($field['class'] ?? ''); ?>"
                    <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                    <?php foreach ($field['options'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"
                            <?php selected($field['value'] ?? '', $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <?php else: ?>
                <input type="<?php echo esc_attr($field['type']); ?>"
                    id="<?php echo esc_attr($field['id']); ?>"
                    name="<?php echo esc_attr($field['name']); ?>"
                    value="<?php echo esc_attr($field['value'] ?? ''); ?>"
                    class="<?php echo esc_attr($field['class'] ?? ''); ?>"
                    <?php echo !empty($field['required']) ? 'required' : ''; ?>
                    <?php echo !empty($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : ''; ?>
                    <?php echo !empty($field['pattern']) ? 'pattern="' . esc_attr($field['pattern']) . '"' : ''; ?>>
            <?php endif; ?>

            <?php if (!empty($field['description'])): ?>
                <p class="description"><?php echo esc_html($field['description']); ?></p>
            <?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Get form button HTML
     */
    private function get_form_button(string $text, string $name, string $class = ''): string
    {
        return sprintf(
            '<button type="submit" name="%s" class="button %s">%s</button>',
            esc_attr($name),
            esc_attr($class),
            esc_html($text)
        );
    }

    /**
     * Get form links HTML
     */
    private function get_form_links(array $links): string
    {
        $output = '<div class="cobra-form-links">';
        foreach ($links as $link) {
            $output .= sprintf(
                '<a href="%s" class="%s">%s</a>',
                esc_url($link['url']),
                esc_attr($link['class'] ?? ''),
                esc_html($link['text'])
            );
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Get countries list
     */
    protected function get_countries(): array
    {
        return [
            'AF' => __('Afghanistan', 'cobra-ai'),
            'AX' => __('Åland Islands', 'cobra-ai'),
            'AL' => __('Albania', 'cobra-ai'),
            'DZ' => __('Algeria', 'cobra-ai'),
            'AS' => __('American Samoa', 'cobra-ai'),
            'AD' => __('Andorra', 'cobra-ai'),
            'AO' => __('Angola', 'cobra-ai'),
            'AI' => __('Anguilla', 'cobra-ai'),
            'AQ' => __('Antarctica', 'cobra-ai'),
            'AG' => __('Antigua and Barbuda', 'cobra-ai'),
            'AR' => __('Argentina', 'cobra-ai'),
            'AM' => __('Armenia', 'cobra-ai'),
            'AW' => __('Aruba', 'cobra-ai'),
            'AU' => __('Australia', 'cobra-ai'),
            'AT' => __('Austria', 'cobra-ai'),
            'AZ' => __('Azerbaijan', 'cobra-ai'),
            'BS' => __('Bahamas', 'cobra-ai'),
            'BH' => __('Bahrain', 'cobra-ai'),
            'BD' => __('Bangladesh', 'cobra-ai'),
            'BB' => __('Barbados', 'cobra-ai'),
            'BY' => __('Belarus', 'cobra-ai'),
            'BE' => __('Belgium', 'cobra-ai'),
            'BZ' => __('Belize', 'cobra-ai'),
            'BJ' => __('Benin', 'cobra-ai'),
            'BM' => __('Bermuda', 'cobra-ai'),
            'BT' => __('Bhutan', 'cobra-ai'),
            'BO' => __('Bolivia', 'cobra-ai'),
            'BA' => __('Bosnia and Herzegovina', 'cobra-ai'),
            'BW' => __('Botswana', 'cobra-ai'),
            'BV' => __('Bouvet Island', 'cobra-ai'),
            'BR' => __('Brazil', 'cobra-ai'),
            'IO' => __('British Indian Ocean Territory', 'cobra-ai'),
            'BN' => __('Brunei Darussalam', 'cobra-ai'),
            'BG' => __('Bulgaria', 'cobra-ai'),
            'BF' => __('Burkina Faso', 'cobra-ai'),
            'BI' => __('Burundi', 'cobra-ai'),
            'KH' => __('Cambodia', 'cobra-ai'),
            'CM' => __('Cameroon', 'cobra-ai'),
            'CA' => __('Canada', 'cobra-ai'),
            'CV' => __('Cape Verde', 'cobra-ai'),
            'KY' => __('Cayman Islands', 'cobra-ai'),
            'CF' => __('Central African Republic', 'cobra-ai'),
            'TD' => __('Chad', 'cobra-ai'),
            'CL' => __('Chile', 'cobra-ai'),
            'CN' => __('China', 'cobra-ai'),
            'CX' => __('Christmas Island', 'cobra-ai'),
            'CC' => __('Cocos (Keeling) Islands', 'cobra-ai'),
            'CO' => __('Colombia', 'cobra-ai'),
            'KM' => __('Comoros', 'cobra-ai'),
            'CG' => __('Congo', 'cobra-ai'),
            'CD' => __('Congo, Democratic Republic', 'cobra-ai'),
            'CK' => __('Cook Islands', 'cobra-ai'),
            'CR' => __('Costa Rica', 'cobra-ai'),
            'CI' => __('Cote D\'Ivoire', 'cobra-ai'),
            'HR' => __('Croatia', 'cobra-ai'),
            'CU' => __('Cuba', 'cobra-ai'),
            'CY' => __('Cyprus', 'cobra-ai'),
            'CZ' => __('Czech Republic', 'cobra-ai'),
            'DK' => __('Denmark', 'cobra-ai'),
            'DJ' => __('Djibouti', 'cobra-ai'),
            'DM' => __('Dominica', 'cobra-ai'),
            'DO' => __('Dominican Republic', 'cobra-ai'),
            'EC' => __('Ecuador', 'cobra-ai'),
            'EG' => __('Egypt', 'cobra-ai'),
            'SV' => __('El Salvador', 'cobra-ai'),
            'GQ' => __('Equatorial Guinea', 'cobra-ai'),
            'ER' => __('Eritrea', 'cobra-ai'),
            'EE' => __('Estonia', 'cobra-ai'),
            'ET' => __('Ethiopia', 'cobra-ai'),
            'FK' => __('Falkland Islands', 'cobra-ai'),
            'FO' => __('Faroe Islands', 'cobra-ai'),
            'FJ' => __('Fiji', 'cobra-ai'),
            'FI' => __('Finland', 'cobra-ai'),
            'FR' => __('France', 'cobra-ai'),
            'GF' => __('French Guiana', 'cobra-ai'),
            'PF' => __('French Polynesia', 'cobra-ai'),
            'TF' => __('French Southern Territories', 'cobra-ai'),
            'GA' => __('Gabon', 'cobra-ai'),
            'GM' => __('Gambia', 'cobra-ai'),
            'GE' => __('Georgia', 'cobra-ai'),
            'DE' => __('Germany', 'cobra-ai'),
            'GH' => __('Ghana', 'cobra-ai'),
            'GI' => __('Gibraltar', 'cobra-ai'),
            'GR' => __('Greece', 'cobra-ai'),
            'GL' => __('Greenland', 'cobra-ai'),
            'GD' => __('Grenada', 'cobra-ai'),
            'GP' => __('Guadeloupe', 'cobra-ai'),
            'GU' => __('Guam', 'cobra-ai'),
            'GT' => __('Guatemala', 'cobra-ai'),
            'GG' => __('Guernsey', 'cobra-ai'),
            'GN' => __('Guinea', 'cobra-ai'),
            'GW' => __('Guinea-Bissau', 'cobra-ai'),
            'GY' => __('Guyana', 'cobra-ai'),
            'HT' => __('Haiti', 'cobra-ai'),
            'HM' => __('Heard Island & Mcdonald Islands', 'cobra-ai'),
            'VA' => __('Holy See (Vatican City State)', 'cobra-ai'),
            'HN' => __('Honduras', 'cobra-ai'),
            'HK' => __('Hong Kong', 'cobra-ai'),
            'HU' => __('Hungary', 'cobra-ai'),
            'IS' => __('Iceland', 'cobra-ai'),
            'IN' => __('India', 'cobra-ai'),
            'ID' => __('Indonesia', 'cobra-ai'),
            'IR' => __('Iran', 'cobra-ai'),
            'IQ' => __('Iraq', 'cobra-ai'),
            'IE' => __('Ireland', 'cobra-ai'),
            'IM' => __('Isle Of Man', 'cobra-ai'),
            'IL' => __('Israel', 'cobra-ai'),
            'IT' => __('Italy', 'cobra-ai'),
            'JM' => __('Jamaica', 'cobra-ai'),
            'JP' => __('Japan', 'cobra-ai'),
            'JE' => __('Jersey', 'cobra-ai'),
            'JO' => __('Jordan', 'cobra-ai'),
            'KZ' => __('Kazakhstan', 'cobra-ai'),
            'KE' => __('Kenya', 'cobra-ai'),
            'KI' => __('Kiribati', 'cobra-ai'),
            'KR' => __('Korea', 'cobra-ai'),
            'KW' => __('Kuwait', 'cobra-ai'),
            'KG' => __('Kyrgyzstan', 'cobra-ai'),
            'LA' => __('Lao People\'s Democratic Republic', 'cobra-ai'),
            'LV' => __('Latvia', 'cobra-ai'),
            'LB' => __('Lebanon', 'cobra-ai'),
            'LS' => __('Lesotho', 'cobra-ai'),
            'LR' => __('Liberia', 'cobra-ai'),
            'LY' => __('Libyan Arab Jamahiriya', 'cobra-ai'),
            'LI' => __('Liechtenstein', 'cobra-ai'),
            'LT' => __('Lithuania', 'cobra-ai'),
            'LU' => __('Luxembourg', 'cobra-ai'),
            'MO' => __('Macao', 'cobra-ai'),
            'MK' => __('Macedonia', 'cobra-ai'),
            'MG' => __('Madagascar', 'cobra-ai'),
            'MW' => __('Malawi', 'cobra-ai'),
            'MY' => __('Malaysia', 'cobra-ai'),
            'MV' => __('Maldives', 'cobra-ai'),
            'ML' => __('Mali', 'cobra-ai'),
            'MT' => __('Malta', 'cobra-ai'),
            'MH' => __('Marshall Islands', 'cobra-ai'),
            'MQ' => __('Martinique', 'cobra-ai'),
            'MR' => __('Mauritania', 'cobra-ai'),
            'MU' => __('Mauritius', 'cobra-ai'),
            'YT' => __('Mayotte', 'cobra-ai'),
            'MX' => __('Mexico', 'cobra-ai'),
            'FM' => __('Micronesia, Federated States Of', 'cobra-ai'),
            'MD' => __('Moldova', 'cobra-ai'),
            'MC' => __('Monaco', 'cobra-ai'),
            'MN' => __('Mongolia', 'cobra-ai'),
            'ME' => __('Montenegro', 'cobra-ai'),
            'MS' => __('Montserrat', 'cobra-ai'),
            'MA' => __('Morocco', 'cobra-ai'),
            'MZ' => __('Mozambique', 'cobra-ai'),
            'MM' => __('Myanmar', 'cobra-ai'),
            'NA' => __('Namibia', 'cobra-ai'),
            'NR' => __('Nauru', 'cobra-ai'),
            'NP' => __('Nepal', 'cobra-ai'),
            'NL' => __('Netherlands', 'cobra-ai'),
            'AN' => __('Netherlands Antilles', 'cobra-ai'),
            'NC' => __('New Caledonia', 'cobra-ai'),
            'NZ' => __('New Zealand', 'cobra-ai'),
            'NI' => __('Nicaragua', 'cobra-ai'),
            'NE' => __('Niger', 'cobra-ai'),
            'NG' => __('Nigeria', 'cobra-ai'),
            'NU' => __('Niue', 'cobra-ai'),
            'NF' => __('Norfolk Island', 'cobra-ai'),
            'MP' => __('Northern Mariana Islands', 'cobra-ai'),
            'NO' => __('Norway', 'cobra-ai'),
            'OM' => __('Oman', 'cobra-ai'),
            'PK' => __('Pakistan', 'cobra-ai'),
            'PW' => __('Palau', 'cobra-ai'),
            'PS' => __('Palestine', 'cobra-ai'),
            'PA' => __('Panama', 'cobra-ai'),
            'PG' => __('Papua New Guinea', 'cobra-ai'),
            'PY' => __('Paraguay', 'cobra-ai'),
            'PE' => __('Peru', 'cobra-ai'),
            'PH' => __('Philippines', 'cobra-ai'),
            'PN' => __('Pitcairn', 'cobra-ai'),
            'PL' => __('Poland', 'cobra-ai'),
            'PT' => __('Portugal', 'cobra-ai'),
            'PR' => __('Puerto Rico', 'cobra-ai'),
            'QA' => __('Qatar', 'cobra-ai'),
            'RE' => __('Reunion', 'cobra-ai'),
            'RO' => __('Romania', 'cobra-ai'),
            'RU' => __('Russian Federation', 'cobra-ai'),
            'RW' => __('Rwanda', 'cobra-ai'),
            'BL' => __('Saint Barthelemy', 'cobra-ai'),
            'SH' => __('Saint Helena', 'cobra-ai'),
            'KN' => __('Saint Kitts And Nevis', 'cobra-ai'),
            'LC' => __('Saint Lucia', 'cobra-ai'),
            'MF' => __('Saint Martin', 'cobra-ai'),
            'PM' => __('Saint Pierre And Miquelon', 'cobra-ai'),
            'VC' => __('Saint Vincent And Grenadines', 'cobra-ai'),
            'WS' => __('Samoa', 'cobra-ai'),
            'SM' => __('San Marino', 'cobra-ai'),
            'ST' => __('Sao Tome And Principe', 'cobra-ai'),
            'SA' => __('Saudi Arabia', 'cobra-ai'),
            'SN' => __('Senegal', 'cobra-ai'),
            'RS' => __('Serbia', 'cobra-ai'),
            'SC' => __('Seychelles', 'cobra-ai'),
            'SL' => __('Sierra Leone', 'cobra-ai'),
            'SG' => __('Singapore', 'cobra-ai'),
            'SK' => __('Slovakia', 'cobra-ai'),
            'SI' => __('Slovenia', 'cobra-ai'),
            'SB' => __('Solomon Islands', 'cobra-ai'),
            'SO' => __('Somalia', 'cobra-ai'),
            'ZA' => __('South Africa', 'cobra-ai'),
            'GS' => __('South Georgia And Sandwich Isl.', 'cobra-ai'),
            'ES' => __('Spain', 'cobra-ai'),
            'LK' => __('Sri Lanka', 'cobra-ai'),
            'SD' => __('Sudan', 'cobra-ai'),
            'SR' => __('Suriname', 'cobra-ai'),
            'SJ' => __('Svalbard And Jan Mayen', 'cobra-ai'),
            'SZ' => __('Swaziland', 'cobra-ai'),
            'SE' => __('Sweden', 'cobra-ai'),
            'CH' => __('Switzerland', 'cobra-ai'),
            'SY' => __('Syrian Arab Republic', 'cobra-ai'),
            'TW' => __('Taiwan', 'cobra-ai'),
            'TJ' => __('Tajikistan', 'cobra-ai'),
            'TZ' => __('Tanzania', 'cobra-ai'),
            'TH' => __('Thailand', 'cobra-ai'),
            'TL' => __('Timor-Leste', 'cobra-ai'),
            'TG' => __('Togo', 'cobra-ai'),
            'TK' => __('Tokelau', 'cobra-ai'),
            'TO' => __('Tonga', 'cobra-ai'),
            'TT' => __('Trinidad And Tobago', 'cobra-ai'),
            'TN' => __('Tunisia', 'cobra-ai'),
            'TR' => __('Turkey', 'cobra-ai'),
            'TM' => __('Turkmenistan', 'cobra-ai'),
            'TC' => __('Turks And Caicos Islands', 'cobra-ai'),
            'TV' => __('Tuvalu', 'cobra-ai'),
            'UG' => __('Uganda', 'cobra-ai'),
            'UA' => __('Ukraine', 'cobra-ai'),
            'AE' => __('United Arab Emirates', 'cobra-ai'),
        ];
    }

    /**
     * is recaptcha enabled
     */

    public function is_recaptcha_enabled(): bool
    {
        $settings = $this->feature->get_settings();
        // Check if reCAPTCHA is enabled in settings
        if (empty($settings['general']['use_recaptcha'])) {
            return false;
        }

        // Get reCAPTCHA feature
        global $cobra_ai;
        $recaptcha = $cobra_ai->get_feature('recaptcha');

        // Check if reCAPTCHA feature is available and ready
        return $recaptcha && $recaptcha->is_ready();
    }



    /**
     * Add an error message to the session
     *
     * @param string|array|\WP_Error $message Error message(s) to store
     * @param string $type Optional error type/key
     * @return void
     */
    private function set_error_message($message, string $type = 'general'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!isset($_SESSION['cobra_login_errors'])) {
            $_SESSION['cobra_login_errors'] = [];
        }

        // Handle different types of error inputs
        if ($message instanceof \WP_Error) {
            foreach ($message->get_error_codes() as $code) {
                $error_messages = $message->get_error_messages($code);
                foreach ($error_messages as $error_message) {
                    $_SESSION['cobra_login_errors'][$code][] = $error_message;
                }
            }
        } elseif (is_array($message)) {
            foreach ($message as $key => $msg) {
                $error_key = is_string($key) ? $key : $type;
                $_SESSION['cobra_login_errors'][$error_key][] = $msg;
            }
        } else {
            $_SESSION['cobra_login_errors'][$type][] = $message;
        }
    }

    /**
     * Get and clear stored error messages
     *
     * @param string|null $type Specific error type to retrieve (null for all)
     * @return array
     */
    private function get_error_messages(?string $type = null): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['cobra_login_errors'])) {
            return [];
        }

        $errors = $_SESSION['cobra_login_errors'];
        unset($_SESSION['cobra_login_errors']);

        if ($type !== null) {
            return $errors[$type] ?? [];
        }

        // Flatten array if getting all errors
        $all_errors = [];
        foreach ($errors as $error_group) {
            if (is_array($error_group)) {
                $all_errors = array_merge($all_errors, $error_group);
            }
        }

        return $all_errors;
    }

    /**
     * Check if there are any error messages
     *
     * @param string|null $type Specific error type to check
     * @return bool
     */
    private function has_errors(?string $type = null): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['cobra_login_errors'])) {
            return false;
        }

        if ($type !== null) {
            return !empty($_SESSION['cobra_login_errors'][$type]);
        }

        return !empty($_SESSION['cobra_login_errors']);
    }

    /**
     * Render error messages
     *
     * @param string|null $type Specific error type to render
     * @return string
     */

    private function render_error_messages(?string $type = null): string
    {
        $errors = $this->get_error_messages($type);

        if (empty($errors)) {
            return '';
        }

        $output = '<div class="cobra-message error">';
        if (count($errors) === 1) {
            // Single error message
            $output .= sprintf('<div class="error-message">%s</div>', $errors[0]);
        } else {
            // Multiple error messages
            $output .= '<ul class="error-list">';
            foreach ($errors as $error) {
                $output .= sprintf('<li>%s</li>', $error);
            }
            $output .= '</ul>';
        }
        $output .= '</div>';

        return wp_kses_post($output);
    }

    /**
     * Redirect back to login page
     */
    private function redirect_back(): void
    {
        // wp_safe_redirect(wp_get_referer() ?: home_url());
        // exit;
        $referer = wp_get_referer() ?: home_url();

        // Determine form type from POST data
        $form_type = isset($_POST['cobra_login']) ? 'login' : (isset($_POST['cobra_register']) ? 'register' : '');

        if ($form_type && !empty($_POST)) {
            // Store form data before redirect
            $this->store_form_data($form_type, $_POST);
        }

        wp_safe_redirect($referer);
        exit;
    }
    private function store_form_data(string $form_type, array $data): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Remove sensitive data
            unset($data['password']);
            unset($data['_wpnonce']);
            unset($data['_wp_http_referer']);

            $_SESSION['cobra_form_data_' . $form_type] = $data;
        }
    }
    private function get_field_value(string $field, string $form_type): string
    {
        $stored_data = $this->get_stored_form_data($form_type);
        return esc_attr($stored_data[$field] ?? '');
    }
    private function get_stored_form_data(string $form_type): array
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['cobra_form_data_' . $form_type])) {
            $data = $_SESSION['cobra_form_data_' . $form_type];
            // unset($_SESSION['cobra_form_data_' . $form_type]);
            return $data;
        }
        return [];
    }
    /**
     * Redirect after successful login
     */
    private function redirect_after_login(): void
    {
        wp_safe_redirect($this->get_redirect_url('after_login'));
        exit;
    }

    /**
     * Get logged in message
     */
    private function get_logged_in_message(): string
    {
        if (current_user_can('edit_users')) {
            return sprintf(
                '<div class="cobra-message info">%s</div>',
                esc_html__('You are logged in as an administrator and can view this form for testing purposes.', 'cobra-ai')
            );
        }

        return sprintf(
            '<div class="cobra-message info">%s <a href="%s">%s</a></div>',
            esc_html__('You are already logged in.', 'cobra-ai'),
            esc_url($this->get_page_url('account')),
            esc_html__('View your account', 'cobra-ai')
        );
    }



    private function is_user_logged_in(): bool
    {
        return is_user_logged_in();
    }
}
