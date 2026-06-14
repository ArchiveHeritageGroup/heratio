# Export — Research module

This note assesses the Research module's export surface (what it currently produces, gaps in coverage or format, incomplete code, and practical enhancements). It is written as an operational-runbook for implementers and product owners; every recommendation includes a testable acceptance criterion.

1. Current exports (what exists)

- Project-level exports: the Research module supports bibliography exports (BibTeX, RIS, CSL-JSON) via CitationService and Bibliography export endpoints. These produce flat bibliographic representations intended for researchers to download.
- Scene/manifest exports: where research intersects with exhibition or repository packages, ad-hoc JSON/IIIF manifests may be produced by the relevant exporter services (ExhibitionSpaceService, Repository exports). The Research module can reference these but does not centrally author them.
- Simple CSV/JSON exports: some list endpoints support CSV or JSON dumps using controller-level exporters.
- Partial RiC outputs: limited mapping exists from research activities → RiC entities, but no canonical, idempotent RiCBridge export process that exports Research projects/activities/agents as RiC nodes.

2. Gaps (first look)

- No canonical Research export endpoint that packages: project metadata + bibliography + sources manifest + claims + provenance into a single bundle suitable for sharing/preservation (e.g. a Research Package, BagIt, or RiC bundle).
- No idempotent RiC bridge: research Activities, Agents, Events and Relations are not consistently mapped and published to the ahg-ric consumer as an atomic export. Existing RiC exports are partial and ad-hoc.
- Provenance omission: exports seldom include full ai_provenance or research_activity_log traces (AI prompts, reviewer decisions, timestamps) — critical for reproducibility and audit.
- Missing packaging formats: there is no option to export a research package as BagIt, ZIP (with structured manifest), or as an OAIS-friendly submission object.
- No signed/verified export: exports are not cryptographically signed or accompanied by a provenance manifest with checksums (fixity) for preservation consumers.
- No export UI for curators: operators cannot assemble and preview an export bundle from the Research UI (select project → include/exclude nodes → build preview).

3. Incomplete code / technical holes (concrete)

- RiCBridge service missing or incomplete: packages/ahg-research lacks a robust RiCBridgeService that constructs RiC-CM 1.0 graphs and publishes them to ahg-ric via HTTP/CLI/DB. (Search: no `RiCBridgeService` under packages/ahg-research/src/Services.)
- No export packaging service: missing a ResearchExportService (packaging orchestration). There are points that produce fragments (bibliography, DMP, outputs) but no orchestrator that gathers them and writes a package.
- ai_provenance not consistently included: controllers that produce exportable artifacts (writing studio, claim ledger, output publisher) do not attach ai_provenance rows to the exported bundle. The `ai_provenance` table exists in core, but not every export path reads from it.
- Missing background job to build exports: heavy exports should run in the queue (export builder job) and be downloadable when ready — this is not implemented.

4. Practical enhancements and suggestions (what to build)

A. ResearchExportService (or ResearchPackageService)
- Responsibility: orchestrate creation of a project export bundle. Inputs: project_id, options (include_bibliography, include_sources, include_claims, include_ai_provenance, include_dmp, export_format).
- Output formats: BagIt ZIP (recommended), plain ZIP with manifest.json, and a RiC-O RDF export (Turtle/JSON-LD) of the same content.
- Implementation notes:
  - Gather pieces: project metadata, bibliography (CSL-JSON), sources (list of IO identifiers + optional copies), claims (JSON), writing studio final sections (PDF/HTML), ai_provenance slices, DMP, ethics approvals.
  - Produce a manifest.json with schema: version, created_by, created_at, file_list (path, size, sha256), riC_graph (optional file path), provenance_bundle (ai_provenance entries exported as a separate JSON file).
  - Acceptance: built bundle contains manifest.json, checksums, and the requested artifacts; a md5/sha256 fixity check can verify content.

B. RiCBridgeService + event bus
- Responsibility: map Research entities → RiC-CM 1.0 graph and publish to ahg-ric or to a local staging RDF file.
- Implementation notes:
  - Design a mapping spec: Research Project → Activity; Researcher → Agent; Section/Claim → RecordResource/Assertion; Events (publish, accept, disposition) → RiC Event nodes.
  - Emit events: project.created / project.exported / claim.registered etc. ResearchExportService should subscribe to project.exported and publish a RiC export as part of the bundle.
  - Acceptance: a sample project export produces a RiC JSON-LD file that can be consumed by ahg-ric tests (roundtrip: import into ahg-ric shows 1 Activity, N Agents, M Relations).

C. Provenance-first exports
- Include ai_provenance and research_activity_log in every archival export by default (or by toggle). For each exported artifact attach: who suggested it, AI prompt + model + response hash, reviewer decision, timestamp.
- Store provenance as a machine-readable file (provenance.json) and as RDF triples inside the RiC graph where appropriate.
- Acceptance: provenance.json contains entries with keys {artifact_path, provenance_type, actor, timestamp, evidence} for each exported artifact.

D. Packaging + fixity + signing
- Build BagIt-compatible ZIP: bagit.txt, manifest-sha256.txt, data/… and the manifest.json described above.
- Implement fixity calculations (sha256) during package build and include them in the manifest. Optionally sign the manifest with configured operator key (openssl or internal signing service) and include a signature file.
- Acceptance: package verifies locally by recalculating checksums and verifying signature.

E. Export UI + background jobs
- Add an Export panel to the project UI: select items, choose formats, build export. Kick off a queued ExportBuilderJob that constructs the package and marks job status in the DB with a download link when done.
- Acceptance: user sees export job status and can download resulting archive; job logs persist under project exports table.

F. Preservation gateway & ingest
- Provide a `/export/{id}/push` option that can send the package to a configured preservation target (S3/Glacier, On-prem preservation store, or an institutional repository) using robust retry and delivery receipts.
- Acceptance: export push returns delivery receipt and stores receipt metadata in export record.

G. API & automation
- Expose an API (token-auth) to request exports for automation (e.g. scheduled monthly export). The API returns a job id and later a download URL.
- Acceptance: API client can request export.json and poll job status.

5. Tests & verification
- Unit tests for mapping functions (Research → RiC). Integration test that builds a small project export (mock artifacts) and asserts: manifest.json present, sha256 manifests correct, RiC JSON-LD parsable.
- E2E smoke test: build export for a sample project, unpack, verify fixity.

6. Migration / storage policy
- Decide what to include as binary sources: include references only (IDs + URLs) or embed copies of each source file. Default: embed small files (<=10MB); reference large files and include a download manifest with fixity checks.

7. Roadmap & estimates
- MVP (ResearchExportService producing manifest.json + zipped bundle with bibliography, project metadata, claims, provenance.json): 5–8 days. Includes tests and queued job.
- RiCBridge + mapping spec: 5–10 days (schema design + implementation + tests).
- Packaging + signer + push to preservation target: 3–5 days.

---

File: docs/research/export.md
Generated: automated audit (2026-06-13)

Status: very good
Next action — outstanding issue to work on
1. Implement ResearchExportService MVP (bundle + manifest + provenance.json) and add ExportBuilderJob (5–8 days).  
2. Draft and implement RiCBridgeService mapping spec and a sample JSON-LD export (5–10 days).  
3. Add Export UI + queued job status with download links (2–3 days).  
4. Add tests: unit mapping tests + integration E2E export verification (2–3 days).
