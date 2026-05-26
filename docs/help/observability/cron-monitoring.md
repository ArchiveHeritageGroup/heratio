# Cron monitoring

Heratio tracks every scheduled artisan command into a database row, exposes
Prometheus metrics, and notifies you when a high-priority command misses its
expected run window. This is Phase 2 of GitHub issue #673.

## Summary (read this first)

- Every scheduled command writes one row to `ahg_cron_run` (start + finish, exit code, duration, hostname).
- A detector (`cron:check-missed-runs`) runs every 5 minutes. When a run is more than 2x its interval late, it lands in `ahg_cron_missed_run`.
- Three Prometheus metrics: `heratio_cron_runs_total`, `heratio_cron_duration_seconds`, `heratio_cron_missed_runs_total`.
- High-priority misses raise a Workbench notification on Johan's bell automatically.
- No configuration is required for the default behaviour. Set `HERATIO_CRON_MISS_MULTIPLIER` to relax or tighten the threshold.

If your Heratio host is already running Laravel's scheduler (see the
"Cron setup" help article), monitoring is on the moment you deploy this
release - no extra cron lines required.

## What gets tracked

| Column in `ahg_cron_run` | Meaning |
|---|---|
| `command` | The artisan command string (full token incl. flags). |
| `started_at` | Start timestamp, rounded down to the minute (idempotency key). |
| `finished_at` | End timestamp; `NULL` while still running. |
| `exit_code` | Symfony exit code (0 = success). |
| `duration_ms` | Wall-clock duration in milliseconds. |
| `status` | `running`, `success`, `failed`, or `skipped`. |
| `lock_token` | Atomic-cache lock token when `->onOneServer()` granted the run; `NULL` when the cache driver doesn't support locks. |
| `hostname` | Server that executed the run (handy on multi-box deployments). |
| `output` | Trailing 5000 chars of artisan output, failures only. |

## High-priority commands

The default list is in `config/cron-monitoring.php`:

- `ahg:search-update`, `ahg:search-populate`
- `ahg:preservation-fixity --age=30 --report`
- `ahg:backup --components=database --retention=30`
- `sharepoint:renew-subscriptions`, `sharepoint:auto-ingest`
- `ahg:services-check`, `ahg:llm-health`
- `ahg:integrity-schedule --run-due`

A miss on any of these drops a JSON notification into
`/var/spool/workbench/notifications/`, which the workbench watcher surfaces
on the bell within 15 seconds. Other commands still land in
`ahg_cron_missed_run` and bump the Prometheus counter, but stay quiet.

## How distributed locking works

When your Heratio cache driver supports atomic locks (`redis`, `database`,
`dynamodb`, `memcached`), every scheduled event gets `->onOneServer()`. On
multi-box deployments this prevents two boxes from running the same command
at the same minute.

When the driver doesn't (`file`, `array`), the wrapper logs a one-shot
warning in the Laravel log and skips the lock annotation rather than
crashing the scheduler. Single-host deployments are unaffected.

## CLI

| Command | Purpose |
|---|---|
| `php artisan cron:check-missed-runs` | Run the detector manually. |
| `php artisan cron:check-missed-runs --dry-run` | Report would-be misses without inserting rows or notifying. |
| `php artisan ahg:cron-status` | Show all seeded `cron_schedule` rows + their last run. |

## Where to look when something looks wrong

1. `SELECT * FROM ahg_cron_missed_run WHERE resolved_at IS NULL` - all currently overdue commands.
2. `SELECT * FROM ahg_cron_run WHERE command = ? ORDER BY started_at DESC LIMIT 20` - recent run history.
3. `/var/log/laravel.log` - look for `[ahg-core] cron-monitoring` warnings (cache driver, install failures).
4. Prometheus scrape `heratio_cron_missed_runs_total` - per-command count over time.

## Configuration overrides

In `.env`:

```
HERATIO_CRON_NOTIFY_USER=johan
HERATIO_CRON_MISS_MULTIPLIER=2.0
WORKBENCH_NOTIFICATIONS_INBOX=/var/spool/workbench/notifications
```

`miss_threshold_multiplier` of `1.5` makes the detector more sensitive
(flag earlier); `3.0` makes it more forgiving (only flag clear-cut misses).

See the operator reference at `docs/reference/cron-monitoring.md` for the
schema details, Prometheus alert rules, and architectural notes.
