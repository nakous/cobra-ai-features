<?php
/** @var \CobraAI\Features\StripePayments\Feature $feature */
/** @var object|null $order */
defined('ABSPATH') || exit;
?>
<div class="cobra-sp-result cobra-sp-result--success">
    <?php if ($order && $order->status === 'paid'):
        $product = get_post((int) $order->product_id);
    ?>
        <div class="cobra-sp-result-icon">
            <svg viewBox="0 0 52 52" width="64" height="64"><circle cx="26" cy="26" r="25" fill="none" stroke="#00a32a" stroke-width="2"/><path fill="none" stroke="#00a32a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M14 27l7 7 16-16"/></svg>
        </div>
        <h2 class="cobra-sp-result-title"><?php _e('Thank you for your purchase!', 'cobra-ai'); ?></h2>
        <p class="cobra-sp-result-text"><?php _e('Your payment has been confirmed. A confirmation will be sent to you by email.', 'cobra-ai'); ?></p>

        <div class="cobra-sp-result-details">
            <div class="cobra-sp-result-row">
                <span class="cobra-sp-result-label"><?php _e('Order', 'cobra-ai'); ?></span>
                <span class="cobra-sp-result-value">#<?php echo (int) $order->id; ?></span>
            </div>
            <?php if ($product): ?>
            <div class="cobra-sp-result-row">
                <span class="cobra-sp-result-label"><?php _e('Product', 'cobra-ai'); ?></span>
                <span class="cobra-sp-result-value"><?php echo esc_html($product->post_title); ?></span>
            </div>
            <?php endif; ?>
            <div class="cobra-sp-result-row">
                <span class="cobra-sp-result-label"><?php _e('Amount', 'cobra-ai'); ?></span>
                <span class="cobra-sp-result-value cobra-sp-result-amount">
                    <?php echo esc_html(number_format((float) $order->amount, 2) . ' ' . $order->currency); ?>
                </span>
            </div>
            <div class="cobra-sp-result-row">
                <span class="cobra-sp-result-label"><?php _e('Date', 'cobra-ai'); ?></span>
                <span class="cobra-sp-result-value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order->created_at))); ?></span>
            </div>
        </div>

        <a href="<?php echo esc_url(home_url()); ?>" class="cobra-sp-result-btn">
            <?php _e('Back to home', 'cobra-ai'); ?>
        </a>

    <?php else: ?>
        <div class="cobra-sp-result-icon">
            <svg viewBox="0 0 52 52" width="64" height="64"><circle cx="26" cy="26" r="25" fill="none" stroke="#dba617" stroke-width="2"/><line x1="26" y1="14" x2="26" y2="30" stroke="#dba617" stroke-width="3" stroke-linecap="round"/><circle cx="26" cy="37" r="2" fill="#dba617"/></svg>
        </div>
        <h2 class="cobra-sp-result-title"><?php _e('Payment processing', 'cobra-ai'); ?></h2>
        <p class="cobra-sp-result-text"><?php _e('Your payment is being confirmed. Please wait a moment and refresh the page.', 'cobra-ai'); ?></p>
        <button onclick="location.reload()" class="cobra-sp-result-btn cobra-sp-result-btn--secondary">
            <?php _e('Refresh', 'cobra-ai'); ?>
        </button>
    <?php endif; ?>
</div>

<style>
.cobra-sp-result {
    max-width: 520px;
    margin: 40px auto;
    text-align: center;
    padding: 40px 30px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
}
.cobra-sp-result-icon { margin-bottom: 20px; }
.cobra-sp-result-title { font-size: 24px; margin: 0 0 10px; color: #1d2327; }
.cobra-sp-result-text { color: #646970; font-size: 15px; margin: 0 0 24px; line-height: 1.5; }
.cobra-sp-result-details {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px 20px;
    margin: 0 0 24px;
    text-align: left;
}
.cobra-sp-result-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e8e8e8;
}
.cobra-sp-result-row:last-child { border-bottom: none; }
.cobra-sp-result-label { color: #646970; font-size: 14px; }
.cobra-sp-result-value { font-weight: 600; color: #1d2327; font-size: 14px; }
.cobra-sp-result-amount { color: #635bff; font-size: 16px; }
.cobra-sp-result-btn {
    display: inline-block;
    padding: 10px 28px;
    background: #635bff;
    color: #fff !important;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background .2s;
}
.cobra-sp-result-btn:hover { background: #4b44d9; color: #fff !important; }
.cobra-sp-result-btn--secondary { background: #dba617; }
.cobra-sp-result-btn--secondary:hover { background: #c49516; }
</style>
