<?php

namespace CobraAI\Features\Authgoogle;

use CobraAI\FeatureBase;

use function CobraAI\{
    cobra_ai_db
};

class Feature extends FeatureBase
{
    protected string $feature_id = 'authgoogle';
    protected string $name = 'Google Authentication';
    protected string $description = 'Allow users to login with their Google accounts and set them as subscribers';
    protected string $version = '1.1.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = true;
    
    protected function setup(): void
    {
        // No custom tables needed, we'll use WordPress user meta
    }

    protected function init_hooks(): void
    {
        parent::init_hooks();

        // Login and authentication hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_google_assets']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_google_assets']);
        
        // Add login button to WordPress login form
        add_action('login_form', [$this, 'add_google_login_button']);
        add_action('woocommerce_login_form', [$this, 'add_google_login_button']);
        
        // Hook into Cobra AI forms based on settings
        $this->init_cobra_form_hooks();
        
        // Handle Google OAuth redirect
        add_action('init', [$this, 'handle_google_oauth_redirect']);
        
        // API endpoints for authentication
        add_action('rest_api_init', [$this, 'register_api_routes']);
        
        // Add shortcode for login button
        add_shortcode('cobra_google_login', [$this, 'render_google_login_shortcode']);
        
        // Disconnect Google account
        add_action('wp_ajax_cobra_google_disconnect', [$this, 'handle_disconnect_account']);
        
        // Filter avatar with Google profile picture
        add_filter('get_avatar', [$this, 'get_google_avatar'], 10, 5);
    }
    
    /**
     * Initialize hooks for Cobra AI forms based on settings
     */
    protected function init_cobra_form_hooks(): void
    {
        $settings = $this->get_settings();
        
        // Hook into login form if enabled
        if (!empty($settings['display']['show_on_login'])) {
            add_action('cobra_before_login_form', [$this, 'render_google_login_for_cobra_forms']);
        }
        
        // Hook into register form if enabled
        if (!empty($settings['display']['show_on_register'])) {
            add_action('cobra_before_register_form', [$this, 'render_google_login_for_cobra_forms']);
        }
    }
    
    /**
     * Render Google login button for Cobra AI forms
     */
    public function render_google_login_for_cobra_forms(): void
    {
        if (!$this->is_google_configured()) {
            return;
        }
        
        echo '<div class="cobra-google-auth-wrapper">';
        echo do_shortcode('[cobra_google_login]');
        echo '</div>';
    }

    /**
     * Get default settings for this feature
     */
    protected function get_feature_default_options(): array
    {
        return [
            'google' => [
                'client_id' => '',
                'client_secret' => '',
                'redirect_uri' => site_url('wp-json/cobra-ai/v1/google-auth/callback'),
            ],
            'login' => [
                'enabled' => true,
                'auto_register' => true,
                'button_text' => __('Login with Google', 'cobra-ai'),
                'user_role' => 'subscriber',
            ],
            'display' => [
                'show_on_login' => true,
                'show_on_register' => true,
                'show_on_wordpress_login' => false,
                'show_on_woocommerce' => false,
            ]
        ];
    }

    /**
     * Check if Google authentication is properly configured
     */
    public function is_google_configured(): bool
    {
        $settings = $this->get_settings();
        return !empty($settings['google']['client_id']) && !empty($settings['google']['client_secret']);
    }

