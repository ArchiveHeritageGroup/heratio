# Authority Resolution Engine - Park Queue & NER Feedback (Tasks 7+9, AtoM side)

Tasks 7 (parked-mention queue, dedicated screen) and Task 9 (NER feedback
capture for retraining) of the AHG Authority Resolution Engine, as
shipped in `atom-ahg-plugins/ahgAuthorityResolutionPlugin/` on the AtoM
Heratio side (Symfony 1.4 plugin, `archive` database, GPL-3.0-or-later).
Both tasks share the existing DecisionRecorder pipeline; this doc
captures the data shapes, routes, tasks, and JSONL export contract so
the Laravel-side mirror and retraining pipeline can stay aligned.

## Task 7 - Park queue dedicated screen

### Route layout

| Route name | Method | URL | Action |
| --- | --- | --- | --- |
| `ar_auth_res_park_list` | GET | `/admin/authorityResolution/park` | `parkList` |
| `ar_auth_res_unpark` | POST | `/admin/authorityResolution/park/:id/unpark` | `unpark` |
| `ar_auth_res_park_dashboard_json` | GET | `/admin/authorityResolution/park/dashboard.json` | `parkDashboardJson` |

The existing `ar_auth_res_park` (POST `/admin/authorityResolution/:id/park`)
route from Task 5 still owns the act of parking from the review screen;
this task adds the read/unpark/dashboard surface.

### Filters on the park list

The park-queue list view supports the following filters (passed as GET
query string):

| Param | Type | Notes |
| --- | --- | --- |
| `parked_by` | int | `ahg_mention_park.parked_by_user_id`; dropdown populated from rows that exist |
| `entity_type` | string | One of `PERSON / ORG / GPE / LOC / PLACE` |
| `new_candidate_only` | checkbox | `1` => only rows where `new_candidate_available = 1` |
| `since_parked` | YYYY-MM-DD | Lower bound on `parked_at` |
| `limit` | int | Page size; clamped [10, 200] |

Rows are sorted `new_candidate_available DESC, parked_at DESC` so newly
flagged rows surface first.

### Re-review (un-park) flow

`POST /admin/authorityResolution/park/:id/unpark` deletes the
`ahg_mention_park` row, sets `ahg_mention.state = 'pending'`, re-runs
`CandidateGeneratorService::generate()` (the same adapter pipeline used
by the original review), then re-runs `EvidenceScorer::scoreAllForMention()`
so the row lands back in the pending queue with fresh candidates +
fresh composite scores. The action redirects to
`/admin/authorityResolution/:id/review` for the archivist to decide.

### Background sweep

`ParkQueueService::scanForNewCandidates()` iterates every park row,
dry-runs the candidate adapters against the live authority store, and
compares the normalised key set (source, authority_id, fuseki_uri,
display_name) to what's persisted in `ahg_mention_candidate`. On
mismatch, `new_candidate_available` flips to `1` and
`new_candidate_check_at` is stamped with `NOW()`. The flag is sticky -
once raised it stays until the row is deleted (i.e. un-parked) so a
transient lookup-source outage doesn't lose a real signal.

Operator-side cron entry (daily 02:00):

```cron
0 2 * * * cd /usr/share/nginx/archive && sudo -u www-data php symfony auth-res:scan-parked
```

## Task 9 - NER feedback capture

### Table: `ahg_ner_feedback`

```sql
id                    BIGINT UNSIGNED PK
mention_id            BIGINT UNSIGNED  -- ahg_mention.id
ner_entity_id         BIGINT UNSIGNED  -- ahg_ner_entity.id
decision_id           BIGINT UNSIGNED  -- ahg_mention_decision.id
source_text           MEDIUMTEXT       -- concat of relevant IO i18n fields
mention_value         VARCHAR(1000)    -- entity_value as extracted
mention_entity_type   VARCHAR(20)      -- PERSON / ORG / GPE / LOC / PLACE
mention_offset_start  INT UNSIGNED NULL  -- char offset within source_text
mention_offset_end    INT UNSIGNED NULL
rejection_reason      TEXT             -- archivist explanation
archivist_user_id     INT              -- user.id
ner_model_version     VARCHAR(100) NULL  -- if the upstream API recorded one
training_exported     TINYINT(1) DEFAULT 0
exported_at           DATETIME NULL
created_at            DATETIME
KEY idx_feedback_unexported (training_exported, created_at)
```

Idempotent in `install.sql` (CREATE TABLE IF NOT EXISTS).

### How rows land in `ahg_ner_feedback`

