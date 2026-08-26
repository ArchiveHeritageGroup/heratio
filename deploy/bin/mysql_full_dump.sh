#!/bin/bash
# MySQL full dump to TrueNAS (192.168.0.111)
# Runs daily via cron — keeps 30 days of compressed backups

set -euo pipefail

DATE=$(date +%F_%H-%M)
NAS_DIR="/mnt/nas/heratio/backups/mysql"
LOCAL_FALLBACK="/home/backupuser/mysql_backups"
LOG="/var/log/mysql-backup.log"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG"; }

# Use NAS if mounted, otherwise fall back to local
if mountpoint -q /mnt/nas/heratio 2>/dev/null; then
    BACKUP_DIR="$NAS_DIR"
else
    BACKUP_DIR="$LOCAL_FALLBACK"
    log "WARNING: NAS not mounted, dumping to local fallback"
fi

mkdir -p "$BACKUP_DIR"

DUMP_FILE="$BACKUP_DIR/full_mysql_dump_$DATE.sql.gz"

log "Starting MySQL dump to $DUMP_FILE"

# The large ahg_ai.knowledge_chunks table (embeddings) exceeds the server's
# max_execution_time (300s) during a full dump, aborting it mid-run. This
# mysqldump build has no --init-command, so lift the cap for the dump window
# and restore it on exit (trap covers failure paths too). max_execution_time
# caps only read-only SELECTs and only affects connections opened after the
# change, so live app sessions are unaffected.
PREV_MET=$(mysql --defaults-file=/root/.my.cnf -N -e "SELECT @@GLOBAL.max_execution_time" 2>/dev/null || echo "")
PREV_NWT=$(mysql --defaults-file=/root/.my.cnf -N -e "SELECT @@GLOBAL.net_write_timeout" 2>/dev/null || echo "")
PREV_NRT=$(mysql --defaults-file=/root/.my.cnf -N -e "SELECT @@GLOBAL.net_read_timeout" 2>/dev/null || echo "")
restore_globals(){
    [ -n "$PREV_MET" ] && mysql --defaults-file=/root/.my.cnf -e "SET GLOBAL max_execution_time=$PREV_MET" 2>/dev/null || true
    [ -n "$PREV_NWT" ] && mysql --defaults-file=/root/.my.cnf -e "SET GLOBAL net_write_timeout=$PREV_NWT" 2>/dev/null || true
    [ -n "$PREV_NRT" ] && mysql --defaults-file=/root/.my.cnf -e "SET GLOBAL net_read_timeout=$PREV_NRT" 2>/dev/null || true
}
trap restore_globals EXIT
# max_execution_time: the large ahg_ai embeddings table exceeds the 300s cap.
# net_read/write_timeout: a transient NAS/gzip write stall can otherwise get the
# dump's connection dropped mid-table ("Lost connection ... at row" => rc=3, the
# 2026-07-05 failure). All three affect only connections opened after the change
# (mysqldump opens after), so live app sessions are unaffected. Restored on exit.
mysql --defaults-file=/root/.my.cnf -e "SET GLOBAL max_execution_time=0" 2>/dev/null || true
mysql --defaults-file=/root/.my.cnf -e "SET GLOBAL net_write_timeout=3600" 2>/dev/null || true
mysql --defaults-file=/root/.my.cnf -e "SET GLOBAL net_read_timeout=3600" 2>/dev/null || true
log "Lifted max_execution_time (was ${PREV_MET}ms) + net_read/write_timeout (${PREV_NRT}/${PREV_NWT}s -> 3600s) for dump; will restore on exit"

MIN_BYTES=1000000
NOTIFY_DIR="/var/spool/workbench/notifications"

