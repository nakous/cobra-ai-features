<?php
// views/admin/partials/modal-refund.php
defined('ABSPATH') || exit;
?>

<div id="refund-payment-modal" class="cobra-modal">
    <div class="cobra-modal-content">
        <div class="cobra-modal-header">
            <h2><?php echo esc_html__('Refund Payment', 'cobra-ai'); ?></h2>
            <button type="button" class="close-modal">×</button>
        </div>
        <div class="cobra-modal-body">
            <p><?php echo esc_html__('Please specify the refund amount and reason:', 'cobra-ai'); ?></p>
            
            <div class="form-field">
                <label for="refund_amount">
                    <?php echo esc_html__('Refund Amount', 'cobra-ai'); ?>
                </label>
                <div class="amount-input">
                    <span class="currency-symbol">$</span>
                    <input type="number" 
                           id="refund_amount" 
                           name="refund_amount" 
                           step="0.01" 
                           min="0.01">
                    <button type="button" class="button-link refund-full">
                        <?php echo esc_html__('Refund full amount', 'cobra-ai'); ?>
                    </button>
                </div>
            </div>

            <div class="form-field">
                <label for="refund_reason">
                    <?php echo esc_html__('Reason', 'cobra-ai'); ?>
                </label>
                <select id="refund_reason" name="refund_reason">
                    <option value="requested_by_customer">
                        <?php echo esc_html__('Requested by customer', 'cobra-ai'); ?>
                    </option>
                    <option value="duplicate">
                        <?php echo esc_html__('Duplicate charge', 'cobra-ai'); ?>
                    </option>
                    <option value="fraudulent">
                        <?php echo esc_html__('Fraudulent', 'cobra-ai'); ?>
                    </option>
                    <option value="other">
                        <?php echo esc_html__('Other', 'cobra-ai'); ?>
                    </option>
                </select>
            </div>

            <div class="form-field reason-details" style="display: none;">
                <label for="refund_reason_details">
                    <?php echo esc_html__('Additional Details', 'cobra-ai'); ?>
                </label>
                <textarea id="refund_reason_details" 
                         name="refund_reason_details" 
                         rows="3"></textarea>
            </div>

            <p class="description">
                <?php echo esc_html__('Note: This action cannot be undone.', 'cobra-ai'); ?>
            </p>

            <div class="cobra-modal-actions">
                <button type="button" class="button button-primary" id="confirm-refund">
                    <?php echo esc_html__('Process Refund', 'cobra-ai'); ?>
                </button>
                <button type="button" class="button button-secondary close-modal">
                    <?php echo esc_html__('Cancel', 'cobra-ai'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function initRefundModal() {
    jQuery(function($) {
        const $modal = $('#refund-payment-modal');
        let currentPaymentId = null;
        let currentNonce = null;
        let maxRefundAmount = 0;

        // Show modal
        $('.refund-payment').on('click', function(e) {
            e.preventDefault();
            currentPaymentId = $(this).data('id');
            currentNonce = $(this).data('nonce');
            maxRefundAmount = parseFloat($(this).data('amount'));
            
            // Reset form
            $('#refund_amount').val('').attr('max', maxRefundAmount);
            $('#refund_reason').val('requested_by_customer');
            $('#refund_reason_details').val('');
            $('.reason-details').hide();
            
            $modal.show();
        });

        // Handle full amount button
        $('.refund-full').on('click', function() {
            $('#refund_amount').val(maxRefundAmount.toFixed(2));
        });

        // Show/hide reason details
        $('#refund_reason').on('change', function() {
            $('.reason-details').toggle($(this).val() === 'other');
        });

        // Process refund
        $('#confirm-refund').on('click', function() {
            const $button = $(this);
            const amount = parseFloat($('#refund_amount').val());
            
            // Validate amount
            if (!amount || amount <= 0 || amount > maxRefundAmount) {
                alert('<?php echo esc_js(__('Please enter a valid refund amount', 'cobra-ai')); ?>');
                return;
            }

            // Get reason
            const reason = $('#refund_reason').val();
            const reasonDetails = $('#refund_reason_details').val();

            // Disable button and show loading
            $button.prop('disabled', true)
                .html('<span class="spinner is-active"></span> <?php echo esc_js(__('Processing...', 'cobra-ai')); ?>');

            // Process refund
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_refund',
                    payment_id: currentPaymentId,
                    amount: amount,
                    reason: reason,
                    reason_details: reasonDetails,
                    _ajax_nonce: currentNonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('Failed to process refund', 'cobra-ai')); ?>');
                        $button.prop('disabled', false)
                            .text('<?php echo esc_js(__('Process Refund', 'cobra-ai')); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Failed to process request', 'cobra-ai')); ?>');
                    $button.prop('disabled', false)
                        .text('<?php echo esc_js(__('Process Refund', 'cobra-ai')); ?>');
                }
            });
        });

        // Close modal
        $('.close-modal').on('click', function() {
            $modal.hide();
        });
    });
}
</script>