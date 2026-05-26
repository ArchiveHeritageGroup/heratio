# Preservation scan (identify + malware)

> Audience: digital preservation officer, sysadmin

The `preservation:scan` artisan command runs two checks across every digital object attached to an archival description:

1. **Format identification** - what kind of file is this, and which PRONOM PUID does it match?
2. **Malware scan** - does it contain any known malicious signatures?

Both produce `preservation_event` rows in the standard PREMIS log, which then surface in the PREMIS XML export (see "PREMIS XML export") and in the preservation dashboard.

## Quick usage

```bash
# Scan a single IO
php artisan preservation:scan 1234

# Sweep every IO that doesn't have a fixity_check event in the last 90 days
php artisan preservation:scan

# Custom sweep window
php artisan preservation:scan --stale-days=180 --limit=200

# Force a specific tool list
php artisan preservation:scan 1234 --tools=siegfried,clamav
```

If you omit the IO id the command picks up to `--limit` IOs (default 50) that are "stale" - no fixity_check event newer than `--stale-days` (default 90).

## Tools

Heratio wraps two external open-source binaries plus a no-op fallback:

| Tool | Binary | Role | Install |
|---|---|---|---|
| Siegfried | `sf` | PRONOM-aware format identification | https://www.itforarchivists.com/siegfried/ |
| ClamAV | `clamscan` | Malware scan | `apt install clamav` |
| Null | (none) | Fallback when neither is installed | always available |

Both binaries are **optional**. If neither is present the scan still completes - it just records "unknown format, clean" for every file via the null tool. For production you should install at least Siegfried.

Use `--tools=` to force a particular order. The first tool whose `identify()` returns a non-unknown format wins; the first tool whose `scan()` reports a real scanner version wins. The null tool is always appended as a safety net.

## What gets written

For each digital object the service writes:

- One `preservation_event` row of `event_type = format_identification`. Detail includes the format name, version, MIME type, and PRONOM PUID.
- One `preservation_event` row of `event_type = virus_check`. Detail includes the scanner version and any threat names.
- When the `preservation_virus_scan` table is present, a row there too (status `clean` or `infected`, scanner version, threats JSON).

Outcome is `success` for a clean scan / identified format, `warning` for an unknown format, `failure` for an infected file or a missing file on disk.

## Reading the output

```
Tools in play: siegfried, clamav, null
  io=1234 scanned=8 identified=8 clean=8 infected=0 errors=0
  io=1287 scanned=3 identified=2 clean=3 infected=0 errors=0
```

- **scanned** - how many digital objects were attempted (file present on disk).
- **identified** - how many got a non-unknown format match.
- **clean / infected** - malware results.
- **errors** - missing files or tool failures. These also produce `failure` events.

Infected files are surfaced inline with a warning line:

```
    INFECTED do=1456 threats=Eicar-Signature
```

## When to run it

- **After bulk ingest** - sweep the new IOs to populate format + malware events.
- **Before export / dissemination** - confirm files haven't drifted.
- **On a schedule** - drop the sweep form into a `cron_schedule` entry. The simplest pattern:

  ```
  0 5 * * * cd /usr/share/nginx/heratio && php artisan preservation:scan --stale-days=90 --limit=200
  ```

## Performance notes

- Siegfried processes a typical archive file in under 50ms; the bottleneck is disk I/O on the storage path.
- ClamAV's `clamscan` is single-threaded and slow (~1 file/sec for large PDFs). For high-throughput sites use `clamdscan` (daemon mode) - Heratio's wrapper is a drop-in target for that future refinement.
- Files on slow remote storage (NAS over CIFS) will dominate wall-clock time. Tune `--limit` accordingly.

## Troubleshooting

- **"siegfried binary not found at sf"** - `sf` is not on the PHP user's `$PATH`. Install or set the full path.
- **"clamscan failed (exit 2)"** - usually means clamav-freshclam hasn't downloaded the signature DB yet. Run `freshclam` once and retry.
- **All scans report `file_missing`** - the configured `heratio.uploads_path` doesn't match where digital_object.path actually lives. Check `.env` `HERATIO_UPLOADS_PATH` and the mounted storage.
- **No events written** - check the operator user has write access to the preservation tables; the service writes via the standard Laravel DB connection.

## Related

- "PREMIS XML export" - emits the events this command writes.
- Issue #653 tracks the broader Phase 2+ items (real PRONOM signature sync, JHOVE validation, replication).
