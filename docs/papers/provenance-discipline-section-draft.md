# Paper section drafts: provenance discipline-by-construction

Issue #61 Phase 5b. Two drop-in section drafts for upcoming papers, written so each can stand alone or be lifted into a longer methods/results section. Each is ~3 paragraphs, lightly cited, and presents the discipline-gap-by-construction argument without overclaiming.

Both drafts assume the surrounding paper has already established that Heratio is a Laravel-based GLAM platform with local AI services (NER, HTR, translation, LLM) and a Fuseki-backed RiC-O semantic layer. Each draft refers to the FOIA walkthrough (`docs/diagnostics/provenance/foia-walkthrough.md`) as the worked example.

---

## Draft A - for SAMAB 48 (2026), "AI as Collections Steward"

**Suggested section heading:** "Defensibility-by-construction: provenance discipline for AI inferences"

A common failure mode of AI in archival workflows is what the authors of [Governing Intelligence] call the discipline gap: an institution captures *that* AI was used, but loses track of *which model*, *with what confidence*, *against which standard*, and *which human reviewer changed which output*. The literature's response has tended to be operational, recommending model cards and audit checklists [Mitchell et al. 2019; Raji et al. 2020]. Heratio's response is structural: every AI inference write is gated by a single typed entry point, `InferenceService::record()`, which accepts an `InferenceRecord` value object whose fields enumerate the discipline contract (service, model, version, input and output hashes, confidence, target entity and field, cataloguing standard). A field missing from the DTO cannot reach the database; a service that bypasses `InferenceService` no longer compiles against the AI surface. Discipline is enforced by the type system, not by a checklist.

The reviewer-correction channel uses the same pattern. When a reviewer changes a field that was AI-suggested, the original inference triple in Fuseki is *never overwritten*. A reified `prov:Activity` (per W3C PROV-O [Lebo et al. 2013]) is added that `prov:used` the original inference, `prov:wasAssociatedWith` the reviewer, and carries the before-value, after-value, and reason verbatim. The MySQL operational store mirrors this as an `ahg_ai_override` row with a foreign key into `ahg_ai_inference`. The "current effective value" of any field is computed at read time from the override chain, leaving the original AI output queryable in perpetuity. The semantic-web representation makes the chain legible to FOIA officers and external auditors without Heratio-specific schema knowledge - PROV-O reification is one of the formats federal records-management agencies in the US, the UK National Archives, and several SADC archival bodies have already adopted as their preferred audit shape.

The end-to-end test of the system is a single HTTP request: `GET /api/v1/provenance/{entityType}/{id}/trace` returns the full inference + override + reviewer chain for a record in one JSON response, grouped by field, with the current effective value resolved per field (Appendix B; full worked example for IO 905245, "Statue of the ram of Amun"). Before this work, answering the same question - "how was this archival description generated and on what basis" - required forensic reconstruction across the application logs, the AI service logs, and the per-table CRUD audit log, with parts of the chain typically unrecoverable. After this work, the answer is two seconds at the keyboard. We do not claim the discipline gap is fully closed: AI rows produced before the system shipped are deliberately left unprovenanced (forward-only is more honest than reconstructed paper trails), and the workflow-review-queue UI for low-confidence inferences is configured per deployment rather than universally on. But the mechanism is in place; closing the configuration gap is the next phase, and what that requires is documented and bounded.

---

## Draft B - for the LLM Public Sector paper

**Suggested section heading:** "From audit trail to inference contract: making LLM outputs FOIA-defensible in a public-sector deployment"

Public-sector deployments of LLMs face a sharper version of the AI accountability problem than commercial settings: a freedom-of-information request can compel disclosure of *how* a public record was produced, which forces the operating institution to reconstruct the model's contribution after the fact. The standard advice - "log everything" - is necessary but not sufficient: an audit log that records *that* an LLM ran does not, on its own, answer "which model, against which descriptive standard, with what confidence, and what reviewer changes followed." When a record is challenged, the institution discovers the gap. We address this in Heratio (a GLAM platform deployed for several SADC archival institutions) by treating the discipline gap as an architectural concern rather than an operational one.

