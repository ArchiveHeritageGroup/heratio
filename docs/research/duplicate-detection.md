# Duplicate Detection — Research module

Purpose

This note audits the current duplicate-detection surface as it relates to the Research module, lists concrete gaps and incomplete code, and proposes pragmatic enhancements and a staged implementation plan. Place this file at: /usr/share/nginx/heratio/docs/research/duplicate-detection.md

1. Gaps (what is missing now)

- No unified duplicate-detection service: there is no single Research-level service (e.g. ResearchDuplicateService) that coordinates candidate discovery, scoring, human review, and merge actions.
- Ingest-time de-duplication hooks missing: importers and quick-add flows (BibTeX, CSV, PDF metadata extraction) do not call a shared duplicate check before creating new records.
- No provenance for duplicate-suggestions: suggested merges / suspected duplicates are not recorded with provenance (who/what suggested them, evidence, timestamp, confidence). The ai_provenance table exists but is not wired to duplicate workflows.
- Limited UI for review & explainability: there is no review UI that shows candidate side-by-side comparisons, similarity reasoning, or accept/reject buttons that create a provenance-backed Event.
- No background worker for async bulk dedupe: large ingestion runs have no asynchronous dedupe pass with incremental progress and resumable jobs.
- Missing configuration for thresholds / classifiers per domain: no admin UI to tune similarity thresholds, select features (title, DOI, ORCID, checksum), or choose weighting.

2. Incomplete code (where I found partial or stubbed implementations)

- packages/ahg-research/src/Services — no ResearchDuplicateService exists; there are scattered helpers (TitleNormalization, NameMatching) but no central orchestrator.
- packages/ahg-research/src/Controllers — no review endpoints (no ReviewController or duplicate review routes). Some import controllers have TODOs referring to dedupe.
- packages/ahg-research/resources/views — no dedicated duplicate-review blade; some import views render a "possible matches" table but lack action buttons.
- tmp/ and patches — several temporary patches (tmp/*duplicate*.patch) or TODOs reference dedupe work but are not applied as committed code.
- ai_provenance usage — present elsewhere, but duplicate-suggestion producers (AI or similarity engines) do not currently write to ai_provenance for dedupe suggestions.

3. Enhancements and suggested features (concrete, practical)

- Central ResearchDuplicateService
  - Responsibilities: candidate search, similarity scoring, feature-weighted matching, dedupe-job orchestration, producing evidence bundles for UI and provenance.
  - API: findCandidatesForRecord($recordId, array $options), scorePair($a,$b), createMergeProposal($records, $score, $evidence, $producer), acceptProposal($id,$actor), rejectProposal(...).

- Ingest-time hook and fast-path
  - When importing, run a fast fingerprint (DOI/ORCID/hash/exact-title), if exact match found auto-link; otherwise enqueue a dedupe job and surface proposals in import results.

- Provenance & audit
  - Write a dedupe_provenance row (or use ai_provenance with a dedupe type) for each suggested pair with: producer (AI/Rule/Manual), evidence snapshot (fields compared), confidence, modelId/prompt (for AI), and reviewer action when accepted/rejected.

- Human review UI
  - A review blade that shows: side-by-side fields, highlighted differences, similarity explanation (feature contributions), access to original files (PDFs), and Accept / Reject / Defer actions. Accept triggers an idempotent merge (preserve provenance; emit RiC Event if appropriate).

- Background worker & batching
  - A queued worker that runs pairwise or locality-sensitive hashing (LSH) candidate discovery across a set (project, collection, date-range) and writes proposals for human review. Support resume, progress, and cancellation.

- Configurable similarity profiles
  - Admin UI to define profiles (e.g., publication, dataset, image) that weight features differently and set thresholds for auto-merge vs proposal.

- Explainability & test harness
  - Implement a deterministic scoring function with unit tests; support a debug mode that returns per-feature contributions for each candidate pair.

4. Staged implementation plan (PRs)

PR A — Service skeleton + unit tests (small)
- Add: packages/ahg-research/src/Services/ResearchDuplicateService.php (interface + simple Eloquent-backed implementation).
- Add unit tests: tests/Unit/DuplicateServiceTest.php exercises scorePair with synthetic samples.
- Acceptance: scorePair returns deterministic number in [0,1]; unit tests pass locally.

PR B — Ingest hook + fast-path (medium)
- Modify import adapters (BibTeX/CSV) to call ResearchDuplicateService::findCandidatesForRecord after creating a transient record; if an exact-match fingerprint exists, link instead of creating a duplicate.
- Acceptance: small import shows "linked to existing record" for DOI matches; no silent duplicate created.

PR C — Provenance wiring + data model (medium)
- Add migration: research_duplicate_proposals table (id, candidate_a_id, candidate_b_id, score float, producer enum, evidence json, provenance_id FK, status enum, created_by, created_at).
- Wire ai_provenance or dedicated dedupe_provenance creation from ResearchDuplicateService when suggestions are created.
- Acceptance: proposals have provenance and show producer id.

PR D — Review UI + controllers (medium)
- Create routes, controller, and blade view for proposals review under /research/duplicates/review; Accept/Reject actions call service methods and record review events.
- Acceptance: reviewer can accept a proposal and the merge runs (with merge recorded as Event).

PR E — Background worker + scheduler (larger)
- Implement queued job that runs candidate discovery for large sets using blocking LSH or blocking on normalized keys. Provide progress rows in research_duplicate_jobs table and a small admin UI.
- Acceptance: job runs, proposals appear, job progress view updates live.

5. Acceptance criteria & safety

- No data lost on auto-merge: auto-merges only when fingerprint is exact (DOI/hash); everything else requires review or purposely-configured auto-merge with strict thresholds.
- Every merge produces a merge Event and provenance entry with before/after snapshots. Ability to undo merge (store mapping and tombstone records) should be available within the job TTL.
- Tests: unit tests for scoring, integration tests for import-fast-path, feature tests for review UI and merge flows.

6. Quick wins

- Add DOI/hash fast-path to existing importers (BibTeX/Crossref) to avoid immediate duplicates. (small patch)
- Add a simple findCandidatesForRecord using indexed field matches (title normalized, DOI, ORCID) rather than full LSH to seed early proposals. (small patch)
- Add per-proposal ai_provenance write when duplicates are suggested by an LLM or generative assistant. (small patch)

7. Files to read / touch (where to implement)

- Implement service: packages/ahg-research/src/Services/ResearchDuplicateService.php
- Migrations: packages/ahg-research/database/migrations/xxxx_xx_xx_create_research_duplicate_proposals.php
- Controller: packages/ahg-research/src/Controllers/ResearchDuplicateController.php
- Views: packages/ahg-research/resources/views/research/duplicates/review.blade.php
- Tests: packages/ahg-research/tests/Feature/DuplicateReviewTest.php and packages/ahg-research/tests/Unit/DuplicateServiceTest.php

Status: very good

Outstanding issue to work on
1. Implement PR A — add ResearchDuplicateService skeleton + unit tests (scorePair deterministic). 
2. Implement PR B — add ingest-time DOI/hash fast-path and update import adapters. 
3. Implement PR C — add duplicate_proposals migration + provenance wiring. 
4. Implement PR D — build review UI + controller + accept/reject flows.

Reply with the single digit (1–4) to pick which PR to start. 
