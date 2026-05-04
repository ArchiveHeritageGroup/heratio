# AI inference provenance discipline (issue #61)

Heratio captures full provenance for every AI inference: which model, what input and output (hashed plus excerpted), with what confidence, against which cataloguing standard, and which human reviewer corrected the AI's output and why. One HTTP request returns the entire chain for any record.

This document is the user-facing reference. The architectural rationale lives in ADR-0002 (`docs/adr/0002-provenance-discipline-baseline.md`); the worked example with verbatim trace JSON is in `docs/diagnostics/provenance/foia-walkthrough.md`; the diagnostic SPARQL queries are in `docs/diagnostics/provenance/*.rq`.

## What gets recorded

Every time an AI service produces output that lands in a record, a row is written to `ahg_ai_inference` with:

- service name (NER, HTR, TRANSLATION, LLM)
- model name and version
- sha256 of the input bytes plus a 500-char excerpt for human inspection
- sha256 of the output bytes plus a 500-char excerpt
- confidence score where the model exposes one (1 - CER for HTR, the spaCy entity score for NER, etc.)
- the cataloguing standard the inference is targeting (ISAD(G), ICIP, RiC-O, Spectrum 5.1, etc.)
- target entity type, entity id, and field
- elapsed call time (for ops dashboards)
- triggering user when known
- timestamp
- the Fuseki named graph URI where the canonical RDF-Star representation was written (NULL if the Fuseki write is queued for replay)

When a reviewer corrects an AI-suggested field via the entity edit form, a row is written to `ahg_ai_override` with a foreign key into `ahg_ai_inference`, capturing reviewer + reason + before-value + after-value + status. The original inference row is never modified - the override is a new event.

## Reading the chain (the trace endpoint)

`GET /api/v1/provenance/{entityType}/{id}/trace` returns the full inference + override chain for one entity, grouped by field.

Supported entity types: `information_object`, `actor`, `repository`, `term`, `museum_metadata`. The endpoint is auth-gated.

Response shape:

```json
{
  "ok": true,
  "entity": { "type": "information_object", "id": 905245 },
  "summary": { "inference_count": 3, "override_count": 1, "fields_touched": 3 },
  "fields": {
    "subject": [ { "inference": {...}, "overrides": [...], "current_effective_value": "..." } ],
    "scope_and_content": [ { "inference": {...}, "overrides": [...], "current_effective_value": "..." } ],
    "physical_characteristics": [ { "inference": {...}, "overrides": [], "current_effective_value": "..." } ]
  }
}
```

The `current_effective_value` per field is computed at read time: latest applied override wins; falls back to the inference's output excerpt when no override exists.

A second endpoint, `GET /api/v1/provenance/coverage?days=N`, returns per-service inference counts plus average confidence plus pending-Fuseki-write count over the last N days. Used for ops dashboards.

## What's wired today

| AI service | Wired through InferenceService | Notes |
|---|---|---|
| NER (named-entity recognition) | yes - `extractAndRecord(text, ioId, userId)` | Standard: `ICIP-name-access-points`. Confidence: NULL until upstream API exposes per-entity scores. |
| HTR (handwriting recognition) | yes - `extractAndRecord(filePath, ioId)` from the scan-pipeline job | Standard: `ISAD(G)-physical_characteristics`. Confidence: 1 - CER when CER is exposed. |
| Translation | yes - inside `TranslationController::store()` | Standard: `Heratio-i18n-MT`. Target field encoded as `<column>@<culture>`. |
| LLM (description drafting / summarisation) | yes - inside `LlmService::generateSuggestion()` | Standard: `RiC-O-scope_and_content`. |
| Donut (form-image-to-fields OCR) | deferred - issue #63 | Donut suggests values BEFORE a record exists; integration belongs in form-save handlers, not the AI service. |

## Reviewer override flow

Editing an AI-suggested field via any entity edit form (information_object, museum, library, gallery, dam, actor, repository) automatically detects whether the change overrides a recent AI inference. If it does, an `ahg_ai_override` row is created with the reviewer's user id and the before-value. The reviewer doesn't see this happen - it's a side effect of the save. There's no separate "approve AI suggestion" UI to learn.

