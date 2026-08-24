#!/bin/bash
# Deploy a live Heratio instance from GitHub (pull-only).
# Model: develop+release from heratio-dev -> this pulls `main` into a live app.
# Steps: snapshot DB -> ff-pull -> composer -> build -> migrate -> clear caches
#        -> restart queue workers -> restart php-fpm -> health check
#        -> on the demo only, REBASE the baseline (so the 02:00 reset keeps this deploy).
#
# Run:  sudo /usr/local/sbin/heratio-deploy.sh [branch]         # the demo (heratio.org)
#       sudo /usr/share/nginx/sasa/deploy/sbin/heratio-deploy.sh  # sasa, resolved from location
#       sudo HERATIO_APP=/usr/share/nginx/sasa /usr/local/sbin/heratio-deploy.sh
#       sudo /usr/local/sbin/heratio-deploy.sh --app=/usr/share/nginx/sasa main
#
# WHY THE TARGET IS RESOLVED RATHER THAN HARDCODED. This file is tracked in the
# repo, so every instance that pulls `main` gets a copy at
# <app>/deploy/sbin/heratio-deploy.sh. It used to hardcode
# APP=/usr/share/nginx/heratio, which meant running sasa's own copy from sasa's
# own directory silently deployed heratio.org instead - pulling into the wrong
# tree, dumping the wrong database, restarting on the wrong health URL and
# re-stamping the prod demo baseline, while leaving sasa untouched. It printed
# "deploy done" and looked like it had worked. Found 2026-08-17.
#
# Precedence: $HERATIO_APP -> --app=DIR -> the app this script lives inside
# (<app>/deploy/sbin/..) -> /usr/share/nginx/heratio, which keeps the installed
# /usr/local/sbin copy behaving exactly as before since it lives outside any app.
set -uo pipefail

BR=main
APP="${HERATIO_APP:-}"
CHECK=0
SKIP_BACKUP=0
for arg in "$@"; do
  case "$arg" in
    --app=*)          APP="${arg#--app=}" ;;
    --check|--dry-run) CHECK=1 ;;   # resolve + report, change nothing
    --no-backup)      SKIP_BACKUP=1 ;;   # proceed even if the pre-deploy dump fails
    -*)               echo "unknown option: $arg"; exit 1 ;;
    *)                BR="$arg" ;;
  esac
done

if [ -z "$APP" ]; then
  _self="$(readlink -f "$0")"
  _cand="$(cd "$(dirname "$_self")/../.." 2>/dev/null && pwd || true)"
  if [ -n "$_cand" ] && [ -f "$_cand/artisan" ] && [ -f "$_cand/.env" ]; then
    APP="$_cand"
  else
    APP=/usr/share/nginx/heratio
  fi
fi
APP="${APP%/}"

# Refuse rather than guess: a wrong target here pulls into the wrong tree and
# dumps the wrong database.
[ -d "$APP" ]        || { echo "ABORT: no such app dir: $APP"; exit 1; }
[ -f "$APP/artisan" ]|| { echo "ABORT: $APP has no artisan - not a Laravel app"; exit 1; }
[ -f "$APP/.env" ]   || { echo "ABORT: $APP has no .env"; exit 1; }

