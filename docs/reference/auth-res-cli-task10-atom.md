# Authority Resolution Engine - CLI Consolidation (Task 10, AtoM side)

Task 10 of the AHG Authority Resolution Engine, as shipped in
`atom-ahg-plugins/ahgAuthorityResolutionPlugin/` on the AtoM Heratio side
(Symfony 1.4 plugin, `archive` database, GPL-3.0-or-later). Adds 5 new
Symfony 1.4 tasks under the existing `auth-res:` namespace and is the last
build step on the AtoM mirror.

The pre-existing tasks shipped with Tasks 2-9 stay as-is:

- `auth-res:promote-sample` (Task 2)
- `auth-res:generate-candidates` (Task 3)
- `auth-res:score-evidence` (Task 4)
- `auth-res:scan-parked` (Task 7)
- `auth-res:write-provenance` (Task 8)
- `auth-res:export-ner-feedback` (Task 9)

The 5 new ones below give the operator one place to look at the working
set, push reprocessing through, and manage the external-authority cache.

## Files added

| File | Purpose |
| --- | --- |
| `lib/task/authResStatusTask.class.php` | `auth-res:status` |
| `lib/task/authResReprocessTask.class.php` | `auth-res:reprocess` |
| `lib/task/authResReprocessParkedTask.class.php` | `auth-res:reprocess-parked` |
| `lib/task/authResCacheStatsTask.class.php` | `auth-res:cache-stats` |
| `lib/task/authResCacheClearTask.class.php` | `auth-res:cache-clear` |

`lib/Services/FusekiUpdateService.php` gained two new methods:
`executeQuery()` and `queryCount()`. Both target the SPARQL query endpoint
(not the update endpoint). Endpoint resolution falls back through:

1. `ahg_settings.fuseki_query_endpoint` (explicit)
2. `ahg_settings.fuseki_update_endpoint` with `/update` swapped for `/sparql`
3. `ahg_settings.fuseki_endpoint` + `/sparql`

On the live server (`openric-model` dataset) both `/sparql` and `/query`
respond identically, so step 2's `/sparql` derivation is what's used.

## Task contracts

### `auth-res:status`

```
php symfony auth-res:status
php symfony auth-res:status --json
```

Aggregates the authority-resolution working set:

- `ahg_mention` rows by `state` (pending / linked / parked / rejected / new_record_created)
- `ahg_mention` rows by `entity_type` (PERSON / ORG / GPE / PLACE)
- `ahg_mention_candidate` row count + avg per mention + mentions-with-candidates
- `ahg_mention_decision` rows by `decision_type`
- `ahg_mention_park` rows + `new_candidate_available` count
- `ahg_ner_feedback` rows + unexported count
- `ahg_authority_lookup_cache` rows total + by source
- Fuseki named-graph triple counts:
  - `urn:atom:auth-res:graph:decisions` (configurable via `ahg_settings.authority_resolution.decisions_graph_uri`)
  - `urn:atom:auth-res:graph:field-provenance` (configurable via `ahg_settings.authority_resolution.field_provenance_graph_uri`)

Pure SELECT against MySQL plus two `SELECT (COUNT(*) AS ?c) WHERE { GRAPH <...> { ?s ?p ?o } }`
calls into Fuseki.

`--json` emits a machine-readable payload (mirrors the human render
structure 1:1) for piping into dashboards or curl checks.

### `auth-res:reprocess`

```
php symfony auth-res:reprocess --mention-id=138
php symfony auth-res:reprocess --all-pending
php symfony auth-res:reprocess --all-pending --limit=100
```

Re-runs `CandidateGeneratorService::generate()` (Task 3) then
`EvidenceScorer::scoreAllForMention()` (Task 4) for either:

- a single `ahg_mention.id` (`--mention-id=X`), or
- every mention with `state = 'pending'` (`--all-pending`)

Uses the same adapter + evaluator chain as
`ParkQueueService::unparkAndRereview`, so all three reprocess paths
(CLI single, CLI bulk, un-park) converge on the same candidate set for
the same `(entity_value, entity_type)` pair.

`--limit=N` caps the `--all-pending` sweep, useful when 1000+ mentions
are in `pending` and you want to drip-feed.

### `auth-res:reprocess-parked`

```
php symfony auth-res:reprocess-parked --since=2026-05-01
php symfony auth-res:reprocess-parked --since=2026-05-01 --dry-run
php symfony auth-res:reprocess-parked --since=2026-05-01 --user-id=42
```

Bulk-unpark + re-review every `ahg_mention_park` row whose
`parked_at >= --since 00:00:00`. For each row calls
`ParkQueueService::unparkAndRereview($mentionId, $userId)`, which:

1. deletes the `ahg_mention_park` row
2. flips `ahg_mention.state` from `parked` -> `pending`
3. regenerates candidates via Task 3
4. re-scores via Task 4

`--user-id` attributes the bulk un-park to a specific archivist; default
is `0` ("CLI bulk", distinguishable in downstream audit reports).

`--dry-run` previews which park rows would be touched without acting.

The task treats "is not parked" and "not found" results from
`unparkAndRereview` as idempotent skips (counted but not errored), so a
repeat run of the same `--since` window is harmless.

### `auth-res:cache-stats`

```
php symfony auth-res:cache-stats
php symfony auth-res:cache-stats --json
```

