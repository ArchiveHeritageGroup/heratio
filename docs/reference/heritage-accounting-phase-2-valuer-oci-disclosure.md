# Heritage Accounting Phase 2 - Valuer Registry, OCI Tracking, and Disclosure Templates

**Issue:** #668 (Heritage accounting - GRAP 103 / IPSAS 45) Phase 2
**Package:** `packages/ahg-heritage-manage/`
**Status:** Shipped Phase 2 (Phase 3 reconciliation note + IPSAS 45 transitional rules outstanding)
**Date:** 2026-05-26

## What this phase ships

Three pieces of statutory heritage-accounting infrastructure that GRAP 103 (South Africa) and IPSAS 45 (international) both require, built in a jurisdiction-neutral way so the same machinery serves every market.

### 1. Valuer registry

GRAP 103.41 and IPSAS 45.69 require that certain heritage asset valuations are performed by an appropriately qualified valuer. Heratio now keeps a first-class registry of those valuers so revaluation entries can reference a specific accredited individual.

- Table: `ahg_valuer` (id, name, credential, professional_body, accreditation_number, email, phone, specialisations JSON, active, notes, timestamps).
- Admin UI: `/admin/heritage/valuers` (list / add / edit / soft-deactivate). Bootstrap 5 + bi-* icons.
- The existing `heritage_valuation_history` table gets a `valuer_id` FK added idempotently at boot (information_schema guard - no ALTER if the column already exists).
- Soft-deactivation only: deleting flips `active=0`, preserving historical references.

### 2. OCI / Revaluation Reserve tracking

Heritage revaluations under GRAP 103.51 / IPSAS 45.74 do not always flow to profit-and-loss. Surpluses post to Other Comprehensive Income (the Revaluation Reserve in net assets); reversals first reduce that reserve before spilling to P&L; on disposal any residual surplus is transferred to retained earnings rather than recycled through P&L.

- Table: `ahg_heritage_oci_movement` (information_object_id, heritage_asset_id, movement_type, amount DECIMAL(15,2), currency CHAR(3), valuation_date, valuer_id FK, valuation_method, reason, posted_to ENUM-like VARCHAR (OCI | P&L | Reserve), affected_period_start/end, created_by_user_id).
- Service: `AhgHeritageManage\Services\OciMovementService` with four entry points that encode the GRAP 103 / IPSAS 45 split rules so callers never have to reach into the standard themselves.
  - `recordRevaluation()` - sign-aware. Up = entire delta to OCI. Down = consume existing OCI surplus first, remainder to P&L.
  - `recordImpairment()` - consume existing OCI surplus first, remainder to P&L.
  - `recordReversal()` - unwind P&L impairment first up to prior P&L impairment, remainder restores OCI surplus.
  - `recordDisposal()` - gain/loss to P&L, residual OCI surplus transferred to Reserve (retained earnings) instead of cycling through P&L.
- Audit-trail integration: every insert writes a hashed `heritage.oci_movement.recorded` row via `AhgAuditTrail\Services\AuditService::log()` (#676 Phase 5 chain). Best-effort; financial movement is not blocked if the audit chain is unavailable.
- Admin UI: `/admin/heritage/oci` (filterable ledger view + period summary card + record-movement form).

### 3. Disclosure templates and renderer

Templates live in `packages/ahg-heritage-manage/templates/disclosures/`:

- `grap-103-note.md.template` - SA-jurisdictional template covering accounting policy, measurement basis, carrying amount, period movement table (OCI / P&L / Reserve split), impairment policy, valuer credentials, restrictions, and insurance.
- `ipsas-45-note.md.template` - international template, IPSAS-paragraph-anchored, includes the non-recognition disclosure required by IPSAS 45.71.
- `transitional-note.md.template` - for first-time adopters of GRAP 103 / IPSAS 45 covering the deemed-cost / disclosure-only relief and the recognition-completion plan.

Renderer: `php artisan heritage:disclosure-note --standard=grap-103|ipsas-45|transitional --period=YYYY-MM-DD..YYYY-MM-DD [--out=path]`.

Placeholder syntax is plain `{{ var }}` so the templates stay auditor-readable. Substituted variables include `asset_count`, `total_carrying_amount`, the five movement totals, the three posting-bucket totals, `valuer_list`, and `measurement_basis_summary`. Missing values render empty rather than crashing, so an incomplete dataset never breaks the audit pipeline.

## Routes added

| Method | Path                                         | Name                       |
|--------|----------------------------------------------|----------------------------|
| GET    | `/admin/heritage/valuers`                    | `heritage.valuer.index`    |
| GET    | `/admin/heritage/valuers/create`             | `heritage.valuer.create`   |
| POST   | `/admin/heritage/valuers`                    | `heritage.valuer.store`    |
| GET    | `/admin/heritage/valuers/{id}/edit`          | `heritage.valuer.edit`     |
| PUT    | `/admin/heritage/valuers/{id}`               | `heritage.valuer.update`   |
| DELETE | `/admin/heritage/valuers/{id}`               | `heritage.valuer.destroy`  |
| GET    | `/admin/heritage/oci`                        | `heritage.oci.index`       |
| GET    | `/admin/heritage/oci/create`                 | `heritage.oci.create`      |
| POST   | `/admin/heritage/oci`                        | `heritage.oci.store`       |

All routes are under the existing `admin` middleware group. The sidebar menu (`_heritage-accounting-menu.blade.php`) gains two Administration entries linking to the new screens.

## Out of scope (Phase 3)

- Reconciliation note (opening to closing carrying-amount reconciliation per IPSAS 45.88).
- IPSAS 45 transitional rules engine (programmatic application of the five-year relief windows; the transitional disclosure template is shipped, the engine is not).

## Files

- `packages/ahg-heritage-manage/database/install.sql` (appended new tables)
- `packages/ahg-heritage-manage/src/Services/OciMovementService.php`
- `packages/ahg-heritage-manage/src/Controllers/ValuerController.php`
- `packages/ahg-heritage-manage/src/Controllers/OciMovementController.php`
- `packages/ahg-heritage-manage/src/Console/Commands/HeritageDisclosureNoteCommand.php`
- `packages/ahg-heritage-manage/src/Providers/AhgHeritageManageServiceProvider.php`
- `packages/ahg-heritage-manage/templates/disclosures/grap-103-note.md.template`
- `packages/ahg-heritage-manage/templates/disclosures/ipsas-45-note.md.template`
- `packages/ahg-heritage-manage/templates/disclosures/transitional-note.md.template`
- `packages/ahg-heritage-manage/resources/views/valuers/{index,add,edit,_form}.blade.php`
- `packages/ahg-heritage-manage/resources/views/oci/{index,add}.blade.php`
- `packages/ahg-heritage-manage/resources/views/partials/_heritage-accounting-menu.blade.php` (menu link)
- `packages/ahg-heritage-manage/routes/web.php` (route registrations)
- `docs/help/heritage-disclosure-notes.md` (in-app help)