    /**
     * Enqueue Google assets
     */
    public function enqueue_google_assets(): void
    {
        // Check if Google login is enabled
        if (!$this->get_settings('login.enabled', true)) {
            return;
        }

        // Enqueue Google API client
        wp_enqueue_script(
            'google-api-client',
            'https://accounts.google.com/gsi/client',
            [],
            null,
            true
        );

        // Enqueue our custom script
        wp_enqueue_script(
            'cobra-google-login',
            $this->assets_url . 'js/google-login.js',
            ['jquery', 'google-api-client'],
            $this->version,
            true
        );

        // Enqueue styles
        wp_enqueue_style(
            'cobra-google-login',
            $this->assets_url . 'css/google-login.css',
            [],
            $this->version
        );
        
        // Enqueue hooks integration CSS
        wp_enqueue_style(
            'cobra-google-hooks-integration',
            $this->assets_url . 'css/hooks-integration.css',
            ['cobra-google-login'],
            $this->version
        ); 
        $sets= $this->get_settings();
        // Localize script
        wp_localize_script('cobra-google-login', 'cobraGoogleLogin', [
            'client_id' => $sets["google"]['client_id'],
            'redirect_uri' => $sets["google"]['redirect_uri'],
            'button_text' => $this->get_settings('login.button_text', __('Login with Google', 'cobra-ai')),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cobra-google-login'),
            'is_user_logged_in' => is_user_logged_in(),
            'login_url' => site_url('wp-login.php')
        ]);
    }

