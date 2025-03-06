<?php
// /views/admin/plan-meta-box.php
defined('ABSPATH') || exit;

// Get current plan data
// $plan_data = get_post_meta($post->ID, '_stripe_plan_data', true) ?: [];
$stripe_price_id = get_post_meta($post->ID, '_stripe_price_id', true);
$stripe_product_id = get_post_meta($post->ID, '_stripe_product_id', true);

// Default values
$defaults = [
    'price' => '',
    'currency' => 'USD',
    'billing_interval' => 'month',
    'interval_count' => 1,
    'trial_days' => 0,
    'features' => [],
    'status' => 'active',
    'public' => true
];

$plan_data = wp_parse_args($plan_data, $defaults);

// Add nonce for security
wp_nonce_field('stripe_plan_save', 'stripe_plan_nonce');
?>

<div class="cobra-plan-meta">
    <!-- Price Information -->
    <div class="plan-section">
        <h4><?php echo esc_html__('Price Information', 'cobra-ai'); ?></h4>
        <div class="form-field">
            <label for="plan_price">
                <?php echo esc_html__('Price', 'cobra-ai'); ?>
                <span class="required">*</span>
            </label>
            <input type="number" 
                   id="plan_price" 
                   name="stripe_plan[price]" 
                   value="<?php echo esc_attr($plan_data['price']); ?>"
                   step="0.01" 
                   min="0" 
                   required>
        </div>

        <div class="form-field">
            <label for="plan_currency">
                <?php echo esc_html__('Currency', 'cobra-ai'); ?>
            </label>
            <select id="plan_currency" name="stripe_plan[currency]">
                <option value="USD" <?php selected($plan_data['currency'], 'USD'); ?>>USD</option>
                <option value="EUR" <?php selected($plan_data['currency'], 'EUR'); ?>>EUR</option>
                <option value="GBP" <?php selected($plan_data['currency'], 'GBP'); ?>>GBP</option>
                <!-- Add more currencies as needed -->
            </select>
        </div>
    </div>

    <!-- Billing Information -->
    <div class="plan-section">
        <h4><?php echo esc_html__('Billing Information', 'cobra-ai'); ?></h4>
        <div class="form-field">
            <label for="plan_interval">
                <?php echo esc_html__('Billing Interval', 'cobra-ai'); ?>
            </label>
            <select id="plan_interval" name="stripe_plan[billing_interval]">
                <option value="month" <?php selected($plan_data['billing_interval'], 'month'); ?>>
                    <?php echo esc_html__('Monthly', 'cobra-ai'); ?>
                </option>
                <option value="year" <?php selected($plan_data['billing_interval'], 'year'); ?>>
                    <?php echo esc_html__('Yearly', 'cobra-ai'); ?>
                </option>
                <option value="week" <?php selected($plan_data['billing_interval'], 'week'); ?>>
                    <?php echo esc_html__('Weekly', 'cobra-ai'); ?>
                </option>
                <option value="day" <?php selected($plan_data['billing_interval'], 'day'); ?>>
                    <?php echo esc_html__('Daily', 'cobra-ai'); ?>
                </option>
            </select>
        </div>

        <div class="form-field">
            <label for="plan_interval_count">
                <?php echo esc_html__('Interval Count', 'cobra-ai'); ?>
            </label>
            <input type="number" 
                   id="plan_interval_count" 
                   name="stripe_plan[interval_count]" 
                   value="<?php echo esc_attr($plan_data['interval_count']); ?>"
                   min="1" 
                   max="12">
            <p class="description">
                <?php echo esc_html__('For example, interval 3 with months means bill every 3 months.', 'cobra-ai'); ?>
            </p>
        </div>
    </div>

    <!-- Trial Period -->
    <div class="plan-section">
        <h4><?php echo esc_html__('Trial Period', 'cobra-ai'); ?></h4>
        <div class="form-field">
            <label for="plan_trial_days">
                <?php echo esc_html__('Trial Days', 'cobra-ai'); ?>
            </label>
            <input type="number" 
                   id="plan_trial_days" 
                   name="stripe_plan[trial_days]" 
                   value="<?php echo esc_attr($plan_data['trial_days']); ?>"
                   min="0" 
                   max="730">
            <p class="description">
                <?php echo esc_html__('Number of trial days (0 for no trial).', 'cobra-ai'); ?>
            </p>
        </div>
    </div>

    <!-- Plan Features -->
    <div class="plan-section">
        <h4><?php echo esc_html__('Features', 'cobra-ai'); ?></h4>
        <div class="form-field feature-list">
            <?php if (!empty($plan_data['features'])): ?>
                <?php foreach ($plan_data['features'] as $feature): ?>
                    <div class="feature-item">
                        <input type="text" 
                               name="stripe_plan[features][]" 
                               value="<?php echo esc_attr($feature); ?>"
                               placeholder="<?php echo esc_attr__('Add a feature...', 'cobra-ai'); ?>">
                        <button type="button" class="remove-feature">&times;</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="feature-item">
                <input type="text" 
                       name="stripe_plan[features][]" 
                       placeholder="<?php echo esc_attr__('Add a feature...', 'cobra-ai'); ?>">
                <button type="button" class="remove-feature">&times;</button>
            </div>
            <button type="button" class="add-feature button-secondary">
                <?php echo esc_html__('Add Feature', 'cobra-ai'); ?>
            </button>
        </div>
    </div>

    <!-- Plan Status -->
    <div class="plan-section">
        <h4><?php echo esc_html__('Plan Status', 'cobra-ai'); ?></h4>
        <div class="form-field">
            <label>
                <input type="radio" 
                       name="stripe_plan[status]" 
                       value="active" 
                       <?php checked($plan_data['status'], 'active'); ?>>
                <?php echo esc_html__('Active', 'cobra-ai'); ?>
            </label>
            <br>
            <label>
                <input type="radio" 
                       name="stripe_plan[status]" 
                       value="inactive" 
                       <?php checked($plan_data['status'], 'inactive'); ?>>
                <?php echo esc_html__('Inactive', 'cobra-ai'); ?>
            </label>
        </div>
        <div class="form-field">
            <label>
                <input type="checkbox" 
                       name="stripe_plan[public]" 
                       value="1" 
                       <?php checked($plan_data['public']); ?>>
                <?php echo esc_html__('Show in public plan list', 'cobra-ai'); ?>
            </label>
        </div>
    </div>

    <!-- Stripe IDs (readonly) -->
    <?php if ($stripe_price_id || $stripe_product_id): ?>
        <div class="plan-section">
            <h4><?php echo esc_html__('Stripe Information', 'cobra-ai'); ?></h4>
            <?php if ($stripe_price_id): ?>
                <div class="form-field">
                    <label><?php echo esc_html__('Stripe Price ID:', 'cobra-ai'); ?></label>
                    <code><?php echo esc_html($stripe_price_id); ?></code>
                </div>
            <?php endif; ?>
            <?php if ($stripe_product_id): ?>
                <div class="form-field">
                    <label><?php echo esc_html__('Stripe Product ID:', 'cobra-ai'); ?></label>
                    <code><?php echo esc_html($stripe_product_id); ?></code>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Add feature
    $('.add-feature').on('click', function() {
        const $list = $(this).closest('.feature-list');
        const $item = $list.find('.feature-item').first().clone();
        $item.find('input').val('');
        $item.insertBefore($(this));
    });

    // Remove feature
    $(document).on('click', '.remove-feature', function() {
        const $list = $(this).closest('.feature-list');
        if ($list.find('.feature-item').length > 1) {
            $(this).closest('.feature-item').remove();
        } else {
            $list.find('input').val('');
        }
    });
});
</script>

<style>
.cobra-plan-meta {
    padding: 12px;
}

.plan-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid #eee;
}

.plan-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.form-field {
    margin-bottom: 16px;
}

.form-field label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
}

.form-field input[type="number"],
.form-field input[type="text"],
.form-field select {
    width: 100%;
    max-width: 400px;
}

.feature-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.feature-item input {
    flex: 1;
    margin-right: 8px;
}

.remove-feature {
    background: none;
    border: none;
    color: #cc0000;
    cursor: pointer;
    padding: 0 6px;
    font-size: 18px;
}

.add-feature {
    margin-top: 8px !important;
}

.required {
    color: #cc0000;
}

.description {
    color: #666;
    font-style: italic;
    margin-top: 4px;
}

code {
    background: #f5f5f5;
    padding: 4px 8px;
    border-radius: 3px;
}
</style>