<?php

/**
 * SecurityHeaders — emits the secondary OWASP / W3C security headers
 * that aren't covered by Spatie\Csp\AddCspHeaders.
 *
 * Headers:
 *   Strict-Transport-Security    — HSTS, force HTTPS for 1 year (incl. subdomains, preload-eligible)
 *   Permissions-Policy           — browser feature gating (camera, mic, geolocation, etc.)
 *   Cross-Origin-Opener-Policy   — process-isolate top-level windows
 *   Cross-Origin-Resource-Policy — restrict who can embed our resources
 *   X-Permitted-Cross-Domain-Policies — block legacy Flash/PDF cross-domain
 *
 * Already emitted by nginx vhost (don't duplicate):
 *   X-Frame-Options, X-XSS-Protection, X-Content-Type-Options, Referrer-Policy
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // HSTS — 1 year, includes subdomains, eligible for browser preload list.
        // Only set on HTTPS responses; otherwise it's a no-op per RFC 6797.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Permissions-Policy — disable browser features Heratio doesn't use.
        // Whitelist only what's actually needed (camera/mic for AR/3D capture
        // workflows when those routes ship; for now everything is blocked).
        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            'autoplay=()',
            'camera=()',
            'clipboard-read=(self)',
            'clipboard-write=(self)',
            'encrypted-media=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=(self)',
            'picture-in-picture=()',
            'publickey-credentials-get=(self)',
            'screen-wake-lock=()',
            'sync-xhr=(self)',
            'usb=()',
            'xr-spatial-tracking=()',
        ]));

        // Cross-Origin-Opener-Policy — when a window.opener'd page is on another
        // origin, isolate it so it can't read this window's globals.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Cross-Origin-Resource-Policy — only same-site can embed our resources.
        // 'same-site' is more permissive than 'same-origin' and lets subdomains
        // (e.g. registry.theahg.co.za) embed heratio.theahg.co.za assets.
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        // Block legacy Flash/PDF cross-domain policies entirely.
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        return $response;
    }
}
