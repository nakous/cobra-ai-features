<?php

namespace CobraAI\Features\Register;

use WP_Error;

class UserRegistrationHandler
{
    /**
     * Parent feature instance
     */
    private $feature;

    /**
     * Email handler instance
     */
    private $email_handler;

    /**
     * Constructor
     */
    public function __construct($feature)
    {
        $this->feature = $feature;
        $this->email_handler = new EmailHandler($feature);
    }

    /**
     * Handle user registration
     */
    public function handle_registration($user_id): void
    {
        try {
            // Get settings
            $settings = $this->feature->get_settings();

            // Set default role
            $user = get_user_by('id', $user_id);
            $user->set_role($settings['general']['default_role']);

            // Generate verification token
            $token = $this->generate_verification_token($user_id);

            // Save registration data
            update_user_meta($user_id, '_email_verified', false);
            update_user_meta($user_id, '_registration_date', current_time('mysql'));

            // Log registration
            $this->log_action($user_id, 'register', 'completed');

            // Send verification email
            $this->email_handler->send_verification_email($user_id, $token);
        } catch (\Exception $e) {
            $this->log_action($user_id, 'register', 'failed', ['error' => $e->getMessage()]);
        }
    }
    public function handle_resend_verification(): void
    {

        $user_id = $_POST['user_id'] ?? 0;
        // Validate user ID
        if (!is_numeric($user_id) || $user_id <= 0) {
            wp_send_json_error(__('Invalid user ID.', 'cobra-ai'));
            return;
        }
        try {
             
              
            // Get settings
            $settings = $this->feature->get_settings();

            // Generate verification token
            $token = $this->generate_verification_token($user_id);

            // Send verification email
            $this->email_handler->send_verification_email($user_id, $token);

            wp_send_json_success([
                'message' => __('Verification email sent successfully.', 'cobra-ai')
            ]);
        } catch (\Exception $e) {
            $this->log_action($user_id, 'resend_verification', 'failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }
    /**
     * Handle email verification
     */
    public function handle_email_verification(): void
    {
        if (
            !isset($_GET['action']) || $_GET['action'] !== 'verify_email' ||
            !isset($_GET['token']) || !isset($_GET['user_id'])
        ) {
            return;
        }

        try {
            $user_id = absint($_GET['user_id']);
            $token = sanitize_text_field($_GET['token']);

            // Verify token
            if (!$this->verify_token($user_id, $token, 'email_verify')) {
                $redirect_url = add_query_arg(
                    ['verified' => '0'],                    
                    $this->get_page_url('login')
                );
                wp_redirect($redirect_url);
                exit;
                // throw new \Exception(__('Invalid or expired verification token.', 'cobra-ai'));
            }

            // Update user status
            update_user_meta($user_id, '_email_verified', true);

            // Update user role if verification successful
            $user = get_user_by('id', $user_id);
            if ($user && $user->roles[0] === 'pending') {
                $user->set_role('subscriber');
            }

            // Log verification
            $this->log_action($user_id, 'verify', 'completed');

            // Send confirmation email
            $this->email_handler->send_confirmation_email($user_id);

            // Redirect to login page with success message
            $redirect_url = add_query_arg(
                ['verified' => '1'],
                $this->get_page_url('login')
            );
            wp_redirect($redirect_url);
            exit;
        } catch (\Exception $e) {
            $this->log_action($user_id ?? 0, 'verify', 'failed', ['error' => $e->getMessage()]);
            wp_die($e->getMessage());
        }
    }

    /**
     * Handle user logout
     */
    public function handle_logout(): void
    {
        $settings = $this->feature->get_settings();
        $redirect_url = $settings['redirects']['after_logout'] ?? home_url();
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Register a new user
     */
    public function register_user(array $data): int
    {
        // Validate required fields
        $required_fields = $this->get_required_fields();
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new \Exception(sprintf(
                    __('Field "%s" is required.', 'cobra-ai'),
                    $field
                ));
            }
        }

        // Validate email
        if (!is_email($data['email'])) {
            throw new \Exception(__('Invalid email address.', 'cobra-ai'));
        }

        // Check if email exists
        if (email_exists($data['email'])) {
            throw new \Exception(__('Email address already registered.', 'cobra-ai'));
        }

        // Validate username
        if (username_exists($data['username'])) {
            throw new \Exception(__('Username already exists.', 'cobra-ai'));
        }

        // Validate password
        if ($data['password'] !== $data['confirm_password']) {
            throw new \Exception(__('Passwords do not match.', 'cobra-ai'));
        }

        // Create user
        $user_id = wp_create_user(
            $data['username'],
            $data['password'],
            $data['email']
        );

        // update option user 
        // not Show Toolbar when viewing site fpr user
        update_user_meta($user_id, 'show_admin_bar_front', 'false');
        
        if (is_wp_error($user_id)) {
            throw new \Exception($user_id->get_error_message());
        }

        // Save additional fields
        $fields = $this->get_enabled_fields();
        foreach ($fields as $field) {
            if (isset($data[$field]) && !in_array($field, ['username', 'email', 'password', 'confirm_password'])) {
                update_user_meta($user_id, $field, sanitize_text_field($data[$field]));
            }
        }

        return $user_id;
    }

    /**
     * Approve user
     */
    public function approve_user(int $user_id): bool
    {
        try {
            $user = get_user_by('id', $user_id);
            if (!$user) {
                throw new \Exception(__('User not found.', 'cobra-ai'));
            }

            // Update user role
            $user->set_role('subscriber');

            // Log action
            $this->log_action($user_id, 'approve', 'completed');

            // Send notification email
            $this->email_handler->send_approval_email($user_id);

            return true;
        } catch (\Exception $e) {
            $this->log_action($user_id, 'approve', 'failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Reject user
     */
    public function reject_user(int $user_id): bool
    {
        try {
            $user = get_user_by('id', $user_id);
            if (!$user) {
                throw new \Exception(__('User not found.', 'cobra-ai'));
            }

            // Update user role
            $user->set_role('');

            // Log action
            $this->log_action($user_id, 'reject', 'completed');

            // Send notification email
            $this->email_handler->send_rejection_email($user_id);

            return true;
        } catch (\Exception $e) {
            $this->log_action($user_id, 'reject', 'failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Generate verification token
     */
    private function generate_verification_token(int $user_id): string
    {
        global $wpdb;

        // Generate token
        $token = wp_generate_password(32, false);

        // Save token
        $wpdb->insert(
            $this->feature->get_table_name('verification_tokens'),
            [
                'user_id' => $user_id,
                'token' => $token,
                'type' => 'email_verify',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            ],
            ['%d', '%s', '%s', '%s']
        );

        return $token;
    }

    /**
     * Verify token
     */
    private function verify_token(int $user_id, string $token, string $type): bool
    {
        global $wpdb;

        $table = $this->feature->get_table_name('verification_tokens');
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE user_id = %d 
            AND token = %s 
            AND type = %s 
            AND expires_at > NOW()",
            $user_id,
            $token,
            $type
        ));

        if ($result) {
            // Delete used token
            $wpdb->delete(
                $table,
                ['id' => $result->id],
                ['%d']
            );
            return true;
        }

        return false;
    }

    /**
     * Log action
     */
    private function log_action(int $user_id, string $action, string $status, array $data = []): void
    {
        // global $wpdb;

        // $wpdb->insert(
        //     $this->feature->get_table_name('registration_logs'),
        //     [
        //         'user_id' => $user_id,
        //         'action' => $action,
        //         'status' => $status,
        //         'data' => json_encode($data),
        //     ],
        //     ['%d', '%s', '%s', '%s']
        // );
    }

    /**
     * Get required fields
     */
    private function get_required_fields(): array
    {
        $fields = [];
        $settings = $this->feature->get_settings();

        foreach ($settings['fields'] as $field => $config) {
            if ($config['enabled'] && $config['required']) {
                $fields[] = $field;
            }
        }

        // Always include essential fields
        return array_unique(array_merge(
            $fields,
            ['username', 'email', 'password', 'confirm_password']
        ));
    }

    /**
     * Get enabled fields
     */
    private function get_enabled_fields(): array
    {
        $fields = [];
        $settings = $this->feature->get_settings();

        foreach ($settings['fields'] as $field => $config) {
            if ($config['enabled']) {
                $fields[] = $field;
            }
        }

        return $fields;
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
}
