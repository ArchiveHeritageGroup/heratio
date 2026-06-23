# External Review Rebuttal and Corrected Gap List (2026-06-23)

## Summary

An external reviewer produced a six-theme "what Heratio needs to be a great GLAM/DAM"
gap list (governance/compliance, scale/reliability, discovery/metadata,
preservation/authenticity, operator/user workflows, ecosystem/adoption). We
fact-checked every "missing" claim against the actual codebase (~115 packages under
`packages/`). The review's central premise is mostly incorrect: it reads like a
feature-name audit rather than an inspection of the code. The large majority of items
it calls "missing" are already implemented, frequently to production depth (schema +
service + UI + CI). Only nine genuine gaps survive, mostly UX polish and two library
connectors - not the foundational governance/preservation holes the review implies.

The nine genuine gaps are tracked as GitHub issues #1324-#1332.

## Part 1 - Review claims that are wrong (already implemented)

Each item below was asserted "missing" or weak by the review but exists with concrete
schema/service/UI evidence.

- AI provenance enforcement: `ahg-provenance-ai` - `ahg_ai_inference` (model, prompt
  hash, confidence, signature) + central `InferenceService` auto-writes on every
  LLM/TTS/HTR/NER call; Ed25519 signing; Fuseki RDF-Star replay (ADR-0002, #61/#135/#136/#141).
- Retention / embargo / disposition: `ahg-records-manage` - `rm_retention_schedule`,
  `rm_disposal_class`, `rm_disposal_action` (initiated -> legal_cleared -> approved ->
  executed), `rights_embargo` enforcement; ISO 15489 / MoReq2010 aligned.
- DSAR / privacy: `ahg-privacy` - `privacy_dsar` + `privacy_dsar_object`, PII gate
  (`DbEmbeddedMetadataPiiGate` strips GPS pre-OCFL), DPIA, `privacy_breach`,
  `rm_compliance_assessment` (GDPR/POPIA).
- Fixity monitoring: `ahg-preservation` - `preservation_checksum` +
  `preservation_fixity_check`, scheduled daily/weekly jobs, `preservation_stats`
  dashboard alerts, `FixityScanService`.
- Format identification: `ahg-scan/FormatIdService` - Siegfried/DROID + `file`
  fallback, `preservation_format` PRONOM registry, obsolescence tracking.
- BagIt / preservation packaging: BagIt ingest + OAIS `preservation_package`
  SIP/AIP/DIP (bagit/zip/tar) with full lifecycle events.
- Digitisation-event model: PREMIS `preservation_event` emitted at ingest
  (`PremisEventService`); C2PA digitisation-provenance UI with signing.
- Semantic + faceted search: `ahg-search` ElasticsearchService - 11 facets,
  field-weighted boosts, highlighting, multilingual; `ahg-semantic-search` + Qdrant
  vector + Reciprocal Rank Fusion.
- RiC export: `ahg-ric` - 35+ RiC-O endpoints, JSON-LD/RDF, SPARQL, SHACL, RiC
  OAI-PMH, DCAT/VoID dataset descriptor.
- IIIF: `ahg-iiif-collection` - manifest.json, Mirador, IIIF Auth v1/v2, Content
  Search 2.0, Change Discovery 1.0, Content State (#694/#695/#696) + Cantaloupe.
- ORCID/DOI/OAI/Z39.50/SRU: ORCID sync (`OrcidService`/`OrcidSyncCommand`),
  `ahg-doi-manage` Datacite mint/sync, OAI-PMH (3 surfaces), `ahg-z3950` Z39.50 + SRU.
- Multi-tenant isolation: `ahg-core/TenantScope` (repository_id filter on ES + DB) +
  `ahg-acl/AclService` per-entity ACLs.
- Access vs preservation storage split: `DigitalObjectService`
  master(140)/reference(141)/thumbnail(142); configurable paths; S3 offsite
  (`ahg-backup/S3OffsiteDriver`).
- CI / tests / backup: `.github/workflows/test.yml` (unit/feature/e2e/security gates);
  `ahg-backup` granular restore + offsite replicators.
- Ingest wizard / sharing / bibliography: `ahg-ingest` 6-stage wizard;
  `ahg-share-link/TokenService` HMAC time-boxed tokens; research
  workspaces/collaborators; CSL bibliography export.
- Record-part / hierarchy: RiC-O `rico:RecordPart` mapping; `TreeviewService` full
  fonds-to-item hierarchy.

Conclusion: review themes 1-4 and most of 5-6 are already built. Citing them as gaps
is the review's primary error.

## Part 2 - Genuine gaps (tracked as issues)

1. Bulk accept/reject across review queues - #1324. 10+ queues exist but drive one
   record at a time.
2. Researcher download/storage quotas - #1325. No quota enforcement in `ahg-research`.
3. AI-decision and human-action provenance coverage - #1326. Infra complete; some
   research controllers skip `research_activity_log`; Crossref/OpenAlex enrichment not
   always logged.
4. DSAR export / anonymisation one-job packager - #1327. Intake + redaction gate
   exist; single-job export appears phased.
5. Chunked / resumable >1GB upload handler - #1328. Streaming/FTP/queue exist; no
   resumable multipart web upload.
6. DSpace federation/import connector - #1329. Only a storage-service label today.
7. Ex Libris Alma connector - #1330. Absent in any form.
8. Pre-built sector site templates / 1-click provisioning - #1331. `bin/install` is
   parametric; no sector scaffolds.
9. In-app help surfacing - #1332. 540+ articles as `docs/help/*.md`; `ahg-help`
   integration layer is TODO.

Everything else the review lists is already covered.
