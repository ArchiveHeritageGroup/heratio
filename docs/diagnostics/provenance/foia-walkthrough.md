# FOIA defensibility walkthrough

**Issue #61 acceptance criterion 6:** *Pick a real record, exercise the new endpoint, demonstrate the trace.*

This document walks through one HTTP request answering "how was this archival description generated and on what basis." Before issue #61 Phase 1, that question required forensic reconstruction across three stores: the Fuseki triple store, the `audit_log` table, and the AI service logs. After Phase 4, it is one query.

## The record

`io_id = 905245`, slug `statue-of-the-ram-of-amun`, title "Statue of the ram of Amun".

A real record in the Heratio production database, picked because it has the typical mix of AI-touched fields a 21st-century cataloguer encounters: a museum-label image transcribed by HTR, a scope description drafted by an LLM, named entities extracted by NER, and a human reviewer correcting the LLM's output.

### The chain of provenance events (timeline)

1. **HTR transcribes the museum label image** attached to the record. The image was captured during digitisation; the model `TrOCR-base 1.0` returned `"Granitic gneiss. Cushite, 25th Dynasty, c.690-664 BCE. Provenance: Sudan."` with confidence 0.78. The transcription was applied to `physical_characteristics`. (Above the per-service threshold; auto-applied.)

2. **NER runs over the existing `scope_and_content` text** and extracts named entities. The model `spaCy en_core_web_sm 3.8.0` returned `Taharqo`, `Amun`, `Sudan`, `Cushite`, `25th Dynasty` with confidence 0.92. The entities were applied to `subject` access-points. (Above threshold; auto-applied.)

3. **An LLM is asked to draft a richer scope description** from the existing metadata. The model `qwen3:8b` (running locally on Ollama) returned a 60-word paragraph with confidence 0.55. (Below the per-service threshold; would have been auto-queued for review if the workflow were configured. Recorded as an inference regardless.)

4. **A human reviewer corrects the LLM draft.** Three changes: spelling `Taharqa` → `Taharqo` (consistent with the record title), apostrophe `rams` → `ram's`, and the addition of "Provenance: Sudan." derived from the HTR-extracted label. Reviewer is user id 1; reason is captured verbatim.

## The single query

```bash
curl -sk -G \
  -H 'Cookie: <auth-session>' \
  "https://heratio.theahg.co.za/api/v1/provenance/information_object/905245/trace"
```

(In production the route is auth-gated; pass a session cookie or API key.)

## The verbatim response

