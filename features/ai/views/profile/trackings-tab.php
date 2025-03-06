<?php
// views/profile/trackings-tab.php
defined('ABSPATH') || exit;

// Get user stats
$stats = $this->feature->tracking->get_user_tracking_stats($user->ID);

// Get settings
$settings = $this->feature->get_settings();

// Get daily limit
$daily_limit = $settings['limits']['requests_per_day'] ?? 0;
$today_count = $stats['today'];
$remaining = $daily_limit > 0 ? max(0, $daily_limit - $today_count) : '∞';

// Get latest trackings
$trackings = $this->feature->tracking->get_user_trackings($user->ID, [
    'limit' => 10,
    'orderby' => 'created_at',
    'order' => 'DESC'
]);
?>

<div class="cobra-ai-trackings-tab">
    <!-- Usage Summary -->
    <div class="usage-summary">
        <div class="summary-cards">
            <div class="summary-card daily-limit">
                <div class="card-header">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <h3><?php _e('Daily Limit', 'cobra-ai'); ?></h3>
                </div>
                <div class="card-content">
                    <div class="usage-meter">
                        <?php if ($daily_limit > 0): ?>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo min(100, ($today_count / $daily_limit) * 100); ?>%"></div>
                            </div>
                            <div class="usage-text">
                                <?php printf(
                                    __('%d / %d requests today', 'cobra-ai'),
                                    $today_count,
                                    $daily_limit
                                ); ?>
                            </div>
                        <?php else: ?>
                            <div class="unlimited-text">
                                <?php _e('Unlimited', 'cobra-ai'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="summary-card total-requests">
                <div class="card-header">
                    <span class="dashicons dashicons-calculator"></span>
                    <h3><?php _e('Total Requests', 'cobra-ai'); ?></h3>
                </div>
                <div class="card-content">
                    <div class="stat-value"><?php echo number_format_i18n($stats['total']); ?></div>
                    <div class="stat-breakdown">
                        <span class="today"><?php printf(__('Today: %d', 'cobra-ai'), $stats['today']); ?></span>
                        <span class="week"><?php printf(__('Week: %d', 'cobra-ai'), $stats['week']); ?></span>
                        <span class="month"><?php printf(__('Month: %d', 'cobra-ai'), $stats['month']); ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($stats['by_provider'])): ?>
                <div class="summary-card provider-usage">
                    <div class="card-header">
                        <span class="dashicons dashicons-networking"></span>
                        <h3><?php _e('Provider Usage', 'cobra-ai'); ?></h3>
                    </div>
                    <div class="card-content">
                        <div class="provider-stats">
                            <?php foreach ($stats['by_provider'] as $provider => $count): ?>
                                <div class="provider-row">
                                    <span class="provider-name"><?php echo esc_html(ucfirst($provider)); ?></span>
                                    <span class="provider-count"><?php echo number_format_i18n($count); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($stats['by_type'])): ?>
                <div class="summary-card response-types">
                    <div class="card-header">
                        <span class="dashicons dashicons-format-aside"></span>
                        <h3><?php _e('Response Types', 'cobra-ai'); ?></h3>
                    </div>
                    <div class="card-content">
                        <div class="type-stats">
                            <?php foreach ($stats['by_type'] as $type => $count): ?>
                                <div class="type-row">
                                    <span class="type-name"><?php echo esc_html(ucfirst($type)); ?></span>
                                    <span class="type-count"><?php echo number_format_i18n($count); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Requests -->
    <div class="recent-requests">
        <div class="section-header">
            <h3><?php _e('Recent Requests', 'cobra-ai'); ?></h3>
            <div class="section-actions">
                <select id="request-filter" class="request-filter">
                    <option value=""><?php _e('All Providers', 'cobra-ai'); ?></option>
                    <?php foreach ($this->feature->get_active_providers() as $provider_id => $provider): ?>
                        <option value="<?php echo esc_attr($provider_id); ?>">
                            <?php echo esc_html($provider->get_name()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (!empty($trackings)): ?>
            <div class="requests-list">
                <?php foreach ($trackings as $tracking): ?>
                    <div class="request-item" data-provider="<?php echo esc_attr($tracking->ai_provider); ?>">
                        <div class="request-header">
                            <div class="request-meta">
                                <span class="provider-badge <?php echo esc_attr($tracking->ai_provider); ?>">
                                    <?php echo esc_html(ucfirst($tracking->ai_provider)); ?>
                                </span>
                                <span class="status-badge <?php echo esc_attr($tracking->status); ?>">
                                    <?php echo esc_html(ucfirst($tracking->status)); ?>
                                </span>
                                <span class="request-date">
                                    <?php echo human_time_diff(strtotime($tracking->created_at), current_time('timestamp')); ?>
                                    <?php _e('ago', 'cobra-ai'); ?>
                                </span>
                            </div>
                            <button type="button" class="toggle-details" data-tracking="<?php echo esc_attr($tracking->id); ?>">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </div>
                        <div class="request-prompt">
                            <?php echo wp_trim_words($tracking->prompt, 20); ?>
                        </div>
                        <div class="request-details" style="display: none;">
                            <div class="details-content">
                                <div class="prompt-section">
                                    <h4><?php _e('Prompt', 'cobra-ai'); ?></h4>
                                    <pre><?php echo esc_html($tracking->prompt); ?></pre>
                                </div>
                                <div class="response-section">
                                    <h4><?php _e('Response', 'cobra-ai'); ?></h4>
                                    <pre><?php echo esc_html($tracking->response); ?></pre>
                                </div>
                                <?php if (!empty($tracking->meta_data)):
                                    $meta = json_decode($tracking->meta_data, true);
                                    if ($meta): ?>
                                        <div class="meta-section">
                                            <h4><?php _e('Additional Info', 'cobra-ai'); ?></h4>
                                            <table class="meta-table">
                                                <?php foreach ($meta as $key => $value): ?>
                                                    <tr>
                                                        <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                                        <td><?php echo is_array($value) ? '<pre>' . esc_html(json_encode($value, JSON_PRETTY_PRINT)) . '</pre>' : esc_html($value); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </div>
                                <?php endif;
                                endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="requests-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-trackings&action=user&user_id=' . $user->ID)); ?>"
                    class="button">
                    <?php _e('View All Requests', 'cobra-ai'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="no-requests">
                <p><?php _e('No requests found.', 'cobra-ai'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .cobra-ai-trackings-tab {
        margin: 20px 0;
    }

    .usage-summary {
        margin-bottom: 30px;
    }

    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .summary-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
    }

    .card-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .card-header .dashicons {
        font-size: 20px;
        width: 20px;
        height: 20px;
        margin-right: 10px;
        color: #2271b1;
    }

    .card-header h3 {
        margin: 0;
        font-size: 14px;
        color: #1d2327;
    }

    .usage-meter {
        text-align: center;
    }

    .progress-bar {
        background: #f0f0f1;
        border-radius: 10px;
        height: 10px;
        margin-bottom: 10px;
        overflow: hidden;
    }

    .progress {
        background: #2271b1;
        height: 100%;
        transition: width 0.3s ease;
    }

    .usage-text,
    .unlimited-text {
        font-size: 13px;
        color: #50575e;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .stat-breakdown {
        display: flex;
        gap: 15px;
        font-size: 13px;
        color: #50575e;
    }

    .provider-stats,
    .type-stats {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .provider-row,
    .type-row {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .section-header h3 {
        margin: 0;
    }

    .requests-list {
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }

    .request-item {
        padding: 15px;
        border-bottom: 1px solid #f0f0f1;
    }

    .request-item:last-child {
        border-bottom: none;
    }

    .request-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .request-meta {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .provider-badge,
    .status-badge {
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 12px;
    }

    .provider-badge {
        background: #f0f0f1;
    }

    .status-badge.completed {
        background: #dff0d8;
        color: #3c763d;
    }

    .status-badge.pending {
        background: #fcf8e3;
        color: #8a6d3b;
    }

    .status-badge.failed {
        background: #f2dede;
        color: #a94442;
    }

    .request-date {
        color: #646970;
        font-size: 12px;
    }

    .request-prompt {
        color: #50575e;
        margin-bottom: 10px;
    }

    .toggle-details {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        color: #2271b1;
    }

    .request-details {
        background: #f6f7f7;
        padding: 15px;
        border-radius: 4px;
        margin-top: 10px;
    }

    .details-content {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .details-content h4 {
        margin: 0 0 10px 0;
        font-size: 13px;
    }

    .details-content pre {
        background: #fff;
        padding: 10px;
        border-radius: 3px;
        overflow: auto;
        margin: 0;
        font-size: 13px;
        line-height: 1.5;
    }

    .meta-table {
        width: 100%;
        border-collapse: collapse;
    }

    .meta-table th,
    .meta-table td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #f0f0f1;
    }

    .meta-table th {
        width: 150px;
        color: #646970;
    }

    .requests-footer {
        margin-top: 20px;
        text-align: right;
    }

    .no-requests {
        text-align: center;
        padding: 30px;
        color: #646970;
    }

    @media screen and (max-width: 782px) {
        .summary-cards {
            grid-template-columns: 1fr;
        }

        .section-header {
            flex-direction: column;
            gap: 10px;
        }

        .request-meta {
            flex-wrap: wrap;
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Filter requests by provider
        $('#request-filter').on('change', function() {
            const provider = $(this).val();

            if (provider) {
                $('.request-item').hide()
                    .filter(`[data-provider="${provider}"]`).show();
            } else {
                $('.request-item').show();
            }
        });

        // Toggle request details
        $('.toggle-details').on('click', function() {
            const button = $(this);
            const details = button.closest('.request-item').find('.request-details');
            const icon = button.find('.dashicons');

            details.slideToggle(200, function() {
                if (details.is(':visible')) {
                    icon.removeClass('dashicons-arrow-down-alt2')
                        .addClass('dashicons-arrow-up-alt2');
                } else {
                    icon.removeClass('dashicons-arrow-up-alt2')
                        .addClass('dashicons-arrow-down-alt2');
                }
            });
        });

        // Refresh usage stats
        function refreshUsageStats() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_ai_get_user_stats',
                    user_id: <?php echo esc_js($user->ID); ?>,
                    nonce: '<?php echo wp_create_nonce("cobra_ai_admin"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Update daily usage
                        const dailyLimit = <?php echo esc_js($daily_limit); ?>;
                        const todayCount = response.data.today;

                        if (dailyLimit > 0) {
                            const percentage = Math.min(100, (todayCount / dailyLimit) * 100);
                            $('.progress').css('width', percentage + '%');
                            $('.usage-text').text(
                                `${todayCount} / ${dailyLimit} requests today`
                            );
                        }

                        // Update total stats
                        $('.total-requests .stat-value').text(response.data.total);
                        $('.stat-breakdown .today').text(`Today: ${response.data.today}`);
                        $('.stat-breakdown .week').text(`Week: ${response.data.week}`);
                        $('.stat-breakdown .month').text(`Month: ${response.data.month}`);

                        // Update provider stats if changed
                        if (response.data.by_provider) {
                            const providerStats = $('.provider-stats');
                            providerStats.empty();

                            Object.entries(response.data.by_provider).forEach(([provider, count]) => {
                                providerStats.append(`
                                <div class="provider-row">
                                    <span class="provider-name">${provider}</span>
                                    <span class="provider-count">${count}</span>
                                </div>
                            `);
                            });
                        }
                    }
                }
            });
        }

        // Auto-refresh stats every minute
        setInterval(refreshUsageStats, 60000);

        // Copy request content
        $('.request-details pre').each(function() {
            const pre = $(this);
            const copyBtn = $('<button>')
                .addClass('copy-content')
                .html('<span class="dashicons dashicons-clipboard"></span>')
                .attr('title', '<?php _e("Copy to clipboard", "cobra-ai"); ?>')
                .insertAfter(pre);

            copyBtn.on('click', function(e) {
                e.preventDefault();

                const text = pre.text();
                navigator.clipboard.writeText(text).then(() => {
                    const icon = copyBtn.find('.dashicons');
                    icon.removeClass('dashicons-clipboard')
                        .addClass('dashicons-yes');

                    setTimeout(() => {
                        icon.removeClass('dashicons-yes')
                            .addClass('dashicons-clipboard');
                    }, 1000);
                });
            });
        });

        // Handle date links in stats
        $('.stat-breakdown span').on('click', function() {
            const period = $(this).attr('class');
            window.location.href = `<?php echo esc_js(admin_url('admin.php?page=cobra-ai-trackings&action=user&user_id=' . $user->ID)); ?>&period=${period}`;
        });
    });
</script>
<?php
// Add custom CSS class to body for our section
add_filter('admin_body_class', function ($classes) {
    return "$classes cobra-ai-profile";
});
