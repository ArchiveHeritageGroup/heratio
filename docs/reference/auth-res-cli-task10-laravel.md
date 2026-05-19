# Authority Resolution Engine - Task 10 (CLI consolidation, Heratio Laravel)

Task 10 added five new artisan commands to
`packages/ahg-authority-resolution/`, bringing the total to **11
commands** registered by
`AhgAuthorityResolutionServiceProvider::boot()`. All commands run as
`sudo -u www-data php artisan auth-res:<name>`.

## The five new commands

### 1. `auth-res:status`

Read-only one-shot snapshot of every workflow table:

- `ahg_mention` grouped by `state` + by `entity_type`
- `ahg_mention_candidate` total + avg per scored mention
- `ahg_mention_decision` grouped by `decision_type`
- `ahg_mention_park` total + `new_candidate_available` subtotal
- `ahg_ner_feedback` total + `training_exported=0` subtotal
- `ahg_authority_lookup_cache` total + per-source subtotal
- Fuseki triple counts per provenance graph (best-effort; if
  `AhgRic\Services\SparqlQueryService` is missing, prints `(skipped: ...)`)

Sample (live data 2026-05-19):

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

### 2. `auth-res:reprocess`

```
auth-res:reprocess --mention-id=24
auth-res:reprocess --all-pending [--limit=N]
```

Re-runs `CandidateGeneratorService::generate()` +
`EvidenceScorer::scoreAllForMention()` for one mention or every pending
mention. Idempotent.

```
$ sudo -u www-data php artisan auth-res:reprocess --mention-id=24
Mention 24: regenerated 2 candidates, rescored 2.
```

### 3. `auth-res:reprocess-parked`

```
auth-res:reprocess-parked [--since=YYYY-MM-DD] [--limit=N] [--user-id=N]
```

Bulk re-review every parked mention parked on/after `--since`. For each
parked row, calls `ParkQueueService::unparkAndRereview()`. Outputs count
of mentions that now have at least one candidate.

```
$ sudo -u www-data php artisan auth-res:reprocess-parked --since=2026-05-01
Re-reviewing 1 parked mention(s) (parked >= 2026-05-01)...
  mention 24: unparked, 2 candidates, 2 scored.
Done. 1 re-reviewed, 0 failed, 1 now have at least one candidate (new_candidate_available).
```

### 4. `auth-res:cache-stats`

Per-source summary of `ahg_authority_lookup_cache`. Lists all seven
known sources even when row count is zero.

```
$ sudo -u www-data php artisan auth-res:cache-stats
ahg_authority_lookup_cache: 2 rows
  viaf:     1 entries, oldest 2026-05-19, newest 2026-05-19, types PERSON
  wikidata: 1 entries, oldest 2026-05-19, newest 2026-05-19, types PERSON
  geonames: 0 entries
  tgn:      0 entries
  gnd:      0 entries
  isni:     0 entries
  sagnc:    0 entries
```

### 5. `auth-res:cache-clear`

```
auth-res:cache-clear --source=NAME [--force]
auth-res:cache-clear --all [--force]
```

Destructive. Deletes rows from `ahg_authority_lookup_cache`. Without
`--force`, prompts for yes/no. With `--force`, cron-safe.

```
$ sudo -u www-data php artisan auth-res:cache-clear --source=viaf
Delete 1 row(s) from ahg_authority_lookup_cache (source=viaf)? (yes/no) [no]:
```

## Files added (Task 10)

- `src/Console/Commands/StatusCommand.php`
- `src/Console/Commands/ReprocessCommand.php`
- `src/Console/Commands/ReprocessParkedCommand.php`
- `src/Console/Commands/CacheStatsCommand.php`
- `src/Console/Commands/CacheClearCommand.php`

Registered in
`src/Providers/AhgAuthorityResolutionServiceProvider::boot()` under the
`// Task 10: CLI consolidation` comment.

## Full command list

| Command                              | Task | Purpose                                                  |
|-------------------------------------|------|----------------------------------------------------------|
| `auth-res:promote-sample`            | 2    | Promote NER rows into the workflow                       |
| `auth-res:generate-candidates`       | 3    | Generate ranked candidates                               |
| `auth-res:score-evidence`            | 4    | Run evaluators + composite_score, re-rank                |
| `auth-res:scan-parked`               | 7    | Flag parked mentions with new candidates                 |
| `auth-res:write-provenance`          | 8    | Backfill RDF-Star for a decision                         |
| `auth-res:export-ner-feedback`       | 9    | Dump unexported NER feedback to JSONL                    |
| `auth-res:status`                    | 10   | Snapshot of every workflow table                         |
| `auth-res:reprocess`                 | 10   | Re-run candidate + scoring                               |
| `auth-res:reprocess-parked`          | 10   | Bulk unpark + re-review                                  |
| `auth-res:cache-stats`               | 10   | Per-source lookup cache row count                        |
| `auth-res:cache-clear`               | 10   | Purge lookup cache rows by source                        |

## Cron suggestions

```cron
# Hourly: keep park queue fresh
0 * * * * www-data /usr/bin/php /usr/share/nginx/heratio/artisan auth-res:scan-parked

# Daily 02:00: drop NER feedback for retrainer
0 2 * * * www-data /usr/bin/php /usr/share/nginx/heratio/artisan auth-res:export-ner-feedback --limit=10000
```

## Cross-reference

- Polished user docs: `/usr/share/nginx/heratio/docs/auth-res/ops/cli-laravel.md`
- AtoM-side mirror (10 tasks): `atom-ahg-plugins/ahgAuthorityResolutionPlugin/lib/task/`
- Schema reference: `database/install.sql`
