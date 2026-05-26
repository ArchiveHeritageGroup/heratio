# Loki + Grafana deployment for Heratio

> Issue #677 Phase 4. Operator guide to centralised log aggregation and
> dashboarding on top of the structured-JSON channel (Phase 1) and
> Prometheus exporter (Phase 3).

## What you get when this is wired

- Every line written to `storage/logs/laravel-json-*.log` is shipped to
  Loki in near real time.
- The 30-day default retention is set in Loki itself, so you can run a
  small disk and still keep a month of investigation context.
- A Grafana datasource pair (Loki + Prometheus) makes both the metrics
  from `/metrics` and the matching log lines queryable side by side.
- A starter dashboard (`grafana/heratio-overview.json`) gives you request
  rate, error rate, p99 latency, and queue depth without writing a single
  PromQL expression.

## Prerequisites

1. Phase 1 shipped, so `config/logging.php` has a `json` channel writing
   to `storage/logs/laravel-json-*.log`. Confirm with
   `tail -1 storage/logs/laravel-json-*.log | jq .`.
2. Phase 3 shipped, so `GET /metrics` returns a Prometheus body. Confirm
   with the bearer token in your `.env` (`OBSERVABILITY_TOKEN`).
3. A reachable Loki (>= 2.9), Grafana (>= 10), and Promtail (>= 2.9). On
   a single-host deployment they can all sit on the Heratio box; on
   multi-host they belong on the observability node.

## Promtail scrape config

Promtail tails the JSON log files, parses each line as JSON, and
forwards labelled streams to Loki. Save as `/etc/promtail/config.yml`:

```yaml
server:
  http_listen_port: 9080
  grpc_listen_port: 0

positions:
  filename: /var/lib/promtail/positions.yaml

clients:
  - url: http://loki.internal:3100/loki/api/v1/push

scrape_configs:
  - job_name: heratio-json
    static_configs:
      - targets: [localhost]
        labels:
          job: heratio
          env: production
          host: heratio01
          __path__: /usr/share/nginx/heratio/storage/logs/laravel-json-*.log

    pipeline_stages:
      # Phase 1 of #677 writes one JSON object per line.
      - json:
          expressions:
            level: level
            channel: channel
            request_id: context.request_id
            user_id: context.user_id
            route: context.route
            method: context.method
            status: context.status
      - labels:
          level:
          channel:
          route:
          status:
      # Keep timestamps from the application, not from Promtail's clock,
      # so log lines align with Prometheus samples in Grafana.
      - timestamp:
          source: timestamp
          format: RFC3339Nano
```

Heratio's Phase 1 log lines contain a `request_id` that is also written
to the `X-Request-Id` response header. Grafana can pivot from a single
5xx response to all the log lines for that request in one click; keep
`request_id` out of the label set (high cardinality) and rely on the
parsed field in the message body.

## Loki config (retention, single-binary)

Sample `loki-config.yml` for a single-binary deployment. The
**`retention_period: 720h`** line is the 30-day default referenced in
the brief; override via `LOKI_RETENTION_PERIOD` on the systemd unit if
your compliance window is different.

```yaml
auth_enabled: false

server:
  http_listen_port: 3100

common:
  path_prefix: /var/lib/loki
  storage:
    filesystem:
      chunks_directory: /var/lib/loki/chunks
      rules_directory: /var/lib/loki/rules
  replication_factor: 1
  ring:
    kvstore:
      store: inmemory

schema_config:
  configs:
    - from: 2024-01-01
      store: tsdb
      object_store: filesystem
      schema: v13
      index:
        prefix: index_
        period: 24h

limits_config:
  retention_period: 720h          # 30 days. Override via LOKI_RETENTION_PERIOD.
  reject_old_samples: true
  reject_old_samples_max_age: 168h

compactor:
  working_directory: /var/lib/loki/compactor
  retention_enabled: true
  retention_delete_delay: 2h
  delete_request_store: filesystem
```

Restart Loki after editing. Confirm retention is live with
`curl -s localhost:3100/config | grep retention_period`.

## Grafana datasource setup

In Grafana, add two datasources via the UI (Configuration -> Data sources
-> Add):

| Type       | Name             | URL                                  | Notes                                                    |
| ---------- | ---------------- | ------------------------------------ | -------------------------------------------------------- |
| Loki       | `loki`           | `http://loki.internal:3100`          | Default. No auth in a private VLAN; add basic auth otherwise. |
| Prometheus | `prometheus`     | `http://prometheus.internal:9090`    | Set scrape interval = 30s to match Heratio defaults.     |

The starter dashboard expects these exact datasource names. Rename if
you must, then `sed` the JSON before importing.

## Import the starter dashboard

```bash
curl -X POST \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer ${GRAFANA_API_TOKEN}" \
  -d @docs/observability/grafana/heratio-overview.json \
  http://grafana.internal:3000/api/dashboards/db
```

You get four panels:

1. **Request rate** - `sum(rate(heratio_http_requests_total[1m])) by (status)`
2. **Error rate (%)** - 5xx ratio over the last 5 minutes.
3. **p99 latency** - `histogram_quantile(0.99, ...)` of the HTTP duration histogram.
4. **Queue depth** - `heratio_queue_depth{queue="default"}` over time.

This is intentionally minimal; teams that want per-route or per-tenant
breakdowns can clone and extend the panels using the same metric names.

## Cross-reference

- Phase 1 (structured JSON) - `docs/help/observability/log-channels.md`
  (if missing on your install, see CLAUDE.md "structured logging" section
  for the channel definition).
- Phase 3 (Prometheus exporter) - `docs/help/observability/prometheus-exporter.md`
  and `packages/ahg-observability/README.md`.
- Phase 4 alerts that this dashboard surfaces -
  `packages/ahg-observability/config/alerts/heratio.rules.yml`.

Once the dashboard + alerting are both running you have the full Phase
1 - Phase 4 path: structured logs land in Loki, metrics roll up into
Prometheus, Alertmanager routes severities to email + workbench bell,
and Grafana ties the two streams together for a single operator pane.
