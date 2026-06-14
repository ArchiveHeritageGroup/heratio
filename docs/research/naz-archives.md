# NAZ Archives — Research module audit

This note audits the NAZ-specific archival features as they interact with the Research module, lists concrete gaps and incomplete code locations discovered in the repository, and proposes practical enhancements with a staged implementation plan.

1) Gaps (what is missing now)

- NAZ compliance mappings incomplete: no single NAZ policy module mapping (NAZ→retention/mandate) surfaced in Research. There is no documented crosswalk for NAZ-specific access restrictions or disposition rules.
- No NAZ-tailored reporting or export: RiC/JSON‑LD and export pipelines do not include NAZ-specific fields (e.g. NAZ classification codes, NAZ licence metadata) by default.
- Access-review workflows for NAZ-restricted records missing: the Research access-request and reviewer flows do not have NAZ-specific policy steps or sign-off queues.
- No QA/checklist for NAZ ingestion: imports lack a NAZ-conformance validator (controlled vocab, minimal metadata checklist) and a dry‑run validator for batch imports.
- Limited documentation for operators: there is no `docs/research/naz-archives.md` (this file addresses that) or a short admin checklist describing NAZ operational steps.

2) Incomplete code (where partial or stubbed implementations were found)

- packages/ahg-research/src/Services — no NazPolicyService; existing Policy/Access services are generic and lack NAZ rules.
- packages/ahg-ric — mapping helpers exist but there is no NAZ-specific mapper to inject NAZ fields into RiC exports.
- packages/ahg-research/src/Controllers/AccessRequestController.php — access request handlers are generic; TODO comments reference policy hooks that are not implemented for NAZ.
- packages/ahg-data-migration — import adapters include NAZ mentions in comments but the NAZ validator function is missing or unbound.
- docs/ — NAZ operator guidance absent; some draft notes live in tmp/ but are not committed into docs/research.

3) Enhancements and suggested features (concrete)

- NAZ Policy Service (NazPolicyService)
  - Encapsulate NAZ rules: retention, sensitivity labels, embargo durations, special licence clauses, mandatory metadata fields.
  - API: evaluatePolicy(Resource $r, Action $a, Agent $g): PolicyDecision with explicit reason and mandate path.

- NAZ Conformance Validator
  - CLI and pre-import hook that checks incoming records (CSV/BibTeX/JSONLD) for required NAZ fields, allowed vocabularies, and returns a dry‑run report flagging missing fields and suggested corrections.

- NAZ-aware Access Request Flow
  - Reviewer queue and sign-off step for NAZ-restricted items, with SLA tracking and audit log entries. Wire into the existing Research access-request UI and the ResearchAdminController reviewer assignment UI.

- NAZ-specific RiC exporter mapper
  - Extend RiCBridgeService with a NAZ mapper that adds NAZ classification nodes, mandates and restriction edges, and ensures NAZ licence text is exported in JSON‑LD and RiC-O.

- NAZ reports and dashboards
  - Add a NAZ compliance report showing items with missing NAZ metadata, upcoming embargo expiries tied to NAZ mandates, and action overdue items.

- Documentation & operator checklist
  - Add docs/research/naz-archives.md (this file) and a short in-app admin checklist, plus acceptance criteria for NAZ import and access-review workflows.

4) Staged implementation plan (PRs)

PR A — NazPolicyService skeleton + unit tests (small)
- Add: packages/ahg-research/src/Services/NazPolicyService.php and interface.
- Add unit tests that load sample NAZ mandates and evaluate simple decisions.
- Acceptance: service implements evaluatePolicy() and unit tests run locally.

PR B — NAZ Conformance Validator + import hook (medium)
- Add CLI command and a validator module invoked by import adapters (BibTeX/CSV/JSON). Produce a dry‑run report as JSON.
- Acceptance: validator catches missing mandatory NAZ fields and returns non‑zero exit code on strict mode.

PR C — NAZ-aware access-request reviewer queue (medium)
- Extend access-request controllers to add NAZ reviewer assignment and SLA tracking. Add reviewer UI elements in ResearchAdminController and a small feature test.
- Acceptance: NAZ-tagged access requests appear in the NAZ reviewer queue and can be approved/rejected with audit log entries.

PR D — RiC NAZ mapper + export tests (medium-large)
- Add mapping module in packages/ahg-ric to inject NAZ nodes and edges; include unit tests with sample projects and expected JSON‑LD fragments.
- Acceptance: RiC export includes NAZ classification and mandate nodes for a sample item.

PR E — Reports + docs (small)
- Add NAZ compliance report, embed the admin checklist in docs/research/naz-archives.md, and link the guide from the Research guide modal.
- Acceptance: report renders in the Admin UI with example stats; docs accessible from /admin/help and the Research Guide.

5) Files to touch / read
- packages/ahg-research/src/Services/NazPolicyService.php (new)
- packages/ahg-research/src/Controllers/AccessRequestController.php (enhance reviewer assignment)
- packages/ahg-research/resources/views/admin/ (add NAZ reviewer tiles)
- packages/ahg-ric/src/mappers/NazMapper.php (new)
- packages/ahg-data-migration/src/validators/NazConformanceValidator.php (new)
- docs/research/naz-archives.md (this file)

6) Acceptance criteria & safety notes
- No automatic disposition for NAZ items without explicit signed mandate. Any auto-action must be traceable, reversible and require operator opt‑in.
- All access-request changes must be audit‑trailed in research_activity_log and produce a RiC Event where applicable.
- Validator must support a non‑destructive dry‑run mode and a strict mode for CI.

Status: very good

Next steps — pick one:
1. Scaffold PR A (NazPolicyService + unit tests) and post the unified patch for review.  
2. Scaffold PR B (NAZ Conformance Validator + import hook) and post the patch.  
3. Link this doc into the Research docs index and the in-app Guide.  
4. Do nothing — I will wait for your next instruction.

Reply with the single digit (1–4).