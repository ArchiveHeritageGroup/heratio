# Authority Resolution - Assign / Workflow feature

The AHG Authority Resolution Engine lets an archivist **assign** a mention to
another archivist. Assignment routes the mention through the existing
**ahg-workflow** plugin, so the assignee picks the task up from their normal
workflow dashboard. The review-queue also supports **batch assign** across a
whole filtered set.

## Summary

- Assign one mention from the review screen, or one-or-many from the queue.
- Each assignment creates (or re-targets) an `ahg_workflow_task` row whose
  `object_type = 'ahg_mention'`.
- The mention's own assignment columns are written alongside the workflow
  task so the queue and review screen can show "Assigned to ...".
- If the ahg-workflow plugin is not installed, assignment still records the
  mention columns and degrades gracefully (no workflow task).

## Schema - `ahg_mention` assignment columns

Four columns on `ahg_mention` back the feature (in `install.sql`):

| Column | Type | Purpose |
|---|---|---|
| `assigned_to_user_id` | `INT NULL` | `user.id` the mention is assigned to |
| `assigned_by_user_id` | `INT NULL` | `user.id` of the archivist who assigned it |
| `assigned_at` | `DATETIME NULL` | when the mention was last assigned |
| `workflow_task_id` | `BIGINT UNSIGNED NULL` | linked `ahg_workflow_task.id` |

Indexes: `idx_mention_assigned (assigned_to_user_id, state)` and
`idx_mention_workflow_task (workflow_task_id)`.

## Workflow-plugin integration

A minimal workflow definition is seeded into the ahg-workflow tables:

- `ahg_workflow` row **"Authority Resolution Review"** -
  `scope_type='global'`, `applies_to='ahg_mention'`, `is_active=1`.
- One `ahg_workflow_step` named **"Review"** (`step_type='review'`,
  `action_required='approve_reject'`).

Seeded by `database/seed_workflow.sql` and auto-seeded on first boot by
`AhgAuthorityResolutionServiceProvider` (insert-if-missing, keyed on the
workflow name). `ahg_workflow_task` is polymorphic on
`object_id` + `object_type`, so `object_type='ahg_mention'` is valid.

### `WorkflowService::assignToUser()`

Added to `ahg-workflow` (`packages/ahg-workflow/src/Services/WorkflowService.php`):

```php
public function assignToUser(int $taskId, int $userId, int $performedBy): bool
```

Mirrors `claimTask()` but assigns to an arbitrary user instead of self-claim:
sets `ahg_workflow_task.assigned_to = $userId`, `status = 'claimed'`,
`claimed_at = now()`, and logs an `ahg_workflow_history` row with
`action = 'reassigned'` (the `workflow_history_action` taxonomy code for a
delegated assignment). Additive only - existing methods untouched.

## AssignmentService

`AhgAuthorityResolution\Services\AssignmentService`:

- `assign(int $mentionId, int $archivistUserId, int $byUserId): array` -
  returns `['ok'=>bool, 'workflow_task_id'=>?int, 'error'=>?string]`.
  If the mention already has a `workflow_task_id`, the existing task is
  **re-assigned** (no second task is created). Otherwise a task is started on
  the Authority Resolution Review workflow and then assigned. The mention's
  four assignment columns are written inside a transaction.
- `assignBatch(array $mentionIds, int $archivistUserId, int $byUserId): array` -
  loops `assign()`, returns `['assigned'=>int, 'failed'=>int, 'errors'=>[]]`.
  One failure does not abort the rest.
- `archivists(): array` - eligible assignees. Restricts to admin / editor /
  archivist ACL groups (via `acl_user_group` + `acl_group_i18n`) when those
  tables exist; otherwise lists all active `user` rows. Display name prefers
  `actor_i18n.authorized_form_of_name`, then `user.username`.

Note: archivists come from the Qubit `user` table (the Laravel `users` table
is empty on this install).

## Routes

All under `/admin/authority-resolution`, `web` + `admin` middleware:

| Method | Path | Name | Purpose |
|---|---|---|---|
| POST | `/review/{mention}/assign` | `auth-res.review.assign` | assign from the review screen |
| POST | `/queue/assign` | `auth-res.queue.assign` | single or batch assign from the queue |
| GET | `/archivists.json` | `auth-res.archivists.json` | JSON archivist list for pickers |

`AssignmentController` handles all three. The review and queue controllers
also pass `$archivists` + assignee display names to their views.

## Batch mechanics (queue)

- Each queue row has a checkbox; the header has a "select all on this page"
  checkbox.
- A "select all N matching filter" button selects every mention id matching
  the current entity-type / state / object-id filter - not just the visible
  page. The controller computes the full filtered id list server-side and
  hands it to the page as JSON.
- A sticky bottom action bar appears when >= 1 row is checked: an archivist
  `<select>` + "Assign selected" button posts `mention_ids[]` +
  `archivist_user_id` to `auth-res.queue.assign`.
- The selection model is a JS `Set`; hidden `mention_ids[]` inputs are
  rebuilt on every change. Pure vanilla JS + Bootstrap 5 modal - no build
  step.

## UI

Bootstrap 5 throughout (`bi-*` icons, `atom-btn`/`btn-*`, BS5 modals):

- **Review screen** - an "Assign" / "Re-assign" button in the right-region
  action column opens `_assign-modal.blade.php` (archivist `<select>`). The
  current assignee and linked workflow task id are shown when set.
- **Queue** - per-row "Assign" button (opens a scoped modal), checkboxes +
  batch action bar, and an "Assigned to" column.

## Modal trigger hardening (declarative)

Every modal-opening button in the authority-resolution views uses the
**declarative** Bootstrap 5 pattern - `data-bs-toggle="modal"` +
`data-bs-target="#<modal-id>"` - rather than custom JS
(`new bootstrap.Modal(el).show()`).

Custom-JS opening silently no-ops whenever `window.bootstrap` is not exposed
as a reachable global (asset-bundling differences, deferred loads, CSP). The
declarative attributes are auto-wired by Bootstrap's own delegated click
handler regardless of whether `window.bootstrap` is reachable - so the
trigger is robust.

The per-row **Assign** button on the queue previously opened its modal via
`new window.bootstrap.Modal(...).show()`. It is now declarative
(`data-bs-toggle="modal" data-bs-target="#ar-queue-assign-modal"`). Per-row
population (the hidden `mention_ids[]` value and the `#NN` label) is done in
a `show.bs.modal` listener that reads `event.relatedTarget` to recover the
clicked button and its `data-mention-id`. All review-screen action buttons
and every modal partial's close/cancel button were already declarative.

## Queue "Source" column

The queue table carries a **Source** column linking each mention to its
source information object, with a digital-object indicator:

- `AuthorityReviewController::queue()` LEFT JOINs `slug` (IO URL),
  `information_object` (`identifier` label) and the Master `digital_object`
  per IO (`usage_id = 140`, de-duplicated via a `MIN(id)` grouped subquery).
- The cell renders a link to the IO show page (`url('/'.$io_slug)`, the
  `/{slug}` catch-all route) plus a `bi-*` icon: `bi-file-pdf` for
  `application/pdf`, `bi-file-image` for any `image/*`, `bi-file-earmark`
  for any other digital-object MIME, and a muted dash when the IO has no
  Master digital object.
- No slug -> the cell degrades to a plain `identifier` / `Object #N` label
  with no link. `slug.object_id` is UNIQUE so the join cannot multiply rows.
