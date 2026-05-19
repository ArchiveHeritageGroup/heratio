# Authority Resolution - CLI Commands

The engine exposes 11 artisan commands. All are registered in `AhgAuthorityResolutionServiceProvider::boot()` and run as `sudo -u www-data php artisan ...`. Six come from the original workflow tasks (2-9), five from the CLI consolidation pass (task 10).

## Snapshot

| Command | Purpose |
|---|---|
| `auth-res:promote-sample` | Promote NER rows for an information object into the workflow |
| `auth-res:generate-candidates` | Generate the ranked candidate set for a mention |
| `auth-res:score-evidence` | Run evaluators, compute composite score, re-rank candidates |
| `auth-res:scan-parked` | Flag parked mentions whose candidate set has changed |
| `auth-res:write-provenance` | Backfill RDF-Star provenance for a single decision row |
| `auth-res:export-ner-feedback` | Dump unexported NER false-positive rows to JSONL or CoNLL |
| `auth-res:status` | Read-only snapshot of every workflow table |
| `auth-res:reprocess` | Re-run candidate + scoring for one mention or all pending |
| `auth-res:reprocess-parked` | Bulk unpark and re-review parked mentions |
| `auth-res:cache-stats` | Per-source row count for the lookup cache |
| `auth-res:cache-clear` | Purge cache rows by source (or `--all`) |

Every command is idempotent. Re-running them with no change is safe.

## auth-res:status

```
Signature: auth-res:status
```

Read-only. Cheap (one SELECT per dimension). Safe to run any time, including in cron and monitoring jobs.

Sample output:

```
ahg_mention rows by state:
    linked:              1
    new_record_created:  1
    parked:              1
    pending:             980
    rejected:            1
ahg_mention rows by entity_type:
    GPE:       417
    ORG:       251
    PERSON:    316
ahg_mention_candidate rows: 8 (avg 1.1 per scored mention)
ahg_mention_decision rows by type:
    create_new:          1
    link:                2
    park:                2
    reject:              1
ahg_mention_park rows: 1 (new_candidate_available: 0)
ahg_ner_feedback rows: 1 (unexported: 0)
ahg_authority_lookup_cache rows: 2 (by source: viaf=1, wikidata=1)
Fuseki provenance graphs:
    urn:heratio:auth-res:graph:decisions : 0 triples
    urn:atom:auth-res:graph:decisions : 0 triples
    urn:heratio:auth-res:graph:field-provenance : 0 triples
    urn:atom:auth-res:graph:field-provenance : 0 triples
```

The Fuseki block is best-effort. If `AhgRic\Services\SparqlQueryService` is not installed or the dataset is unreachable, the block prints `(skipped: ...)` rather than aborting.

## auth-res:reprocess

```
Signature: auth-res:reprocess
    {--mention-id= : Single ahg_mention.id to reprocess}
    {--all-pending : Reprocess every mention with state=pending}
    {--limit=0     : Cap when used with --all-pending (0 = no cap)}
```

Backed by `CandidateGeneratorService::generate()` plus `EvidenceScorer::scoreAllForMention()`. Idempotent.

```
$ sudo -u www-data php artisan auth-res:reprocess --mention-id=24
Mention 24: regenerated 2 candidates, rescored 2.

$ sudo -u www-data php artisan auth-res:reprocess --all-pending --limit=100
Reprocessing 100 pending mention(s)...
Mention 1: regenerated 4 candidates, rescored 4.
Mention 2: regenerated 5 candidates, rescored 5.
...
Done. 100 reprocessed, 0 failed.
```

Use this when an adapter weight changed, when the candidate-generator code changed, or when you want to flush every pending mention before a release.

## auth-res:reprocess-parked

```
Signature: auth-res:reprocess-parked
    {--since=    : Re-review every mention parked on/after YYYY-MM-DD}
    {--limit=0   : Cap (0 = no cap)}
    {--user-id=0 : archivist_user_id to attribute the unpark (0 = system)}
```

For each parked mention calls `ParkQueueService::unparkAndRereview()`, which deletes the park row, flips state to `pending`, and runs the full pipeline. The summary line counts how many mentions now have at least one candidate.

```
$ sudo -u www-data php artisan auth-res:reprocess-parked --since=2026-05-01
Re-reviewing 1 parked mention(s) (parked >= 2026-05-01)...
  mention 24: unparked, 2 candidates, 2 scored.
Done. 1 re-reviewed, 0 failed, 1 now have at least one candidate (new_candidate_available).
```

## auth-res:cache-stats

```
Signature: auth-res:cache-stats
```

Pure SQL aggregation. Lists every known source even when its row count is zero, so you can see at a glance which adapters have been exercised.

```
ahg_authority_lookup_cache: 2 rows
  viaf:     1 entries, oldest 2026-05-19, newest 2026-05-19, types PERSON
  wikidata: 1 entries, oldest 2026-05-19, newest 2026-05-19, types PERSON
  geonames: 0 entries
  tgn:      0 entries
  gnd:      0 entries
  isni:     0 entries
  sagnc:    0 entries
```

## auth-res:cache-clear

```
Signature: auth-res:cache-clear
    {--source=  : Source to purge (viaf, wikidata, geonames, tgn, gnd, isni, sagnc)}
    {--all      : Purge every source}
    {--force    : Skip the interactive confirm prompt}
```

Destructive. Requires either `--source=NAME` or `--all`. Without `--force` it shows a confirm prompt with the exact row count before deleting.

