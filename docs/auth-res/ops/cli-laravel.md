# CLI commands (Laravel / Heratio)

Every command is registered in
`AhgAuthorityResolutionServiceProvider::boot()` and runs via
`sudo -u www-data php artisan ...`. Eleven commands total: six from
Tasks 2-9, five from Task 10 (CLI consolidation).

## Snapshot

| Command                              | Task | Purpose                                                  |
|--------------------------------------|------|----------------------------------------------------------|
| `auth-res:promote-sample`            | 2    | Promote NER rows for an info-object into the workflow    |
| `auth-res:generate-candidates`       | 3    | Generate ranked candidate set for a mention              |
| `auth-res:score-evidence`            | 4    | Run evaluators + composite_score, re-rank candidates     |
| `auth-res:scan-parked`               | 7    | Flag parked mentions with new candidates                 |
| `auth-res:write-provenance`          | 8    | Backfill RDF-Star provenance for a decision              |
| `auth-res:export-ner-feedback`       | 9    | Dump unexported NER false-positive rows to JSONL         |
| `auth-res:status`                    | 10   | Read-only snapshot of every workflow table               |
| `auth-res:reprocess`                 | 10   | Re-run candidate + scoring for one mention or all pending|
| `auth-res:reprocess-parked`          | 10   | Bulk unpark + re-review parked mentions                  |
| `auth-res:cache-stats`               | 10   | Per-source row count for the lookup cache                |
| `auth-res:cache-clear`               | 10   | Purge cache rows by source (or --all)                    |

## auth-res:status

```
Signature: auth-res:status
```

Read-only. Cheap (single SELECT per dimension). Safe to run any time,
including in cron + monitoring jobs.

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

The Fuseki block is best-effort: if `AhgRic\Services\SparqlQueryService`
isn't installed or the dataset is unreachable, the block prints
`(skipped: ...)` rather than aborting.

## auth-res:reprocess

```
Signature: auth-res:reprocess
    {--mention-id= : Single ahg_mention.id to reprocess}
    {--all-pending : Reprocess every mention with state=pending}
    {--limit=0     : Cap when used with --all-pending (0 = no cap)}
```

Backed by `CandidateGeneratorService::generate()` +
`EvidenceScorer::scoreAllForMention()`. Idempotent.

Sample output:

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

## auth-res:reprocess-parked

```
Signature: auth-res:reprocess-parked
    {--since=    : Re-review every mention parked on/after YYYY-MM-DD}
    {--limit=0   : Cap (0 = no cap)}
    {--user-id=0 : archivist_user_id to attribute the unpark (0 = system)}
```

For each parked mention calls
`ParkQueueService::unparkAndRereview()`, which deletes the park row,
flips state to `pending`, and runs the full pipeline. Counts how many
mentions now have at least one candidate.

Sample output:

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

Pure SQL aggregation. Lists every known source even if its row count is
zero, so you can see at a glance which adapters have been exercised.

Sample output:

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

Destructive. Requires either `--source=NAME` or `--all`. Without `--force`,
shows a confirm prompt with the exact row count before deleting.

Sample:

```
$ sudo -u www-data php artisan auth-res:cache-clear --source=viaf
Delete 1 row(s) from ahg_authority_lookup_cache (source=viaf)? (yes/no) [no]:
> yes
Deleted 1 row(s) from ahg_authority_lookup_cache (source=viaf).

$ sudo -u www-data php artisan auth-res:cache-clear --source=viaf --force
Deleted 0 row(s) from ahg_authority_lookup_cache (source=viaf).
# (already empty after the first run)
```

## Tasks 2-9 commands (existing)

### auth-res:promote-sample

Promotes PERSON/ORG/GPE/PLACE entities for an info-object into
`ahg_mention` + `ahg_mention_context`. Use `--object-id=N`.

### auth-res:generate-candidates

```
auth-res:generate-candidates {mention_id?} {--object-id=} {--show} {--top=}
```

Generates candidates via every registered adapter, persists top-N.

### auth-res:score-evidence

```
auth-res:score-evidence {--candidate-id=} {--mention-id=} {--object-id=} {--show}
```

Runs evaluators, writes `evidence_signals`, `evidence_data`,
`composite_score`, re-ranks the candidate list.

### auth-res:scan-parked

```
auth-res:scan-parked
```

Flips `new_candidate_available=1` on park rows whose candidate
fingerprint has changed since parking. Cheap; safe to cron.

### auth-res:write-provenance

```
auth-res:write-provenance {decision_id}
```

Backfill RDF-Star to Fuseki for a single decision row. Normally written
synchronously by `DecisionRecorder` on decide; this command is for
backfilling rows where the Fuseki call failed.

### auth-res:export-ner-feedback

```
auth-res:export-ner-feedback {--output-dir=} {--limit=}
```

Walks `ahg_ner_feedback` where `training_exported=0` and writes a JSONL
file (one row per line) into the configured export dir. Flips
`training_exported=1` + `exported_at=now()`.

See [NER feedback export](ner-feedback.md) for the JSONL schema.

## Cron suggestions

```cron
# Hourly: keep the park queue fresh against new authority imports.
0 * * * * www-data /usr/bin/php /usr/share/nginx/heratio/artisan auth-res:scan-parked >/dev/null 2>&1

# Daily 02:00: drop NER feedback to disk for the next retrainer run.
0 2 * * * www-data /usr/bin/php /usr/share/nginx/heratio/artisan auth-res:export-ner-feedback --limit=10000 >/dev/null 2>&1
```
