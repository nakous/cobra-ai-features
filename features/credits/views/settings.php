<?php
// /Admin/views/settings.php
defined('ABSPATH') || exit;

// Get current settings
$settings = $this->get_settings();

// Credit types with their labels
$available_types = \CobraAI\Features\Credits\CreditType::get_all();
// print_r($settings);
// Display settings errors
$this->display_settings_errors();
?>

<div class="wrap">
    <h1><?php _e('Credits Settings', 'cobra-ai'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_feature_settings_' . $this->get_feature_id()); ?>
        <input type="hidden" name="action" value="cobra_ai_save_feature_settings">
        <input type="hidden" name="feature_id" value="<?php echo esc_attr($this->get_feature_id()); ?>">

        <!-- Settings navigation tabs -->
        <nav class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'cobra-ai'); ?></a>
            <a href="#credits" class="nav-tab"><?php _e('Credit Types', 'cobra-ai'); ?></a>
            <a href="#notifications" class="nav-tab"><?php _e('Notifications', 'cobra-ai'); ?></a>
            <a href="#expiration" class="nav-tab"><?php _e('Expiration', 'cobra-ai'); ?></a>
            <a href="#display" class="nav-tab"><?php _e('Display', 'cobra-ai'); ?></a>
        </nav>

        <div class="tab-content">
            <!-- General Settings -->
            <div id="general" class="tab-pane active">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Credit Unit', 'cobra-ai'); ?></th>
                        <td>
                            <select name="settings[general][credit_unit]" id="credit_unit">
                                <option value="points" <?php selected($settings['general']['credit_unit'], 'points'); ?>>
                                    <?php _e('Points', 'cobra-ai'); ?>
                                </option>
                                <option value="currency" <?php selected($settings['general']['credit_unit'], 'currency'); ?>>
                                    <?php _e('Currency', 'cobra-ai'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Credit Name', 'cobra-ai'); ?></th>
                        <td>
                            <input type="text" 
                                   name="settings[general][credit_name]" 
                                   value="<?php echo esc_attr($settings['general']['credit_name']); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Name for credits (e.g., "Credits", "Points", "Coins")', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Credit Symbol', 'cobra-ai'); ?></th>
                        <td>
                            <input type="text" 
                                   name="settings[general][credit_symbol]" 
                                   value="<?php echo esc_attr($settings['general']['credit_symbol']); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Symbol or abbreviation (e.g., "pts", "$", "€")', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Credit Types Settings -->
            <div id="credits" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enabled Credit Types', 'cobra-ai'); ?></th>
                        <td>
                            <?php foreach ($available_types as $type_id => $type): ?>
                                <label class="credit-type-option">
                                    <input type="checkbox" 
                                           name="settings[general][credit_types][]" 
                                           value="<?php echo esc_attr($type_id); ?>"
                                           <?php checked(in_array($type_id, $settings['general']['credit_types'])); ?>>
                                    <span class="dashicons <?php echo esc_attr($type['icon']); ?>"></span>
                                    <?php echo esc_html($type['name']); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Type Order', 'cobra-ai'); ?></th>
                        <td>
                            <ul id="credit-type-order" class="sortable-list">
                                <?php foreach ($settings['general']['type_order'] as $type_id): ?>
                                    <?php if (isset($available_types[$type_id])): ?>
                                        <li class="sortable-item" data-type="<?php echo esc_attr($type_id); ?>">
                                            <input type="hidden" 
                                                   name="settings[general][type_order][]" 
                                                   value="<?php echo esc_attr($type_id); ?>">
                                            <span class="dashicons <?php echo esc_attr($available_types[$type_id]['icon']); ?>"></span>
                                            <?php echo esc_html($available_types[$type_id]['name']); ?>
                                            <span class="dashicons dashicons-menu drag-handle"></span>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                            <p class="description">
                                <?php _e('Drag to reorder credit types. This affects the order of consumption.', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Notifications Settings -->
            <div id="notifications" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Expiration Notices', 'cobra-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="settings[notifications][enable_expiration_notice]" 
                                       value="1"
                                       <?php checked($settings['notifications']['enable_expiration_notice']); ?>>
                                <?php _e('Send notifications when credits are about to expire', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Notice Days', 'cobra-ai'); ?></th>
                        <td>
                            <input type="number" 
                                   name="settings[notifications][expiration_notice_days]" 
                                   value="<?php echo esc_attr($settings['notifications']['expiration_notice_days']); ?>" 
                                   min="1" 
                                   class="small-text">
                            <p class="description">
                                <?php _e('Days before expiration to send notification', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Email Template', 'cobra-ai'); ?></th>
                        <td>
                            <textarea name="settings[notifications][notification_email_template]" 
                                      rows="10" 
                                      class="large-text code"><?php 
                                      if (isset($settings['notifications']['notification_email_template']))
                                echo esc_textarea($settings['notifications']['notification_email_template']); 
                            ?></textarea>
                            <p class="description">
                                <?php _e('Available placeholders: {site_name}, {user_name}, {credit_list}, {credits_page_url}', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Expiration Settings -->
            <div id="expiration" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Duration', 'cobra-ai'); ?></th>
                        <td>
                            <input type="number" 
                                   name="settings[expiration][default_duration]" 
                                   value="<?php echo esc_attr($settings['expiration']['default_duration']); ?>" 
                                   min="0" 
                                   class="small-text">
                            <p class="description">
                                <?php _e('Default number of days before credits expire (0 = never)', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Grace Period', 'cobra-ai'); ?></th>
                        <td>
                            <input type="number" 
                                   name="settings[expiration][grace_period]" 
                                   value="<?php
                                   if (isset($settings['expiration']['grace_period']))
                                    echo esc_attr($settings['expiration']['grace_period']); 
                                    ?>" 
                                   min="0" 
                                   class="small-text">
                            <p class="description">
                                <?php _e('Number of days after expiration before credits are removed', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto Expire', 'cobra-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="settings[expiration][auto_expire]" 
                                       value="1"
                                       <?php checked($settings['expiration']['auto_expire']); ?>>
                                <?php _e('Automatically expire credits after expiration date', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Display Settings -->
            <div id="display" class="tab-pane">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Show in Profile', 'cobra-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="settings[display][show_in_profile]" 
                                       value="1"
                                       <?php checked($settings['display']['show_in_profile']); ?>>
                                <?php _e('Show credit information in user profile', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Show in Admin List', 'cobra-ai'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="settings[display][show_in_admin_list]" 
                                       value="1"
                                       <?php checked($settings['display']['show_in_admin_list']); ?>>
                                <?php _e('Show credit column in admin users list', 'cobra-ai'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('History Items', 'cobra-ai'); ?></th>
                        <td>
                            <input type="number" 
                                   name="settings[display][history_per_page]" 
                                   value="<?php echo esc_attr($settings['display']['history_per_page']); ?>" 
                                   min="1" 
                                   class="small-text">
                            <p class="description">
                                <?php _e('Number of items to show per page in credit history', 'cobra-ai'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<style>
.credit-type-option {
    display: block;
    margin-bottom: 8px;
}

.credit-type-option .dashicons {
    margin-right: 5px;
    vertical-align: middle;
}

.sortable-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.sortable-item {
    padding: 8px;
    margin-bottom: 5px;
    background: #fff;
    border: 1px solid #ddd;
    cursor: move;
    display: flex;
    align-items: center;
}

.sortable-item .dashicons {
    margin-right: 8px;
}

.drag-handle {
    margin-left: auto;
    color: #999;
}

.tab-content > .tab-pane {
    display: none;
}

.tab-content > .active {
    display: block;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show content
        const target = $(this).attr('href').substring(1);
        $('.tab-pane').removeClass('active');
        $('#' + target).addClass('active');
    });

    // Sortable credit types
    if ($.fn.sortable) {
        $('#credit-type-order').sortable({
            handle: '.drag-handle',
            axis: 'y',
            update: function(event, ui) {
                // Update hidden inputs if needed
            }
        });
    }

    // Show first tab
    $('.nav-tab').first().click();
});
</script>