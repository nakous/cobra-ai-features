<?php
// views/admin/user-trackings.php
defined('ABSPATH') || exit;

// Check user exists
if (!$user) {
    wp_die(__('Invalid user ID', 'cobra-ai'));
}

// Get user stats
$stats = $this->feature->tracking->get_user_tracking_stats($user->ID);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php printf(
            __('AI Requests History: %s', 'cobra-ai'),
            esc_html($user->display_name)
        ); ?>
    </h1>

    <p class="description">
        <?php echo esc_html($user->user_email); ?>
        <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="page-title-action">
            <?php _e('Edit User', 'cobra-ai'); ?>
        </a>
    </p>

    <!-- User Stats -->
    <div class="user-stats">
        <div class="stats-card total">
            <span class="dashicons dashicons-chart-bar"></span>
            <div class="stats-content">
                <span class="stats-value"><?php echo number_format_i18n($stats['total']); ?></span>
                <span class="stats-label"><?php _e('Total Requests', 'cobra-ai'); ?></span>
            </div>
        </div>

        <div class="stats-card today">
            <span class="dashicons dashicons-clock"></span>
            <div class="stats-content">
                <span class="stats-value"><?php echo number_format_i18n($stats['today']); ?></span>
                <span class="stats-label"><?php _e('Today', 'cobra-ai'); ?></span>
            </div>
        </div>

        <div class="stats-card week">
            <span class="dashicons dashicons-calendar-alt"></span>
            <div class="stats-content">
                <span class="stats-value"><?php echo number_format_i18n($stats['week']); ?></span>
                <span class="stats-label"><?php _e('This Week', 'cobra-ai'); ?></span>
            </div>
        </div>

        <div class="stats-card month">
            <span class="dashicons dashicons-calendar"></span>
            <div class="stats-content">
                <span class="stats-value"><?php echo number_format_i18n($stats['month']); ?></span>
                <span class="stats-label"><?php _e('This Month', 'cobra-ai'); ?></span>
            </div>
        </div>
    </div>

    <!-- Provider Usage -->
    <?php if (!empty($stats['by_provider'])): ?>
    <div class="provider-usage">
        <h2><?php _e('Usage by Provider', 'cobra-ai'); ?></h2>
        <div class="provider-cards">
            <?php foreach ($stats['by_provider'] as $provider => $count): ?>
                <div class="provider-card">
                    <div class="provider-icon">
                        <?php
                        $icon_class = 'dashicons-share-alt';
                        switch ($provider) {
                            case 'openai':
                                $icon_class = 'dashicons-share-alt';
                                break;
                            case 'claude':
                                $icon_class = 'dashicons-superhero';
                                break;
                            case 'gemini':
                                $icon_class = 'dashicons-google';
                                break;
                            case 'perplexity':
                                $icon_class = 'dashicons-admin-network';
                                break;
                        }
                        ?>
                        <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                    </div>
                    <div class="provider-info">
                        <span class="provider-name"><?php echo esc_html(ucfirst($provider)); ?></span>
                        <span class="provider-count"><?php echo number_format_i18n($count); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" class="tracking-filters">
            <input type="hidden" name="page" value="cobra-ai-trackings">
            <input type="hidden" name="action" value="user">
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
            
            <div class="alignleft actions">
                <select name="ai_provider">
                    <option value=""><?php _e('All Providers', 'cobra-ai'); ?></option>
                    <?php foreach ($this->feature->get_active_providers() as $provider_id => $provider): ?>
                        <option value="<?php echo esc_attr($provider_id); ?>" 
                                <?php selected(isset($_GET['ai_provider']) ? $_GET['ai_provider'] : '', $provider_id); ?>>
                            <?php echo esc_html($provider->get_name()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value=""><?php _e('All Statuses', 'cobra-ai'); ?></option>
                    <?php
                    $statuses = [
                        'completed' => __('Completed', 'cobra-ai'),
                        'pending' => __('Pending', 'cobra-ai'),
                        'failed' => __('Failed', 'cobra-ai')
                    ];
                    foreach ($statuses as $key => $label):
                    ?>
                        <option value="<?php echo esc_attr($key); ?>" 
                                <?php selected(isset($_GET['status']) ? $_GET['status'] : '', $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="response_type">
                    <option value=""><?php _e('All Types', 'cobra-ai'); ?></option>
                    <?php
                    $types = [
                        'text' => __('Text', 'cobra-ai'),
                        'image' => __('Image', 'cobra-ai'),
                        'json' => __('JSON', 'cobra-ai')
                    ];
                    foreach ($types as $key => $label):
                    ?>
                        <option value="<?php echo esc_attr($key); ?>" 
                                <?php selected(isset($_GET['response_type']) ? $_GET['response_type'] : '', $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" 
                       name="start_date" 
                       value="<?php echo isset($_GET['start_date']) ? esc_attr($_GET['start_date']) : ''; ?>" 
                       placeholder="<?php esc_attr_e('Start Date', 'cobra-ai'); ?>">
                <input type="date" 
                       name="end_date" 
                       value="<?php echo isset($_GET['end_date']) ? esc_attr($_GET['end_date']) : ''; ?>" 
                       placeholder="<?php esc_attr_e('End Date', 'cobra-ai'); ?>">

                <?php submit_button(__('Filter', 'cobra-ai'), 'secondary', 'filter', false); ?>
            </div>

            <div class="alignright">
                <input type="search" 
                       id="tracking-search" 
                       name="s" 
                       value="<?php echo esc_attr(get_search_query()); ?>"
                       placeholder="<?php esc_attr_e('Search requests...', 'cobra-ai'); ?>">
            </div>
        </form>
    </div>

    <!-- Tracking List -->
    <form method="post">
        <?php
        $tracking_table->prepare_items();
        $tracking_table->display();
        ?>
    </form>
</div>

<!-- Tracking Details Modal -->
<div id="tracking-modal" class="tracking-modal" style="display: none;">
    <div class="tracking-modal-content">
        <span class="close">&times;</span>
        <div class="tracking-details"></div>
    </div>
</div>

<style>
.user-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stats-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
}

.stats-card .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    margin-right: 15px;
    color: #2271b1;
}

.stats-content {
    display: flex;
    flex-direction: column;
}

.stats-value {
    font-size: 24px;
    font-weight: 600;
    line-height: 1;
    margin-bottom: 5px;
}

.stats-label {
    color: #646970;
    font-size: 13px;
}

.provider-usage {
    margin: 30px 0;
}

.provider-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.provider-card {
    background: #f6f7f7;
    border-radius: 4px;
    padding: 15px;
    display: flex;
    align-items: center;
}

.provider-icon {
    margin-right: 10px;
}

.provider-icon .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.provider-info {
    display: flex;
    flex-direction: column;
}

.provider-name {
    font-weight: 500;
    margin-bottom: 2px;
}

.provider-count {
    color: #646970;
    font-size: 13px;
}

/* Responsive Adjustments */
@media screen and (max-width: 782px) {
    .user-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .provider-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media screen and (max-width: 480px) {
    .user-stats {
        grid-template-columns: 1fr;
    }

    .provider-cards {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // View tracking details
    $('.view-tracking').on('click', function(e) {
        e.preventDefault();
        const trackingId = $(this).data('tracking');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_ai_get_tracking_details',
                tracking_id: trackingId,
                nonce: '<?php echo wp_create_nonce("cobra_ai_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Populate and show modal
                    $('.tracking-details').html(response.data.html);
                    $('#tracking-modal').fadeIn();
                }
            }
        });
    });

    // Close modal
    $('.close, .tracking-modal').on('click', function(e) {
        if (e.target === this) {
            $('#tracking-modal').fadeOut();
        }
    });

    // Handle date range changes
    $('input[type="date"]').on('change', function() {
        const startDate = $('input[name="start_date"]').val();
        const endDate = $('input[name="end_date"]').val();
        
        if (startDate && endDate && startDate > endDate) {
            alert('<?php _e('Start date cannot be after end date', 'cobra-ai'); ?>');
            $(this).val('');
        }
    });
});
</script>