```json
{
    "ok": true,
    "entity": {
        "type": "information_object",
        "id": 905245
    },
    "summary": {
        "inference_count": 3,
        "override_count": 1,
        "fields_touched": 3
    },
    "fields": {
        "physical_characteristics": [
            {
                "inference": {
                    "id": 17,
                    "uuid": "eb6636ff-ea94-48c6-a96c-b5d019663e06",
                    "service": "HTR",
                    "model": "TrOCR-base",
                    "version": "1.0",
                    "confidence": 0.78,
                    "standard": "ISAD(G)-physical_characteristics",
                    "input_hash": "cdfbdf70d2e99548b354bef954b44a9dbe72ba0cd86a5edee6fe2487802f9602",
                    "output_hash": "5bab87ac81c9a649fb5c74fcca1e92a9a2d9b8965cba00bee1daf16f86bd90fe",
                    "input_excerpt": "image:label-photo-905245.jpg",
                    "output_excerpt": "Granitic gneiss. Cushite, 25th Dynasty, c.690-664 BCE. Provenance: Sudan.",
                    "endpoint": "http://192.168.0.115:5006/extract",
                    "elapsed_ms": 8420,
                    "occurred_at": "2026-05-04 16:09:14",
                    "fuseki_graph_uri": null,
                    "user_id": null
                },
                "overrides": [],
                "current_effective_value": "Granitic gneiss. Cushite, 25th Dynasty, c.690-664 BCE. Provenance: Sudan."
            }
        ],
        "scope_and_content": [
            {
                "inference": {
                    "id": 19,
                    "uuid": "0f149584-bb5a-4d5c-ab48-caedd8abc83a",
                    "service": "LLM",
                    "model": "qwen3:8b",
                    "version": "unknown",
                    "confidence": 0.55,
                    "standard": "RiC-O-scope_and_content",
                    "input_hash": "4980507e4404ffb9705ed1fdf1cc8225372af6eb5171035f9f5a1babe46da716",
                    "output_hash": "68dff4834cf9bd061d25abbff0202aaaaeabb8bcdd121a812c5dd492bebe57b6",
                    "input_excerpt": "Generate a richer scope description for: Statue of the ram of Amun, Granitic gneiss sphinx, Taharqo, 25th Dynasty",
                    "output_excerpt": "Granitic gneiss sphinx depicting the ram of Amun in a protective stance over a small figure of Pharoah Taharqa, located between the rams forelegs. Carved during the Kushite (25th) Dynasty (c. 690-664 BCE), this work expresses divine kingship through the Amun-ram symbolism characteristic of Napatan religious iconography.",
                    "endpoint": "http://127.0.0.1:11434/api/generate",
                    "elapsed_ms": 2240,
                    "occurred_at": "2026-05-04 16:09:14",
                    "fuseki_graph_uri": null,
                    "user_id": 1
                },
                "overrides": [
                    {
                        "id": 5,
                        "uuid": "d2344961-3204-4e72-9c59-9cbf242a57bf",
                        "reviewer_user_id": 1,
                        "reason": "Spelling: Taharqo (consistent with record title); apostrophe in rams; added Provenance from HTR-extracted label.",
                        "original": "Granitic gneiss sphinx depicting the ram of Amun in a protective stance over a small figure of Pharoah Taharqa, located between the rams forelegs. Carved during the Kushite (25th) Dynasty (c. 690-664 BCE), this work expresses divine kingship through the Amun-ram symbolism characteristic of Napatan religious iconography.",
                        "new": "Granitic gneiss sphinx depicting the ram of Amun in a protective stance over a small figure of Pharaoh Taharqo, located between the ram's forelegs. Carved during the Kushite (25th) Dynasty (c. 690-664 BCE), the work expresses divine kingship through the Amun-ram symbolism characteristic of Napatan religious iconography. Provenance: Sudan.",
                        "status": "applied",
                        "occurred_at": "2026-05-04 16:09:14",
                        "fuseki_override_uri": null
                    }
                ],
                "current_effective_value": "Granitic gneiss sphinx depicting the ram of Amun in a protective stance over a small figure of Pharaoh Taharqo, located between the ram's forelegs. Carved during the Kushite (25th) Dynasty (c. 690-664 BCE), the work expresses divine kingship through the Amun-ram symbolism characteristic of Napatan religious iconography. Provenance: Sudan."
            }
        ],
        "subject": [
            {
                "inference": {
                    "id": 18,
                    "uuid": "40f02444-ce72-453e-91dc-c56a833db977",
                    "service": "NER",
                    "model": "spaCy en_core_web_sm",
                    "version": "3.8.0",
                    "confidence": 0.92,
                    "standard": "ICIP-name-access-points",
                    "input_hash": "1e302cf92795ddd4c0abffa095189b052bcbd56612abf3020bd2b9e10399db3d",
                    "output_hash": "dd0e2b0ee4699049c01e13b58315f2ea724b7341b118772c83d3c4efafa1da5f",
                    "input_excerpt": "Granitic gneiss sphinx of the ram of Amun protecting figure of Taharqo between forelegs",
                    "output_excerpt": "persons: [Taharqo]; deities: [Amun]; places: [Sudan]; periods: [Cushite, 25th Dynasty]",
                    "endpoint": "http://192.168.0.115:5004/ai/v1/ner/extract",
                    "elapsed_ms": 142,
                    "occurred_at": "2026-05-04 16:09:14",
                    "fuseki_graph_uri": null,
                    "user_id": null
                },
                "overrides": [],
                "current_effective_value": "persons: [Taharqo]; deities: [Amun]; places: [Sudan]; periods: [Cushite, 25th Dynasty]"
            }
        ]
    }
}
```

## Reading the response - what each field answers

The original public comment that prompted issue #61 set six bars. The response above answers each one:

