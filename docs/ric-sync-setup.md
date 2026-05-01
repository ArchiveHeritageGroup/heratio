# RiC Sync Setup and Configuration

Administrator guide for configuring the RiC → Fuseki triplestore sync runner.

---

## Overview

Heratio's RiC (Records in Contexts) module can mirror archival descriptions into an **Apache Jena Fuseki** triplestore as RiC-O RDF. This page documents how to configure that sync, how the safety gates work, and how to troubleshoot.

The sync is **optional**. A Heratio install with no Fuseki configured is fully functional - the RiC *dashboard* and *entity management* features work without Fuseki. Only the **Sync to Fuseki** button on `/admin/ric` requires it.

---

## Readiness gate (how the UI decides if sync is safe)

When you open `/admin/ric`, the dashboard calls `/admin/ric/ajax-sync-readiness` before enabling the **Sync to Fuseki** button. That endpoint checks, in order:

1. **RiC tables installed** - `ric_sync_status`, `ric_sync_queue`, `ric_orphan_tracking`, `ric_sync_log` all exist.
2. **Sync script present** - `packages/ahg-ric/bin/ric_sync.sh` is a file and executable.
3. **Config keys set** - `RIC_FUSEKI_URL` and `RIC_FUSEKI_DATASET` are set in `.env`.
4. **Fuseki reachable** - `GET {RIC_FUSEKI_URL}/$/ping` returns `200` or `401` within one second.

If any check fails, the button stays **disabled** and the specific blocking reason is shown inline. No shell process is spawned.

The `ajaxSync` endpoint itself re-runs the same readiness check and returns **HTTP 503** with the blocking reason if anything is not in place, so the gate also protects against stale client UI.

---

## Configuration

All configuration is **env-driven**. Add to `.env`:

```dotenv
# --- Fuseki triplestore ---
RIC_FUSEKI_URL=http://your-fuseki-host:3030
RIC_FUSEKI_DATASET=ric
RIC_FUSEKI_USER=admin
RIC_FUSEKI_PASS=<your-password>

# --- RiC identity ---
RIC_BASE_URI=https://your-heratio-host/ric
RIC_INSTANCE_ID=heratio-prod

# --- Source DB (optional; defaults to the Heratio DB) ---
# Set these only if you want to extract RiC triples from a DB
# other than the main Heratio database - e.g. a legacy AtoM install.
# RIC_SOURCE_DB_HOST=localhost
# RIC_SOURCE_DB_USER=root
# RIC_SOURCE_DB_PASSWORD=
# RIC_SOURCE_DB_NAME=heratio

# --- Optional: override the sync script path ---
# RIC_SYNC_SCRIPT=/custom/path/to/ric_sync.sh
```

Run `php artisan config:clear` after editing `.env`.

### What each key does

| Key | Purpose |
|---|---|
| `RIC_FUSEKI_URL` | Base URL of the Fuseki server (no trailing slash, no dataset). |
| `RIC_FUSEKI_DATASET` | Name of the Fuseki dataset that will hold RiC triples. |
| `RIC_FUSEKI_USER` / `_PASS` | Basic-auth credentials for the Fuseki admin API. |
| `RIC_BASE_URI` | URI prefix used when minting RiC entity URIs. Should resolve to your Heratio host. |
| `RIC_INSTANCE_ID` | Short identifier for this Heratio instance, embedded in minted URIs. |
| `RIC_SOURCE_DB_*` | Source database for RiC extraction. Defaults to the main Heratio DB. Set explicitly for hybrid installs (e.g. legacy AtoM alongside Heratio). |
| `RIC_SYNC_SCRIPT` | Absolute path override for the shell runner. Leave unset to use the bundled script. |

### Legacy `ATOM_DB_*` fallback

The Python RiC extractor historically read `ATOM_DB_HOST`, `ATOM_DB_USER`, `ATOM_DB_PASSWORD`, and `ATOM_DB_NAME` env vars. The shell runner now exports both name sets pointing at the same values, so:

- New installs: set `RIC_SOURCE_DB_*` (preferred).
- Hybrid installs already running on `ATOM_DB_*`: nothing to change - the old names are still honoured as a fallback.

---

## Running the sync

### From the dashboard (recommended)

