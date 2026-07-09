<?php
/** @var \WP_Post[] $products */
/** @var int $columns */
/** @var \CobraAI\Features\StripePayments\Feature $feature */
defined('ABSPATH') || exit;
?>
<div class="cobra-products-grid" style="display:grid;grid-template-columns:repeat(<?php echo (int) $columns; ?>,1fr);gap:20px;">
    <?php if (empty($products)): ?>
        <p><?php _e('No products available.', 'cobra-ai'); ?></p>
    <?php else: ?>
        <?php foreach ($products as $product):
            include __DIR__ . '/product-card.php';
        endforeach; ?>
    <?php endif; ?>
</div>
