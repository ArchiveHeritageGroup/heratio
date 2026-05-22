> Heratio Help Center article. Category: AI & Automation / Governance.

# AI Inventory & Governance

Heratio runs several AI services - named-entity recognition (NER), handwriting transcription (HTR), summarisation, translation and condition assessment. The **AI Inventory & Governance** dashboard is the single place to see which models are in use, how they are configured, what they have produced, and whether each result can be cryptographically verified.

Open it at **Admin -> AI Inventory & Governance** (`/admin/governance`). The page is admin-gated.

## Why this page exists

An archive that lets AI touch its catalogue records has to be able to answer three questions for any auditor, funder or freedom-of-information request:

1. **What model produced this?** Name, version and the full runtime configuration in force at the time.
2. **Has the result been tampered with since?** Every recorded inference carries a cryptographic signature over a canonical manifest of its inputs, outputs and model identity.
3. **Who configured the AI, and how?** The model inventory shows every LLM endpoint Heratio is wired to, minus the secrets.

The dashboard answers all three from one screen. It reads only - nothing on this page changes a record or re-runs a model.

## What the dashboard shows

The page has three regions.

**Stat cards** along the top: total inferences recorded, count of distinct models seen, how many inferences are signed, and the share of inferences carrying a model manifest.

**Model inventory table** - one row per configured LLM (from `ahg_llm_config`): the model name, its endpoint, the service it backs, and a manifest badge. API keys are **never** rendered on this page or returned by its JSON endpoints; only their presence is indicated.

**Recent inferences table** - the latest rows from `ahg_ai_inference`: service (NER / HTR / TRANSLATION / LLM / CONDITION), model name and version, target record, confidence, a manifest key-count badge, and a **signed** column that reads the real `signer_key_id` - not a placeholder.

Two JSON endpoints back the page for scripted audits:

- `GET /admin/governance/models` - the model inventory as JSON (keys redacted).
- `GET /admin/governance/inferences` - recent inferences as JSON.

## The model inventory

Every AI endpoint Heratio can call is described by a row in `ahg_llm_config`: a friendly model name, the HTTP endpoint, the service it serves, and an optional operator-curated **model manifest** (see below). The API key for each endpoint is stored in that table but is never exposed through the dashboard, its JSON endpoints, or the audit log.

To add or change a model configuration, use the AI settings screens - not this page. The governance dashboard is a read-only mirror of what those screens have configured.

## Model manifest

A **model manifest** is a small JSON object that captures the identity and material configuration of a model at the moment it ran: model name, version, endpoint, decoding parameters, and any operator notes. It exists so that a result can be reproduced and audited even years later, after the live model has been upgraded or retired.

There are two manifests, and they work together:

- **Operator-curated manifest** - an optional JSON blob you maintain on the `ahg_llm_config` row. Use it to record things Heratio cannot discover automatically: licence terms, training-data provenance, a model card URL, an internal approval reference.
- **Per-inference manifest** - composed automatically by `InferenceService::record()` every time an inference is logged. It takes the operator-curated manifest and overlays the live model identity reported at call time. The result is persisted on the `ahg_ai_inference.model_manifest` column and folded into the signed manifest.

The dashboard shows a key-count badge for each inference's manifest so you can see at a glance which results carry a full manifest and which were recorded before manifests were introduced.

The `model_manifest` columns are added to both tables idempotently by the package's `ensureSchema` routine - no manual migration is needed.

## Inference signing (Ed25519)

Every recorded inference can be cryptographically signed so that any later change to its manifest is detectable.

**How it works.** When an inference is recorded, `InferenceService::record()` builds a canonical, key-sorted manifest of the inference (service, model identity, input and output hashes, confidence, target, model manifest) and signs it with an **Ed25519** key using libsodium. The signature and the signing key's identifier are stored on the `ahg_ai_inference.signature` and `signer_key_id` columns.

**Setting it up.** Signing is opt-in. Until an operator key exists, inferences are still recorded - just unsigned. To switch signing on, generate an operator keypair once:

```
php artisan ahg:provenance-ai:keygen
```

