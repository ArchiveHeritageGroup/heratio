# Format Registry — Research module

Purpose

This document audits the current "Format Registry" surface as it relates to the Research module, records concrete gaps and incomplete code, and proposes practical enhancements and a staged implementation plan. Place this file at: /usr/share/nginx/heratio/docs/research/format-registry.md

1) Gaps (what is missing now)

- No central Format Registry service
  - There is no single canonical service or data store that normalises format identifiers, MIME types, PRONOM PUIDs, common extensions and preservation properties (e.g. recommended preservation action, preferred archival container format).

- Weak integration with ingest/validation pipelines
  - Importers (CSV, BibTeX, PDF/IMAGE ingest) do not consistently consult a shared format registry to validate file types, choose validation/normalisation pipelines, or record format provenance.

- Missing format-to-action mapping
  - No formal mapping exists that ties a format to a recommended set of preservation actions, conversion strategies, or required metadata extraction steps.

- No UI for maintainers
  - There is no admin UI to look up, edit, add, or deprecate format entries (PUID ↔ MIME ↔ extensions ↔ label ↔ preservation notes).

- Incomplete provenance and audit for format claims
  - When a format claim is inferred (e.g. from file extension or magic), it is not consistently recorded with evidence (tool used, confidence, file bytes sampled). The ai_provenance table is present for LLM activity but format-detection provenance is not standardised.

- No lightweight format-recognition worker / service
  - Rapid detection (libmagic, PRONOM match, checksum-based identification) should be centralised behind a service; currently detection is ad-hoc across importers.


2) Incomplete code (where partial or stubbed implementations commonly appear)

- Import adapters and controllers
  - Many import adapters contain `TODO` or `FIXME` notes around type detection and rely purely on file extension. Search `TODO`/`magic`/`mimetype` inside packages/*/src/Controllers and packages/*/src/Services to find callers.

- Validation hooks
  - There are scattered validator helpers but no registry-backed `FormatValidator` or `FormatPolicy` to decide whether an ingest should be accepted or rejected on format grounds.

- Preservation mapping
  - Some preservation/planning code references `preferred_container` or `preserve_as` config keys in comments but lacks a persisted mapping table (format_registry) and a programmatic API to retrieve actions for a format.

- UI stubs
  - Admin views that might show format details (if present) are often stubs or lack edit forms — search resources/views for `format` or `mimetype` to find any leftovers.

- Provenance logging
  - Detection steps do not consistently write entries to ai_provenance or a similar ingest_provenance table. There is no consistent contract for provenance shape for detection events.


3) Enhancements and suggested features (concrete, practical)

- Add a Format Registry data model
  - Migration: `format_registry` table with columns: id, puid (nullable), mime_type, extensions (json), common_suffix, label, description, preservation_action json, canonical_tool (e.g. DROID/libmagic), preferred_container, deprecated (bool), created_at, updated_at.

- Implement FormatRegistryService
  - Responsibilities: lookup by extension/mime/puid; normalisation (return canonical entry); enrich detection results with registry metadata; provide method `suggest_preservation_actions($formatId)`.

- Centralise detection in a FormatDetectionService
  - Wrap libmagic + PRONOM (DROID) + heuristics; accept a file path / byte stream and return {puid, mime, extensions, confidence, evidence}. Write a provenance record for each detection.

- Integrate into importers and upload paths
  - All upload/import adapters call FormatDetectionService first, which returns canonical format id. Import pipeline records format_id on the object and stores detection_provenance record (tool, signature, confidence, sampled_bytes_hash).

- Add a lightweight admin UI
  - CRUD for registry entries, search, deprecation controls, and a CSV import for bulk PUID/MIME mappings. Include a preview that shows sample files matched to the entry.

- Mapping to preservation actions and automated workflows
  - Allow registry entries to store an array of recommended actions (e.g. `{'preserve': 'keep','migrate_to':'PDF/A-2','validate_with':'JHOVE'}`) and expose an API used by preservation jobs.

- Provenance & metrics
  - Add ingest_provenance table or extend ai_provenance for detection events: include detection_tool, config_version, sample_hash, confidence. Build dashboards for detection error rates by tool and extension mismatches.

- Validation rules & policy enforcement
  - Allow admins to define `FormatPolicy` objects per collection/project: permitted formats, auto-reject thresholds, and auto-conversion targets.

- Batch re-detection job
  - A worker to re-run detection against existing objects if registry updates (e.g. new PRONOM signatures) or when admin changes a mapping.


4) Implementation plan & file targets (staged PRs)

PR A — Data model + service skeleton (small)
- Create migration: packages/ahg-core/database/migrations/xxxx_create_format_registry_table.php
- Implement `FormatRegistry` Eloquent model and `FormatRegistryService` skeleton in packages/ahg-core or packages/ahg-preservation/service namespace (choose a cross-package location).
- Add unit tests for model & basic lookup by extension/mime.

PR B — Detection service + provenance (medium)
- Implement `FormatDetectionService` which calls libmagic and an adapter for DROID signatures when available. Add `detection_provenance` table or extend ai_provenance with detection_type.
- Wire detection calls into upload endpoints (file upload controller, import adapters) and ensure each object stores `format_registry_id` and writes a provenance row.

PR C — Importer integration + validation policy (medium)
- Update import adapters (CSV/BibTeX/PDF/Image) to call FormatDetectionService early in pipeline, invoke `FormatPolicy` for acceptance/rejection or auto-conversion. Add tests for fast-path DOI/hash + format handling.

PR D — Admin UI + bulk loader (small→medium)
- Add admin views to manage registry entries; add CSV import for PUID ↔ MIME ↔ extensions. Add API endpoints for read-only lookups used by client-side typeahead.

PR E — Preservation mapping & action runner (larger)
- Allow registry entries to define preservation actions. Create `PreservationActionRunner` that schedules jobs (validation/JHOVE, migration to container formats) using mapping.

PR F — Batch re-detection job + dashboard (larger)
- Implement a job that re-scans objects in batches when signatures/registry entries change. Add a dashboard tile to show re-detection progress/errors.


5) Acceptance criteria & safeguards

- Detection must be non-destructive and idempotent. Do not alter object content during detection.
- All detection events are provenance-logged with tool id, version, confidence, and sample hash. UI must show detection provenance for each object.
- Auto-reject or auto-conversion is only allowed when strict fingerprint matches exist (DOI/hash/PUID == exact) and when admin policy explicitly allows auto actions.
- Tests: unit tests for service lookups, integration tests for importer pipeline, and acceptance tests for admin UI CRUD.


6) Quick wins (0.5–1 day each)
- Add a small libmagic wrapper and call it from upload endpoint; record returned mime in object metadata.
- Add a lightweight format_registry CSV seed (common types: pdf, tiff, jpeg, mp3, wav) so detect+lookup has immediate value.
- Surface detection result in object show page (format label + confidence + detection tool) to help operators triage mismatches.


Status: very good

Next actions you can pick (1–4)
1. Implement PR A — add FormatRegistry model + FormatRegistryService skeleton and tests. (estimate 1–2 days)
2. Implement PR B — FormatDetectionService + detection_provenance table and wire into upload/import. (estimate 2–4 days)
3. Implement PR D — Admin UI + CSV bulk loader for registry entries. (estimate 1–2 days)
4. Run a repo search for existing `mimetype`/`magic`/`pronom` references and produce a remediation checklist so the detection integration can be applied consistently. (quick audit)

Reply with the single digit (1–4) to choose which I should start next.
