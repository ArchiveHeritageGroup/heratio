# Session note, 22-28 September 2026: the demo reset, the audit trail, the storage movement log

Written from the heratio-dev session at rotation. Covers CH-000175, CH-000139, CH-000136, CH-000177, heratio#1514 and heratio#1515, and the releases v1.154.779 through v1.156.3.

## CH-000175: the nightly demo reset, four nights and three wrong answers

The 02:00 demo reset failed for four consecutive nights with `ERROR 1359: Trigger already exists` at line 2543 of the baseline dump, leaving heratio.org part baseline and part live. The cause took three attempts, and the first two were wrong.

**Attempt one, v1.154.780 - wrong.** The reset now waits for any detached artisan process before restoring. The reasoning was that `ahg:optimize-models` is scheduled hourly with `runInBackground()`, so it fires at 00:00 UTC, and Laravel runs a background event as a detached `(artisan cmd ; artisan schedule:finish)` that outlives the scheduler lock. The mechanism is real. It was not the cause: the next failure logged no wait, so nothing was running. The wait loop is retained because it does guard the ERROR 1050 class from 15 and 16 September.

**Attempt two, v1.155.0 - necessary, not sufficient.** Rather than identify the writer, make the restore immune: the dump is piped through `sed` so every `CREATE TRIGGER` is preceded by `DROP TRIGGER IF EXISTS`. Plus failure diagnostics - each trigger with its `CREATED` time, and the connections on the database, deliberately without query text, because row values in an operator log is what CH-000135 was about. The injection works: the failure line moved 2543 to 2544, which is the injected DROP. The restore still failed, so something re-created the trigger between that DROP and the CREATE.

**The answer, from those diagnostics.** Both triggers were created *during* the restore, 0.42s apart - `ahg_audit_log_no_update_chained` at 02:00:47.58 and `ahg_audit_log_no_delete_chained` at 02:00:48.00. That pairing is `AhgAuditTrailServiceProvider` running `install-trigger.sql` at app boot. What booted the app was a **web request that maintenance mode failed to stop**: nginx logged 427 responses of 503 alongside **31 of 200** for real Heratio routes in those same seconds (`/glam/browse` 23 times, `/term/...`, `/help/article/...`, `/glam/exportCsv`).

**Why maintenance mode leaks.** php-fpm caches `stat()` results for `realpath_cache_ttl`, 120s on this host. A long-lived worker keeps *not seeing* the maintenance file `artisan down` has just created, and serves normally for up to two minutes. That is why the failure landed around 45 seconds into the restore, every night.

**The fix, v1.155.0 (installed 28 Sep).** The reset runs `systemctl reload php8.3-fpm` immediately after `artisan down`, recycling every worker so they all see maintenance mode at once, then sleeps 5s for in-flight requests. This is a host-wide fact, not a quirk of this script: **any deploy or restore that relies on maintenance mode needs the same reload.**

**Unproven as of 28 September.** Four nights were lost, and only two of them to the bug. The other two went to the baseline version stamp.

## The version stamp, and why it kept costing nights

`heratio-demo-reset.sh` refuses to restore when the baseline's version stamp differs from the deployed `version.json`, because the baseline is a full dump and restoring an old one would roll the schema back under newer code. Correct behaviour, and the reason the demo survives.

Normally `heratio-deploy.sh` re-snapshots and re-stamps the baseline after a healthy deploy, keeping the two in step. Since 22 September prod's database has been part baseline and part live, so deploys have been done by **manual pull** precisely to avoid freezing that mixed state as the golden baseline - which means nothing maintains the stamp, and every release silently makes the next reset abort.

Two nights were lost that way. Twice the stamp was set from a version typed into a command, and both times it went stale or was reverted from shell history. The durable form reads the deployed version rather than trusting anyone's memory:

```
grep -oE '[0-9]+\.[0-9]+\.[0-9]+' /usr/share/nginx/heratio/version.json | head -1 \
  | sudo tee /mnt/nas/heratio/demo-baseline/heratio-demo.version
```

Once a reset completes and the database is clean again, resume deploying with `heratio-deploy.sh` and the problem disappears with it.

## CH-000139 and CH-000136

**CH-000139** (cron-history re-apply, fixed in v1.154.777) is still unproven: it needs a reset that reaches the re-apply step, which none has. It is proven by `cron-monitoring history preserved` with no ERROR 1050 in the reset log.

**CH-000136** (the PII scan writing to `ahg_pii_scan_report`) proves out on a Monday 02:00 UTC run.

