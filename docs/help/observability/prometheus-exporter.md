# Prometheus metrics exporter

Heratio exposes a Prometheus-compatible `/metrics` endpoint so operators can scrape request rates, latencies, database query timings, and queue depth into their existing monitoring stack. This is Phase 3 of GitHub issue #677.

## Summary (read this first)

- The endpoint is `GET /metrics`, served in standard Prometheus text format.
- It is **gated by a bearer token, an IP allow-list, or both** - unconfigured deployments deny every request.
- Five metric families are exposed: HTTP requests + duration, DB queries + duration, and queue depth.
- Default storage is `auto`: Redis if your cache uses Redis, else APCu, else in-memory.

If you just want it running, set `OBSERVABILITY_TOKEN` in `.env`, point Prometheus at the URL, and you're done.

## Quick start

1. Generate a token: `openssl rand -hex 32`
2. Add to `.env`:

   ```
   OBSERVABILITY_TOKEN=your-long-random-token
   ```

3. Restart php-fpm so the new env loads.
4. Confirm with `curl -H "Authorization: Bearer $OBSERVABILITY_TOKEN" https://your-host/metrics`. You should see lines starting with `# HELP heratio_http_requests_total`.

## Authentication model

Two checks, **OR**:

1. `Authorization: Bearer <OBSERVABILITY_TOKEN>` matches the configured token, **OR**
2. The client IP is in `OBSERVABILITY_ALLOWED_IPS` (defaults to `127.0.0.1,::1`).

If neither check passes, the endpoint returns **401 Unauthorised**. With both empty + no loopback access, the endpoint is unreachable - that is the intended fail-closed posture.

Use the bearer token when Prometheus is on a different host. Use the IP allow-list when it's on the same box.

## What's collected

### HTTP requests

- `heratio_http_requests_total{method, route, status}` - count of requests by named route.
- `heratio_http_request_duration_seconds{method, route, status}` - latency histogram with buckets 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10 seconds.

The `route` label is the **named Laravel route**, not the URL. Unnamed routes are reported as `route="unnamed"`. This is deliberate cardinality control - using raw URLs would create one timeseries per slug.

### Database queries

- `heratio_db_queries_total{connection}` - count of executed queries per DB connection.
- `heratio_db_query_duration_seconds{connection}` - histogram with buckets 0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1 seconds.

We do **not** label by SQL text or table - that would create unbounded cardinality. For per-query investigation use the slow query log / tracing (Phase 4).

### Queue depth

- `heratio_queue_depth{connection, queue}` - the number of pending jobs as a gauge.

Sampled every minute by the scheduled command `observability:record-queue-depth`. Configure which queues to sample in `config/observability.php` under `queues` (default: just `default`).

## Configuration reference

| Env var | Default | Meaning |
|---|---|---|
| `OBSERVABILITY_TOKEN` | `""` | Bearer token. Empty = no token check |
| `OBSERVABILITY_ALLOWED_IPS` | `127.0.0.1,::1` | Comma-separated IPs that bypass the token |
| `OBSERVABILITY_STORAGE_DRIVER` | `auto` | `auto` / `redis` / `apcu` / `inmemory` |

Publish the config file to tune queues or buckets:

```
php artisan vendor:publish --tag=observability-config
```

## Storage drivers

- **Redis** - multi-process safe; correct for production with php-fpm + workers.
- **APCu** - single-host fallback; counters reset on php-fpm reload.
- **InMemory** - process-local; for tests and CLI debugging only.

`auto` picks Redis when `cache.default = redis`, else APCu if loaded, else InMemory.

## Common dashboards

- Request rate: `sum by (route) (rate(heratio_http_requests_total[1m]))`
- P95 latency: `histogram_quantile(0.95, sum by (route, le) (rate(heratio_http_request_duration_seconds_bucket[5m])))`
- 5xx rate: filter on `status=~"5.."`
- Queries per request: `sum(rate(heratio_db_queries_total[1m])) / sum(rate(heratio_http_requests_total[1m]))`
- Queue backlog: `heratio_queue_depth` line per queue

## Troubleshooting

### 401 from every scrape

Either the token is wrong (`hash_equals` is case-sensitive and exact) or the Prometheus host is not in `OBSERVABILITY_ALLOWED_IPS`. Confirm with a `curl` from the Prometheus box.

### `route="unnamed"` swamps the chart

That just means the route in question has no `->name('...')` call in its definition. Add names to your busy routes for cleaner labels.

### Counters reset every reload

You're on the APCu driver. Switch to Redis (`OBSERVABILITY_STORAGE_DRIVER=redis`) for persistence across php-fpm reloads.

### Queue depth gauge is flat / not appearing

Check the scheduler is running (`* * * * *  cd /usr/share/nginx/heratio && php artisan schedule:run`). Without the scheduler the gauge never gets sampled.

## Related

- Operator runbook: `docs/observability-prometheus.md`
- Phase 2 (request_id) of #677: `RequestIdMiddleware`
- Phase 4 (tracing): TODO

Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/677
