<?php

/**
 * Features Management View
 * 
 * @package CobraAI
 */

namespace CobraAI\Admin\Views;

use function CobraAI\{
    cobra_ai,
    cobra_ai_db
};


defined('ABSPATH') || exit;

// Get features information
// $available_features = cobra_ai()->get_features(true);  // Get all features including inactive
// $active_features = get_option('cobra_ai_enabled_features', []);
$feature_errors = [];

// Get feature dependencies and status
foreach ($available_features as $feature_id => $feature) {
    try {
        $dependencies = $feature->check_dependencies();
        $feature_errors[$feature_id] = is_array($dependencies) ? $dependencies : [];
    } catch (\Exception $e) {
        $feature_errors[$feature_id] = [];
        error_log('Error checking dependencies for feature ' . $feature_id . ': ' . $e->getMessage());
    }
}
?>
 
<div class="wrap cobra-ai-features">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Cobra AI Features', 'cobra-ai'); ?>
        <span class="title-count"><?php echo count($active_features) . '/' . count($available_features); ?></span>
    </h1>

    <?php if (isset($_GET['feature_updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Feature settings updated successfully.', 'cobra-ai'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Features Filter -->
    <div class="cobra-ai-features-filter">
        <div class="filter-group">
            <label for="feature-status">
                <?php echo esc_html__('Status:', 'cobra-ai'); ?>
            </label>
            <select id="feature-status">
                <option value="all"><?php echo esc_html__('All', 'cobra-ai'); ?></option>
                <option value="active"><?php echo esc_html__('Active', 'cobra-ai'); ?></option>
                <option value="inactive"><?php echo esc_html__('Inactive', 'cobra-ai'); ?></option>
            </select>
        </div>

        <div class="filter-group">
            <label for="feature-search">
                <?php echo esc_html__('Search:', 'cobra-ai'); ?>
            </label>
            <input type="text" id="feature-search" placeholder="<?php echo esc_attr__('Search features...', 'cobra-ai'); ?>">
        </div>
    </div>

    <!-- Features Grid -->
    <div class="cobra-ai-features-grid">

        <?php if (!empty($available_features)): ?>

            <?php foreach ($available_features as $feature_id => $feature):
                $info = $feature->get_info();
                $is_active = in_array($feature_id, $active_features);
                $has_errors = !empty($feature_errors[$feature_id]);
                $can_activate = empty($feature_errors[$feature_id]);

                // Get feature health status
                $health = $feature->get_health_status();
            ?>
                <div class="feature-card <?php echo $is_active ? 'active' : ''; ?> <?php echo $has_errors ? 'has-errors' : ''; ?>"
                    id="feature-<?php echo esc_attr($feature_id); ?>"
                    data-feature-id="<?php echo esc_attr($feature_id); ?>"
                    data-feature-status="<?php echo $is_active ? 'active' : 'inactive'; ?>">

                    <div class="feature-header">
                        <div class="feature-title">
                            <h3>
                                <?php echo esc_html($info['name']); ?>
                              
                                <?php if ($is_active && $health['status'] === 'healthy'): ?>
                                    <span class="health-status healthy" title="<?php echo esc_attr__('Feature is healthy', 'cobra-ai'); ?>">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </span>
                                <?php elseif ($is_active && $health['status'] === 'warning'): ?>
                                    <span class="health-status warning" title="<?php echo esc_attr__('Feature has warnings', 'cobra-ai'); ?>">
                                        <span class="dashicons dashicons-warning"></span>
                                        <ul class="health-warnings">
                                            <?php foreach ($health['warnings'] as $warning): ?>
                                                <li><?php echo esc_html($warning); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </span>
                                   
                                <?php elseif ($is_active && $health['status'] === 'error'): ?>
                                     <span class="health-status error" title="<?php echo esc_attr__('Feature has errors', 'cobra-ai'); ?>">
                                        <span class="dashicons dashicons-no-alt"></span>
                                        <ul class="health-errors">
                                            <?php foreach ($health['errors'] as $error): ?>
                                                <li><?php echo esc_html($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </span>


                                <?php endif; ?>
                            </h3>
                            <span class="version">v<?php echo esc_html($info['version']); ?></span>
                            <button type="button"
                                class="help-button"
                                data-feature="<?php echo esc_attr($feature_id); ?>"
                                title="<?php esc_attr_e('View Documentation', 'cobra-ai'); ?>">
                                <span class="dashicons dashicons-editor-help"></span>
                            </button>
                        </div>

                        <div class="feature-toggle">
                            <label class="switch" title="<?php echo $can_activate ? '' : esc_attr__('Cannot activate - dependencies not met', 'cobra-ai'); ?>">
                                <input type="checkbox"
                                    class="feature-toggle-input"
                                    data-feature="<?php echo esc_attr($feature_id); ?>"
                                    <?php checked($is_active); ?>
                                    <?php disabled(!$can_activate); ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>

                    <div class="feature-content">
                        <?php if (!empty($info['logo'])): ?>
                            <img src="<?php echo esc_url($info['logo']); ?>"
                                alt="<?php echo esc_attr($info['name']); ?>"
                                class="feature-logo"
                                width="42"
                                height="42">
                        <?php endif; ?>

                        <p class="description"><?php echo esc_html($info['description']); ?></p>

                        <?php if (!empty($info['author'])): ?>
                            <p class="author">
                                <?php echo sprintf(
                                    esc_html__('By %s', 'cobra-ai'),
                                    esc_html($info['author'])
                                ); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($has_errors): ?>
                            <div class="notice notice-warning inline">
                                <p>
                                    <strong><?php echo esc_html__('Required Features:', 'cobra-ai'); ?></strong>
                                    <?php
                                    if (is_array($feature_errors[$feature_id])) {
                                        $dep_names = [];
                                        foreach ($feature_errors[$feature_id] as $dep) {
                                            $dep_feature = cobra_ai()->get_feature($dep);
                                            if ($dep_feature) {
                                                $dep_info = $dep_feature->get_info();
                                                $dep_names[] = $dep_info['name'];
                                            } else {
                                                $dep_names[] = $dep; // Fallback to ID if feature not found
                                            }
                                        }
                                        echo esc_html(implode(', ', $dep_names));
                                    } else {
                                        // If feature_errors[$feature_id] is boolean
                                        echo esc_html__('One or more dependencies are missing', 'cobra-ai');
                                    }
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_active && !empty($health_status['warnings'])): ?>
                            <div class="notice notice-warning inline">
                                <p><?php echo esc_html($health_status['message']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="feature-meta">
                        <?php if (!empty($info['requires'])): ?>
                            <div class="meta-item">
                                <span class="dashicons dashicons-admin-plugins"></span>
                                <?php echo esc_html__('Dependencies:', 'cobra-ai'); ?>
                                <?php
                                $deps = [];
                                foreach ($info['requires'] as $dep) {
                                    $dep_feature = cobra_ai()->get_feature($dep);
                                    if ($dep_feature) {
                                        $dep_info = $dep_feature->get_info();
                                        $deps[] = $dep_info['name'];
                                    } else {
                                        $deps[] = $dep; // Fallback to ID if feature not found
                                    }
                                }
                                echo esc_html(implode(', ', $deps));
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_active): ?>
                            <?php
                            $usage_stats = cobra_ai_db()->get_feature_analytics($feature_id, [
                                'start_date' => date('Y-m-d H:i:s', strtotime('-30 days')),
                                'limit' => 1
                            ]);
                            if (!empty($usage_stats)): ?>
                                <div class="meta-item">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                    <?php echo esc_html__('30-Day Usage:', 'cobra-ai'); ?>
                                    <?php echo esc_html($usage_stats[0]['metric_value']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="feature-footer">
                        <?php if ($is_active): ?>
                            <?php if (method_exists($feature, 'has_settings') && $feature->has_settings()): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-' . $feature_id)); ?>"
                                    class="button">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <?php echo esc_html__('Settings', 'cobra-ai'); ?>
                                </a>
                            <?php endif; ?>

                            <button type="button"
                                class="button button-secondary view-logs"
                                data-feature="<?php echo esc_attr($feature_id); ?>">
                                <span class="dashicons dashicons-list-view"></span>
                                <?php echo esc_html__('View Logs', 'cobra-ai'); ?>
                            </button>
                        <?php endif; ?>

                        <?php if (!empty($info['documentation'])): ?>
                            <a href="<?php echo esc_url($info['documentation']); ?>"
                                class="button"
                                target="_blank">
                                <span class="dashicons dashicons-book"></span>
                                <?php echo esc_html__('Documentation', 'cobra-ai'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notice notice-info">
                <p><?php echo esc_html__('No features are currently available.', 'cobra-ai'); ?></p>
            </div>
        <?php endif; ?>
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

<!-- Logs Modal -->
<div id="feature-logs-modal" class="cobra-modal">
    <div class="cobra-modal-content">
        <div class="cobra-modal-header">
            <h2><?php echo esc_html__('Feature Logs', 'cobra-ai'); ?></h2>
            <button type="button" class="close-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="cobra-modal-body">
            <div class="logs-content"></div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // Feature toggle handling
        $('.feature-toggle-input').on('change', function() {
            const featureId = $(this).data('feature');
            const isEnabled = $(this).is(':checked');
            const $card = $(`#feature-${featureId}`);

            // Disable toggle during request
            $(this).prop('disabled', true);

            // Show loading state
            $card.addClass('loading');
            $card.append('<div class="loading-overlay"><span class="spinner is-active"></span></div>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_toggle_feature',
                    feature_id: featureId,
                    enabled: isEnabled,
                    nonce: '<?php echo wp_create_nonce('cobra-ai-admin'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                 
                        // Show success message
                        const $notice = $('<div class="notice notice-success is-dismissible"><p>' +
                            response.data.message + '</p></div>');
                        $('.wrap.cobra-ai-features h1').after($notice);

                        // Reload if needed
                        if (response.data.reload) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                       
                        // Revert toggle and show error
                        $(this).prop('checked', !isEnabled);
                        alert(response.data.message || '<?php echo esc_js(__('Failed to update feature status.', 'cobra-ai')); ?>');
                    }
                },
                error: function() {
                    
                    // Revert toggle on error
                    $(this).prop('checked', !isEnabled);
                    alert('<?php echo esc_js(__('An error occurred. Please try again.', 'cobra-ai')); ?>');
                },
                complete: function() {
                    // Remove loading state
                    $card.removeClass('loading');
                    $('.loading-overlay').remove();
                    $(this).prop('disabled', false);
                }
            });
        });

        // Feature filtering
        $('#feature-status, #feature-search').on('change keyup', function() {
            const status = $('#feature-status').val();
            const search = $('#feature-search').val().toLowerCase();

            $('.feature-card').each(function() {
                const $card = $(this);
                const cardStatus = $card.data('feature-status');
                const cardText = $card.text().toLowerCase();

                const statusMatch = status === 'all' || cardStatus === status;
                const searchMatch = cardText.includes(search);

                $card.toggle(statusMatch && searchMatch);
            });
        });

        // Help modal
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

        // View logs
        $('.view-logs').on('click', function() {
            const featureId = $(this).data('feature');
            const $modal = $('#feature-logs-modal');
            const $content = $modal.find('.logs-content');

            // Show loading state
            $content.html(
                '<div class="loading">' +
                '<span class="spinner is-active"></span> ' +
                '<?php echo esc_js(__('Loading logs...', 'cobra-ai')); ?>' +
                '</div>'
            );

            $modal.show();

            // Fetch logs
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_get_feature_logs',
                    feature: featureId,
                    nonce: '<?php echo wp_create_nonce('cobra-ai-logs'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.logs.length > 0) {
                            let logsHtml = '<div class="logs-wrapper">';

                            // Add filters
                            logsHtml += '<div class="logs-filters">';
                            logsHtml += '<select class="log-level-filter">';
                            logsHtml += '<option value="all"><?php echo esc_js(__('All Levels', 'cobra-ai')); ?></option>';
                            logsHtml += '<option value="error"><?php echo esc_js(__('Errors', 'cobra-ai')); ?></option>';
                            logsHtml += '<option value="warning"><?php echo esc_js(__('Warnings', 'cobra-ai')); ?></option>';
                            logsHtml += '<option value="info"><?php echo esc_js(__('Info', 'cobra-ai')); ?></option>';
                            logsHtml += '</select>';

                            logsHtml += '<input type="text" class="log-search" placeholder="<?php echo esc_js(__('Search logs...', 'cobra-ai')); ?>">';
                            logsHtml += '</div>';

                            // Add logs table
                            logsHtml += '<table class="wp-list-table widefat fixed striped">';
                            logsHtml += '<thead><tr>';
                            logsHtml += '<th><?php echo esc_js(__('Time', 'cobra-ai')); ?></th>';
                            logsHtml += '<th><?php echo esc_js(__('Level', 'cobra-ai')); ?></th>';
                            logsHtml += '<th><?php echo esc_js(__('Message', 'cobra-ai')); ?></th>';
                            logsHtml += '<th><?php echo esc_js(__('Context', 'cobra-ai')); ?></th>';
                            logsHtml += '</tr></thead><tbody>';

                            response.data.logs.forEach(function(log) {
                                logsHtml += '<tr class="log-entry" data-level="' + log.level + '">';
                                logsHtml += '<td>' + log.created_at + '</td>';
                                logsHtml += '<td><span class="log-level-' + log.level + '">' + log.level + '</span></td>';
                                logsHtml += '<td>' + log.message + '</td>';
                                logsHtml += '<td>';
                                if (log.context) {
                                    logsHtml += '<button type="button" class="button button-small view-context" ' +
                                        'data-context="' + btoa(JSON.stringify(log.context)) + '">' +
                                        '<?php echo esc_js(__('View Context', 'cobra-ai')); ?></button>';
                                }
                                logsHtml += '</td>';
                                logsHtml += '</tr>';
                            });

                            logsHtml += '</tbody></table>';

                            // Add pagination if needed
                            if (response.data.total_pages > 1) {
                                logsHtml += '<div class="tablenav"><div class="tablenav-pages">';
                                logsHtml += '<span class="pagination-links">';

                                for (let i = 1; i <= response.data.total_pages; i++) {
                                    logsHtml += '<a class="page-numbers' + (i === 1 ? ' current' : '') + '" ' +
                                        'data-page="' + i + '">' + i + '</a>';
                                }

                                logsHtml += '</span></div></div>';
                            }

                            logsHtml += '</div>';
                            $content.html(logsHtml);

                            // Initialize log filters
                            initializeLogFilters($content);
                        } else {
                            $content.html(
                                '<div class="notice notice-info"><p>' +
                                '<?php echo esc_js(__('No logs found for this feature.', 'cobra-ai')); ?>' +
                                '</p></div>'
                            );
                        }
                    } else {
                        $content.html(
                            '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                        );
                    }
                },
                error: function() {
                    $content.html(
                        '<div class="notice notice-error"><p>' +
                        '<?php echo esc_js(__('Failed to load logs. Please try again.', 'cobra-ai')); ?>' +
                        '</p></div>'
                    );
                }
            });
        });

        // Initialize log filters
        function initializeLogFilters($content) {
            const $levelFilter = $content.find('.log-level-filter');
            const $searchFilter = $content.find('.log-search');
            const $logEntries = $content.find('.log-entry');

            function filterLogs() {
                const level = $levelFilter.val();
                const search = $searchFilter.val().toLowerCase();

                $logEntries.each(function() {
                    const $entry = $(this);
                    const entryLevel = $entry.data('level');
                    const entryText = $entry.text().toLowerCase();

                    const levelMatch = level === 'all' || entryLevel === level;
                    const searchMatch = !search || entryText.includes(search);

                    $entry.toggle(levelMatch && searchMatch);
                });
            }

            $levelFilter.on('change', filterLogs);
            $searchFilter.on('keyup', filterLogs);
        }

        // View log context
        $(document).on('click', '.view-context', function() {
            const context = JSON.parse(atob($(this).data('context')));
            const $modal = $('<div class="cobra-modal context-modal">')
                .appendTo('body')
                .html(
                    '<div class="cobra-modal-content">' +
                    '<div class="cobra-modal-header">' +
                    '<h2><?php echo esc_js(__('Log Context', 'cobra-ai')); ?></h2>' +
                    '<button type="button" class="close-modal">' +
                    '<span class="dashicons dashicons-no-alt"></span>' +
                    '</button>' +
                    '</div>' +
                    '<div class="cobra-modal-body">' +
                    '<pre>' + JSON.stringify(context, null, 2) + '</pre>' +
                    '</div>' +
                    '</div>'
                )
                .show();

            $modal.find('.close-modal').on('click', function() {
                $modal.remove();
            });
        });

        // Modal close buttons
        $('.close-modal').on('click', function() {
            $(this).closest('.cobra-modal').hide();
        });

        // Close modals when clicking outside
        $(window).on('click', function(event) {
            if ($(event.target).hasClass('cobra-modal')) {
                $('.cobra-modal').hide();
            }
        });

        // Prevent modal close when clicking inside
        $('.cobra-modal-content').on('click', function(event) {
            event.stopPropagation();
        });
    });
</script>