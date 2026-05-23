# Spectrum 5.1 compliance dashboard

Phase C of the Spectrum integration adds **collection-wide compliance tracking** to Heratio. It tells you — at a glance — what proportion of your information objects have been through each of the 21 Spectrum 5.1 procedures, where bottlenecks are, and when individual procedures fall behind schedule.

## What it answers

- **"What's our Spectrum compliance percentage?"** — heatmap dashboard shows per-procedure completion across the whole collection.
- **"Which procedures are bottlenecked?"** — overdue counts surface per row.
- **"Where is this specific object in the procedural pipeline?"** — per-object compliance panel.
- **"What happens automatically after this procedure?"** — cross-procedure chain rules wire Acquisition → Cataloguing → Location etc.
- **"What's overdue right now?"** — `spectrum:overdue` cron drops Workbench notifications.

## Compliance dashboard

**Admin → Workflow → Spectrum compliance**, or directly: `/spectrum/dashboard`

Shows a row per Spectrum procedure with 5 status columns:

| Status | Meaning |
|---|---|
| Not started | No task has been created on this procedure for the object |
| In progress | Task exists, pending or claimed, not past the overdue threshold |
| Completed | A task on this procedure has been approved |
| Overdue | Pending task older than the overdue threshold (default 30 days, configurable) |
| Rejected | A task on this procedure was rejected (sticky — overrides later states for visibility) |

**CSV export:** the "Export CSV" button downloads the heatmap as a CSV for auditors and reporting tools.

## Per-object compliance panel

Any information object view page can embed the per-object panel via:

```php
@include('ahg-workflow::_spectrum-object-panel', ['informationObjectId' => $io->id])
```

The panel renders a 21-cell grid showing the object's status on each procedure with colour-coded badges. No action required — it's a read-only visualisation. Renders nothing (graceful no-op) if the Spectrum compliance tables aren't present yet.

## Chain rules — automate cross-procedure handoffs

**Admin → Workflow → Spectrum chain rules**, or: `/spectrum/chain`

A chain rule says *"when procedure X completes for an object, automatically spawn a task on procedure Y for the same object"*. The classic chain is:

1. **Acquisition** completes → spawn **Cataloguing**
2. **Cataloguing** completes → spawn **Location and movement control**

To wire that up: open the chain rules page, add two rules with the matching from/to procedures, set `Active`, and save. Next time a workflow task is approved by an authorized user, the system checks the rules and creates the downstream task automatically.

**Safe by design:**

- Self-chains (X → X) are rejected at save time
- Unknown procedure codes are rejected
- Double-spawning is prevented (won't create the same task twice for the same object)
- Chain spawn is best-effort — if it fails, the approval still succeeds

## Overdue scan (cron)

The `spectrum:overdue` Artisan command finds pending tasks past a configurable threshold and drops Workbench notifications:

```bash
# Default — 14-day threshold, log only
php artisan spectrum:overdue --dry-run

# Production — 30-day threshold, notify Johan via the Workbench bell
php artisan spectrum:overdue --days=30 --notify=johan

# Custom Workbench inbox path
php artisan spectrum:overdue --notify=registrar --inbox=/var/spool/workbench/notifications
```

The command groups overdue items by procedure so the user gets one notification per procedure (not one per overdue task — avoids spam). Suggested cron:

```
0 8 * * 1 cd /usr/share/nginx/heratio && php artisan spectrum:overdue --days=30 --notify=registrar
```

(Weekly Monday morning summary.)

## What it deliberately does NOT do

- Does not store compliance state as authoritative — the **task table is the source of truth**, the compliance cache table is derived.
- Does not enforce Spectrum compliance gates on workflow execution — it's an observability layer.
- Does not auto-create procedures for new objects — use the chain rules to opt in to that behaviour for the chains you care about.
- Does not currently produce PDF reports — CSV only. (PDF could be added if needed using an existing PDF export pattern from elsewhere in the codebase.)

## Database schema

Two new tables:

```sql
ahg_spectrum_object_compliance (
  object_id INT, object_type VARCHAR(50), spectrum_procedure VARCHAR(64),
  status VARCHAR(20), started_at, completed_at, last_task_id, last_computed_at
)

ahg_spectrum_chain_rule (
  id, from_procedure VARCHAR(64), to_procedure VARCHAR(64),
  trigger_event VARCHAR(20), is_active TINYINT(1), notes
)
```

Both have `VARCHAR(N)` with COMMENT enumerating valid values (no ENUMs per project rules).

## Related

- Phase A: `spectrum_procedure` column on `ahg_workflow`
- Phase B: 21-procedure starter pack via `workflow:seed-spectrum`
- This Phase C: the compliance dashboard / chain rules / per-object panel / overdue cron
- See also: `spectrum-procedure-pack.md` for the seed pack documentation