_env(){ grep -m1 "^$1=" "$APP/.env" | cut -d= -f2- | tr -d '\r' \
        | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"; }

DB="$(_env DB_DATABASE)"
APP_URL="$(_env APP_URL)"
[ -n "$DB" ] || { echo "ABORT: DB_DATABASE not set in $APP/.env"; exit 1; }

PHP=/usr/bin/php8.3
INSTANCE="$(basename "$APP")"

# --check exists so the resolved target can be confirmed WITHOUT deploying.
# Without it the only way to find out what this script would touch is to let it
# touch it, which is how the wrong-instance bug stayed invisible.
if [ "$CHECK" = "1" ]; then
  echo "=== heratio deploy --check ==="
  echo "    branch:      $BR"
  echo "    target app:  $APP"
  echo "    instance:    $INSTANCE"
  echo "    database:    $DB"
  echo "    health url:  ${APP_URL:-<unset>}/"
  echo "    lock file:   /var/run/heratio-deploy-$INSTANCE.lock"
  if [ "$APP" = "/usr/share/nginx/heratio" ]; then
    echo "    baseline:    WILL be rebased (this is the demo)"
  else
    echo "    baseline:    not rebased (only the demo has one)"
  fi
  echo "nothing changed."
  exit 0
fi

# Per-instance lock: deploying sasa must not be blocked by, or block, a demo deploy.
exec {lock}>"/var/run/heratio-deploy-$INSTANCE.lock" || true
flock -n "$lock" || { echo "another deploy of $INSTANCE is running - abort"; exit 1; }

cd "$APP" || { echo "no $APP"; exit 1; }
echo "=== heratio deploy ($BR) $(date) ==="
echo "    target app: $APP"
echo "    database:   $DB"
echo "    health url: ${APP_URL:-<unset>}"
git config --global --add safe.directory "$APP" 2>/dev/null || true

# Guard: refuse if TRACKED files are modified/staged (untracked files are fine).
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "ABORT: live has uncommitted tracked changes:"; git status --short --untracked-files=no; exit 1
fi

# Safety: snapshot the live DB BEFORE pulling new code. Taken pre-pull so the new
# boot-install (idempotent ALTERs) cannot run during the dump and race it -
# --single-transaction is NOT isolated from concurrent DDL. One retry for safety.
# Dump with the instance's OWN credentials. This used to export MYSQL_PWD from the
# app's .env but connect as `-u root`, which only worked because heratio.org's app
# happens to run as the root DB user - on sasa (DB user `sasa`) the password and
# the user disagreed, root auth failed with "Access denied ... (using password:
# YES)", both attempts failed, and the deploy carried on with NO pre-deploy backup
# after printing a warning that scrolls past in normal output. Found 2026-08-17.
#
# --no-tablespaces because dumping tablespace info needs the PROCESS privilege,
# which an ordinary app user does not have; without it mysqldump still exits 0 but
# prints an access-denied line that reads like a failure.
export MYSQL_PWD="$(_env DB_PASSWORD)"
DB_USER="$(_env DB_USERNAME)"; DB_USER="${DB_USER:-root}"
ts="$(date +%Y%m%d-%H%M%S)"

# The demo keeps its backups beside its baseline on the NAS; other instances get
# their own directory. Falls back to local disk when the NAS path is absent, so a
# non-demo instance still gets a pre-deploy dump instead of silently skipping one.
BASELINE_DIR="/mnt/nas/$DB/demo-baseline"
if [ -d "$(dirname "$BASELINE_DIR")" ]; then BACKUP_DIR="$BASELINE_DIR"
else BACKUP_DIR="/var/backups/heratio-deploy/$DB"; fi
mkdir -p "$BACKUP_DIR"
dst="$BACKUP_DIR/pre-deploy-$ts.sql.gz"
# umask 077 for the dump window: a database dump contains everything the
# database contains - user rows, session data, and email_setting.smtp_password
# in plaintext. These were being created 0644 on a host carrying ~10 non-root
# service accounts, so every one of them could read every backup (found
# 2026-08-24; 251 existing dumps were world- or group-readable). chmod after
# the fact would still leave a window where the file exists and is readable.
# The rc is captured and re-returned: the caller is `if _backup && _backup_ok`,
# and ending the function on `chmod ... || true` would make it ALWAYS report
# success. _backup_ok would still catch a bad dump via the completion marker,
# but a wrapper must not quietly change the status its caller tests on.
_backup(){ ( umask 077; mysqldump --defaults-file=/dev/null -u "$DB_USER" --no-tablespaces --single-transaction --quick --routines --triggers "$DB" | gzip > "$dst" ); local rc=$?; chmod 600 "$dst" 2>/dev/null || true; return $rc; }
# A dump is only real if it ends with mysqldump's completion marker: a truncated or
# permission-refused dump can still leave a valid-looking .gz behind.
_backup_ok(){ [ -s "$dst" ] && zcat "$dst" 2>/dev/null | tail -5 | grep -q 'Dump completed'; }
if _backup && _backup_ok; then echo "pre-deploy DB backup saved ($DB as $DB_USER -> $dst)"
else echo "pre-deploy backup incomplete - retrying in 3s..."; sleep 3
  if _backup && _backup_ok; then echo "pre-deploy DB backup saved (2nd attempt)"
  else
    rm -f "$dst"
    # Loud, and it stops the deploy: proceeding without a restore point is how a
    # bad migration becomes unrecoverable. Pass --no-backup to override knowingly.
    echo "!!! ABORT: could not take a pre-deploy backup of '$DB' as '$DB_USER'."
    echo "!!! Check that user can dump it, or re-run with --no-backup to skip."
    [ "$SKIP_BACKUP" = "1" ] || exit 1
    echo "!!! --no-backup given: continuing WITHOUT a restore point."
  fi
fi
find "$BACKUP_DIR" -name 'pre-deploy-*.sql.gz' -mtime +14 -delete 2>/dev/null || true

echo "--- fetch + fast-forward to origin/$BR ---"
git fetch origin --prune || { echo "fetch failed"; exit 1; }
git checkout "$BR" 2>/dev/null || { echo "checkout $BR failed"; exit 1; }
if ! git merge-base --is-ancestor HEAD "origin/$BR"; then
  echo "ABORT: HEAD diverges from origin/$BR (not fast-forwardable)"; exit 1
fi
git pull --ff-only origin "$BR" || { echo "pull failed"; exit 1; }
echo "now at: $(git log --oneline -1)"

echo "--- composer + assets ---"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress || { echo "composer failed"; exit 1; }
npm ci --no-progress && npm run build || { echo "npm build failed"; exit 1; }

# runtime-writable dirs back to www-data (composer/npm ran as root)
chown -R www-data:www-data "$APP/storage" "$APP/bootstrap/cache"

# ...and the git metadata + tracked files, for the same reason. composer can
# rewrite composer.json/composer.lock and git itself writes .git as root here,
# which leaves them unwritable by www-data. That is not cosmetic: on 2026-07-10
# a root-run git left 399 files in .git root-owned, www-data could no longer
# update refs/heads/main, HEAD detached, and this script's `git checkout main`
# would then have rolled the app back 315 releases - so deploys moved to manual
# pulls, which skip the baseline rebase below, which let the 02:00 demo reset
# roll the schema back nightly for a month before anyone noticed. One ownership
# slip, five symptoms. Scoped to tracked files + their directories; vendor/ and
# node_modules/ stay root-owned since git does not manage them and chowning
# ~3M files would cost minutes for no benefit.
chown -R www-data:www-data "$APP/.git"
( cd "$APP" && sudo -u www-data git ls-files -z 2>/dev/null | xargs -0 -r chown www-data:www-data ) || true
( cd "$APP" && sudo -u www-data git ls-files -z 2>/dev/null | xargs -0 -r -n1 dirname \
    | sort -u | tr '\n' '\0' | xargs -0 -r chown www-data:www-data ) || true
echo "    ownership: .git + tracked files restored to www-data"

echo "--- migrate + caches (as www-data) ---"
sudo -u www-data $PHP artisan migrate --force || { echo "MIGRATE FAILED - check; baseline NOT rebased"; exit 2; }
sudo -u www-data $PHP artisan optimize:clear >/dev/null 2>&1
sudo -u www-data $PHP artisan storage:link >/dev/null 2>&1 || true

# Queue workers are long-lived PHP processes: they load the application ONCE and
# keep serving jobs from that copy, so a deploy leaves them running the previous
# release indefinitely. Restarting php-fpm does not touch them.
#
# This is not theoretical. On 2026-08-14 an Archivematica fix was deployed,
# verified in the file, and kept failing - because the job that used it runs on
# the queue and dev's worker had been up for nearly NINE DAYS on pre-fix code.
# Anything dispatched to a queue (DIP ingest, normalization, webhooks, exports)
# has the same exposure.
#
# queue:restart does not kill anything mid-job: it sets a restart signal, each
# worker finishes the job in hand and exits cleanly, and systemd starts a fresh
# one. The units are heratio-queue-worker@N / heratio-dev-queue-worker@N /
# sasa-queue-worker@N. A `|| true` because an instance with no queue worker
# running is a normal state, not a deploy failure.
echo "--- restart queue workers (they hold the OLD code until told otherwise) ---"
sudo -u www-data $PHP artisan queue:restart >/dev/null 2>&1 || true

# RESTART, not reload. The pools here run opcache.validate_timestamps=0, so the
# workers never re-stat a PHP file once it is compiled: a graceful reload keeps
# the OLD code in the shared opcache and the site carries on serving the previous
# release. It fails deceptively - `php artisan` from the CLI reads the new files
# and reports everything fine while the browser runs last month's code. That is
# what produced the spurious 405 on the demo on 2026-07-31, and it meant every
# deploy since needed a manual `systemctl restart` afterwards to actually land.
#
# The cost is a brief drop of in-flight requests instead of a graceful drain.
# That is the right trade here: this is a demo host, and a deploy that silently
# does not take effect is worse than a half-second of refused connections.
#
# Cleared first because the manifests are what a new package/provider lands in;
# a stale packages.php or services.php is the classic post-deploy 500.
echo "--- clear compiled manifests + restart php-fpm ---"
rm -f "$APP"/bootstrap/cache/packages.php \
      "$APP"/bootstrap/cache/services.php \
      "$APP"/bootstrap/cache/config.php \
      "$APP"/bootstrap/cache/events.php \
      "$APP"/bootstrap/cache/routes-*.php
systemctl restart php8.3-fpm

# Health check BEFORE the baseline rebase, and the rebase is gated on it.
# It used to run after, which meant a broken deploy was snapshotted as the
# "golden" state the 02:00 reset restores to every night - baking the breakage
# in and re-applying it daily. Checking first turns that into a safe cascade:
# unhealthy deploy -> baseline not rebased -> the reset's version guard sees a
# stale baseline and refuses -> the demo keeps serving current code rather than
# being reset into a known-bad state.
#
# The URL comes from the instance's own APP_URL. It was hardcoded to
# https://heratio.org/, which meant a deploy of any other instance health-checked
# the demo and passed regardless of whether the instance it just deployed was up.
# Before that it was https://heratio.theahg.co.za/, a 301 to heratio.org that
# never served this docroot, so the check reported a redirect instead of
# confirming the app was up.
#
# -L because a healthy instance may legitimately redirect its root: sasa lands on
# /heritage. Following it and expecting 200 checks the app, not the redirect.
HEALTH_URL="${APP_URL:-https://heratio.org}/"

# Retry rather than check once. A restart (unlike a reload) drops the workers and
# takes a moment to accept connections again, so a single immediate check can
# catch the gap and report a false failure - which under the cascade above would
# skip the baseline rebase on a perfectly good deploy. Up to ~15s, and it exits
# the loop the moment it gets a 200, so a healthy deploy costs nothing.
code=""
for attempt in 1 2 3 4 5 6 7 8 9 10; do
    code="$(curl -sL -o /dev/null -w '%{http_code}' "$HEALTH_URL")"
    [ "$code" = "200" ] && break
    [ "$attempt" = "10" ] && break
    sleep 1.5
done

if [ "$code" = "200" ]; then
    # The baseline rebase belongs to the demo alone: only heratio.org is reset from
    # a golden snapshot at 02:00. heratio-demo-snapshot.sh reads
    # /usr/share/nginx/heratio/.env directly and aborts unless the DB is `heratio`,
    # so calling it from another instance was never destructive - just misleading,
    # since it would re-stamp the DEMO baseline during someone else's deploy.
    if [ "$APP" = "/usr/share/nginx/heratio" ] && [ -x /usr/local/sbin/heratio-demo-snapshot.sh ]; then
        echo "--- health OK ($code) - rebase demo baseline so 02:00 reset keeps this deploy ---"
        /usr/local/sbin/heratio-demo-snapshot.sh
    else
        echo "--- health OK ($code) - $INSTANCE has no demo baseline to rebase (skipped) ---"
    fi
else
    echo "!!! HEALTH CHECK FAILED: $HEALTH_URL returned $code (expected 200)"
    echo "!!! demo baseline NOT rebased - it still describes the previous release."
    echo "!!! the 02:00 reset will refuse to run while the baseline version does not"
    echo "!!! match the deployed one, which is intended: fix the app, then run"
    echo "!!! /usr/local/sbin/heratio-demo-snapshot.sh once it is healthy."
fi

echo "=== deploy done; live HTTP $code @ $(date) ==="
