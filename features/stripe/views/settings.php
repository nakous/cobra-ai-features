<?php
// views/settings.php
namespace CobraAI\Features\Stripe\Views;

// Prevent direct access
defined('ABSPATH') || exit;
// print_r($settings)   ;// print_r($setting) to check the value of setting
// Get current mode and webhook status
$current_mode = $settings['mode'] ?? 'test';
$is_test_mode = $current_mode === 'test';
$webhook_status = $this->get_webhook()->get_status();
?>
<?php if (!$this->has_api_keys()): ?>
    <div class="notice notice-warning">
        <p>
            <?php echo esc_html__('API keys are not configured. Please add your Stripe API keys to enable the integration.', 'cobra-ai'); ?>
        </p>
    </div>
<?php endif; ?>
<div class="wrap cobra-stripe-settings">
    <h1><?php echo esc_html($this->name . ' ' . __('Settings', 'cobra-ai')); ?></h1>


    <?php
    // Display validation errors
    $this->display_settings_errors();
      ?>

    <?php
    // Display success message
    if (isset($_GET['settings-updated']) && empty($validation_errors)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Settings updated successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">
        <!-- Stripe Info -->
        <div class="card">
            <h2><?php echo esc_html__('Stripe Info', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Store Name', 'cobra-ai'); ?></th>
                    <td>
                        <input type="text"
                            name="settings[store_name]"
                            value="<?php echo esc_attr($settings['store_name'] ?? ''); ?>"
                            class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Your store name as it will appear on the Stripe checkout page.', 'cobra-ai'); ?>
                        </p>
                    </td>
              
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('App URL', 'cobra-ai'); ?></th>
                    <td>
                        <input type="url"
                            name="settings[app_url]"
                            value="<?php echo esc_attr($settings['app_url'] ?? ''); ?>"
                            class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Your App URL as it will appear on the Stripe checkout page.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
                <!-- partner_id -->
                <tr>
                    <th scope="row"><?php echo esc_html__('Partner ID', 'cobra-ai'); ?></th>
                    <td>
                        <input type="text"
                            name="settings[partner_id]"
                            value="<?php echo esc_attr($settings['partner_id'] ?? ''); ?>"
                            class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Your Partner ID as it will appear on the Stripe checkout page.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <!-- Mode Selection -->
        <div class="card">
            <h2><?php echo esc_html__('API Mode', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Mode', 'cobra-ai'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="settings[mode]" value="test"
                                    <?php checked($current_mode, 'test'); ?>>
                                <?php echo esc_html__('Test Mode', 'cobra-ai'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="settings[mode]" value="live"
                                    <?php checked($current_mode, 'live'); ?>>
                                <?php echo esc_html__('Live Mode', 'cobra-ai'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Select test mode while testing your integration.', 'cobra-ai'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Test Mode API Keys -->
        <div class="card" id="test-api-keys" <?php echo !$is_test_mode ? 'style="display:none;"' : ''; ?>>
            <h2><?php echo esc_html__('Test API Keys', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_publishable_key">
                            <?php echo esc_html__('Test Publishable Key', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                            id="test_publishable_key"
                            name="settings[test_publishable_key]"
                            value="<?php echo esc_attr($settings['test_publishable_key'] ?? ''); ?>"
                            class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Your test mode publishable key (starts with pk_test_).', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_secret_key">
                            <?php echo esc_html__('Test Secret Key', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="password"
                            id="test_secret_key"
                            name="settings[test_secret_key]"
                            value="<?php echo esc_attr($settings['test_secret_key'] ?? ''); ?>"
                            class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Your test mode secret key (starts with sk_test_).', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Live Mode API Keys -->
        <div class="card" id="live-api-keys" <?php echo $is_test_mode ? 'style="display:none;"' : ''; ?>>
            <h2><?php echo esc_html__('Live API Keys', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="live_publishable_key">
                            <?php echo esc_html__('Live Publishable Key', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                            id="live_publishable_key"
                            name="settings[live_publishable_key]"
                            value="<?php echo esc_attr($settings['live_publishable_key'] ?? ''); ?>"
                            class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Your live mode publishable key (starts with pk_live_).', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="live_secret_key">
                            <?php echo esc_html__('Live Secret Key', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="password"
                            id="live_secret_key"
                            name="settings[live_secret_key]"
                            value="<?php echo esc_attr($settings['live_secret_key'] ?? ''); ?>"
                            class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Your live mode secret key (starts with sk_live_).', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Webhook Settings -->
        <div class="card">
            <h2><?php echo esc_html__('Webhook Settings', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="webhook_secret">
                            <?php echo esc_html__('Webhook Secret', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="password"
                            id="webhook_secret"
                            name="settings[webhook_secret]"
                            value="<?php echo esc_attr($settings['webhook_secret'] ?? ''); ?>"
                            class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('Your webhook signing secret (starts with whsec_).', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Webhook URL', 'cobra-ai'); ?></th>
                    <td>
                        <code><?php echo esc_url(rest_url('cobra-ai/v1/stripe/webhook')); ?></code>
                        <p class="description">
                            <?php echo esc_html__('Add this URL to your Stripe webhook settings.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Advanced Settings -->
        <div class="card">
            <h2><?php echo esc_html__('Advanced Settings', 'cobra-ai'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="debug_mode">
                            <?php echo esc_html__('Debug Mode', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                id="debug_mode"
                                name="settings[debug_mode]"
                                value="1"
                                <?php checked($settings['debug_mode'] ?? false); ?>>
                            <?php echo esc_html__('Enable debug logging', 'cobra-ai'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Log detailed information about Stripe interactions.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="log_retention_days">
                            <?php echo esc_html__('Log Retention', 'cobra-ai'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number"
                            id="log_retention_days"
                            name="settings[log_retention_days]"
                            value="<?php echo esc_attr($settings['log_retention_days'] ?? 30); ?>"
                            min="1"
                            max="365"
                            class="small-text">
                        <?php echo esc_html__('days', 'cobra-ai'); ?>
                        <p class="description">
                            <?php echo esc_html__('Number of days to keep Stripe logs.', 'cobra-ai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        // Toggle API key sections based on mode
        $('input[name="settings[mode]"]').on('change', function() {
            const isTestMode = $(this).val() === 'test';
            $('#test-api-keys').toggle(isTestMode);
            $('#live-api-keys').toggle(!isTestMode);
        });

        // Verify API key button
        $('.verify-api-key').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const mode = $('input[name="settings[mode]"]:checked').val();
            const key = mode === 'test' ?
                $('#test_secret_key').val() :
                $('#live_secret_key').val();

            if (!key) {
                alert('<?php echo esc_js(__('Please enter an API key first.', 'cobra-ai')); ?>');
                return;
            }

            $button.prop('disabled', true)
                .text('<?php echo esc_js(__('Verifying...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_verify_stripe_key',
                    mode: mode,
                    key: key,
                    nonce: '<?php echo wp_create_nonce('cobra-ai-stripe'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('API key verified successfully!', 'cobra-ai')); ?>');
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('API key verification failed.', 'cobra-ai')); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Failed to verify API key. Please try again.', 'cobra-ai')); ?>');
                },
                complete: function() {
                    $button.prop('disabled', false)
                        .text('<?php echo esc_js(__('Verify API Key', 'cobra-ai')); ?>');
                }
            });
        });
    });
</script>