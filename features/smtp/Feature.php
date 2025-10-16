<?php

namespace CobraAI\Features\SMTP;

use CobraAI\FeatureBase;

 

/**
 * SMTP Feature for Cobra AI
 * Adds SMTP/POP functionality with custom email settings
 */
class Feature extends FeatureBase
{
    /**
     * Feature properties
     */
    protected string $feature_id = 'smtp';
    protected string $name = 'SMTP';
    protected string $description = 'Configure SMTP and POP settings to improve WordPress email deliverability';
    protected string $version = '1.1.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = true;
    protected bool $has_admin = true;

    /**
     * Setup feature
     */
    protected function setup(): void
    {
        // Register tables if needed
        $this->tables = [];
    }

    /**
     * Initialize hooks
     */
    protected function init_hooks(): void
    {
        // Call parent hooks
        parent::init_hooks();

        // Add phpmailer_init hook for SMTP configuration
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);

        // Add test email functionality
        add_action('wp_ajax_cobra_ai_test_smtp_email', [$this, 'handle_test_email']);
    }

    /**
     * Get default options
     */
    protected function get_feature_default_options(): array
    {
        return [
            'enabled' => false,
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'smtp' => [
                'enabled' => false,
                'host' => '',
                'port' => '587',
                'encryption' => 'tls', // 'tls', 'ssl', or ''
                'auth' => true,
                'username' => '',
                'password' => '',
            ],
            'pop' => [
                'enabled' => false,
                'host' => '',
                'port' => '110',
                'username' => '',
                'password' => '',
            ],
            'testing' => [
                'recipient' => get_option('admin_email'),
                'subject' => 'Test Email from ' . get_bloginfo('name'),
                'message' => 'This is a test email from the Email Service feature of Cobra AI.'
            ]
        ];
    }

    /**
     * Configure PHPMailer
     * This is the main function that configures WordPress to use our SMTP settings
     */
    public function configure_phpmailer($phpmailer)
    {
        $settings = $this->get_settings();

        // Only proceed if feature is enabled
        if (empty($settings['enabled'])) {
            return;
        }
        $phpmailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        // Set sender details
        if (!empty($settings['from_email'])) {
            $phpmailer->setFrom($settings['from_email'], $settings['from_name']);
        }

        // Configure SMTP if enabled
        if (!empty($settings['smtp']['enabled'])) {
            // Set mailer to use SMTP
            $phpmailer->isSMTP();
            
            // Set host
            if (!empty($settings['smtp']['host'])) {
                $phpmailer->Host = $settings['smtp']['host'];
            }
            
            // Set port
            if (!empty($settings['smtp']['port'])) {
                $phpmailer->Port = $settings['smtp']['port'];
            }
            
            // Set encryption (TLS or SSL)
            if (!empty($settings['smtp']['encryption'])) {
                if ($settings['smtp']['encryption'] === 'tls') {
                    $phpmailer->SMTPSecure = 'tls';
                } elseif ($settings['smtp']['encryption'] === 'ssl') {
                    $phpmailer->SMTPSecure = 'ssl';
                }
            } else {
                $phpmailer->SMTPSecure = '';
                $phpmailer->SMTPAutoTLS = false;
            }
            
            // Set authentication
            if (!empty($settings['smtp']['auth'])) {
                $phpmailer->SMTPAuth = true;
                
                // Set credentials
                if (!empty($settings['smtp']['username'])) {
                    $phpmailer->Username = $settings['smtp']['username'];
                }
                
                if (!empty($settings['smtp']['password'])) {
                    $phpmailer->Password = $settings['smtp']['password'];
                }
            } else {
                $phpmailer->SMTPAuth = false;
            }
            
            // Debug mode (optional, set to 0 for production)
            $phpmailer->SMTPDebug = 0;
        }

        // Configure POP before SMTP if enabled
        if (!empty($settings['pop']['enabled'])) {
            // We need to perform POP authentication before sending via SMTP
            $this->authenticate_pop($settings['pop']);
        }

        // Add action for logging
        $this->log('info', 'PHPMailer configured with custom settings');
    }

    /**
     * Authenticate with POP3 server
     */
    private function authenticate_pop($pop_settings)
    {
        if (empty($pop_settings['host']) || empty($pop_settings['username']) || empty($pop_settings['password'])) {
            return false;
        }

        try {
            // Get the PHPMailer instance
            global $phpmailer;
            
            // Make sure the global PHPMailer object is instantiated
            if (!is_object($phpmailer) || !$phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer) {
                require_once ABSPATH . WPINC . '/class-phpmailer.php';
                $phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            }
            
            // For older WordPress versions
            if (!class_exists('\PHPMailer\PHPMailer\POP3')) {
                // Try the WordPress 4.6+ path
                if (file_exists(ABSPATH . WPINC . '/class-pop3.php')) {
                    require_once ABSPATH . WPINC . '/class-pop3.php';
                    if (class_exists('POP3')) {
                        // Use the old POP3 class
                        $port = !empty($pop_settings['port']) ? $pop_settings['port'] : 110;
                        $pop3 = new \POP3();
                        
                        // Connect and authenticate
                        if (!$pop3->connect($pop_settings['host'], $port)) {
                            $this->log('error', 'Failed to connect to POP3 server (Legacy)');
                            return false;
                        }
                        
                        if (!$pop3->login($pop_settings['username'], $pop_settings['password'])) {
                            $this->log('error', 'Failed to authenticate with POP3 server (Legacy)');
                            return false;
                        }
                        
                        return true;
                    }
                }
            } else {
                // Modern PHPMailer POP3 class
                $pop3 = new \PHPMailer\PHPMailer\POP3();
                $port = !empty($pop_settings['port']) ? $pop_settings['port'] : 110;
                
                if (method_exists($pop3, 'setDebugLevel')) {
                    $pop3->setDebugLevel(0);
                }
                
                if (!$pop3->connect($pop_settings['host'], $port, 30)) {
                    $this->log('error', 'Failed to connect to POP3 server');
                    return false;
                }
                
                if (!$pop3->login($pop_settings['username'], $pop_settings['password'])) {
                    $this->log('error', 'Failed to authenticate with POP3 server');
                    return false;
                }
                
                return true;
            }
            
            // If we get here, no suitable POP3 class was found
            $this->log('error', 'No suitable POP3 class found');
            return false;
            
        } catch (\Exception $e) {
            $this->log('error', 'POP3 authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle test email
     */
    public function handle_test_email()
    {
        // Check nonce
        if (!check_ajax_referer('cobra_ai_smtp_test', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Invalid security token', 'cobra-ai')
            ]);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action', 'cobra-ai')
            ]);
        }

        $settings = $this->get_settings();
        $recipient = !empty($_POST['recipient']) ? sanitize_email($_POST['recipient']) : $settings['testing']['recipient'];
        $subject = !empty($_POST['subject']) ? sanitize_text_field($_POST['subject']) : $settings['testing']['subject'];
        $message = !empty($_POST['message']) ? wp_kses_post($_POST['message']) : $settings['testing']['message'];

        // Send test email
        $result = wp_mail($recipient, $subject, $message);

        if ($result) {
            wp_send_json_success([
                'message' => sprintf(__('Test email sent successfully to %s', 'cobra-ai'), $recipient)
            ]);
        } else {
            // Get mail error
            global $phpmailer;
            if (!empty($phpmailer->ErrorInfo)) {
                $error = $phpmailer->ErrorInfo;
            } else {
                $error = __('Unknown error', 'cobra-ai');
            }

            wp_send_json_error([
                'message' => sprintf(__('Failed to send test email: %s', 'cobra-ai'), $error)
            ]);
        }
    }

    /**
     * Validate settings
     */
    protected function validate_settings(array $settings): array
    {
        // Ensure basic fields are present
        if (!isset($settings['enabled'])) {
            $settings['enabled'] = false;
        }

        // Validate email addresses
        if (!empty($settings['from_email']) && !is_email($settings['from_email'])) {
            add_settings_error(
                'cobra_ai_email_service',
                'invalid_from_email',
                __('Please enter a valid From Email Address', 'cobra-ai')
            );
            $settings['from_email'] = get_option('admin_email');
        }

        // Validate SMTP settings
        if (!empty($settings['smtp']['enabled'])) {
            if (empty($settings['smtp']['host'])) {
                add_settings_error(
                    'cobra_ai_email_service',
                    'empty_smtp_host',
                    __('SMTP Host is required when SMTP is enabled', 'cobra-ai')
                );
                $settings['smtp']['enabled'] = false;
            }

            if (!empty($settings['smtp']['port'])) {
                $settings['smtp']['port'] = absint($settings['smtp']['port']);
            }
        }

        // Validate POP settings
        if (!empty($settings['pop']['enabled'])) {
            if (empty($settings['pop']['host'])) {
                add_settings_error(
                    'cobra_ai_email_service',
                    'empty_pop_host',
                    __('POP Host is required when POP is enabled', 'cobra-ai')
                );
                $settings['pop']['enabled'] = false;
            }

            if (!empty($settings['pop']['port'])) {
                $settings['pop']['port'] = absint($settings['pop']['port']);
            }
        }

        return $settings;
    }

    /**
     * Render settings
     */
    public function render_settings(): void
    {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current settings
        $settings = $this->get_settings();

        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

        // Include settings view
        include $this->path . 'views/settings.php';
    }
}