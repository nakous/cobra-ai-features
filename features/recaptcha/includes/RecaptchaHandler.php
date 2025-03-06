<?php

namespace CobraAI\Features\Recaptcha\Includes;

defined('ABSPATH') || exit;

/**
 * Handles reCAPTCHA form integration and verification
 */
class RecaptchaHandler {
    
    /**
     * Parent feature instance
     */
    private $feature;

    /**
     * reCAPTCHA verifier instance
     */
    private $verifier;

    /**
     * Constructor
     */
    public function __construct($feature) {
        $this->feature = $feature;
        $this->verifier = new RecaptchaVerifier($feature);
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // WordPress core forms
        add_filter('authenticate', [$this, 'verify_login'], 99, 3);
        add_filter('registration_errors', [$this, 'verify_registration'], 99, 3);
        add_action('lostpassword_post', [$this, 'verify_lost_password']);
        add_filter('preprocess_comment', [$this, 'verify_comment']);
        add_filter('pre_update_option_pwd_protected_pwd', [$this, 'verify_protected_post'], 99, 2);

        // Contact Form 7 integration
        add_filter('wpcf7_validate', [$this, 'verify_contact_form_7'], 99, 2);

        // Custom forms integration
        add_action('wp_ajax_verify_recaptcha', [$this, 'ajax_verify_recaptcha']);
        add_action('wp_ajax_nopriv_verify_recaptcha', [$this, 'ajax_verify_recaptcha']);
    }

    /**
     * Verify reCAPTCHA response
     */
    public function verify_response(?string $response = null, string $action = ''): bool {
        // try {
            // Check if IP is allowlisted
            if ($this->is_ip_allowlisted()) {
                return true;
            }

            // Get response from POST if not provided
      
            if ($response === null) {
                $response = $_POST['g-recaptcha-response'] ?? '';
            }

            // Verify the response
            return $this->verifier->verify_response($response, $action);
 
        // } catch (\Exception $e) {
        //     $this->log_error('reCAPTCHA verification failed', [
        //         'error' => $e->getMessage(),
        //         'action' => $action,
        //         'ip' => $this->get_client_ip()
        //     ]);
        //     return false;
        // }
    }

    /**
     * Verify login form
     */
    public function verify_login($user, string $username, string $password) {
        // Skip if not enabled for login form
        if (!$this->is_enabled_for('login')) {
            return $user;
        }

        // Skip if already authenticated
        if ($user instanceof \WP_User) {
            return $user;
        }
 
        // if (!$this->verify_response(null, 'login')) {
        //     return new \WP_Error(
        //         'recaptcha_error',
        //         $this->get_error_message('invalid_input_response')
        //     );
        // }

        return $user;
    }

    /**
     * Verify registration form
     */
    public function verify_registration($errors, string $sanitized_user_login, string $user_email) {
        if (!$this->is_enabled_for('register')) {
            return $errors;
        }

        if (!$this->verify_response(null, 'register')) {
            $errors->add(
                'recaptcha_error',
                $this->get_error_message('invalid_input_response')
            );
        }

        return $errors;
    }

    /**
     * Verify lost password form
     */
    public function verify_lost_password($errors) {
        if (!$this->is_enabled_for('lostpassword')) {
            return;
        }

        if (!$this->verify_response(null, 'lostpassword')) {
            if (!is_wp_error($errors)) {
                $errors = new \WP_Error();
            }
            $errors->add(
                'recaptcha_error',
                $this->get_error_message('invalid_input_response')
            );
            wp_die($errors);
        }
    }

    /**
     * Verify comment form
     */
    public function verify_comment($commentdata) {
        if (!$this->is_enabled_for('comments')) {
            return $commentdata;
        }

        // Skip verification for logged-in users if configured
        if (is_user_logged_in() && !$this->should_verify_logged_in_users()) {
            return $commentdata;
        }

        if (!$this->verify_response(null, 'comment')) {
            wp_die(
                $this->get_error_message('invalid_input_response'),
                'Comment Submission Failed',
                ['response' => 403, 'back_link' => true]
            );
        }

        return $commentdata;
    }

