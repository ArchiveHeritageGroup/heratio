# Cron setup for Heratio

Heratio uses **per-task `/etc/cron.d/` files** (one file per scheduled artisan command) plus **one entrypoint that triggers Laravel's scheduler** every minute. The scheduler entrypoint covers everything registered via `Schedule::command()` in service providers (audit:prune, auth:gc-attempts, auth:warn-password-expiry, ahg:cron-run, etc.); the per-task files cover commands that need their own cadence or per-database iteration.

## The single entrypoint

`/etc/cron.d/heratio-schedule` (installed 2026-05-06) calls `php artisan schedule:run` every minute. Without this file, **all `Schedule::command()` registrations are dormant** - `schedule:list` shows them but they never fire.

```cron
* * * * * www-data flock -n /var/run/heratio-schedule.lock -c '/usr/bin/php8.3 /usr/share/nginx/heratio/artisan schedule:run >> /var/log/heratio-schedule.log 2>&1'
```

Output goes to `/var/log/heratio-schedule.log` (chowned to `www-data`).

## Per-task cron files

| File | User | Cadence | Command |
|---|---|---|---|
| `/etc/cron.d/ahg-discovery-prune` | `www-data` | minute 17 of every hour | `ahg:discovery-prune` against heratio + atom + archive DBs |
| `/etc/cron.d/ahg-facet-cache` | `www-data` | minute 0 of every hour | `ahg:refresh-facet-cache` against archive + atom + dam + heratio DBs |
| `/etc/cron.d/atom-backup` | `root` | daily 3am | shell script (mysqldump + filesystem rsync; no PHP) |
| `/etc/cron.d/atom-bot-watch` | `root` | weekly Mon 8am | shell script (no PHP) |
| `/etc/cron.d/mog-nas-backup` | `root` | per file | shell script (no PHP) |
| `/etc/cron.d/mysql-full-dump` | `root` | per file | shell script (no PHP) |

## Critical pattern: absolute paths

`/etc/cron.d/*` commands run with `cwd=/`, so a bare `php artisan ...` fails with `Could not open input file: artisan` because `artisan` is a relative path. Always use:

```cron
/usr/bin/php8.3 /usr/share/nginx/heratio/artisan <command>
```

Not:

```cron
php artisan <command>      # ← fails silently in /etc/cron.d/
```

This bit `/etc/cron.d/ahg-discovery-prune` and `/etc/cron.d/ahg-facet-cache` from their original deployment until 2026-05-06; both had been silently failing every hour - `/var/log/heratio-discovery-prune.log` was full of `Could not open input file: artisan`. Both fixed in the same release that wired audit issue #90.

## Critical pattern: artisan crons must run as `www-data`, never `root`

Any cron line that invokes `php artisan ...` MUST set the user field to `www-data`. Artisan commands lazy-create directories under `storage/framework/cache/data/<2c>/<2c>/` (Laravel file cache shards), and if a root-run cron creates any of those, the next web request that hashes into the same shard tree gets:

```
fopen(/usr/share/nginx/heratio/storage/framework/cache/data/.../...): Failed to open stream: No such file or directory
```

at `vendor/laravel/framework/src/Illuminate/Filesystem/LockableFile.php:68`. The web user (`www-data`) cannot `mkdir` inside a root-owned shard, `@mkdir` returns false silently, and `fopen('c+')` then 500s.

**Concrete incident: 2026-05-08.** `/etc/cron.d/ahg-facet-cache` and `/etc/cron.d/ahg-discovery-prune` had been running as root since deployment. Overnight 2026-05-07 they created 17 root-owned shards under `cache/data/`, which surfaced as 500s on `/api/ric/v1/relations-for/{id}` and `/api/ric/v1/records/{id}/entities` in `/admin/errorLog`. Fix: `chown -R www-data:www-data storage/framework/cache/data` + flip both cron files to `www-data` + chown `/var/log/facet-cache.log` and `/var/log/heratio-discovery-prune.log` to `www-data:adm` so the new owner can write them.

This is the same root-cause family as `feedback_no_root_smoke_writes.md` (CLI smoke writes as root creating root-owned dirs under `storage/app`); the cron variant is more dangerous because it recreates the misowned dirs hourly.

**Allowed exceptions** (root-run is fine because no PHP-FPM read path follows): `atom-backup`, `atom-bot-watch`, `mog-nas-backup`, `mysql-full-dump` - pure shell scripts that do not touch `storage/framework/cache/`.

## How `Schedule::command()` registrations look

Inside any service provider's `boot()`:

```php
if ($this->app->runningInConsole()) {
    $this->commands([\App\Console\Commands\AuthGcAttemptsCommand::class]);
    $this->app->afterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
        $schedule->command('auth:gc-attempts')->hourly()->withoutOverlapping();
    });
}
```

Each registration appears in `php artisan schedule:list` and fires whenever `/etc/cron.d/heratio-schedule` ticks the minute that matches its cron expression.

## Verify a registration is wired

```bash
sudo -u www-data php artisan schedule:list
# Look for the command name and "Next Due"

# After waiting past Next Due, check the log:
sudo tail -20 /var/log/heratio-schedule.log
sudo journalctl -u cron --since "5 min ago" | grep heratio-schedule
```

## Pre-existing cron debt

- `/etc/cron.d/ric_cron.disabled` - RiC sync cron, intentionally disabled (renamed `.disabled` so cron skips it). Re-enable by renaming back to `ric-sync` if RiC sync is needed.
- `/etc/cron.d/ahg-facet-cache.bak-20260428` - backup from the 2026-04-28 stuck-query incident; can be deleted but kept for audit-trail.
