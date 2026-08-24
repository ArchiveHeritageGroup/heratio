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
    /usr/bin/mysqldump --defaults-file=/root/.my.cnf --all-databases --single-transaction --routines --triggers 2>"$ERRFILE" | gzip > "$DUMP_FILE"
    RC=${PIPESTATUS[0]}
    umask "$_prev_umask"
    set -e
    BYTES=$(stat -c%s "$DUMP_FILE" 2>/dev/null || echo 0)
    if [ "$RC" -eq 0 ] && [ "$BYTES" -ge "$MIN_BYTES" ]; then break; fi
    log "attempt $attempt failed (rc=$RC, ${BYTES}B): $(tail -n 2 "$ERRFILE" 2>/dev/null | tr '\n' ' ' | tr -s ' ')"
    attempt=$((attempt + 1))
    [ "$attempt" -le "$MAX_ATTEMPTS" ] && { log "retrying in 60s…"; sleep 60; }
done
rm -f "$ERRFILE"

# Guard against the silent-empty-dump failure: a real --all-databases dump is
# multi-MB+; a creds failure leaves a ~20-byte gzip. On failure: drop the bad
# file, ring Johan's bell, and DO NOT prune the good historical backups.
if [ "$RC" -ne 0 ] || [ "$BYTES" -lt "$MIN_BYTES" ]; then
    log "ERROR: mysqldump failed (rc=$RC) or dump too small (${BYTES}B) — removing bad file, skipping retention prune"
    [ -d "$NOTIFY_DIR" ] && printf '{"username":"johan","title":"MySQL backup FAILED","message":"mysqldump rc=%s size=%sB — check /root/.my.cnf. Old backups left intact.","eventType":"alert"}\n' "$RC" "$BYTES" > "$NOTIFY_DIR/mysql-backup-$DATE.json" 2>/dev/null || true
    rm -f "$DUMP_FILE"
    exit 1
fi

SIZE=$(du -h "$DUMP_FILE" | cut -f1)
log "Dump complete: $DUMP_FILE ($SIZE)"

# Keep last 30 days of backups
DELETED=$(find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +30 -delete -print | wc -l)
# Also clean up any old uncompressed dumps
DELETED_RAW=$(find "$BACKUP_DIR" -type f -name "*.sql" -mtime +7 -delete -print | wc -l)

log "Cleaned up: $DELETED compressed + $DELETED_RAW uncompressed old dumps"
log "Backup complete"
