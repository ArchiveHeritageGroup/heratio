<?php

/**
 * MetricsController - serve the /metrics endpoint in Prometheus text format.
 *
 * Auth model (or-semantics):
 *   - If the Authorization header is `Bearer <observability.token>` AND
 *     observability.token is non-empty: ALLOW.
 *   - If the request IP appears in observability.allowed_ips: ALLOW.
 *   - Otherwise: 401.
 *
 * The deliberate consequence of an empty token + empty allowed_ips is
 * "deny all". That's the correct fail-closed behaviour for a metrics
 * endpoint exposed under the main web vhost - if the operator hasn't
 * configured access they shouldn't be able to scrape from the public
 * internet by accident.
 *
 * Response Content-Type is the OpenMetrics-compatible text version 0.0.4
 * encoding as produced by Prometheus\RenderTextFormat - this is what the
 * Prometheus server expects when it scrapes a /metrics endpoint.
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

namespace AhgObservability\Http\Controllers;

use AhgObservability\Services\MetricsRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

class MetricsController
{
    public function __construct(private readonly MetricsRegistry $metrics) {}

    public function show(Request $request): Response
    {
        if (! $this->authorised($request)) {
            return response('Unauthorised', 401);
        }

        $renderer = new RenderTextFormat;
        $body = $renderer->render($this->metrics->registry()->getMetricFamilySamples());

        return response($body, 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
            // Cache-Control 'no-store' so an upstream cache layer doesn't
            // serve stale counters to Prometheus.
            'Cache-Control' => 'no-store',
        ]);
    }

    private function authorised(Request $request): bool
    {
        $token = (string) config('observability.token', '');
        $allowedIps = (array) config('observability.allowed_ips', []);

        $bearer = $this->bearer($request);
        if ($token !== '' && hash_equals($token, (string) $bearer)) {
            return true;
        }

        if (! empty($allowedIps) && in_array($request->ip(), $allowedIps, true)) {
            return true;
        }

        return false;
    }

    private function bearer(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (! is_string($header) || $header === '') {
            return null;
        }
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return null;
        }

        return trim($m[1]);
    }
}