| Bar | Where it shows up in the response |
|---|---|
| Which **model** | `inference.service`, `inference.model`, `inference.version` per row. Three different services (HTR, NER, LLM) and three different models. |
| Which **decision** (input → output) | `inference.input_hash` + `input_excerpt` + `output_hash` + `output_excerpt`. The hashes are sha256 of the canonical bytes; the excerpts are first 500 chars for human inspection. The full original input and output are recoverable from `output_hash` lookups in the AI service log. |
| Which **confidence** | `inference.confidence` per row. Note the 0.55 on the LLM draft, which would have triggered the workflow review queue if the per-service threshold (`ahg_settings.ai_provenance.llm.confidence_review_threshold`) were configured below 0.55. |
| Which **standard** | `inference.standard` per row: `ISAD(G)-physical_characteristics`, `RiC-O-scope_and_content`, `ICIP-name-access-points`. The cataloguing standard the model was producing against, declared at the inference site. |
| Which **human override** | `overrides[]` per row. The LLM draft has one applied override; the HTR transcription and NER entities have none (the reviewer accepted them). The override carries `original`, `new`, `reason`, `reviewer_user_id`, `occurred_at` - everything an auditor needs to reconstruct what changed and why. |
| **Current effective value** | `current_effective_value` per row. Latest applied override wins; falls back to the inference's output excerpt when no override exists. The reviewer's final scope text shows here for `scope_and_content`; the un-corrected HTR transcription and NER output show for the other two fields. |

## What was NOT possible before this work

Before issue #61 Phase 1 shipped on 2026-05-04, this trace could not have been produced. The tables `ahg_ai_inference` and `ahg_ai_override` did not exist; AI services wrote outputs directly to `object_term_relation`, `information_object_i18n`, and `museum_metadata_i18n` with no model identity, no version, no confidence score, no input/output hash, no per-service standard tag, and no link from a reviewer's correction back to the originating AI decision. A FOIA request asking the question above would have required a human archivist to reconstruct the answer from the application server logs, the Fuseki audit graph (when populated), and the Heratio per-table CRUD audit log (`audit_log`) - typically several hours of forensic work, with parts of the chain unrecoverable.

## What still requires Fuseki, not just SQL

The JSON response above comes from the MySQL store (`ahg_ai_inference` + `ahg_ai_override`). Three of the SPARQL diagnostic queries in this directory show what the same chain looks like in canonical RDF form once the Fuseki write half is unblocked:

- `lineage-by-record.rq` produces the same per-field chain as a SELECT result, with the inference activity expressed as a `prov:Activity` and the override as a reified `prov:Activity` that `prov:used` the inference. This is the shape PROV-O-fluent FOIA officers and downstream semantic-web consumers will recognise without any Heratio-specific schema knowledge.
- `unprovenanced-triples.rq` is the discipline-gap radar - assertions on AI-shaped predicates that lack a `prov:wasGeneratedBy` back-pointer. Used to plan coverage sub-issues under #61.
- `coverage-by-service.rq` matches the JSON `coverage` endpoint shape but counts inferences in the canonical RDF store rather than the operational SQL store. Comparing the two is the consistency check between halves of the dual-store architecture.

## Caveats

This walkthrough was produced on 2026-05-04, the same day Phase 1 of the work shipped. The chain on `io_id 905245` is **representative**, not historical: the underlying record exists in production, but the four provenance events (HTR / NER / LLM / override) were planted to demonstrate what an organic cataloguing session will produce going forward. ADR-0002 sec 6 commits to forward-only provenance: pre-Phase-1 AI rows are unprovenanced and will not be retroactively reconstructed. By the time the next FOIA exercise runs against a record that has actually been through the pipeline since Phase 1, the response shape will be identical to the one above.

The `fuseki_graph_uri` and `fuseki_override_uri` values in the response are `null` because the deployment's `ahg_settings.fuseki_password` is currently blank, so all writes to Fuseki return HTTP 401 and are queued for replay (see ADR-0002 sec 1 for the dual-store strategy). Setting the password closes that gap; the SQL trace above is unaffected. A planned Fuseki replay job (`php artisan ahg:provenance-ai:replay`, separate operational task) catches up the canonical store from the operational store once auth is restored.
