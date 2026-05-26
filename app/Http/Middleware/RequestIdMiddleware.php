<?php

/**
 * RequestIdMiddleware - generates a UUID per inbound HTTP request and
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
 * Phase 5 of #677: also opens an OpenTelemetry `http.server.request`
 * parent span around the request when tracing is configured. The span
 * carries method / url / route / status / user_id / tenant_id and is
 * activated as the current span so all downstream child spans (DB,
 * outbound HTTP, manual Trace::span() calls) nest correctly under it.
 *
 * The middleware stays synchronous - no terminate() hook - so subsequent
 * middleware (PrometheusHttpMiddleware, SessionTimeout, ...) run inside
 * the active span context.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Http\Middleware;

use AhgObservability\Tracing\Trace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Open the OTel parent span for this request. The Trace helper is
        // a no-op when observability.otel_exporter is "null" so this is
        // free when tracing is disabled.
        //
        // We deliberately don't propagate trace context from inbound
        // `traceparent` headers here - that's the job of the tracer
        // provider's W3C TextMapPropagator, which OTel installs by
        // default. Our parent span will adopt the inbound traceparent
        // automatically if Context::getCurrent() already carries one.
        $span = Trace::start('http.server.request', [
            'http.request.method' => strtoupper($request->getMethod()),
            'url.full'            => $request->fullUrl(),
            'url.path'            => $request->getPathInfo(),
            'url.scheme'          => $request->getScheme(),
            'server.address'      => $request->getHost(),
            'client.address'      => $request->ip() ?? '',
            'request_id'          => $requestId,
        ]);
        $scope = Trace::activate($span);
        $request->attributes->set('_obs_span', $span);
        $request->attributes->set('_obs_scope', $scope);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Trace::recordException($span, $e);
            $scope?->detach();
            Trace::end($span);
            $request->attributes->remove('_obs_span');
            $request->attributes->remove('_obs_scope');
            throw $e;
        }

        // Echo the request_id on the response so client-side tracing can follow it
        $response->headers->set('X-Request-Id', $requestId);

        // Late-bound attributes: route name, status, user, tenant.
        try {
            $routeName = $request->route()?->getName() ?? 'unnamed';
            $span->setAttribute('http.route', $routeName);
            $span->setAttribute('http.response.status_code', $response->getStatusCode());

            if (Auth::check()) {
                $span->setAttribute('user.id', (int) Auth::id());
            }

            // Phase 6 of #676 binds the active tenant under tenant.current.
            // Available only after ResolveTenantMiddleware has run (which
            // sits later in the web stack), so guard the call.
            if (app()->bound('tenant.current')) {
                $tenant = app('tenant.current');
                $tenantId = is_object($tenant) ? ($tenant->id ?? null) : null;
                if ($tenantId !== null) {
                    $span->setAttribute('tenant.id', (int) $tenantId);
                }
            }

            if ($response->getStatusCode() >= 500) {
                Trace::recordException($span, new \RuntimeException(
                    'http.server status '.$response->getStatusCode()
                ));
            } else {
                Trace::ok($span);
            }
        } catch (\Throwable) {
            // never let span teardown bubble
        }

        $scope?->detach();
        Trace::end($span);
        $request->attributes->remove('_obs_span');
        $request->attributes->remove('_obs_scope');

        return $response;
    }
}
