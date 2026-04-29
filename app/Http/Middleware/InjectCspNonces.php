<?php

/**
 * InjectCspNonces — auto-tags inline <script> and <style> elements in HTML
 * responses with the per-request CSP nonce that spatie/laravel-csp emits in
 * the Content-Security-Policy header.
 *
 * Heratio has ~270 view partials that emit bare <script>...</script> blocks
 * inherited from the AtoM port. CSP rule: when a nonce-source is present in
 * script-src, browsers ignore 'unsafe-inline'. Without this middleware, every
 * un-nonced inline script is silently blocked, breaking clipboard, hierarchy,
 * TTS, feedback widgets, IIIF viewer init, and many other features.
 *
 * Touching every view file is the wrong fix; a single response post-processor
 * is the right one. Tags that already carry a nonce are left alone.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectCspNonces
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->bound('csp-nonce')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return $response;
        }

        $nonce = (string) app('csp-nonce');
        if ($nonce === '') {
            return $response;
        }

        // Match opening <script ...> and <style ...> tags. Leave self-closing
        // and closing tags alone. Skip tags that already declare a nonce.
        $pattern = '/<(script|style)\b([^>]*)>/i';
        $injected = preg_replace_callback($pattern, function (array $m) use ($nonce): string {
            if (stripos($m[2], 'nonce=') !== false) {
                return $m[0];
            }
            return '<' . $m[1] . $m[2] . ' nonce="' . $nonce . '">';
        }, $content);

        if (is_string($injected) && $injected !== $content) {
            $response->setContent($injected);
        }

        return $response;
    }
}
