# ahg-workflow

Workflow management for Heratio - task approval, SLA tracking, publish gates, Spectrum 5.1 procedure pack, visual diagram, drag-drop designer, and overdue-task email notifications (heratio#143, #674, and the Spectrum series).

## Purpose

- Workflows = named, ordered chain of `step` rows, each with an assignee role and SLA
- Tasks = a workflow instance bound to a target object (IO, actor, accession, etc.)
- Claim / release / approve / reject lifecycle with full audit
- Pool view + my-tasks view + queues view
- Publish gates (`workflow_publish_gate_rule`) that block IO publication until upstream tasks close
- Spectrum 5.1 procedure pack (21 codes) - install / re-install + per-procedure compliance dashboard + chain rules + CSV export
- Visual workflow diagram (read-only) + task progress overlay
- Drag-drop workflow designer (`/workflow/{id}/designer`)
- Daily overdue-task notification (`workflow:notify-overdue`, scheduled at 09:00)

## Install

Auto-discovered. The ServiceProvider:

1. Binds `WorkflowService` as a singleton
2. Loads routes + views (`web` group)
3. Registers three Artisan commands (`SeedSpectrumCommand`, `SpectrumOverdueCommand`, `WorkflowNotifyOverdueCommand`)
4. Schedules `workflow:notify-overdue` daily at 09:00 with `withoutOverlapping()`

Tables come from `database/install.sql` (`ahg_workflow`, `ahg_workflow_step`, `ahg_workflow_task`, `ahg_workflow_publish_gate_rule`, plus the Spectrum bookkeeping rows).

## Routes (highlights)

All under `admin` middleware:

- Dashboards: `/workflow`, `/workflow/my-tasks`, `/workflow/pool`, `/workflow/queues`, `/workflow/overdue`, `/workflow/my-work`, `/workflow/team-work`
- Task: `/workflow/task/{id}` plus `/claim`, `/release`, `/approve`, `/reject`
- Diagram (#143): `/workflow/{id}/diagram`, `/workflow/task/{taskId}/diagram`, `/workflow/{id}/designer`, `/workflow/{id}/designer/save`
- Admin workflows: `/workflow/admin`, `/workflow/admin/create`, `/workflow/admin/{id}/edit`
- Spectrum: `/spectrum/dashboard`, `/spectrum/export.csv`, `/spectrum/chain` (+ save / delete), `/workflow/admin/install-spectrum`
- Publish gates: `/workflow/admin/gates`, `/workflow/admin/gates/edit/{id?}`, `/workflow/admin/gates/{id}/delete`
- Steps: `/workflow/admin/{wfId}/step/add`, `/workflow/admin/step/{id}/delete`, plus form variants
- Publish readiness / simulate: `/workflow/publish-readiness/{objectId}`, `/workflow/publish-simulate/{objectId}`

## Key classes

| Class | Role |
|---|---|
| `Services\WorkflowService` | Workflow + task CRUD, lifecycle, queue queries |
| `Services\WorkflowEdgeService` | Step-to-step edge logic for the designer |
| `Services\WorkflowDiagramService` | Server-side diagram rendering |
| `Services\SpectrumComplianceService` | Spectrum dashboard + chain-rule engine |
| `Services\SpectrumProcedureCatalog` | The 21 Spectrum 5.1 codes + label resolver + normaliser |
| `Mail\WorkflowTaskApprovedMail` / `RejectedMail` / `OverdueMail` | Notifications |
| `Console\Commands\WorkflowNotifyOverdueCommand` | Daily overdue sweep (`workflow:notify-overdue`) |
| `Console\Commands\SeedSpectrumCommand` | Install / re-install the Spectrum pack |

## Tests

`tests/Feature/` covers:

- `SeedSpectrumCommandTest` - Spectrum pack installer idempotency
- `SpectrumProcedureTest` - catalog shape (21 codes, unique, snake_case) + workflow service plumbing
- `SpectrumComplianceTest` - compliance dashboard aggregation
- `WorkflowDiagramTest`, `WorkflowEdgeTest`, `WorkflowTaskDiagramTest` - diagram rendering
- `WorkflowDesignerSaveTest` - drag-drop designer persistence

## Notes

- Spectrum codes are snake_case (`object_entry`, `cataloguing`, `loans_in`, ...). `SpectrumProcedureCatalog::normalize()` trims input and returns `null` for unknown codes; the service then stores `NULL` rather than the bogus value.
- The overdue scheduler uses `withoutOverlapping()` so a slow run cannot stack with the next 09:00 trigger.
