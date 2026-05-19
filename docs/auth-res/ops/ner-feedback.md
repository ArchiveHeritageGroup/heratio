# NER feedback export

The engine captures every `decision_type=reject` decision as a NER false
positive. Over time this becomes a high-quality training corpus for the
upstream NER model.

## Capture

When the archivist clicks **Reject** in the review screen,
`DecisionRecorder::recordReject()` writes:

1. The `ahg_mention_decision` audit row (as usual).
2. RDF-Star provenance to Fuseki (as usual).
3. A row in `ahg_ner_feedback` via `NerFeedbackService::capture()`.

The third step runs inside a try/catch - if it fails, the reject decision
still goes through. The feedback is a side benefit, not a blocker.

## ahg_ner_feedback schema

| column                | purpose                                                          |
|-----------------------|------------------------------------------------------------------|
| `mention_id`          | back-reference to the rejected mention                           |
| `ner_entity_id`       | the original NER row                                             |
| `decision_id`         | back-reference to `ahg_mention_decision`                         |
| `source_text`         | the full document text the NER saw                               |
| `mention_value`       | the surface form that was rejected                               |
| `mention_entity_type` | what the NER said it was (PERSON / ORG / GPE / ...)              |
| `mention_offset_start`| start char offset in source_text                                 |
| `mention_offset_end`  | end char offset in source_text                                   |
| `rejection_reason`    | free text from the reject modal                                  |
| `archivist_user_id`   | who rejected                                                     |
| `ner_model_version`   | which NER model produced the original mention (for filtering)    |
| `training_exported`   | 0 / 1 - flipped on export                                        |
| `exported_at`         | timestamp set on export                                          |
| `created_at`          | row insert                                                       |

## Export command

```bash
sudo -u www-data php artisan auth-res:export-ner-feedback \
    --output-dir=/var/spool/ahg-ai/ner-feedback \
    --limit=10000
```

Behavior:

1. Selects rows where `training_exported = 0` (oldest first).
2. Writes a JSONL file `ner-feedback-YYYYMMDD-HHMMSS.jsonl` to
   `--output-dir` (defaults to `authority_resolution.ner_feedback.export_dir`).
3. For each row, emits one JSONL line.
4. In a transaction: sets `training_exported = 1` + `exported_at = now()`.

## JSONL line shape

```json
{
  "mention_id": 24,
  "decision_id": 6,
  "source_text": "...the regent witnessed by Pretoria, his favourite horse...",
  "mention": {
    "value": "Pretoria",
    "entity_type": "GPE",
    "offset_start": 412,
    "offset_end": 420
  },
  "rejection": {
    "reason": "Horse name, not a place. NER false positive.",
    "archivist_user_id": 1
  },
  "ner_model_version": "ahg-ner-stanza-2025-12",
  "exported_at": "2026-05-19T09:42:11+02:00"
}
```

## Downstream: /opt/ahg-ai retrainer

The retrainer (separate codebase, `/opt/ahg-ai/retrainer/`) sweeps the
export directory nightly, batches the JSONL files into a training set,
and runs `fine-tune --negative-examples=ner-feedback-*.jsonl`. The
specifics are out of scope for the engine, but the contract is:

- One JSONL line == one labelled negative example.
- The retrainer reads `mention.offset_start` + `offset_end` against
  `source_text` to extract the span.
- `mention.entity_type` is the label the model **incorrectly** assigned.
- `rejection.reason` is human-readable context; the retrainer does not
  parse it but does carry it through to the audit log.

## Idempotency

Re-running the export with no new unexported rows produces a zero-line
file (or no file, depending on `--limit=0` behaviour - see
`ExportNerFeedbackCommand` source). Rows are never double-exported
because of the `training_exported` flag flip.

## Re-export

For training-pipeline debugging only:

```sql
UPDATE ahg_ner_feedback
   SET training_exported = 0,
       exported_at        = NULL
 WHERE id IN (...);
```

Then re-run `auth-res:export-ner-feedback`. Do NOT do this in production
unless you're rebuilding the model from scratch.

## Suggested cron

```cron
# Daily 02:00 export
0 2 * * * www-data /usr/bin/php /usr/share/nginx/heratio/artisan auth-res:export-ner-feedback --limit=10000 >/dev/null 2>&1
```
