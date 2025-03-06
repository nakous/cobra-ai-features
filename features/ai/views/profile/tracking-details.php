<?php
// views/admin/tracking-details.php
defined('ABSPATH') || exit;

if (!$tracking) {
    wp_die(__('Tracking not found', 'cobra-ai'));
}

// Get user data
$user = get_userdata($tracking->user_id);
if (!$user) {
    wp_die(__('User not found', 'cobra-ai'));
}

// Parse meta data
$meta_data = !empty($tracking->meta_data) ? json_decode($tracking->meta_data, true) : [];
?>

<div class="wrap cobra-ai-tracking-details">
    <h1 class="wp-heading-inline">
        <?php printf(
            __('Tracking Details #%d', 'cobra-ai'),
            $tracking->id
        ); ?>
    </h1>

    <div class="tracking-header">
        <div class="tracking-meta">
            <div class="meta-item">
                <span class="meta-label"><?php _e('Status', 'cobra-ai'); ?></span>
                <span class="status-badge <?php echo esc_attr($tracking->status); ?>">
                    <?php echo esc_html(ucfirst($tracking->status)); ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label"><?php _e('Provider', 'cobra-ai'); ?></span>
                <span class="provider-badge <?php echo esc_attr($tracking->ai_provider); ?>">
                    <?php echo esc_html(ucfirst($tracking->ai_provider)); ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label"><?php _e('Type', 'cobra-ai'); ?></span>
                <span class="type-badge">
                    <?php echo esc_html(ucfirst($tracking->response_type)); ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label"><?php _e('Date', 'cobra-ai'); ?></span>
                <span class="date-value">
                    <?php echo get_date_from_gmt($tracking->created_at, get_option('date_format') . ' ' . get_option('time_format')); ?>
                </span>
            </div>
        </div>

        <div class="tracking-actions">
            <?php if (current_user_can('manage_options')): ?>
                <button type="button" class="button delete-tracking" data-id="<?php echo esc_attr($tracking->id); ?>">
                    <?php _e('Delete', 'cobra-ai'); ?>
                </button>
            <?php endif; ?>
            <a href="<?php echo esc_url(wp_get_referer()); ?>" class="button">
                <?php _e('Back', 'cobra-ai'); ?>
            </a>
        </div>
    </div>

    <div class="tracking-content">
        <!-- User Information -->
        <div class="tracking-section user-info">
            <h2><?php _e('User Information', 'cobra-ai'); ?></h2>
            <div class="section-content">
                <div class="user-avatar">
                    <?php echo get_avatar($user->ID, 64); ?>
                </div>
                <div class="user-details">
                    <h3><?php echo esc_html($user->display_name); ?></h3>
                    <p class="user-email"><?php echo esc_html($user->user_email); ?></p>
                    <div class="user-actions">
                        <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="button button-small">
                            <?php _e('Edit User', 'cobra-ai'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=cobra-ai-trackings&action=user&user_id=' . $user->ID)); ?>"
                            class="button button-small">
                            <?php _e('View History', 'cobra-ai'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Details -->
        <div class="tracking-section request-details">
            <h2><?php _e('Request Details', 'cobra-ai'); ?></h2>
            <div class="section-content">
                <div class="request-stats">
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Consumed Tokens', 'cobra-ai'); ?></span>
                        <span class="stat-value"><?php echo number_format_i18n($tracking->consumed); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('IP Address', 'cobra-ai'); ?></span>
                        <span class="stat-value"><?php echo esc_html($tracking->ip); ?></span>
                    </div>
                    <?php if (!empty($meta_data['duration'])): ?>
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('Duration', 'cobra-ai'); ?></span>
                            <span class="stat-value">
                                <?php printf(
                                    __('%s seconds', 'cobra-ai'),
                                    number_format_i18n($meta_data['duration'], 2)
                                ); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Provider Settings -->
                <?php if (!empty($meta_data['options'])): ?>
                    <div class="provider-settings">
                        <h3><?php _e('Provider Settings', 'cobra-ai'); ?></h3>
                        <table class="widefat striped">
                            <tbody>
                                <?php foreach ($meta_data['options'] as $key => $value): ?>
                                    <tr>
                                        <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                        <td>
                                            <?php if (is_array($value)): ?>
                                                <pre><?php echo esc_html(json_encode($value, JSON_PRETTY_PRINT)); ?></pre>
                                            <?php else: ?>
                                                <?php echo esc_html($value); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Prompt and Response -->
        <div class="tracking-section prompt-response">
            <div class="prompt-section">
                <h2>
                    <?php _e('Prompt', 'cobra-ai'); ?>
                    <button type="button" class="copy-content" data-content="prompt">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </h2>
                <div class="section-content">
                    <pre id="prompt-content"><?php echo esc_html($tracking->prompt); ?></pre>
                </div>
            </div>

            <div class="response-section">
                <h2>
                    <?php _e('Response', 'cobra-ai'); ?>
                    <button type="button" class="copy-content" data-content="response">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </h2>
                <div class="section-content <?php echo $tracking->status === 'failed' ? 'error' : ''; ?>">
                    <?php if ($tracking->response_type === 'image'): ?>
                        <div class="image-response">
                            <img src="<?php echo esc_url($tracking->response); ?>" alt="AI Generated Image">
                        </div>
                    <?php else: ?>
                        <pre id="response-content"><?php echo esc_html($tracking->response); ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Additional Meta Data -->
        <?php if (!empty($meta_data) && !empty($meta_data['response_meta'])): ?>
            <div class="tracking-section meta-data">
                <h2><?php _e('Additional Information', 'cobra-ai'); ?></h2>
                <div class="section-content">
                    <table class="widefat striped">
                        <tbody>
                            <?php foreach ($meta_data['response_meta'] as $key => $value): ?>
                                <tr>
                                    <th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                                    <td>
                                        <?php if (is_array($value)): ?>
                                            <pre><?php echo esc_html(json_encode($value, JSON_PRETTY_PRINT)); ?></pre>
                                        <?php else: ?>
                                            <?php echo esc_html($value); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .cobra-ai-tracking-details {
        max-width: 1200px;
    }

    .tracking-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
        padding: 20px;
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
    }

    .tracking-meta {
        display: flex;
        gap: 20px;
    }

    .meta-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .meta-label {
        font-size: 12px;
        color: #646970;
    }

    .status-badge,
    .provider-badge,
    .type-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
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

    .provider-badge,
    .type-badge {
        background: #f0f0f1;
        color: #1d2327;
    }

    .tracking-section {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .tracking-section h2 {
        margin: 0;
        padding: 15px 20px;
        border-bottom: 1px solid #c3c4c7;
        font-size: 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-content {
        padding: 20px;
    }

    .user-info .section-content {
        display: flex;
        gap: 20px;
    }

    .user-avatar img {
        border-radius: 50%;
    }

    .user-details h3 {
        margin: 0 0 5px 0;
    }

    .user-email {
        margin: 0 0 10px 0;
        color: #646970;
    }

    .user-actions {
        display: flex;
        gap: 10px;
    }

    .request-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-item {
        padding: 15px;
        background: #f6f7f7;
        border-radius: 4px;
    }

    .stat-label {
        display: block;
        font-size: 12px;
        color: #646970;
        margin-bottom: 5px;
    }

    .stat-value {
        font-size: 16px;
        font-weight: 500;
    }

    .prompt-response {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .prompt-section,
    .response-section {
        background: #f6f7f7;
        border-radius: 4px;
    }

    .section-content pre {
        background: #fff;
        padding: 15px;
        border-radius: 3px;
        overflow: auto;
        margin: 0;
        font-family: Consolas, Monaco, monospace;
        font-size: 13px;
        line-height: 1.5;
        white-space: pre-wrap;
    }

    .section-content.error pre {
        color: #a94442;
    }

    .copy-content {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        color: #2271b1;
    }

    .copy-content .dashicons {
        width: 16px;
        height: 16px;
        font-size: 16px;
    }

    .image-response {
        text-align: center;
    }

    .image-response img {
        max-width: 100%;
        height: auto;
    }

    @media screen and (max-width: 782px) {
        .tracking-meta {
            flex-wrap: wrap;
        }

        .prompt-response {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Copy content functionality
        $('.copy-content').on('click', function() {
            const type = $(this).data('content');
            const content = $(`#${type}-content`).text();
            const button = $(this);
            const icon = button.find('.dashicons');

            navigator.clipboard.writeText(content).then(() => {
                icon.removeClass('dashicons-clipboard')
                    .addClass('dashicons-yes');

                setTimeout(() => {
                    icon.removeClass('dashicons-yes')
                        .addClass('dashicons-clipboard');
                }, 1000);
            });
        });

        // Delete tracking confirmation
        $('.delete-tracking').on('click', function() {
            if (confirm('<?php _e('Are you sure you want to delete this tracking?', 'cobra-ai'); ?>')) {
                const trackingId = $(this).data('id');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cobra_ai_delete_tracking',
                        tracking_id: trackingId,
                        nonce: '<?php echo wp_create_nonce("cobra_ai_admin"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = '<?php echo esc_js(admin_url('admin.php?page=cobra-ai-trackings&message=deleted')); ?>';
                        } else {
                            alert('<?php _e('Failed to delete tracking', 'cobra-ai'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred', 'cobra-ai'); ?>');
                    }
                });
            }
        });

        // Syntax highlighting for JSON responses
        if (typeof hljs !== 'undefined') {
            $('.section-content pre').each(function(i, block) {
                try {
                    // Try to parse as JSON
                    const content = $(block).text();
                    const jsonObj = JSON.parse(content);
                    $(block).text(JSON.stringify(jsonObj, null, 2));
                    hljs.highlightBlock(block);
                } catch (e) {
                    // Not JSON, skip highlighting
                }
            });
        }

        // Expandable sections
        $('.tracking-section h2').not('.prompt-response h2').on('click', function() {
            const section = $(this).closest('.tracking-section');
            const content = section.find('.section-content');

            content.slideToggle(200);
            $(this).toggleClass('collapsed');
        });

        // Image zoom functionality
        if ($('.image-response').length) {
            $('.image-response img').on('click', function() {
                const img = $(this);
                const isZoomed = img.hasClass('zoomed');

                if (isZoomed) {
                    img.removeClass('zoomed');
                    img.css({
                        'max-width': '100%',
                        'cursor': 'zoom-in'
                    });
                } else {
                    img.addClass('zoomed');
                    img.css({
                        'max-width': 'none',
                        'cursor': 'zoom-out'
                    });
                }
            });

            // Add zoom cursor
            $('.image-response img').css('cursor', 'zoom-in');
        }

        // Request retry functionality
        if ($('.section-content.error').length) {
            const retryButton = $('<button>')
                .addClass('button retry-request')
                .text('<?php _e('Retry Request', 'cobra-ai'); ?>')
                .insertAfter('.response-section h2');

            retryButton.on('click', function() {
                const button = $(this);
                const originalText = button.text();

                button.prop('disabled', true).text('<?php _e('Retrying...', 'cobra-ai'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cobra_ai_retry_request',
                        tracking_id: <?php echo esc_js($tracking->id); ?>,
                        nonce: '<?php echo wp_create_nonce("cobra_ai_admin"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            alert(response.data);
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred', 'cobra-ai'); ?>');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
        }

        // Additional response metadata expandable sections
        $('.meta-data pre').each(function() {
            const pre = $(this);
            const height = pre.height();
            const maxHeight = 100;

            if (height > maxHeight) {
                pre.css({
                    'max-height': maxHeight + 'px',
                    'overflow': 'hidden',
                    'position': 'relative'
                });

                const expandButton = $('<button>')
                    .addClass('button button-small expand-content')
                    .text('<?php _e('Show More', 'cobra-ai'); ?>')
                    .insertAfter(pre);

                expandButton.on('click', function() {
                    const isExpanded = pre.hasClass('expanded');

                    if (isExpanded) {
                        pre.removeClass('expanded')
                            .css('max-height', maxHeight + 'px');
                        expandButton.text('<?php _e('Show More', 'cobra-ai'); ?>');
                    } else {
                        pre.addClass('expanded')
                            .css('max-height', 'none');
                        expandButton.text('<?php _e('Show Less', 'cobra-ai'); ?>');
                    }
                });
            }
        });
    });
</script>

<?php
// Add body class for styling
add_filter('admin_body_class', function ($classes) {
    return "$classes cobra-ai-tracking-details";
});

// Enqueue syntax highlighting if available
if (wp_script_is('wp-syntax-highlight', 'registered')) {
    wp_enqueue_script('wp-syntax-highlight');
    wp_enqueue_style('wp-syntax-highlight');
}
