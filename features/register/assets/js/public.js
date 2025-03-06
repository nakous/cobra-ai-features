(function($) {
    'use strict';

    // Password strength criteria
    const PASSWORD_CRITERIA = {
        minLength: 8,
        hasUpperCase: /[A-Z]/,
        hasLowerCase: /[a-z]/,
        hasNumbers: /[0-9]/,
        hasSpecialChar: /[^A-Za-z0-9]/
    };

    class RegistrationForm {
        constructor() {
            this.$form = $('#cobra-register-form');
            this.$password = $('#password');
            this.$confirmPassword = $('#confirm_password');
            this.$username = $('#username');
            this.$email = $('#email');
            this.$submitButton = this.$form.find('button[type="submit"]');

            this.initializeHandlers();
        }

        initializeHandlers() {
            // Password strength checker
            this.$password.on('input', () => this.checkPasswordStrength());

            // Password confirmation checker
            this.$confirmPassword.on('input', () => this.checkPasswordMatch());

            // Username availability checker
            this.$username.on('blur', () => this.checkUsernameAvailability());

            // Email availability checker
            this.$email.on('blur', () => this.checkEmailAvailability());

            // Password visibility toggle
            $('.toggle-password').on('click', (e) => this.togglePasswordVisibility(e));

            // Form submission
            this.$form.on('submit', (e) => this.handleSubmit(e));
        }

        checkPasswordStrength() {
            const password = this.$password.val();
            const $strengthBar = $('.strength-bar');
            const $strengthText = $('.strength-text');

            if (!password) {
                this.updateStrengthIndicator($strengthBar, $strengthText, 0, '');
                return;
            }

            let score = 0;
            let feedback = [];

            // Check length
            if (password.length >= PASSWORD_CRITERIA.minLength) {
                score++;
            } else {
                feedback.push(cobraRegister.i18n.passwordTooShort);
            }

            // Check uppercase
            if (PASSWORD_CRITERIA.hasUpperCase.test(password)) score++;
            else feedback.push(cobraRegister.i18n.passwordNeedsUpper);

            // Check lowercase
            if (PASSWORD_CRITERIA.hasLowerCase.test(password)) score++;
            else feedback.push(cobraRegister.i18n.passwordNeedsLower);

            // Check numbers
            if (PASSWORD_CRITERIA.hasNumbers.test(password)) score++;
            else feedback.push(cobraRegister.i18n.passwordNeedsNumber);

            // Check special characters
            if (PASSWORD_CRITERIA.hasSpecialChar.test(password)) score++;
            else feedback.push(cobraRegister.i18n.passwordNeedsSpecial);

            // Calculate percentage
            const strengthPercentage = (score / 5) * 100;
            
            // Get strength text
            let strengthText = '';
            if (score < 2) strengthText = cobraRegister.i18n.veryWeak;
            else if (score < 3) strengthText = cobraRegister.i18n.weak;
            else if (score < 4) strengthText = cobraRegister.i18n.medium;
            else if (score < 5) strengthText = cobraRegister.i18n.strong;
            else strengthText = cobraRegister.i18n.veryStrong;

            // Add feedback if password is weak
            if (score < 3 && feedback.length) {
                strengthText += ': ' + feedback.join(', ');
            }

            this.updateStrengthIndicator($strengthBar, $strengthText, strengthPercentage, strengthText);
        }

        updateStrengthIndicator($bar, $text, percentage, message) {
            // Update strength bar
            $bar.css({
                'width': `${percentage}%`,
                'background-color': this.getStrengthColor(percentage)
            });

            // Update strength text
            $text.text(message)
                 .css('color', this.getStrengthColor(percentage));
        }

        getStrengthColor(percentage) {
            if (percentage <= 20) return '#dc3232'; // Very weak - Red
            if (percentage <= 40) return '#dc3232'; // Weak - Red
            if (percentage <= 60) return '#dba617'; // Medium - Yellow
            if (percentage <= 80) return '#7ad03a'; // Strong - Light green
            return '#00a32a'; // Very strong - Green
        }

        checkPasswordMatch() {
            const password = this.$password.val();
            const confirmPassword = this.$confirmPassword.val();
            const $feedback = this.$confirmPassword.siblings('.password-match-feedback');

            if (!confirmPassword) {
                this.removePasswordMatchFeedback($feedback);
                return;
            }

            if (password === confirmPassword) {
                this.showPasswordMatchSuccess($feedback);
            } else {
                this.showPasswordMatchError($feedback);
            }
        }

        removePasswordMatchFeedback($feedback) {
            $feedback.remove();
        }

        showPasswordMatchSuccess($feedback) {
            this.updatePasswordMatchFeedback($feedback, cobraRegister.i18n.passwordsMatch, 'success');
        }

        showPasswordMatchError($feedback) {
            this.updatePasswordMatchFeedback($feedback, cobraRegister.i18n.passwordsMismatch, 'error');
        }

        updatePasswordMatchFeedback($feedback, message, type) {
            if ($feedback.length) {
                $feedback.text(message).attr('class', `password-match-feedback ${type}`);
            } else {
                this.$confirmPassword.after(
                    `<span class="password-match-feedback ${type}">${message}</span>`
                );
            }
        }

        checkUsernameAvailability() {
            const username = this.$username.val();
            if (!username) return;

            this.checkAvailability('username', username, this.$username);
        }

        checkEmailAvailability() {
            const email = this.$email.val();
            if (!email) return;

            this.checkAvailability('email', email, this.$email);
        }

        checkAvailability(type, value, $field) {
            $.ajax({
                url: cobraRegister.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cobra_check_availability',
                    nonce: cobraRegister.nonce,
                    type: type,
                    value: value
                },
                beforeSend: () => {
                    $field.addClass('checking');
                    this.removeAvailabilityFeedback($field);
                },
                success: (response) => {
                    if (response.success) {
                        this.showAvailabilitySuccess($field);
                    } else {
                        this.showAvailabilityError($field, response.data.message);
                    }
                },
                error: () => {
                    this.showAvailabilityError($field, cobraRegister.i18n.checkFailed);
                },
                complete: () => {
                    $field.removeClass('checking');
                }
            });
        }

        removeAvailabilityFeedback($field) {
            $field.siblings('.availability-feedback').remove();
        }

        showAvailabilitySuccess($field) {
            this.updateAvailabilityFeedback(
                $field,
                cobraRegister.i18n.available,
                'success'
            );
        }

        showAvailabilityError($field, message) {
            this.updateAvailabilityFeedback($field, message, 'error');
        }

        updateAvailabilityFeedback($field, message, type) {
            const $feedback = $field.siblings('.availability-feedback');
            
            if ($feedback.length) {
                $feedback.text(message).attr('class', `availability-feedback ${type}`);
            } else {
                $field.after(`<span class="availability-feedback ${type}">${message}</span>`);
            }
        }

        togglePasswordVisibility(event) {
            const $button = $(event.currentTarget);
            const $input = $button.siblings('input');
            const $icon = $button.find('.dashicons');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                $button.attr('aria-label', cobraRegister.i18n.hidePassword);
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $button.attr('aria-label', cobraRegister.i18n.showPassword);
            }
        }

        handleSubmit(event) {
            if (!this.validateForm()) {
                event.preventDefault();
                return;
            }

            this.$submitButton.prop('disabled', true).addClass('loading');
        }

        validateForm() {
            let isValid = true;

            // Clear previous errors
            $('.cobra-form-error').remove();

            // Check required fields
            this.$form.find('[required]').each((index, element) => {
                const $field = $(element);
                if (!$field.val()) {
                    this.showFieldError($field, cobraRegister.i18n.fieldRequired);
                    isValid = false;
                }
            });

            // Check password strength
            if (this.$password.val() && !this.isPasswordStrengthAcceptable()) {
                this.showFieldError(this.$password, cobraRegister.i18n.passwordTooWeak);
                isValid = false;
            }

            // Check password match
            if (this.$password.val() !== this.$confirmPassword.val()) {
                this.showFieldError(this.$confirmPassword, cobraRegister.i18n.passwordsMismatch);
                isValid = false;
            }

            return isValid;
        }

        isPasswordStrengthAcceptable() {
            const password = this.$password.val();
            let score = 0;

            if (password.length >= PASSWORD_CRITERIA.minLength) score++;
            if (PASSWORD_CRITERIA.hasUpperCase.test(password)) score++;
            if (PASSWORD_CRITERIA.hasLowerCase.test(password)) score++;
            if (PASSWORD_CRITERIA.hasNumbers.test(password)) score++;
            if (PASSWORD_CRITERIA.hasSpecialChar.test(password)) score++;

            return score >= 3; // Require at least medium strength
        }

        showFieldError($field, message) {
            if (!$field.siblings('.cobra-form-error').length) {
                $field.after(`<span class="cobra-form-error">${message}</span>`);
            }
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new RegistrationForm();
    });

})(jQuery);