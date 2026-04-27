#!/usr/bin/env bash
#
# Heratio overlay install — schema overlay step.
#
# Dumps every table that exists in the REFERENCE Heratio DB but NOT in the TARGET DB
# (no data, no triggers, no routines, schema only) and applies it to the target with
# FOREIGN_KEY_CHECKS=0 and CREATE TABLE IF NOT EXISTS so re-runs are safe.
#
# Idempotent. Non-destructive. Adds tables; never drops or modifies existing ones.
#
# Usage:
#   ./overlay-schema.sh --reference=heratio --target=dam [--apply] [--host=localhost] [--user=root]
#
# Without --apply, prints the list of tables that would be added and exits.
#
# @copyright  Johan Pieterse / Plain Sailing Information Systems
# @license    AGPL-3.0-or-later

set -euo pipefail

REFERENCE="heratio"
TARGET=""
APPLY=0
HOST="localhost"
USER="root"

for arg in "$@"; do
  case "$arg" in
    --reference=*) REFERENCE="${arg#*=}" ;;
    --target=*)    TARGET="${arg#*=}" ;;
    --host=*)      HOST="${arg#*=}" ;;
    --user=*)      USER="${arg#*=}" ;;
    --apply)       APPLY=1 ;;
    -h|--help)
      sed -n '2,18p' "$0" | sed 's/^# \?//'
      exit 0
      ;;
    *)
      echo "Unknown arg: $arg" >&2
      exit 1
      ;;
  esac
done

if [[ -z "$TARGET" ]]; then
  echo "Error: --target is required" >&2
  exit 1
fi
if [[ "$REFERENCE" == "$TARGET" ]]; then
  echo "Error: reference and target are the same DB" >&2
  exit 1
fi

# Common mysql/mysqldump auth — relies on either ~/.my.cnf or MYSQL_PWD env var.
MYSQL_OPTS=(-h "$HOST" -u "$USER")

# Tables that exist in reference but not in target.
MISSING_TABLES=$(
  mysql "${MYSQL_OPTS[@]}" -N -B -e "
    SELECT r.table_name
      FROM information_schema.tables r
      LEFT JOIN information_schema.tables t
        ON t.table_schema = '${TARGET}'
       AND t.table_name   = r.table_name
     WHERE r.table_schema = '${REFERENCE}'
       AND r.table_type   = 'BASE TABLE'
       AND t.table_name IS NULL
     ORDER BY r.table_name"
)

if [[ -z "$MISSING_TABLES" ]]; then
  echo "No missing tables. Target \`${TARGET}\` already has every table that \`${REFERENCE}\` has."
  exit 0
fi

COUNT=$(echo "$MISSING_TABLES" | wc -l)
echo "Reference: ${REFERENCE}"
echo "Target:    ${TARGET}"
echo "Missing tables: ${COUNT}"

if [[ "$APPLY" -eq 0 ]]; then
  echo ""
  echo "$MISSING_TABLES"
  echo ""
  echo "Dry run only. Re-run with --apply to overlay these tables onto ${TARGET}."
  exit 0
fi

# Dump schemas for those tables only.
TMPFILE=$(mktemp /tmp/heratio-overlay.XXXXXX.sql)
trap 'rm -f "$TMPFILE"' EXIT

# shellcheck disable=SC2086
mysqldump "${MYSQL_OPTS[@]}" \
  --no-data --skip-triggers --skip-routines --skip-events \
  --skip-add-drop-table --skip-comments \
  --single-transaction --quick \
  "${REFERENCE}" $(echo "$MISSING_TABLES" | tr '\n' ' ') \
  | sed 's/^CREATE TABLE /CREATE TABLE IF NOT EXISTS /' \
  > "$TMPFILE"

# Apply with FK checks disabled (FK targets may resolve only after all tables land).
{
  echo "SET FOREIGN_KEY_CHECKS = 0;"
  cat "$TMPFILE"
  echo "SET FOREIGN_KEY_CHECKS = 1;"
} | mysql "${MYSQL_OPTS[@]}" --force "${TARGET}"

# Verify.
NEW_COUNT=$(
  mysql "${MYSQL_OPTS[@]}" -N -B -e "
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = '${TARGET}' AND table_type = 'BASE TABLE'"
)
echo ""
echo "Overlay complete. Target \`${TARGET}\` now has ${NEW_COUNT} tables."
