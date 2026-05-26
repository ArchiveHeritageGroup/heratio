> Heratio Help Center article. Category: AI & Compliance.

# C2PA Content Provenance

## Overview

When Heratio's AI services produce or assist with content (suggestions, summaries, translations, transcriptions, NER), Heratio attaches a **C2PA 2.1 manifest** to that output. The manifest is a cryptographically signed declaration that says "this content was AI-generated (or AI-assisted), by this model, on this date, against this archival description".

Downstream consumers (regulators, publishers, audit teams) can verify the manifest without contacting Heratio - all they need is the public Ed25519 key (published at the operator's `ai_inference_key` endpoint).

## How it works

1. An AI service produces an artefact (text, image, transcript).
2. Heratio assembles a C2PA manifest with:
   - a **c2pa.actions.v2** assertion describing what happened (ai-generated or ai-assisted, which model, which model version, when, against which archival description)
   - a **c2pa.training-mining** assertion declaring whether the artefact may be used for downstream AI training (default: notAllowed)
3. The manifest is signed Ed25519 with the same key the system uses for its EU AI Act Article 12 inference receipt chain.
4. The signed manifest is written next to the artefact as a sidecar JSON (`<artefact>.c2pa.json`), or - for JPEGs, when the operator has installed the `c2patool` CLI - embedded directly in the JPEG as a JUMBF box.
5. A row is logged in `ahg_c2pa_manifest` and a digest of the manifest is added to the Article 12 chain, so "all AI activity on this install" is queryable in one place.

## Verifying a manifest

Operators (or external auditors) can verify a manifest with:

```
php artisan c2pa:verify /path/to/artefact.c2pa.json
```

The command re-hashes every assertion, validates the claim signature, walks the ingredient chain, and prints a pass/fail summary. Tampering (a single byte change in any assertion) fails verification immediately.

## EU AI Act alignment

C2PA manifests satisfy Article 50 of the EU AI Act (transparency: machine-readable marking of AI-generated content). The linkage to Heratio's Article 12 inference receipt chain (shared signing key) satisfies the corresponding record-keeping audit trail. The default training-mining stance (notAllowed) protects archival output from being silently scraped into someone else's model.

## What gets a manifest

| AI service                    | Manifest action |
|-------------------------------|-----------------|
| Suggestion / autocomplete     | ai-generated    |
| Summarise                     | ai-generated    |
| Translate                     | ai-generated    |
| NER (named-entity recognition)| ai-generated    |
| HTR transcription             | ai-generated    |
| Reviewer-revised AI output    | ai-assisted     |

## Sidecar vs. embedded

By default Heratio writes a sidecar JSON next to the artefact. This works for any file type (text, PDF, image, video). For JPEGs you can optionally install the open-source `c2patool` binary (Content Authenticity Initiative) and Heratio will embed the manifest directly in the file - so it travels with the image when shared.

## Specification

- Full C2PA spec: https://c2pa.org/specifications/specifications/2.1/index.html
- Heratio reference doc: `docs/reference/c2pa-content-provenance.md`
