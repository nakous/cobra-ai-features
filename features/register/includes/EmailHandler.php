<?php

namespace CobraAI\Features\Register;

class EmailHandler {
    /**
     * Parent feature instance
     */
    private $feature;

    /**
     * Constructor
     */
    public function __construct($feature) {
        $this->feature = $feature;
    }

    /**
     * Send verification email
     */
    public function send_verification_email(int $user_id, string $token): bool {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $settings = $this->feature->get_settings();
        $template = $settings['emails']['verification'] ?? '';

        // Build verification URL
        $verify_url = add_query_arg([
            'action' => 'verify_email',
            'token' => $token,
            'user_id' => $user_id
        ], home_url());

        // Prepare variables
        $variables = [
            'user_name' => $user->display_name,
            'verification_link' => '<a href="' . esc_url($verify_url) . '">' . __('Verify Email', 'cobra-ai') . '</a>',
            'verification_url' => $verify_url,
            'expiry_time' => '24 ' . __('hours', 'cobra-ai')
        ];

        // Send email
        return $this->send_email(
            $user->user_email,
            __('Verify Your Email Address', 'cobra-ai'),
            $template,
            $variables
        );
    }

    /**
     * Send confirmation email
     */
    public function send_confirmation_email(int $user_id): bool {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $settings = $this->feature->get_settings();
        $template = $settings['emails']['confirmation'] ?? '';

        // Get login URL
        $login_url = get_permalink($settings['pages']['login'] ?? '');

        // Prepare variables
        $variables = [
            'user_name' => $user->display_name,
            'login_link' => '<a href="' . esc_url($login_url) . '">' . __('Login', 'cobra-ai') . '</a>',
            'login_url' => $login_url
        ];

        // Send email
        return $this->send_email(
            $user->user_email,
            __('Email Verification Successful', 'cobra-ai'),
            $template,
            $variables
        );
    }

    /**
     * Send approval email
     */
    public function send_approval_email(int $user_id): bool {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $settings = $this->feature->get_settings();
        $login_url = get_permalink($settings['pages']['login'] ?? '');

        $template = $this->get_template('approval');
        $variables = [
            'user_name' => $user->display_name,
            'login_link' => '<a href="' . esc_url($login_url) . '">' . __('Login', 'cobra-ai') . '</a>',
            'login_url' => $login_url
        ];

        return $this->send_email(
            $user->user_email,
            __('Account Approved', 'cobra-ai'),
            $template,
            $variables
        );
    }

    /**
     * Send rejection email
     */
    public function send_rejection_email(int $user_id): bool {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $template = $this->get_template('rejection');
        $variables = [
            'user_name' => $user->display_name,
            'site_name' => get_bloginfo('name')
        ];

        return $this->send_email(
            $user->user_email,
            __('Account Application Status', 'cobra-ai'),
            $template,
            $variables
        );
    }

    /**
     * Send password reset email
     */
    public function send_password_reset_email(int $user_id, string $reset_key): bool {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $settings = $this->feature->get_settings();
        
        // Build reset URL
        $reset_url = add_query_arg([
            'action' => 'reset_password',
            'key' => $reset_key,
            'user_id' => $user_id
        ], get_permalink($settings['pages']['reset_password'] ?? ''));

        $template = $this->get_template('password_reset');
        $variables = [
            'user_name' => $user->display_name,
            'reset_link' => '<a href="' . esc_url($reset_url) . '">' . __('Reset Password', 'cobra-ai') . '</a>',
            'reset_url' => $reset_url,
            'expiry_time' => '24 ' . __('hours', 'cobra-ai')
        ];

        return $this->send_email(
            $user->user_email,
            __('Password Reset Request', 'cobra-ai'),
            $template,
            $variables
        );
    }

    /**
     * Send admin notification
     */
    public function send_admin_notification(int $user_id, string $type): bool {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $admin_email = get_option('admin_email');
        $template = $this->get_template('admin_' . $type);
        $variables = [
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'registration_date' => get_user_meta($user_id, '_registration_date', true),
            'admin_url' => admin_url('user-edit.php?user_id=' . $user_id)
        ];

        $subject = sprintf(
            __('New User Registration: %s', 'cobra-ai'),
            $user->display_name
        );

        return $this->send_email(
            $admin_email,
            $subject,
            $template,
            $variables
        );
    }

    /**
     * Send email
     */
    private function send_email(string $to, string $subject, string $template, array $variables = []): bool {
        try {
            // Get settings
            $settings = $this->feature->get_settings();

            // Get global template
            $global_template = $settings['emails']['global_template'] ?? '';

            // Replace variables in content template
            $content = $this->replace_variables($template, $variables);

            // Replace variables in global template
            $body = $this->replace_variables($global_template, [
                'site_name' => get_bloginfo('name'),
                'site_url' => home_url(),
                'header' => $subject,
                'content' => $content,
                'footer' => $this->get_email_footer()
            ]);

            // Set headers
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
            ];

            // Send email
            return wp_mail($to, $subject, $body, $headers);

        } catch (\Exception $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email template
     */
    private function get_template(string $template): string {
        $template_path = __DIR__ . '/../templates/email/' . $template . '.html';
        
        if (file_exists($template_path)) {
            return file_get_contents($template_path);
        }

        return '';
    }

    /**
     * Replace variables in template
     */
    private function replace_variables(string $template, array $variables): string {
        foreach ($variables as $key => $value) {
            $template = str_replace(
                ['{' . $key . '}', '{{' . $key . '}}'],
                $value,
                $template
            );
        }

        return $template;
    }

    /**
     * Get email footer
     */
    private function get_email_footer(): string {
        return sprintf(
            __('This email was sent from %s', 'cobra-ai'),
            get_bloginfo('name')
        );
    }

    /**
     * Get default templates
     */
    public function get_default_templates(): array {
        return [
            'verification' => $this->get_template('verification'),
            'confirmation' => $this->get_template('confirmation'),
            'approval' => $this->get_template('approval'),
            'rejection' => $this->get_template('rejection'),
            'password_reset' => $this->get_template('password_reset'),
            'admin_notification' => $this->get_template('admin_notification')
        ];
    }

    /**
     * Test email configuration
     */
    public function test_email_configuration(): array {
        $test_email = get_option('admin_email');
        $success = $this->send_email(
            $test_email,
            __('Test Email', 'cobra-ai'),
            '<p>' . __('This is a test email from your user registration system.', 'cobra-ai') . '</p>',
            ['site_name' => get_bloginfo('name')]
        );

        return [
            'success' => $success,
            'message' => $success 
                ? __('Test email sent successfully.', 'cobra-ai')
                : __('Failed to send test email.', 'cobra-ai')
        ];
    }
}