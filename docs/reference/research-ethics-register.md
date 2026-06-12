# Research Ethics and Consent register - technical reference

Summary: a Research Operating System slice in `packages/ahg-research` recording a project's ethics approvals and consent basis. Mirrors the research-outputs slice (Service + Controller + self-contained route file + views, auto-installed via the `AhgResearchServiceProvider` `$installs` array, wired into `CommandCentreService::tools()`). Shipped Heratio v1.142.54 (this reference and the user guide corrected/added in v1.142.55, replacing an inaccurate auto-generated help stub).

## Storage

Table `research_ethics` (additive, auto-installed at boot, Schema::hasTable-guarded in one try/catch):

- `project_id`, `title`, `approval_type`, `reference_number`, `committee_name`
- `status`, `decision_date`, `expiry_date`
- `consent_basis`, `data_sensitivity`, `notes`
- `dmp_id` (nullable, FK-by-convention to `research_dmp.id`, validated to the same project)
- `owner_id`, `created_by`, timestamps

All enumerated columns are `VARCHAR`, never MySQL `ENUM`. No `ALTER` of existing tables.

## Dropdown taxonomies (ahg_dropdown, INSERT IGNORE)

- `research_ethics_approval_type`: human_subjects, animal, data_protection, biosafety, other
- `research_ethics_status`: not_required, pending, approved, conditions, expired, rejected
- `research_consent_basis`: informed_consent, legitimate_interest, public_task, anonymised, not_applicable
- `research_data_sensitivity`: none, personal, special_category, restricted

The consent-basis and sensitivity terms are generic governance concepts, jurisdiction-neutral (not GDPR/POPIA/HIPAA-specific); seed comments state this.

## Expiry flag

`expiryFlag()` returns `expired` (past expiry_date), `soon` (within 60 days), or null; terminal statuses (rejected / not_required) raise no flag. `summary()` returns totals, counts by status and type, and `expiring_soon` / `expired` counts surfaced as a warning banner.

## Routes

Under `/research/projects/{projectId}/ethics` (index/create/store/edit/update/show/destroy + `export.json`), all `[0-9]+`-constrained, web+auth, names `research.ethics.*`. Multi-segment under the `research` prefix (excluded from the slug catch-all), so the single-segment `/{slug}` archival-record catch-all never intercepts them.

## Wiring

`ResearchEthicsService` singleton + the `$installs` entry + a `research-ethics` route-file entry are chained into the already-discovered `AhgResearchServiceProvider`. `CommandCentreService::tools()` adds a Route::has-gated `ethics` tool so it appears on the researcher journey.

## Note

The slice's auto-generated help stub (`ethics-milestones.md`) described a non-existent "milestones/checkpoints" feature and was removed; this reference and `docs/help/research-ethics-register.md` are the accurate records.
