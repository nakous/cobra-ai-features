<?php
// views/bulk-add-credits.php
defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php _e('Bulk Add Credits', 'cobra-ai'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cobra_ai_bulk_add_credits'); ?>
        <input type="hidden" name="action" value="cobra_ai_bulk_add_credits">

        <div class="users-list">
            <h3><?php _e('Selected Users', 'cobra-ai'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Username', 'cobra-ai'); ?></th>
                        <th><?php _e('Email', 'cobra-ai'); ?></th>
                        <th><?php _e('Current Credits', 'cobra-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <input type="hidden" name="user_ids[]" value="<?php echo esc_attr($user->ID); ?>">
                        <tr>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td>
                                <?php 
                                $total = $this->feature->get_user_credit_total($user->ID);
                                echo number_format_i18n($total, 2) . ' ' . 
                                     esc_html($settings['general']['credit_symbol']);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Credit Type', 'cobra-ai'); ?></th>
                <td>
                    <select name="credit_type" id="credit_type" required>
                        <?php foreach ($credit_types as $type => $label): ?>
                            <option value="<?php echo esc_attr($type); ?>">
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
                           class="regular-text" 
                           step="0.01" 
                           min="0" 
                           required>
                    <?php echo esc_html($settings['general']['credit_symbol']); ?>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Expiration Date', 'cobra-ai'); ?></th>
                <td>
                    <input type="datetime-local" 
                           name="expiration_date" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Leave empty for no expiration', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Comment', 'cobra-ai'); ?></th>
                <td>
                    <textarea name="comment" class="large-text" rows="3"></textarea>
                    <p class="description">
                        <?php _e('This comment will be added to all credit entries', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Add Credits to All Users', 'cobra-ai')); ?>

        <p>
            <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="button">
                <?php _e('Back to Users List', 'cobra-ai'); ?>
            </a>
        </p>
    </form>
</div>