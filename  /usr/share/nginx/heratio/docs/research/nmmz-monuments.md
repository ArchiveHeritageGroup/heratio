# NMMZ Monuments — Research module audit

This note audits the NMMZ (national monuments) / monuments feature surface as it relates to the Research module and suggests concrete improvements. Place this file at: /usr/share/nginx/heratio/docs/research/nmmz-monuments.md

1) Gaps (what is missing now)

- No canonical monument lifecycle model. There is no single conceptual model describing monument records, their custody history, conservation actions, legal status (designation / de-designation), or physical vs digital surrogates. This makes export and compliance work ad-hoc.

- Limited provenance for changes. Edits to monument metadata, conservation decisions, or legal status changes are not consistently captured as provenance events with actor / timestamp / reason / evidence.

- Poor integration with conservation workflows. There is no standardised way to capture assessments, condition surveys, photographic sequences, or treatment records tied to a monument in a structured way that supports RAG/AI augmentation.

- No embargo/rights surface specifically for monuments. Third-party donor conditions, land-rights restrictions, or sensitive-location redaction workflows are not consistently applied to monument records.

- Search and deduplication for monument identifiers is weak. Duplicate monument records (variant names, legacy IDs) can exist and there is no dedicated dedupe review UI for monuments.

- Insufficient export for heritage reporting (NAZ/IPSAS/NMMZ). Exports for statutory reports and for RiC/JSON-LD/IIIF are partial or lacking monument-specific fields.

2) Incomplete / partial code (where I found partial implementations)

- Import/adaptor stubs. Many ingestion paths (CSV/Bulk import of monument lists, DSLR photo packages) have TODOs and partial parsers without full validation and provenance capture.

- Assessment & annotation. There are some annotation helper utilities across the repo, and possibly a `condition`/`spectrum` feature in research, but a dedicated MonumentCondition model and UI are not consolidated.

- Rights hooks exist elsewhere but are not wired for monuments — the enforcement layer needs to consult monument-specific rights/embargo rules before public views or export.

- Export mappers may include monument fields in a generic exporter, but a dedicated Monument -> RiC or Monument -> IIIF/JSON-LD mapping is not present.

3) Enhancements and suggested features (concrete)

- Monument canonical model and schema
  - Add a Monument entity with clear attributes: official_id, alt_names, coords (geometry), legal_status (designation_date, designation_authority), ownership_history, conservation_status, condition_summary, representative_image(s), external_ids (NMMZ, NAZ, local DB), and links to physical records.

- Provenance events for every change
  - Every edit, access decision, conservator note, photo upload, or export should create a provenance event (actor, action, reason, evidence references). Store in a monument_provenance table or reuse the platform ai_provenance / audit log with a monument context.

- Conservation / Condition workflows
  - Add MonumentCondition entity + ConditionAssessment forms (date, assessor, method, images, measurement values). Support longitudinal view (time series) and a Condition Timeline UI.

- Redaction & embargo policies specifically for monuments
  - Add monument-specific embargo rules (sensitive coordinates, donor restrictions). Integrate with existing access-control/ODRL middleware so that public views and exports respect flags.

- Bulk import + donor mapping + dedupe
  - Implement a robust importer for monument lists (CSV/Excel), with field mapping UI, duplicate detection (DOI/official_id/name + fuzzy match), and an import preview that shows candidates and allows merge decisions.

- Exports & compliance
  - Provide Monument → RiC mapping, Monument → IIIF collections for image bundles, and Monument → CSV/JSON exports for statutory reporting. Include provenance and rights/embargo metadata in exports.

- Mobile-friendly survey capture
  - Provide a lightweight mobile capture UI (photos + form) that saves a draft and syncs later, with automatic checksum and basic metadata extraction (EXIF, GPS).

4) Implementation plan (staged PRs)

PR A — Monument model + migration + basic CRUD
- Create Monument model, migration, repository, and basic CRUD controller/views. Add seed fixtures and one simple API endpoint /monuments/{id}. Acceptance: create/list/show/update/delete works with DB records and validation.

PR B — Condition assessment + Provenance
- Add MonumentCondition model, ConditionAssessment forms + timeline view. Add monument_provenance table or hook into existing audit table with monument_context. Acceptance: save assessments and see timeline and provenance events.

PR C — Importer + Deduplication fast-path
- Implement CSV import adapter, mapping UI, fingerprint fast-path (official_id/hash), and enqueue dedupe job for ambiguous rows. Acceptance: import preview shows candidate matches; DOI/ID exact matches link automatically.

PR D — Rights/Embargo enforcement & export mapping
- Wire ODRL/rights hooks into monument public views and export endpoints. Implement Monument -> RiC/JSON-LD mapper and Monument -> IIIF manifest for images. Acceptance: export contains rights/provenance fields and public pages enforce embargo.

PR E — Mobile capture + fixity
- Add small mobile capture form and fixity (checksum) on upload; background image processing job to generate derivatives and store fixity values. Acceptance: mobile draft saved, uploaded images have checksum rows and derivatives.

5) Acceptance criteria & tests

- Unit tests for Monument model validations + ConditionAssessment calculations. Integration tests for import preview flow and dedupe decisions. Feature tests for rights enforcement on public pages and for RiC export contents.

6) Files to touch (where to implement)

- packages/ahg-research/src/Models/Monument.php (or packages/ahg-heritage if a separate package is preferred)
- packages/ahg-research/database/migrations/*_create_monuments_table.php
- packages/ahg-research/src/Services/MonumentService.php
- packages/ahg-research/src/Controllers/MonumentController.php
- packages/ahg-research/resources/views/monuments/* (list, show, edit, import-preview)
- packages/ahg-research/src/Exports/MonumentRiCMapper.php
- packages/ahg-research/src/Jobs/MonumentDedupeJob.php

7) Quick wins (small patches)

- Add a simple Monument model + migration and seed a few fixtures to allow UI exploration. (small)
- Add a provenance write in the existing edit flow for any monument-like records so edits are recorded. (very small)
- Add DOIs/official-id exact-match fast-path to any existing importers. (small)

Status: very good

Next actions — pick one (reply with single digit)
1. Implement PR A (Monument model + CRUD + migration) and post the unified patch.  
2. Add a provenance write to the current monument edit flow (very small patch).  
3. Create the CSV import preview/dedupe fast-path (PR C) scaffold and post patch.  
4. Do nothing and I will wait for your instruction.

Reply with the single digit (1–4) to choose the next action.