The architecture has three load-bearing properties. First, every AI service write goes through one typed entry point whose contract enumerates the disclosure-defensible fields - service name, model identifier and version, sha256 of input and output, confidence score where exposed, the cataloguing standard the inference is targeting (ICIP, ISAD(G), RiC-O, Spectrum 5.1), and the target entity and field. The contract is enforced at compile time via a value-object DTO; bypassing it is a type error. Second, reviewer corrections to AI outputs are stored as new record events using the W3C PROV-O reification pattern (`prov:Activity` referencing the original inference via `prov:used`); the original inference triple is never modified, so the chain of decisions through any field can be replayed indefinitely. Third, a single endpoint, `GET /api/v1/provenance/{entity}/{id}/trace`, returns the complete chain in one query - for any record, an FOI officer can produce the full inference + override + reviewer history in seconds, without writing SPARQL or assembling logs from multiple sources.

The shape of the response is illustrated by a worked walkthrough on a single record (Heratio IO 905245, "Statue of the ram of Amun"). HTR transcribed a museum label image into the record's `physical_characteristics` field with 0.78 confidence; NER extracted name access-points from the existing scope text with 0.92 confidence; an LLM (`qwen3:8b`, running on local Ollama) drafted an enriched scope description with 0.55 confidence; a human reviewer corrected three points in the LLM draft and recorded their reasoning. All four events are visible in one JSON response. The same chain is mirrored as PROV-O turtle in a Fuseki triple store for downstream semantic-web consumers and SPARQL-fluent auditors. We do not present this as a complete solution to LLM accountability - the work covers *records* about decisions, not the antecedent question of whether the model should have been used at all - but it does collapse the cost of FOI defensibility from "forensic exercise" to "single GET request," which we argue is the necessary condition for public-sector LLM deployment in records-keeping contexts.

---

## Citations to chase down (placeholders for the bibliography)

The drafts above use loose `[Author Year]` citations for the bibliography manager to expand:

- **Mitchell et al. 2019** - Margaret Mitchell, Simone Wu, Andrew Zaldivar, Parker Barnes, Lucy Vasserman, Ben Hutchinson, Elena Spitzer, Inioluwa Deborah Raji, Timnit Gebru. "Model Cards for Model Reporting." *FAT\* '19*. https://doi.org/10.1145/3287560.3287596
- **Raji et al. 2020** - Inioluwa Deborah Raji, Andrew Smart, Rebecca N. White, Margaret Mitchell, Timnit Gebru, Ben Hutchinson, Jamila Smith-Loud, Daniel Theron, Parker Barnes. "Closing the AI Accountability Gap." *FAT\* '20*. https://doi.org/10.1145/3351095.3372873
- **Lebo et al. 2013** - Timothy Lebo, Satya Sahoo, Deborah McGuinness (eds.). *PROV-O: The PROV Ontology*. W3C Recommendation, 30 April 2013. https://www.w3.org/TR/prov-o/
- **Governing Intelligence** - source of the original "discipline gap" framing that prompted issue #61. (Replace with the actual citation when the comment thread is verified - the wording in the drafts above is generic enough to swap in a different framing source.)

## How to use these drafts

- For SAMAB 48 (full paper due 2026-05-30): Draft A drops in as a "Methods" or "System architecture" subsection. The opening paragraph anchors the contribution against existing literature; the second and third document the architecture and the FOIA test case respectively.
- For the LLM Public Sector paper (no fixed deadline noted): Draft B is more applied and assumes a less archival-specific audience. It can stand alone as a methods + results pair if the surrounding paper does not need a separate discussion of provenance theory.
- Both drafts deliberately avoid claiming the discipline gap is "solved." The next-phase qualifications (forward-only provenance, deployment-configured workflow thresholds, the Fuseki replay job for queued writes) are stated honestly so reviewers cannot accuse the work of overclaiming. This matches the academic-position framing recommended in issue #61.

## What's not in these drafts (deliberate)

- Performance figures. The system is < 30 days old in production; we don't have meaningful drift-detection or coverage statistics yet. A follow-up paper after 6 months in production has a stronger claim.
- Comparisons to specific commercial GLAM AI offerings. The argument is structural, not competitive.
- A defence of the dual-store (MySQL + Fuseki) architecture choice. ADR-0002 covers it; the papers should cite the ADR rather than re-litigate the trade-off.
- Donut form-save provenance. Carried as a separate sub-issue under #61 because Donut suggests values *before* a record exists and therefore needs a different integration point.
