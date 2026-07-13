# Fuseki /ric TDB2 Bloat, Compaction Fix, and Prevention (OpenRiC triplestore)

**Summary:** Heratio's RiC/OpenRiC triplestore is Apache Jena Fuseki (Docker container `fuseki`, image `stain/jena-fuseki` running Jena 5.1.0, port 3030, data bind-mounted at `/var/lib/fuseki`). The archival RiC instance store is the **`/ric`** dataset (TDB2, text-indexed with jena-text Lucene). TDB2 never reclaims space in place: every load, and even `CLEAR ALL`, appends. Without periodic compaction the store grows unbounded until queries thrash and the endpoint appears "down". This doc records the July 2026 incident, the fix, and the prevention now shipped (Heratio v1.154.313).

## Datasets

- **`/openric-model`** - the RiC-O ontology model (~26 MB). Queried by `AhgRic\Services\SparqlQueryService` via `config('heratio.fuseki_endpoint')`. Was never affected.
- **`/openric`** - secondary (~6 MB).
- **`/ric`** - the archival RiC instance store (~18 M triples). Queried by `AhgRic\Services\RelationshipService` and `RicController` at `/ric/query` (`config('ric.fuseki.url')`, default `http://localhost:3030/ric`). Written by the RiC sync (`FusekiSyncService` / `SparqlUpdateService` / `packages/ahg-ric/bin/ric_sync.sh`). This is the one that bloated.

## Incident (July 2026): "Fuseki is down"

The Fuseki server and `/openric-model` were fine, but `/ric` queries hung (multi-minute timeouts; one `GET /ric/` ran ~96 minutes before a 500/EofException). Root cause: the `/ric` TDB2 store had grown to **272 GB** in a single `Data-0001` generation (never compacted). Live data was only ~2.8 GB; the rest was dead space. A small JVM heap scanning 272 GB thrashed. So the "outage" was pathological slowness from an un-compacted store, not a dead server.

## Fix

1. **Online compaction** reclaims dead space by writing a fresh generation of live triples only and (with `deleteOld`) discarding the old one: `POST /$/compact/ric?deleteOld=true` (admin-authenticated; async - a `Compact` task appears under `/$/tasks`). Result: 272 GB → 2.8 GB.
2. **Watch out for a stuck swap.** Online compaction's final generation-swap needs exclusive access; a leaked/long-running read transaction (e.g. the 96-minute query) can wedge it - the data copy completes but the task never reports done and the old generation is not deleted. Recovery: restart the Fuseki container. On restart TDB2 loads the latest valid generation (the compacted one); the old generation, if `deleteOld` did not run, becomes orphaned and can be removed once the new generation is confirmed serving.
3. **Heap.** The image sets the heap via `JVM_ARGS` (`fuseki-server` default `-Xmx4G`). It was raised to `-Xmx8G`, and the container memory limit raised accordingly (an 8 GB heap cannot live in an 8 GB container - heap + off-heap + memory-mapped TDB files would OOM-kill; it was given a 16 GB ceiling). Data persists across the container recreate via the bind mount.

## Jena quirk: finished Compact tasks are NOT removed from /$/tasks

A completed compaction task stays listed in `/$/tasks`, but gains a `"finished"` timestamp and a `"success"` boolean. **Detect completion by polling the specific task (`GET /$/tasks/{id}`) for the `"finished"` field, not by the task disappearing from the list.** Polling for the task's absence never terminates.

## Prevention (shipped, Heratio v1.154.313)

`packages/ahg-ric/bin/ric_sync.sh` (the full-sync script invoked by `RicController::ajaxSync` and by cron) now compacts `/ric` after a full sync:

- New `compact_triplestore()` POSTs `/$/compact/ric?deleteOld=true`, captures the returned `taskId`, then polls `GET /$/tasks/{id}` for `"finished"` + `"success":true`.
- Runs only after a **full** sync; targeted `--fonds` runs (small appends) are skipped. Controls: `RIC_COMPACT_AFTER_SYNC` (default true), `--compact` (force), `--no-compact` (skip). `RIC_COMPACT_POLL_TIMEOUT` bounds the wait.
- **Fails safe:** any compaction problem only logs an error; it never fails the data load. A slow compaction hits the poll timeout, logs "left running", and exits cleanly.

The same change fixed a pre-existing bug: `get_status()` / `show_status()` were POSTing their SPARQL to `/$/ping` instead of `/{dataset}/query`, so the sync log's "Total triples" figure was always 0.

## Operator notes

- Manual compaction any time: `POST http://localhost:3030/$/compact/ric?deleteOld=true` (admin auth), then poll `GET /$/tasks/{id}` for `"finished"`.
- If a compaction wedges on the swap, restart the `fuseki` container; TDB2 recovers to the latest valid generation with no data loss.
- Credentials/host details are operator-local, not recorded here.

See the companion RiC/OpenRiC docs for the API split and the sync architecture.
