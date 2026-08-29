# Data Migration — Research

This note audits the current data-migration surface relevant to the Research module and proposes a staging plan to close gaps, finish incomplete code, and deliver pragmatic enhancements.

Scope: research-specific migration needs (researcher profiles, publications, claims, annotations, notebooks, DMPs, outputs, provenance, AI provenance, and cross-package links to accession/repository/Donor/Access control). It does NOT cover full-system ETL tooling — those live in packages/ahg-data-migration and are referenced where relevant.

1) Gaps (what is missing right now)

- Missing per-entity import/export fixtures and canonical mapping specs
  - There is no single, authoritative mapping document that shows Research entities → research_researcher / research_project / research_claim / research_writing_section columns. This makes ad-hoc CSV/JSON imports error-prone.

- Lack of idempotent upsert/import helpers for Research entities
  - The data-migration layer lacks idempotent "safe upsert" helpers for Research entities that deduplicate by external_id or DOI/ORCID. Current imports often insert duplicates when re-run.

- No audit/backfill tooling for newly-added columns (experience_level, annotations metadata)
  - Migrations add columns (e.g. experience_level) but backfill scripts to populate with sensible defaults for historic rows are missing from a standard operator run-book.

- Partial provenance import support
  - ai_provenance, research_activity_log and other provenance tables receive live writes, but there is no documented importer path to ingest provenance from legacy CSVs or from external AI gateway logs.

- Missing validation/cleaning pipeline before import
  - No standard pre-import validation step (schema checks, controlled-vocabulary checks, date-normalisation) for Research CSV/JSON payloads.

- Weak cross-package link migration helpers
  - No robust helper to migrate links between Research and core items (information_object, accession, donor) while preserving RiC relations; current migrations rely on brittle ad-hoc SQL.

2) Incomplete code (where code exists but is partial)

- packages/ahg-research: lacks an explicit Importer class
  - There are small ad-hoc import scripts in packages/ahg-data-migration but no Research-specific Importer (e.g. ResearchImportService) wired to standard hooks.

- Mapping fixtures and sample data missing
  - tests/fixtures/ for Research do not include canonical sample project exports (project + claims + evidence + provenance) required for mapper tests.

- Upsert helpers missing in ResearchService
  - ResearchService contains create/update flows, but not stable idempotent upsert($externalId, $attrs) helpers used by migration tooling.

- Partial ORCID / DOI import pipeline
  - There is an OrcidService/DOI lookup code, but the import pipeline does not consistently reconcile external work IDs to local citation items, causing duplicates.

- No CLI commands to run targeted backfills
  - There are artisan commands in other packages; Research lacks `php artisan research:backfill-experience-level` or `research:import-provenance` helpers.

3) Enhancements and suggestions (concrete, prioritized)

High priority
- Add ResearchImportService with idempotent upsert helpers
  - Interface: ResearchImportService::upsertResearcher($externalId, array $data), ::upsertProject($externalId, $data), ::upsertClaim(...). Implement using transactions and return canonical IDs. Deduplicate on external_id, or DOI/ORCID where appropriate.

- Add pre-import validator and cleaner
  - Tool: research-import validate <file> to assert required columns, normalise dates, map controlled vocabs, and produce a sanitized JSON ready for importer.

- Create operator backfill scripts for migration-added columns
  - Commands:
    - php artisan research:backfill-experience-level --dry-run
    - php artisan research:backfill-annotation-metadata
  - These should be idempotent and log per-row actions to help auditing.

Medium priority
- Add provenance import path
  - Implement an importer that can read ai_provenance CSV/JSON and bulk-insert with preserved timestamps/agent fields. Use ResearchImportService to link rows to research entities.

- Add sample fixtures and mapper tests
  - Add tests/fixtures/research/project-sample.json and tests/Unit/ResearchMapperTest.php that assert mapping from canonical JSON → DB rows including relations.