Visit `/admin/ric` and click **Sync to Fuseki**. The button is disabled until all readiness checks pass. Progress is streamed back to the UI by polling the log file.

### From the CLI

```bash
cd /usr/share/nginx/heratio
./packages/ahg-ric/bin/ric_sync.sh --cron          # full sync, silent
./packages/ahg-ric/bin/ric_sync.sh --fonds 776,829 # sync specific fonds
./packages/ahg-ric/bin/ric_sync.sh --validate      # run SHACL validation after sync
./packages/ahg-ric/bin/ric_sync.sh --clear         # drop triplestore and resync
./packages/ahg-ric/bin/ric_sync.sh --status        # show Fuseki dataset status
```

The script reads the same `RIC_*` env vars. When launched from the dashboard, the controller passes them explicitly via the subprocess environment.

---

## Scheduler integration

RiC sync is **not** enabled as a default cron schedule. The two historical seeds (`ric-queue`, `ric-integrity`) referenced artisan commands that never shipped and have been removed. If you want scheduled syncs, add a system cron entry:

```cron
# /etc/cron.d/heratio-ric-sync
0 2 * * * www-data cd /usr/share/nginx/heratio && ./packages/ahg-ric/bin/ric_sync.sh --cron >> /var/log/ric_sync.log 2>&1
```

Or add it to the in-app scheduler (Admin → Cron) with a **shell command**, not an artisan command. (The in-app scheduler now validates that artisan commands exist before dispatching, so it will fail cleanly rather than crash if you point it at a missing command.)

---

## Troubleshooting

### "Sync not configured: …" on the dashboard

The message lists the exact missing piece. Common cases:

- `RIC_FUSEKI_URL is not set in .env` → add the key and run `php artisan config:clear`.
- `Fuseki not reachable at <url>` → confirm the Fuseki service is running (`curl http://host:3030/$/ping`) and that the firewall allows the connection from the Heratio host.
- `RiC tables not installed` → run `mysql -u root heratio < packages/ahg-ric/database/install.sql`.
- `Sync script missing or not executable` → `chmod +x packages/ahg-ric/bin/ric_sync.sh`.

### The "ric namespace not found" Symfony error

This was the symptom of an older bug where the dashboard shelled out to `php artisan ric:sync` (a command that never existed). Fixed. If you see it now, it means something external (an old cron entry, a custom script) is still invoking that non-existent command. Search `cron_schedule` and `/etc/cron.*` for `ric:sync` or `ahg:ric-queue` / `ahg:ric-integrity` and remove the offending entry.

### A cron schedule is failing with "Command not registered"

The in-app cron scheduler now refuses to dispatch artisan commands that are not registered. If you see `last_run_output` containing `Command not registered: 'ahg:xxx'`, either implement the command or delete the schedule row:

```sql
DELETE FROM cron_schedule WHERE slug = 'offending-slug';
```

---

## Reference: what changed vs. older installs

- **`bin/ric_sync.sh`** no longer hardcodes `FUSEKI_URL` or the AtoM DB credentials. `RIC_*` env vars take precedence; legacy `ATOM_DB_*` and `FUSEKI_*` names still work as a fallback.
- **`RicController::ajaxSync()`** preflights readiness and returns `503` with a reason instead of spawning a doomed process.
- **`RicController::ajaxSyncReadiness()`** is a new endpoint the dashboard uses to decide whether to enable the Sync button.
- **`config/ahg-ric.php`** now exposes `fuseki.*`, `source_db.*`, and `sync_script` keys, all env-driven.
- **`CronSchedulerService::runSingle()`** pre-checks `Artisan::all()` and fails fast with a clear message if the command is not registered.
- **Seed entries** `ric-queue` and `ric-integrity` have been removed from `getDefaultSchedules()`. Any existing rows in `cron_schedule` should be deleted manually (see Troubleshooting above).

---

## Security notes

- `RIC_FUSEKI_PASS` is held in `.env` and passed to the shell runner via the subprocess environment. It is **not** exposed to the browser. Ensure `.env` is not web-readable (standard Laravel convention).
- The readiness endpoint only reports *whether* sync is configured, not the config values themselves - no secrets leak to the dashboard client.
- `shell_exec` invocation uses `escapeshellcmd` on the script path and `escapeshellarg` on all env values to prevent injection.
