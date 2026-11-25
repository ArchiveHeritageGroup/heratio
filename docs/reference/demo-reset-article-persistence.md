# Demo Reset: Article + Read-Count Persistence

**Summary:** The demo reset on heratio.org keeps every article, comment, link, attachment and read count. Since 10 July 2026 the reset script itself dumps the articles (`blog_*`) tables before it restores the baseline and loads them back afterwards. No separate cron job or artisan command is involved.

## How it works now

- `/etc/cron.d/heratio-demo-reset` runs `/usr/local/sbin/heratio-demo-reset.sh` weekly, on Sunday at 02:20 (source in git: `deploy/sbin/heratio-demo-reset.sh`).
- Before the restore, the script dumps the `blog_*` tables to `articles-live.sql` and checks the dump.
- After the restore, it loads that dump back. The log line is "articles preserved (active/growing)".
- If the dump fails its check, the script keeps the baseline articles for that run rather than loading a partial dump ("articles snapshot FAILED - keeping baseline articles this run").

All articles survive the reset, whether or not they are flagged. The "Keep on reset" toggle on Manage Articles (`blog_post.protect_from_reset`) still stores its flag, but the reset no longer reads it.

## Retired (July 2026)

The first design ran two cron jobs around the old nightly reset: `ahg:articles-persist capture` at 01:50 and `apply` at 02:15, with a state file in `storage/app/demo-extras/blog-state.json`. It was replaced on 10 July 2026 by the dump-and-reload inside the reset script.

- v1.154.291 removed the `ahg:articles-persist` command.
- v1.154.292 removed `ArticlePersistenceService`.
- The unused cron file `deploy/cron/heratio-articles-persist` and its config-drift entry were removed in November 2026. It was never installed after July, and would have failed because its command no longer exists.

## Checking it after a reset

Look for "articles snapshot OK" and "articles preserved (active/growing)" in the reset log for that Sunday.
