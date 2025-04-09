/**
 * Google Authentication JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initGoogleAuth();
    });

    /**
     * Initialize Google Authentication
     */
    function initGoogleAuth() {
        // Check if Google button exists
        if (!$('#cobra-google-login-button').length) {
            return;
        }

        // Handle login button click
        $('#cobra-google-login-button').on('click', function() {
            // Check if we have client ID
            if (!cobraGoogleLogin.client_id) {
                console.error('Google Client ID not configured');
                return;
            }

            // Create URL with nonce for security
            var authUrl = window.location.origin + window.location.pathname + '?cobra_google_auth=1&_wpnonce=' + cobraGoogleLogin.nonce;
            
            // Add redirect parameter if on login page
            var redirectTo = getParameterByName('redirect_to');
            if (redirectTo) {
                authUrl += '&redirect_to=' + encodeURIComponent(redirectTo);
            }
            
            // Redirect to authorization URL
            window.location.href = authUrl;
        });
    }

    /**
     * Helper function to get URL parameters
     */
    function getParameterByName(name, url) {
        if (!url) url = window.location.href;
        name = name.replace(/[\[\]]/g, '\\$&');
        var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, ' '));
    }

})(jQuery);