// assets/js/admin.js
jQuery(document).ready(function($) {
    // Plan currency symbol updater
    $('#plan_currency').on('change', function() {
        $('.currency-symbol').text($(this).val());
    });

    // Form validation
    $('#plan-form').on('submit', function(e) {
        e.preventDefault();
        validatePlanForm();
    });

    function validatePlanForm() {
        let isValid = true;
        const requiredFields = ['plan_name', 'plan_amount'];
        
        requiredFields.forEach(field => {
            const $field = $(`#${field}`);
            if (!$field.val()) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });

        return isValid;
    }

    // Modal helpers
    window.showPlanModal = function() {
        $('#plan-modal').fadeIn();
    }

    window.hidePlanModal = function() {
        $('#plan-modal').fadeOut();
        $('#plan-form')[0].reset();
        $('.error').removeClass('error');
    }

    $(window).click(function(e) {
        if ($(e.target).hasClass('cobra-modal')) {
            hidePlanModal();
        }
    });

    // Add custom error handling
    $(document).ajaxError(function(event, jqxhr, settings, error) {
        console.error('Ajax error:', error);
        alert('Error processing request. Please try again.');
    });

    
});