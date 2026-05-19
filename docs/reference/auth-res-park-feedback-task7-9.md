# Authority Resolution Engine - Park Queue (Task 7) + NER Feedback (Task 9)

Shipped 2026-05-19. Both tasks extend the same Task 5 review controllers and share the `DecisionRecorder` write-path, so they ship together. Package: `packages/ahg-authority-resolution/` on the Laravel Heratio side.

## Task 7 - Park queue (dedicated screen)

### Screen

`/admin/authority-resolution/park` - dedicated parked-mention queue. Lists every active `ahg_mention_park` row, joined with `ahg_mention`, `ahg_ner_entity`, and `ahg_mention_context`.

Filters:
- `parked_by` - archivist user id (dropdown of users who have parked anything)
- `entity_type` - PERSON / ORG / GPE / PLACE / LOC
- `reason_q` - text search in `ahg_mention_park.reason`
- `new_candidate_only` - boolean, shows only rows the scan job has flagged
- `sort_by` - `parked_at_desc` (default), `parked_at_asc`, `entity_type`, `new_candidate`

Top-line tiles:
- Total parked
- New candidate available (rows where the background scan saw the candidate set change)
- Archivists involved

Per-archivist dashboard widget partial (`auth-res::_park-dashboard-widget`) is reusable from any admin dashboard and is rendered on the park screen itself.

### Re-review flow

`POST /admin/authority-resolution/park/{mention}/unpark` runs `ParkQueueService::unparkAndRereview()`:

1. `DELETE FROM ahg_mention_park WHERE mention_id = ?`
2. `UPDATE ahg_mention SET state = 'pending'` for that mention
3. `CandidateGeneratorService::generate($mentionId)` re-queries every adapter
4. `EvidenceScorer::scoreAllForMention($mentionId)` re-scores the new set
5. Redirect to `/admin/authority-resolution/review/{mention}` so the archivist resumes review on the fresh candidate set

### Background scan

`php artisan auth-res:scan-parked` runs `ParkQueueService::scanForNewCandidates()`. For every parked mention:
1. Compute a fingerprint of the current `ahg_mention_candidate` set (sorted CSV of `source|authority_id|fuseki_uri|display_name`)
2. Re-run `CandidateGeneratorService::generate()` (idempotent - replaces the candidate set)
3. Compute the new fingerprint and compare
4. If changed, set `ahg_mention_park.new_candidate_available = 1` and `new_candidate_check_at = NOW()`. If unchanged, set the flag to 0 and update the check timestamp.

Returns the count of mentions that JUST became flagged (was 0, now 1). Re-runs against a static authority store are no-ops.

Wire via cron or `php artisan schedule:run` - daily is plenty.

### Dashboard JSON

`GET /admin/authority-resolution/park/dashboard.json` returns:

```json
{
  "total_parked": 1,
  "total_new_candidate": 0,
  "by_archivist": [{"user_id": 900148, "name": null, "count": 1}]
}
```

For a future admin-dashboard widget that pulls counts without rendering the full screen.

## Task 9 - NER feedback capture

### Schema

```sql
CREATE TABLE ahg_ner_feedback (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    mention_id BIGINT UNSIGNED NOT NULL,
    ner_entity_id BIGINT UNSIGNED NOT NULL,
    decision_id BIGINT UNSIGNED NOT NULL,
    source_text MEDIUMTEXT NOT NULL,
    mention_value VARCHAR(1000) NOT NULL,
    mention_entity_type VARCHAR(20) NOT NULL,
    mention_offset_start INT UNSIGNED NULL,
    mention_offset_end INT UNSIGNED NULL,
    rejection_reason TEXT NOT NULL,
    archivist_user_id INT NOT NULL,
    ner_model_version VARCHAR(100) NULL,
    training_exported TINYINT(1) NOT NULL DEFAULT 0,
    exported_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_feedback_decision (decision_id),
    KEY idx_feedback_mention (mention_id),
    KEY idx_feedback_unexported (training_exported, created_at)
);
```

Idempotent CREATE TABLE lives in `packages/ahg-authority-resolution/database/install.sql`.

### Capture hook

The Task 5 reject action (`POST /admin/authority-resolution/review/{mention}/reject`) now accepts an optional `rejection_reason` form field. The signature of `DecisionRecorder::recordReject()` widens to `recordReject(int $mentionId, int $userId, ?string $reason = null)`.

After the audit row + state flip are written, `DecisionRecorder` calls `NerFeedbackService::captureFromRejection($decisionId, $reason)` inside a try/catch. Capture is best-effort - failure NEVER blocks the reject decision (the durable spine is `ahg_mention_decision` + `ahg_mention.state='rejected'`).

