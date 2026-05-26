# Observability Phase 5 - OpenTelemetry distributed tracing

> Issue #677 Phase 5. Ships in v2.0.0.

## Summary

Heratio now emits OpenTelemetry traces. Every HTTP request opens a
parent `http.server.request` span; slow DB queries, outbound
`Http::` calls, and any `Trace::span(...)` call from app code nest
as child spans underneath. Spans are exported via OTLP (gRPC or HTTP)
to an operator-managed OpenTelemetry collector, which can fan-out to
Jaeger, Grafana Tempo, Honeycomb, Datadog, or any OTLP backend.

When no collector is configured Heratio runs with `otel_exporter=null`
and the SDK is a no-op - zero runtime cost, no spans emitted, no errors.

## Configuration

`config/observability.php`, all overridable from `.env`:

| Key | Env var | Default | Purpose |
|---|---|---|---|
| `otel_exporter` | `OBSERVABILITY_OTEL_EXPORTER` | `null` | `otlp` / `console` / `null` |
| `otel_endpoint` | `OBSERVABILITY_OTEL_ENDPOINT` | `http://localhost:4317` | Collector address |
| `otel_protocol` | `OBSERVABILITY_OTEL_PROTOCOL` | `grpc` | `grpc` / `http/protobuf` / `http/json` |
| `otel_service_name` | `OBSERVABILITY_OTEL_SERVICE_NAME` | `heratio` | `service.name` resource attribute |
| `otel_environment` | `OBSERVABILITY_OTEL_ENVIRONMENT` | `APP_ENV` | `deployment.environment` |
| `otel_sample_ratio` | `OBSERVABILITY_OTEL_SAMPLE_RATIO` | `1.0` | Root-span sampling ratio (parent-based) |
| `otel_db_slow_query_ms` | `OBSERVABILITY_OTEL_DB_SLOW_QUERY_MS` | `50` | DB queries faster than this don't get spans |
| `otel_http_client_enabled` | `OBSERVABILITY_OTEL_HTTP_CLIENT_ENABLED` | `true` | Toggle outbound HTTP span emission |

Resource attributes auto-populated from Heratio runtime:

- `service.name` - from config (default `heratio`)
- `service.version` - from `version.json`
- `service.instance.id` - from `gethostname()`
- `deployment.environment` - from config / `APP_ENV`

## Span catalogue

| Span | Where it opens | Attributes |
|---|---|---|
| `http.server.request` | `RequestIdMiddleware` (web requests) | method, url.full, url.path, url.scheme, server.address, client.address, request_id, http.route, http.response.status_code, user.id, tenant.id |
| `db.query` | `TraceDbQuery` listener (when `query.time >= otel_db_slow_query_ms`) | db.system=mysql, db.name, db.connection, db.statement (200 char truncate), db.statement.sha256, db.duration_ms |
| `http.client.request` | `TraceHttpClient` listener (Http:: facade) | http.request.method, url.full, server.address, http.response.status_code |
| `<custom>` | App code via `Trace::span()` / `Trace::start()` | Whatever the caller passes |

## Manual tracing from app code

```php
use AhgObservability\Tracing\Trace;

// Wrapped form - exceptions propagate, return value passes through.
$result = Trace::span('htr.run', fn () => $htr->process($pageId), [
    'page_id'      => $pageId,
    'model.name'   => 'kraken-en',
]);

// Imperative form for spans whose lifetime crosses function boundaries.
$span = Trace::start('export.zip.build', ['record_count' => $n]);
try {
    doWork();
} finally {
    Trace::end($span);
}

// Late-binding an attribute on whatever span is currently active.
Trace::setCurrentAttributes(['archive.fonds' => $fonds->slug]);
```

Spans created here automatically nest under the active
`http.server.request` parent (when called from a web request) or the
active job/console span (when called from queue / artisan code).

## Trace ID <-> request_id correlation

The existing `X-Request-Id` header (Phase 2) and the OTel trace_id are
emitted independently. `X-Request-Id` stays UUID-shaped for log
correlation (the Loki labels in Phase 4 key on it); the OTel trace_id
is a separate W3C trace-context value carried in the `traceparent`
header. Both end up on the `http.server.request` span - filter on
`request_id` in your trace UI to jump from logs to traces.

## Sampling

Parent-based, ratio-based. Inbound `traceparent` decisions win - if an
upstream service flagged a request as sampled, Heratio honours it. For
root spans (no inbound trace) the configured ratio applies:

- `1.0` - every request traced (the default; safe on low-traffic boxes)
- `0.1` - 10% sample, suitable for production with heavy traffic
- `0.0` - drop everything (equivalent to `otel_exporter=null`)

## Collector

The OTel collector is operator-managed. Without a reachable collector
the SDK silently swallows spans (the exporter retry budget exhausts,
then BatchSpanProcessor drops). Heratio keeps serving requests
regardless.

See `docs/observability/otel-collector.yaml.example` for a minimal
collector config that receives OTLP and ships to Tempo / Jaeger /
Honeycomb / Datadog.

## Failure modes

- **No collector** - spans are buffered and dropped after the batch
  processor's retry budget. App is unaffected.
- **Misconfigured exporter** (bad URL, wrong protocol) - `TracerProvider::build()`
  catches the exception during construction and falls back to a
  `NoopTracerProvider`. The app gets a no-op tracer; spans are not
  emitted but nothing crashes.
- **OTel SDK not installed** (composer didn't run, or the package was
  removed) - `class_exists()` guards return a Noop fallback. App still
  boots.
- **Span listener exception** - every listener catches `\Throwable` and
  swallows. DB queries and HTTP calls run regardless.

## Files

- `packages/ahg-observability/src/Tracing/TracerProvider.php` - SDK boot factory
- `packages/ahg-observability/src/Tracing/Trace.php` - static helper
- `packages/ahg-observability/src/Tracing/Listeners/TraceDbQuery.php` - DB child spans
- `packages/ahg-observability/src/Tracing/Listeners/TraceHttpClient.php` - outbound HTTP spans
- `app/Http/Middleware/RequestIdMiddleware.php` - opens the parent span
- `docs/observability/otel-collector.yaml.example` - operator collector config
- `docs/help/observability-tracing.md` - end-user `/help` article

## Tests

`packages/ahg-observability/tests/Feature/TracingTest.php`:

- Null-mode build returns `NoopTracerProvider`
- `Trace::span()` returns the wrapped callable's value
- `Trace::span()` propagates exceptions
- `Trace::span()` is a no-op when no tracer is bound (defensive path)
