# CLI tasks (AtoM)

The AtoM Heratio side ships the same engine as Symfony 1.4 tasks. They
live in `ahgAuthorityResolutionPlugin/lib/task/`. Run with:

```bash
sudo -u www-data php symfony auth-res:<task> [arg1] [--option=value]
```

## Snapshot

| Task                                | Mirrors Laravel command                   | Class file                                |
|-------------------------------------|-------------------------------------------|-------------------------------------------|
| `auth-res:promote-sample`           | `auth-res:promote-sample`                 | `authResPromoteSampleTask.class.php`      |
| `auth-res:generate-candidates`      | `auth-res:generate-candidates`            | `authResGenerateCandidatesTask.class.php` |
| `auth-res:score-evidence`           | `auth-res:score-evidence`                 | `authResScoreEvidenceTask.class.php`      |
| `auth-res:scan-parked`              | `auth-res:scan-parked`                    | `authResScanParkedTask.class.php`         |
| `auth-res:export-ner-feedback`      | `auth-res:export-ner-feedback`            | `authResExportNerFeedbackTask.class.php`  |
| `auth-res:status`                   | `auth-res:status`                         | `authResStatusTask.class.php`             |
| `auth-res:reprocess`                | `auth-res:reprocess`                      | `authResReprocessTask.class.php`          |
| `auth-res:reprocess-parked`         | `auth-res:reprocess-parked`               | `authResReprocessParkedTask.class.php`    |
| `auth-res:cache-stats`              | `auth-res:cache-stats`                    | `authResCacheStatsTask.class.php`         |
| `auth-res:cache-clear`              | `auth-res:cache-clear`                    | `authResCacheClearTask.class.php`         |

There is no `auth-res:write-provenance` task on the AtoM side; the
provenance write is always synchronous in the AtoM `DecisionRecorder`.

## auth-res:status

```bash
$ php symfony auth-res:status

ahg_mention rows by state:
    pending:             523
    linked:              42
    parked:              3
    rejected:            1
    new_record_created:  6
...
```

Output shape mirrors the Heratio command exactly.

## auth-res:reprocess

```bash
$ php symfony auth-res:reprocess --mention-id=42
Mention 42: regenerated 4 candidates, rescored 4.

$ php symfony auth-res:reprocess --all-pending --limit=200
Reprocessing 200 pending mention(s)...
...
Done. 200 reprocessed, 0 failed.
```

## auth-res:reprocess-parked

```bash
$ php symfony auth-res:reprocess-parked --since=2026-05-01
Re-reviewing 3 parked mention(s) (parked >= 2026-05-01)...
  mention 11: unparked, 5 candidates, 5 scored.
  mention 12: unparked, 0 candidates, 0 scored.
  mention 13: unparked, 4 candidates, 4 scored.
Done. 3 re-reviewed, 0 failed, 2 now have at least one candidate.
```

## auth-res:cache-stats

Same SQL aggregation as the Laravel side - both codebases query the same
`ahg_authority_lookup_cache` table.

## auth-res:cache-clear

```bash
# With confirm
$ php symfony auth-res:cache-clear --source=viaf

# Cron-safe
$ php symfony auth-res:cache-clear --source=viaf --force
```

## Shared schema, separate cron

Both codebases write to the same `ahg_*` tables (when both deployments
are pointed at the same database). If you're running side-by-side
(Heratio Laravel for the live ingest, AtoM Heratio for legacy review),
**only schedule the periodic tasks on ONE codebase** to avoid double
processing.

Recommended: schedule on the Laravel side (which has cron under
`/etc/cron.d/heratio` already), keep the AtoM tasks for ad-hoc runs.

## Why two implementations?

Per the project's `feedback_wdb_atom_heratio.md` memory: WDB and other
legacy deployments still run AtoM Heratio (Symfony 1.4 + AHG plugins).
The Laravel rewrite is the forward path, but the engine has to work
identically on both during the transition. Convergence means: same
tables, same provenance graph URIs (scoped by codebase prefix), same
operator-facing CLI surface.
