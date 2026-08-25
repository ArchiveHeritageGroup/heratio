#!/bin/bash
#
# Live VM backup to the NAS.
#
# Until 2026-08-25 no VM image was backed up ANYWHERE - no libvirt snapshots, no
# ZFS snapshots over the NFS mount, no copy of any qcow2. The databases were
# dumped nightly, so the archives were safe, but the guest OS and application
# layer of every VM had no restore point at all. See #1486.
#
# Uses libvirt's push-mode backup API (`virsh backup-begin`, libvirt >= 6.0).
# The guest keeps RUNNING throughout - verified on heratio-dev: 7.5 GB in 5
# minutes, `qemu-img check` clean, domain never left the running state.
#
# WHY A WEEKLY ROTATION rather than nightly-everything: measured throughput to
# the NAS is ~25 MB/s, and the images total ~1.6 TB. Backing everything up every
# night would take ~17 hours and never finish before the working day. Each VM is
# therefore backed up once a week, assigned by weekday, with the 1 TB
# Mogalakwena given a Saturday slot because it alone needs ~11 hours.
#
# Exit 0 on success. Notifies the Workbench bell on failure, matching the
# convention in mysql_full_dump.sh.

set -uo pipefail

DEST_BASE="/mnt/nas/heratio/backups/vms"
LOG="/var/log/vm-backup.log"
NOTIFY_DIR="/var/spool/workbench/notifications"
KEEP=2                      # generations to retain per domain
MIN_FREE_GB=2000            # refuse to start below this much free space

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG"; }

notify() {
    [ -d "$NOTIFY_DIR" ] || return 0
    printf '{"username":"johan","title":"VM backup FAILED","message":"%s","eventType":"alert"}\n' \
        "$(echo "$1" | tr -d '"' | cut -c1-300)" \
        > "$NOTIFY_DIR/$(date +%Y%m%dT%H%M%S)-vm-backup.json" 2>/dev/null
}

# Which domains tonight. Balanced so no weeknight runs long into the morning.
case "$(date +%u)" in
    1) DOMAINS="atom" ;;                              # ~192 GB, ~2h
    2) DOMAINS="archivematica" ;;                     # ~300 GB, ~3.5h
    3) DOMAINS="rari-dev atom28 atom210" ;;           # ~65 GB,  <1h
    4) DOMAINS="heratio-dev ahgerp" ;;                # ~25 GB,  <30m
    5) DOMAINS="" ;;                                  # spare - retry night
    6) DOMAINS="Mogalakwena" ;;                       # ~1 TB, ~11h - weekend
    7) DOMAINS="" ;;                                  # spare
esac