Pure SELECT report of `ahg_authority_lookup_cache`. Per source:

- row count
- oldest `retrieved_at`
- newest `retrieved_at`
- entity-type breakdown (PERSON / ORG / PLACE)

Pairs with `auth-res:cache-clear` for the read side of cache lifecycle.

### `auth-res:cache-clear`

```
php symfony auth-res:cache-clear --source=viaf            # PREVIEW only, exit 2
php symfony auth-res:cache-clear --source=viaf --force    # actual delete
php symfony auth-res:cache-clear --all --force            # nuke everything
```

DELETEs from `ahg_authority_lookup_cache` scoped to one source or every
row. Safety first: without `--force` the task prints the count it would
delete and exits 2 (non-zero). With `--force` it deletes and reports the
affected-rows count.

No interactive STDIN prompting is used. Symfony 1.4 task readline + sudo
+ cron is a footgun, so the gate is the `--force` flag instead.

## Cron wiring

None of these 5 tasks require a cron schedule out of the box. Suggested
ops patterns:

- `auth-res:status` on demand, or daily in a job-board summary email.
- `auth-res:reprocess --all-pending --limit=200` weekly to mop up
  pending mentions whose authority store has grown.
- `auth-res:reprocess-parked --since=$(date -d '7 days ago' +%F)` weekly
  to drain the parked queue against fresh lookup data.
- `auth-res:cache-stats` quarterly; `auth-res:cache-clear --source=X
  --force` only when an external authority contract changes.

## Surprises and design choices

- **Fuseki query endpoint derivation.** Production has both
  `fuseki_endpoint` and `fuseki_update_endpoint` configured; the new
  `executeQuery()` derives the query URL from `fuseki_update_endpoint` by
  stripping `/update` and appending `/sparql`. Falls back to
  `fuseki_endpoint + '/sparql'` if the update endpoint isn't set. This
  matches the Apache Jena Fuseki default routing where `/dataset/update`,
  `/dataset/sparql`, and `/dataset/query` are siblings.
- **`ParkQueueService` interaction.** `unparkAndRereview` already
  returns `ok=false` with a typed error when the row is already unparked
  or the mention is gone. The bulk task treats those two error strings
  ("is not parked", "not found") as skips, not failures, so concurrent
  invocations or a partial repeat of the same `--since` window won't
  inflate the error count.
- **`auth-res:reprocess --all-pending` exit code.** Per-mention errors
  are surfaced inline but the task still exits 0. This is deliberate:
  the per-mention failures are visible in stderr (`logSection` with
  `ERROR`), and treating one bad mention as a job failure would break
  scheduled wrappers. The single-mention mode (`--mention-id`) still
  exits non-zero when the only mention being processed fails.
- **`auth-res:cache-clear` no interactive prompt.** Earlier draft had a
  `STDIN readline()` confirmation. Removed because in practice the task
  is going to be run as `sudo -u www-data` (no controlling TTY for
  www-data) or from cron (no TTY at all). The `--force` flag is a
  cleaner gate.
- **Schema reality check.** `ahg_mention.state` enumerates `pending`,
  `linked`, `parked`, `rejected`, `new_record_created`. The status task
  reports whatever states actually appear in the table; if a sixth value
  shows up (e.g. a future migration adds `superseded`), it surfaces
  automatically with no code change.

## Demo outputs (live)

`auth-res:status` (formatted, against live data on 2026-05-19):

```
Authority Resolution status @ 2026-05-19 11:17:08
============================================================
ahg_mention rows by state:
    pending: 1008
    linked: 2
    new_record_created: 1
    rejected: 1

ahg_mention rows by entity_type:
    GPE: 421
    PERSON: 312
    ORG: 279

ahg_mention_candidate rows: 9 (avg 1.50 per mention, across 6 mention(s) with candidates)

ahg_mention_decision rows by type:
    link: 3
    create_new: 1
    park: 1
    reject: 1

ahg_mention_park rows: 0 (new_candidate_available: 0)
ahg_ner_feedback rows: 1 (unexported: 0)
ahg_authority_lookup_cache rows: 2 (by source: viaf=1, wikidata=1)

Fuseki named-graph triple counts:
    decisions (urn:atom:auth-res:graph:decisions): 79 triples
    field-provenance (urn:atom:auth-res:graph:field-provenance): 38 triples
```

`auth-res:reprocess --mention-id=138` (PERSON "Frederick Douglass"):

```
Reprocessed 1 mention (#138). Candidates generated: 2. Candidates scored: 2.
```

`auth-res:reprocess-parked --since=2026-05-01` (park queue empty at run time):

```
No parked mentions since 2026-05-01. Nothing to do.
```

`auth-res:cache-stats`:

```
ahg_authority_lookup_cache: 2 row(s) across 2 source(s)
--------------------------------------------------------------------------------------------
source         rows   oldest                newest                by entity_type
--------------------------------------------------------------------------------------------
viaf              1   2026-05-19 10:42:30   2026-05-19 10:42:30   PERSON=1
wikidata          1   2026-05-19 10:42:31   2026-05-19 10:42:31   PERSON=1
--------------------------------------------------------------------------------------------
```

`auth-res:cache-clear --source=viaf` (preview without `--force`, exit 2,
cache untouched):

```
Would delete 1 row(s) from ahg_authority_lookup_cache (source=viaf).
Re-run with --force to actually delete.
```