# Retry once: the rc=3 "Lost connection ... at row" failures are transient
# (a write stall dropping the dump connection), so a second attempt usually
# succeeds instead of losing the whole day. mysqldump's stderr is captured so
# the next genuine failure records WHY, not just an exit code.
ERRFILE=$(mktemp)
# Durable copy of mysqldump's stderr, kept ONLY when an attempt fails.
# $ERRFILE is a mktemp that the next attempt truncates and that used to be
# rm -f'd BEFORE the failure branch below ever read it - so the one artefact
# naming the offending table was destroyed on every failure since June, and
# the alert fell back to guessing "check /root/.my.cnf". See #1491.
ERR_KEEP="/var/log/mysql_full_dump-stderr-${DATE}.log"
# Tables whose DATA is excluded from the dump. #1491.
#
# display_facet_cache is a DERIVED CACHE, rebuilt hourly by
# ahg:refresh-facet-cache, and it is what has been breaking this dump: on
# 2026-08-26 attempt 1 died with "Error 1412: Table definition has changed"
# while dumping it, at 5,192,911,527 bytes - within 2 KB of the 24 Aug failure,
# i.e. the same point every time. The facet cron fires at 01:00:01, the same
# second this script starts.
#
# Excluding it removes the collision and ~240 MB (atom and heratio hold 120 MB
# each across 9 databases). Losing the contents costs nothing: the next hourly
# rebuild repopulates it.
#
# The STRUCTURE is still dumped, appended below - `--ignore-table` drops the
# CREATE TABLE as well as the rows, and a restore from this file alone would
# then be missing a table the application expects.
EXCLUDE_DATA_TABLE="display_facet_cache"
mapfile -t FACET_DBS < <(mysql --defaults-file=/root/.my.cnf -N -e \
    "SELECT table_schema FROM information_schema.tables WHERE table_name='${EXCLUDE_DATA_TABLE}';" 2>/dev/null || true)
IGNORE_ARGS=()
for _db in "${FACET_DBS[@]}"; do
    [ -n "$_db" ] && IGNORE_ARGS+=( "--ignore-table=${_db}.${EXCLUDE_DATA_TABLE}" )
done
log "excluding ${EXCLUDE_DATA_TABLE} data from ${#IGNORE_ARGS[@]} database(s); structure still dumped"

attempt=1; MAX_ATTEMPTS=2; RC=1; BYTES=0
while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
    log "mysqldump attempt $attempt/$MAX_ATTEMPTS"
    set +e
    # umask 077 for the dump window: an --all-databases dump carries every
    # credential every app on this host keeps in its own tables, including
    # email_setting.smtp_password in plaintext. These were written 0664 to a NAS
    # path readable by the ~10 non-root service accounts on this box (2026-08-24).
    #
    # Set as a plain statement and restored after, NOT by wrapping the pipeline
    # in a subshell: PIPESTATUS[0] on the next line must see mysqldump's exit
    # code, and a subshell would give it the subshell's (i.e. gzip's) instead,
    # silently disabling the retry and the too-small-file guard below.
    _prev_umask=$(umask)
    umask 077
    /usr/bin/mysqldump --defaults-file=/root/.my.cnf --all-databases --single-transaction --routines --triggers "${IGNORE_ARGS[@]}" 2>"$ERRFILE" | gzip > "$DUMP_FILE"
    RC=${PIPESTATUS[0]}
    umask "$_prev_umask"
    set -e
    BYTES=$(stat -c%s "$DUMP_FILE" 2>/dev/null || echo 0)
    if [ "$RC" -eq 0 ] && [ "$BYTES" -ge "$MIN_BYTES" ]; then break; fi
    log "attempt $attempt failed (rc=$RC, ${BYTES}B): $(tail -n 5 "$ERRFILE" 2>/dev/null | tr '\n' ' ' | tr -s ' ' | cut -c1-500)"
    # Append, never overwrite: attempt 2 truncates $ERRFILE, and the two
    # attempts can fail for different reasons.
    { echo "=== attempt $attempt  rc=$RC  ${BYTES}B  $(date '+%Y-%m-%d %H:%M:%S') ==="
      cat "$ERRFILE" 2>/dev/null
      echo; } >> "$ERR_KEEP" 2>/dev/null || true
    chmod 600 "$ERR_KEEP" 2>/dev/null || true
    attempt=$((attempt + 1))
    [ "$attempt" -le "$MAX_ATTEMPTS" ] && { log "retrying in 60s…"; sleep 60; }
