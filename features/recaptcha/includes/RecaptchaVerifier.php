<?php

namespace CobraAI\Features\Recaptcha\Includes;

defined('ABSPATH') || exit;

/**
 * Handles reCAPTCHA response verification
 */
class RecaptchaVerifier {
    
    /**
     * Parent feature instance
     */
    private $feature;

    /**
     * Verification endpoint
     */
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Constructor
     */
    public function __construct($feature) {
        $this->feature = $feature;
    }

    /**
     * Verify reCAPTCHA response
     *
     * @param string $response The reCAPTCHA response token
     * @param string $action The action name for v3
     * @return bool Whether verification was successful
     * @throws \Exception If verification fails
     */
    public function verify_response(string $response, string $action = ''): bool {
        try {
            // Get settings
            $settings = $this->feature->get_settings();
         
            // Check if we have required keys
            if (empty($settings['secret_key'])) {
                throw new \Exception($this->get_error_message('missing_input_secret'));
            }

            if (empty($response)) {
                throw new \Exception($this->get_error_message('missing_input_response'));
            }

            // Prepare verification data
            $verify_data = [
                'secret' => $settings['secret_key'],
                'response' => $response,
                'remoteip' => $this->get_client_ip()
            ];

            // Make API request
            $result = $this->make_verification_request($verify_data);

           
            // Check if verification was successful
            if (empty($result['success'])) {
                throw new \Exception($this->get_error_from_codes($result['error-codes'] ?? []));
            }

            // For v3, verify score
            if ($settings['version'] === 'v3') {
                if (empty($result['action']) || $result['action'] !== $action) {
                    throw new \Exception($this->get_error_message('invalid_action'));
                }

                if (empty($result['score']) || $result['score'] < ($settings['score_threshold'] ?? 0.5)) {
                    throw new \Exception($this->get_error_message('low_score'));
                }
            }

            return true;

        } catch (\Exception $e) {
            // Log error
            $this->log_error('Verification failed: ' . $e->getMessage(), [
                'response' => $response,
                'action' => $action,
                'ip' => $this->get_client_ip()
            ]);

            throw $e;
        }
    }

    /**
     * Make verification request to Google
     *
     * @param array $data Request data
     * @return array Response data
     * @throws \Exception If request fails
     */
    private function make_verification_request(array $data): array {
        // Build query
        $query = http_build_query($data);
       
        // Make request
        $response = wp_remote_post(self::VERIFY_URL, [
            'body' => $query,
            'timeout' => 100,
            'sslverify' => true
        ]);

        // Check for errors
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        // Get response body
        $body = wp_remote_retrieve_body($response);

        // Decode JSON response
        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid response from verification server');
        }

        return $result;
    }

    /**
     * Log verification attempt
     */
    private function log_verification_attempt(array $result, string $action): void {
        global $wpdb;

        try {
            $logs_table = $this->feature->get_logs_table();
            
            if (empty($logs_table)) {
                return;
            }

            $wpdb->insert(
                $logs_table,
                [
                    'form_type' => $action,
                    'ip_address' => $this->get_client_ip(),
                    'verification_status' => !empty($result['success']),
                    'error_code' => isset($result['error-codes']) ? implode(',', $result['error-codes']) : null,
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );

        } catch (\Exception $e) {
            $this->log_error('Failed to log verification attempt: ' . $e->getMessage());
        }
    }

    /**
     * Get error message from error codes
     */
    private function get_error_from_codes(array $codes): string {
        if (empty($codes)) {
            return $this->get_error_message('unknown_error');
        }

        $messages = [];
        foreach ($codes as $code) {
            $messages[] = $this->get_error_message($code);
        }

        return implode(' ', $messages);
    }

    /**
     * Get error message
     */
    private function get_error_message(string $code): string {
        $settings = $this->feature->get_settings();
        $messages = $settings['error_messages'] ?? [];

        return $messages[$code] ?? $this->get_default_error_message($code);
    }

    /**
     * Get default error message
     */
    private function get_default_error_message(string $code): string {
        $defaults = [
            'missing_input_secret' => __('The secret key is missing.', 'cobra-ai'),
            'invalid_input_secret' => __('The secret key is invalid or malformed.', 'cobra-ai'),
            'missing_input_response' => __('Please complete the reCAPTCHA.', 'cobra-ai'),
            'invalid_input_response' => __('The reCAPTCHA response is invalid or expired.', 'cobra-ai'),
            'bad_request' => __('The request is invalid or malformed.', 'cobra-ai'),
            'timeout_or_duplicate' => __('The response is no longer valid: either is too old or has been used previously.', 'cobra-ai'),
            'invalid_action' => __('The action name does not match.', 'cobra-ai'),
            'low_score' => __('The verification score is too low.', 'cobra-ai'),
            'unknown_error' => __('An unknown error occurred.', 'cobra-ai')
        ];

        return $defaults[$code] ?? $defaults['unknown_error'];
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
     * Log error
     */
    private function log_error(string $message, array $context = []): void {
        if (method_exists($this->feature, 'log_error')) {
            $this->feature->log_error($message, $context);
        }
    }

    /**
     * Clean old logs
     */
    public function cleanup_logs(int $days = 30): bool {
        try {
            $logs_table = $this->feature->get_logs_table();
            
            if (empty($logs_table)) {
                return false;
            }

            global $wpdb;
            
            return $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$logs_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            ) !== false;

        } catch (\Exception $e) {
            $this->log_error('Failed to cleanup logs: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test API keys
     */
    public function test_keys(string $site_key, string $secret_key): array {
        try {
            // Basic format validation
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $site_key)) {
                throw new \Exception('Invalid site key format');
            }

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $secret_key)) {
                throw new \Exception('Invalid secret key format');
            }

            // Make a test verification request
            $result = $this->make_verification_request([
                'secret' => $secret_key,
                'response' => 'test',
                'remoteip' => $this->get_client_ip()
            ]);

            // Expected to fail but should get valid error codes
            if (empty($result['error-codes']) || !is_array($result['error-codes'])) {
                throw new \Exception('Invalid response from verification server');
            }

            return [
                'success' => true,
                'message' => 'API keys appear to be valid'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}