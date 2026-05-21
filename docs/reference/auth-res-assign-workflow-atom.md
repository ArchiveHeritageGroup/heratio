# Authority Resolution - Assign / Workflow (AtoM Heratio side)

Task 12 of the AHG Authority Resolution Engine on the AtoM Heratio side.
Archivists can assign an authority-resolution mention - from the review screen
or the queue - to another archivist. Assignment routes the mention through the
existing Workflow plugin (`ahgWorkflowPlugin`). The queue also supports batch
assign, including "select all matching the current filter".

This is the AtoM-AHG (Symfony 1.4) counterpart of the Laravel-side build
documented in `auth-res-assign-workflow.md`. Both sides converge on the same
`ahg_mention` columns and the same workflow-task contract.

## Schema

Four columns + two keys on `ahg_mention` (plugin
`ahgAuthorityResolutionPlugin`):

- `assigned_to_user_id INT NULL` - `user.id` of the assignee archivist
- `assigned_by_user_id INT NULL` - `user.id` of the archivist who assigned
- `assigned_at DATETIME NULL` - timestamp of the most recent assignment
- `workflow_task_id BIGINT UNSIGNED NULL` - `ahg_workflow_task.id`
- keys `idx_mention_assigned (assigned_to_user_id)` and
  `idx_mention_workflow_task (workflow_task_id)`

These are in the `CREATE TABLE ahg_mention` block of
`ahgAuthorityResolutionPlugin/database/install.sql` for fresh-install parity.

## Workflow definition seed

`ahgAuthorityResolutionPlugin/database/seed_workflow.sql` seeds one
"Authority Resolution Review" workflow (`ahg_workflow.id = 200`,
`scope_type='global'`, `applies_to='ahg_mention'`, `is_active=1`) plus one
`ahg_workflow_step` ("Review", `ahg_workflow_step.id = 200`,
`step_type='review'`, `pool_enabled=1`). Ids 200/200 sit clear of the
`ahgWorkflowPlugin` seed range (1, 100, 101). Run once against the `archive`
DB.

The workflow id is passed explicitly to `WorkflowService::startWorkflow()`.
That matters: `startWorkflow()` only does an `information_object` scope lookup
when no workflow id is supplied. Passing id 200 means an `ahg_mention` object
never has to satisfy `getApplicableWorkflow()`'s `information_object`-shaped
query.

## Reaching ahgWorkflowPlugin's WorkflowService from our plugin

`WorkflowService` lives at
`ahgWorkflowPlugin/lib/Services/WorkflowService.php` and is in the **root
namespace** (no `namespace` declaration) with **no PSR-4 autoload**. From
`ahgAuthorityResolutionPlugin` it is reached by an explicit `require_once` of
a sibling path - the two plugins are siblings under `atom-ahg-plugins/`:

```php
$path = dirname(__FILE__)
      . '/../../../ahgWorkflowPlugin/lib/Services/WorkflowService.php';
if (is_file($path)) { require_once $path; }
$wf = class_exists('WorkflowService', false) ? new \WorkflowService() : null;
```

The `class_exists` guard gives graceful degradation: if `ahgWorkflowPlugin`
is absent, `AssignmentService` still writes the `ahg_mention` assignment
columns and returns `ok` with `workflow_task_id = null`.

`ahgWorkflowPlugin` itself is **not modified**. `WorkflowService` already
ships `startWorkflow()`, `assignToUser()` and `getApplicableWorkflow()`.

## AssignmentService

`ahgAuthorityResolutionPlugin/lib/Services/AssignmentService.php`, namespace
`AtomFramework\Services\AuthorityResolution`. Pure Capsule, `date('Y-m-d
H:i:s')` (no Laravel app helpers).

- `assign(int $mentionId, int $archivistUserId, int $byUserId): array` -
  returns `['ok'=>bool,'workflow_task_id'=>?int,'error'=>?string]`. If the
  mention already carries a `workflow_task_id`, the existing task is
  re-assigned via `WorkflowService::assignToUser()`; otherwise
  `startWorkflow($mentionId, $byUserId, 'ahg_mention', 200)` then
  `assignToUser()`. The `ahg_mention` columns are always updated. Whole body
  is wrapped in `DB::transaction`.
- `assignBatch(array $mentionIds, int $archivistUserId, int $byUserId):
  array` - loops `assign()`, returns `['assigned'=>int,'failed'=>int,
  'results'=>array]`. One failure does not abort the rest.
- `archivists(): array` - eligible assignees. Joins `user` ->
  `acl_user_group` -> `acl_group_i18n` (the AtoM ACL group name lives in the
  i18n table) filtered to `editor / administrator / contributor /
  translator`; falls back to all users if that join yields nothing.

## Module actions + routes

