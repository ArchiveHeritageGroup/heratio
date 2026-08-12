#!/bin/bash
# Capture the live `heratio` DB as the demo BASELINE (the "golden" state the nightly
# reset restores to). RUN THIS after any legitimate change you want to keep on the
# demo (a deploy that runs migrations, new showcase content) - otherwise the 02:00
# reset will revert it. Does NOT touch heratio-dev.
set -euo pipefail
ENV=/usr/share/nginx/heratio/.env
OUT=/mnt/nas/heratio/demo-baseline
mkdir -p "$OUT"
raw="$(grep -m1 '^DB_PASSWORD=' "$ENV")"
export MYSQL_PWD="$(printf '%s' "${raw#DB_PASSWORD=}" | tr -d '\r' | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/")"
DB="$(grep -m1 '^DB_DATABASE=' "$ENV" | cut -d= -f2- | tr -d '\r')"
[ "$DB" = "heratio" ] || { echo "SAFETY: live DB is '$DB', expected heratio - abort"; exit 1; }
ts="$(date +%Y%m%d-%H%M%S)"
mysqldump --defaults-file=/dev/null -u root --single-transaction --quick --routines --triggers "$DB" \
  | gzip > "$OUT/heratio-demo.sql.gz.tmp"
mv "$OUT/heratio-demo.sql.gz.tmp" "$OUT/heratio-demo.sql.gz"
cp "$OUT/heratio-demo.sql.gz" "$OUT/heratio-demo-$ts.sql.gz"

# Stamp the baseline with the app version it was taken from. heratio-demo-reset.sh
# refuses to restore a baseline whose version does not match the deployed code -
# without this stamp a stale baseline silently rolls the schema back under newer
# code, which went unnoticed from 2026-07-10 until it was found on 2026-08-12.
VER="$(grep -m1 '"version"' /usr/share/nginx/heratio/version.json 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || echo unknown)"
printf '%s\n' "$VER" > "$OUT/heratio-demo.version"
echo "baseline stamped for version $VER"
find "$OUT" -name 'heratio-demo-*.sql.gz' -mtime +14 -delete 2>/dev/null || true
echo "[$(date)] baseline updated: $OUT/heratio-demo.sql.gz ($(du -h "$OUT/heratio-demo.sql.gz" | cut -f1))"
