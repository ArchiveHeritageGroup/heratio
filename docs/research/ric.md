# Records-in-Contexts (RiC) — audit for Heratio Research module

Purpose
- Summarise the current state of RiC-related work in the Research area, identify gaps and incomplete code, and propose concrete enhancements and an implementation plan. This document is intended for repository-accurate triage and a staged engineering roadmap.

1) Quick factual summary
- RiC conceptual support appears in the codebase via: an `ahg-ric` package (exporter views and helpers), limited RiC export helpers in `packages/ahg-research/src/Services/` (some RiC mapping helpers were added earlier), and ad-hoc mapping code in exporters. There is no single, tested, idempotent RiC export service for Research projects yet.

2) Gaps (what is missing)
- No dedicated RiCBridgeService for Research: there is no single service that maps Research entities (Project, Activity, Agent, Event, RecordResource) to RiC-CM 1.0 entities and publishes them to the central `ahg-ric` ingestion endpoint.
- No event emission contract: ResearchService and slices do not consistently emit domain events (project.created, claim.registered, output.published) that a RiC bridge could subscribe to.
- Partial, inconsistent mapping coverage: some Research entities (claims, outputs, methods) have export helpers; others (bookings, notebooks, DMPs, ethics approvals) lack RiC mapping.
- No idempotent export: exports are ad-hoc and risk duplication; there is no canonical external ID mapping or export-state tracking to produce idempotent RiC entities.
- No provenance-laden relations: relations in RiC require edge-level provenance (who asserted relation, evidence); Research exports do not attach ai_provenance/agent assertions to relations.
- No tests or fixtures for RiC exports: no unit/integration tests validate the RiC payload shape or that the bridge consumes the Research payload correctly.

3) Incomplete code (exact files and where to look)
- packages/ahg-research/src/Services/ResearchService.php
  - Contains ad-hoc mapping functions but no central publish method.
- packages/ahg-research/src/Services/AnalysisBridgeService.php (if present)
  - May contain query helpers; not a RiC mapping service.
- packages/ahg-ric/ (exporter)
  - Has view fragments and low-level helpers, but lacks a documented API contract for Research exports.
- packages/ahg-research/resources/views/export/ (if present)
  - Templates may show partial JSON-LD examples; no test fixtures or canned export samples included.
- tmp patches (tmp/admin_acl_full_remediation.patch etc.)
  - Some RiC-related patches may be staged in tmp/ but not reviewed/merged; verify tmp/ for any `ric`-named patches.

4) Enhancements & suggestions (concrete)
- Implement a RiCBridgeService (Research → RiC)
  - Responsibilities: map Research domain model to RiC-CM 1.0 entities, attach relation-level provenance (agent who asserted, evidence pointers, confidence), produce idempotent publishing (externalId mapping), and retry/error handling.
- Emit domain events from Research services
  - Add a thin event bus (Laravel events or internal pub/sub) that emits standard domain events (project.created, project.updated, claim.registered, claim.resolved, output.published, researcher.approved). Make RiCBridgeService a subscriber.