`DecisionRecorder::record($mentionId, 'reject', $userId, ['reason' => ...])`
now does, in this order:

1. Insert `ahg_mention_decision` (existing behaviour). The rejection
   reason is embedded in `evidence_snapshot` JSON under
   `rejection_reason` because the table has no dedicated column.
2. Update `ahg_mention.state = 'rejected'`.
3. Fire DecisionProvenanceWriter (existing).
4. **Task 9:** call `NerFeedbackService::captureFromRejection($decisionId)`
   inside a try/catch. Failure does NOT roll back the decision; it
   surfaces in the result payload as `feedback_error`.

The Task 5 reject UI was updated to use a `_rejectModal.php` partial
that POSTs a `reason` field; the action handler captures that as
`$opts['reason']` for the reject path (previously only park did this).
Reason is OPTIONAL on the action so legacy submit-from-form rejects
still work, but the modal asks for it explicitly.

### Export format

`auth-res:export-ner-feedback --format=jsonl|conll` drains every
`training_exported = 0` row, writes a dated file under
`/usr/share/nginx/archive/uploads/auth-res/ner-feedback/`, flips
`training_exported = 1` + `exported_at = NOW()` on the shipped rows.

If `uploads/auth-res/ner-feedback/` isn't writable, falls back to
`/tmp/ahg-auth-res-ner-feedback/`.

JSONL line shape:

```json
{
  "feedback_id": 1,
  "mention_id": 168,
  "decision_id": 7,
  "text": "<source_text>",
  "spans": [
    {
      "type": "PERSON",
      "value": "Mark Twain",
      "rejection_reason": "NER mis-typed; this is a date, not a place",
      "archivist_user_id": 1,
      "ner_model_version": null,
      "start": 42,
      "end": 52
    }
  ]
}
```

CoNLL-2003 format: one `token TAG` line per token, blank line between
examples. Rejected span tagged `B-<TYPE>` / `I-<TYPE>`; outside tokens
tagged `O`. Each example is preceded by a `# feedback_id=... reason=...`
comment line.

### Retraining integration

The exported file is the input the retraining job consumes. Each line
is a **negative** training example: the NER model proposed an entity,
the archivist said no. The `rejection_reason` field is the archivist's
free-text explanation, useful both as a label-quality filter (e.g. drop
rows tagged "OCR artefact" from final training set) and as feature for
a downstream reason-classifier head if you want one.

Once a file is exported, the source rows are flagged `training_exported = 1`
so subsequent exports never double-count. Re-export of the same window
is not currently supported - if you need it, run the SQL update directly
to flip the flag back.

## SF1.4 task list (after Tasks 7+9)

```
auth-res:export-ner-feedback   Export rejected-mention feedback as a training corpus (JSONL or CoNLL).
auth-res:generate-candidates   Generate ranked authority candidates for an ahg_mention.
auth-res:promote-sample        Promote PERSON/ORG/GPE entities for an information object into the authority-resolution mention workflow.
auth-res:scan-parked           Flag parked mentions whose candidate set has changed since parking.
auth-res:score-evidence        Score evidence signals + composite for each candidate of a mention. Re-ranks by composite.
auth-res:write-provenance      Write RDF-Star provenance for an authority-resolution decision to Fuseki.
```

All six tasks use the SF1.4 explicit `require_once` chain because the
plugin classes live under `lib/Services/` and Symfony 1.4 has no PSR-4
autoloader for them.

## Files

Plugin tree (atom-ahg-plugins/ahgAuthorityResolutionPlugin):

```
database/install.sql                                            (modified - new ahg_ner_feedback table)
lib/Services/ParkQueueService.php                                (new)
lib/Services/NerFeedbackService.php                              (new)
lib/Services/DecisionRecorder.php                                (modified - reject embeds reason, calls feedback hook)
lib/task/authResScanParkedTask.class.php                         (new)
lib/task/authResExportNerFeedbackTask.class.php                  (new)
modules/authorityResolution/actions/actions.class.php            (modified - parkList/unpark/parkDashboardJson + reject reason capture)
modules/authorityResolution/templates/parkListSuccess.php        (new)
modules/authorityResolution/templates/_parkRow.php               (new)
modules/authorityResolution/templates/_rejectModal.php           (new)
modules/authorityResolution/templates/reviewSuccess.php          (modified - reject button now triggers modal)
config/ahgAuthorityResolutionPluginConfiguration.class.php       (modified - three new routes)
```

No base AtoM modifications. No other AHG plugin modifications.
