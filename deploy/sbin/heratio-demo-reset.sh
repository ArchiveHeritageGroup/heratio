#!/bin/bash
# Nightly demo reset: restore the live `heratio` DB from the demo baseline, wiping any
# visitor changes back to the golden state. heratio-dev (heratio_dev) is NOT touched.
#
# The Articles/blog section is EXCLUDED from the reset (active, growing content): its
# tables are dumped BEFORE the baseline restore and re-applied AFTER, so articles,
# comments, links and their files persist and grow independently of the baseline.
#
# Pass --no-reindex to skip the ES rebuild.
set -uo pipefail
ENV=/usr/share/nginx/heratio/.env
BASE=/mnt/nas/heratio/demo-baseline/heratio-demo.sql.gz
LOG=/var/log/heratio-demo-reset.log
ART_DIR=/var/lib/heratio
ART_TABLES="blog_post blog_attachment blog_comment blog_post_link"
# Cron monitoring history. The restore used to roll ahg_cron_run back to the
# baseline dump time, so cron:check-missed-runs then read a stale finished_at
# and raised false "missed run" alerts for every high-priority command - 13 of
# them between 09-12 and 09-17. The jobs had run; their records were rewound.
CRON_TABLES="ahg_cron_run ahg_cron_missed_run cron_schedule"
exec >>"$LOG" 2>&1
echo "[$(date)] === demo reset start ==="
[ -f "$BASE" ] || { echo "no baseline at $BASE - abort"; exit 1; }
raw="$(grep -m1 '^DB_PASSWORD=' "$ENV")"
export MYSQL_PWD="$(printf '%s' "${raw#DB_PASSWORD=}" | tr -d '\r' | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/")"
DB="$(grep -m1 '^DB_DATABASE=' "$ENV" | cut -d= -f2- | tr -d '\r')"
[ "$DB" = "heratio" ] || { echo "SAFETY: live DB is '$DB', expected heratio - abort"; exit 1; }

# --- Staleness guard: refuse to restore a baseline older than the deployed code ---
# The baseline is a FULL dump, so restoring an old one rolls the SCHEMA back too.
# Between 2026-07-10 and 2026-08-12 the baseline went unrefreshed while releases
# kept shipping, so every night this reset reverted prod's schema by a month and
# it only ever recovered because a deploy happened to re-run migrations. On a day
# with no deploy, live code ran against a month-old database.
#
# Refusing is the safe failure: the demo keeps a day of visitor changes, which is
# cosmetic, instead of serving current code against a schema that predates it.
# Refresh with /usr/local/sbin/heratio-demo-snapshot.sh after a deploy.
APP_VER="$(grep -m1 '"version"' /usr/share/nginx/heratio/version.json 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"
BASE_VER="$(head -n1 /mnt/nas/heratio/demo-baseline/heratio-demo.version 2>/dev/null | tr -d '[:space:]' || true)"
if [ -z "$APP_VER" ]; then
  echo "WARN: could not read the deployed version - skipping the staleness check"
elif [ -z "$BASE_VER" ]; then
  echo "ABORT: baseline carries no version stamp, so it cannot be checked against deployed $APP_VER."
  echo "       It predates the stamping change. Run heratio-demo-snapshot.sh to adopt the current DB as the baseline."
  exit 1
elif [ "$BASE_VER" != "$APP_VER" ]; then
  echo "ABORT: baseline is for $BASE_VER but the deployed code is $APP_VER."
  echo "       Restoring it would roll the schema back under newer code. Run heratio-demo-snapshot.sh after verifying live is healthy."
  exit 1
else
  echo "baseline version $BASE_VER matches deployed code - proceeding"
fi

# --- Preserve the Articles/blog section (active/growing) across the reset ---
# Snapshot the live article tables BEFORE the restore. Only trust the dump if it
# validates; otherwise skip the re-apply so a bad dump degrades to baseline articles
# rather than wiping them.
mkdir -p "$ART_DIR"
ART_DUMP="$ART_DIR/articles-live.sql"
ART_OK=0
if mysqldump --defaults-file=/dev/null -u root --single-transaction --no-tablespaces "$DB" $ART_TABLES > "$ART_DUMP.tmp" 2>>"$LOG" \
   && grep -q 'CREATE TABLE `blog_post`' "$ART_DUMP.tmp" \
   && grep -q 'CREATE TABLE `blog_attachment`' "$ART_DUMP.tmp"; then
  mv "$ART_DUMP.tmp" "$ART_DUMP"; ART_OK=1; echo "articles snapshot OK ($(wc -l < "$ART_DUMP") lines)"
else
  rm -f "$ART_DUMP.tmp"; echo "articles snapshot FAILED - keeping baseline articles this run"
fi
CRON_DUMP="$ART_DIR/cron-monitoring-live.sql"
CRON_OK=0
if mysqldump --defaults-file=/dev/null -u root --single-transaction --no-tablespaces "$DB" $CRON_TABLES > "$CRON_DUMP.tmp" 2>>"$LOG" \
   && grep -q 'CREATE TABLE `ahg_cron_run`' "$CRON_DUMP.tmp"; then
  mv "$CRON_DUMP.tmp" "$CRON_DUMP"; CRON_OK=1; echo "cron-monitoring snapshot OK ($(wc -l < "$CRON_DUMP") lines)"
else
  rm -f "$CRON_DUMP.tmp"; echo "cron-monitoring snapshot FAILED - expect false missed-run alerts after this reset"
fi

systemctl stop heratio-queue-worker@1.service 2>/dev/null || true
# Nothing may boot the app while the dump is replaying. Heratio packages auto-install
# their tables at boot (CREATE TABLE IF NOT EXISTS from install.sql), so a request or a
# scheduler tick landing between the dump's DROP TABLE and its CREATE TABLE recreates
# the table and the dump's CREATE then fails. That is what happened on 15 Sep 2026:
# "ERROR 1050: Table 'ahg_io_funding' already exists" at line 5311.
# - web: artisan down makes public/index.php serve the maintenance page BEFORE the app
#   boots, so no provider runs;
# - scheduler: the cron entry takes /run/lock/heratio-schedule.lock with flock -n, so
#   holding that lock here makes every tick skip until we release it.
# The trap undoes both on every exit, including the abort path below.
APP_DIR=/usr/share/nginx/heratio
exec 9>/run/lock/heratio-schedule.lock
flock -w 120 9 || echo "scheduler lock not acquired in 120s - restore may race a running tick"
sudo -u www-data /usr/bin/php8.3 "$APP_DIR/artisan" down --retry=60 >/dev/null 2>&1 \
  && echo "app in maintenance mode for the restore" || echo "artisan down FAILED - restore may race web requests"
_reset_release() {
  sudo -u www-data /usr/bin/php8.3 "$APP_DIR/artisan" up >/dev/null 2>&1 || rm -f "$APP_DIR/storage/framework/down" "$APP_DIR/storage/framework/maintenance.php"
  flock -u 9 2>/dev/null || true
}
trap _reset_release EXIT
# Clear any triggers still attached before restoring. Trigger names are unique per
# SCHEMA, not per table, so a single trigger surviving from a half-applied previous
# run makes the dump's CREATE TRIGGER fail and takes the whole restore down with it.
# That is what happened on 28 Aug 2026: "ERROR 1359: Trigger already exists" on
# ahg_audit_log_no_update_chained. Dropping first is safe because the dump recreates
# every trigger it needs.
# Distinguish "no triggers" from "the sweep could not run" - a sweep that fails
# quietly and calls itself skipped is how the restore hits the error it is meant
# to prevent.
_TRG_SQL="$(mysql --defaults-file=/dev/null -u root -N -B "$DB" -e \
  "SELECT CONCAT('DROP TRIGGER IF EXISTS \`', trigger_name, '\`;') FROM information_schema.triggers WHERE trigger_schema = '$DB';")" || _TRG_SQL="__FAILED__"
if [ "$_TRG_SQL" = "__FAILED__" ]; then
    echo "pre-restore trigger sweep COULD NOT RUN - the restore may fail on 'Trigger already exists'"
elif [ -z "$_TRG_SQL" ]; then
    echo "pre-restore: no triggers to clear"
elif printf '%s\n' "$_TRG_SQL" | mysql --defaults-file=/dev/null -u root "$DB"; then
    echo "pre-restore: dropped $(printf '%s\n' "$_TRG_SQL" | grep -c 'DROP TRIGGER') trigger(s)"
else
    echo "pre-restore trigger sweep FAILED - the restore may fail on 'Trigger already exists'"
fi

# A failed restore is fatal. This used to log "restore FAILED" and carry straight on
# through the truncate, the article re-apply, the onboard and the help re-ingest,
# then finish with "=== demo reset done ===". A run that reports success it did not
# achieve is worse than one that stops: it leaves a half-applied database looking
# like a completed reset, and the next person to run heratio-demo-snapshot.sh would
# freeze that state as the demo's permanent baseline.
if zcat "$BASE" | mysql --defaults-file=/dev/null -u root "$DB"; then
    echo "restore OK"
else
    echo "restore FAILED - ABORTING the rest of the reset."
    echo "       ${DB} may now be half-applied. Check it before trusting the demo,"
    echo "       and do NOT run heratio-demo-snapshot.sh until it is verified healthy."
    systemctl start heratio-queue-worker@1.service 2>/dev/null || true
    echo "[$(date)] === demo reset ABORTED (restore failed) ==="
    exit 1
fi
# ahg_error_log is ephemeral operational diagnostics, not demo content. The baseline
# dump has months-old error rows baked in that otherwise resurrect every night; wipe
# it so the demo's error log starts each day clean (real same-day errors still log).
mysql --defaults-file=/dev/null -u root "$DB" -e "TRUNCATE TABLE ahg_error_log;" 2>/dev/null \
  && echo "ahg_error_log truncated" || echo "ahg_error_log truncate skipped"

# Restore finished: let requests and the scheduler back in before the long onboard.
_reset_release
trap - EXIT
echo "app back online"

# Re-apply the live Articles/blog section over the baseline (excludes it from the reset).
if [ "$ART_OK" = "1" ] && [ -s "$ART_DUMP" ]; then
  if mysql --defaults-file=/dev/null -u root "$DB" < "$ART_DUMP"; then echo "articles preserved (active/growing)"; else echo "articles re-apply FAILED"; fi
fi
if [ "$CRON_OK" = "1" ] && [ -s "$CRON_DUMP" ]; then
  if mysql --defaults-file=/dev/null -u root "$DB" < "$CRON_DUMP"; then echo "cron-monitoring history preserved"; else echo "cron-monitoring re-apply FAILED - expect false missed-run alerts"; fi
fi

systemctl start heratio-queue-worker@1.service 2>/dev/null || true
if [ "${1:-}" != "--no-reindex" ]; then
  sudo -u www-data /usr/bin/php8.3 /usr/share/nginx/heratio/artisan ahg:onboard-atom-db --skip-migrate --fresh-index --no-interaction >/dev/null 2>&1 \
    && echo "onboard (closure+index+classify+facets) OK" || echo "onboard failed"
fi
sudo -u www-data /usr/bin/php8.3 /usr/share/nginx/heratio/artisan ahg:help-ingest-all >/dev/null 2>&1 \
  && echo "help re-ingest OK" || echo "help re-ingest failed"
echo "[$(date)] === demo reset done ==="