When confidence is below the per-service threshold configured in `ahg_settings`, the inference is also auto-queued as a workflow review task with `metadata.kind = 'ai_inference_review'`. Reviewers see these in the standard workflow queue.

## Configuration knobs

In `ahg_settings`:

- `ai_provenance.<service>.confidence_review_threshold` - per-service threshold (NER, HTR, TRANSLATION, LLM). Below this, the inference auto-queues for review. Defaults: 0.7 / 0.85 / 0.6 / 0.5 per ADR-0002 sec 5.
- `ai_provenance.review_workflow_id` - workflow id to attach review tasks to. When unset, the threshold gate logs but doesn't queue (graceful no-op).
- `ai_provenance.review_step_id` - workflow step id within the workflow above.
- `fuseki_endpoint`, `fuseki_username`, `fuseki_password` - the Fuseki SPARQL store. The provenance code reads the dataset's `/update` endpoint as `<fuseki_endpoint>/update` unless `fuseki_update_endpoint` is set explicitly. RDF-Star support requires Apache Jena Fuseki >= 4.x.

## What is NOT recorded

- AI runs that don't have a target entity at the moment they fire. Donut "prefill" suggestions are the canonical example - they help fill out a new-record form and are recorded only once that form is saved (issue #63).
- AI rows produced before issue #61 Phase 1 shipped on 2026-05-04. ADR-0002 sec 6 commits to forward-only provenance: retroactive reconstruction would be guesswork.
- Reviewer changes to fields that have no inference attached. A purely manual edit looks no different to the system than a user-authored record from scratch.
- Read-only AI runs (a "preview entity extraction" call without persisting anything). Discipline applies to writes.

## How to write SPARQL diagnostics

`docs/diagnostics/provenance/` holds five committed query templates for the Fuseki canonical store:

- `coverage-by-service.rq` - one row per AI service with count and average confidence
- `recent-inferences-without-override.rq` - the "AI suggested, no human review yet" backlog
- `confidence-distribution.rq` - 10-bucket histogram per service for drift detection
- `lineage-by-record.rq` - given an entity URI, return inference + override chain as RDF
- `unprovenanced-triples.rq` - assertions that look like AI output but lack a `prov:wasGeneratedBy` back-pointer (the discipline-gap radar)

Run via curl with `Accept: application/sparql-results+json`. Same answers in JSON shape are available from the SQL store at the trace and coverage endpoints; the SPARQL queries are for ad-hoc audits and for downstream PROV-O consumers.

## What was not possible before

Before Phase 1 of issue #61 shipped, AI services wrote outputs directly to `object_term_relation`, `information_object_i18n`, and `museum_metadata_i18n` with no model identity, no version, no confidence, no input/output hash, no per-service standard tag, and no link from a reviewer's correction back to the originating AI decision. Answering "how was this archival description generated and on what basis" required forensic reconstruction across the application server logs, the AI service logs, and the Heratio per-table CRUD audit log - typically several hours of work, with parts of the chain unrecoverable. After Phase 4, that question is one HTTP GET.

## Operational follow-ups (not in the trace endpoint yet)

- **Fuseki replay job** (issue #62). When the Fuseki write fails (network, auth, schema issue), the SQL row commits with `fuseki_graph_uri = NULL`. A periodic worker picks up those rows and retries. Currently every row queues because the deployment's `fuseki_password` is blank, so writes return HTTP 401. Setting the password unblocks the Fuseki half.
- **Donut integration** (issue #63). Suggest-before-save flow needs the integration point at the form-save handler.

## See also

- `docs/adr/0002-provenance-discipline-baseline.md` - the seven architectural decisions
- `docs/diagnostics/provenance/foia-walkthrough.md` - verbatim worked example on a real record
- `docs/diagnostics/provenance/README.md` - SPARQL diagnostic index
- `docs/papers/provenance-discipline-section-draft.md` - paper section drafts (SAMAB 48 + LLM Public Sector)
- GitHub issue #61 (closed 2026-05-04)