Three actions added to
`modules/authorityResolution/actions/actions.class.php` (each loads
`AssignmentService` via the plugin's existing `require_once` loader pattern):

- `executeAssign` - `POST /admin/authorityResolution/:id/assign`, body
  `archivist_user_id` -> `AssignmentService::assign`
- `executeBatchAssign` - `POST /admin/authorityResolution/assign-batch`, body
  `mention_ids[]` + `archivist_user_id` -> `assignBatch`
- `executeArchivistsJson` - `GET /admin/authorityResolution/archivists.json`

Routes registered in
`config/ahgAuthorityResolutionPluginConfiguration.class.php` via the existing
`AtomFramework\Routing\RouteLoader` pattern:

```php
$r->any('ar_auth_res_archivists_json', '/admin/authorityResolution/archivists.json', 'archivistsJson');
$r->post('ar_auth_res_batch_assign', '/admin/authorityResolution/assign-batch', 'batchAssign');
$r->post('ar_auth_res_assign', '/admin/authorityResolution/:id/assign', 'assign', ['id' => '\d+']);
```

`archivists.json` and `assign-batch` sit on distinct static paths so they are
not shadowed by the `:id/...` patterns.

## UI (Bootstrap 5, SF1.4 templates)

- Review screen (`reviewSuccess.php`): an **Assign** button in the action
  region opens the BS5 modal partial `_assignModal.php` (archivist `<select>`
  + submit). Current assignee is shown when set. The button appears both for
  pending mentions and for already-decided ones (re-routing stays possible).
- Queue (`indexSuccess.php`): a checkbox column with a header "select all"
  checkbox, a "select all matching the current filter" link (covers the whole
  filtered set across all pages, fed by `allMatchingIds` from the action), a
  per-row **Assign** button (opens a single-row modal), an **Assigned to**
  column, and a sticky batch bar (archivist `<select>` + "Assign selected")
  that POSTs `mention_ids[]` to `assign-batch`.
- Checkbox + modal logic is vanilla JS in an inline `<script>` that carries
  the CSP nonce: `sfConfig::get('csp_nonce', '')`.

The per-mention assign URL for the queue's row modal is built from
`url_for('@ar_auth_res_assign?id=0')` with `0` swapped for an `__ID__` token
client-side - `url_for()` cannot take a non-numeric `:id` because the route
declares `id => '\d+'`.

## Demo results

Run against the `archive` DB after `sudo -u www-data php symfony cc` and the
workflow seed:

- Single assign of mention #1 created `ahg_workflow_task` #1 (`workflow_id=200`,
  `object_type='ahg_mention'`, `status='claimed'`); the `ahg_mention` row got
  `assigned_to_user_id`, `assigned_by_user_id`, `assigned_at`,
  `workflow_task_id=1`.
- Re-assigning mention #1 reused task #1 (no new task row).
- `assignBatch([2,3,4])` produced 3 tasks + 3 updated mentions
  (`assigned=3, failed=0`).
- `/admin/authorityResolution` and `/admin/authorityResolution/1/review` both
  return HTTP 200 with the full Assign UI rendered (per-row buttons, select-all,
  batch bar, "Assigned to" column, assign modals).

## Review-screen enhancements (2026-05-20)

Two follow-up enhancements to the Authority Resolution review screen, both in
`ahgAuthorityResolutionPlugin` only (no `ahgWorkflowPlugin` change).

### View full context

The review screen left region gains a **"View full context"** button next to
the existing "View full document text" button. It opens a `modal-xl` BS5 modal
(`_contextModal.php`) that shows the entire source text of the mention's
information object with the mention occurrence `<mark>`-highlighted and the
enclosing paragraph subtly shaded.

- New action `executeContext` -> `GET /admin/authorityResolution/:id/context`
  (route `ar_auth_res_context`, `:id` requirement `\d+`). Returns JSON
  `{ ok, source_text, offset_start, offset_end, paragraph_start,
  paragraph_end, entity_value }`.
- `source_text` is the concatenated IO i18n descriptive fields, produced by
  `PromoteToMentionService::fetchSourceText(int $objectId)` (changed from
  `private` to `public` so the action can reuse the exact reconstruction the
  mention offsets index into). `object_id` comes from the `ahg_mention` row.
- Offsets come from `ahg_mention_context`. They may be NULL (on-demand backfill
  that found no match) - the modal then shows the full text plus a "exact
  position not recorded" note.
- `ahg_mention_context` stores **byte** offsets (`ContextDerivationService`
  uses `strlen`/`stripos`/`substr`). `executeContext` converts them to JS
  String code-unit offsets (`mb_strlen` of the byte-prefix) before sending, so
  the browser's `String.slice()` splices `source_text` at the correct boundary
  for multibyte text.
- The modal JS builds the highlight by slicing the raw string at the offsets,
  HTML-escaping each slice independently, then concatenating with the `<mark>`
  / paragraph-shading wrapper tags (escape-then-splice - tags are never spliced
  into already-escaped text).

