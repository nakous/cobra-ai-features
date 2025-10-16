<?php
/**
 * Stripe Subscriptions Settings Page
 * Enhanced with page creation functionality
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Get current settings and status
$settings = $this->get_settings();
$current_tab = $_GET['tab'] ?? 'general';

?>

<div class="wrap cobra-stripe-settings">
    <h1><?php echo esc_html($this->name . ' ' . __('Settings', 'cobra-ai')); ?></h1>
    
    <?php
    // Display validation errors
    $this->display_settings_errors();
    ?>
    
    <!-- Navigation Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=cobra-ai-<?php echo esc_attr($this->feature_id); ?>&tab=general"
            class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'cobra-ai'); ?>
        </a>
        <a href="?page=cobra-ai-<?php echo esc_attr($this->feature_id); ?>&tab=pages"
            class="nav-tab <?php echo $current_tab === 'pages' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Pages', 'cobra-ai'); ?>
        </a>
        <a href="?page=cobra-ai-<?php echo esc_attr($this->feature_id); ?>&tab=trial"
            class="nav-tab <?php echo $current_tab === 'trial' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Trial Settings', 'cobra-ai'); ?>
        </a>
        <a href="?page=cobra-ai-<?php echo esc_attr($this->feature_id); ?>&tab=webhooks"
            class="nav-tab <?php echo $current_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Webhooks', 'cobra-ai'); ?>
        </a>
    </h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">
        <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">

        <?php if ($current_tab === 'general'): ?>
            <!-- General Settings Tab -->
            <div class="card">
                <h2><?php echo esc_html__('General Settings', 'cobra-ai'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_feature">
                                <?php echo esc_html__('Enable Stripe Subscriptions', 'cobra-ai'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="enable_feature" 
                                   name="settings[enabled]" 
                                   value="1" 
                                   <?php checked($settings['enabled'] ?? false, true); ?>>
                            <p class="description">
                                <?php echo esc_html__('Enable subscription functionality on your site.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="currency">
                                <?php echo esc_html__('Default Currency', 'cobra-ai'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="currency" name="settings[currency]" class="regular-text">
                                <?php
                                $currencies = [
                                    'USD' => 'US Dollar',
                                    'EUR' => 'Euro',
                                    'GBP' => 'British Pound',
                                    'CAD' => 'Canadian Dollar',
                                    'AUD' => 'Australian Dollar',
                                    'JPY' => 'Japanese Yen'
                                ];
                                
                                foreach ($currencies as $code => $name):
                                ?>
                                    <option value="<?php echo esc_attr($code); ?>" 
                                            <?php selected($settings['currency'] ?? 'USD', $code); ?>>
                                        <?php echo esc_html($name . ' (' . $code . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Default currency for new subscription plans.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_emails">
                                <?php echo esc_html__('Enable Email Notifications', 'cobra-ai'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="enable_emails" 
                                   name="settings[enable_emails]" 
                                   value="1" 
                                   <?php checked($settings['enable_emails'] ?? true, true); ?>>
                            <p class="description">
                                <?php echo esc_html__('Send email notifications for subscription events.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

        <?php elseif ($current_tab === 'pages'): ?>
            <!-- Pages Settings Tab -->
            <div class="card">
                <h2><?php echo esc_html__('Page Management', 'cobra-ai'); ?></h2>
                <p><?php echo esc_html__('Configure pages for your subscription system. You can create new pages automatically or select existing ones.', 'cobra-ai'); ?></p>
                
                <table class="form-table">
                    <?php
                    // Get all pages
                    $pages = get_pages([
                        'sort_column' => 'post_title',
                        'sort_order' => 'ASC',
                    ]);

                    // Define page fields with their shortcodes
                    $page_fields = [
                        'checkout_page' => [
                            'label' => __('Checkout Page', 'cobra-ai'),
                            'description' => __('Page containing the checkout form for subscriptions.', 'cobra-ai'),
                            'shortcode' => '[stripe_checkout plan_id=""]',
                            'content' => '<h2>Choose Your Plan</h2>
[stripe_plans columns="3" show_trial="true"]

<h2>Checkout</h2>
[stripe_checkout]'
                        ],
                        'success_page' => [
                            'label' => __('Success Page', 'cobra-ai'),
                            'description' => __('Page users see after successful subscription.', 'cobra-ai'),
                            'shortcode' => '[stripe_success]',
                            'content' => '<h2>Welcome!</h2>
<p>Thank you for your subscription. Your account is now active!</p>
[stripe_success]

<h3>What\'s Next?</h3>
<p>You can now access all premium features. <a href="/account">Manage your subscription</a></p>'
                        ],
                        'cancel_page' => [
                            'label' => __('Cancel Page', 'cobra-ai'),
                            'description' => __('Page users see when they cancel their subscription.', 'cobra-ai'),
                            'shortcode' => '[stripe_cancel]',
                            'content' => '<h2>Subscription Cancelled</h2>
<p>We\'re sorry to see you go. Your subscription has been cancelled.</p>
[stripe_cancel]

<h3>Change Your Mind?</h3>
<p>You can always resubscribe at any time. <a href="' . 
                            get_permalink($settings['checkout_page'] ?? '') . '">View our plans</a></p>'
                        ],
                        'plans_page' => [
                            'label' => __('Plans Page', 'cobra-ai'),
                            'description' => __('Page displaying all available subscription plans.', 'cobra-ai'),
                            'shortcode' => '[stripe_plans]',
                            'content' => '<h2>Subscription Plans</h2>
<p>Choose the plan that works best for you.</p>
[stripe_plans columns="3" show_trial="true" show_features="true"]'
                        ]
                    ];

                    // Function to check if page exists and has correct content
                    $check_page_status = function($page_id, $shortcode) {
                        if (empty($page_id)) {
                            return ['exists' => false, 'has_shortcode' => false];
                        }
                        
                        $page = get_post($page_id);
                        if (!$page || $page->post_status !== 'publish') {
                            return ['exists' => false, 'has_shortcode' => false];
                        }
                        
                        $has_shortcode = strpos($page->post_content, 'stripe_') !== false;
                        return ['exists' => true, 'has_shortcode' => $has_shortcode, 'page' => $page];
                    };

                    foreach ($page_fields as $field => $config): 
                        $page_status = $check_page_status($settings[$field] ?? '', $config['shortcode']);
                        
                        // Reset setting if page doesn't exist
                        if (!empty($settings[$field]) && !$page_status['exists']) {
                            $settings[$field] = '';
                        }
                    ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($field); ?>">
                                    <?php echo esc_html($config['label']); ?>
                                </label>
                            </th>
                            <td>
                                <select name="settings[<?php echo esc_attr($field); ?>]"
                                    id="<?php echo esc_attr($field); ?>"
                                    class="regular-text page-select">
                                    <option value=""><?php _e('-- Select Page --', 'cobra-ai'); ?></option>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>"
                                            <?php selected($settings[$field] ?? '', $page->ID); ?>>
                                            <?php echo esc_html($page->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php
                                    echo esc_html($config['description']);
                                    echo ' ';
                                    printf(
                                        __('Suggested shortcode: %s', 'cobra-ai'),
                                        '<code>' . esc_html($config['shortcode']) . '</code>'
                                    );
                                    ?>
                                </p>
                                
                                <!-- Page Status & Actions -->
                                <div class="page-actions">
                                    <?php if (!empty($settings[$field]) && $page_status['exists']): ?>
                                        <a href="<?php echo admin_url('post.php?post=' . $settings[$field] . '&action=edit'); ?>" 
                                           class="button" target="_blank">
                                            <?php _e('Edit Page', 'cobra-ai'); ?>
                                        </a>
                                        <a href="<?php echo get_permalink($settings[$field]); ?>" 
                                           class="button" target="_blank">
                                            <?php _e('View Page', 'cobra-ai'); ?>
                                        </a>
                                        <button type="button" 
                                                class="button button-secondary reset-page" 
                                                data-field="<?php echo esc_attr($field); ?>">
                                            <?php _e('Reset', 'cobra-ai'); ?>
                                        </button>
                                        
                                        <?php if (!$page_status['has_shortcode']): ?>
                                            <p class="description" style="color: #d63638;">
                                                <strong><?php _e('Warning:', 'cobra-ai'); ?></strong> 
                                                <?php _e('Selected page doesn\'t contain the required shortcode.', 'cobra-ai'); ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="button"
                                            class="button create-page"
                                            data-page-type="<?php echo esc_attr($field); ?>"
                                            data-page-title="<?php echo esc_attr($config['label']); ?>"
                                            data-page-content="<?php echo esc_attr($config['content']); ?>">
                                            <?php _e('Create Page', 'cobra-ai'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

        <?php elseif ($current_tab === 'trial'): ?>
            <!-- Trial Settings Tab -->
            <div class="card">
                <h2><?php echo esc_html__('Trial Settings', 'cobra-ai'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_trial">
                                <?php echo esc_html__('Enable Trial Period', 'cobra-ai'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="enable_trial" 
                                   name="settings[enable_trial]" 
                                   value="1" 
                                   <?php checked($settings['enable_trial'] ?? false, true); ?>>
                            <p class="description">
                                <?php echo esc_html__('Allow trial periods for subscription plans.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="trial_days">
                                <?php echo esc_html__('Default Trial Days', 'cobra-ai'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="trial_days" 
                                   name="settings[trial_days]" 
                                   value="<?php echo esc_attr($settings['trial_days'] ?? 7); ?>" 
                                   min="1" 
                                   max="365" 
                                   class="small-text">
                            <p class="description">
                                <?php echo esc_html__('Default number of trial days for new plans.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

        <?php elseif ($current_tab === 'webhooks'): ?>
            <!-- Webhooks Settings Tab -->
            <div class="card">
                <h2><?php echo esc_html__('Webhook Configuration', 'cobra-ai'); ?></h2>
                <p><?php echo esc_html__('Webhook settings are managed by the main Stripe feature. These values are read-only for reference.', 'cobra-ai'); ?></p>
                
                <?php 
                // Get Stripe feature settings for webhook information
                $stripe_feature = $this->get_stripe_feature();
                $stripe_settings = $stripe_feature ? $stripe_feature->get_settings() : [];
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="webhook_endpoint_readonly">
                                <?php echo esc_html__('Webhook Endpoint URL', 'cobra-ai'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="webhook_endpoint_readonly" 
                                   value="<?php echo esc_attr(rest_url('cobra-ai/v1/stripe/webhook')); ?>" 
                                   class="large-text" 
                                   readonly>
                            <p class="description">
                                <?php 
                                echo esc_html__('This is the webhook URL configured in your Stripe dashboard.', 'cobra-ai');
                                echo ' ';
                                printf(
                                    '<a href="%s" target="_blank">%s</a>',
                                    esc_url(admin_url('admin.php?page=cobra-ai-stripe')),
                                    esc_html__('Manage in Stripe Settings', 'cobra-ai')
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="webhook_secret_readonly">
                                <?php echo esc_html__('Webhook Secret Status', 'cobra-ai'); ?>
                            </label>
                        </th>
                        <td>
                            <?php 
                            $webhook_secret = $stripe_settings['webhook_secret'] ?? '';
                            $has_secret = !empty($webhook_secret);
                            ?>
                            <div class="webhook-status">
                                <span class="status-indicator <?php echo $has_secret ? 'configured' : 'not-configured'; ?>">
                                    <?php if ($has_secret): ?>
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php echo esc_html__('Webhook secret is configured', 'cobra-ai'); ?>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-warning"></span>
                                        <?php echo esc_html__('Webhook secret not configured', 'cobra-ai'); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <p class="description">
                                <?php 
                                if ($has_secret) {
                                    echo esc_html__('Your webhook secret is properly configured in the Stripe feature settings.', 'cobra-ai');
                                } else {
                                    echo esc_html__('Please configure your webhook secret in the Stripe feature settings for secure webhook processing.', 'cobra-ai');
                                }
                                echo ' ';
                                printf(
                                    '<a href="%s" target="_blank">%s</a>',
                                    esc_url(admin_url('admin.php?page=cobra-ai-stripe')),
                                    esc_html__('Configure Webhook Secret', 'cobra-ai')
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Webhook Events', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <div class="webhook-events-info">
                                <h4><?php echo esc_html__('Required Webhook Events for Subscriptions:', 'cobra-ai'); ?></h4>
                                <ul class="webhook-events-list">
                                    <li><code>customer.subscription.created</code> - <?php echo esc_html__('New subscription created', 'cobra-ai'); ?></li>
                                    <li><code>customer.subscription.updated</code> - <?php echo esc_html__('Subscription updated', 'cobra-ai'); ?></li>
                                    <li><code>customer.subscription.deleted</code> - <?php echo esc_html__('Subscription cancelled', 'cobra-ai'); ?></li>
                                    <li><code>customer.subscription.trial_will_end</code> - <?php echo esc_html__('Trial ending notification', 'cobra-ai'); ?></li>
                                    <li><code>invoice.payment_succeeded</code> - <?php echo esc_html__('Payment successful', 'cobra-ai'); ?></li>
                                    <li><code>invoice.payment_failed</code> - <?php echo esc_html__('Payment failed', 'cobra-ai'); ?></li>
                                    <li><code>invoice.upcoming</code> - <?php echo esc_html__('Upcoming invoice notification', 'cobra-ai'); ?></li>
                                </ul>
                                <p class="description">
                                    <?php echo esc_html__('Make sure these events are configured in your Stripe Dashboard webhook settings.', 'cobra-ai'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <?php if ($stripe_feature): ?>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html__('Test Mode Status', 'cobra-ai'); ?>
                        </th>
                        <td>
                            <?php 
                            $test_mode = $stripe_settings['test_mode'] ?? false;
                            ?>
                            <div class="mode-status">
                                <span class="status-indicator <?php echo $test_mode ? 'test-mode' : 'live-mode'; ?>">
                                    <span class="dashicons dashicons-<?php echo $test_mode ? 'admin-settings' : 'yes-alt'; ?>"></span>
                                    <?php 
                                    echo $test_mode 
                                        ? esc_html__('Test Mode (Safe for development)', 'cobra-ai')
                                        : esc_html__('Live Mode (Processing real payments)', 'cobra-ai');
                                    ?>
                                </span>
                            </div>
                            <p class="description">
                                <?php 
                                echo $test_mode 
                                    ? esc_html__('All transactions are in test mode. No real charges will be made.', 'cobra-ai')
                                    : esc_html__('Live mode is active. Real payments will be processed.', 'cobra-ai');
                                ?>
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

        <?php endif; ?>

        <!-- Submit Button -->
        <div class="submit-section">
            <?php submit_button(__('Save Settings', 'cobra-ai')); ?>
        </div>
    </form>
</div>

<!-- JavaScript for page management -->
<script>
jQuery(document).ready(function($) {
    // Handle page creation
    $(document).on('click', '.create-page', function() {
        var button = $(this);
        var pageData = button.data();

        button.prop('disabled', true).text('<?php esc_js(_e('Creating...', 'cobra-ai')); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_create_stripe_page',
                page_type: pageData.pageType,
                page_title: pageData.pageTitle,
                page_content: pageData.pageContent,
                nonce: '<?php echo wp_create_nonce('cobra_create_stripe_page'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    var successMsg = $('<div class="notice notice-success is-dismissible"><p><strong>' + 
                        response.data.message + '</strong></p></div>');
                    $('.wrap h1').after(successMsg);
                    
                    // Reload page to show updated settings
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    alert(response.data.message || '<?php esc_js(_e('Failed to create page.', 'cobra-ai')); ?>');
                    button.prop('disabled', false)
                        .text('<?php esc_js(_e('Create Page', 'cobra-ai')); ?>');
                }
            },
            error: function() {
                alert('<?php esc_js(_e('Failed to create page.', 'cobra-ai')); ?>');
                button.prop('disabled', false)
                    .text('<?php esc_js(_e('Create Page', 'cobra-ai')); ?>');
            }
        });
    });

    // Handle page reset
    $(document).on('click', '.reset-page', function() {
        var button = $(this);
        var field = button.data('field');
        var container = button.closest('td');
        var select = container.find('select[name="settings[' + field + ']"]');
        
        if (confirm('<?php esc_js(_e('Are you sure you want to reset this page selection?', 'cobra-ai')); ?>')) {
            // Reset select value
            select.val('');
            
            // Trigger change event to update interface
            select.trigger('change');
            
            // Show success message
            var successMsg = $('<p class="description" style="color: #00a32a;"><strong><?php esc_js(_e('Page setting reset successfully.', 'cobra-ai')); ?></strong></p>');
            container.find('.page-actions').append(successMsg);
            setTimeout(function() {
                successMsg.fadeOut();
            }, 3000);
        }
    });

    // Handle page selection changes
    $('.page-select').on('change', function() {
        var select = $(this);
        var container = select.closest('td');
        var pageId = select.val();
        var field = select.attr('name').replace('settings[', '').replace(']', '');

        // Clear existing actions
        container.find('.page-actions').empty();

        if (pageId) {
            // Show page management buttons
            var actions = '<a href="<?php echo admin_url('post.php?action=edit&post='); ?>' + pageId + 
                         '" class="button" target="_blank"><?php esc_js(_e('Edit Page', 'cobra-ai')); ?></a> ' +
                         '<a href="<?php echo home_url('?p='); ?>' + pageId + 
                         '" class="button" target="_blank"><?php esc_js(_e('View Page', 'cobra-ai')); ?></a> ' +
                         '<button type="button" class="button button-secondary reset-page" ' +
                         'data-field="' + field + '"><?php esc_js(_e('Reset', 'cobra-ai')); ?></button>';
            
            container.find('.page-actions').html(actions);
        } else {
            // Show create page button
            var pageTitle = container.find('label').text();
            var shortcodeElement = container.find('code');
            var shortcode = shortcodeElement.length ? shortcodeElement.text() : '';
            
            // Get default content based on field type
            var defaultContent = getDefaultPageContent(field);
            
            var createBtn = '<button type="button" class="button create-page" ' +
                           'data-page-type="' + field + '" ' +
                           'data-page-title="' + pageTitle + '" ' +
                           'data-page-content="' + defaultContent + '">' +
                           '<?php esc_js(_e('Create Page', 'cobra-ai')); ?></button>';
            
            container.find('.page-actions').html(createBtn);
        }
    });
    
    // Function to get default content for page types
    function getDefaultPageContent(field) {
        var contents = {
            'checkout_page': '<h2>Choose Your Plan</h2>\n[stripe_plans columns="3" show_trial="true"]\n\n<h2>Checkout</h2>\n[stripe_checkout]',
            'success_page': '<h2>Welcome!</h2>\n<p>Thank you for your subscription. Your account is now active!</p>\n[stripe_success]\n\n<h3>What\'s Next?</h3>\n<p>You can now access all premium features. <a href="/account">Manage your subscription</a></p>',
            'cancel_page': '<h2>Subscription Cancelled</h2>\n<p>We\'re sorry to see you go. Your subscription has been cancelled.</p>\n[stripe_cancel]\n\n<h3>Change Your Mind?</h3>\n<p>You can always resubscribe at any time. <a href="">View our plans</a></p>',
            'plans_page': '<h2>Subscription Plans</h2>\n<p>Choose the plan that works best for you.</p>\n[stripe_plans columns="3" show_trial="true" show_features="true"]'
        };
        
        return contents[field] || '[stripe_' + field.replace('_page', '') + ']';
    }
    
    // Initialize page actions on load
    $('.page-select').each(function() {
        $(this).trigger('change');
    });
});
</script>

<style>
.cobra-stripe-settings .card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin: 20px 0;
    padding: 20px;
}

.cobra-stripe-settings .card h2 {
    margin-top: 0;
    font-size: 1.3em;
}

.page-actions {
    margin-top: 10px;
}

.page-actions .button {
    margin-right: 5px;
}

.submit-section {
    margin: 20px 0;
}

.nav-tab-wrapper {
    border-bottom: 1px solid #c3c4c7;
    margin-bottom: 20px;
}

.notice {
    margin: 20px 0 5px;
    padding: 10px 15px;
}

/* Webhook status styles */
.webhook-status .status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 4px;
    font-weight: 500;
}

.webhook-status .status-indicator.configured {
    background-color: #d1e7dd;
    color: #0a3622;
    border: 1px solid #a3cfbb;
}

.webhook-status .status-indicator.not-configured {
    background-color: #fff3cd;
    color: #664d03;
    border: 1px solid #ffecb5;
}

.mode-status .status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 4px;
    font-weight: 500;
}

.mode-status .status-indicator.test-mode {
    background-color: #cff4fc;
    color: #055160;
    border: 1px solid #b8daff;
}

.mode-status .status-indicator.live-mode {
    background-color: #d1e7dd;
    color: #0a3622;
    border: 1px solid #a3cfbb;
}

.webhook-events-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}

.webhook-events-info h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #495057;
}

.webhook-events-list {
    margin: 10px 0;
}

.webhook-events-list li {
    margin: 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.webhook-events-list code {
    background: #f1f3f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.9em;
    min-width: 280px;
    display: inline-block;
}
</style>