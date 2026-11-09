<?php
defined('ABSPATH') || exit;
?>
<div class="cobra-sp-result cobra-sp-result--cancel">
    <div class="cobra-sp-result-icon">
        <svg viewBox="0 0 52 52" width="64" height="64"><circle cx="26" cy="26" r="25" fill="none" stroke="#d63638" stroke-width="2"/><line x1="18" y1="18" x2="34" y2="34" stroke="#d63638" stroke-width="3" stroke-linecap="round"/><line x1="34" y1="18" x2="18" y2="34" stroke="#d63638" stroke-width="3" stroke-linecap="round"/></svg>
    </div>
    <h2 class="cobra-sp-result-title"><?php _e('Payment cancelled', 'cobra-ai'); ?></h2>
    <p class="cobra-sp-result-text"><?php _e('Your payment has been cancelled. No amount has been charged.', 'cobra-ai'); ?></p>
    <a href="<?php echo esc_url(home_url()); ?>" class="cobra-sp-result-btn cobra-sp-result-btn--back">
        <?php _e('Back to home', 'cobra-ai'); ?>
    </a>
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
.cobra-sp-result-btn--back { background: #50575e; }
.cobra-sp-result-btn--back:hover { background: #3c4043; }
</style>
