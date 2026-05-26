# Observability and alerting reference

One-shot read covering Heratio's Phase 4 alerting wiring on top of the
Phase 3 Prometheus exporter and the Phase 1 structured-JSON channel
(GitHub issue #677). Pair with the operator deployment guide at
`docs/observability/loki-grafana-deployment.md`.

## Metrics surface (recap)

Heratio exposes these five metric families at `GET /metrics` (token-gated
or loopback-only, see `packages/ahg-observability/README.md`):

| Metric                                          | Type      | Labels                  |
| ----------------------------------------------- | --------- | ----------------------- |
| `heratio_http_requests_total`                   | counter   | method, route, status   |
| `heratio_http_request_duration_seconds`         | histogram | method, route, status   |
| `heratio_db_queries_total`                      | counter   | connection              |
| `heratio_db_query_duration_seconds`             | histogram | connection              |
| `heratio_queue_depth`                           | gauge     | connection, queue       |

Phase 4 adds a synthetic gauge written by node_exporter's textfile
collector (not the `/metrics` endpoint):

| Metric                                | Source                                          |
| ------------------------------------- | ----------------------------------------------- |
| `ai_compliance_verify_status`         | `ai-compliance:emit-metrics` (hourly cron)      |
| `ai_compliance_verify_last_run_seconds` | same                                          |

## The five alerts

All rules live in
`packages/ahg-observability/config/alerts/heratio.rules.yml`. Load them
into Prometheus via `rule_files:`.

| Alert                  | When it fires                                                         | Severity | Team       |
| ---------------------- | --------------------------------------------------------------------- | -------- | ---------- |
| `HighErrorRate`        | 5xx ratio > 5% over 5m, for 5m                                        | page     | ops        |
| `SlowResponses`        | p99 latency > 2s over 5m, for 10m                                     | warn     | sre        |
| `QueueBacklog`         | `heratio_queue_depth{queue="default"}` > 100 for 5m                   | warn     | ops        |
| `DbSlowQueries`        | p99 DB query duration > 1s over 5m, for 10m                           | warn     | sre        |
| `InferenceChainBroken` | `ai_compliance_verify_status != 1` for 1m (missing samples included)  | page     | compliance |

Treat missing `ai_compliance_verify_status` samples as a real failure -
it's almost always a silently broken cron, which is itself a compliance
incident.

## Wiring through Alertmanager

A complete starter config lives at
`docs/observability/alertmanager.yml.example`. The routing tree is
intentionally short:

```
route (default: log only)
 +- severity=page  -> heratio-page  (email + workbench bell, repeat 1h)
 +- severity=warn  -> heratio-workbench-bell (repeat 12h)
 +- severity=info  -> heratio-log-only
```

`HighErrorRate` inhibits `SlowResponses` while it is firing - if the
service is degraded enough to page, the secondary latency warn becomes
noise.

## Email and workbench bell receivers

Two non-default surfaces:

- **Email** uses the Heratio email pipeline shipped under issue #674
  Phase 2 (templated, locale-aware, with bounce handling). Alertmanager
  posts to `/api/observability/alert-email` and the pipeline picks the
  template from the `alertname` label.
- **Workbench bell** uses the cross-agent notification spool documented
  in the global CLAUDE.md. A tiny webhook receiver at
  `/usr/local/sbin/alertmanager-workbench-bell` translates the
  Alertmanager webhook JSON into the spool file shape:

  ```json
  {
    "username": "johan",
    "title":    "[page] HighErrorRate on heratio",
    "message":  "5xx ratio above 5% for 5m. Check storage/logs/laravel-json-*.log",
    "eventType":"alert",
    "webLink":  "https://grafana.example.org/d/heratio-overview"
  }
  ```

  Drop the file in `/var/spool/workbench/notifications/`; the
  notification-inbox watcher picks it up within 15s and surfaces the
  bell + toast + chime.

## Overriding thresholds per tenant

Heratio is multi-tenant via the `ahg-multitenant` package. The default
thresholds in `heratio.rules.yml` are written for the smallest
deployment; large tenants will trip `QueueBacklog` and `DbSlowQueries`
constantly under normal load.

Two recommended patterns:

1. **Per-tenant Prometheus** - if each tenant runs its own scrape target
   (recommended for compliance isolation), copy `heratio.rules.yml` into
   that tenant's Prometheus and bump the constants in place. Keep the
   `severity` / `team` labels stable so the same Alertmanager routes
   work without change.

2. **Single Prometheus with per-tenant labels** - if you scrape every
   tenant from one Prometheus, add an external label per scrape job and
   write rules like:

   ```yaml
   - alert: QueueBacklogLargeTenant
     expr: heratio_queue_depth{queue="default", tenant=~"big-.*"} > 500
     for: 5m
     labels: { severity: warn, team: ops }
   ```

   Disable the default `QueueBacklog` for tenants covered by the new
   rule via Alertmanager inhibition.

Do NOT edit the shipped rules file in place on a multi-tenant install -
keep tenant deltas in a sibling file so future Heratio releases stay
mergeable.

## How the inference-chain gauge gets there

The Article 12 verifier (`ai-compliance:verify-inference-log`) is too
expensive to invoke from every Prometheus scrape, so we cache its result
in a textfile that node_exporter publishes:

1. Cron runs `php artisan ai-compliance:emit-metrics` hourly.
2. The command shells out to `ai-compliance:verify-inference-log
   --quiet-pass`.
3. PASS = write `ai_compliance_verify_status 1`, FAIL = `0`.
4. The file is atomically renamed into
   `config('observability.textfile_dir')`
   (default `/var/lib/node_exporter/textfile_collector`).
5. node_exporter exposes it on its own `/metrics`, Prometheus scrapes
   that endpoint as usual, the InferenceChainBroken rule evaluates
   against the gauge.

Cron entry (`/etc/cron.d/heratio-compliance-metrics`):

```
0 * * * * www-data cd /usr/share/nginx/heratio && /usr/bin/php artisan ai-compliance:emit-metrics >> storage/logs/ai-compliance-emit-metrics.log 2>&1
```

If `ahg-ai-compliance` is not installed on a particular host, the
command exits with a clear error and does not write a file - the
alerting will fire (as designed) because the gauge is missing.

## Cross-references

- Phase 1 (structured-JSON channel) - `docs/help/observability/log-channels.md` /
  `config/logging.php` `json` driver.
- Phase 3 (Prometheus exporter) - `packages/ahg-observability/README.md`.
- Phase 4 alert rules - `packages/ahg-observability/config/alerts/heratio.rules.yml`.
- Alertmanager routes - `docs/observability/alertmanager.yml.example`.
- Loki + Grafana deployment - `docs/observability/loki-grafana-deployment.md`.
- Email pipeline (#674 Phase 2) - `docs/help/email-phase2-bounce-locale-branding.md`.
- Workbench notification spool - global `CLAUDE.md` "notification drop folder" section.

Open follow-up phases on #677: OpenTelemetry tracing, APM, per-tenant
dashboards, runbook authoring, uptime monitoring, RUM. None of those
are required to operate the alerting in this document.
