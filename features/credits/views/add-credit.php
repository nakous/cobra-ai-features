<?php
// views/add-credit.php
defined('ABSPATH') || exit;


// Display any error messages
if (isset($_GET['error'])) {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
    </div>
    <?php
}
?>

<div class="wrap">
    <h1><?php _e('Add Credit', 'cobra-ai'); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cobra-add-credit-form">
        <?php wp_nonce_field('cobra_ai_add_credit'); ?>
        <input type="hidden" name="action" value="cobra_ai_add_credit">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="user_id"><?php _e('User', 'cobra-ai'); ?></label>
                </th>
                <td>
                    <?php if ($user): ?>
                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" id ="user_id">
                        <strong><?php echo esc_html($user->display_name); ?></strong>
                        (<?php echo esc_html($user->user_email); ?>)
                    <?php else: ?>
                        <select name="user_id" id="user_id" class="regular-text" required>
                            <option value=""><?php _e('Select a user...', 'cobra-ai'); ?></option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>">
                                    <?php echo esc_html($user->display_name); ?> 
                                    (<?php echo esc_html($user->user_email); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="credit_type"><?php _e('Credit Type', 'cobra-ai'); ?></label>
                </th>
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
                <th scope="row">
                    <label for="amount"><?php _e('Amount', 'cobra-ai'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           name="amount" 
                           id="amount" 
                           class="regular-text" 
                           step="0.01" 
                           min="0" 
                           required>
                    <?php echo esc_html($settings['general']['credit_symbol']); ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="expiration_date"><?php _e('Expiration Date', 'cobra-ai'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" 
                           name="expiration_date" 
                           id="expiration_date" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Leave empty for no expiration', 'cobra-ai'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="comment"><?php _e('Comment', 'cobra-ai'); ?></label>
                </th>
                <td>
                    <textarea name="comment" 
                              id="comment" 
                              class="large-text" 
                              rows="3"></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Add Credit', 'cobra-ai')); ?>
        
        <a href="<?php echo esc_url(add_query_arg('page', $this->menu_slug, admin_url('admin.php'))); ?>" 
           class="button button-secondary">
            <?php _e('Cancel', 'cobra-ai'); ?>
        </a>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Form submission handling
    $('.cobra-add-credit-form').on('submit', function(e) {
        // Basic validation
 
        if (!$('#user_id').val() || !$('#credit_type').val() || !$('#amount').val()) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Please fill in all required fields', 'cobra-ai')); ?>');
            return false;
        }
    });
});
</script>