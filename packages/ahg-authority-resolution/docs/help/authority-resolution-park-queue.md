# Authority Resolution - Park Queue

The park queue is the engine's "I cannot decide yet" outlet. Mentions that are parked stay alive, get re-scanned for new candidates, and re-surface when the upstream authority store changes. This article covers when to park, how to find parked items, what "new candidate available" means, and the re-review flow.

## Lifecycle

```
mention pending
  |
  +--- archivist clicks Park --->  ahg_mention_park row, state = parked
                                         |
                          (cron: auth-res:scan-parked)
                                         |
                                         v
                          candidate set changed?
                                  /         \
                              yes             no
                                /               \
        new_candidate_available = 1          (no change, just update
                  |                           new_candidate_check_at)
                  v
        archivist sees flag, clicks Unpark
                  |
                  v
        ParkQueueService::unparkAndRereview()
                  |
                  v
        mention pending (with fresh candidates)
```

`park` is the only non-terminal decision in the five-outcome tree. All other outcomes (`link`, `link_different`, `create_new`, `reject`) are terminal.

## When to park

- The mention is real but the right authority record does not exist yet, and an import is pending.
- The mention is real but the document context is ambiguous and needs off-line research.
- The mention is real but the candidate scoring is unsafe (close ties at the top, or the wrong card is highest).
- The mention may be a NER false positive but you are not sure enough to reject. Rejection writes NER training data, so you want it to be confident; if you cannot be confident, park.

## The park screen

`/admin/authority-resolution/park`

### Top-line tiles

- **Total parked** - count of active `ahg_mention_park` rows.
- **New candidate available** - count of rows where the background scan saw the candidate set change.
- **Archivists involved** - count of distinct `parked_by_user_id`.

### Filters

- **Parked by** - dropdown of users who have parked anything. Defaults to "Mine" (the logged-in user).
- **Entity type** - PERSON / ORG / GPE / PLACE / LOC.
- **New candidate only** - boolean, shows only rows the scan job has flagged.
- **Parked at** - date range.
- **Reason** - text search (`LIKE`) inside the park reason.
- **Sort** - `parked_at_desc` (default), `parked_at_asc`, `entity_type`, `new_candidate` flag.

### Per-row actions

Each row shows the mention value, entity type, parked-by user, parked-at timestamp, the reason, and an `NEW CANDIDATE` badge when the background scan has flagged it.

| Action | What it does |
|---|---|
| **Unpark + review** | Calls `ParkQueueService::unparkAndRereview()`, regenerates candidates, re-scores evidence, redirects to the review screen with `state = pending`. |
| **Show context** | Expands the surrounding text and the original `ahg_ner_entity` row inline. |
| **Discard** | Permanent reject. Writes `decision_type = reject` and removes the park row in one transaction. |

## What "new candidate available" means

The background scan `auth-res:scan-parked` walks every parked row and computes a **fingerprint** of the current candidate set: a sorted CSV of `source|authority_id|fuseki_uri|display_name` tuples. If the fingerprint has changed since the last scan, the job sets `new_candidate_available = 1` and records `new_candidate_check_at = NOW()`. If it has not changed, the flag stays at 0 and only the check timestamp is updated.

Triggers that typically change the fingerprint:

- A new external adapter went live (VIAF, Wikidata, GeoNames, ...).
- A MARC or EAD import added authority records to the local store.
- A previously stub Fuseki adapter started returning rows.

The scan is cheap (one candidate-generator pass per parked mention) and idempotent. Suggested cron: hourly.

```
0 * * * * www-data /usr/bin/php /usr/share/nginx/heratio/artisan auth-res:scan-parked >/dev/null 2>&1
```

## Re-review flow

`POST /admin/authority-resolution/park/{mention}/unpark` runs `ParkQueueService::unparkAndRereview($mentionId, $userId)`:

1. `DELETE FROM ahg_mention_park WHERE mention_id = ?`.
2. `UPDATE ahg_mention SET state = 'pending'` for that mention.
3. `CandidateGeneratorService::generate($mentionId)` re-queries every adapter.
4. `EvidenceScorer::scoreAllForMention($mentionId)` re-scores the new set.
5. Redirects to `/admin/authority-resolution/review/{id}`.

The candidate list may be empty after re-review. The review screen handles this by surfacing the "Create new" path as the obvious next step.

## Bulk re-review

When you know a wave of parked mentions can now be decided (an import landed, a new adapter shipped), use the artisan command:

```
$ sudo -u www-data php artisan auth-res:reprocess-parked --since=2026-05-01
Re-reviewing 1 parked mention(s) (parked >= 2026-05-01)...
  mention 24: unparked, 2 candidates, 2 scored.
Done. 1 re-reviewed, 0 failed, 1 now have at least one candidate.
```

Options:

- `--since=YYYY-MM-DD` - re-review every mention parked on or after the given date.
- `--limit=0` - cap (0 = no cap).
- `--user-id=0` - archivist user id to attribute the unpark. 0 means "system".

The command does the same thing as the per-row "Unpark + review" button, but in bulk. It does **not** make decisions for you - it just moves the mentions back to `pending` so they re-surface on the review queue.

## Data model

`ahg_mention_park`:

| Column | Purpose |
|---|---|
| `mention_id` | UNIQUE - one active park row per mention |
| `parked_by_user_id` | For the "My parked" filter and per-archivist counts |
| `parked_at` | Timestamp; sort key and `--since` filter |
| `reason` | Free text, `LIKE`-searchable |
| `new_candidate_available` | 0 / 1, set by `scan-parked` |
| `new_candidate_check_at` | Timestamp of last scan |

## Dashboard widget

`ParkQueueService::countsByArchivist()` returns `[archivist_user_id => count]`. The partial `auth-res::_park-dashboard-widget` renders this as a sortable list, with "Mine" wired to the logged-in user. The widget is reusable from any admin dashboard.

A JSON endpoint at `GET /admin/authority-resolution/park/dashboard.json` exposes the same counts without rendering the screen:

```json
{
  "total_parked": 1,
  "total_new_candidate": 0,
  "by_archivist": [{"user_id": 900148, "name": null, "count": 1}]
}
```

## Edge cases

- **Unpark with no candidates** - `unparkAndRereview()` always returns. The candidate list may be empty; the review screen handles this with a "create new" prompt.
- **Re-parking** - parking an already-parked mention is a no-op. The UNIQUE constraint on `mention_id` prevents duplicates; the existing row's `reason` is updated.
- **Park then reject** - rejecting from the review screen deletes the park row in the same transaction; you do not need to "unpark to reject".
- **Park then create-new** - same: terminal decisions sweep the park row away.

## Related

- "AHG Authority Resolution - User Guide" - the mental model and the five outcomes.
- "Authority Resolution - Review Screen Reference" - the screen the unpark flow lands on.
- "Authority Resolution - CLI Commands" - `auth-res:scan-parked` and `auth-res:reprocess-parked` in detail.