## CH-000176 and CH-000177: the audit trail

v1.154.779 shipped the audit fixes and they were verified on prod. Three follow-ups became CH-000177:

1. **`session_id` (done, v1.154.783).** `ahg_audit_log` stored the raw session id, which is the visitor's session cookie and, under the database session driver, the `sessions` table key - so the audit viewer, its CSV export and `audit:report` were all handing out live credentials. Now `hash_hmac('sha256', $sessionId, APP_KEY)`: deterministic, so events still group into a sitting and a session used from two addresses is still visible, but it cannot be replayed or matched back to the sessions table. Rows written earlier keep their raw ids and cannot be rewritten, because the table is hash-chained and append-only; they age out with the retention prune.
2. **The inference signing key (partly done).** `storage/keys/inference-signing.sk` on heratio-dev was world-readable. Tightened to `-rw-r-----`. The finding worth recording: the public key derived from dev's secret key matches prod's byte for byte, so **dev and prod share one keypair** - the world-readable dev copy exposed the key that signs prod's AI inference manifests to any local account on this host, from 22 June to 22 September. Tightening does not undo that window. **Rotation is still open**, along with two questions: whether old manifests verify against a retired key, and whether dev should have its own key or run unsigned.
3. **`AuditService::getSetting()` (done, v1.154.781), and it was worse than the ticket.** The file declared `namespace Ahg\AuditTrail\Services` while PSR-4 and every caller use `AhgAuditTrail` - wrong since at least 31 March. The class never autoloaded, so three callers' `class_exists()` checks were false and SharePoint push, SharePoint auto-ingest and heritage OCI movement events were **never audited**. Worse, a second lookup in one process re-included the file and died on an uncatchable `Cannot declare class`, which no try/catch could hold. Fixed, along with the process-lifetime settings cache and the callers, which were calling an instance method statically with the wrong arguments and would have written nothing anyway.

## heratio#1514: storage movement log

v1.155.0 and v1.155.1 put hierarchical storage locations live. v1.156.0 added the movement log, ported from `ahgStorageManagePlugin` v3.111.0 onto tables copied verbatim, so both platforms read one schema.

`ahg_storage_movement` is the record - append-only, one row per move per subject. `ahg_physical_object_location` is the current-state index beside it, written in the same transaction: the same split as `parent_id` and its closure table. The invariant the design rests on, and the first thing the tests assert: the index always equals the latest movement per object.

A NULL location is load-bearing and reads per subject: for an object, a first placement or a removal from storage; for a location, the columns hold its old and new *parent* and NULL is the root. Hence RESTRICT foreign keys - nulling a deleted location's id would silently turn "moved out of Room A" into "taken out of storage".

UI: movement history and an objects-here card with bulk move on the location page; current location, history and a Move action on the object page; a move form where removing from storage is a deliberate tick, not a blank destination. v1.156.1 added the help article and `docs/reference/storage-movement-log.md`.

## heratio#1515: one test's admin leaked into every later test

`AclService` keeps the signed-in user and their ACL groups in `private static` properties, and nothing cleared them between tests. One case's `actingAs()` admin was still the answer for every later case in the same process, so tests asserting what a **guest** may see ran as an administrator. `ActorVisibilityTest` caught it - a guest seeing draft and embargoed authority records - but only when something signed an admin in first, which is why it passed alone and failed in the full suite. `StorageMovementTest` in v1.156.0 was the trigger, not the defect.

Fixed in v1.156.3: `tests/TestCase` calls `AclService::forgetUser()` in `setUp` and `tearDown`, which is what that helper was written for and had no callers. Full suite: 1487 tests, no failures.

**Left open deliberately.** Under php-fpm a static lives one request, so this is test-only today. It is not test-only by nature: in Octane, a queue worker, or an artisan batch across users, the first identity seen would stick for the life of the process and a later guest would get the staff view. That is the third process-lifetime static found this month, after the audit middleware and `AuditService::getSetting()`. Worth treating as a class of bug rather than three incidents.

## The pattern behind most of this week

Boot-time schema mutation. Package providers create tables and triggers whenever the app boots, which is what collides with a database restore - ERROR 1359 on the audit triggers, ERROR 1050 on `ahg_cron_run`, `blog_post` and `ahg_io_funding`. Maintenance mode is supposed to prevent the boot, and for two minutes after `artisan down` it does not.

Two changes would end the class rather than the instances: providers not writing schema at boot (an explicit install command instead), and maintenance mode being reliable the moment it is switched on. Neither is done.
