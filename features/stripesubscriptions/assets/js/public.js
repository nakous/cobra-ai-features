// Initialize subscription modals and handlers
function initSubscriptionModals() {
    const $ = jQuery;

    // Handle payment method update
    $('.update-payment').on('click', function() {
        $('#update-payment-modal').show();
    });

    // Handle subscription cancellation
    $('.cancel-subscription').on('click', function() {
        $('#cancel-subscription-modal').show();
    });

    // Close modals
    $('.close-modal').on('click', function() {
        $(this).closest('.cobra-modal').hide();
    });

    // Close on outside click
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('cobra-modal')) {
            $('.cobra-modal').hide();
        }
    });
}

// Handle payment method update
function initPaymentUpdate() {
    const $ = jQuery;
    const stripe = Stripe(cobra_vars.stripe_key);
    const elements = stripe.elements();
    let cardElement = null;

    // Create card element when modal is shown
    $('.update-payment').on('click', function() {
        if (!cardElement) {
            cardElement = elements.create('card');
            cardElement.mount('#card-element');
        }
    });

    // Handle form submission
    $('#payment-update-form').on('submit', async function(e) {
        e.preventDefault();
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        
        $button.prop('disabled', true)
            .html(cobra_vars.i18n.processing);

        try {
            // Create payment method
            const { paymentMethod, error } = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement
            });

            if (error) {
                throw new Error(error.message);
            }

            // Update subscription payment method
            const response = await $.ajax({
                url: cobra_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_update_payment',
                    subscription_id: $form.find('input[name="subscription_id"]').val(),
                    payment_method: paymentMethod.id,
                    _ajax_nonce: $form.find('#_wpnonce').val()
                }
            });

            if (!response.success) {
                throw new Error(response.data.message || cobra_vars.i18n.update_failed);
            }

            location.reload();

        } catch (error) {
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = error.message;
            $button.prop('disabled', false)
                .text(cobra_vars.i18n.try_again);
        }
    });
}

// Handle subscription cancel/resume
function initSubscriptionCancel() {
    const $ = jQuery;

    // Cancel subscription
    $('#confirm-cancel').on('click', async function() {
        const $button = $(this);
        const $subscriptionButton = $('.cancel-subscription');
        const subscriptionId = $subscriptionButton.data('id');
        const nonce = $subscriptionButton.data('nonce');
        
        $button.prop('disabled', true)
            .html(cobra_vars.i18n.processing);

        try {
            const response = await $.ajax({
                url: cobra_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_cancel',
                    subscription_id: subscriptionId,
                    _ajax_nonce: nonce
                }
            });

            if (!response.success) {
                throw new Error(response.data.message || cobra_vars.i18n.cancel_failed);
            }

            location.reload();

        } catch (error) {
            alert(error.message);
            $button.prop('disabled', false)
                .text(cobra_vars.i18n.try_again);
        }
    });

    // Resume subscription
    $('.resume-subscription').on('click', async function() {
        const $button = $(this);
        const subscriptionId = $button.data('id');
        const nonce = $button.data('nonce');
        
        if (!confirm(cobra_vars.i18n.confirm_resume)) {
            return;
        }

        $button.prop('disabled', true)
            .html(cobra_vars.i18n.processing);

        try {
            const response = await $.ajax({
                url: cobra_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cobra_subscription_resume',
                    subscription_id: subscriptionId,
                    _ajax_nonce: nonce
                }
            });

            if (!response.success) {
                throw new Error(response.data.message || cobra_vars.i18n.resume_failed);
            }

            location.reload();

        } catch (error) {
            alert(error.message);
            $button.prop('disabled', false)
                .text(cobra_vars.i18n.try_again);
        }
    });
}

// jQuery(function($) {
    
//     $('.subscribe-btn').on('click', function(e) {
//         e.preventDefault();
//         const planId = $(this).data('plan');
//         // window.location.href = `${CobraSubscription.checkout_url}?plan=${planId}`;
//         const url = new URL(CobraSubscription.checkout_url);
// url.searchParams.append('plan', planId);
// window.location.href = url.toString();
//         // console.log(`${CobraSubscription.checkout_url}?plan=${planId}`);

//     });
//  });