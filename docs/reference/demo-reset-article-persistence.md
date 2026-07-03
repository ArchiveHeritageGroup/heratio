# Demo Reset: Article + Read-Count Persistence

**Summary:** The Heratio demo box wipes its DB nightly at 02:00 (`heratio-demo-reset.sh` restores a fixed baseline snapshot), which used to delete any article written since the baseline and roll every article's `view_count` back to the baseline value. A capture/apply pair in the `ahg-articles` package now preserves flagged articles and all read counts across the reset. Admins toggle protection per article from the Manage Articles screen.

## Problem

`/etc/cron.d/heratio-demo-reset` runs `/usr/local/sbin/heratio-demo-reset.sh` at 02:00. It does `zcat /mnt/nas/heratio/demo-baseline/heratio-demo.sql.gz | mysql heratio`, replacing the whole DB (schema + data) with the baseline. Consequences for the blog/articles module:

- Articles created after the baseline snapshot (e.g. new blog posts) are deleted outright.
- Every article's `view_count` reverts to the baseline number, so accumulated reads are lost nightly.

## Design

Two crons bracket the reset (they do NOT modify the vetted reset script):

| Time  | Job | Action |
|-------|-----|--------|
| 01:50 | `php artisan ahg:articles-persist capture` | Snapshot the live DB to `storage/app/demo-extras/blog-state.json` |
| 02:00 | `heratio-demo-reset.sh` (existing) | Restore baseline (unchanged) |
| 02:15 | `php artisan ahg:articles-persist apply` | Re-insert protected articles + restore all read counts |

Snapshot contents (`blog-state.json`, on disk so it survives the DB wipe):

- Full rows for every article flagged `protect_from_reset = 1`, plus their `blog_attachment` and `blog_comment` children.
- `view_count` for **every** article (protected or not) - analytics is preserved even for baseline/demo articles.

`apply()` is self-healing: if the baseline restore recreated `blog_post` without the `protect_from_reset` column, apply re-adds it before writing. It is idempotent (updateOrInsert on posts, delete-then-insert on children), so re-running never duplicates rows.

Protection also re-captures immediately when an admin toggles it or deletes an article, so a change takes effect the same night without waiting for 01:50.

## Admin UI

Manage Articles (`/admin/articles`) gains a **Keep on reset** column with a per-article toggle (shield button). Green "Kept" = survives the reset; grey "Reset" = wiped nightly. Route: `PUT /admin/articles/{id}/protect` (admin-guarded).

## Schema

`blog_post.protect_from_reset TINYINT(1) NOT NULL DEFAULT 0`. Added by migration `2026_07_03_090000_add_protect_from_reset_to_blog_post` and included in the package `install.sql` for fresh installs.

## Ops

- Command: `php artisan ahg:articles-persist {capture|apply}` (run as www-data, never root).
- State file: `storage/app/demo-extras/blog-state.json`.
- Cron reference: `deploy/cron/heratio-articles-persist` -> install to `/etc/cron.d/heratio-articles-persist`.
- Log: `storage/logs/articles-persist.log`.
- Ordering assumption: the 02:00 baseline restore completes within ~15 min. If the baseline grows past that, move apply later or append it to `heratio-demo-reset.sh`.

## Limitation

The first night's already-lost counts cannot be recovered - there was no snapshot before that reset. Protection prevents loss from the next reset onward.
