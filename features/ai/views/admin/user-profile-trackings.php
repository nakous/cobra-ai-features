<?php
// views/admin/user-profile-trackings.php
defined('ABSPATH') || exit;
?>

<div class="cobra-ai-profile-section">
    <h2><?php _e('AI Usage History', 'cobra-ai'); ?></h2>
    <table class="form-table">
        <tbody>
            <tr>
                <th><?php _e('Total Requests', 'cobra-ai'); ?></th>
                <td>
                    <strong><?php echo number_format_i18n($total); ?></strong>
                    &nbsp;
                    <a href="<?php echo esc_url(add_query_arg([
                        'page'    => 'cobra-ai-trackings',
                        'action'  => 'user',
                        'user_id' => $user->ID,
                    ], admin_url('admin.php'))); ?>">
                        <?php _e('View full history', 'cobra-ai'); ?>
                    </a>
                </td>
            </tr>
            <?php if (!empty($providers)): ?>
            <tr>
                <th><?php _e('Active Providers', 'cobra-ai'); ?></th>
                <td><?php echo esc_html(implode(', ', array_keys($providers))); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($trackings)): ?>
    <h3><?php _e('Recent Requests', 'cobra-ai'); ?></h3>
    <table class="widefat striped" style="max-width:900px;">
        <thead>
            <tr>
                <th><?php _e('Date', 'cobra-ai'); ?></th>
                <th><?php _e('Provider', 'cobra-ai'); ?></th>
                <th><?php _e('Model', 'cobra-ai'); ?></th>
                <th><?php _e('Tokens', 'cobra-ai'); ?></th>
                <th><?php _e('Status', 'cobra-ai'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($trackings as $tracking): ?>
            <tr>
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($tracking->created_at))); ?></td>
                <td><?php echo esc_html($tracking->provider ?? '—'); ?></td>
                <td><?php echo esc_html($tracking->model ?? '—'); ?></td>
                <td><?php echo isset($tracking->tokens_used) ? number_format_i18n($tracking->tokens_used) : '—'; ?></td>
                <td><?php echo esc_html($tracking->status ?? '—'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="description"><?php _e('No AI requests recorded yet.', 'cobra-ai'); ?></p>
    <?php endif; ?>
</div>