```
$ sudo -u www-data php artisan auth-res:cache-clear --source=viaf
Delete 1 row(s) from ahg_authority_lookup_cache (source=viaf)? (yes/no) [no]:
> yes
Deleted 1 row(s) from ahg_authority_lookup_cache (source=viaf).

$ sudo -u www-data php artisan auth-res:cache-clear --source=viaf --force
Deleted 0 row(s) from ahg_authority_lookup_cache (source=viaf).
# (already empty after the first run)
```

## auth-res:promote-sample

```
Signature: auth-res:promote-sample
    {--object-id= : information_object.id to promote NER rows from}
    {--types=PERSON,ORG,GPE,LOC,PLACE : comma-separated entity types}
```

Promotes selected `ahg_ner_entity` rows for the given information object into `ahg_mention` and computes the neighbourhood context packet in `ahg_mention_context`. The mention starts in `state = pending`.

The promotion is idempotent. A `UNIQUE` on `ahg_mention.ner_entity_id` prevents duplicates; re-running just touches `updated_at`.

## auth-res:generate-candidates

```
Signature: auth-res:generate-candidates {mention_id?}
    {--object-id= : alternative: every pending mention on this IO}
    {--show       : print the resulting candidate table}
    {--top=       : override authority_resolution.candidate_top_n}
```

Walks every registered candidate adapter, ranks by Jaro-Winkler name similarity, persists top-N to `ahg_mention_candidate`. Idempotent: re-running clears the candidate set and re-inserts.

## auth-res:score-evidence

```
Signature: auth-res:score-evidence {mention_id?}
    {--candidate-id= : score one specific row}
    {--mention-id=   : score every candidate for one mention}
    {--object-id=    : score every mention on one IO}
    {--show          : print the resulting evidence table}
    {--async         : dispatch as a queue job}
```

Runs every applicable evaluator over every candidate, writes `evidence_signals`, `evidence_data`, and `composite_score`, then re-ranks the candidate list. See "Authority Resolution - Evidence Scoring" for the evaluator catalogue and the composite formula.

## auth-res:scan-parked

```
Signature: auth-res:scan-parked
```

Computes a candidate fingerprint (`source|authority_id|fuseki_uri|display_name`, sorted CSV) for every parked mention and compares it against the last scan. Flips `ahg_mention_park.new_candidate_available = 1` when the fingerprint has changed; records `new_candidate_check_at = NOW()` either way.

Cheap (one candidate-generator pass per parked mention) and idempotent. Suggested cron: hourly.

## auth-res:write-provenance

```
Signature: auth-res:write-provenance {decision_id}
```

Backfill RDF-Star to Fuseki for a single `ahg_mention_decision` row. Normally written synchronously by `DecisionRecorder` on decide; this command is for backfilling rows where the synchronous Fuseki call failed and `fuseki_graph_uri` is still NULL.

## auth-res:export-ner-feedback

```
Signature: auth-res:export-ner-feedback
    {--output-dir= : override the default storage/app/auth-res/ner-feedback/ dir}
    {--limit=      : cap the row count}
    {--format=     : jsonl (default) or conll}
```

Walks `ahg_ner_feedback WHERE training_exported = 0` and writes one file (`<YYYYMMDD-HHMMSS>.jsonl` or `.conll`) into the configured export dir. After the file is fully written, the matching rows are flipped to `training_exported = 1` and `exported_at = NOW()` in a single UPDATE.

JSONL shape (one record per line):

```json
{
  "feedback_id": 1,
  "mention_id": 56,
  "ner_entity_id": 1063,
  "decision_id": 7,
  "text": "<full source text>",
  "spans": [{
    "start": 725, "end": 729,
    "type": "GPE", "value": "U.S.",
    "label": "reject",
    "rejection_reason": "NER mis-typed; this is a place, not a person",
    "archivist_user_id": 900148,
    "ner_model_version": null
  }],
  "created_at": "2026-05-19 18:02:18"
}
```

CoNLL-2003 style: flat tag file using `B-REJ-<TYPE>`, `I-REJ-<TYPE>`, `O` over whitespace-tokenised text.

The `label: "reject"` flag tells the retrainer this is a NEGATIVE example. Operators hand the file to the NER service at `/opt/ahg-ai` for the next retraining pass.

## Cron suggestions

```cron
# Hourly: keep the park queue fresh against new authority imports.
0 * * * * www-data /usr/bin/php /usr/share/nginx/heratio/artisan auth-res:scan-parked >/dev/null 2>&1

# Daily 02:00: drop NER feedback to disk for the next retrainer run.
0 2 * * * www-data /usr/bin/php /usr/share/nginx/heratio/artisan auth-res:export-ner-feedback --limit=10000 >/dev/null 2>&1
```

Both jobs are cheap. Scan-parked is a no-op against a static authority store; export-ner-feedback is a no-op when no new rejections have landed.

## Always run as www-data

Heratio's storage path is owned by `www-data`. Running artisan as `root` from these directories can create the daily Laravel log file owned by `root:root`, which then breaks every subsequent web request (file-system writes from php-fpm fail). Always:

```
sudo -u www-data php artisan auth-res:<subcommand> ...
```

## Related

- "Authority Resolution - Park Queue" - `auth-res:scan-parked` and `auth-res:reprocess-parked` in operator context.
- "Authority Resolution - Evidence Scoring" - the formula behind `auth-res:score-evidence`.
- "Authority Resolution - Creating a New Authority Record" - the cache that `auth-res:cache-stats` and `auth-res:cache-clear` operate on.
