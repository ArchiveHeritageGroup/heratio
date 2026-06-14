# Duplicate detection — Research module

This document audits the current duplicate-detection surface for the Research module, lists gaps and incomplete code, suggests concrete enhancements, and outlines a staged implementation plan.

1. Gaps (what is missing now)
- No central ResearchDuplicateService coordinating candidate discovery, scoring, job orchestration and merge actions.
- Importers and quick-add flows do not run a shared de-duplication check before creating records.
- No structured provenance for duplicate suggestions (who/what suggested, evidence, confidence, model/prompt).
- No human-review UI that shows side-by-side comparisons, per-feature explainability, and accept/reject actions.
- No scalable background worker for async bulk dedupe (resume/progress/cancel support).
- No admin UI to tune similarity profiles (weights/thresholds) per content type.

2. Incomplete code (where partial implementations or stubs exist)
- packages/ahg-research/src/Services: helper utilities (normalisers, name/title matchers) exist but no service orchestrator.
- packages/ahg-research/src/Controllers: import controllers reference TODOs for dedupe; no ReviewController or duplicate routes.
- packages/ahg-research/resources/views: import pages show "possible matches" tables without action handlers; no review blade.
- tmp/ and patches: several temporary patches or TODOs reference dedupe but are not committed.
- ai_provenance: present in the codebase but not wired to dedupe suggestion producers.

3. Enhancements and suggested features (concrete)
- ResearchDuplicateService
  - API: findCandidatesForRecord($recordId, $opts), scorePair($a,$b), createProposal($pairs,$score,$evidence,$producer), acceptProposal($id,$actor), rejectProposal($id,$actor).
  - Responsibilities: candidate search (fast/slow), scoring, evidence bundling, provenance creation, merge execution.

- Ingest-time fast-path
  - On import, compute fingerprint (DOI/ORCID/checksum) and if exact match found link instead of create. Else, call fast candidate search and surface proposals in import results.

- Provenance for proposals
  - Record every suggestion with provenance: producer (AI/rule), evidence snapshot, prompt/model details (if AI), confidence, and request_id. Use ai_provenance or a dedicated dedupe_provenance table linking to ai_provenance.

- Human review UI
  - A review blade showing side-by-side comparison, per-feature contributions, evidence excerpts and Accept / Reject / Defer actions. Accept triggers idempotent merge with a Merge Event recorded.

- Background worker & batching
  - A queued job for large datasets using blocking keys or LSH. Job records progress, proposals created incrementally, and supports resume/cancel.

- Configurable similarity profiles
  - Admin UI to define profiles (publication/dataset/image) with feature weights and thresholds for auto-link vs. proposal.

- Explainability & tests
  - Deterministic scoring function with unit tests and a debug mode returning per-feature contributions.

4. Staged implementation plan (PRs)
- PR A — Service skeleton + unit tests
  - Add ResearchDuplicateService + simple implementation and unit tests for scorePair.

- PR B — Ingest hook + fast-path
  - Modify import adapters to call duplicate service and implement fingerprint fast-path for DOI/hash.

- PR C — Provenance model + wiring
  - Migration: research_duplicate_proposals table and wiring to ai_provenance (or dedicated provenance). Proposals created with evidence json.

- PR D — Review UI + controllers
  - Routes, controller, blade for review, accept/reject actions, merge implementation and recording of merge Event.

- PR E — Background worker + admin UI
  - Duplicate job table, queued worker, admin UI for job management, thresholds profiles UI.

5. Acceptance & safety
- Auto-merge only on exact-fingerprint matches. Otherwise proposals require human accept (unless admin tuned auto-merge thresholds).
- Every merge must create a provenance record and an undo path (store original ids mapping or tombstone record with metadata) for a window.
- Unit tests for deterministic scoring; feature tests for ingest-fast-path and review acceptance.

6. Quick wins
- Add DOI/hash fast-path in importers. (small)
- Implement findCandidatesForRecord with indexed field matching (title/DOI/ORCID) to seed proposals. (small)
- Write ai_provenance entries when an LLM suggests duplicates. (small)

7. Files to touch (recommended)
- packages/ahg-research/src/Services/ResearchDuplicateService.php
- packages/ahg-research/database/migrations/xxxx_xx_xx_create_research_duplicate_proposals.php
- packages/ahg-research/src/Controllers/ResearchDuplicateController.php
- packages/ahg-research/resources/views/research/duplicates/review.blade.php
- packages/ahg-research/tests/Unit/DuplicateServiceTest.php
- packages/ahg-research/tests/Feature/DuplicateReviewTest.php

Status: very good

Next action — outstanding issue to work on
1. Implement PR A (ResearchDuplicateService skeleton + unit tests). 
2. Implement PR B (ingest fast-path + importer wiring). 
3. Implement PR C (proposals migration + provenance wiring). 
4. Implement PR D (review UI + accept/reject + merge flow).
