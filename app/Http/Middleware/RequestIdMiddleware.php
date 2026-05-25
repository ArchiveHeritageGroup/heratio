<?php

/**
 * RequestIdMiddleware — generates a UUID per inbound HTTP request and
 * binds it to the container as `request.id`. The
 * `App\Logging\RequestContextProcessor` reads from this binding so every
 * log line on the same request shares the same request_id.
 *
 * Also emits the request_id as an `X-Request-Id` response header so
 * downstream services + log aggregators + browser DevTools can
 * correlate a client-side trace to backend logs.
 *
 * Honours an inbound `X-Request-Id` header when present (lets a reverse
 * proxy or upstream service preserve the trace ID).
 *
 * Phase 2 of #677.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Honour upstream X-Request-Id when sane (UUID-like); else mint a new one.
        $inbound = (string) $request->headers->get('X-Request-Id', '');
        $requestId = (preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $inbound)) ? $inbound : (string) Str::uuid();

        // Bind to container so the Monolog processor + downstream code can read it
        app()->instance('request.id', $requestId);
        $request->attributes->set('request.id', $requestId);

        $response = $next($request);
        // Echo the request_id on the response so client-side tracing can follow it
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