done
rm -f "$ERRFILE"

# Guard against the silent-empty-dump failure: a real --all-databases dump is
# multi-MB+; a creds failure leaves a ~20-byte gzip. On failure: drop the bad
# file, ring Johan's bell, and DO NOT prune the good historical backups.
if [ "$RC" -ne 0 ] || [ "$BYTES" -lt "$MIN_BYTES" ]; then
    log "ERROR: mysqldump failed (rc=$RC) or dump too small (${BYTES}B) — removing bad file, skipping retention prune"
    # Carry the ACTUAL error into the alert instead of guessing.
    #
    # if-blocks rather than `[ test ] && action` for legibility only. To be
    # clear, since it is easy to get wrong in the other direction: `&&` would
    # ALSO be safe here. Under `set -e` a command that fails is exempt when it
    # is not the final command of an `&&` list, so `[ -z "$X" ] && X=default`
    # does NOT exit when the test is false. Verified, not assumed.
    #
    # Quotes, backslashes, tabs and newlines are stripped because this is
    # hand-built JSON and mysqldump error text contains all of them - one
    # unescaped quote puts the file in notifications/failed/ and nobody is told.
    # grep -v drops this script's own "=== attempt N ===" separators, which
    # otherwise lead the alert and push the actual error off the end of a phone
    # notification. What Johan reads first should be mysqldump's words.
    ERR_MSG=$(grep -v '^=== attempt' "$ERR_KEEP" 2>/dev/null | grep -v '^[[:space:]]*$' \
        | tail -n 3 | tr '\n\r\t' '   ' | tr -d '"\\' | tr -s ' ' | cut -c1-240 || true)
    if [ -z "$ERR_MSG" ]; then
        ERR_MSG="no stderr captured"
    fi
    if [ -d "$NOTIFY_DIR" ]; then
        printf '{"username":"johan","title":"MySQL backup FAILED","message":"mysqldump rc=%s size=%sB. Error: %s | full stderr kept at %s. Old backups left intact.","eventType":"alert"}\n' \
            "$RC" "$BYTES" "$ERR_MSG" "$ERR_KEEP" \
            > "$NOTIFY_DIR/$(date +%Y%m%dT%H%M%S)-mysql-backup-$DATE.json" 2>/dev/null || true
    fi
    log "stderr preserved at $ERR_KEEP"
    rm -f "$DUMP_FILE"
    exit 1
fi

# Append the excluded table's STRUCTURE, so a restore from this file alone still
# has it. Concatenated gzip members decompress as one continuous stream, so this
# is appended rather than re-compressed - and it runs only on the success path,
# after the guard above, so a failed dump is never extended.
#
# NOT folded into the main pipeline: `{ a; b; } | gzip` would make
# PIPESTATUS[0] report the group (i.e. gzip) instead of mysqldump, silently
# disabling the retry and the too-small-file guard. Same trap as the umask.
if [ "${#FACET_DBS[@]}" -gt 0 ]; then
    _prev_umask=$(umask)
    umask 077
    {
        for _db in "${FACET_DBS[@]}"; do
            [ -n "$_db" ] || continue
            echo "USE \`${_db}\`;"
            /usr/bin/mysqldump --defaults-file=/root/.my.cnf --no-data --skip-comments \
                "$_db" "$EXCLUDE_DATA_TABLE" 2>/dev/null || true
        done
    } | gzip >> "$DUMP_FILE"
    umask "$_prev_umask"
    log "appended ${EXCLUDE_DATA_TABLE} structure for ${#FACET_DBS[@]} database(s)"
fi

SIZE=$(du -h "$DUMP_FILE" | cut -f1)
log "Dump complete: $DUMP_FILE ($SIZE)"

# Keep last 30 days of backups
DELETED=$(find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +30 -delete -print | wc -l)
# Also clean up any old uncompressed dumps
DELETED_RAW=$(find "$BACKUP_DIR" -type f -name "*.sql" -mtime +7 -delete -print | wc -l)

log "Cleaned up: $DELETED compressed + $DELETED_RAW uncompressed old dumps"
log "Backup complete"
