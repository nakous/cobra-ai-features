<?php
/**
 * Admin Dashboard View for Cobra AI Features
 * 
 * @package CobraAI
 */

namespace CobraAI\Admin\Views;
use function CobraAI\cobra_ai;
use function CobraAI\{
    cobra_ai_get_plugin_info,
    cobra_ai_get_settings,
    cobra_ai_db,
    cobra_ai_is_debug
};

defined('ABSPATH') || exit;



// Get plugin info and settings
$plugin_info = cobra_ai_get_plugin_info();
$settings = cobra_ai_get_settings();
$active_features = get_option('cobra_ai_enabled_features', []);

// Get system status
$system_status = [
    'features' => count($active_features),
    'php_version' => PHP_VERSION,
    'wp_version' => get_bloginfo('version'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize')
];

// Get recent logs
$recent_logs = cobra_ai_db()->get_recent_logs(5);

// Get analytics data for the last 30 days
$start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
$end_date = current_time('mysql');
?>

<div class="wrap cobra-ai-dashboard">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Cobra AI Dashboard', 'cobra-ai'); ?>
        <span class="version"><?php echo esc_html(COBRA_AI_VERSION); ?></span>
    </h1>

    <?php if (empty($active_features)): ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php 
                printf(
                    esc_html__('No features are currently active. %1$sGo to Features%2$s to enable some features.', 'cobra-ai'),
                    '<a href="' . esc_url(admin_url('admin.php?page=cobra-ai-features')) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- System Overview -->
    <div class="cobra-ai-stats-grid">
        <div class="cobra-ai-stat-card">
            <div class="stat-icon dashicons dashicons-plugins-checked"></div>
            <div class="stat-content">
                <h3><?php echo esc_html($system_status['features']); ?></h3>
                <p><?php echo esc_html__('Active Features', 'cobra-ai'); ?></p>
            </div>
        </div>

        <div class="cobra-ai-stat-card">
            <div class="stat-icon dashicons dashicons-performance"></div>
            <div class="stat-content">
                <h3><?php echo esc_html($system_status['memory_limit']); ?></h3>
                <p><?php echo esc_html__('Memory Limit', 'cobra-ai'); ?></p>
            </div>
        </div>

        <div class="cobra-ai-stat-card">
            <div class="stat-icon dashicons dashicons-clock"></div>
            <div class="stat-content">
                <h3><?php echo esc_html($system_status['max_execution_time']); ?>s</h3>
                <p><?php echo esc_html__('Max Execution Time', 'cobra-ai'); ?></p>
            </div>
        </div>

        <div class="cobra-ai-stat-card">
            <div class="stat-icon dashicons dashicons-upload"></div>
            <div class="stat-content">
                <h3><?php echo esc_html($system_status['upload_max_filesize']); ?></h3>
                <p><?php echo esc_html__('Upload Limit', 'cobra-ai'); ?></p>
            </div>
        </div>
    </div>

    <!-- Active Features Overview -->
    <div class="cobra-ai-card">
        <h2>
            <?php echo esc_html__('Active Features', 'cobra-ai'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-features')); ?>" class="page-title-action">
                <?php echo esc_html__('Manage Features', 'cobra-ai'); ?>
            </a>
        </h2>
        
        <div class="cobra-ai-features-grid">
            <?php
            if (!empty($active_features)):
                foreach ($active_features as $feature_id):
                    $feature = cobra_ai()->get_feature($feature_id);
                    if (!$feature) continue;
                    $info = $feature->get_info();
                    
                    // Get feature analytics
                    $analytics = cobra_ai_db()->get_feature_analytics($feature_id, [
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'limit' => 1
                    ]);
            ?>
                    <div class="feature-card">
                        <?php if (!empty($info['logo'])): ?>
                            <img src="<?php echo esc_url($info['logo']); ?>" 
                                 alt="<?php echo esc_attr($info['name']); ?>"
                                 class="feature-logo">
                        <?php endif; ?>

                        <h3><?php echo esc_html($info['name']); ?></h3>
                        <p><?php echo esc_html($info['description']); ?></p>

                        <div class="feature-meta">
                            <span class="version">v<?php echo esc_html($info['version']); ?></span>
                            <?php if (!empty($info['author'])): ?>
                                <span class="author">
                                    <?php echo esc_html(sprintf(__('by %s', 'cobra-ai'), $info['author'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="feature-stats">
                            <?php if (!empty($analytics)): ?>
                                <div class="stat">
                                    <span class="label"><?php echo esc_html__('Usage', 'cobra-ai'); ?></span>
                                    <span class="value"><?php echo esc_html($analytics[0]['metric_value'] ?? 0); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="feature-actions">
                            <?php if (method_exists($feature, 'has_settings') && $feature->has_settings()): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-' . $feature_id)); ?>" 
                                   class="button">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <?php echo esc_html__('Settings', 'cobra-ai'); ?>
                                </a>
                            <?php endif; ?>

                            <button type="button" 
                                    class="button help-button"
                                    data-feature="<?php echo esc_attr($feature_id); ?>">
                                <span class="dashicons dashicons-editor-help"></span>
                                <?php echo esc_html__('Help', 'cobra-ai'); ?>
                            </button>
                        </div>
                    </div>
                <?php
                endforeach;
            else:
                ?>
                <div class="notice notice-info">
                    <p>
                        <?php
                        printf(
                            esc_html__('No features are currently active. %1$sGo to the Features page%2$s to enable some features.', 'cobra-ai'),
                            '<a href="' . esc_url(admin_url('admin.php?page=cobra-ai-features')) . '">',
                            '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="cobra-ai-card">
        <h2><?php echo esc_html__('Recent Activity', 'cobra-ai'); ?></h2>
        
        <?php if (!empty($recent_logs)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Time', 'cobra-ai'); ?></th>
                        <th><?php echo esc_html__('Level', 'cobra-ai'); ?></th>
                        <th><?php echo esc_html__('Message', 'cobra-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(
                                human_time_diff(
                                    strtotime($log->created_at), 
                                    current_time('timestamp')
                                ) . ' ' . __('ago', 'cobra-ai')
                            ); ?></td>
                            <td>
                                <span class="log-level log-level-<?php echo esc_attr($log->level); ?>">
                                    <?php echo esc_html(ucfirst($log->level)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->message); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="description">
                <?php echo esc_html__('No recent activity to display.', 'cobra-ai'); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="cobra-ai-card">
        <h2><?php echo esc_html__('Quick Actions', 'cobra-ai'); ?></h2>
        <div class="quick-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-features')); ?>" 
               class="button button-primary">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php echo esc_html__('Manage Features', 'cobra-ai'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-settings')); ?>" 
               class="button button-primary">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php echo esc_html__('Global Settings', 'cobra-ai'); ?>
            </a>

            <?php if (cobra_ai_is_debug()): ?>
                <button type="button" class="button" id="clear-cache">
                    <span class="dashicons dashicons-trash"></span>
                    <?php echo esc_html__('Clear Cache', 'cobra-ai'); ?>
                </button>
            <?php endif; ?>

            <a href="https://docs.example.com/cobra-ai" 
               target="_blank" 
               class="button">
                <span class="dashicons dashicons-book"></span>
                <?php echo esc_html__('Documentation', 'cobra-ai'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div id="feature-help-modal" class="cobra-modal">
    <div class="cobra-modal-content">
        <div class="cobra-modal-header">
            <h2></h2>
            <button type="button" class="close-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="cobra-modal-body">
            <div class="help-content"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Help modal functionality
    $('.help-button').on('click', function() {
        const featureId = $(this).data('feature');
        const $modal = $('#feature-help-modal');

        $modal.find('.help-content').html(
            '<div class="loading"><?php echo esc_js(__('Loading documentation...', 'cobra-ai')); ?></div>'
        );
        
        $modal.show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_ai_load_feature_help',
                feature: featureId,
                nonce: '<?php echo wp_create_nonce('cobra-ai-help'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $modal.find('.cobra-modal-header h2').text(response.data.title);
                    $modal.find('.help-content').html(response.data.content);
                } else {
                    $modal.find('.help-content').html(
                        '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                    );
                }
            },
            error: function() {
                $modal.find('.help-content').html(
                    '<div class="notice notice-error"><p><?php echo esc_js(__('Error loading documentation', 'cobra-ai')); ?></p></div>'
                );
            }
        });
    });

    // Clear cache functionality
    $('#clear-cache').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_ai_clear_cache',
                nonce: '<?php echo wp_create_nonce('cobra-ai-admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Failed to clear cache', 'cobra-ai')); ?>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
</script>