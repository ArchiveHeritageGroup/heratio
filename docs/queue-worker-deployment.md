# Heratio Queue Worker Deployment

The ahg-queue-engine ships 5 DB tables and CLI commands (`heratio:queue:work`,
`heratio:queue:status`, `heratio:queue:retry`, `heratio:queue:failed`,
`heratio:queue:cleanup`) plus the standard Laravel `queue:work`. To actually
process jobs in production a long-running worker daemon must be supervised. This
doc covers both supervisord and systemd paths.

## Prerequisites

- PHP 8.3 CLI installed at `/usr/bin/php8.3`
- Heratio deployed at `/usr/share/nginx/heratio` and runnable as `www-data`
- `.env` has `QUEUE_CONNECTION=database` (default) and the `jobs` /
  `failed_jobs` / `job_batches` tables migrated
- `php artisan queue:work --once --stop-when-empty` exits 0 (smoke test)
- Log directory `/var/log` writable by the `www-data` user (or grant via the
  systemd `ReadWritePaths` block already included in the unit)

Pick one supervisor — do not run both.

## Path A — supervisord (recommended for single-host installs)

```bash
sudo apt install supervisor
sudo cp /usr/share/nginx/heratio/tools/supervisord/heratio-queue-worker.conf \
        /etc/supervisor/conf.d/heratio-queue-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start heratio-queue-worker:*
```

The program block runs `numprocs=2` workers, restarts on exit, and writes to
`/var/log/heratio-queue-worker-00.log` and `-01.log`.

## Path B — systemd (recommended where supervisord is unavailable)

```bash
sudo cp /usr/share/nginx/heratio/tools/systemd/heratio-queue-worker@.service \
        /etc/systemd/system/heratio-queue-worker@.service
sudo systemctl daemon-reload
sudo systemctl enable --now heratio-queue-worker@1.service \
                            heratio-queue-worker@2.service
```

This is a template unit — `@1` and `@2` are the two worker instances.

## Verification

```bash
# Service state
sudo systemctl status heratio-queue-worker@1.service
sudo supervisorctl status heratio-queue-worker:*

# Queue depth + failed counts
cd /usr/share/nginx/heratio && php artisan heratio:queue:status

# Live log
sudo journalctl -u heratio-queue-worker@1.service -f
tail -f /var/log/heratio-queue-worker-00.log
```

A healthy worker prints `[YYYY-MM-DD HH:MM:SS] Processing: <Job>` lines when
work is available and stays silent while idle.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Worker keeps dying / restart loop | Fatal in the job class or bootstrap | Check `/var/log/heratio-queue-worker-*.log` and Laravel `storage/logs/laravel-*.log` |
| Memory creeps up over time | Job leaks objects between iterations | Worker auto-exits at 512M (the `--memory=512` flag); supervisor restarts it. Reduce limit or look for the leaking job |
| Jobs stuck in `jobs` table | No worker running, or wrong queue name | `systemctl status …` / `supervisorctl status`; confirm `--queue=default,integrations` matches the queues the dispatchers push to |
| Worker can't write to log | `www-data` lacks `/var/log` write | Pre-create the log files with `install -o www-data -g www-data -m 0640 /dev/null /var/log/heratio-queue-worker-00.log` (etc.) |
| Worker writes 500s like Laravel web requests | php-fpm `ProtectSystem=full` is leaking into CLI? It shouldn't — CLI ignores that. Check `.env` is readable | `sudo -u www-data php /usr/share/nginx/heratio/artisan tinker` should boot cleanly |
| Graceful stop hangs | Long-running job exceeds `stopwaitsecs` / `TimeoutStopSec` | Both are set to 60s; raise if individual jobs need longer, or signal `--timeout` higher |