This writes the keypair into `storage/app/ai-signing/`, which is gitignored. The **private key never leaves that directory** - it is never stored in the database, never committed to git, and never sent to the AI gateway. From the moment the key exists, new inferences are signed automatically.

**Verifying.** The signature covers a canonical serialisation of the manifest, so verification is deterministic: re-serialise the manifest, check the Ed25519 signature against the public key identified by `signer_key_id`. A failed check means the manifest was altered after the fact.

Signing is **best-effort**: if signing fails for any reason, the inference is still recorded (unsigned) rather than lost. The dashboard's signed count tells you how much of your inference history is covered.

## RAG guardrails

Every LLM and RAG call Heratio makes passes through a policy guardrail before the prompt is dispatched, and the generated text is checked for grounding afterwards. The guardrails enforce three things:

- **Data-scope policy.** Each request carries a data scope. A cloud provider may only receive scopes on the allow-list (`rag_cloud_allowed_scopes`, default `public,internal`); local providers carry any scope because the data never leaves the trust domain. Out-of-scope data to a cloud provider is blocked, and email addresses and long number sequences in cloud-bound prompts are masked.
- **Purpose limitation.** Each request declares a purpose. It must be in the operator-sanctioned set (`rag_sanctioned_purposes`); an unsanctioned purpose is blocked or flagged.
- **Grounding / hallucination check.** When a RAG call supplies its retrieved sources, the generated output is scored against them; output that is not grounded in those sources is flagged as a possible hallucination.

**Modes.** One setting, `rag_guardrail_mode`, sets the strength: `off`; `warn` (the default - compute and flag, but never block or alter a prompt); `mask` (redact PII in cloud-bound prompts); or `block` (reject out-of-scope data and unsanctioned purposes outright). Start on `warn`, watch the flags, then opt up. The five `rag_*` settings live alongside the other AI settings.

The guardrail verdict - mode, action, data scope, purpose, masked-PII count, grounding score and flags - is attached to every LLM result and persisted on the `ahg_ai_inference.guardrail` column for inferences that are recorded. The guardrail layer is **fail-open**: a bug in the policy code never denies the AI service, though a deliberate block always blocks.

## How AI requests are routed

All AI traffic from Heratio flows through one place: the **AI gateway** at `https://ai.theahg.co.za/ai/v1/...`. The gateway authenticates each request with a bearer token, routes it to whichever GPU worker is healthy, and logs it. Heratio is an AI *client* - it never hosts a model itself.

Handwriting transcription (HTR) is fully consolidated onto the gateway:

- The HTR endpoint is resolved from the **`htr_url` setting** (default `https://ai.theahg.co.za/ai/v1/htr`) - no hostnames are hardcoded.
- The older standalone HTR API is still reachable, but only through the gateway, under `/ai/v1/htr/legacy/...`. Callers that need crop-OCR or fine-tuned OCR go through that path.
- Every HTR call carries an `Authorization: Bearer` token, drawn from the API key stored in AI settings.

This means HTR requests are authenticated and appear in the gateway's audit trail alongside NER, translation and summarisation - there is no longer a side channel that bypasses governance. If you need to point HTR at a different endpoint (for example a local development server), set `htr_url` in AI settings; the `HTR_SERVICE_URL` environment variable remains as a developer-only override.

## Frequently asked questions

**Does opening this page run any models or change records?** No. It is entirely read-only.

**An inference shows as unsigned - is that a problem?** It means the inference was recorded before an operator signing key existed, or signing failed transiently. Run `ahg:provenance-ai:keygen` once to start signing; existing rows stay unsigned (signing is forward-only).

**Where are API keys?** Stored in `ahg_llm_config` and the AI settings tables, never shown here. The dashboard only reports whether a key is present.

**Can I export this for an audit?** Yes - the `/admin/governance/models` and `/admin/governance/inferences` JSON endpoints give you the inventory and the inference log in machine-readable form.

## See also

- **AI inference provenance - user guide** - the full inference and reviewer-override chain, and the provenance trace API.
- **AI Tools** - using NER, HTR, summarisation and translation day to day.
- **Provenance Management** - provenance for catalogue records generally.
- ADR-0002, *Provenance discipline baseline* (`docs/adr/0002-provenance-discipline-baseline.md`).
