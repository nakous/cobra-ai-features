<?php
// authgoogle/views/settings.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings with defaults merged
$settings = $this->get_settings();

// Display any settings errors
$this->display_settings_errors();
?>

<div class="wrap">
    <h1><?php echo esc_html__('Google Authentication Settings', 'cobra-ai'); ?></h1>

    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings saved successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <div class="notice notice-info">
        <p>
            <?php echo esc_html__('To use Google authentication, you need to create OAuth credentials in the Google Cloud Console. ', 'cobra-ai'); ?>
            <a href="https://console.cloud.google.com/" target="_blank"><?php echo esc_html__('Go to Google Cloud Console', 'cobra-ai'); ?></a>
        </p>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">

        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable Google Login', 'cobra-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[login][enabled]" 
                               value="1" 
                               <?php checked($settings['login']['enabled'] ?? true); ?>>
                        <?php echo esc_html__('Allow users to login with their Google account', 'cobra-ai'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php echo esc_html__('Auto Registration', 'cobra-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="settings[login][auto_register]" 
                               value="1" 
                               <?php checked($settings['login']['auto_register'] ?? true); ?>>
                        <?php echo esc_html__('Automatically register new users who login with Google', 'cobra-ai'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php echo esc_html__('Default User Role', 'cobra-ai'); ?></th>
                <td>
                    <select name="settings[login][user_role]">
                        <?php
                        // Get all available roles
                        $roles = get_editable_roles();
                        
                        foreach ($roles as $role_key => $role) {
                            echo '<option value="' . esc_attr($role_key) . '" ' . 
                                 selected($settings['login']['user_role'] ?? 'subscriber', $role_key, false) . '>' . 
                                 esc_html($role['name']) . 
                                 '</option>';
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Role assigned to new users who register with Google', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php echo esc_html__('Button Text', 'cobra-ai'); ?></th>
                <td>
                    <input type="text" 
                           name="settings[login][button_text]" 
                           value="<?php echo esc_attr($this->get_settings('login.button_text', __('Login with Google', 'cobra-ai')) ); ?>" 
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php echo esc_html__('Google Client ID', 'cobra-ai'); ?></th>
                <td>
                    <input type="text" 
                           name="settings[google][client_id]" 
                           value="<?php echo esc_attr($this->get_settings('google.client_id', "") ?? ''); ?>"
                           class="regular-text">
                    <p class="description">
                        <?php echo esc_html__('Enter your Google OAuth Client ID', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php echo esc_html__('Google Client Secret', 'cobra-ai'); ?></th>
                <td>
                    <input type="password" 
                           name="settings[google][client_secret]" 
                           value="<?php echo esc_attr($settings['google']['client_secret'] ?? ''); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php echo esc_html__('Enter your Google OAuth Client Secret', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php echo esc_html__('Redirect URI', 'cobra-ai'); ?></th>
                <td>
                    <input type="text" 
                           value="<?php echo esc_url($settings['google']['redirect_uri'] ?? site_url('wp-json/cobra-ai/v1/google-auth/callback')); ?>" 
                           readonly 
                           class="regular-text code">
                    <button type="button" class="button button-secondary copy-clipboard" data-clipboard="<?php echo esc_attr($settings['google']['redirect_uri'] ?? site_url('wp-json/cobra-ai/v1/google-auth/callback')); ?>">
                        <?php echo esc_html__('Copy', 'cobra-ai'); ?>
                    </button>
                    <p class="description">
                        <?php echo esc_html__('Add this URL to your Google OAuth Authorized Redirect URIs', 'cobra-ai'); ?>
                    </p>
                    <input type="hidden" name="settings[google][redirect_uri]" value="<?php echo esc_url($settings['google']['redirect_uri'] ?? site_url('wp-json/cobra-ai/v1/google-auth/callback')); ?>">
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <div class="cobra-ai-section">
        <h2><?php echo esc_html__('Shortcode Usage', 'cobra-ai'); ?></h2>
        <p><?php echo esc_html__('Use this shortcode to display the Google login button on any page or post:', 'cobra-ai'); ?></p>
        <code>[cobra_google_login]</code>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Copy to clipboard functionality
    $('.copy-clipboard').on('click', function() {
        var text = $(this).data('clipboard');
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();
        document.execCommand('copy');
        tempInput.remove();
        
        var $button = $(this);
        var originalText = $button.text();
        $button.text('<?php echo esc_js(__('Copied!', 'cobra-ai')); ?>');
        
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
});
</script>

<style>
.cobra-ai-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px 20px;
    margin: 20px 0;
}

.cobra-ai-section h2 {
    margin-top: 0;
}

code {
    background: #f0f0f1;
    padding: 3px 5px;
    border-radius: 3px;
}
</style>