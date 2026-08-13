#!/bin/bash
# Deploy the public DEMO (/usr/share/nginx/heratio) from GitHub.
# Model: develop+release from heratio-dev -> this pulls `main` into live (pull-only).
# Steps: snapshot DB -> ff-pull -> composer -> build -> migrate -> clear caches -> reload php-fpm
#        -> REBASE the demo baseline (so the 02:00 reset keeps this deploy).
# Run:  sudo /usr/local/sbin/heratio-deploy.sh [branch]   (branch defaults to main)
set -uo pipefail
APP=/usr/share/nginx/heratio
BR="${1:-main}"
PHP=/usr/bin/php8.3
exec {lock}>/var/run/heratio-deploy.lock || true
flock -n "$lock" || { echo "another deploy is running - abort"; exit 1; }

cd "$APP" || { echo "no $APP"; exit 1; }
echo "=== heratio deploy ($BR) $(date) ==="
git config --global --add safe.directory "$APP" 2>/dev/null || true

# Guard: refuse if TRACKED files are modified/staged (untracked files are fine).
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "ABORT: live has uncommitted tracked changes:"; git status --short --untracked-files=no; exit 1
fi

# Safety: snapshot the live DB BEFORE pulling new code. Taken pre-pull so the new
# boot-install (idempotent ALTERs) cannot run during the dump and race it -
# --single-transaction is NOT isolated from concurrent DDL. One retry for safety.
raw="$(grep -m1 '^DB_PASSWORD=' "$APP/.env")"
export MYSQL_PWD="$(printf '%s' "${raw#DB_PASSWORD=}" | tr -d '\r' | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/")"
ts="$(date +%Y%m%d-%H%M%S)"
mkdir -p /mnt/nas/heratio/demo-baseline
dst="/mnt/nas/heratio/demo-baseline/pre-deploy-$ts.sql.gz"
_backup(){ mysqldump --defaults-file=/dev/null -u root --single-transaction --quick --routines --triggers heratio | gzip > "$dst"; }
if _backup; then echo "pre-deploy DB backup saved"
else echo "pre-deploy backup hit a transient error - retrying in 3s..."; sleep 3
  if _backup; then echo "pre-deploy DB backup saved (2nd attempt)"
  else rm -f "$dst"; echo "WARN: pre-deploy backup failed twice - continuing (prior baseline retained)"; fi
fi
find /mnt/nas/heratio/demo-baseline -name 'pre-deploy-*.sql.gz' -mtime +14 -delete 2>/dev/null || true

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
# The URL was https://heratio.theahg.co.za/, which is now a 301 redirect to
# heratio.org and never served this docroot, so the check reported a redirect
# rather than confirming the app was up. /usr/share/nginx/heratio/public is
# served by heratio.org.conf (server_name heratio.org www.heratio.org).
HEALTH_URL="https://heratio.org/"

# Retry rather than check once. A restart (unlike a reload) drops the workers and
# takes a moment to accept connections again, so a single immediate check can
# catch the gap and report a false failure - which under the cascade above would
# skip the baseline rebase on a perfectly good deploy. Up to ~15s, and it exits
# the loop the moment it gets a 200, so a healthy deploy costs nothing.
code=""
for attempt in 1 2 3 4 5 6 7 8 9 10; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$HEALTH_URL")"
    [ "$code" = "200" ] && break
    [ "$attempt" = "10" ] && break
    sleep 1.5
done

if [ "$code" = "200" ]; then
    echo "--- health OK ($code) - rebase demo baseline so 02:00 reset keeps this deploy ---"
    /usr/local/sbin/heratio-demo-snapshot.sh
else
    echo "!!! HEALTH CHECK FAILED: $HEALTH_URL returned $code (expected 200)"
    echo "!!! demo baseline NOT rebased - it still describes the previous release."
    echo "!!! the 02:00 reset will refuse to run while the baseline version does not"
    echo "!!! match the deployed one, which is intended: fix the app, then run"
    echo "!!! /usr/local/sbin/heratio-demo-snapshot.sh once it is healthy."
fi

echo "=== deploy done; live HTTP $code @ $(date) ==="