`captureFromRejection()` writes one `ahg_ner_feedback` row with:
- `source_text` = concatenated IO i18n text fields (same shape as `PromoteToMentionService::fetchSourceText()`)
- `mention_value`, `mention_entity_type` from `ahg_ner_entity`
- `mention_offset_start`/`_end` from `ahg_mention_context` (NULL when context wasn't computed)
- `ner_model_version` from context too
- `rejection_reason` - the archivist's note, or `"(no reason supplied)"` when empty

### Reject modal

`auth-res::_reject-modal` is included from the review screen. It replaces the previous inline confirm-only form with a Tailwind modal containing a `rejection_reason` textarea. The reason is OPTIONAL - empty submissions still reject and still record a feedback row.

### Export

`php artisan auth-res:export-ner-feedback [--format=jsonl|conll]` runs `NerFeedbackService::exportUnexported()`:
- Default `jsonl` - one JSON object per line
- `conll` - CONLL-2003 style flat tag file (B-REJ-<TYPE> / I-REJ-<TYPE> / O on whitespace-tokenised text)

Output path: `storage/app/auth-res/ner-feedback/<YYYYMMDD-HHMMSS>.<ext>`

Exported rows are marked `training_exported=1` and `exported_at=NOW()` in a single UPDATE after the file is fully written. Re-running the export with no new rejects produces an empty file and a 0-count.

JSONL shape:

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

### Retraining integration

Operator hands the exported file to the NER service at `/opt/ahg-ai` for the next retraining pass. The `label: "reject"` flag on every span tells the trainer this is a NEGATIVE example (model should NOT mark this as an entity), distinct from positive training data. The `rejection_reason` gives the human-readable annotation; a future tagger can cluster reasons (mistype-as-person, stopword, ambiguous role-word) into per-class loss terms.

## Files added / modified

Service layer:
- `src/Services/ParkQueueService.php` (NEW)
- `src/Services/NerFeedbackService.php` (NEW)
- `src/Services/DecisionRecorder.php` (modified - `recordReject` accepts `?string $reason`, calls `NerFeedbackService::captureFromRejection()` in try/catch)
- `src/Services/PromoteToMentionService.php` (modified - `fetchSourceText` made public for reuse)

Controllers:
- `src/Http/Controllers/ParkQueueController.php` (NEW)
- `src/Http/Controllers/AuthorityReviewController.php` (modified - `reject` reads `rejection_reason` from request)

Routes (added to `routes/admin.php`):
- `GET  /admin/authority-resolution/park` (`auth-res.park.index`)
- `POST /admin/authority-resolution/park/{mention}/unpark` (`auth-res.park.unpark`)
- `GET  /admin/authority-resolution/park/dashboard.json` (`auth-res.park.dashboard`)

Commands:
- `auth-res:scan-parked` (new)
- `auth-res:export-ner-feedback` (new)

Views:
- `resources/views/park.blade.php` (NEW - dedicated screen)
- `resources/views/_park-row.blade.php` (NEW - per-row partial)
- `resources/views/_park-dashboard-widget.blade.php` (NEW - dashboard widget partial)
- `resources/views/_reject-modal.blade.php` (NEW - reason textarea)
- `resources/views/review.blade.php` (modified - reject button now opens modal; modal include added)

Schema:
- `database/install.sql` (extended - `ahg_ner_feedback` CREATE TABLE IF NOT EXISTS)

Service provider (`AhgAuthorityResolutionServiceProvider`):
- Registers `NerFeedbackService` (depends on `PromoteToMentionService`)
- Registers `ParkQueueService` (depends on `CandidateGeneratorService` + `EvidenceScorer`)
- `DecisionRecorder` now also receives `NerFeedbackService`
- Adds `ScanParkedCommand` + `ExportNerFeedbackCommand` to the console-time command list

## Operator runbook

Park a mention from the review screen: click "Park for later", enter reason, submit. The mention moves to `state='parked'`, `ahg_mention_park` gets one row, and the mention disappears from the pending queue.

Find parked mentions: `/admin/authority-resolution/park`.

Unpark: click "Unpark + re-review" on the row. Confirms via `window.confirm()`, then re-runs candidate generation + evidence scoring and lands you on `/admin/authority-resolution/review/{id}` with `state='pending'` again.

Background scan: schedule `php artisan auth-res:scan-parked` daily. Green "new candidate" badge appears on rows where the candidate set has changed since parking.

Reject a mention: click "Reject as false positive" on the review screen, optionally fill in the reason, submit. The mention flips to `state='rejected'`, an audit row lands on `ahg_mention_decision`, and an `ahg_ner_feedback` row is captured.

Export feedback for retraining: `php artisan auth-res:export-ner-feedback --format=jsonl`. The file lands under `storage/app/auth-res/ner-feedback/`. `training_exported` rows are NOT re-exported on subsequent runs.
