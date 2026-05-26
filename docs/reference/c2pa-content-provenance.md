# C2PA content provenance for AI-touched artefacts

**Package:** `packages/ahg-c2pa/` (namespace `AhgC2pa\`, composer `ahg/c2pa`)
**Spec:** https://c2pa.org/specifications/specifications/2.1/index.html
**Issue:** ArchiveHeritageGroup/heratio#692

## Why

Every AI suggestion, summary, translation, NER call, HTR transcription Heratio produces is itself "content". Increasingly, downstream consumers (regulators, publishers, audit teams under EU AI Act Art 12, Adobe Content Authenticity Initiative, news organisations) expect AI-generated or AI-assisted output to carry a cryptographically signed manifest that declares the AI involvement, the model that did it, and the human (if any) who reviewed it.

C2PA 2.1 is the prevailing standard. Heratio now emits a C2PA-conformant manifest for every AI-touched artefact, signed with the same Ed25519 key that signs the EU AI Act Article 12 inference receipt chain (so a verifier walks one key tree).

## Package layout

```
packages/ahg-c2pa/
  composer.json
  phpunit.xml
  database/install.sql               -- ahg_c2pa_manifest table
  src/
    Providers/AhgC2paServiceProvider.php
    Manifest/
      Assertion.php                  -- c2pa.actions.v2 / training-mining / ingredients
      Claim.php                      -- the central signed structure
      C2paSigner.php                 -- wraps inference-receipts Signer (Ed25519)
      ManifestBuilder.php            -- fluent assembler; JCS JSON + CBOR output
      CborEncoder.php                -- minimal deterministic CBOR for JUMBF embedding
    Services/C2paService.php         -- orchestration entry point
    Events/AiOutputProduced.php      -- dispatched by AI call sites
    Listeners/WriteC2paSidecar.php   -- catches event, persists, links to Article 12 chain
    Console/Commands/
      C2paSmokeCommand.php           -- c2pa:smoke
      C2paVerifyCommand.php          -- c2pa:verify
  tests/Unit/ManifestBuilderTest.php
```

## Manifest shape we emit

JSON form (the canonical authoritative serialisation; CBOR is byte-equivalent for media embedding):

```json
{
  "manifest_label": "ahg.heratio:<uuid>",
  "assertions": [
    {
      "label": "c2pa.actions.v2",
      "instance": 1,
      "data": {
        "actions": [{
          "action": "ai-generated",
          "when": "2026-05-26T10:00:00Z",
          "softwareAgent": {"name": "Heratio", "version": "1.92.0"},
          "parameters": {
            "model_id": "qwen3:14b",
            "model_version": "20260501",
            "output_sha256": "<hex>",
            "heratio_io_id": 12345
          }
        }]
      }
    },
    {
      "label": "c2pa.training-mining",
      "instance": 1,
      "data": {
        "entries": {
          "c2pa.ai_generative_training": {"use": "notAllowed"},
          "c2pa.ai_inference":           {"use": "notAllowed"},
          "c2pa.ai_training":            {"use": "notAllowed"},
          "c2pa.data_mining":            {"use": "notAllowed"}
        },
        "reason": "AI-derived artefact in archival custody; downstream training requires explicit licence"
      }
    }
  ],
  "claim": {
    "claim_generator": "Heratio/1.92.0 c2pa-php/1.0",
    "title": "Heratio AI ai-generated for IO #12345",
    "format": "text/plain",
    "instanceID": "xmp:iid:<random>",
    "signature": "self#jumbf=c2pa.signature",
    "alg": "sha256",
    "created": "2026-05-26T10:00:00Z",
    "asset_hash": {"alg": "sha256", "hash": "<hex of asset>"},
    "assertions": [
      {"alg": "sha256", "hash": "<hex>", "url": "self#jumbf=c2pa.assertions/c2pa.actions.v2__1"},
      {"alg": "sha256", "hash": "<hex>", "url": "self#jumbf=c2pa.assertions/c2pa.training-mining__1"}
    ]
  },
  "claim_signature": {
    "alg": "Ed25519",
    "kid": "<16 hex>",
    "sig": "<128 hex (64-byte Ed25519 detached signature)>",
    "pad": ""
  }
}
```

The signature is Ed25519 over `SHA-256(JCS(claim))`. JCS = RFC 8785 JSON Canonicalization (the same encoder used by `ahg-inference-receipts`). The signing key is the install's existing `storage/keys/inference-signing.sk` so verifiers resolve one `ai_inference_key` row for both Article 12 chain entries and C2PA manifests.

## Sidecar vs. embedded

Two emission paths:

1. **Sidecar JSON** (always works): `C2paService::sidecar($signed, $artefactPath)` writes `<path>.c2pa.json` next to the artefact. No native binary support required. This is the production default.
2. **JUMBF-embedded JPEG** (preferred when shipping images to external consumers): `C2paService::embedInJpeg()` shells out to the `c2patool` CLI (https://github.com/contentauth/c2patool) if installed at `/usr/local/bin/c2patool` or on `$PATH`. If absent, transparently falls back to (1).

To enable embedding on a deploy: download the prebuilt `c2patool` binary from the Content Authenticity Initiative releases page and drop it in `/usr/local/bin/`. No Heratio config change required, the autodetection picks it up.

For text artefacts (summaries, NER output, translations, HTR transcripts) there is no host media container, so the sidecar JSON is always the right answer.

## Integration shape (for ahg-ai-services when it unlocks)

`packages/ahg-ai-services/` is `.locked-paths` (see `feedback_lock_io_show_tree.md`). When that package next unlocks for unrelated work, wire each AI call site with one event dispatch right after the model returns:

```php
event(new \AhgC2pa\Events\AiOutputProduced(
    informationObjectId: $ioId,
    action: 'ai-generated',   // or 'ai-assisted' if a human is reviewing
    modelId: $modelId,
    modelVersion: $modelVersion,
    output: $generatedText,
    artefactPath: null,       // or the path to a generated image / PDF
));
```

The listener `WriteC2paSidecar` does the rest:

- builds the manifest via `C2paService::manifestForAiSuggestion()`
- signs it via the shared Ed25519 key
- if an `artefactPath` is supplied: embeds in JPEG (if c2patool present) or writes a sidecar
- persists a row in `ahg_c2pa_manifest`
- links the C2PA claim digest into the Article 12 inference chain via `InferenceLogger::log(service: 'c2pa', ...)` so the chain is the single source of truth for "what AI activity ever happened on this install"

The five call sites that will need the dispatch (audit, not changes yet):
- `packages/ahg-ai-services/src/Services/LlmService.php` (suggestions, summaries)
- `packages/ahg-ai-services/src/Services/NerService.php`
- `packages/ahg-ai-services/src/Services/HtrService.php`
- `packages/ahg-ai-services/src/Services/DonutService.php`
- `packages/ahg-ai-services/src/Services/GuardrailService.php` (when it overrides output)

## EU AI Act alignment

Article 50 (transparency: notice that content is AI-generated) and Article 12 (record-keeping) both require provable AI provenance. C2PA manifests satisfy Article 50's "machine-readable marking" obligation and, by being linked to the Article 12 chain via shared `kid`, also satisfy the record-keeping audit trail. The `c2pa.training-mining` assertion we ship sets all four sub-keys to `notAllowed` by default so AI-derived archival output is not silently slurped into someone else's training set.

## Commands

```bash
# Smoke: build + sign a manifest for a fake AI suggestion against IO #42
php artisan c2pa:smoke 42 "AI summary text" --action=ai-generated --model=qwen3:14b

# Verify a stored sidecar
php artisan c2pa:verify /path/to/file.txt.c2pa.json
```

## Future work

- Wire the `event(new AiOutputProduced(...))` dispatch into each `ahg-ai-services` call site (separate phase; this issue stops at making it ready).
- Implement the JUMBF box format natively (drop the `c2patool` dependency) so embedded JPEGs work out-of-the-box on every install.
- Surface a `/api/v2/c2pa/manifest/{ioId}` endpoint so external verifiers can pull a manifest without shell access to the install.
