<?php
/** @var \CobraAI\Features\StripePayments\Feature $this */
/** @var array $settings */
defined('ABSPATH') || exit;

$success_page_id = (int) ($settings['success_page'] ?? 0);
$cancel_page_id  = (int) ($settings['cancel_page'] ?? 0);
$success_page    = $success_page_id ? get_post($success_page_id) : null;
$cancel_page     = $cancel_page_id ? get_post($cancel_page_id) : null;

// If the stored page was trashed/deleted, treat as non-existent
if ($success_page && $success_page->post_status === 'trash') { $success_page = null; }
if ($cancel_page && $cancel_page->post_status === 'trash') { $cancel_page = null; }

$create_nonce = wp_create_nonce('cobra_sp_create_page');
?>
<h2><?php _e('Stripe Payments Settings', 'cobra-ai'); ?></h2>
<p class="description">
    <?php _e('One-time payments for products and services via Stripe Checkout. Recurring subscriptions are managed by the <strong>Stripe Subscriptions</strong> feature.', 'cobra-ai'); ?>
</p>

<table class="form-table">
    <!-- Success page -->
    <tr>
        <th><?php _e('Success page', 'cobra-ai'); ?></th>
        <td>
            <div class="cobra-sp-page-row">
                <?php wp_dropdown_pages([
                    'name' => 'settings[success_page]',
                    'selected' => $success_page_id,
                    'show_option_none' => __('— None —', 'cobra-ai'),
                    'option_none_value' => '0',
                    'id' => 'sp_success_page',
                ]); ?>

                <?php if ($success_page): ?>
                    <a href="<?php echo esc_url(get_edit_post_link($success_page_id)); ?>"
                       class="button" target="_blank" title="<?php esc_attr_e('Edit page', 'cobra-ai'); ?>">
                        <span class="dashicons dashicons-edit" style="vertical-align:middle;margin-top:3px;"></span>
                        <?php _e('Edit', 'cobra-ai'); ?>
                    </a>
                    <a href="<?php echo esc_url(get_permalink($success_page_id)); ?>"
                       class="button" target="_blank" title="<?php esc_attr_e('View page', 'cobra-ai'); ?>">
                        <span class="dashicons dashicons-external" style="vertical-align:middle;margin-top:3px;"></span>
                    </a>
                <?php else: ?>
                    <button type="button" class="button button-primary cobra-sp-create-page"
                            data-type="success" data-select="#sp_success_page"
                            data-nonce="<?php echo esc_attr($create_nonce); ?>">
                        <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-top:3px;"></span>
                        <?php _e('Create page', 'cobra-ai'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <p class="description">
                <?php _e('Return page after successful payment. Shortcode:', 'cobra-ai'); ?>
                <code>[stripe_payment_success]</code>
            </p>
        </td>
    </tr>

    <!-- Cancel page -->
    <tr>
        <th><?php _e('Cancel page', 'cobra-ai'); ?></th>
        <td>
            <div class="cobra-sp-page-row">
                <?php wp_dropdown_pages([
                    'name' => 'settings[cancel_page]',
                    'selected' => $cancel_page_id,
                    'show_option_none' => __('— None —', 'cobra-ai'),
                    'option_none_value' => '0',
                    'id' => 'sp_cancel_page',
                ]); ?>

                <?php if ($cancel_page): ?>
                    <a href="<?php echo esc_url(get_edit_post_link($cancel_page_id)); ?>"
                       class="button" target="_blank" title="<?php esc_attr_e('Edit page', 'cobra-ai'); ?>">
                        <span class="dashicons dashicons-edit" style="vertical-align:middle;margin-top:3px;"></span>
                        <?php _e('Edit', 'cobra-ai'); ?>
                    </a>
                    <a href="<?php echo esc_url(get_permalink($cancel_page_id)); ?>"
                       class="button" target="_blank" title="<?php esc_attr_e('View page', 'cobra-ai'); ?>">
                        <span class="dashicons dashicons-external" style="vertical-align:middle;margin-top:3px;"></span>
                    </a>
                <?php else: ?>
                    <button type="button" class="button button-primary cobra-sp-create-page"
                            data-type="cancel" data-select="#sp_cancel_page"
                            data-nonce="<?php echo esc_attr($create_nonce); ?>">
                        <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-top:3px;"></span>
                        <?php _e('Create page', 'cobra-ai'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <p class="description">
                <?php _e('Return page when the customer cancels. Shortcode:', 'cobra-ai'); ?>
                <code>[stripe_payment_cancel]</code>
            </p>
        </td>
    </tr>

    <!-- Currency -->
    <tr>
        <th><?php _e('Default currency', 'cobra-ai'); ?></th>
        <td>
            <input type="text" name="settings[currency_default]" value="<?php echo esc_attr($settings['currency_default'] ?? 'USD'); ?>" maxlength="3" style="width:80px;text-transform:uppercase;">
            <p class="description"><?php _e('3-letter ISO code (USD, EUR, GBP...).', 'cobra-ai'); ?></p>
        </td>
    </tr>

    <!-- Email notifications -->
    <tr>
        <th><?php _e('Email notifications', 'cobra-ai'); ?></th>
        <td>
            <label>
                <input type="hidden" name="settings[email_notifications]" value="0">
                <input type="checkbox" name="settings[email_notifications]" value="1" <?php checked(!empty($settings['email_notifications'])); ?>>
                <?php _e('Send a confirmation email to the customer after payment', 'cobra-ai'); ?>
            </label>
        </td>
    </tr>
</table>

<h3><?php _e('Registered product types', 'cobra-ai'); ?></h3>
<p class="description">
    <?php _e('Other features can register their own product types via the filter', 'cobra-ai'); ?>
    <code>cobra_stripepayments_product_types</code>.
</p>
<ul style="margin-left:20px;list-style:disc;">
    <?php foreach ($this->get_product_types() as $slug => $label): ?>
        <li><code><?php echo esc_html($slug); ?></code> — <?php echo esc_html($label); ?></li>
    <?php endforeach; ?>
</ul>

<h3><?php _e('Available hooks', 'cobra-ai'); ?></h3>
<pre style="background:#f6f7f7;padding:12px;border-left:3px solid #2271b1;overflow:auto;">
do_action('cobra_ai_order_paid', int $order_id, array $order_data);
do_action('cobra_ai_order_failed', int $order_id, array $order_data);
do_action('cobra_ai_order_refunded', int $order_id, array $order_data);

apply_filters('cobra_stripepayments_product_types', array $types);
apply_filters('cobra_stripepayments_checkout_args', array $args, int $product_id, int $user_id);
do_action('cobra_stripepayments_product_meta_fields', WP_Post $post);
do_action('cobra_stripepayments_save_product_meta', int $post_id, array $_POST);
</pre>

<style>
.cobra-sp-page-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.cobra-sp-page-row select { min-width: 220px; }
.cobra-sp-create-page .spinner { float: none; margin: 0 0 0 4px; }
.cobra-sp-page-created {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #d4edda;
    color: #155724;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
}
</style>

<script>
jQuery(function($){
    $('.cobra-sp-create-page').on('click', function(e){
        e.preventDefault();
        var $btn = $(this);
        var type = $btn.data('type');
        var selectId = $btn.data('select');
        var nonce = $btn.data('nonce');

        $btn.prop('disabled', true).text(<?php echo wp_json_encode(__('Creating...', 'cobra-ai')); ?>);

        $.post(ajaxurl, {
            action: 'cobra_sp_create_page',
            page_type: type,
            nonce: nonce
        }, function(response){
            if (response.success) {
                var d = response.data;
                // Add the new page to the <select> and select it
                var $select = $(selectId);
                $select.append(
                    $('<option>', { value: d.page_id, text: d.title, selected: true })
                );

                // Replace button with edit + view links
                var $row = $btn.closest('.cobra-sp-page-row');
                $btn.remove();

                var $created = $('<span class="cobra-sp-page-created"><span class="dashicons dashicons-yes-alt"></span>' +
                    <?php echo wp_json_encode(__('Page created', 'cobra-ai')); ?> + '</span>');
                $row.append($created);

                var editBtn = $('<a>', {
                    href: d.edit_url,
                    class: 'button',
                    target: '_blank',
                    html: '<span class="dashicons dashicons-edit" style="vertical-align:middle;margin-top:3px;"></span> ' + <?php echo wp_json_encode(__('Edit', 'cobra-ai')); ?>
                });
                $row.append(editBtn);

                // Fade out the "created" notice after 3s
                setTimeout(function(){ $created.fadeOut(400, function(){ $(this).remove(); }); }, 3000);
            } else {
                alert(response.data && response.data.message ? response.data.message : <?php echo wp_json_encode(__('Error creating the page.', 'cobra-ai')); ?>);
                $btn.prop('disabled', false).text(<?php echo wp_json_encode(__('Create page', 'cobra-ai')); ?>);
            }
        }).fail(function(){
            alert(<?php echo wp_json_encode(__('Network error.', 'cobra-ai')); ?>);
            $btn.prop('disabled', false).text(<?php echo wp_json_encode(__('Create page', 'cobra-ai')); ?>);
        });
    });
});
</script>
