#!/bin/bash
# Weekly (Sunday 02:20) demo reset: restore the live `heratio` DB from the demo baseline, wiping any
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
UPGRADE=0
APP_VER="$(grep -m1 '"version"' /usr/share/nginx/heratio/version.json 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"
BASE_VER="$(head -n1 /mnt/nas/heratio/demo-baseline/heratio-demo.version 2>/dev/null | tr -d '[:space:]' || true)"
if [ -z "$APP_VER" ]; then
  echo "WARN: could not read the deployed version - skipping the staleness check"
elif [ -z "$BASE_VER" ]; then
  echo "ABORT: baseline carries no version stamp, so it cannot be checked against deployed $APP_VER."
  echo "       It predates the stamping change. Run heratio-demo-snapshot.sh to adopt the current DB as the baseline."
  exit 1
elif [ "$BASE_VER" != "$APP_VER" ] && [ "$(printf '%s\n%s\n' "$BASE_VER" "$APP_VER" | sort -V | tail -n1)" = "$BASE_VER" ]; then
  # Baseline NEWER than the code (the code was rolled back): migrations cannot
  # bring a schema down, so this is still a hard stop.
  echo "ABORT: baseline is for $BASE_VER but the deployed code is OLDER ($APP_VER)."
  echo "       Restoring it would put a newer schema under older code. Snapshot again once the deploy is settled."
  exit 1
elif [ "$BASE_VER" != "$APP_VER" ]; then
  # Baseline OLDER than the code. This used to abort, and every release then needed
  # a manual re-snapshot or the reset stopped for good (it aborted every night from
  # 12 to 16 Nov 2026). Now: restore, bring the schema up to the deployed code the
  # same way a deploy does (hooks/post-merge runs `migrate --force`; package tables
  # self-install at boot), check the site, and adopt the result as the new baseline.
  UPGRADE=1
  echo "baseline is for $BASE_VER, deployed code is $APP_VER - will migrate after the restore and re-snapshot"
else
  echo "baseline version $BASE_VER matches deployed code - proceeding"
fi

# --- Build the scratch copy while the site stays up (heratio#1569) ---
# The baseline loads into a scratch database (heratio-demo-swap-restore.sh
# --prepare); nothing live changes, so requests, the scheduler and the queue keep
# running. This is most of the reset's time: the whole reset used to run in
# maintenance mode, which answered every URL - robots.txt included - with 503 for
# about 7 minutes a night, and Google pauses crawling on a robots.txt 5xx. Only
# the swap below needs the site down. A failed load aborts here, site still up.
if /usr/local/sbin/heratio-demo-swap-restore.sh --prepare "$BASE" "$DB"; then
  echo "scratch copy prepared while the site stayed up"
else
  echo "scratch load FAILED - live unchanged, site never went down"
  echo "[$(date)] === demo reset ABORTED (scratch load failed) ==="
  exit 1
fi

systemctl stop heratio-queue-worker@1.service 2>/dev/null || true
# Maintenance now covers only the snapshots and the swap (heratio#1569).
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
  && echo "app in maintenance mode for the swap" || echo "artisan down FAILED - restore may race web requests"
# Maintenance mode LEAKS for up to two minutes without this (CH-000175, proven on the
# 25 Sep run). php-fpm caches stat() results for realpath_cache_ttl, 120s on this host,
# so a long-lived worker keeps missing the maintenance file `artisan down` just created
# and serves requests normally. That night nginx logged 427 x 503 alongside 31 x 200 in
# the same seconds - /glam/browse served 23 times - and each of those 200s booted the
# app, whose audit-trail provider re-created the ahg_audit_log triggers at 02:00:47.58
# and 02:00:48.00, mid-restore, which is what the dump's CREATE TRIGGER then collided
# with. A graceful reload recycles the workers so every one of them sees the file at
# once; the short sleep lets requests already in flight finish before the dump starts.
if systemctl reload php8.3-fpm 2>/dev/null; then
    echo "php-fpm reloaded - all workers now see maintenance mode"
else
    echo "php-fpm reload FAILED - leaked requests may boot the app during the restore"
