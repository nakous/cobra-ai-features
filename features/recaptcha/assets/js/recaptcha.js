(function($) {
    'use strict';

    // reCAPTCHA instances
    var recaptchas = [];

    // Initialize reCAPTCHA on all forms
    function initRecaptcha() {
        $('.cobra-recaptcha').each(function() {
            var $container = $(this);
            var formType = $container.data('form');

            if (cobraRecaptcha.version === 'v2') {
                var widgetId = grecaptcha.render($container[0], {
                    'sitekey': cobraRecaptcha.siteKey,
                    'theme': cobraRecaptcha.theme,
                    'size': cobraRecaptcha.size,
                    'callback': function(response) {
                        onRecaptchaSuccess($container, response);
                    },
                    'expired-callback': function() {
                        onRecaptchaExpired($container);
                    }
                });

                recaptchas.push({
                    widget: widgetId,
                    container: $container,
                    form: $container.closest('form')
                });
            }
        });

        // Disable submit buttons if configured
        if (cobraRecaptcha.disableSubmit) {
            disableSubmitButtons();
        }
    }

    // Handle successful verification
    function onRecaptchaSuccess($container, response) {
        var $form = $container.closest('form');
        
        if (cobraRecaptcha.disableSubmit) {
            $form.find('input[type="submit"], button[type="submit"]').prop('disabled', false);
        }
    }

    // Handle expired response
    function onRecaptchaExpired($container) {
        var $form = $container.closest('form');
        
        if (cobraRecaptcha.disableSubmit) {
            $form.find('input[type="submit"], button[type="submit"]').prop('disabled', true);
        }
    }

    // Disable submit buttons
    function disableSubmitButtons() {
        recaptchas.forEach(function(recaptcha) {
            recaptcha.form.find('input[type="submit"], button[type="submit"]').prop('disabled', true);
        });
    }

    // Initialize when reCAPTCHA API is loaded
    window.onRecaptchaLoaded = function() {
        initRecaptcha();
    };

    // Add callback to reCAPTCHA script
    $(function() {
        if (typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(function() {
                initRecaptcha();
            });
        }
    });

})(jQuery);