### Optional reason / message on Assign

The Assign modal (`_assignModal.php`), the queue per-row assign modal and the
queue batch-assign bar all gain an optional **"Reason / message (optional)"**
field (`<textarea name="reason">`, or a text input in the batch bar).

- `AssignmentService::assign()` and `assignBatch()` take an optional
  `?string $reason = null` final parameter; the batch applies one reason to
  every mention.
- `executeAssign` / `executeBatchAssign` read `reason` from the request
  (empty string normalises to NULL) and pass it through.
- The reason is stored as the workflow task's assignment comment **without
  modifying `ahgWorkflowPlugin`**: `ahg_workflow_task` has no generic
  `notes`/`comment` column (only `decision_comment`), so `AssignmentService`
  writes an `ahg_workflow_history` row itself via Capsule -
  `action='reassigned'` (same code `WorkflowEventService` uses for
  reassignment events), `comment` = the reason, `object_type='ahg_mention'`,
  `workflow_id`/`workflow_step_id` copied off the task. No schema change.
- The history write is best-effort (a missing `ahg_workflow_history` table is
  swallowed) so the assignment itself always succeeds.

### Demo results (2026-05-20, `archive` DB)

- `executeContext` payload for matched mention #15: `ok=true`,
  `source_text`=846 chars, `offset_start=497`, `offset_end=503`,
  `paragraph_start=12`, `paragraph_end=839`, `entity_value="Gdansk"`; the
  character-offset slice of `source_text` is exactly the entity value.
- For unmatched mention #1 (no context offsets): `ok=true`, full
  `source_text`, all four offsets `NULL` - handled gracefully.
- `AssignmentService::assign(1, 701, 701, 'test reason ...')` returned
  `workflow_task_id=1`; an `ahg_workflow_history` row was created with
  `action='reassigned'`, `object_type='ahg_mention'` and `comment` = the reason.
- `/admin/authorityResolution/15/context` is a registered route (returns 403
  at the ACL gate when unauthenticated; a bogus sibling path returns 404).

## Declarative-modal fix + queue Source column (2026-05-20)

### Queue Assign button fix

The per-row Assign button on the queue (`/admin/authorityResolution`,
`.ar-row-assign` in `indexSuccess.php`) did nothing when clicked. The cause: the
inline script opened `#ar-queue-assign-modal` with
`new bootstrap.Modal(el).show()`, which depends on `window.bootstrap` being a
reachable global. The AtoM theme bundle does not always expose that global, so
`queueModal` was `null` and the click was a no-op.

Fix - the modal trigger is now declarative. The `.ar-row-assign` button carries
`data-bs-toggle="modal" data-bs-target="#ar-queue-assign-modal"`, so Bootstrap's
own delegated listener opens the modal whether or not `window.bootstrap` is a
global. The dead `new bootstrap.Modal(...)` / `queueModal.show()` lines were
removed and replaced with a `show.bs.modal` listener on the modal element: it
reads `event.relatedTarget` (the button that opened it) and copies that button's
`data-mention-id` onto the form `action` (`assignUrlTpl.replace('__ID__', id)`)
and `data-mention-label` onto the label text. The button keeps its
`data-mention-id` / `data-mention-label` attributes.

The review screen (`reviewSuccess.php`) and its modals (link-different, park,
reject, context, assign, full-text) were checked - they were all already
declarative `data-bs-toggle="modal"`, so no change was needed there. The
batch-assign sticky bar is a plain form submit and its checkbox-driven
show/hide JS does not use Bootstrap, so it was left as is.

This is the canonical pattern for AR modals: prefer declarative
`data-bs-toggle="modal"` triggers; use a `show.bs.modal` handler only to
populate per-row data into a shared modal.

### Queue Source column - digital-object indicator

`executeIndex` already LEFT JOINed `slug` + `information_object_i18n` so the
queue's Source IO column links to the IO show page. The query now also LEFT
JOINs `digital_object` on `dobj.object_id = m.object_id AND dobj.parent_id IS
NULL` (master DO only - skips thumbnail / reference derivatives) and selects
`do_mime_type`, `do_media_type_id`, `do_name`. No IO has more than one master
DO or more than one slug, so the LEFT JOINs do not multiply queue rows (verified
1359 joined rows = 1359 `ahg_mention` rows).

The Source column in `indexSuccess.php` renders a small icon after the IO link,
classified from the master DO's mime type / filename extension:
`fas fa-file-pdf` (red) for PDF, `fas fa-file-image` (blue) for images, a
generic `fas fa-file` (muted) for any other digital object, and nothing when
the IO has no digital object. The icon `title`/`aria-label` includes the mime
type. When the IO has no slug the column degrades to the muted title text (or
`Object #<id>`), no link.
