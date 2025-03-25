/**
 * SMTP Admin Scripts
 */
jQuery(document).ready(function($) {
    'use strict';

    // Toggle SMTP settings
    function toggleSmtpSettings() {
        var enabled = $('#smtp-enabled').is(':checked');
        $('.smtp-setting').toggle(enabled);
        toggleSmtpAuthSettings();
    }

    // Toggle SMTP authentication settings
    function toggleSmtpAuthSettings() {
        var enabled = $('#smtp-enabled').is(':checked') && $('#smtp-auth').is(':checked');
        $('.smtp-auth-setting').toggle(enabled);
    }

    // Toggle POP settings
    function togglePopSettings() {
        var enabled = $('#pop-enabled').is(':checked');
        $('.pop-setting').toggle(enabled);
    }

    // Initialize toggles
    toggleSmtpSettings();
    togglePopSettings();

    // Bind change events
    $('#smtp-enabled').on('change', toggleSmtpSettings);
    $('#smtp-auth').on('change', toggleSmtpAuthSettings);
    $('#pop-enabled').on('change', togglePopSettings);

    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Get target tab
        var tabId = $(this).data('tab');
        
        // Update URL without refreshing
        var currentUrl = window.location.href;
        var newUrl;
        
        if (currentUrl.indexOf('tab=') > -1) {
            // Replace existing tab parameter
            newUrl = currentUrl.replace(/tab=[^&]*/, 'tab=' + tabId);
        } else if (currentUrl.indexOf('?') > -1) {
            // Add tab parameter to existing query string
            newUrl = currentUrl + '&tab=' + tabId;
        } else {
            // Add tab parameter as new query string
            newUrl = currentUrl + '?tab=' + tabId;
        }
        
        if (history.pushState) {
            window.history.pushState({path: newUrl}, '', newUrl);
            // window.location.href = newUrl;
        }
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Save current tab in input field
        $('input[name="tab"]').val(tabId);
        
        // Show/hide tab content
        $('.settings-tab').hide();
        $('#' + tabId).show();
    });

    // Show the current tab on page load
    var currentTab = getUrlParameter('tab');
    if (currentTab) {
        $('.nav-tab[data-tab="' + currentTab + '"]').click();
    } else {
        // Default to first tab if none specified
        $('.nav-tab:first').click();
    }

    // Helper function to get URL parameters
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Test email functionality
    $('#send-test-email').on('click', function() {
        var $button = $(this);
        var $spinner = $('#test-email-spinner');
        var $result = $('#test-email-result');

        // Validate form
        var recipient = $('#test-email-recipient').val();
        var subject = $('#test-email-subject').val();
        var message = $('#test-email-message').val();

        if (!recipient || !subject || !message) {
            $result.removeClass('hidden notice-success')
                   .addClass('notice-error')
                   .html('<p>Please fill in all fields.</p>')
                   .show();
            return;
        }

        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.addClass('hidden');

        // Send test email
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cobra_ai_test_smtp_email',
                nonce: $('#email-test-nonce').val(),
                recipient: recipient,
                subject: subject,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('hidden notice-error')
                           .addClass('notice-success')
                           .html('<p>' + response.data.message + '</p>');
                } else {
                    $result.removeClass('hidden notice-success')
                           .addClass('notice-error')
                           .html('<p>' + response.data.message + '</p>');
                }
            },
            error: function() {
                $result.removeClass('hidden notice-success')
                       .addClass('notice-error')
                       .html('<p>An error occurred while sending the test email.</p>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});