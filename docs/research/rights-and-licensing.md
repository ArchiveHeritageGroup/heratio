# Rights & Licensing — Research module

This document replaces the previous Rights & Licensing notes for the Research area. It lists observed gaps, incomplete code, suggested enhancements, and a staged delivery plan. Use this as the canonical checklist for making the Research module rights-compliant and exporter-ready.

1. Executive summary

The Research module integrates with multiple platform components that carry rights, donor, and licence obligations (accessions, repository deposits, donor agreements, and exports). The codebase contains partial surface area for rights and licensing, but a focused audit + a few engineering tasks are required before we can claim feature-completeness:

- Missing: consistent rights enforcement across research exports and AI-produced artefacts.
- Incomplete: exporter inclusion of licence and donor-restriction metadata (RiC / JSON‑LD / IIIF) in some Research → Repository flows.
- Suggested: provenance-first approach (every rights decision / licence assertion is produced as an auditable event and attached to the exported manifest).

2. Observed gaps (what to fix first)

- Export completeness: Research-driven exports (publication packages, replication packs, DMP attachments, IIIF/JSON‑LD manifests) sometimes omit licence, donor, or embargo metadata. This makes downstream access control and legal compliance brittle.
- Rights provenance: there is no single consistent provenance record for where a licence assertion originated (project, user, donor agreement, external licence). ai_provenance captures AI calls but not human licence decisions.
- Enforcement vs description mismatch: the descriptive model (metadata fields on records) is not always wired to the enforcement layer (access control middleware). A retention/embargo/licence recorded on a research output is not always consulted by download endpoints.
- UI clarity: the Research admin UI does not surface a clear, centralised "Licence & Access" panel per output where curators can read and set licence, embargo, and donor restrictions together.
- Tests: no automated tests assert that licence metadata survives export and that access endpoints respect that metadata.

3. Incomplete code (concrete places to inspect)

Check (or add) the following code areas — these are likely places where the work is partial or absent:

- packages/ahg-research/src/Services/ResearchExportService.php (or similar): is the exporter composing licence + donor restriction nodes in exports?
- packages/ahg-ric/src/Services/RiCExportService.php (or equivalent): does it accept and map Research licence/restriction objects into RiC relations?
- packages/ahg-repository/src/Controllers/RepositoryDownloadController.php: does download enforce licence/embargo checks derived from Research output metadata?
- packages/ahg-research/resources/views/research/output-licence.blade.php (or similar): centralised UI for setting licence + embargo may be absent or incomplete.
- packages/ahg-donor-manage/src/Services/DonorService.php: confirm donor_agreement metadata and restrictions are exposed through an API the exporter can call.
- packages/ahg-ai / ai_provenance integration points: ensure licence-affecting AI suggestions (e.g. suggested licence text) record provenance and require explicit human accept.

4. Suggested enhancements (concrete, implementable)

A. Rights as first-class objects
- Model: introduce a small bounded context (ResearchLicence) that can be attached to any Research Output (or an accession/object). It should include: licence_type (CC-BY, CC0, Custom), start/end (embargo), URI, source (donor, project, user), notes, and provenance_id.
- Storage: store as research_output_licence table (or reuse an existing licence table if platform-level canonical licence model exists). Add FK to research_output.id.

B. Export mapping and validation
- Ensure the exporter (RiC / IIIF / JSON‑LD / replication pack) consumes ResearchLicence and donor_agreement_restriction and emits them in the appropriate JSON-LD nodes.
- Implement an export validation step: before publishing a manifest, run a validation that checks required licence fields and rejects export if mandatory provenance is missing.

C. Enforcement hook
- Add an enforcement middleware that reads ResearchLicence and donor_agreement_restriction for the object and enforces: embargo (deny until date), restricted access (require access request), and licence display (include licence text in the download response).
- Wire the middleware into download endpoints used by Research exports and repository downloads.

D. Provenance & UI
- Show a Licence & Restrictions panel on each Research Output and Project page. Panel shows: current licence, embargo, donor restrictions, who set it, and when (provenance). Provide an "Accept AI suggestion" flow if AI proposed licence text.
- Persist human decisions as a provenance event (ai_provenance for AI suggestions; research_activity_log or a new rights_provenance table for human decisions).

E. Tests & CI
- Add integration tests: create an output with a donor restriction + embargo, produce an export, and assert manifest contains the restriction and that a download endpoint enforces embargo.
- Add API tests for licence CRUD and a test that ensures licence survives export and round-trip.

F. Admin workflow & audit
- Add an admin queue view for outstanding licence/embargo/donor decisions (Review queue). Provide bulk actions (approve / embargo-lift / redact) with audit trail.

5. Suggested file and code changes (PR plan)

PR 1 — Rights model + DB
- Add migration: create research_output_licence table (columns: id, research_output_id FK, licence_type, licence_uri, embargo_until, source_type, source_id, notes, created_by, created_at). Add index on research_output_id.
- Add Eloquent model: ResearchOutputLicence with relation to ResearchOutput.
- Add policy & migration tests.

PR 2 — Export & mapping
- Update ResearchExportService and RiCBridgeService to consume ResearchOutputLicence and donor_agreement_restriction. Emit corresponding RiC/JSON‑LD nodes.
- Add export validation that fails if licence provenance missing and config requires it.

PR 3 — Enforcement middleware
- New middleware RightsEnforcementMiddleware that consults ResearchOutputLicence + donor_agreement_restriction and denies/permits downloads accordingly.
- Apply to repository download + research export download routes.

PR 4 — UI + provenance
- Add a Blade partial for the Licence & Restrictions panel, with an "Edit" workflow and a confirmation/accept UI for AI suggestions. Implement AJAX saveExperienceLevel-style endpoint to persist licence records.
- Ensure every change writes a rights_provenance record (created_by, change_type, details, ai_provenance_id optional).

PR 5 — Tests & CI
- Add integration tests for the exporter, middleware, UI endpoints, and provenance rows.

6. Acceptance criteria

- Research outputs have an attached ResearchOutputLicence record (or explicit null) with provenance.
- Exports (RiC/JSON‑LD/IIIF) contain licence + donor restriction nodes when present.
- Download endpoints enforce embargo and donor restrictions for research outputs and return proper HTTP codes (403/451/200) with licence metadata in headers/body.
- UI surfaces the licence panel and shows provenance for the most recent decision.
- Tests cover: licence CRUD, export mapping, middleware enforcement, provenance logging.

7. Files to inspect and sanity-check locally

- packages/ahg-research/src/Services/ResearchExportService.php
- packages/ahg-research/src/Controllers/ResearchExportController.php
- packages/ahg-ric/src/Services/RiCExportService.php
- packages/ahg-research/resources/views/research/output-licence.blade.php
- packages/ahg-donor-manage/src/Services/DonorService.php
- packages/ahg-repository/src/Controllers/RepositoryDownloadController.php
- packages/ahg-research/src/Models/ResearchOutput.php

8. Quick commands you can run locally

- Create migration skeleton (example):
  php artisan make:migration create_research_output_licence_table --path=packages/ahg-research/database/migrations

- Run migrations in testing env:
  export APP_ENV=testing
  php artisan migrate --env=testing --force

- Run exporter test (after PR 2):
  vendor/bin/phpunit --filter ResearchExportTest --testdox

9. Estimate

- PR 1: 0.5–1 day
- PR 2: 1–2 days
- PR 3: 1 day
- PR 4: 1–2 days
- PR 5 (tests): 1–2 days

Status: very good

If you want I can scaffold PR 1 now (migration + model + basic policy) and post the unified diff for review.