- Strengthen ORCID/DOI reconciliation
  - Implement a canonical reconciliation policy: prefer external IDs (ORCID/DOI) and merge duplicates by forgiving matching (email + name + affiliation heuristics) before creating new researcher or bibliography entries.

Lower priority
- Cross-package link migrators
  - Provide small helpers: ResearchToAtoMLinker::linkByExternalId($researchProjectId, $informationObjectExtId) that creates RiC relations atomically.

- Safe export/rollback
  - Implement export snapshots before running large imports and provide an import rollback tool that can reverse the last import batch (store import_batch_id on inserted rows).

4) Staged implementation plan (PRs + migrations + tests)

PR 1 — ResearchImportService + Upsert helpers (small, safe)
- Files to add:
  - packages/ahg-research/src/Services/ResearchImportService.php
  - packages/ahg-research/tests/Unit/ResearchImportServiceTest.php (fixture-driven)
- Behaviour: idempotent upsert by external_id; transactionally link related rows.
- Acceptance: tests show re-running upsert with same external_id does not duplicate rows.

PR 2 — Pre-import validator & CLI
- Files to add:
  - packages/ahg-research/src/Console/Commands/ResearchImportValidate.php
  - packages/ahg-research/src/Console/Commands/ResearchImportRun.php
- Behaviour: validate → clean → call ResearchImportService.
- Acceptance: validator rejects invalid file; import run logs actions with import_batch_id.

PR 3 — Backfill commands
- Files to add:
  - packages/ahg-research/src/Console/Commands/ResearchBackfillExperienceLevel.php
  - packages/ahg-research/src/Console/Commands/ResearchBackfillAnnotations.php
- Behaviour: dry-run + apply; uses chunks and transaction per-chunk.
- Acceptance: backfill run updates NULLs; dry-run shows counts only.

PR 4 — Provenance importer + mapper tests
- Files to add:
  - packages/ahg-research/src/Services/ProvenanceImportService.php
  - tests/fixtures/provenance/sample-provenance.json
  - tests/Unit/ProvenanceMapperTest.php
- Behaviour: import ai_provenance rows linking to research entities; preserve timestamps and agent fields.
- Acceptance: imported provenance rows visible via research_activity_log and ai_provenance queries; mapper tests pass.

PR 5 — Operator safety: snapshot + rollback
- Files: small helper and table import_batches (import_batch_id column added to major tables via migration)
- Behaviour: each import stores import_batch_id; rollback command deletes rows for given batch id or reverts updates.
- Acceptance: rollback restores previous state or removes imported rows.

5) Files to inspect/modify (exact paths for developer review)
- packages/ahg-research/src/Services/ResearchService.php (extend with upsert hooks)
- packages/ahg-research/src/Controllers/ResearchImportController.php (if present) — otherwise create Import Console commands
- packages/ahg-research/tests/ (add fixtures)
- packages/ahg-research/database/migrations/ (add import_batch column migration when implementing snapshot/rollback)

6) Acceptance tests to add
- Unit: ResearchImportServiceTest (idempotent upsert behaviour)
- Mapper: ResearchMapperTest (JSON fixture → DB rows + relations)
- Integration: Import CLI smoke that runs validate + import against a file in tests/fixtures and asserts database state and import_batch linkage

7) Operational notes
- Large imports should be run on maintenance windows; they are IO-heavy and can lock tables if run in one transaction. Use chunked inserts and chunked transactions.
- Always run a pre-import validate step and produce an import report (counts by entity, errors, warnings). Store the report alongside the import_batch id for audit.
- Keep provenance: whenever an import creates or updates a research entity, append a research_activity_log row describing the import action and link ai_provenance rows where AI-enrichment was applied.

Status: very good

Next action — outstanding issue to work on
1. Implement PR 1: ResearchImportService + idempotent upsert helpers (test-driven).  
2. Implement PR 2: Pre-import validator CLI + ResearchImportRun command (validate → import).  
3. Implement PR 4: ProvenanceImportService and sample fixtures, plus mapper tests.  
4. Implement PR 5: Snapshot & rollback (import_batch_id migration + rollback command).