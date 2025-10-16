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
    </div>
</div>

<script>
function initCancellationModal() {
    jQuery(function($) {
        const $modal = $('#cancel-subscription-modal');
        let currentSubscriptionId = null;
        let currentNonce = null;

        // Show modal when cancel button is clicked
        $('.cancel-subscription').on('click', function(e) {
            e.preventDefault();
            currentSubscriptionId = $(this).data('id');
            currentNonce = $(this).data('nonce');
            $modal.show();
        });

        // Close modal
        $('.close-modal').on('click', function() {
            $modal.hide();
        });

        // Confirm cancellation
        $('#confirm-cancel').on('click', function() {
            const $button = $(this);
            const immediately = $('input[name="cancel_type"]:checked').val() === 'immediately';
            
            $button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_cancel',
                    subscription_id: currentSubscriptionId,
                    immediately: immediately ? 1 : 0,
                    _ajax_nonce: currentNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Subscription cancelled successfully', 'cobra-ai')); ?>');
                        location.reload();
                    } else {
                        alert(response.data.error || '<?php echo esc_js(__('Failed to cancel subscription', 'cobra-ai')); ?>');
                        $button.prop('disabled', false).text('<?php echo esc_js(__('Confirm Cancellation', 'cobra-ai')); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('An error occurred', 'cobra-ai')); ?>');
                    $button.prop('disabled', false).text('<?php echo esc_js(__('Confirm Cancellation', 'cobra-ai')); ?>');
                }
            });
        });
    });
}
</script>