fi
sleep 5
_reset_release() {
  sudo -u www-data /usr/bin/php8.3 "$APP_DIR/artisan" up >/dev/null 2>&1 || rm -f "$APP_DIR/storage/framework/down" "$APP_DIR/storage/framework/maintenance.php"
  flock -u 9 2>/dev/null || true
}
# An exit before the swap also drops the prepared scratch copy (live is unchanged).
_reset_abort() {
  /usr/local/sbin/heratio-demo-swap-restore.sh --discard "$DB" >/dev/null 2>&1 || true
  _reset_release
}
trap _reset_abort EXIT
# Wait for detached artisan processes to finish (CH-000175). The scheduler lock only
# covers schedule:run itself: a ->runInBackground() event runs as a detached
# "(artisan cmd ; artisan schedule:finish)" that outlives the tick, and each of those
# is a full app boot. ahg:optimize-models is hourly, so it fires at 00:00 UTC - the
# same minute as this reset. On 22 Sep 2026 one such boot landed mid-restore, the
# audit-trail provider found ahg_audit_log without its triggers and re-created them,
# and the dump's own CREATE TRIGGER died on "ERROR 1359: Trigger already exists".
# Matched by working directory (the scheduler's relative 'artisan') or by the absolute
# path (cron.d entries), so heratio-dev and SASA processes do not count.
_app_artisan_pids() {
  local p
  pgrep -f "$APP_DIR/artisan"
  for p in $(pgrep -f artisan); do
    [ "$(readlink "/proc/$p/cwd" 2>/dev/null)" = "$APP_DIR" ] && echo "$p"
  done
}
_app_artisan_busy() { [ -n "$(_app_artisan_pids)" ]; }
_waited=0
while _app_artisan_busy; do
  if [ "$_waited" -ge 600 ]; then
    echo "ABORT: artisan processes for $APP_DIR still running after 600s - not restoring under them:"
    for p in $(_app_artisan_pids | sort -u); do ps -o pid=,etime=,args= -p "$p"; done
    systemctl start heratio-queue-worker@1.service 2>/dev/null || true
    echo "[$(date)] === demo reset ABORTED (app busy, nothing restored) ==="
    exit 1
  fi
  sleep 5; _waited=$((_waited + 5))
done
[ "$_waited" -gt 0 ] && echo "waited ${_waited}s for background artisan processes to finish"

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
# Data only, as INSERT IGNORE - no DROP/CREATE (CH-000139). A DROP-then-CREATE
# re-apply left a moment with the table missing; a live scheduler tick's
# CronRunTrackerService::ensureTable() re-created it in that gap and the CREATE
# died on ERROR 1050, so no history was ever restored. The restored baseline
# already has these tables, so the re-apply truncates and refills them in place.
# 0600: ahg_cron_run.output can hold anything a job printed.
if (umask 077; mysqldump --defaults-file=/dev/null -u root --single-transaction --no-tablespaces --no-create-info --insert-ignore "$DB" $CRON_TABLES > "$CRON_DUMP.tmp") 2>>"$LOG" \
   && grep -q 'Dumping data for table `ahg_cron_run`' "$CRON_DUMP.tmp"; then
  mv "$CRON_DUMP.tmp" "$CRON_DUMP"; CRON_OK=1; echo "cron-monitoring snapshot OK ($(wc -l < "$CRON_DUMP") lines)"
else
  rm -f "$CRON_DUMP.tmp"; echo "cron-monitoring snapshot FAILED - expect false missed-run alerts after this reset"
fi

# The pre-restore trigger sweep is gone (heratio#1569). It was for the old streamed
# restore. The swap drops live's triggers in the same statement as its RENAME, and
# a sweep before it left nothing for the swap to put back if the swap failed.

# A failed restore is fatal. This used to log "restore FAILED" and carry straight on
# through the truncate, the article re-apply, the onboard and the help re-ingest,
# then finish with "=== demo reset done ===". A run that reports success it did not
# achieve is worse than one that stops: it leaves a half-applied database looking
# like a completed reset, and the next person to run heratio-demo-snapshot.sh would
# freeze that state as the demo's permanent baseline.
#
# Every CREATE TRIGGER in the stream gets a DROP TRIGGER IF EXISTS in front of it
# (CH-000175). The pre-restore sweep clears the triggers that exist when the restore
# STARTS; it cannot help with one that appears while the dump is replaying, and on
# 22 and 23 Sep 2026 one did - twice, at the same line 2543, with no artisan process
# running either night, so the culprit is still unidentified. Dropping immediately
# before each create makes the restore immune to it whoever the writer turns out to
# be. The `;;` matches the DELIMITER mysqldump sets around trigger blocks, and the
# ^ anchor keeps row data that merely contains the same text untouched.
if /usr/local/sbin/heratio-demo-swap-restore.sh --swap "$DB"; then
    trap _reset_release EXIT   # swapped: nothing left to discard
    echo "restore OK"
