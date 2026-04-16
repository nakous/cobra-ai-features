<?php
/** @var \WP_Post $post */
/** @var float|string $amount */
/** @var string $currency */
/** @var string $product_type */
/** @var string $stripe_price */
/** @var array $product_types */
/** @var \CobraAI\Features\StripePayments\Feature $feature */
defined('ABSPATH') || exit;
?>
<style>
.cobra-product-meta .form-field { margin-bottom: 14px; }
.cobra-product-meta label { display: block; font-weight: 600; margin-bottom: 4px; }
.cobra-product-meta input[type="text"],
.cobra-product-meta input[type="number"],
.cobra-product-meta select { width: 100%; max-width: 400px; }
.cobra-product-meta .row { display: flex; gap: 16px; }
.cobra-product-meta .row > .form-field { flex: 1; }
</style>

<div class="cobra-product-meta">
    <div class="row">
        <div class="form-field">
            <label for="_price_amount"><?php _e('Price', 'cobra-ai'); ?></label>
            <input type="number" id="_price_amount" name="_price_amount" value="<?php echo esc_attr($amount); ?>" step="0.01" min="0">
        </div>
        <div class="form-field">
            <label for="_price_currency"><?php _e('Currency', 'cobra-ai'); ?></label>
            <input type="text" id="_price_currency" name="_price_currency" value="<?php echo esc_attr($currency); ?>" maxlength="3" style="text-transform:uppercase;max-width:100px;">
        </div>
    </div>

    <div class="form-field">
        <label for="_product_type"><?php _e('Product type', 'cobra-ai'); ?></label>
        <select id="_product_type" name="_product_type">
            <?php foreach ($product_types as $slug => $label): ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($product_type, $slug); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Third-party features can add their own types via the filter', 'cobra-ai'); ?>
            <code>cobra_stripepayments_product_types</code>.
        </p>
    </div>

    <div class="form-field">
        <label for="_stripe_price_id"><?php _e('Stripe Price ID (optional)', 'cobra-ai'); ?></label>
        <input type="text" id="_stripe_price_id" name="_stripe_price_id" value="<?php echo esc_attr($stripe_price); ?>" placeholder="price_XXXXXXXXXXXX">
        <p class="description">
            <?php _e('Leave empty to use the price above (created on the fly in Checkout). Fill in to point to an existing Stripe Price.', 'cobra-ai'); ?>
        </p>
    </div>

    <?php
    /**
     * Extension point: other features (e.g. credits) hook here to add their
     * own fields (shown/hidden via JS based on _product_type).
     */
    do_action('cobra_stripepayments_product_meta_fields', $post);
    ?>
</div>

<script>
(function($){
    function refresh() {
        var type = $('#_product_type').val();
        $('[data-product-type]').each(function(){
            var target = $(this).data('product-type');
            $(this).toggle(target === type);
        });
    }
    $(document).on('change', '#_product_type', refresh);
    $(refresh);
})(jQuery);
</script>
