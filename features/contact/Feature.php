<?php

namespace CobraAI\Features\Contact;

use CobraAI\FeatureBase;

use function CobraAI\{
    cobra_ai_db
};

class Feature extends FeatureBase
{
    protected $feature_id = 'contact';
    protected $name = 'Contact Form';
    protected $description = 'Add a customizable contact form to your website with spam protection and admin reply interface';
    protected $version = '1.0.0';
    protected $author = 'Cobra AI';
    protected $has_settings = true;

    protected function setup(): void
    {
        global $wpdb;
        
        // Define database tables - just one table for submissions
        $this->tables = [
            'submissions' => [
                'name' => $wpdb->prefix . 'cobra_contact_submissions',
                'schema' => [
                    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT',
                    'name' => 'varchar(100) NOT NULL',
                    'email' => 'varchar(100) NOT NULL',
                    'subject' => 'varchar(255) NOT NULL',
                    'message' => 'text NOT NULL',
                    'status' => "enum('unread','read','replied') NOT NULL DEFAULT 'unread'",
                    'user_id' => 'bigint(20) DEFAULT NULL',
                    'user_ip' => 'varchar(45) DEFAULT NULL',
                    'user_agent' => 'text DEFAULT NULL',
                    'created_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                    'updated_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'response' => 'text DEFAULT NULL',
                    'response_date' => 'datetime DEFAULT NULL',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => [
                        'status' => '(status)',
                        'created_at' => '(created_at)'
                    ]
                ]
            ]
        ];
    }

    protected function init_hooks(): void
    {
        parent::init_hooks();
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // AJAX handlers for form submission
        add_action('wp_ajax_cobra_contact_submit', [$this, 'handle_form_submission']);
        add_action('wp_ajax_nopriv_cobra_contact_submit', [$this, 'handle_form_submission']);
        
        // AJAX handlers for admin actions
        add_action('wp_ajax_cobra_contact_mark_read', [$this, 'mark_as_read']);
        add_action('wp_ajax_cobra_contact_send_reply', [$this, 'send_reply']);
        add_action('wp_ajax_cobra_contact_delete_message', [$this, 'delete_message']);
        
        // Add admin dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        
        // Handle form notification emails
        add_action('cobra_contact_form_submitted', [$this, 'send_notification_email'], 10, 2);
    }

    protected function register_shortcodes(): void
    {
        add_shortcode('cobra_contact_form', [$this, 'render_contact_form_shortcode']);
    }

    public function add_admin_menu(): void
    {
        add_submenu_page(
            'cobra-ai-dashboard',
            __('Contact Submissions', 'cobra-ai'),
            __('Contact Submissions', 'cobra-ai'),
            'manage_options',
            'cobra-contact-submissions',
            [$this, 'render_submissions_page']
        );
        
        // Hidden page for viewing a single submission
        add_submenu_page(
            'cobra-contact-submissions',
            __('View Submission', 'cobra-ai'),
            __('View Submission', 'cobra-ai'),
            'manage_options',
            'cobra-contact-view-submission',
            [$this, 'render_submission_detail']
        );
    }

