<?php

/**
 * Global csp_nonce() helper — returns the per-request nonce string emitted
 * by spatie/laravel-csp, or '' if CSP is disabled / nonce not bound.
 *
 * The package binds `csp-nonce` as a singleton in the container, so every
 * call within the same request returns the SAME value. This is critical:
 * the nonce in <script nonce="X"> must match the nonce in the response
 * Content-Security-Policy header, otherwise the script is blocked.
 *
 * Heratio retains a substantial number of AtoM-port views that already
 * call csp_nonce() — this helper lets them Just Work without each view
 * checking function_exists().
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

if (! function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        try {
            return app()->bound('csp-nonce') ? (string) app('csp-nonce') : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
