# Knowledge Platform — gaps, incomplete code, and suggested enhancements

This note summarises the current state of the "Knowledge Platform" concerns for the Research module and surrounding Heratio services, identifies concrete gaps and incomplete code to fix, and proposes practical enhancements and acceptance criteria. Place this file in the Research docs so developers and operators can act on the findings.

---

## 1) High-level intent

A Knowledge Platform in Heratio (the Research context) should provide:

- Reliable ingestion pipelines for research artefacts (publications, datasets, claims, annotations, notes).
- A canonical knowledge index / vector store for semantic search and retrieval (RAG) used by the Writing Studio, Question Builder, Analysis Bridge and AI features.
- Strong provenance for every assertion (who/what/when/why) linked into research objects and exported to RiC where appropriate.
- A developer-facing API and UI components for browsing, curating, and exporting knowledge (graph exports, snapshots, JSON-LD).

This document identifies what is missing, what is incomplete, and pragmatic next steps.

---

## 2) First look — gaps (what is missing)

1. Central ingestion pipeline
   - No documented, resilient ingest pipeline that normalises uploads (PDFs, datasets, metadata) into canonical research entities + embeddings. Ingestion is scattered across studios and services.
   - Consequence: inconsistent metadata, duplicated indexing code, and missed provenance capture.

2. Single canonical vector index / search API
   - AI features reference an external AI gateway and an `ai_provenance` table, but there is no single, documented vector-index abstraction (service + config) for Research to rely on (index name, schema, refresh rules, pruning policy).
   - Consequence: slices implement ad-hoc embedding and retrieval logic leading to duplicated cost and inconsistent recall.

3. Provenance-first ingestion
   - Ingest steps do not consistently create a provenance record that ties the raw resource, the extracted metadata, the embedding, and the actor who triggered ingestion.
   - Consequence: auditability and explainability gaps; hard to reproduce or revert ingestion outcomes.

4. Knowledge governance & lifecycle rules
   - No documented policies for retention, embargo, or access control specifically for knowledge-indexed items (e.g. embargoed datasets vs public summaries).
   - Consequence: regulatory/compliance risk if sensitive research data is indexed and surfaced incorrectly.

5. Export & interoperability
   - No clear exporter that turns knowledge objects into RiC or JSON-LD snapshots suitable for long-term archival ingestion.
   - Consequence: integration gaps between Research and the archival graph (ahg-ric) and external aggregators.

6. Tests and CI for the knowledge layer
   - Unit and feature tests for ingestion, indexing, and provenance writes are sparse or missing.
   - Consequence: regressions are likely when changing the indexing or AI plumbing.

---

## 3) Look at incomplete code (where to inspect and what to fix)

The Research module touches many code paths. Inspect these areas and files as a starting point (the exact file list may vary by install; search for references to `ai_provenance`, `AnalysisBridge`, `Embedding`, `vector`, `ai_gateway`):

- Research AI / embedding calls
  - Check Writing Studio, Question Builder, AnalysisBridge for ad-hoc embedding logic. Ensure there are helper services that call a single vector-index service.

- ai_provenance & audit
  - Investigate where `ai_provenance` is written. Ensure every AI call (embedding + generation) creates an ai_provenance row including: caller, prompt, model, params, response, confidence, and user-reviewed flag.

- Knowledge index abstraction
  - Look for non-unified code that stores embeddings directly (DB blobs, local files). Replace with a `KnowledgeIndexService` interface used by all slices. Implement one adapter for the configured vector store.

- Ingest/transform pipeline
  - Search for PDF ingest helpers, OCR calls, and metadata extractors. Consolidate into a single `IngestService` that emits events: raw_uploaded -> extracted_metadata -> embedding -> provenance_written -> indexed.

- Export & RiC bridge
  - Ensure ResearchService emits events (project.created, claim.registered, output.published). Implement `RiCBridgeService` subscriber that maps research entities to RiC concepts and writes export snapshots.

- Tests
  - Add tests that mock the vector store, assert ai_provenance writes, and verify that ingestion produces both an index record and a provenance row.

If any of the above services/files are missing or fragmented, treat them as incomplete code that must be consolidated.