# Explicit domains on the command line override the schedule - for an ad-hoc
# backup before risky work, and for testing this script without waiting for the
# right weekday.
[ $# -gt 0 ] && DOMAINS="$*"

log "=== run start (weekday $(date +%u)): domains='${DOMAINS:-none}' ==="

# --- domain XML for EVERY domain, every run -------------------------------
# Tiny, and without it you cannot recreate the VM definition even if you still
# have the disk. Cheapest, highest-value part of this whole script.
XMLDIR="$DEST_BASE/_definitions"
mkdir -p "$XMLDIR" || { log "ERROR: cannot create $XMLDIR"; notify "cannot create $XMLDIR"; exit 1; }
for d in $(virsh list --all --name 2>/dev/null | grep -a .); do
    if virsh dumpxml "$d" > "$XMLDIR/${d}.xml.tmp" 2>/dev/null; then
        mv "$XMLDIR/${d}.xml.tmp" "$XMLDIR/${d}.xml"
    else
        rm -f "$XMLDIR/${d}.xml.tmp"
        log "WARN: could not dump XML for $d"
    fi
done
virsh net-dumpxml default > "$XMLDIR/_net-default.xml" 2>/dev/null || true
log "domain definitions written for $(ls -1 "$XMLDIR"/*.xml 2>/dev/null | wc -l) domains"

[ -z "$DOMAINS" ] && { log "no domains scheduled tonight; definitions only"; exit 0; }

# --- space guard ----------------------------------------------------------
FREE_GB=$(df -BG --output=avail "$DEST_BASE" 2>/dev/null | tail -1 | tr -dc '0-9')
if [ -z "$FREE_GB" ] || [ "$FREE_GB" -lt "$MIN_FREE_GB" ]; then
    log "ERROR: only ${FREE_GB:-unknown} GB free on the NAS, need $MIN_FREE_GB"
    notify "VM backup aborted: only ${FREE_GB:-unknown} GB free on the NAS"
    exit 1
fi

STAMP=$(date +%Y-%m-%d)
FAILED=""

for DOM in $DOMAINS; do
    STATE=$(virsh domstate "$DOM" 2>/dev/null)
    if [ -z "$STATE" ]; then log "WARN: domain $DOM does not exist, skipping"; continue; fi

    OUT="$DEST_BASE/$DOM/$STAMP"
    mkdir -p "$OUT" || { log "ERROR: cannot create $OUT"; FAILED="$FAILED $DOM"; continue; }

    # Static, tiny, and not backed up by the block API - just copy them.
    for iso in $(virsh domblklist "$DOM" 2>/dev/null | awk 'NR>2 && $2 ~ /\.iso$/ {print $2}'); do
        cp -p "$iso" "$OUT/" 2>/dev/null && log "$DOM: copied $(basename "$iso")"
    done

    # qcow2 disks, by their target name (vda, etc).
    DISKS=$(virsh domblklist "$DOM" 2>/dev/null | awk 'NR>2 && $2 ~ /\.qcow2$/ {print $1}')
    if [ -z "$DISKS" ]; then log "WARN: $DOM has no qcow2 disks"; continue; fi

    if [ "$STATE" != "running" ]; then
        # Not running: a plain copy is consistent and avoids the block job.
        for t in $DISKS; do
            src=$(virsh domblklist "$DOM" 2>/dev/null | awk -v T="$t" 'NR>2 && $1==T {print $2}')
            log "$DOM: not running - plain copy of $t"
            cp --sparse=always "$src" "$OUT/${DOM}-${t}.qcow2" || FAILED="$FAILED $DOM"
        done
    else
        BX=$(mktemp)
        {
            echo "<domainbackup mode='push'><disks>"
            for t in $DISKS; do
                echo "<disk name='$t' backup='yes' type='file'>"
                echo "  <target file='$OUT/${DOM}-${t}.qcow2'/><driver type='qcow2'/>"
                echo "</disk>"
            done
            echo "</disks></domainbackup>"
        } > "$BX"

        log "$DOM: starting live backup of [$(echo $DISKS | tr '\n' ' ')]"
        if ! virsh backup-begin "$DOM" --backupxml "$BX" >>"$LOG" 2>&1; then
            log "ERROR: $DOM backup-begin failed"; FAILED="$FAILED $DOM"; rm -f "$BX"; continue
        fi
        rm -f "$BX"

        # Wait for the block job to finish. No timeout: Mogalakwena legitimately
        # takes ~11 hours, and killing a backup that is merely slow would leave
        # us with nothing.
        while true; do
            sleep 30
            jt=$(virsh domjobinfo "$DOM" 2>/dev/null | awk -F: '/Job type/{gsub(/ /,"",$2); print $2}')
            [ "$jt" = "None" ] || [ -z "$jt" ] && break
        done
        log "$DOM: block job complete"
    fi

    # Verify. A file of the right size that qemu cannot open is not a backup.
    for t in $DISKS; do
        f="$OUT/${DOM}-${t}.qcow2"
        if [ ! -s "$f" ]; then
            log "ERROR: $DOM $t produced no output"; FAILED="$FAILED $DOM"; continue
        fi
        if qemu-img check "$f" >/dev/null 2>&1; then
            log "$DOM $t: OK, $(du -h "$f" | cut -f1)"
        else
            log "ERROR: $DOM $t FAILED qemu-img check"; FAILED="$FAILED $DOM"
        fi
    done

    # Retention, per domain, only after a clean run for that domain.
    if ! echo "$FAILED" | grep -q "$DOM"; then
        ls -1dt "$DEST_BASE/$DOM"/*/ 2>/dev/null | tail -n +$((KEEP+1)) | while read -r old; do
            log "$DOM: pruning $old"; rm -rf "$old"
        done
    else
        log "$DOM: FAILED - retention prune skipped so older copies survive"
    fi
done

if [ -n "$FAILED" ]; then
    log "=== run finished WITH FAILURES:$FAILED ==="
    notify "VM backup failed for:$FAILED - see /var/log/vm-backup.log"
    exit 1
fi
log "=== run finished OK ==="
exit 0
