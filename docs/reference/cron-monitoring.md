# Cron monitoring (ahg_cron_run + missed-run detector)

Issue #673 Phase 2. Every scheduled artisan command in Heratio is wrapped with a
tracking decorator that writes one row per invocation into `ahg_cron_run`, emits
Prometheus metrics, and is checked every 5 minutes for missed runs.

This is the read-side counterpart to `docs/reference/cron-setup.md` (which
covers how the schedule is *triggered*). This doc covers how runs are
*observed*.

## What gets tracked

Two tables back the monitoring layer:

| Table | Purpose |
|---|---|
| `ahg_cron_run` | One row per scheduled-command invocation. Insert at `started_at`, update at `finished_at`. Carries `exit_code`, `duration_ms`, `status`, `lock_token`, `hostname`, truncated `output`. |
| `ahg_cron_missed_run` | Flagged by `cron:check-missed-runs` when the gap between expected and actual run timestamps exceeds `miss_threshold_multiplier x interval` (default 2x). Idempotent on `(command, expected_at)`. `resolved_at` auto-stamps when a fresh successful run lands. |

Both tables auto-install on first boot via `AhgCoreServiceProvider::boot()`. The
install SQL lives in `packages/ahg-core/database/install_cron_run.sql`.

## How runs are wrapped

`CronSchedulerService::registerWithLaravelSchedule(Schedule $schedule)` is
called from the service provider inside an `$this->app->booted()` block. It
walks `getDefaultSchedules()` and registers each command on Laravel's native
Schedule facade with three lifecycle hooks:

```
before(...)    -> CronRunTrackerService::markStarted($command)   -> ahg_cron_run insert
after(...)     -> Artisan::call($command); markFinished(...)     -> ahg_cron_run update + metric
onFailure(...) -> markFinished($runId, 1, 'onFailure callback')  -> defensive close
```

`runSingle()` (the DB-driven path used by `cron:run`) also calls
`markStarted` / `markFinished` so both invocation modes are tracked.

### Distributed locking

When the active cache driver supports atomic locks (`redis`, `database`,
`dynamodb`, `memcached`), every event gets `->onOneServer()` so multi-box
deployments don't double-fire the same command. When the driver doesn't
(`file`, `array`), the wrapper logs a one-shot warning at boot and skips the
lock annotation rather than crashing `schedule:run`.

`CronRunTrackerService::supportsDistributedLocks()` is the gate.

## The missed-run detector

`cron:check-missed-runs` runs every 5 minutes (embedded by
`registerWithLaravelSchedule`). For every command in `getDefaultSchedules()`:

1. Compute the most recent expected run via `cron-expression/CronExpression::getPreviousRunDate()`.
2. Look up the latest `ahg_cron_run.finished_at` for that command.
3. If the gap > `miss_threshold_multiplier x interval`, insert into
   `ahg_cron_missed_run` (idempotent on `(command, expected_at)`).
4. Bump `heratio_cron_missed_runs_total{command}`.
5. If the command is in `config('cron-monitoring.high_priority_commands')`,
   drop a JSON notification into `/var/spool/workbench/notifications/` for
   the operator's bell.

The threshold multiplier defaults to **2x** so a job that ran 90s into a
5-minute window isn't flagged, but a job that hasn't run for 12 minutes is.
Override with `HERATIO_CRON_MISS_MULTIPLIER` in `.env`.

Auto-resolution: `CronRunTrackerService::markFinished()` stamps `resolved_at`
on every open miss row for the command the moment a successful run lands. So
dashboards see the gap close without operator intervention.

### Dry run

```
php artisan cron:check-missed-runs --dry-run
```

Reports would-be misses without inserting rows or firing notifications. Useful
when tuning `miss_threshold_multiplier`.

## Prometheus metrics

Exposed via `ahg-observability` (issue #677). Scrape from `/metrics` with the
bearer token configured in `OBSERVABILITY_METRICS_TOKEN`.

| Metric | Type | Labels | Meaning |
|---|---|---|---|
| `heratio_cron_runs_total` | counter | `command`, `status` | Total scheduled cron runs. `status` is `success` or `failed`. |
| `heratio_cron_duration_seconds` | histogram | `command` | Run wall-clock in seconds. Buckets 0.1, 0.5, 1, 5, 15, 60, 300, 900, 3600. |
| `heratio_cron_missed_runs_total` | counter | `command` | Total times `cron:check-missed-runs` flagged a command as overdue. |

Command labels are normalised to the base verb (no flags) so cardinality
stays one bucket per scheduled job, not one per per-flag variant. E.g.
`ahg:doi-process-queue --limit=50` reports as `ahg:doi-process-queue`.

### Suggested alerts

```yaml
- alert: HeratioCronMissedRun
  expr: increase(heratio_cron_missed_runs_total[10m]) > 0
  for: 5m
  labels: { severity: warning }
  annotations:
    summary: "Heratio cron {{ $labels.command }} missed its expected run window."

- alert: HeratioCronFailureRate
  expr: rate(heratio_cron_runs_total{status="failed"}[15m])
      / rate(heratio_cron_runs_total[15m]) > 0.5
  for: 10m
  labels: { severity: warning }
  annotations:
    summary: "More than half of recent {{ $labels.command }} runs failed."
```

## Configuration

`config/cron-monitoring.php` (mergeable; `cron-monitoring.*` keys):

| Key | env override | Default | Meaning |
|---|---|---|---|
| `high_priority_commands` | - | search, fixity, backup, sharepoint webhooks, llm-health, services-check, integrity-schedule | Commands whose missed runs raise a Workbench notification. |
| `notification_user` | `HERATIO_CRON_NOTIFY_USER` | `johan` | Username dropped into the spool payload. |
| `inbox_path` | `WORKBENCH_NOTIFICATIONS_INBOX` | `/var/spool/workbench/notifications` | Spool directory. |
| `miss_threshold_multiplier` | `HERATIO_CRON_MISS_MULTIPLIER` | `2.0` | Gap multiplier before a run is considered missed. |

## CLI reference

| Command | Purpose |
|---|---|
| `cron:check-missed-runs [--dry-run]` | Detector entrypoint. Runs every 5 minutes via the schedule. |
| `ahg:cron-status` | Existing dashboard of seeded `cron_schedule` rows + their last run. |
| `ahg:cron-run` | Existing DB-driven scheduler tick (also tracked via `runSingle`). |

## Operational notes

- The `notification_inbox` watcher (in `/usr/share/nginx/workbench/`) sweeps the
  spool every 15s. Files land in `archive/` after ingestion; malformed payloads
  go to `failed/`. Don't try to mkdir under `/var/spool/` from the php-fpm
  worker - it's blocked by `ProtectSystem=full`. The operator owns the
  inbox layout.
- `ahg_cron_run` is unbounded by design - retention is a downstream concern.
  When it gets large, prune via `DELETE FROM ahg_cron_run WHERE finished_at <
  DATE_SUB(NOW(), INTERVAL 90 DAY);` (or wire a retention command in Phase 3).
- The decorator's `Artisan::call()` runs the command **in-process** inside the
  scheduler tick. Long-running commands (e.g. `ahg:es-reindex`) should keep
  using `ahg:cron-run` (out-of-process) instead of the in-process wrap to
  avoid blocking the scheduler.
