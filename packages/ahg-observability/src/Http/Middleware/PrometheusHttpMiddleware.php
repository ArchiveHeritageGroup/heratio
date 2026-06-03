<?php

/**
 * PrometheusHttpMiddleware - request-level instrumentation.
 *
 * Records two metrics per HTTP request, deferred to terminate() so the
 * response is already flushed to the client when we touch the registry:
 *
 *   heratio_http_requests_total{method, route, status}
 *   heratio_http_request_duration_seconds_bucket{method, route, status, le}
 *
 * "route" is the named Laravel route (e.g. "io.show") when one is set,
 * otherwise the literal string "unnamed". This is deliberate - using the
 * raw URI as the label would blow up cardinality (one series per slug,
 * page-number combo, etc.) and is the #1 thing the upstream prometheus_*
 * docs warn against.
 *
 * Defensive: a registry failure (e.g. Redis briefly down) must NOT bubble
 * out of terminate() - the response has already gone. We swallow + ignore
 * so the user never sees a 500 caused by metrics.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgObservability\Http\Middleware;

use AhgObservability\Services\MetricsRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrometheusHttpMiddleware
{
    public function __construct(private readonly MetricsRegistry $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Record the start time on the request so terminate() can compute
        // the duration. Using a defined instant from middleware entry is
        // more accurate than LARAVEL_START (which fires before bootstrap)
        // for measuring request *processing* time specifically.
        $request->attributes->set('_obs_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            // LARAVEL_START is only defined by public/index.php — NOT in CLI,
            // queue, or test contexts. Referencing it unguarded throws
            // "Undefined constant" (the default arg is eagerly evaluated even
            // when _obs_started_at is set), which the catch below swallowed —
            // silently dropping request metrics outside a web request.
            $fallbackStart = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
            $start = (float) $request->attributes->get('_obs_started_at', $fallbackStart);
            $duration = max(0.0, microtime(true) - $start);

            $method = strtoupper($request->getMethod());
            $route  = $request->route()?->getName() ?? 'unnamed';
            $status = (string) $response->getStatusCode();

            $this->metrics
                ->counter('http_requests_total', 'Total HTTP requests', ['method', 'route', 'status'])
                ->inc([$method, $route, $status]);

            $this->metrics
                ->histogram(
                    'http_request_duration_seconds',
                    'HTTP request duration in seconds',
                    ['method', 'route', 'status'],
                    MetricsRegistry::HTTP_BUCKETS
                )
                ->observe($duration, [$method, $route, $status]);
        } catch (\Throwable $e) {
            // Never let a metrics failure crash a terminate() chain.
        }
    }
}
