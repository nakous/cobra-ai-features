<?php
/** @var \WP_Post $product */
/** @var \CobraAI\Features\StripePayments\Feature $feature */
defined('ABSPATH') || exit;

$amount   = (float) get_post_meta($product->ID, '_price_amount', true);
$currency = get_post_meta($product->ID, '_price_currency', true) ?: 'USD';
$type     = get_post_meta($product->ID, '_product_type', true) ?: 'standard';
$nonce    = wp_create_nonce('cobra-stripepayments-nonce');
?>
<div class="cobra-product-card" data-product-id="<?php echo (int) $product->ID; ?>">
    <?php if (has_post_thumbnail($product)): ?>
        <div class="cobra-product-image"><?php echo get_the_post_thumbnail($product, 'medium'); ?></div>
    <?php endif; ?>
    <div class="cobra-product-body">
        <h3 class="cobra-product-title"><?php echo esc_html(get_the_title($product)); ?></h3>
        <div class="cobra-product-desc"><?php echo wp_kses_post(wpautop($product->post_excerpt ?: wp_trim_words(strip_shortcodes($product->post_content), 30))); ?></div>
        <div class="cobra-product-price">
            <?php echo esc_html(number_format($amount, 2) . ' ' . $currency); ?>
        </div>

        <?php if (is_user_logged_in()): ?>
            <button class="cobra-buy-button"
                    data-product-id="<?php echo (int) $product->ID; ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                <?php _e('Buy now', 'cobra-ai'); ?>
            </button>
        <?php else: ?>
            <a class="cobra-buy-button cobra-buy-button--login" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                <?php _e('Log in to purchase', 'cobra-ai'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<style>
.cobra-product-card {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
    display: flex;
    flex-direction: column;
    transition: box-shadow .2s, transform .2s;
}
.cobra-product-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,.12);
    transform: translateY(-2px);
}
.cobra-product-image { line-height: 0; }
.cobra-product-image img { width: 100%; height: 200px; object-fit: cover; }
.cobra-product-body { padding: 20px; display: flex; flex-direction: column; flex: 1; }
.cobra-product-title { font-size: 18px; margin: 0 0 8px; color: #1d2327; }
.cobra-product-desc { font-size: 14px; color: #646970; line-height: 1.5; flex: 1; margin: 0 0 16px; }
.cobra-product-desc p { margin: 0; }
.cobra-product-price {
    font-size: 24px;
    font-weight: 700;
    color: #635bff;
    margin: 0 0 16px;
}
.cobra-buy-button {
    display: block;
    width: 100%;
    padding: 12px;
    background: #635bff;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: background .2s;
    box-sizing: border-box;
}
.cobra-buy-button:hover { background: #4b44d9; color: #fff; }
.cobra-buy-button:disabled { background: #a0a0a0; cursor: wait; }
.cobra-buy-button--login { background: #50575e; }
.cobra-buy-button--login:hover { background: #3c4043; color: #fff; }
</style>

<script>
(function(){
    if (window.CobraStripePaymentsInit) return;
    window.CobraStripePaymentsInit = true;
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.cobra-buy-button');
        if (!btn || btn.tagName === 'A') return;
        e.preventDefault();
        var productId = btn.getAttribute('data-product-id');
        var nonce = btn.getAttribute('data-nonce');
        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = <?php echo wp_json_encode(__('Processing...', 'cobra-ai')); ?>;

        var body = new URLSearchParams();
        body.append('action', 'cobra_stripepayments_checkout');
        body.append('product_id', productId);
        body.append('nonce', nonce);

        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString()
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data && data.success && data.data && data.data.checkout_url) {
                window.location.href = data.data.checkout_url;
            } else {
                alert((data && data.data && data.data.message) || <?php echo wp_json_encode(__('Error creating the checkout session.', 'cobra-ai')); ?>);
                btn.disabled = false;
                btn.textContent = originalText;
            }
        })
        .catch(function(){
            alert(<?php echo wp_json_encode(__('Network error. Please try again.', 'cobra-ai')); ?>);
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
})();
</script>
