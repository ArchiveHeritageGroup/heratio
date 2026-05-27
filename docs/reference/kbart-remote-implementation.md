# KBART Remote Feeds + Scheduler Implementation Reference

**Issues:** heratio#767 (remote URL fetch), heratio#768 (scheduler)
**Package:** `packages/ahg-library/`
**Status:** #767 fully wired (Option B - vendor registry). #768 partially shipped (command + daily cron); diff detection, notifications, import-log table, per-feed refresh frequency pending.

## Surface

| Component | Path |
|---|---|
| Remote service | `packages/ahg-library/src/Services/KbartRemoteService.php` |
| Existing parser | `packages/ahg-library/src/Services/KbartService.php` |
| Vendor controller | `packages/ahg-library/src/Controllers/KbartAdminController.php` |
| Upload controller | `packages/ahg-library/src/Controllers/KbartoController.php` |
| Refresh command | `packages/ahg-library/src/Console/Commands/KbartRefreshFeedsCommand.php` |
| Views | `packages/ahg-library/resources/views/kbart/{index,import,admin-remote,remote-form}.blade.php` |

Scheduler line in `AhgLibraryServiceProvider::boot()`:

```php
$schedule->command('ahg:library-kbart-refresh')
    ->dailyAt('01:00')
    ->withoutOverlapping(60);
```

## Routes (#767)

```
GET  /library-manage/kbart/remote                       -> library.kbart-remote
POST /library-manage/kbart/remote                       -> library.kbart-remote-store
GET  /library-manage/kbart/remote/create                -> library.kbart-remote-create
GET  /library-manage/kbart/remote/{feed}/edit           -> library.kbart-remote-edit
PUT  /library-manage/kbart/remote/{feed}                -> library.kbart-remote-update
DEL  /library-manage/kbart/remote/{feed}                -> library.kbart-remote-destroy
POST /library-manage/kbart/remote/{feed}/refresh        -> library.kbart-remote-refresh
POST /library-manage/kbart/remote/{feed}/toggle         -> library.kbart-remote-toggle
POST /library-manage/kbart/remote/test-url              -> library.kbart-remote-test-url
```

## Database (`library_kbart_feed`)

Columns include: `id`, `name`, `vendor`, `url`, `auth_header`, `active`, `last_fetch_at`, `last_row_count`, `last_error`, timestamps.

## Refresh command

`php artisan ahg:library-kbart-refresh` walks `library_kbart_feed WHERE active = 1`, calls `KbartRemoteService::fetch($feed)`, parses via `KbartService::parseTsv()`, validates, writes staged batch, updates `last_*` columns.

## HTTP fetch defaults

- Connect timeout: 10s
- Read timeout: 60s
- Max redirects: 5
- Max body: 100 MB
- UA: `Heratio-KBART/1.0`

## Gaps vs heratio#768 acceptance

- [ ] Diff detection (titles/coverage/identifiers) vs prior fetch
- [ ] `ahg_notification` integration (added titles, removed titles, fetch failure)
- [ ] `library_kbart_import_log` table for per-fetch history
- [ ] "KBART refresh log" admin page
- [ ] Per-vendor `refresh_frequency` column + per-feed cron
- [ ] Help article inside in-app /help
