/**
 * COBRA AI STRIPE SUBSCRIPTIONS
 * Public JavaScript Module
 */

(function($) {
    'use strict';

    // Main object
    const CobraSubscriptions = {
        // Configuration
        config: {
            selectors: {
                // Modals
                cancelModal: '#cancelSubscriptionModal',
                updatePaymentModal: '#updatePaymentModal',
                
                // Buttons
                cancelBtn: '#cobra-cancel-subscription',
                resumeBtn: '#cobra-resume-subscription',
                updatePaymentBtn: '#cobra-update-payment',
                
                // Forms
                cancelForm: '#cancel-subscription-form',
                updatePaymentForm: '#update-payment-form',
                
                // Other elements
                modalClose: '.cobra-modal-close',
                loadingSpinner: '.loading-spinner',
                messageContainer: '.cobra-message'
            },
            endpoints: {
                cancel: 'cobra_cancel_subscription',
                resume: 'cobra_resume_subscription',
                updatePayment: 'cobra_update_payment_method'
            }
        },

        // Initialize the module
        init: function() {
            this.bindEvents();
            this.setupModals();
            console.log('âœ… Cobra Subscriptions initialized');
        },

        // Bind event handlers
        bindEvents: function() {
            const self = this;
            
            // Cancel subscription
            $(document).on('click', self.config.selectors.cancelBtn, function(e) {
                e.preventDefault();
                self.showCancelModal();
            });

            // Resume subscription
            $(document).on('click', self.config.selectors.resumeBtn, function(e) {
                e.preventDefault();
                self.handleResumeSubscription();
            });

            // Update payment method
            $(document).on('click', self.config.selectors.updatePaymentBtn, function(e) {
                e.preventDefault();
                self.handleUpdatePayment();
            });

            // Cancel form submission
            $(document).on('submit', self.config.selectors.cancelForm, function(e) {
                e.preventDefault();
                self.handleCancelSubscription($(this));
            });

            // Modal close handlers
            $(document).on('click', self.config.selectors.modalClose, function() {
                self.closeModals();
            });

            // Close modal on outside click
            $(document).on('click', '.cobra-modal', function(e) {
                if (e.target === this) {
                    self.closeModals();
                }
            });

            // ESC key to close modals
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModals();
                }
            });

            // Radio button selection styling
            $(document).on('change', 'input[name="cancellation_type"]', function() {
                $('.cobra-radio-option').removeClass('selected');
                $(this).closest('.cobra-radio-option').addClass('selected');
            });
        },

        // Setup modal functionality
        setupModals: function() {
            // Initialize any modal-specific functionality
            console.log('ðŸ“± Modals initialized');
        },

        // Show cancellation modal
        showCancelModal: function() {
            const modal = $(this.config.selectors.cancelModal);
            if (modal.length) {
                modal.addClass('show');
                // Focus first radio button
                modal.find('input[name="cancellation_type"]:first').focus();
            }
        },

        // Handle subscription cancellation
        handleCancelSubscription: function($form) {
            const self = this;
            const formData = new FormData($form[0]);
            const submitBtn = $form.find('#confirm-cancellation');

            // Show loading state
            this.setLoadingState(submitBtn, true);

            // Prepare AJAX data
            const ajaxData = {
                action: self.config.endpoints.cancel,
                subscription_id: formData.get('subscription_id'),
                cancellation_type: formData.get('cancellation_type'),
                cancellation_reason: formData.get('cancellation_reason'),
                nonce: formData.get('nonce')
            };

            // Send AJAX request
            $.post(cobra_vars.ajax_url, ajaxData)
                .done(function(response) {
                    if (response.success) {
                        self.showMessage('success', response.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        self.showMessage('error', response.data.message || 'Une erreur est survenue');
                    }
                })
                .fail(function() {
                    self.showMessage('error', 'Erreur de connexion. Veuillez rÃ©essayer.');
                })
                .always(function() {
                    self.setLoadingState(submitBtn, false);
                });
        },

        // Handle subscription resumption
        handleResumeSubscription: function() {
            const self = this;
            const subscriptionId = $(this.config.selectors.resumeBtn).data('subscription-id');

            if (!subscriptionId) {
                this.showMessage('error', 'ID d\'abonnement manquant');
                return;
            }

            // Show loading state on button
            const resumeBtn = $(this.config.selectors.resumeBtn);
            this.setLoadingState(resumeBtn, true);

            // Send AJAX request
            $.post(cobra_vars.ajax_url, {
                action: self.config.endpoints.resume,
                subscription_id: subscriptionId,
                nonce: cobra_vars.nonce
            })
            .done(function(response) {
                if (response.success) {
                    self.showMessage('success', response.data.message);
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    self.showMessage('error', response.data.message || 'Erreur lors de la reprise');
                }
            })
            .fail(function() {
                self.showMessage('error', 'Erreur de connexion. Veuillez rÃ©essayer.');
            })
            .always(function() {
                self.setLoadingState(resumeBtn, false);
            });
        },

        // Handle payment method update
        handleUpdatePayment: function() {
            const self = this;
            const subscriptionId = $(this.config.selectors.updatePaymentBtn).data('subscription-id');

            if (!subscriptionId) {
                this.showMessage('error', 'ID d\'abonnement manquant');
                return;
            }

            // Show loading state
            const updateBtn = $(this.config.selectors.updatePaymentBtn);
            this.setLoadingState(updateBtn, true);

            // Send AJAX request to get portal URL
            $.post(cobra_vars.ajax_url, {
                action: self.config.endpoints.updatePayment,
                subscription_id: subscriptionId,
                nonce: cobra_vars.nonce
            })
            .done(function(response) {
                if (response.success && response.data.portal_url) {
                    // Redirect to Stripe billing portal
                    window.location.href = response.data.portal_url;
                } else {
                    self.showMessage('error', response.data.message || 'Erreur lors de la redirection');
                    self.setLoadingState(updateBtn, false);
                }
            })
            .fail(function() {
                self.showMessage('error', 'Erreur de connexion. Veuillez rÃ©essayer.');
                self.setLoadingState(updateBtn, false);
            });
        },

        // Close all modals
        closeModals: function() {
            $('.cobra-modal').removeClass('show');
        },

        // Set loading state on buttons
        setLoadingState: function($element, isLoading) {
            if (isLoading) {
                $element.prop('disabled', true).addClass('cobra-loading');
                const spinner = $element.find('.loading-spinner');
                if (spinner.length) {
                    spinner.show();
                }
                const buttonText = $element.find('.button-text');
                if (buttonText.length) {
                    buttonText.hide();
                }
            } else {
                $element.prop('disabled', false).removeClass('cobra-loading');
                const spinner = $element.find('.loading-spinner');
                if (spinner.length) {
                    spinner.hide();
                }
                const buttonText = $element.find('.button-text');
                if (buttonText.length) {
                    buttonText.show();
                }
            }
        },

        // Show message
        showMessage: function(type, message) {
            // Remove existing messages
            $('.cobra-message').remove();

            // Create new message
            const messageHtml = `
                <div class="cobra-message ${type}" style="display: none;">
                    ${message}
                </div>
            `;

            // Insert and show message
            if ($('.cobra-subscription-details').length) {
                $('.cobra-subscription-details').before(messageHtml);
            } else {
                $('body').prepend(messageHtml);
            }

            $('.cobra-message').fadeIn();

            // Auto-remove after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    $('.cobra-message').fadeOut();
                }, 5000);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize if we're on a subscription management page
        if ($('[data-subscription-id]').length || $('.cobra-subscription-details').length) {
            CobraSubscriptions.init();
        }
    });

    // Make available globally
    window.CobraSubscriptions = CobraSubscriptions;

})(jQuery);