    /**
     * Register REST API routes
     */
    public function register_api_routes(): void
    {
        register_rest_route('cobra-ai/v1', '/google-auth/callback', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_google_auth_callback'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Add Google login button to login form
     */
    public function add_google_login_button(): void
    {
        // Check if Google login is enabled
        if (!$this->get_settings('login.enabled', true)) {
            return;
        }

        // Check client ID
        $client_id = $this->get_settings('google.client_id', '');
        if (empty($client_id)) {
            return;
        }

        echo $this->get_google_login_button_html();
    }

    /**
     * Get Google login button HTML
     */
    public function get_google_login_button_html(): string
    {
        $button_text = $this->get_settings('login.button_text', __('Login with Google', 'cobra-ai'));
        
        ob_start();
        ?>
        <div class="cobra-google-login-container">
            
            <div id="cobra-google-login-button" class="cobra-google-login-button">
                <div class="cobra-google-login-icon">
                    <svg viewBox="0 0 24 24" width="24" height="24" xmlns="http://www.w3.org/2000/svg">
                        <g transform="matrix(1, 0, 0, 1, 27.009001, -39.238998)">
                            <path fill="#4285F4" d="M -3.264 51.509 C -3.264 50.719 -3.334 49.969 -3.454 49.239 L -14.754 49.239 L -14.754 53.749 L -8.284 53.749 C -8.574 55.229 -9.424 56.479 -10.684 57.329 L -10.684 60.329 L -6.824 60.329 C -4.564 58.239 -3.264 55.159 -3.264 51.509 Z"/>
                            <path fill="#34A853" d="M -14.754 63.239 C -11.514 63.239 -8.804 62.159 -6.824 60.329 L -10.684 57.329 C -11.764 58.049 -13.134 58.489 -14.754 58.489 C -17.884 58.489 -20.534 56.379 -21.484 53.529 L -25.464 53.529 L -25.464 56.619 C -23.494 60.539 -19.444 63.239 -14.754 63.239 Z"/>
                            <path fill="#FBBC05" d="M -21.484 53.529 C -21.734 52.809 -21.864 52.039 -21.864 51.239 C -21.864 50.439 -21.724 49.669 -21.484 48.949 L -21.484 45.859 L -25.464 45.859 C -26.284 47.479 -26.754 49.299 -26.754 51.239 C -26.754 53.179 -26.284 54.999 -25.464 56.619 L -21.484 53.529 Z"/>
                            <path fill="#EA4335" d="M -14.754 43.989 C -12.984 43.989 -11.404 44.599 -10.154 45.789 L -6.734 42.369 C -8.804 40.429 -11.514 39.239 -14.754 39.239 C -19.444 39.239 -23.494 41.939 -25.464 45.859 L -21.484 48.949 C -20.534 46.099 -17.884 43.989 -14.754 43.989 Z"/>
                        </g>
                    </svg>
                </div>
                <span class="cobra-google-login-text"><?php echo esc_html($button_text); ?></span>
            </div>
            <div class="cobra-google-login-divider">
                <span><?php _e('OR', 'cobra-ai'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Google login shortcode
     */
    public function render_google_login_shortcode($atts): string
    {
        $atts = shortcode_atts([
            'redirect' => '',
        ], $atts);
        
        // If user is already logged in, return empty string or profile info
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            return '<div class="cobra-google-user-info">' .
                '<p>' . sprintf(__('Logged in as %s', 'cobra-ai'), $current_user->display_name) . '</p>' .
                '</div>';
        }
        
        return $this->get_google_login_button_html();
    }

    /**
     * Handle Google OAuth redirect
     */
    public function handle_google_oauth_redirect(): void
    {
        if (!isset($_GET['cobra_google_auth'])) {
            return;
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'cobra-google-login')) {
            wp_die(__('Security check failed', 'cobra-ai'));
        }

        // Get client ID and secret
        $settings = $this->get_settings();
        $client_id = $settings['google']['client_id'] ?? '';
        $client_secret =  $settings['google']['client_secret'] ?? '';
        $redirect_uri = $settings['google']['redirect_uri'] ?? site_url('wp-json/cobra-ai/v1/google-auth/callback');

        if (empty($client_id) || empty($client_secret)) {
            wp_die(__('Google API credentials not configured', 'cobra-ai'));
        }

        // Build authorization URL
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
        $auth_params = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
            'access_type' => 'online',
            'state' => wp_create_nonce('google-login-state'),
        ];

        $auth_url = add_query_arg($auth_params, $auth_url);

        // Redirect to Google
        wp_redirect($auth_url);
        exit;
    }

    /**
     * Handle Google auth callback
     */
    public function handle_google_auth_callback($request)
    {
        // Get parameters
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $error = $request->get_param('error');
  
        // Verify state to prevent CSRF
        if (!wp_verify_nonce($state, 'google-login-state')) {
            return new \WP_Error(
                'invalid_state',
                __('Invalid state parameter', 'cobra-ai'),
                ['status' => 400]
            );
        }

        // Check for error
        if (!empty($error)) {
            return new \WP_Error(
                'google_auth_error',
                $error,
                ['status' => 400]
            );
        }

        // Verify code
        if (empty($code)) {
            return new \WP_Error(
                'missing_code',
                __('Authorization code is missing', 'cobra-ai'),
                ['status' => 400]
            );
        }

        try {
            // Exchange code for tokens
            $tokens = $this->exchange_code_for_tokens($code);
            
            if (is_wp_error($tokens)) {
                return $tokens;
            }

            // Get user info
            $user_info = $this->get_google_user_info($tokens['access_token']);
            
            if (is_wp_error($user_info)) {
                return $user_info;
            }

            // Process user login/registration
            $user_id = $this->process_google_user($user_info);
            
            if (is_wp_error($user_id)) {
                return $user_id;
            }

            // Log the user in
            $this->login_user($user_id);

            // Redirect to the appropriate page
            $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : home_url();
            wp_safe_redirect($redirect_to);
            exit;

        } catch (\Exception $e) {
            return new \WP_Error(
                'google_auth_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Exchange authorization code for tokens
     */
    private function exchange_code_for_tokens(string $code)
    {
        $settings = $this->get_settings();
        $client_id =  $settings['google']['client_id'] ?? '';
        $client_secret =  $settings['google']['client_secret'] ?? '';
        $redirect_uri = $settings['google']['redirect_uri'] ?? site_url('wp-json/cobra-ai/v1/google-auth/callback');

        $token_url = 'https://oauth2.googleapis.com/token';
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || isset($data['error'])) {
            return new \WP_Error(
                'token_exchange_failed',
                $data['error_description'] ?? __('Failed to exchange code for tokens', 'cobra-ai'),
                ['status' => 400]
            );
        }

        return $data;
    }

    /**
     * Get Google user info
     */
    private function get_google_user_info(string $access_token)
    {
        $user_info_url = 'https://www.googleapis.com/oauth2/v3/userinfo';
        
        $response = wp_remote_get($user_info_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || isset($data['error'])) {
            return new \WP_Error(
                'user_info_failed',
                $data['error_description'] ?? __('Failed to get user info', 'cobra-ai'),
                ['status' => 400]
            );
        }

        return $data;
    }

    /**
     * Process Google user (login or register)
     */
    private function process_google_user(array $user_info)
    {
        // Check required fields
        if (empty($user_info['sub']) || empty($user_info['email'])) {
            return new \WP_Error(
                'missing_user_info',
                __('Required user information is missing', 'cobra-ai'),
                ['status' => 400]
            );
        }

        // Get Google ID and email
        $google_id = sanitize_text_field($user_info['sub']);
        $google_email = sanitize_email($user_info['email']);
        
        // Check if user exists by Google ID (stored in user meta)
        $existing_users = get_users([
            'meta_key' => 'cobra_google_id',
            'meta_value' => $google_id,
            'number' => 1
        ]);

        if (!empty($existing_users)) {
            // User exists, update their info and return
            $user_id = $existing_users[0]->ID;
            $this->update_google_user_data($user_id, $user_info);
            return $user_id;
        }

        // Check if user exists by email
        $user = get_user_by('email', $google_email);
        
        if ($user) {
            // User exists by email, link their Google account
            $this->link_google_account($user->ID, $user_info);
            return $user->ID;
        }

        // Auto-registration enabled?
        if (!$this->get_settings('login.auto_register', true)) {
            return new \WP_Error(
                'registration_disabled',
                __('Auto-registration is disabled', 'cobra-ai'),
                ['status' => 403]
            );
        }

        // Register new user
        return $this->register_google_user($user_info);
    }

    /**
     * Update Google user data
     */
    private function update_google_user_data(int $user_id, array $user_info): bool
    {
        // Update user meta
        update_user_meta($user_id, 'cobra_google_email', sanitize_email($user_info['email']));
        update_user_meta($user_id, 'cobra_google_name', sanitize_text_field($user_info['name'] ?? ''));
        update_user_meta($user_id, 'cobra_google_picture', esc_url_raw($user_info['picture'] ?? ''));
        update_user_meta($user_id, 'cobra_google_last_login', current_time('mysql'));
        
        return true;
    }

    /**
     * Link Google account to existing user
     */
    private function link_google_account(int $user_id, array $user_info): bool
    {
        // Add Google data to user meta
        update_user_meta($user_id, 'cobra_google_id', sanitize_text_field($user_info['sub']));
        update_user_meta($user_id, 'cobra_google_email', sanitize_email($user_info['email']));
        update_user_meta($user_id, 'cobra_google_name', sanitize_text_field($user_info['name'] ?? ''));
        update_user_meta($user_id, 'cobra_google_picture', esc_url_raw($user_info['picture'] ?? ''));
        update_user_meta($user_id, 'cobra_google_last_login', current_time('mysql'));
        
        return true;
    }

    /**
     * Register a new user with Google data
     */
    private function register_google_user(array $user_info): int
{
    global $wpdb;
    
    // Extract user data
    $email = sanitize_email($user_info['email']);
    $name = sanitize_text_field($user_info['name'] ?? '');
    $given_name = sanitize_text_field($user_info['given_name'] ?? '');
    $family_name = sanitize_text_field($user_info['family_name'] ?? '');
    
    // Generate username from email or name
    $username = $this->generate_username($email, $name);
    
    // Generate random password
    $password = wp_generate_password(16, true, true);
    
    // Prepare user data
    $user_data = [
        'user_login' => $username,
        'user_email' => $email,
        'user_pass' => wp_hash_password($password),
        'display_name' => $name,
        'user_registered' => current_time('mysql'),
        'user_status' => 0,
        'user_nicename' => sanitize_title($username)
    ];
    
    // Insert user into wp_users table
    $result = $wpdb->insert($wpdb->users, $user_data);
    
    if (!$result) {
        // return new WP_Error('db_insert_error', __('Could not insert user into the database'));
        return 0;
    }
    
    $user_id = $wpdb->insert_id;
    
    // Add first and last name
    update_user_meta($user_id, 'first_name', $given_name);
    update_user_meta($user_id, 'last_name', $family_name);
    
    // Set user role
    $role = $this->get_settings('login.user_role', 'subscriber');
    $capabilities = [$role => true];
    update_user_meta($user_id, $wpdb->get_blog_prefix() . 'capabilities', $capabilities);
    
    // Set user level based on role
    $user_level = 0; // Default for subscriber
    if ($role === 'administrator') $user_level = 10;
    else if ($role === 'editor') $user_level = 7;
    else if ($role === 'author') $user_level = 2;
    else if ($role === 'contributor') $user_level = 1;
    else if ($role === 'subscriber') $user_level = 0;
    else if ($role === 'pending') $user_level = 0;
    else if ($role === 'customer') $user_level = 0;
    else if ($role === 'shop_manager') $user_level = 0;
    
    update_user_meta($user_id, $wpdb->get_blog_prefix() . 'user_level', $user_level);
    
    // Link Google account
    $this->link_google_account($user_id, $user_info);
    
    // Set flag for Google-registered users
    update_user_meta($user_id, 'cobra_google_registered', 1);
    
    // Manually fire user_register action to maintain compatibility
    do_action('user_register', $user_id);
    
    return $user_id;
}

    /**
     * Generate username from email or name
     */
    private function generate_username(string $email, string $name = ''): string
    {
        // Try using name first
        if (!empty($name)) {
            $username = sanitize_user(str_replace(' ', '.', strtolower($name)), true);
            if (!username_exists($username)) {
                return $username;
            }
            
            // Try adding a number
            for ($i = 1; $i <= 10; $i++) {
                $new_username = $username . $i;
                if (!username_exists($new_username)) {
                    return $new_username;
                }
            }
        }
        
        // Fall back to email
        $username = sanitize_user(current(explode('@', $email)), true);
        if (!username_exists($username)) {
            return $username;
        }
        
        // Add a random number if email username exists
        for ($i = 1; $i <= 10; $i++) {
            $new_username = $username . rand(100, 999);
            if (!username_exists($new_username)) {
                return $new_username;
            }
        }
        
        // Last resort: use email with random string
        return sanitize_user('user_' . md5($email . time()), true);
    }

    /**
     * Log user in
     */
    private function login_user(int $user_id): bool
    {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $user->user_login, $user);
        
        return true;
    }

    /**
     * Handle disconnect account
     */
    public function handle_disconnect_account(): void
    {
        // Check nonce
        check_ajax_referer('cobra-google-disconnect', 'nonce');
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('User not logged in', 'cobra-ai')]);
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Delete Google user meta
        delete_user_meta($user_id, 'cobra_google_id');
        delete_user_meta($user_id, 'cobra_google_email');
        delete_user_meta($user_id, 'cobra_google_name');
        delete_user_meta($user_id, 'cobra_google_picture');
        delete_user_meta($user_id, 'cobra_google_last_login');
        delete_user_meta($user_id, 'cobra_google_registered');
        
        wp_send_json_success(['message' => __('Google account successfully disconnected', 'cobra-ai')]);
    }
    
    /**
     * Get Google avatar for a user
     */
    public function get_google_avatar($avatar, $id_or_email, $size, $default, $alt): string
    {
        // Get user ID
        $user_id = 0;
        
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        }
        
        if (empty($user_id)) {
            return $avatar;
        }
        
        // Check for Google avatar
        $google_avatar = get_user_meta($user_id, 'cobra_google_picture', true);
        
        if (empty($google_avatar)) {
            return $avatar;
        }
        
        // Return Google avatar
        $avatar = sprintf(
            '<img alt="%s" src="%s" class="avatar avatar-%d photo" width="%d" height="%d" />',
            esc_attr($alt),
            esc_url($google_avatar),
            esc_attr($size),
            esc_attr($size),
            esc_attr($size)
        );
        
        return $avatar;
    }
}