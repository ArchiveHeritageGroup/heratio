# 2026-05-25 - Issue #677 Phase 3: Prometheus metrics exporter

## Summary

Shipped a new `ahg/observability` package that exposes a Prometheus-compatible `/metrics` endpoint on the Heratio web tier. This is Phase 3 of GitHub issue #677 (the observability umbrella). Phase 2 (request_id middleware) was shipped previously; Phase 4 (tracing) is still out of scope.

## What shipped

### New package: `packages/ahg-observability/`

- `composer.json` requires `php ^8.3` and `promphp/prometheus_client_php ^2.13`.
- `MetricsRegistry` wraps PromPHP's `CollectorRegistry`. Auto-selects storage:
  - Redis when `cache.default = redis` (multi-process safe; correct production default)
  - APCu when the extension is loaded
  - InMemory otherwise (tests, single-process CLI)
- `PrometheusHttpMiddleware::terminate()` records `heratio_http_requests_total` (counter) and `heratio_http_request_duration_seconds` (histogram). Labels: method, route, status. Latency buckets: 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10 s.
- `RecordDbQuery` subscribes to `QueryExecuted` and records `heratio_db_queries_total` + `heratio_db_query_duration_seconds`. Single label: connection. Buckets: 0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1 s.
- `observability:record-queue-depth` artisan command sets `heratio_queue_depth` gauge per `(connection, queue)`. Scheduled every minute by the service provider via `Schedule::command(...)->everyMinute()->withoutOverlapping()`.
- `MetricsController::show()` renders the registry via `RenderTextFormat`, authenticated by bearer token (`OBSERVABILITY_TOKEN`) OR client IP allow-list (`OBSERVABILITY_ALLOWED_IPS`, defaults to loopback). Fail-closed by default.
- Feature test `MetricsEndpointTest` covers: 401 without token, 200 with token, HELP/TYPE preamble present, counter increments between scrapes.

### Wiring

- `composer.json` (root): added `"ahg/observability": "@dev"` alphabetically between `oai` and `pdf-tools`.
- `bootstrap/app.php`: registered `PrometheusHttpMiddleware` in the web `prepend` list, immediately after `RequestIdMiddleware` and before `SessionTimeout`. This places it before any auth middleware (so anonymous + 401 + 403 requests still get counted) while keeping the request_id in scope for `terminate()`.

### Docs

- `docs/observability-prometheus.md` - operator runbook (config, scrape interval, dashboard suggestions, troubleshooting).
- `docs/help/observability/prometheus-exporter.md` - in-app help article. Slug `observability-prometheus`.
- `packages/ahg-observability/README.md` - package-level usage doc.

## Why these counter choices

- **HTTP buckets (0.05 - 10 s)** match typical Laravel web-tier latency: most requests are well under 1 s; the interesting long tail is 2.5-10 s for search and batch endpoints.
- **DB buckets (0.001 - 1 s)** target single-query timings: anything 500 ms+ is interesting; 1 s+ is "slow query" territory and worth alerting on.
- **Route label uses `getName() ?? "unnamed"`** rather than raw URI - prevents per-slug cardinality explosion (the #1 anti-pattern flagged in the prometheus_client_php docs).
- **DB label is only `connection`**, NOT SQL/table - per-statement labels would also blow cardinality, and that's a tracing problem (Phase 4), not metrics.
- **Queue depth as gauge sampled every minute** rather than counter+timestamp from enqueue/dequeue hooks - sampling avoids wiring into every queue driver's job lifecycle, and 60s resolution is fine for backlog alerting.

## How to scrape

```yaml
# prometheus.yml
scrape_configs:
  - job_name: heratio
    metrics_path: /metrics
    scheme: https
    bearer_token_file: /etc/prometheus/heratio.token
    static_configs:
      - targets: ['heratio.example.org']
    scrape_interval: 30s
```

Token-less localhost scrape works out of the box (loopback is in `allowed_ips` by default).

## Constraints honoured

- Stayed inside the worktree; no `composer install`, no `./bin/release`, no `./bin/unlock`, no `git push`.
- No changes under any `.locked-paths` prefix - the new package + config + bootstrap edit all live outside locked subtrees.
- Every new PHP file carries the Johan Pieterse / Plain Sailing / AGPL header.

## Followups

- Run `composer update ahg/observability` on `main` after cherry-pick so PromPHP is fetched. The package will not autoload until composer regenerates `vendor/composer/autoload_*.php`.
- After `ahg:help-ingest` is run against the new help article, surface it in the help navigation.
- Phase 4 (distributed tracing / slow-query dumps) remains open under #677.
