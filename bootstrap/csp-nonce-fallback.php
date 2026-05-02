<?php

/**
 * csp_nonce() global fallback for Heratio-Laravel forks where the
 * spatie/laravel-csp package is not installed (e.g. legacy atom v1.0.5).
 *
 * Loaded from bootstrap/app.php before request handling. When
 * spatie/laravel-csp IS installed, its helper has already been declared
 * via vendor/autoload.php and our function_exists guard is a no-op.
 *
 * Returns the per-request nonce when the container has it bound (i.e.
 * the spatie middleware ran and did `app()->instance('csp-nonce', ...)`),
 * otherwise an empty string. Empty nonces are harmless — CSP just won't
 * tag the script/style — and the InjectCspNonces middleware also bails
 * cleanly when the binding isn't there.
 */
if (! function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        try {
            $app = function_exists('app') ? app() : null;
            if ($app && $app->bound('csp-nonce')) {
                return (string) $app->make('csp-nonce');
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return '';
    }
}