---

## 4) Suggested enhancements (concrete, actionable)

Priority: High

1. Implement a KnowledgeIndexService abstraction
   - Methods: indexDocument(id, metadata, embedding, tags), search(query, k, filters), delete(id), refreshIndex(ids[])
   - Adapters: `QdrantAdapter`, `MilvusAdapter`, `LocalMockAdapter` for tests
   - Acceptance: all Research slices call this service for embeddings and retrieval; no direct embedding storage in feature code.

2. Build an IngestService pipeline
   - Single point for: file ingestion, OCR/NER extraction, metadata normalisation, checksum/fixity, embedding, provenance creation, and index call.
   - Emit domain events at each stage for observability and consumers (RDF exporter, RiC bridge).
   - Acceptance: ingestion of a PDF creates one research_document, one provenance record, and one index record (end-to-end test asserts rows exist).

3. Enforce provenance-first policy
   - Every generated or machine-suggested relation must be stored as a relation object with provenance metadata (agent, activity, evidence, confidence).
   - Add a small `ProvenanceCard` UI in the Writing Studio and Claim Ledger showing the provenance chain for any relation.
   - Acceptance: the UI shows the original prompt and the reviewer decision for AI-suggested edges.

Priority: Medium

4. Event bus + RiC bridge
   - Emit domain events from ResearchService for key lifecycle changes and implement `RiCBridgeService` to consume and map to RiC entities.
   - Acceptance: `php artisan research:export-riC <projectId>` produces a valid RiC snapshot for the sample project.

5. Governance & lifecycle rules for knowledge objects
   - Express retention/visibility as graph edges (Mandate → Activity → retention rule). Integrate with compliance modules (POPIA) and the access-control service.
   - Acceptance: policies block index search results for embargoed items unless the caller has proper scope.

Priority: Low

6. Cross-index deduplication & canonical IDs
   - When multiple slices ingest the same external DOI/file, use canonical deduplication via content-hash and link references to a single canonical knowledge object.
   - Acceptance: repeated ingest of the same PDF results in one index entry and provenance entries referencing it.

7. Cost & usage metering
   - Track embedding/generation counts per user and feature; surface a simple admin usage dashboard.
   - Acceptance: admin page shows monthly LLM calls and vector index API usage.

---

## 5) Implementation sketch (small roadmap)

Phase 1 — Foundations (2–5 days)
- Create KnowledgeIndexService interface and a LocalMockAdapter for tests
- Consolidate embedding calls to that service
- Add ai_provenance enforcement: modify all AI call sites to write provenance (prompt, model, response, confidence)
- Add tests for ingestion → provenance → index flow

Phase 2 — Pipeline & UI (3–7 days)
- Implement IngestService with domain events and a simple queue worker (or synchronous for now)
- Wire Writing Studio to show ProvenanceCard for AI suggestions
- Add a small admin usage page for LLM/index counts

Phase 3 — Exports & governance (5–10 days)
- Implement RiCBridgeService subscriber and mapping spec for research objects
- Add policy enforcement for embargoed items at query-time and in the ingestion flow
- Add deduplication and canonical-linking

---

## 6) Suggested tests / acceptance criteria

- Unit: KnowledgeIndexService mock adapter tests; IngestService transforms input into expected metadata.
- Feature: End-to-end ingestion test (file -> extracted metadata -> provenance row -> index record -> search returns the document).
- Security: Ensure embargoed document is not returned by search for an unauthorized user.
- Audit: Provenance entries must contain prompt, model, timestamp and reviewer decision for AI proposals.

---

## 7) Where to add the docs

- This file should live at `docs/research/knowledge-platform.md` (done). Link it from the Research help index and the architecture doc.

---

If you want I can now:

1. Scaffold the KnowledgeIndexService interface + LocalMockAdapter and a single test.  
2. Add ai_provenance enforcement at the top 3 AI call sites (WritingStudio, AnalysisBridge, QuestionBuilder).  
3. Draft the RiC mapping spec for Research→RiC and implement a minimal RiCBridgeService subscriber.  

Reply with the number (1–3) to pick which implementation task to start.
