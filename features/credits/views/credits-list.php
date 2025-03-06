<?php
// /Admin/views/credits-list.php

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Credits Management', 'cobra-ai'); ?></h1>
    <a href="<?php echo esc_url(add_query_arg('action', 'add')); ?>" class="page-title-action">
        <?php _e('Add New Credit', 'cobra-ai'); ?>
    </a>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($menu_slug); ?>">
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <input type="search" name="user_search" 
                       value="<?php echo esc_attr($user_search); ?>" 
                       placeholder="<?php _e('Search users...', 'cobra-ai'); ?>">

                <select name="credit_type">
                    <option value=""><?php _e('All credit types', 'cobra-ai'); ?></option>
                    <?php foreach ($credit_types as $type => $label): ?>
                        <option value="<?php echo esc_attr($type); ?>" 
                            <?php selected($credit_type, $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value=""><?php _e('All statuses', 'cobra-ai'); ?></option>
                    <option value="active" <?php selected($status, 'active'); ?>>
                        <?php _e('Active', 'cobra-ai'); ?>
                    </option>
                    <option value="pending" <?php selected($status, 'pending'); ?>>
                        <?php _e('Pending', 'cobra-ai'); ?>
                    </option>
                    <option value="expired" <?php selected($status, 'expired'); ?>>
                        <?php _e('Expired', 'cobra-ai'); ?>
                    </option>
                </select>

                <?php submit_button(__('Filter', 'cobra-ai'), 'secondary', 'filter', false); ?>
            </div>
        </div>

        <?php $credits_table->display(); ?>
    </form>
</div>