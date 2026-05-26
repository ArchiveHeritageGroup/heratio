<?php

/**
 * TraceHttpClient - emit child spans for outbound Laravel Http:: calls.
 *
 * Phase 5 of issue #677.
 *
 * Subscribes to:
 *   - Illuminate\Http\Client\Events\RequestSending  (opens span)
 *   - Illuminate\Http\Client\Events\ResponseReceived (closes span, sets status)
 *   - Illuminate\Http\Client\Events\ConnectionFailed (closes span, marks error)
 *
 * Span name: `http.client.request`. Attributes follow OTel semconv:
 *   - http.request.method   = GET / POST / ...
 *   - url.full              = full URL (sensitive query params are NOT
 *                             stripped - the OTel collector / processor
 *                             pipeline is responsible for redaction)
 *   - server.address        = host portion of URL
 *   - http.response.status_code = response status (on success)
 *
 * Because the HTTP client events fire on separate dispatcher ticks for
 * the same call, we key in-flight spans by spl_object_id of the request
 * to pair the open + close back together. Worst case (event lost / out
 * of order) the span stays open and gets garbage-collected when the
 * tracer's batch processor flushes - harmless.
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

namespace AhgObservability\Tracing\Listeners;

use AhgObservability\Tracing\Trace;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use OpenTelemetry\API\Trace\SpanInterface;

class TraceHttpClient
{
    /** @var array<int, SpanInterface> */
    protected array $inflight = [];

    public function handleSending(RequestSending $event): void
    {
        try {
            $req = $event->request;
            $url = (string) $req->url();
            $method = strtoupper((string) $req->method());

            $attrs = [
                'http.request.method' => $method,
                'url.full'            => $url,
            ];

            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $attrs['server.address'] = $host;
            }

            $span = Trace::start('http.client.request', $attrs);
            $this->inflight[spl_object_id($req)] = $span;
        } catch (\Throwable) {
            // tracing failure must not crash the outbound HTTP call
        }
    }

    public function handleReceived(ResponseReceived $event): void
    {
        try {
            $key = spl_object_id($event->request);
            $span = $this->inflight[$key] ?? null;
            if ($span === null) {
                return;
            }
            unset($this->inflight[$key]);

            $status = (int) $event->response->status();
            $span->setAttribute('http.response.status_code', $status);

            if ($status >= 500) {
                Trace::recordException($span, new \RuntimeException('http.client status '.$status));
            } else {
                Trace::ok($span);
            }

            Trace::end($span);
        } catch (\Throwable) {
            // ignore
        }
    }

    public function handleFailed(ConnectionFailed $event): void
    {
        try {
            $key = spl_object_id($event->request);
            $span = $this->inflight[$key] ?? null;
            if ($span === null) {
                return;
            }
            unset($this->inflight[$key]);

            Trace::recordException($span, new \RuntimeException('http.client connection failed'));
            Trace::end($span);
        } catch (\Throwable) {
            // ignore
        }
    }
}
