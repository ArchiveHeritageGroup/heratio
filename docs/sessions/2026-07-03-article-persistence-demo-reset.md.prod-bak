# Session Log - Article + Read-Count Persistence Across the Nightly Demo Reset

- **Date:** 2026-07-03
- **Package:** `ahg-articles`
- **Shipped in:** code landed in **v1.154.217** (bundled into the portable-export release commit by a parallel `./bin/release`; this log names the feature the 217 commit message does not).
- **Author:** Johan Pieterse / Plain Sailing Information Systems

## Problem

The demo box resets its DB nightly at 02:00 via `/usr/local/sbin/heratio-demo-reset.sh`, which restores a fixed baseline snapshot (`/mnt/nas/heratio/demo-baseline/heratio-demo.sql.gz`) over the whole `heratio` database. Two data-loss consequences for the articles/blog module:

1. Articles written after the baseline snapshot were deleted outright each night (e.g. blog_post id 24, "AI and the Paradox of Honesty").
2. Every article's `view_count` reverted to the baseline value, so all accumulated reads were lost nightly.

## Solution

A capture/apply pair in `ahg-articles`, driven by two crons that bracket the reset (the vetted reset script is left untouched):

| Time | Job | Action |
|------|-----|--------|
| 01:50 | `php artisan ahg:articles-persist capture` | Snapshot the live DB to `storage/app/demo-extras/blog-state.json` (on disk, survives the wipe): full rows for `protect_from_reset=1` articles + their attachments/comments, and `view_count` for every article. |
| 02:00 | `heratio-demo-reset.sh` (existing) | Restore baseline (unchanged). |
| 02:15 | `php artisan ahg:articles-persist apply` | Re-add the `protect_from_reset` column if the restore dropped it, re-insert protected articles + children, restore every article's read count. |

- Admin UI: a **Keep on reset** toggle column in Manage Articles (`/admin/articles`), route `PUT /admin/articles/{id}/protect` (admin-guarded). Toggling or deleting re-captures immediately, so a change takes effect the same night.
- `apply()` is idempotent (updateOrInsert on posts, delete-then-insert on children) and self-healing (re-adds the column the baseline restore drops).
- View-count preservation applies to **all** articles, not just protected ones.

## Files

- `database/migrations/2026_07_03_090000_add_protect_from_reset_to_blog_post.php`
- `packages/ahg-articles/database/install.sql` (column added for fresh installs)
- `packages/ahg-articles/src/Services/ArticlePersistenceService.php` (capture/apply)
- `packages/ahg-articles/src/Console/PersistArticlesCommand.php` (`ahg:articles-persist`)
- `packages/ahg-articles/src/Services/BlogService.php` (`setProtected`)
- `packages/ahg-articles/src/Controllers/Admin/BlogAdminController.php` (`toggleProtect`)
- `packages/ahg-articles/src/Providers/AhgArticlesServiceProvider.php` (route + command registration)
- `packages/ahg-articles/resources/views/admin/index.blade.php` (toggle column)
- `deploy/cron/heratio-articles-persist` (cron reference)
- `docs/reference/demo-reset-article-persistence.md` (KM doc)

## Deployment (2026-07-03, demo box)

- Migration already applied on prod via the 217 deploy (column present).
- `deploy/cron/heratio-articles-persist` installed to `/etc/cron.d/heratio-articles-persist` (root:root 0644); `cron` restarted.
- blog_post id 24 flagged protected; initial capture run (1 protected, 24 read counts).
- Verified: the exact 02:15 apply command runs clean as www-data, logs to `storage/logs/articles-persist.log`.

## Verification

End-to-end test on heratio-dev: protected article deleted by a simulated reset was fully restored (title, reads, flag); a baseline article's accumulated reads survived a rollback; the dropped `protect_from_reset` column was re-added by `apply`.

## Notes / limitations

- Ordering assumption: the 02:00 baseline restore of `blog_post` completes before 02:15 (the DB restore precedes the ES reindex in the reset script, so this holds even when reindex runs long).
- The first night's already-lost read counts are unrecoverable - no snapshot existed before that reset. Protection guards forward only.
