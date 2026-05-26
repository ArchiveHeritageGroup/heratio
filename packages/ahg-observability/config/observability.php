<?php

/**
 * observability.php - Configuration for the Heratio Prometheus exporter.
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

return [

    /*
    |--------------------------------------------------------------------------
    | Bearer token for the /metrics endpoint
    |--------------------------------------------------------------------------
    |
    | When non-empty, the request must present `Authorization: Bearer <token>`
    | to reach the renderer. Combined with `allowed_ips` using OR semantics:
    | a request that satisfies EITHER the token check OR the IP allow-list
    | will be accepted. When both are empty / wide-open the endpoint will
    | refuse all traffic - this is intentional fail-closed behaviour.
    |
    */
    'token' => env('OBSERVABILITY_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | IP allow-list for the /metrics endpoint
    |--------------------------------------------------------------------------
    |
    | List of client IPs (or CIDRs in the future) that may scrape /metrics
    | without presenting a bearer token. Default = loopback only, which is
    | the typical Prometheus-on-same-host posture.
    |
    */
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('OBSERVABILITY_ALLOWED_IPS', '127.0.0.1,::1')
    )))),

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    |
    | "auto"     - choose Redis if cache.default=redis, else APCu if loaded,
    |              else InMemory (process-local, drops on reload).
    | "redis"    - force the Redis adapter (requires phpredis + cache.stores.redis).
    | "apcu"     - force the APCu adapter (requires ext-apcu).
    | "inmemory" - process-local; useful for tests and CLI runs.
    |
    */
    'storage_driver' => env('OBSERVABILITY_STORAGE_DRIVER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Queue connections / queues to sample for queue_depth gauge
    |--------------------------------------------------------------------------
    |
    | Each entry is ['connection' => '<name>', 'queue' => '<queue>'].
    | The connection defaults to config('queue.default') when null/empty.
    | The queue name defaults to 'default'.
    |
    */
    'queues' => [
        ['connection' => null, 'queue' => 'default'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prometheus textfile collector directory
    |--------------------------------------------------------------------------
    |
    | Directory that node_exporter is configured to scan for *.prom files via
    | its `--collector.textfile.directory=<dir>` flag. The
    | `ai-compliance:emit-metrics` command writes
    | `heratio_ai_compliance.prom` here so the synthetic
    | `ai_compliance_verify_status` gauge (1=PASS, 0=FAIL) can drive the
    | InferenceChainBroken alert (see config/alerts/heratio.rules.yml).
    |
    | Default `/var/lib/node_exporter/textfile_collector` matches the
    | upstream Debian package; override via env on hosts that put it
    | elsewhere.
    |
    */
    'textfile_dir' => env('OBSERVABILITY_TEXTFILE_DIR', '/var/lib/node_exporter/textfile_collector'),

    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry tracing (issue #677 Phase 5)
    |--------------------------------------------------------------------------
    |
    | Exporter modes:
    |   "otlp"    - export via OTLP gRPC (default) or HTTP/protobuf to an
    |               OpenTelemetry collector listening at `otel_endpoint`.
    |   "console" - dump spans to stderr (development).
    |   "null"    - no-op exporter (tracing disabled). This is what you get
    |               when there is no collector reachable - the SDK silently
    |               swallows spans and Heratio keeps serving requests.
    |
    | The collector itself is operator-managed. Run one alongside Heratio
    | (see docs/observability/otel-collector.yaml.example) and point it at
    | Jaeger / Tempo / Honeycomb / Datadog / etc.
    |
    | Sample ratio is parent-based: when the inbound traceparent carries
    | a sampling decision we honour it, otherwise we apply this ratio to
    | the root span. 0.0 = drop everything, 1.0 = trace everything.
    |
    */
    'otel_exporter'      => env('OBSERVABILITY_OTEL_EXPORTER', 'null'),
    'otel_endpoint'      => env('OBSERVABILITY_OTEL_ENDPOINT', 'http://localhost:4317'),
    'otel_protocol'      => env('OBSERVABILITY_OTEL_PROTOCOL', 'grpc'), // grpc | http/protobuf | http/json
    'otel_service_name'  => env('OBSERVABILITY_OTEL_SERVICE_NAME', 'heratio'),
    'otel_environment'   => env('OBSERVABILITY_OTEL_ENVIRONMENT', env('APP_ENV', 'production')),
    'otel_sample_ratio'  => (float) env('OBSERVABILITY_OTEL_SAMPLE_RATIO', '1.0'),

    /*
    | DB queries faster than this threshold (in milliseconds) are NOT
    | promoted to child spans - this keeps the span volume reasonable on
    | pages that fire dozens of fast lookups. Set to 0 to record every
    | query (expensive, useful for diagnostics).
    */
    'otel_db_slow_query_ms' => (int) env('OBSERVABILITY_OTEL_DB_SLOW_QUERY_MS', '50'),

    /*
    | When false, outbound `Http::` calls won't emit child spans even if
    | tracing is otherwise enabled. Lets operators dial down volume on
    | hosts that make thousands of small HTTP calls per request.
    */
    'otel_http_client_enabled' => (bool) env('OBSERVABILITY_OTEL_HTTP_CLIENT_ENABLED', true),
];
