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

echo "--- reload php-fpm (flush opcache, graceful) ---"
systemctl reload php8.3-fpm

echo "--- rebase demo baseline so 02:00 reset keeps this deploy ---"
/usr/local/sbin/heratio-demo-snapshot.sh

code="$(curl -s -o /dev/null -w '%{http_code}' https://heratio.theahg.co.za/)"
echo "=== deploy done; live HTTP $code @ $(date) ==="
