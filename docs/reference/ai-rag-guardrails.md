# RAG guardrails on the LLM dispatch pipeline

Every LLM / RAG call in Heratio and the AtoM-AHG plugins now passes through a
policy guardrail before dispatch and a grounding check after generation. This
was delivered under issue #141 (AI Governance & Sovereignty design doc, Goal 4).
Before it, prompts - including OCR text and sensitive ISAD(G) fields - went to
cloud providers unfiltered, any purpose was accepted, and RAG output was never
checked against its sources.

## The three guardrails

1. **allowed_data_scopes** - each request carries a `data_scope` (default
   `internal`). A cloud provider may only receive scopes listed in
   `rag_cloud_allowed_scopes`. Local providers (`rag_local_providers`, default
   `ollama`) carry any scope - the data never leaves the trust domain. Out-of-scope
   data to a cloud provider is blocked, and PII in cloud-bound prompts is masked.

2. **purpose limitation** - each request carries a `purpose`. It must be in the
   operator-sanctioned set (`rag_sanctioned_purposes`); an unsanctioned purpose
   is blocked or flagged.

3. **grounding / hallucination check** - when the caller passes a RAG source
   bundle (`context_sources`), the generated output is scored on how many of its
   significant terms appear in those sources. Below `rag_grounding_threshold` the
   output is flagged `low_grounding` as a possible hallucination.

## Modes

One operator setting, `rag_guardrail_mode`, governs enforcement strength:

- `off` - guardrails bypassed.
- `warn` (default) - guardrails compute and flag, but never block or alter a
  prompt. Safe to deploy: zero behaviour change, full observability.
- `mask` - PII in cloud-bound prompts is redacted; purpose violations flagged.
- `block` - out-of-scope data to a cloud provider, and unsanctioned purposes,
  are rejected outright.

Operators start on `warn`, watch the flags, then opt up to `mask` or `block`.

## Settings

heratio reads these from `ahg_ner_settings` (typed accessors on
`AhgAiServices\Support\AiServicesSettings`); the AtoM-AHG side reads them from
`ahg_ai_settings` with `feature = guardrails`. Keys and defaults are identical
on both sides:

| key | default |
|---|---|
| `rag_guardrail_mode` | `warn` |
| `rag_cloud_allowed_scopes` | `public,internal` |
| `rag_local_providers` | `ollama` |
| `rag_sanctioned_purposes` | `description_generation,summarization,translation,entity_extraction,spellcheck,research_assistance,metadata_enrichment` |
| `rag_grounding_threshold` | `0.45` |

## Where it runs

- **heratio** - `AhgAiServices\Services\GuardrailService`, called from
  `LlmService::dispatchToProviderFull()` (covers `complete()` and
  `completeFull()`) and from the cloud-mode override branch of `complete()`.
- **AtoM-AHG** - `GuardrailService` in `ahgAIPlugin/lib/Services/`, called from
  `LlmService::complete()`.

A blocked request returns `success=false` with `blocked=true`; every result
carries a compact `guardrail` array (mode, action, data_scope, purpose,
purpose_sanctioned, pii_masked, grounding_score, flags). The guardrail layer is
fail-open on an unexpected internal error - a bug in the policy code never
denies the AI service, though deliberate `block` decisions always block.

PII masking is jurisdiction-neutral: email addresses and digit sequences
carrying 9+ digits (phone / national-ID / account numbers) become
`[REDACTED:email]` / `[REDACTED:number]`. Short date ranges like `1939-1945`
are left intact.

## Provenance

On the heratio side the guardrail verdict is persisted: `ahg_ai_inference`
gained a nullable `guardrail` JSON column (idempotent ensureSchema ALTER, same
pattern as the #135/#136 columns), and `InferenceService::record()` writes it
when an `InferenceRecord` carries one. `LlmService::generateSuggestion()`
declares `purpose = description_generation`, supplies the retrieved fields as
the `context_sources` bundle, and forwards the verdict onto the inference row.
The verdict is deliberately kept out of the #136 signed manifest - it is an
operational annotation, not part of the cryptographic attestation.

The AtoM-AHG `LlmService` runs the same guardrails and returns the verdict in
the result array; it does not persist to `ahg_ai_inference` because the AtoM AI
actions do not record LLM inferences yet (a separate, pre-existing gap).

## Wired call sites

`generateSuggestion` on both sides declares its purpose and passes the RAG
provenance bundle, so the description-suggestion flow exercises all three
guardrails end to end. Other LLM callers (summarize, translate, spellcheck,
entity extraction) are guardrailed with defaults - data scope `internal`,
purpose `unspecified` (flagged, not blocked).

## Not yet done

- Settings-dashboard form fields for the five `rag_*` keys (the dashboard blade
  pages are change-locked; the keys work from defaults until then).
- Surfacing the `guardrail` column on the AI Governance dashboard (#137).
- A full RiC-triplestore ontology-checker and source `trust_score` - the
  pragmatic purpose check is the configurable sanctioned list.

## Tests

- heratio: `vendor/bin/phpunit packages/ahg-ai-services/tests` (15 tests).
- AtoM-AHG: `php ahgAIPlugin/testing/GuardrailServiceTest.php` (31 assertions,
  no bootstrap needed).
