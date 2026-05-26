# Observability alerting

> Issue #677 Phase 4. How Heratio turns the metrics from `/metrics` into
> pages and warnings.

## Summary (read this first)

- Five alerts ship out of the box: HighErrorRate, SlowResponses,
  QueueBacklog, DbSlowQueries, InferenceChainBroken.
- Two severities reach a human: `page` (email + workbench bell) and
  `warn` (workbench bell only). `info` is logged only.
- The compliance alert relies on a synthetic gauge written by an hourly
  cron - if the cron stops, the alert fires (by design).

The full operator wiring lives in
`docs/reference/observability-alerting.md`. This article is the
end-user "what does the bell mean" view.

## The five alerts in plain language

| Alert                  | What it means                                                         |
| ---------------------- | --------------------------------------------------------------------- |
| HighErrorRate          | Heratio is returning 5xx to more than 1 in 20 requests. Site visible degradation. |
| SlowResponses          | The slowest 1% of requests are taking longer than 2 seconds.          |
| QueueBacklog           | Background jobs are piling up - delayed thumbnails, ingest, indexing. |
| DbSlowQueries          | The database is responding more slowly than usual.                    |
| InferenceChainBroken   | The AI inference audit log can no longer be verified end-to-end.      |

The first four are availability and performance signals. The fifth is a
compliance signal and should be treated as a security incident until
investigated.

## What happens when an alert fires

- **Page** (HighErrorRate, InferenceChainBroken)
  - You get an email via the standard Heratio notification pipeline.
  - The workbench bell at `ai.theahg.co.za` lights up with a toast and chime.
- **Warn** (SlowResponses, QueueBacklog, DbSlowQueries)
  - Workbench bell only.
- **Info** (none shipped by default)
  - Logged in Alertmanager; no notification.

## Where to look when an alert fires

1. The Grafana dashboard `Heratio Overview` (uid `heratio-overview`)
   has the four panels that mirror the alert thresholds.
2. The structured-JSON log channel at
   `storage/logs/laravel-json-*.log` is searchable in Loki by
   `{job="heratio"}` plus any of the parsed labels (`level`, `route`,
   `status`).
3. For `InferenceChainBroken`, run `php artisan
   ai-compliance:verify-inference-log` directly - the output will name
   the broken sequence number.

## Acknowledging and silencing

Use the Alertmanager UI to silence noisy alerts during planned
maintenance (e.g. an ingest run that will spike QueueBacklog for an
hour). Always set an end time on the silence - open-ended silences are
how teams stop noticing real outages.

## Tuning thresholds

Defaults in `packages/ahg-observability/config/alerts/heratio.rules.yml`
suit a small single-tenant deployment. For large multi-tenant installs
follow the per-tenant override pattern in
`docs/reference/observability-alerting.md`.

## Related help

- `observability/prometheus-exporter.md` - the `/metrics` surface
  alerts are built on.
- `email-phase2-bounce-locale-branding.md` - the email pipeline that
  delivers paging alerts.
- `ai-compliance-article-12.md` - background on the inference chain
  that `InferenceChainBroken` protects.
