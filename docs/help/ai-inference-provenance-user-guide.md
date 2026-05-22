> Heratio Help Center article. Category: AI & Automation / Provenance.

# AI inference provenance - user guide

Heratio captures full provenance for every AI inference: which model, what input and output (hashed plus excerpted), with what confidence, against which cataloguing standard, and which human reviewer corrected the AI's output and why. One HTTP request returns the entire chain for any record.

## Reading the chain

`GET /api/v1/provenance/{entityType}/{id}/trace` returns the full inference + override chain for one entity, grouped by field. Supported entity types: information_object, actor, repository, term, museum_metadata. The endpoint is auth-gated.

Every row carries:
- service name (NER / HTR / TRANSLATION / LLM)
- model name and version
- sha256 of input + output bytes plus a 500-char excerpt for human inspection
- confidence (where the model exposes one)
- the cataloguing standard the inference targets (ISAD(G), ICIP, RiC-O, Spectrum 5.1)
- target entity, field, timestamp, triggering user
- the Fuseki named graph URI where the canonical RDF-Star representation was written

`current_effective_value` per field is computed at read time: latest applied override wins; falls back to the inference output when no override exists.

A second endpoint, `GET /api/v1/provenance/coverage?days=N`, returns per-service inference counts plus average confidence over a window. Used for ops dashboards.

## Reviewer override flow

When you edit an AI-suggested field via any entity edit form (IO, museum, library, gallery, DAM, actor, repository), the system automatically detects whether your change overrides a recent AI inference. If it does, an override row is created with your user id and the before/after values. The original inference is NEVER overwritten - the override is a new event in the chain.

You don't see this happen; it's a side effect of saving. No separate "approve AI suggestion" UI to learn.

## Confidence threshold review queue

When confidence is below the per-service threshold configured in `ahg_settings.ai_provenance.<service>.confidence_review_threshold`, the inference auto-queues as a workflow review task. Reviewers see these in the standard workflow queue.

## What is NOT recorded

- AI runs that don't have a target entity at the moment they fire (Donut suggest-before-save is the canonical example - recorded only once the form is saved; tracked separately).
- AI rows produced before 2026-05-04 (forward-only by design).
- Purely manual edits to fields with no AI inference attached.
- Read-only AI runs (preview without persistence).

## Diagnostic SPARQL

`docs/diagnostics/provenance/` holds five query templates for ad-hoc audits against the Fuseki store:
- coverage-by-service.rq, recent-inferences-without-override.rq
- confidence-distribution.rq, lineage-by-record.rq, unprovenanced-triples.rq

Same shapes are available as JSON from the trace and coverage endpoints; SPARQL is for ad-hoc audits and downstream PROV-O consumers.

## See also

- ADR-0002: `docs/adr/0002-provenance-discipline-baseline.md`
- FOIA walkthrough: `docs/diagnostics/provenance/foia-walkthrough.md`
- KM reference: `docs/reference/ai-inference-provenance-discipline.md`
- GitHub issue #61 (closed)
- AtoM-AHG parity: the `atom-ahg-plugins` side now records and Ed25519-signs its own AI inferences too, with `ai-provenance:keygen` / `verify` / `replay` tasks (issue #140, closed).
