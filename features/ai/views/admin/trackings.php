<?php
// views/admin/trackings.php
defined('ABSPATH') || exit;

// Display any messages
if (isset($_GET['message'])) {
    $type = 'success';
    $message = '';

    switch ($_GET['message']) {
        case 'deleted':
            $message = __('Tracking(s) deleted successfully.', 'cobra-ai');
            break;
        case 'error':
            $type = 'error';
            $message = __('An error occurred. Please try again.', 'cobra-ai');
            break;
    }

    if ($message) {
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('AI Trackings', 'cobra-ai'); ?></h1>

    <!-- Filters Form -->
    <div class="tablenav top">
        <form method="get" class="tracking-filters">
            <input type="hidden" name="page" value="cobra-ai-trackings">

            <div class="alignleft actions">
                <select name="ai_provider">
                    <option value=""><?php _e('All Providers', 'cobra-ai'); ?></option>
                    <?php foreach ($this->get_providers_for_display() as $provider_id => $provider_name): ?>
                        <option value="<?php echo esc_attr($provider_id); ?>"
                            <?php selected($ai_provider, $provider_id); ?>>
                            <?php echo esc_html($provider_name); ?>
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
                            <?php selected($status, $key); ?>>
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
                            <?php selected($response_type, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php
                // Date range inputs
                $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
                $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
                ?>
                <input type="date"
                    name="start_date"
                    value="<?php echo esc_attr($start_date); ?>"
                    placeholder="<?php esc_attr_e('Start Date', 'cobra-ai'); ?>">
                <input type="date"
                    name="end_date"
                    value="<?php echo esc_attr($end_date); ?>"
                    placeholder="<?php esc_attr_e('End Date', 'cobra-ai'); ?>">

                <?php submit_button(__('Filter', 'cobra-ai'), 'secondary', 'filter', false); ?>
            </div>

            <div class="alignright">
                <input type="search"
                    id="tracking-search"
                    name="s"
                    value="<?php echo esc_attr(get_search_query()); ?>"
                    placeholder="<?php esc_attr_e('Search trackings...', 'cobra-ai'); ?>">
            </div>
        </form>
    </div>

    <!-- Tracking Stats -->
    <div class="tracking-stats">
        <?php
        $total_trackings = $tracking_table->get_total_items_count([]);
        $completed = $tracking_table->get_total_items_count(['status' => 'completed']);
        $pending = $tracking_table->get_total_items_count(['status' => 'pending']);
        $failed = $tracking_table->get_total_items_count(['status' => 'failed']);
        ?>
        <div class="stat-box">
            <span class="stat-label"><?php _e('Total Requests', 'cobra-ai'); ?></span>
            <span class="stat-value"><?php echo number_format_i18n($total_trackings); ?></span>
        </div>
        <div class="stat-box completed">
            <span class="stat-label"><?php _e('Completed', 'cobra-ai'); ?></span>
            <span class="stat-value"><?php echo number_format_i18n($completed); ?></span>
        </div>
        <div class="stat-box pending">
            <span class="stat-label"><?php _e('Pending', 'cobra-ai'); ?></span>
            <span class="stat-value"><?php echo number_format_i18n($pending); ?></span>
        </div>
        <div class="stat-box failed">
            <span class="stat-label"><?php _e('Failed', 'cobra-ai'); ?></span>
            <span class="stat-value"><?php echo number_format_i18n($failed); ?></span>
        </div>
    </div>

    <!-- Tracking List Table -->
    <form method="post">
        <?php
        $tracking_table->prepare_items();
        $tracking_table->display();
        ?>
    </form>
</div>

<!-- Tracking Details Modal -->
<div id="tracking-modal" class="tracking-modal">
    <div class="tracking-modal-content">
        <span class="close">&times;</span>
        <div class="tracking-details">
            <div class="tracking-info">
                <h2><?php _e('Tracking Details', 'cobra-ai'); ?></h2>
                <table class="tracking-info-table">
                    <tr>
                        <th><?php _e('ID', 'cobra-ai'); ?></th>
                        <td class="tracking-id"></td>
                    </tr>
                    <tr>
                        <th><?php _e('User', 'cobra-ai'); ?></th>
                        <td class="tracking-user"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Provider', 'cobra-ai'); ?></th>
                        <td class="tracking-provider"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Status', 'cobra-ai'); ?></th>
                        <td class="tracking-status"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Date', 'cobra-ai'); ?></th>
                        <td class="tracking-date"></td>
                    </tr>
                    <tr>
                        <th><?php _e('Tokens', 'cobra-ai'); ?></th>
                        <td class="tracking-tokens"></td>
                    </tr>
                </table>
            </div>
            <div class="tracking-content">
                <div class="tracking-prompt">
                    <h3><?php _e('Prompt', 'cobra-ai'); ?></h3>
                    <pre></pre>
                </div>
                <div class="tracking-response">
                    <h3><?php _e('Response', 'cobra-ai'); ?></h3>
                    <pre></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .tracking-stats {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }

    .stat-box {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 15px;
        text-align: center;
        flex: 1;
    }

    .stat-box.completed {
        border-color: #00a32a;
        background: #f0f6f0;
    }

    .stat-box.pending {
        border-color: #dba617;
        background: #fcf9e8;
    }

    .stat-box.failed {
        border-color: #d63638;
        background: #fcf0f1;
    }

    .stat-label {
        display: block;
        font-size: 14px;
        color: #1d2327;
        margin-bottom: 5px;
    }

    .stat-value {
        display: block;
        font-size: 24px;
        font-weight: 600;
    }

    .tracking-modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.4);
    }

    .tracking-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 90%;
        max-width: 1200px;
        max-height: 80vh;
        overflow-y: auto;
        border-radius: 4px;
        position: relative;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .tracking-details {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 20px;
    }

    .tracking-info-table {
        width: 100%;
        border-collapse: collapse;
    }

    .tracking-info-table th,
    .tracking-info-table td {
        padding: 8px;
        border-bottom: 1px solid #f0f0f1;
        text-align: left;
    }

    .tracking-info-table th {
        width: 100px;
        color: #646970;
    }

    .tracking-content {
        border-left: 1px solid #f0f0f1;
        padding-left: 20px;
    }

    .tracking-content h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #1d2327;
    }

    .tracking-content pre {
        background: #f6f7f7;
        padding: 15px;
        border-radius: 4px;
        overflow: auto;
        margin: 0 0 20px 0;
        font-family: Consolas, Monaco, monospace;
        font-size: 13px;
        line-height: 1.5;
        white-space: pre-wrap;
    }

    @media screen and (max-width: 782px) {
        .tracking-stats {
            flex-wrap: wrap;
        }

        .stat-box {
            flex: 0 0 calc(50% - 10px);
        }

        .tracking-details {
            grid-template-columns: 1fr;
        }

        .tracking-content {
            border-left: none;
            padding-left: 0;
            border-top: 1px solid #f0f0f1;
            padding-top: 20px;
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
                        const data = response.data;

                        // Fill modal with data
                        $('.tracking-id').text('#' + data.id);
                        $('.tracking-user').text(data.user.display_name);
                        $('.tracking-provider').text(data.ai_provider);
                        $('.tracking-status').text(data.status);
                        $('.tracking-date').text(data.created_at);
                        $('.tracking-tokens').text(data.consumed);
                        $('.tracking-prompt pre').html(data.prompt);
                        $('.tracking-response pre').html(data.response);

                        // Show modal
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
    });
</script>