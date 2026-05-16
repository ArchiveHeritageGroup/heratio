# php-fpm `ProtectSystem=full` — required `ReadWritePaths` drop-in (host-wide pattern)

**Audience:** anyone deploying a Laravel/PHP app under `/usr/share/nginx/` on the `theahg.co.za` host.

## TL;DR

The Debian/Ubuntu `/usr/lib/systemd/system/php8.3-fpm.service` unit ships with `ProtectSystem=full`, which mounts `/usr`, `/boot`, and `/etc` **read-only for the php-fpm worker**. Apps under `/usr/share/nginx/<app>/` cannot write to their own `storage/`, `bootstrap/cache/`, sessions, views, or logs from a web request without a per-app `ReadWritePaths=` drop-in.

The Registry hit this in production on 2026-05-16: login was partially working but `/admin`, `/map`, and `/my-favorites` returned 500. Root cause was not file permissions and not the disk — it was systemd's per-service filesystem protection.

## Diagnostic fingerprint

- HTTP 500s with `Failed to open stream: Read-only file system` against `…/storage/logs/laravel-YYYY-MM-DD.log` even though the disk is `rw` and the file is owned by `www-data`.
- Concurrent `tempnam(): file created in the system's temporary directory` warnings from `Illuminate\Filesystem\Filesystem::replace()` — symptom of the parent dir being unwritable.
- Cron-driven `php artisan` (e.g. `schedule:run` from `/etc/cron.d/<app>`) writes to logs **just fine**, because cron does not inherit the php-fpm.service unit restrictions. This split — cron writes work, web writes fail — is the classic signature.
- `sudo -u www-data` shell writes succeed but a tiny `file_put_contents()` probe served by php-fpm fails. That asymmetry confirms ProtectSystem rather than ownership.

## Fix template

Per app, create `/etc/systemd/system/php8.3-fpm.service.d/<app>-storage.conf`:

```ini
[Service]
ReadWritePaths=/usr/share/nginx/<app>/storage
ReadWritePaths=/usr/share/nginx/<app>/bootstrap/cache
```

Then:

```bash
systemctl daemon-reload
systemctl restart php8.3-fpm
systemctl show php8.3-fpm | grep ReadWritePaths
```

Restart is sub-second but it blips every PHP site on the host briefly. Schedule accordingly.

## Companion: default ACL on storage subdirs

So a root-owned file accidentally dropped in `storage/logs/` (often from running `php artisan` as root) does not later block the worker:

```bash
APP_ROOT=/usr/share/nginx/<app>
setfacl -d -m u:www-data:rwx \
  "$APP_ROOT/storage/logs" \
  "$APP_ROOT/storage/framework/cache" \
  "$APP_ROOT/storage/framework/sessions" \
  "$APP_ROOT/storage/framework/views" \
  "$APP_ROOT/bootstrap/cache"
setfacl    -m u:www-data:rwx \
  "$APP_ROOT/storage/logs" \
  "$APP_ROOT/storage/framework/cache" \
  "$APP_ROOT/storage/framework/sessions" \
  "$APP_ROOT/storage/framework/views" \
  "$APP_ROOT/bootstrap/cache"
```

Operational rule: **don't run `php artisan` as root** from these directories. Use `sudo -u www-data php artisan …`.

## Already in place

- `/etc/systemd/system/php8.3-fpm.service.d/registry-storage.conf` — for `/usr/share/nginx/registry`.
- `/etc/systemd/system/php8.3-fpm.service.d/heratio-storage.conf` — for `/usr/share/nginx/heratio` (added 2026-05-16 after `/help` started 500ing with a `tempnam(): file created in the system's temporary directory` masking exception — root cause was the same `ProtectSystem=full` issue, not a new bug. The Filesystem::replace() call in BladeCompiler couldn't `tempnam(dirname(...))` into `storage/framework/views/`, fell back to the system temp dir, and PHP raised the warning-as-exception via Laravel's error handler).

Add equivalents for any future Laravel deployment under `/usr/share/nginx/`. Same drop-in pattern; one per app.
