# AI Inventory, Governance Dashboard and Inference Signing

Heratio records every AI inference and can sign it cryptographically. The AI Inventory & Governance dashboard surfaces that record: which models are configured, what they have produced, and whether each result is verifiable. This document covers the dashboard (heratio#137), the model manifest (heratio#135) and Ed25519 inference signing (heratio#136).

## The governance dashboard (heratio#137)

The dashboard lives in the `ahg-provenance-ai` package, served by `GovernanceController`:

- `GET /admin/governance` - the rendered Bootstrap 5 page (admin-gated).
- `GET /admin/governance/models` - model inventory as JSON.
- `GET /admin/governance/inferences` - recent inferences as JSON.

It is entirely read-only - it never re-runs a model or changes a record. The page has three regions: stat cards (total inferences, distinct models, signed count, manifest coverage), a model-inventory table from `ahg_llm_config`, and a recent-inferences table from `ahg_ai_inference`. API keys are never rendered or returned by the JSON endpoints; only their presence is indicated.

The AtoM-side mirror is the `executeGovernance` action in the AI module, rendered by `governanceSuccess.php`, at `/ai/governance` with `/ai/governance/{models,inferences}` JSON endpoints - Capsule queries over the same two table names.

## Model manifest (heratio#135)

A model manifest is a JSON object capturing a model's identity and material configuration at run time, so a result stays reproducible and auditable after the live model is upgraded or retired. There are two, working together:

- **Operator-curated manifest** - an optional JSON blob on the `ahg_llm_config` row, for facts the system cannot discover automatically: licence terms, training-data provenance, a model card URL, an approval reference.
- **Per-inference manifest** - composed automatically by `InferenceService::record()`: the operator-curated manifest overlaid with the live model identity reported at call time. Persisted on `ahg_ai_inference.model_manifest` and folded into the signed manifest.

Both `ahg_llm_config` and `ahg_ai_inference` carry a `model_manifest` JSON column, added idempotently by the package's `ensureSchema` routine. The dashboard shows a key-count badge per inference manifest.

## Ed25519 inference signing (heratio#136)

Every recorded inference can be signed so that any later change to its manifest is detectable.

- **Mechanism** - `InferenceService::record()` builds a canonical, key-sorted manifest of the inference (service, model identity, input/output hashes, confidence, target, model manifest) and signs it with an Ed25519 key via libsodium. The `InferenceSigner` class handles keygen, signing and verification.
- **Storage** - the signature and the signing key identifier are persisted on `ahg_ai_inference.signature` and `signer_key_id` (two nullable columns, added by an idempotent `ensureSchema` ALTER).
- **Keypair** - generated once by the operator with `php artisan ahg:provenance-ai:keygen`, written into `storage/app/ai-signing/` (gitignored). The private key never enters the database, git, or the AI gateway.
- **Opt-in and best-effort** - until the keypair exists, inferences are recorded unsigned. If signing fails transiently the inference is still recorded rather than lost. Signing is forward-only: existing unsigned rows stay unsigned.
- **Verification** - the signature covers a deterministic serialisation, so verifying is re-serialise-and-check against the public key named by `signer_key_id`. A failed check means the manifest was altered after recording.

The AtoM-side AHG plugins host the dashboard and the `signature` / `signer_key_id` / `model_manifest` schema, and the dashboard reads the real signed status - but the AtoM codebase does not yet record or sign its own AI calls; that recording path is tracked as a later issue.

## How the pieces fit

1. An AI service runs and returns a result.
2. `InferenceService::record()` composes the per-inference model manifest, hashes inputs and outputs, builds the canonical manifest, signs it (if a key exists), and writes the row to `ahg_ai_inference`.
3. The governance dashboard reads those rows back and reports model inventory, manifest coverage and signed status.
4. An auditor verifies any inference by re-serialising its manifest and checking the Ed25519 signature.

## See also

- `ai-gateway-htr-routing.md` - the AI gateway and HTR consolidation.
- `ai-inference-provenance-discipline.md` - the inference and reviewer-override provenance chain.
- ADR-0002, provenance discipline baseline.
