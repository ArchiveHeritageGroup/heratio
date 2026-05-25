# Observability - Prometheus exporter (issue #677 Phase 3)

Heratio ships a Prometheus-compatible `/metrics` endpoint via the `ahg/observability` package. This document is the operator runbook: how to enable scraping, what the metrics mean, and what to build dashboards from.

## TL;DR

1. Set `OBSERVABILITY_TOKEN=<long-random-string>` in `.env` (or whitelist the Prometheus host IP in `OBSERVABILITY_ALLOWED_IPS`).
2. Point Prometheus at `https://your-heratio-host/metrics` with the bearer token.
3. Default scrape interval: 30s is fine. 15s if you want sub-minute resolution.

## Endpoint

```
GET /metrics
Authorization: Bearer <OBSERVABILITY_TOKEN>
```

Response: `Content-Type: text/plain; version=0.0.4` - the standard Prometheus exposition format.

Without a valid bearer token AND with no allowed-IP match, the endpoint returns **401**. This is fail-closed by design - an unconfigured deployment will not silently leak metrics over the internet.

## Configuration

| Env | Default | Meaning |
|---|---|---|
| `OBSERVABILITY_TOKEN` | `""` | Bearer token. Empty = no token check. Generate with `openssl rand -hex 32` |
| `OBSERVABILITY_ALLOWED_IPS` | `127.0.0.1,::1` | Comma-separated client IPs that bypass the token check |
| `OBSERVABILITY_STORAGE_DRIVER` | `auto` | `auto` / `redis` / `apcu` / `inmemory` |

If you want to override `queues` (which queue pairs to sample for depth), publish the config:

```bash
php artisan vendor:publish --tag=observability-config
# edit config/observability.php → 'queues'
```

### Storage driver choice

| Driver | When it's right | Caveats |
|---|---|---|
| Redis | php-fpm + queue workers + multiple PHP processes sharing counters | Needs phpredis ext and `cache.default = redis`. The right default for production. |
| APCu | Single-host, single-PHP-pool installs | Counters reset on php-fpm reload. Each php-fpm worker has its own APCu segment - some samples may not aggregate. |
| InMemory | Tests, CLI debugging | Counters live only inside the current PHP process. NOT useful in production. |
| `auto` | Default | Picks Redis when cache uses Redis, else APCu when loaded, else InMemory |

## Metrics catalog

### `heratio_http_requests_total` (counter)

Labels: `method`, `route`, `status`.

`route` is the named Laravel route (e.g. `io.show`, `glam.browse`). Unnamed routes are labelled `unnamed` to bound cardinality. **Do not** assume one timeseries per URL.

Typical PromQL:

```promql
# Request rate per route per second over the last minute
sum by (route) (rate(heratio_http_requests_total[1m]))

# 5xx error rate
sum by (route) (rate(heratio_http_requests_total{status=~"5.."}[5m]))
```

### `heratio_http_request_duration_seconds` (histogram)

Same labels as the counter. Buckets: `0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10` seconds. Tuned for a typical Laravel web tier - most requests are well under 1s; the interesting tail is at 2.5-10s for search and batch operations.

```promql
# P95 latency per route over the last 5 minutes
histogram_quantile(0.95, sum by (route, le) (rate(heratio_http_request_duration_seconds_bucket[5m])))

# How many requests took longer than 5 seconds in the last hour
sum by (route) (increase(heratio_http_request_duration_seconds_bucket{le="+Inf"}[1h]))
  - sum by (route) (increase(heratio_http_request_duration_seconds_bucket{le="5"}[1h]))
```

### `heratio_db_queries_total` (counter)

Labels: `connection` (the named Laravel DB connection, defaults to `mysql`).

Useful for catching N+1 regressions: a sudden jump in `rate(heratio_db_queries_total) / rate(heratio_http_requests_total)` is the classic signal.

### `heratio_db_query_duration_seconds` (histogram)

Labels: `connection`. Buckets `0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1` seconds. Single-query latencies are typically sub-100ms; anything 500ms+ is interesting.

We **deliberately do not label by SQL or table** - per-statement labels would blow cardinality. High-cardinality query inspection belongs in tracing (Phase 4 of #677), not metrics.

### `heratio_queue_depth` (gauge)

Labels: `connection`, `queue`. Sampled every minute by the `observability:record-queue-depth` scheduled command. Reflects the current backlog as `Queue::size($queue)`.

```promql
# Alert if the default queue has more than 1000 jobs backed up for 5 minutes
heratio_queue_depth{queue="default"} > 1000
```

## Scrape config example

```yaml
# /etc/prometheus/prometheus.yml
scrape_configs:
  - job_name: heratio
    metrics_path: /metrics
    scheme: https
    scrape_interval: 30s
    scrape_timeout: 10s
    bearer_token_file: /etc/prometheus/heratio.token
    static_configs:
      - targets: ['heratio.example.org']
        labels:
          environment: production
```

## Dashboard suggestions

For a Grafana dashboard wired against this exporter, the top panels we recommend:

1. **Request rate** - `sum by (route) (rate(heratio_http_requests_total[1m]))` (stacked area)
2. **P50/P95/P99 latency** - `histogram_quantile(0.95, ...)` for each quantile
3. **5xx rate** - filtered on `status=~"5.."`
4. **DB queries per request** - `sum(rate(heratio_db_queries_total[1m])) / sum(rate(heratio_http_requests_total[1m]))`
5. **DB P95 latency** - quantile from `heratio_db_query_duration_seconds_bucket`
6. **Queue backlog** - `heratio_queue_depth` as a line per queue

## Operational notes

### `route="unnamed"` is high

If `heratio_http_requests_total{route="unnamed"}` dominates, it means lots of routes lack a `->name(...)` call. That's not the exporter's problem - it's a routing hygiene issue. Add names to the worst offenders and the metric will split out.

### Redis adapter quietly degraded

If `OBSERVABILITY_STORAGE_DRIVER=redis` but Redis is unreachable at boot, the registry transparently falls back to InMemory rather than 500ing every request. You'll see per-process counters that don't aggregate across workers. Check Redis health.

### APCu and FPM reloads

A `systemctl reload php8.3-fpm` will reset APCu and therefore zero out counters under the APCu driver. Histograms are cumulative since process start, so quantile queries will look weird for a few minutes after reload. Use Redis if you can't tolerate this.

### Phase 4 (tracing) is out of scope

Tracing - per-span timings, distributed context propagation, slow-query dumps - is the next phase of #677. This package handles metrics only. Don't try to bolt on tracing here.

## Files

- Package: `packages/ahg-observability/`
- Config: `packages/ahg-observability/config/observability.php`
- Middleware registered: `bootstrap/app.php` (web prepend)
- Help article: `/help/observability-prometheus` (after `ahg:help-ingest`)
