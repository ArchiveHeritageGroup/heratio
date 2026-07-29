# Archivematica integration - setup and cron reference

A one-page reference for connecting Heratio to an Archivematica preservation pipeline and keeping the round-trip running. For administrators.

## What it does

Heratio sends a record's files to Archivematica for full preservation processing (format identification, virus scan, fixity, AIP storage), then pulls back an access copy (a DIP) and links it to the original record. Two directions, one identifier tying them together.

## 1. Settings

Set these under **Admin -> Archivematica** (`/admin/archivematica`). Current dev values shown for reference:

| Setting | Dev value | Notes |
|---------|-----------|-------|
| Storage Service URL | `http://192.168.0.150:8001` | The AM Storage Service |
| Dashboard URL | `http://192.168.0.150:62080` | The AM pipeline Dashboard |
| Dashboard username | `johanpiet` | AM Dashboard user |
| Dashboard API key | `[configured]` | From the AM user's profile; not shown here |
| Storage Service API key | `[configured]` | From the SS user's profile; not shown here |
| Default pipeline UUID | `8788d3be-7399-412f-a8e1-2b2518b6123f` | The AM processing pipeline |
| Transfer source path | `heratio` | Leaf under AM's watched transfer-source directory |
| Transfer staging path | `/mnt/am-transfer` | Local mount Heratio writes to; AM reads the same directory |

**The staging mount is the key requirement.** `Transfer staging path` must be a directory Heratio can write to *and* that Archivematica reads as its transfer source. Heratio stages the record's files there (plus a `processingMCP.xml` and a `metadata/metadata.csv` carrying the record identifier); Archivematica then ingests them. Without a shared mount there is nothing to transfer.

Check reachability any time:

```bash
sudo -u www-data php artisan am:ping
# Storage Service  OK  http://192.168.0.150:8001   (HTTP 200)
# Dashboard        OK  http://192.168.0.150:62080  (HTTP 200)
```

## 2. Unattended processing

Heratio ships `packages/ahg-archivematica/resources/processingMCP.xml`, dropped into every transfer, so the pipeline runs **without human decisions** - it auto-approves normalization and **stores both the AIP and the DIP**. Nothing to configure. To override per-site, place your own `processingMCP.xml` at the root of the staging path and it wins.

## 3. Crons

The round-trip needs two commands on a timer:

| Command | Timing | Purpose |
|---------|--------|---------|
| `am:poll` | every 5 min | advance in-flight Heratio -> AM transfers to completion |
| `am:ingest-dips` | every 15 min | pull finished DIPs from the Storage Service back into Heratio |

**On dev** these are installed as a dedicated cron (dev's general scheduler is disabled), at `/etc/cron.d/heratio-am-dev`:

```cron
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
MAILTO=""
*/5  * * * * www-data flock -n /var/run/heratio-am-poll.lock   -c '/usr/bin/php8.3 /usr/share/nginx/heratio-dev/artisan am:poll         >> /var/log/heratio-am.log 2>&1'
*/15 * * * * www-data flock -n /var/run/heratio-am-ingest.lock -c '/usr/bin/php8.3 /usr/share/nginx/heratio-dev/artisan am:ingest-dips >> /var/log/heratio-am.log 2>&1'
```

Log: `/var/log/heratio-am.log`. Runs as `www-data` (never root - artisan bootstrap as root creates root-owned logs that then block the web worker).

**On an instance that uses the database scheduler** (e.g. the demo runs `schedule:run` every minute), add the same two under **Admin -> System -> Scheduled tasks** instead - but only once that instance is pointed at an AM server. An unconfigured instance should not run them.

## 4. End-to-end check

```bash
# 1. send a record (or use the record's admin action "Send to Archivematica")
sudo -u www-data php artisan am:ping          # confirm servers reachable
# 2. advance it
sudo -u www-data php artisan am:poll           # in-flight -> complete
# 3. bring the access copy back
sudo -u www-data php artisan am:ingest-dips    # DIP -> linked to the record
```

A completed job shows `status=complete` in `am_job`; a returned DIP shows `status=linked` in `am_link` against the record's object id.

## 5. Troubleshooting

- **Transfer stalls at "Approve normalization"** - the shipped `processingMCP.xml` was not staged. Confirm the staging path is writable and the file lands at the transfer root.
- **Transfer fails at "Assign UUIDs to directories"** - AM-side: the MCPClient's `PROMETHEUS_MULTIPROC_DIR` under `/tmp` was wiped. Restart `archivematica-mcp-client`; the durable fix is to move that dir off `/tmp` on the VM.
- **DIP returns but 0 files ingested** - identifier mismatch. The transfer must carry `metadata/metadata.csv` with the record's `dc.identifier`; matching is by identifier.
- **`am:ingest-dips` keeps reprocessing the same unmatched DIPs** - known; unmatched DIPs are re-dispatched each run until a matching record exists.

## Scope note

Configured on **dev only**. The demo, atom and sasa instances are not wired to an Archivematica server. To enable elsewhere, set the section 1 values for that instance and add the section 3 crons.
