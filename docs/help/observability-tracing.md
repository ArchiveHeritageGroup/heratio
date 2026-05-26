# Distributed tracing (OpenTelemetry)

> Issue #677 Phase 5. What traces are, how to read them, and how to
> wire them up.

## Summary (read this first)

- Heratio emits OpenTelemetry traces for every HTTP request when an
  OTel collector is reachable. When no collector is configured the
  feature is silently off.
- Each web request becomes a parent span called `http.server.request`.
  Slow DB queries, outbound HTTP calls, and any manually wrapped block
  of code show up as child spans nested underneath.
- You'll see traces in whichever backend your operator points the
  collector at: Grafana Tempo, Jaeger, Honeycomb, Datadog, etc.
- Traces are a debugging signal, not a compliance signal. They are not
  retained long-term and they DO carry SQL fragments - treat the trace
  backend like an operator-only system.

## What you'll see in the trace UI

A trace for a single archival-record page load looks roughly like:

```
http.server.request  (250 ms)  GET /uk-tnk-001/abc123-record
  -> db.query  (120 ms)  SELECT * FROM information_object ...
  -> db.query  (40 ms)   SELECT * FROM relation WHERE ...
  -> http.client.request  (60 ms)  POST https://ai.theahg.co.za/ai/v1/ner
```

Every span carries a duration and a set of attributes. Click into a
span to see them - things like the request URL, the user_id (if logged
in), the tenant_id (multi-tenant installs), the response status, the
SQL fingerprint, etc.

## What's NOT in traces

- Login passwords / session cookies / API keys (filtered out by Heratio
  before the span is created)
- Bound query parameters in `db.statement` (we truncate to 200 chars
  and emit a SHA-256 of the full statement for fingerprinting - the raw
  parameter values are not in the span)
- Anything outside an HTTP request, unless a developer wrapped that
  code in `Trace::span(...)` explicitly

If you need data redacted further (POPIA / GDPR / IP), the collector
config supports `attributes/redact` processors - see
`docs/observability/otel-collector.yaml.example`.

## Reading a trace

1. Open the trace backend. The URL is operator-specific (Grafana,
   Jaeger UI, Honeycomb, etc.).
2. Search by request_id. Every Heratio response carries an
   `X-Request-Id` header; the same value is on the `http.server.request`
   span. Paste it into your trace backend's filter.
3. Drill from the parent span. Slow leaf spans usually tell you where
   time was spent. A page that's slow because of database I/O will
   light up `db.query` spans; one that's slow because of an AI gateway
   call will light up `http.client.request`.

## Correlating with logs

- The `request_id` attribute on every span matches the `X-Request-Id`
  response header and the `request_id` field on the structured-JSON
  log lines (Phase 2 of #677).
- Open the trace, grab the request_id, paste it into Loki / Grafana
  Explore - you get the matching log lines.

## Turning it on (operator action)

1. Run an OpenTelemetry collector reachable from the Heratio host.
   The example config at `docs/observability/otel-collector.yaml.example`
   takes OTLP on 4317/4318 and forwards to your trace backend.
2. Set in Heratio's `.env`:

   ```
   OBSERVABILITY_OTEL_EXPORTER=otlp
   OBSERVABILITY_OTEL_ENDPOINT=http://127.0.0.1:4317
   OBSERVABILITY_OTEL_PROTOCOL=grpc
   ```

3. Optional: cut the sample ratio on high-traffic boxes:

   ```
   OBSERVABILITY_OTEL_SAMPLE_RATIO=0.1
   ```

4. Run `php artisan config:clear` and the next request will start
   producing spans.

## Turning it off

```
OBSERVABILITY_OTEL_EXPORTER=null
```

That's it. The SDK becomes a no-op, no spans are emitted, and the
runtime overhead is essentially zero (a single class_exists check per
HTTP request).