    public function add_dashboard_widget(): void
    {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'cobra_contact_dashboard_widget',
                __('Recent Contact Submissions', 'cobra-ai'),
                [$this, 'render_dashboard_widget']
            );
        }
    }

    public function render_dashboard_widget(): void
    {
        global $wpdb;
        $table_name = $this->tables['submissions']['name'];
        
        $recent_submissions = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5"
        );
        
        if (empty($recent_submissions)) {
            echo '<p>' . __('No submissions yet.', 'cobra-ai') . '</p>';
            return;
        }
        
        echo '<ul class="cobra-contact-recent-list">';
        foreach ($recent_submissions as $submission) {
            $status_class = 'status-' . $submission->status;
            $view_url = admin_url('admin.php?page=cobra-contact-view-submission&id=' . $submission->id);
            
            echo '<li class="' . esc_attr($status_class) . '">';
            echo '<a href="' . esc_url($view_url) . '">';
            echo '<span class="submission-name">' . esc_html($submission->name) . '</span>';
            echo '<span class="submission-subject">' . esc_html($submission->subject) . '</span>';
            echo '<span class="submission-date">' . esc_html(human_time_diff(strtotime($submission->created_at), current_time('timestamp'))) . ' ' . __('ago', 'cobra-ai') . '</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
        
        $all_url = admin_url('admin.php?page=cobra-contact-submissions');
        echo '<p class="cobra-contact-view-all"><a href="' . esc_url($all_url) . '">' . __('View all submissions', 'cobra-ai') . '</a></p>';
        
        // Add some basic styling
        echo '<style>
            .cobra-contact-recent-list {
                margin: 0;
                padding: 0;
            }
            .cobra-contact-recent-list li {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
                position: relative;
            }
            .cobra-contact-recent-list li:last-child {
                border-bottom: none;
            }
            .cobra-contact-recent-list li a {
                display: block;
                text-decoration: none;
                color: #23282d;
            }
            .cobra-contact-recent-list li.status-unread a {
                font-weight: bold;
            }
            .cobra-contact-recent-list li.status-unread:before {
                content: "";
                display: block;
                width: 6px;
                height: 6px;
                background: #0073aa;
                border-radius: 50%;
                position: absolute;
                left: -10px;
                top: 50%;
                transform: translateY(-50%);
            }
            .submission-name {
                display: block;
            }
            .submission-subject {
                display: block;
                font-size: 12px;
                color: #72777c;
            }
            .submission-date {
                display: block;
                font-size: 11px;
                color: #72777c;
            }
            .cobra-contact-view-all {
                text-align: right;
                margin: 8px 0 0;
            }
        </style>';
    }

    public function is_recaptcha_enabled(): bool
    {
        $settings = $this->get_settings();
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

    private function should_verify_recaptcha(): bool
    {
        $settings = $this->get_settings();
        return !empty($settings['general']['use_recaptcha']);
    }

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

    public function render_contact_form_shortcode($atts): string
    {
        $atts = shortcode_atts([
            'title' => '',
            'subject_options' => 'General Inquiry,Technical Support,Billing Question,Feedback',
            'show_subject' => 'yes',
            'submit_button_text' => __('Send Message', 'cobra-ai'),
            'success_message' => __('Thank you! Your message has been sent successfully.', 'cobra-ai'),
            'error_message' => __('Sorry, there was an error sending your message. Please try again later.', 'cobra-ai'),
            'redirect_url' => '',
        ], $atts);
        
        // Start output buffering
        ob_start();
        
        // Get form settings
        $settings = $this->get_settings();
        
        // Convert subject options to array
        $subject_options = explode(',', $atts['subject_options']);
        $subject_options = array_map('trim', $subject_options);
        
        // Determine which fields to show
        $show_name = $settings['fields']['show_name'] ?? true;
        $show_email = $settings['fields']['show_email'] ?? true;
        $show_subject = $atts['show_subject'] === 'yes' && ($settings['fields']['show_subject'] ?? true);
        $show_message = $settings['fields']['show_message'] ?? true;
        
        // Include form template
        include $this->path . 'templates/contact-form.php';
        
        // Get the buffered content
        return ob_get_clean();
    }

    public function handle_form_submission(): void
    {
        // Verify nonce
        if (!check_ajax_referer('cobra_contact_form_submit', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed. Please refresh the page and try again.', 'cobra-ai')]);
        }
        
        // Get form data
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        // Validate required fields
        $settings = $this->get_settings();
        $required_fields = [];
        
        if ($settings['fields']['show_name'] ?? true) {
            $required_fields[] = 'name';
        }
        
        if ($settings['fields']['show_email'] ?? true) {
            $required_fields[] = 'email';
        }
        
        if ($settings['fields']['show_subject'] ?? true) {
            $required_fields[] = 'subject';
        }
        
        if ($settings['fields']['show_message'] ?? true) {
            $required_fields[] = 'message';
        }
        
        foreach ($required_fields as $field) {
            if (empty($$field)) {
                wp_send_json_error(['message' => sprintf(__('Please fill in the %s field.', 'cobra-ai'), $field)]);
            }
        }
        
        // Validate email
        if (!empty($email) && !is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'cobra-ai')]);
        }
        
        // Verify reCAPTCHA if enabled
        if ($this->should_verify_recaptcha()) {
            $recaptcha_response = $this->verify_recaptcha('cobra_contact_form_submit');
            if (is_wp_error($recaptcha_response)) {
                wp_send_json_error(['message' => $recaptcha_response->get_error_message()]);
            }
        }
        
        // Insert submission into database
        global $wpdb;
        $table_name = $this->tables['submissions']['name'];
        
        $data = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'status' => 'unread',
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
            'user_ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to save your message. Please try again later.', 'cobra-ai')]);
        }
        
        // Get the new submission ID
        $submission_id = $wpdb->insert_id;
        
        // Trigger action for notification emails
        do_action('cobra_contact_form_submitted', $submission_id, $data);
        
        // Send success response
        wp_send_json_success([
            'message' => __('Thank you! Your message has been sent successfully.', 'cobra-ai'),
            'submission_id' => $submission_id
        ]);
    }

    public function render_submissions_page(): void
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Include Submissions table class
        require_once $this->path . 'includes/Submissions_Table.php';
        
        // Create an instance of the table
        $submissions_table = new Submissions_Table();
        $submissions_table->prepare_items();
        
        // Include view
        include $this->path . 'views/submissions.php';
    }

    public function render_submission_detail(): void
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $submission_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        
        if ($submission_id <= 0) {
            wp_die(__('Invalid submission ID.', 'cobra-ai'));
        }
        
        global $wpdb;
        $table_name = $this->tables['submissions']['name'];
        
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $submission_id
        ));
        
        if (!$submission) {
            wp_die(__('Submission not found.', 'cobra-ai'));
        }
        
        // Mark as read if it's unread
        if ($submission->status === 'unread') {
            $wpdb->update(
                $table_name,
                ['status' => 'read'],
                ['id' => $submission_id],
                ['%s'],
                ['%d']
            );
            
            // Refresh the submission data
            $submission = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $submission_id
            ));
        }
        
        // Include view
        include $this->path . 'views/submission-detail.php';
    }

    public function mark_as_read(): void
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('cobra_contact_admin', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'cobra-ai')]);
        }
        
        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
        
        if ($submission_id <= 0) {
            wp_send_json_error(['message' => __('Invalid submission ID.', 'cobra-ai')]);
        }
        
        global $wpdb;
        $table_name = $this->tables['submissions']['name'];
        
        $result = $wpdb->update(
            $table_name,
            ['status' => 'read'],
            ['id' => $submission_id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to mark message as read.', 'cobra-ai')]);
        }
        
        wp_send_json_success(['message' => __('Message marked as read.', 'cobra-ai')]);
    }

    public function send_reply(): void
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('cobra_contact_admin', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'cobra-ai')]);
        }
        
        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
        $response = isset($_POST['response']) ? wp_kses_post($_POST['response']) : '';
        
        if ($submission_id <= 0 || empty($response)) {
            wp_send_json_error(['message' => __('Invalid submission ID or empty response.', 'cobra-ai')]);
        }
        
        global $wpdb;
        $table_name = $this->tables['submissions']['name'];
        
        // Get the submission data
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $submission_id
        ));
        
        if (!$submission) {
            wp_send_json_error(['message' => __('Submission not found.', 'cobra-ai')]);
        }
        
        // Update the submission with response
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'replied',
                'response' => $response,
                'response_date' => current_time('mysql')
            ],
            ['id' => $submission_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to save response.', 'cobra-ai')]);
        }
        
        // Send email to the contact
        $to = $submission->email;
        $subject = sprintf(__('Re: %s', 'cobra-ai'), $submission->subject);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        // Get email template from settings or use default
        $email_template = $this->get_settings('reply_email_template', '');
        if (empty($email_template)) {
            $email_template = $this->get_default_reply_template();
        }
        
        // Replace variables in template
        $email_body = str_replace(
            ['{{name}}', '{{subject}}', '{{message}}', '{{response}}'],
            [$submission->name, $submission->subject, $submission->message, $response],
            $email_template
        );
        
        // Send the email
        $mail_sent = wp_mail($to, $subject, $email_body, $headers);
        
        wp_send_json_success([
            'message' => __('Response sent successfully.', 'cobra-ai'),
            'mail_sent' => $mail_sent
        ]);
    }

    public function delete_message(): void
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('cobra_contact_admin', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'cobra-ai')]);
        }
        
        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
        
        if ($submission_id <= 0) {
            wp_send_json_error(['message' => __('Invalid submission ID.', 'cobra-ai')]);
        }
        
        global $wpdb;
        $table_name = $this->tables['submissions']['name'];
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $submission_id],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to delete message.', 'cobra-ai')]);
        }
        
        wp_send_json_success(['message' => __('Message deleted successfully.', 'cobra-ai')]);
    }

    public function send_notification_email($submission_id, $data): void
    {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        // Get notification settings
        $notifications_enabled = $this->get_settings('notifications.enabled', true);
        $notification_recipients = $this->get_settings('notifications.recipients', $admin_email);
        
        if (!$notifications_enabled) {
            return;
        }
        
        // If there are multiple recipients, split them
        $recipients = explode(',', $notification_recipients);
        $recipients = array_map('trim', $recipients);
        
        $subject = sprintf(__('New Contact Form Submission on %s', 'cobra-ai'), $site_name);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        // Get notification template from settings or use default
        $notification_template = $this->get_settings('notification_email_template', '');
        if (empty($notification_template)) {
            $notification_template = $this->get_default_notification_template();
        }
        
        // Get admin URL for submission
        $admin_url = admin_url('admin.php?page=cobra-contact-view-submission&id=' . $submission_id);
        
        // Replace variables in template
        $email_body = str_replace(
            ['{{site_name}}', '{{name}}', '{{email}}', '{{subject}}', '{{message}}', '{{submission_link}}'],
            [$site_name, $data['name'], $data['email'], $data['subject'], $data['message'], $admin_url],
            $notification_template
        );
        
        // Send to each recipient
        foreach ($recipients as $recipient) {
            wp_mail($recipient, $subject, $email_body, $headers);
        }
    }

    public function get_default_notification_template(): string
    {
        return '<!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #f8f9fa; padding: 15px; text-align: center; }
                        .content { padding: 20px; background-color: #fff; }
                        .footer { padding: 15px; text-align: center; font-size: 0.8em; color: #777; }
                        .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 4px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>New Contact Form Submission</h2>
                        </div>
                        <div class="content">
                            <p>A new message has been submitted on {{site_name}}:</p>
                            <p><strong>Name:</strong> {{name}}</p>
                            <p><strong>Email:</strong> {{email}}</p>
                            <p><strong>Subject:</strong> {{subject}}</p>
                            <p><strong>Message:</strong></p>
                            <p>{{message}}</p>
                            <p>
                                <a href="{{submission_link}}" class="btn">View Submission</a>
                            </p>
                        </div>
                        <div class="footer">
                            <p>This is an automated notification from your website.</p>
                        </div>
                    </div>
                </body>
                </html>';
    }

    public function get_default_reply_template(): string
    {
        return '<!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #f8f9fa; padding: 15px; text-align: center; }
                        .content { padding: 20px; background-color: #fff; }
                        .message { padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff; margin: 20px 0; }
                        .footer { padding: 15px; text-align: center; font-size: 0.8em; color: #777; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>Response to Your Message</h2>
                        </div>
                        <div class="content">
                            <p>Hello {{name}},</p>
                            <p>Thank you for contacting us. Here is our response to your inquiry:</p>
                            <div class="message">
                                <p>{{response}}</p>
                            </div>
                            <p>Your original message:</p>
                            <div class="message">
                                <p><strong>Subject:</strong> {{subject}}</p>
                                <p>{{message}}</p>
                            </div>
                            <p>If you have any further questions, please don\'t hesitate to contact us again.</p>
                        </div>
                        <div class="footer">
                            <p>This email was sent in response to your contact form submission.</p>
                        </div>
                    </div>
                </body>
                </html>';
    }

    protected function get_feature_default_options(): array
    {
        return [
            'fields' => [
                'show_name' => true,
                'show_email' => true,
                'show_subject' => true,
                'show_message' => true,
                'name_label' => __('Your Name', 'cobra-ai'),
                'email_label' => __('Your Email', 'cobra-ai'),
                'subject_label' => __('Subject', 'cobra-ai'),
                'message_label' => __('Your Message', 'cobra-ai'),
                'submit_button_text' => __('Send Message', 'cobra-ai'),
                'autofill_for_logged_in' => true,
            ],
            'general' => [
                'use_recaptcha' => false, // Simple toggle for enabling/disabling reCAPTCHA
            ],
            'subjects' => [
                'General Inquiry',
                'Technical Support',
                'Billing Question',
                'Feedback'
            ],
            'notifications' => [
                'enabled' => true,
                'recipients' => get_option('admin_email'),
            ],
            'messages' => [
                'success' => __('Thank you! Your message has been sent successfully.', 'cobra-ai'),
                'error' => __('Sorry, there was an error sending your message. Please try again later.', 'cobra-ai'),
                'required_fields' => __('Please fill in all required fields.', 'cobra-ai'),
                'invalid_email' => __('Please enter a valid email address.', 'cobra-ai'),
            ],
            'styling' => [
                'css_class' => 'animated-form',
                'include_default_css' => true,
            ]
        ];
    }
}