( cd /usr/share/nginx/heratio && sudo -u www-data php artisan ahg:cron-seed >/dev/null 2>&1 && echo "cron schedules seeded (missing rows only)" ) || echo "WARNING: ahg:cron-seed failed"
else
    echo "restore FAILED - ABORTING the rest of the reset."
    # Who wrote a trigger, and when? CREATED is the evidence the last two failures
    # lacked. Deliberately NOT logging processlist INFO: query text carries row
    # values, and audit/PII values in an operator log is CH-000135 all over again.
    mysql --defaults-file=/dev/null -u root -t "$DB" -e \
      "SELECT trigger_name, event_object_table, created FROM information_schema.triggers WHERE trigger_schema = '$DB';" 2>&1 || true
    mysql --defaults-file=/dev/null -u root -t "$DB" -e \
      "SELECT id, user, host, db, command, time, state FROM information_schema.processlist WHERE db = '$DB';" 2>&1 || true
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

# Older baseline: bring the schema up to the deployed code while still in
# maintenance, so no request runs current code against the old schema.
MIGRATED=0
if [ "$UPGRADE" = "1" ]; then
  if ( cd "$APP_DIR" && sudo -u www-data /usr/bin/php8.3 artisan migrate --force --no-interaction ); then
    MIGRATED=1; echo "schema migrated from $BASE_VER to $APP_VER"
  else
    echo "WARNING: migrate --force failed after restoring a $BASE_VER baseline under $APP_VER code - NOT re-snapshotting; check the schema"
  fi
fi

# Restore finished: let requests and the scheduler back in before the long onboard.
_reset_release
trap - EXIT
echo "app back online"

# Re-apply the live Articles/blog section over the baseline (excludes it from the reset).
if [ "$ART_OK" = "1" ] && [ -s "$ART_DUMP" ]; then
  if mysql --defaults-file=/dev/null -u root "$DB" < "$ART_DUMP"; then echo "articles preserved (active/growing)"; else echo "articles re-apply FAILED"; fi
fi
if [ "$CRON_OK" = "1" ] && [ -s "$CRON_DUMP" ]; then
  # One session: truncate, then refill. INSERT IGNORE lets a row a racing tick
  # wrote in between survive instead of aborting the load on a duplicate key.
  # ponytail: a row a tick writes between the baseline restore and this line is
  # lost to the TRUNCATE - at most one run record from inside the reset window.
  # Upgrade path: take the scheduler lock reliably instead of after 120s.
  if { for t in $CRON_TABLES; do echo "TRUNCATE TABLE \`$t\`;"; done; cat "$CRON_DUMP"; } | mysql --defaults-file=/dev/null -u root "$DB"; then echo "cron-monitoring history preserved"; else echo "cron-monitoring re-apply FAILED - expect false missed-run alerts"; fi
  rm -f "$CRON_DUMP"   # a snapshot for the restore only; do not leave job output lying around
fi

systemctl start heratio-queue-worker@1.service 2>/dev/null || true
if [ "${1:-}" != "--no-reindex" ]; then
  sudo -u www-data /usr/bin/php8.3 /usr/share/nginx/heratio/artisan ahg:onboard-atom-db --skip-migrate --fresh-index --no-interaction >/dev/null 2>&1 \
    && echo "onboard (closure+index+classify+facets) OK" || echo "onboard failed"
fi
sudo -u www-data /usr/bin/php8.3 /usr/share/nginx/heratio/artisan ahg:help-ingest-all >/dev/null 2>&1 \
  && echo "help re-ingest OK" || echo "help re-ingest failed"

# Adopt the migrated state as the baseline, so the next reset matches the code.
# Only after a clean migrate AND a healthy site: a snapshot freezes whatever is
# there, and a broken state frozen as the baseline would come back every week.
if [ "$MIGRATED" = "1" ]; then
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 https://heratio.org/ || true)"
  if [ "$code" = "200" ] && sudo -u www-data /usr/bin/php8.3 "$APP_DIR/artisan" about --only=environment >/dev/null 2>&1; then
    /usr/local/sbin/heratio-demo-snapshot.sh && echo "baseline re-taken for $APP_VER" || echo "WARNING: re-snapshot failed - next reset migrates again"
  else
    echo "WARNING: site not healthy after migrate (home=$code) - NOT re-snapshotting"
  fi
fi
echo "[$(date)] === demo reset done ==="