- Design a RiC mapping spec and test fixtures
  - Draft a 1–2 page mapping doc (ResearchProject -> Activity, Researcher -> Agent, Claim -> RecordResource/EvidenceEdge) and add JSON-LD fixtures under tests/fixtures/ric/*.json for unit tests.
- Add idempotency and external IDs
  - Store `ric_export_id` or `external_id` on Research project and output records; use that id when upserting RiC entities so repeated publishes are safe.
- Attach provenance to relations
  - When mapping relations (e.g., project hasOutput output; claim isEvidenceFor statement), include who asserted the relation, source-of-evidence (document/AI suggestion), ai_provenance ref, and confidence score. Export these as RiC Relation properties or linked provenance nodes as allowed by RiC-O output.
- Add RiC acceptance tests and contract tests
  - Unit tests for the mapper, and integration tests that serialize a sample project and assert against the RiC schema (or a JSON-LD contract). Provide a small consumer test that simulates the ahg-ric ingestion endpoint (mock) and verifies payload structure.
- Provide an admin/manual export UI and scheduled exporter
  - UI: Project → Export → RiC (preview payload) with 'Publish' button that calls the bridge. Scheduled: nightly export job that publishes projects with changed-since timestamp.
- Security & provenance controls
  - Respect privacy / embargo rules: RiC export must defer or remove embargoed content (consult `ahg-embargo` or `research_...` restrictions). The exporter must check access rules before including personal data.

5) Suggested implementation plan (staged, small PRs)
- PR A (spec + fixtures)
  - Create docs/research/ric-mapping.md (short spec), add tests/fixtures/ric/sample_project.jsonld. Estimated 1 day.
- PR B (RiCBridgeService skeleton + event bus)
  - Add packages/ahg-research/src/Services/RiCBridgeService.php (interface + basic publisher), add event dispatch in ResearchService for project.created and output.published. Add binding in service provider. Estimated 2–3 days.
- PR C (mapper + provenance)
  - Implement mapping functions, attach ai_provenance and agent assertion fields to relations; add unit tests validating generated JSON-LD. Estimated 3–4 days.
- PR D (idempotent upsert & publish endpoint)
  - Add externalId fields, implement upsert logic, and add a project export UI with preview. Estimated 2–3 days.
- PR E (integration & scheduling)
  - Add integration tests that mock ahg-ric consumer, and a scheduled export job. Estimated 2 days.

6) Acceptance criteria (how to know it’s done)
- The RiCBridgeService can produce valid RiC-CM 1.0 JSON-LD for a sample Research project (unit-tested against fixtures).
- The Research module emits domain events that the RiCBridgeService subscribes to; the bridge publishes idempotently (multiple publishes do not create duplicates).
- All exported relations include provenance (assertor, source, confidence) where applicable; ai_suggestions are exported as proposals and carry ai_provenance references.
- Exports obey embargo/access rules: embargoed items are omitted or redacted as configured.
- Integration tests simulate the ahg-ric ingestion endpoint and assert the payload shape and HTTP success handling.

7) Files to inspect & modify (developer checklist)
- packages/ahg-research/src/Services/ResearchService.php — add event dispatches and small mapping helpers.
- packages/ahg-research/src/Services/RiCBridgeService.php — new file; implement publish(Project $p, bool $preview=false).
- packages/ahg-ric/src/Controllers/ or packages/ahg-ric/consumer — verify API contract; add tests/mocks.
- tests/fixtures/ric/sample_project.jsonld and tests/Unit/RiCMapperTest.php — add contract tests.

8) Risks & mitigations
- Mapping ambiguity: RiC is conceptual; canonical mapping decisions may be contentious. Mitigation: produce a mapping spec and review with stakeholders before implementing the full exporter.
- Privacy leaks: exporting PII or embargoed material. Mitigation: export service must check mandate/mandate-like rules and skip/redact where required.
- Duplication: naive re-publish will create duplicate nodes. Mitigation: upsert by external id; include export state tracking.

9) Next immediate actions (pick one)
- Draft the RiC mapping spec and commit to docs/research/ric-mapping.md. (I can do this now.)
- Scaffold the RiCBridgeService skeleton (interface + empty publish method) and add event emission for project.created in ResearchService. (I can patch and stage a small PR.)
- Add unit test fixtures for a small sample project and a basic mapper test that asserts keys exist in JSON-LD. (I can create fixtures and test files.)

Status: very good
Next action — outstanding issue to work on
1. Draft and commit docs/research/ric-mapping.md (mapping spec + sample JSON-LD fixture).  
2. Scaffold RiCBridgeService skeleton and emit project.created events from ResearchService.  
3. Implement mapper unit tests and sample fixtures to validate JSON-LD output.