    /**
     * Verify protected post form
     */
    public function verify_protected_post($value, $old_value) {
        if (!$this->is_enabled_for('protected_posts')) {
            return $value;
        }

        if (!$this->verify_response(null, 'protected_post')) {
            wp_die(
                $this->get_error_message('invalid_input_response'),
                'Access Denied',
                ['response' => 403, 'back_link' => true]
            );
        }

        return $value;
    }

    /**
     * Verify Contact Form 7 submission
     */
    public function verify_contact_form_7($result, $tags) {
        if (!$this->is_enabled_for('contact_form')) {
            return $result;
        }

        if (!$this->verify_response(null, 'contact_form')) {
            $result->invalidate(
                'recaptcha',
                $this->get_error_message('invalid_input_response')
            );
        }

        return $result;
    }

    /**
     * Handle AJAX verification
     */
    public function ajax_verify_recaptcha() {
        try {
            // Verify nonce
            if (!check_ajax_referer('cobra-recaptcha-verify', 'nonce', false)) {
                throw new \Exception('Invalid security token');
            }

            // Get response and action
            $response = sanitize_text_field($_POST['response'] ?? '');
            $action = sanitize_text_field($_POST['action_name'] ?? '');

            // Verify response
            $verified = $this->verify_response($response, $action);

            wp_send_json([
                'success' => $verified,
                'message' => $verified ? 'Verification successful' : $this->get_error_message('invalid_input_response')
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if reCAPTCHA is enabled for a specific form
     */
    private function is_enabled_for(string $form): bool {
        $settings = $this->feature->get_settings();
        return !empty($settings['enabled_forms'][$form]);
    }

    /**
     * Check if IP is allowlisted
     */
    private function is_ip_allowlisted(): bool {
        $settings = $this->feature->get_settings();
        $client_ip = $this->get_client_ip();

        return !empty($settings['allowlisted_ips']) && 
               in_array($client_ip, $settings['allowlisted_ips']);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip(): string {
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
     * Get error message
     */
    private function get_error_message(string $key): string {
        $settings = $this->feature->get_settings();
        return $settings['error_messages'][$key] ?? __('reCAPTCHA verification failed.', 'cobra-ai');
    }

    /**
     * Check if logged-in users should be verified
     */
    private function should_verify_logged_in_users(): bool {
        $settings = $this->feature->get_settings();
        return !empty($settings['verify_logged_in']);
    }

    /**
     * Log error
     */
    private function log_error(string $message, array $context = []): void {
        if (method_exists($this->feature, 'log_error')) {
            $this->feature->log_error($message, $context);
        }
    }

    /**
     * Add reCAPTCHA to a form
     */
    public function add_recaptcha(string $form_type): void {
        if (!$this->is_enabled_for($form_type) || $this->is_ip_allowlisted()) {
            return;
        }

        $settings = $this->feature->get_settings();
        $version = $settings['version'] ?? 'v2';

        if ($version === 'v2') {
            echo sprintf(
                '<div class="cobra-recaptcha" data-form="%s" data-sitekey="%s" data-theme="%s" data-size="%s"></div>',
                esc_attr($form_type),
                esc_attr($settings['site_key']),
                esc_attr($settings['theme']),
                esc_attr($settings['size'])
            );
        }

        // Add nonce field for extra security
        wp_nonce_field('cobra-recaptcha-' . $form_type, 'cobra-recaptcha-nonce');
    }

    /**
     * Test reCAPTCHA configuration
     */
    public function test_configuration(array $settings = null): array {
        try {
            if ($settings === null) {
                $settings = $this->feature->get_settings();
            }

            // Basic validation
            if (empty($settings['site_key']) || empty($settings['secret_key'])) {
                throw new \Exception('API keys are not configured.');
            }

            // Test API keys format
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $settings['site_key'])) {
                throw new \Exception('Invalid site key format.');
            }

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $settings['secret_key'])) {
                throw new \Exception('Invalid secret key format.');
            }

            return [
                'success' => true,
                'message' => 'Configuration appears valid. Please test with actual reCAPTCHA verification.'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}