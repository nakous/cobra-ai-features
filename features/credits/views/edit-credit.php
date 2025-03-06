<?php
// views/edit-credit.php
defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php _e('Edit Credit', 'cobra-ai'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_edit_credit'); ?>
        <input type="hidden" name="action" value="cobra_ai_edit_credit">
        <input type="hidden" name="credit_id" value="<?php echo esc_attr($credit->id); ?>">

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('User', 'cobra-ai'); ?></th>
                <td>
                    <strong><?php echo esc_html($credit->display_name); ?></strong>
                    (<?php echo esc_html($credit->user_email); ?>)
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Credit Type', 'cobra-ai'); ?></th>
                <td>
                    <select name="credit_type" id="credit_type" required>
                        <?php foreach ($credit_types as $type => $label): ?>
                            <option value="<?php echo esc_attr($type); ?>" 
                                <?php selected($credit->credit_type, $type); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Amount', 'cobra-ai'); ?></th>
                <td>
                    <input type="number" 
                           name="amount" 
                           value="<?php echo esc_attr($credit->credit); ?>" 
                           class="regular-text" 
                           step="0.01" 
                           min="0" 
                           required>
                    <?php echo esc_html($settings['general']['credit_symbol']); ?>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Consumed', 'cobra-ai'); ?></th>
                <td>
                    <input type="number" 
                           name="consumed" 
                           value="<?php echo esc_attr($credit->consumed); ?>" 
                           class="regular-text" 
                           step="0.01" 
                           min="0" 
                           max="<?php echo esc_attr($credit->credit); ?>" 
                           required>
                    <?php echo esc_html($settings['general']['credit_symbol']); ?>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Status', 'cobra-ai'); ?></th>
                <td>
                    <select name="status" id="status" required>
                        <option value="active" <?php selected($credit->status, 'active'); ?>>
                            <?php _e('Active', 'cobra-ai'); ?>
                        </option>
                        <option value="pending" <?php selected($credit->status, 'pending'); ?>>
                            <?php _e('Pending', 'cobra-ai'); ?>
                        </option>
                        <option value="expired" <?php selected($credit->status, 'expired'); ?>>
                            <?php _e('Expired', 'cobra-ai'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Start Date', 'cobra-ai'); ?></th>
                <td>
                    <input type="datetime-local" 
                           name="start_date" 
                           value="<?php echo esc_attr(str_replace(' ', 'T', $credit->start_date)); ?>" 
                           class="regular-text" 
                           required>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Expiration Date', 'cobra-ai'); ?></th>
                <td>
                    <input type="datetime-local" 
                           name="expiration_date" 
                           value="<?php echo $credit->expiration_date ? esc_attr(str_replace(' ', 'T', $credit->expiration_date)) : ''; ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Leave empty for no expiration', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Comment', 'cobra-ai'); ?></th>
                <td>
                    <textarea name="comment" class="large-text" rows="3"><?php echo esc_textarea($credit->comment); ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Update Credit', 'cobra-ai')); ?>

        <p>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'cobra-ai-credits'], admin_url('admin.php'))); ?>" 
               class="button">
                <?php _e('Back to Credits List', 'cobra-ai'); ?>
            </a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Update consumed max value when amount changes
    $('input[name="amount"]').on('change', function() {
        $('input[name="consumed"]').attr('max', $(this).val());
    });
});
</script>