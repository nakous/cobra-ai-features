<?php
// views/admin/partials/modal-cancel.php
defined('ABSPATH') || exit;
?>

<div id="cancel-subscription-modal" class="cobra-modal">
    <div class="cobra-modal-content">
        <div class="cobra-modal-header">
            <h2><?php echo esc_html__('Cancel Subscription', 'cobra-ai'); ?></h2>
            <button type="button" class="close-modal">×</button>
        </div>
        <div class="cobra-modal-body">
            <p><?php echo esc_html__('How would you like to cancel this subscription?', 'cobra-ai'); ?></p>
            
            <div class="cobra-modal-options">
                <label>
                    <input type="radio" name="cancel_type" value="end_of_period" checked>
                    <?php echo esc_html__('At end of billing period', 'cobra-ai'); ?>
                </label>
                <br>
                <label>
                    <input type="radio" name="cancel_type" value="immediately">
                    <?php echo esc_html__('Immediately', 'cobra-ai'); ?>
                </label>
            </div>

            <p class="description">
                <?php echo esc_html__('Note: Immediate cancellation will stop all future charges but no refund will be issued.', 'cobra-ai'); ?>
            </p>

            <div class="cobra-modal-actions">
                <button type="button" class="button button-primary" id="confirm-cancel">
                    <?php echo esc_html__('Confirm Cancellation', 'cobra-ai'); ?>
                </button>
                <button type="button" class="button button-secondary close-modal">
                    <?php echo esc_html__('Cancel', 'cobra-ai'); ?>
                </button>
            </div>
        </div>