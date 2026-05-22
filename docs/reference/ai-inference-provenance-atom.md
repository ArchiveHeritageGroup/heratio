# AI inference provenance recording on the AtoM-AHG side

The AtoM-AHG codebase (the `atom-ahg-plugins` repo) records every AI inference
it performs into the `ahg_ai_inference` table, Ed25519-signs the canonical
manifest of each one, and writes an RDF-Star provenance annotation to Fuseki.
Reviewer corrections are recorded into `ahg_ai_override`. This is the AtoM
mirror of the Heratio `ahg-provenance-ai` package, delivered under heratio
issue #140 (Phases 1-4). Before this, the AtoM AI plugin ran AI features but
never wrote a provenance row.

## What records what

`ahgProvenancePlugin` owns the new code (it owns provenance on the AtoM side):

- `lib/Service/InferenceRecord.php` - the inference DTO (the write contract).
- `lib/Service/InferenceSigner.php` - Ed25519 keygen / sign / verify, with a
  deterministic key-sorted canonical manifest.
- `lib/Service/InferenceService.php` - `record()` writes one `ahg_ai_inference`
  row via the Capsule query builder, composes the per-inference model manifest
  (heratio#135), signs the canonical manifest (heratio#136) and writes the
  RDF-Star annotation to Fuseki (Phase 3).
- `lib/Service/OverrideService.php` - records reviewer corrections into
  `ahg_ai_override` plus a reified PROV-O activity in Fuseki (Phase 4). The
  original inference is never overwritten.

`ahgAIPlugin` calls `InferenceService::record()` best-effort from three AI
actions in `modules/ai/actions/actions.class.php`:

- `executeExtract` - NER, `service_name = NER`, `target_field = access_points`.
- `executeSummarize` - `service_name = SUMMARIZE`, `target_field = scope_and_content`.
- `executeHtr` - HTR, `service_name = HTR`, `target_field = transcript`.

The call is wrapped in try/catch and guarded by `class_exists()`: a provenance
failure, or `ahgProvenancePlugin` being absent, never breaks the user-facing AI
flow.

## Signing keypair

`record()` signs only when an operator keypair exists - it is opt-in. Generate
it once per install:

    php symfony ai-provenance:keygen

The private key is written to the AtoM install's `data/ahg-ai-signing/`
directory - outside every plugin git repo, never in the database, never in git
(the keygen also drops a `.gitignore` of `*` as a belt-and-braces guard). Only
the detached base64 signature and a short `signer_key_id` are persisted on the
`ahg_ai_inference` row. Until keygen is run, inferences are recorded unsigned.

Run keygen as the web user so the web request can read the key, e.g.
`sudo -u www-data php symfony ai-provenance:keygen`.

## Verifying signatures

    php symfony ai-provenance:verify            # newest 100 signed rows
    php symfony ai-provenance:verify --id=42    # one row
    php symfony ai-provenance:verify --limit=500

The verify task re-derives each row's canonical manifest and checks its
detached signature against the operator public key. It exits non-zero if any
row fails. Rows signed by a retired key are skipped with a note.

## RDF-Star provenance and the replay task

`record()` writes the inference's RDF-Star annotation to Fuseki via the
`FusekiUpdateService` from `ahgAuthorityResolutionPlugin` (the AtoM side has no
central Fuseki client; that namespace has no autoloader, so it is loaded by
absolute path). The inference becomes a `prov:Activity`, and an RDF-Star
meta-assertion anchors the generated triple back to it. `OverrideService` writes
a reified PROV-O activity for each override.

Writes are SQL-first: if the synchronous Fuseki write fails, the row keeps a
NULL `fuseki_graph_uri` / `fuseki_override_uri` and the replay task retries it:

    php symfony ai-provenance:replay              # retry up to 200 of each
    php symfony ai-provenance:replay --batch=500
    php symfony ai-provenance:replay --dry-run

`ai-provenance:replay` is idempotent (graph URIs derive from the row uuid) -
schedule it via cron every 5 minutes. Inline writes honour the
`ahg_settings.fuseki_sync_enabled` flag; the replay task is the safety net
regardless.

## The signed manifest

The canonical manifest is a fixed field set: `id`, `uuid`, `occurred_at`,
`service_name`, `model_name`, `model_version`, `input_hash`, `output_hash`,
`confidence`, `model_manifest`, and a `target` of `type:id:field`. It is
key-sorted recursively and compacted to JSON before signing, so the signer and
verifier agree on the exact bytes. The crypto and canonicalisation are
byte-identical to the Heratio implementation.

`record()` signs the manifest built from a row object shaped exactly like the
one the verify path selects back, so the bytes that are signed and the bytes
that are verified come from a single builder (`manifestFromRow`). `confidence`
is rounded to the `decimal(6,5)` column precision before both the insert and
the signature, so a recorded signature verifies straight off the persisted row.

## Scope and what is still open

Delivered (heratio#140 Phases 1-4): SQL recording, model manifest, Ed25519
signing, RDF-Star + PROV-O writes to Fuseki, `OverrideService`, the keygen /
verify / replay tasks, and the three wired AI actions. The `/ai/governance`
dashboard reads `signer_key_id` and shows the real signed status.

Still open: wiring override *detection* into the AtoM record edit forms
(`OverrideService::detectOverridesFromForm()` exists as a usable API but no
edit action calls it yet), the per-record provenance trace endpoint, and
recording from the CLI batch tasks (`ai:htr`, `ai:ner`).

## Standalone test

The signing crypto, the manifest round-trip and the RDF-Star SPARQL builder
have a dependency-free test (28 assertions):

    php ahgProvenancePlugin/testing/InferenceSignerTest.php

It needs no AtoM or Symfony bootstrap and exits non-zero on any failure.
