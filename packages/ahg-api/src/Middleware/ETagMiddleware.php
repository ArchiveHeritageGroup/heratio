<?php

/**
 * ETagMiddleware
 *
 * Adds an ETag header to GET responses (sha256 of the body, hex-truncated).
 * Honours conditional requests via If-None-Match (returns 304 Not Modified).
 *
 * Bypass: set request attribute "etag.bypass" = true in the controller.
 *
 * Issue #652 Phase 1.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETagMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->method() !== 'GET') {
            return $response;
        }

        if ($request->attributes->get('etag.bypass') === true) {
            return $response;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $body = (string) $response->getContent();
        if ($body === '') {
            return $response;
        }

        $etag = '"'.substr(hash('sha256', $body), 0, 32).'"';
        $response->headers->set('ETag', $etag);

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch && $this->etagMatches($ifNoneMatch, $etag)) {
            // 304 Not Modified: empty body, keep the ETag.
            $response->setStatusCode(304);
            $response->setContent('');
            $response->headers->remove('Content-Length');
        }

        return $response;
    }

    /**
     * If-None-Match may carry a list of comma-separated ETags or '*'.
     */
    protected function etagMatches(string $header, string $etag): bool
    {
        $header = trim($header);
        if ($header === '*') {
            return true;
        }

        $candidates = array_map('trim', explode(',', $header));
        foreach ($candidates as $candidate) {
            // Tolerate weak ETags (W/"...")
            $stripped = preg_replace('/^W\//', '', $candidate);
            if ($stripped === $etag) {
                return true;
            }
        }

        return false;
    }
}
