/**
 * jQuery CSRF Token Setup
 *
 * Configures jQuery to automatically include the Laravel CSRF token
 * with all AJAX requests. This is needed because the AtoM theme bundle JS
 * makes jQuery AJAX calls (clipboard, exports, etc.) without CSRF tokens,
 * causing Laravel to return 419 (Token Mismatch) errors.
 */
(function() {
    'use strict';

    if (typeof jQuery === 'undefined') {
        return;
    }

    var token = document.querySelector('meta[name="csrf-token"]');

    if (token) {
        jQuery.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': token.getAttribute('content')
            }
        });
    } else {
        console.warn('jQuery CSRF setup: meta[name="csrf-token"] not found.');
    }
})();
