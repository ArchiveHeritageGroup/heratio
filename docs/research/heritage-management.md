# Heritage Management — gaps, incomplete code, and recommended enhancements

This note documents the current state of the Heritage Management surface (what exists, what is missing, and practical enhancements) and a staged implementation plan. Path: /usr/share/nginx/heratio/docs/research/heritage-management.md

1. Quick summary
- Status: Partial — the codebase contains core tables, controllers and views for heritage-related features (exhibitions, accessions, donors, rights), but several integration, audit, UI, and automation gaps remain. The area is functional but not production-complete: missing tests, incomplete export mappings, limited automation for embargo/rights changes and not all provenance / RiC mappings are implemented.

2. First look — gaps (high priority)
- RiC / provenance coverage: relations that should be modelled as RiC (heritage activities, mandates, donors, curatorial decisions) are present in fragments but no single, idempotent RiC export/bridge exists that publishes Research activities as RiC entities with relation-level provenance.
- Export completeness: IIIF, JSON-LD and RiC exports for heritage objects and exhibitions sometimes omit donor agreements, embargo conditions, and authority links.
- Access/embargo automation: scheduled release of embargoed items is partially implemented; enforcement middleware and audit trail for automated unembargo operations is spotty.
- Tests and CI: few package-level feature tests target heritage workflows (exhibition publish, accession + donor linkage, embargo release), so regressions risk unnoticed failures.
- AI provenance: AI-assist features that tag/describe heritage objects (exhibition labels, TTS, docent text) do not consistently write ai_provenance entries with model+prompt+response and accepted/rejected flags.
- PII/encryption policy: donor and lender PII may not be consistently encrypted if the encryption toggle is enabled — backfill/migration strategy missing.

3. Incomplete code (concrete locations)
- RiC bridge: missing or partial: packages/ahg-research/src/Services/RiCBridgeService.php (not present or skeletal). Export mapping helpers exist in packages/ahg-ric but no canonical consumer wiring from Research/Exhibition slices.
- Exhibition manifest builder: packages/ahg-exhibition/src/Services/ExhibitionSpaceService.php generates scenes but caching/invalidation and manifest ETag headers are incompletely implemented.
- Embargo enforcement: packages/ahg-embargo (or equivalent) has scheduled job skeletons but lacks consistent middleware enforcement across download/serve routes.
- Donor agreement export: packages/ahg-donor-manage has tables for donor_agreement but packages/ahg-ric exporter may not include those nodes.
- AI/TTS provenance: multiple controllers call AI gateway directly; some write to ai_provenance, others do not — see generative and writing-studio controllers across packages/ahg-research and packages/ahg-exhibition.
- Tests: packages/ahg-research/tests/Feature contains some smoke tests, but heritage-specific regression tests (exhibition publish, accession+donor+export) are missing.

4. Suggested enhancements (practical, prioritized)
Priority A (safety & compliance)
- Centralise AI provenance: ensure every AI/TTS call writes to core ai_provenance table (prompt, model, inputs, response, confidence, user_id, accepted boolean). Add UI Accept / Reject flow for each suggestion.
- Centralised provisioning: finish converting admin user/ACL writes to UserProvisioner; add pre-commit grep to block new direct writes to core user/acl tables.
- Embargo enforcement: add middleware to enforce embargo status on object download/view; schedule release job that emits RiC Event on release.
- PII encryption backfill: provide an artisan command that dry-runs and then encrypts donor/lender PII when operator enables encryption.

Priority B (interoperability & exports)
- RiCBridgeService: implement an idempotent bridge that subscribes to domain events (project.created, accession.accepted, exhibition.published) and upserts RiC entities and relations with relation-level provenance.
- Rich export mapping: augment scene/manifest builders to include donor_agreements, embargo clauses and curator decisions; add ETag/Cache control headers and Redis caching with invalidation on builder changes.

Priority C (UX & operability)
- Provenance UI: add a provenance card to exhibition editor & writing studio showing a timeline of events & AI proposals; allow export of provenance as JSON.
- Accessibility: ensure all exhibition walkthrough controls, transcripts, and text-tour alternatives are implemented and a11y-tested.
- Monitoring: add metrics for export generation time, LLM latency/errors, embargo-release job success/failure counts.

5. Concrete staged implementation plan (PR-sized work)
PR 1 — Safety & provenance foundation (2–4 days)
- Instrument AI/TTS call sites (writing studio, analysis bridge, exhibition docent) to write ai_provenance rows. Add Accept/Reject UI and ensure accepted updates are audited. Add simple feature tests.
- Deliverables: ai_provenance writes, UI accept/reject, tests.

PR 2 — Embargo enforcement & scheduled release (2–3 days)
- Add middleware that checks object access against embargo status. Implement ReleaseEmbargoJob that runs daily and emits a domain event + RiC Event.
- Deliverables: middleware, job, event emission, test that simulates release.

PR 3 — RiCBridgeService skeleton + event bus (3–6 days)
- Add event bus (Laravel events) and a RiCBridgeService subscriber that maps one exemplar domain model (e.g. accession → RecordResource + Activity + Agent relations) to RiC JSON-LD and writes to ahg-ric via existing API or a queue.
- Deliverables: bridge skeleton, one mapping, idempotent publish, sample test fixture.

PR 4 — Export & caching improvements (2–3 days)
- Cache scene/manifest outputs and add ETag headers. Include donor agreements and embargo clauses in JSON-LD export.
- Deliverables: cache layer, invalidation hook, updated manifest and test for ETag behaviour.

PR 5 — Docs & operator playbook (0.5–1 day)
- Add operator doc (how to run encryption backfill, run release job, inspect provenance), and link into research docs.
- Deliverables: docs/research/heritage-management.md (this file), admin checklist.

6. Files to inspect / code pointers (start here)
- packages/ahg-research/src/Services (ResearchService, any RiC mapping helpers)
- packages/ahg-exhibition/src/Services/ExhibitionSpaceService.php (scene/manifest builder)
- packages/ahg-donor-manage/* (donor_agreement tables and views)
- packages/ahg-research/src/Controllers/* (writing studio, analysis bridge)
- packages/ahg-ric/* (exporter code and tests)
- storage/logs/laravel.log for job errors and AI gateway failures

7. Acceptance criteria
- ai_provenance populated for every LLM/TTS call and UI shows suggestion state (accepted/rejected) with actor and timestamp. Tests validate insertion.
- Embargoed items are blocked by middleware and automatically released by scheduled job which emits an event recorded in RiC export.
- RiCBridgeService can export an accession to RiC JSON-LD and re-run idempotently without duplication.
- Scene/manifest endpoints have ETag and caching; cached payloads invalidated on builder changes.

8. Estimates & risks
- Total initial effort (PR1–PR4): ~9–16 working days (staged across small reviews). Risks: mapping assumptions for RiC need domain review; AI/TTS instrumentation must be careful about sensitive content and PII.

Status: very good

Next action — outstanding issue to work on
1. Implement AI provenance instrumentation for top Research + Exhibition LLM/TTS call sites (PR 1).  
2. Implement embargo enforcement middleware + scheduled release job (PR 2).  
3. Scaffold RiCBridgeService skeleton + one mapping (PR 3).  
4. Add docs & operator playbook (PR 5).

Reply with the single digit (1–4) to pick the next task.