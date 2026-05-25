# ahg/observability

Prometheus metrics exporter for Heratio (issue #677 Phase 3).

## What it exposes

Endpoint: `GET /metrics` (Prometheus text format, version 0.0.4).

Default metrics:

| Metric | Type | Labels | Description |
|---|---|---|---|
| `heratio_http_requests_total` | counter | method, route, status | One increment per HTTP response |
| `heratio_http_request_duration_seconds` | histogram | method, route, status | Request latency, buckets 0.05/0.1/0.25/0.5/1/2.5/5/10 |
| `heratio_db_queries_total` | counter | connection | One increment per executed DB query |
| `heratio_db_query_duration_seconds` | histogram | connection | Query latency, buckets 0.001/0.005/0.01/0.05/0.1/0.5/1 |
| `heratio_queue_depth` | gauge | connection, queue | Backlog size, sampled every minute |

The `route` label is the named Laravel route (`io.show`, `glam.browse`, etc.) or the literal string `unnamed` when no name is set. This keeps cardinality bounded; we deliberately do NOT use the raw URI.

## Configuration

The package merges its config from `config/observability.php`:

| Key | Env | Default | Purpose |
|---|---|---|---|
| `token` | `OBSERVABILITY_TOKEN` | `""` | Bearer token required to scrape. Empty = deny |
| `allowed_ips` | `OBSERVABILITY_ALLOWED_IPS` | `127.0.0.1,::1` | Comma-separated IPs that bypass token check |
| `storage_driver` | `OBSERVABILITY_STORAGE_DRIVER` | `auto` | `redis` / `apcu` / `inmemory` / `auto` |
| `queues` | (file only) | `[{connection: null, queue: default}]` | Pairs to sample for queue depth |

Auth is **or-semantics**: a request with a valid token OR coming from an allowed IP is accepted. With both empty the endpoint denies everything (fail-closed).

## Scrape config example

```yaml
# prometheus.yml
scrape_configs:
  - job_name: heratio
    metrics_path: /metrics
    scheme: https
    bearer_token: ${OBSERVABILITY_TOKEN}
    static_configs:
      - targets: ['heratio.example.org']
    scrape_interval: 30s
```

If Prometheus runs on the same host as Heratio, drop the token and just lean on the loopback default in `allowed_ips`.

## Storage drivers

`auto` picks:

1. Redis when `cache.default = redis` (multi-process safe; the right choice for php-fpm + worker queues sharing counters)
2. APCu when the extension is loaded (single-host fallback; resets on php-fpm reload)
3. InMemory otherwise (process-local; mostly useful for tests/CLI)

## How counters get pushed

- `PrometheusHttpMiddleware::terminate()` (web group) records request count + duration after the response is flushed
- `RecordDbQuery` (subscribed in the service provider) records DB query count + duration via `QueryExecuted`
- `observability:record-queue-depth` is scheduled every minute and sets the queue gauge

## Running the queue-depth sampler manually

```bash
php artisan observability:record-queue-depth
```

## Tests

```bash
php artisan test packages/ahg-observability/tests
```

The feature test forces the InMemory adapter so it doesn't need Redis or APCu in CI.

## License

GNU AGPL v3.0 or later. See repo root for the full text.
