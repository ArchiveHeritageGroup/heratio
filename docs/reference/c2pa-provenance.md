# C2PA Provenance & Content Credentials (issue #1201)

**Summary:** Heratio's `ahg-c2pa` package now has a digitisation-provenance + content-credentials layer. It records who digitised a heritage asset, when, on what device and software, plus any AI-inference steps, and binds each record to an Ed25519-signed C2PA manifest that can be verified inside Heratio. Manifest-level signing works on any host (needs only `ext-sodium`); embedding the manifest into the media file (JUMBF) additionally needs the native `c2patool` binary, which is not installed on this server - the layer degrades honestly to signed sidecars + a durable DB record when `c2patool` is absent.

## What this layer is (and is not)

- **Provenance RECORD + verify/display side.** This is the first slice of #1201: a tamper-evident trust record for digitised assets, checkable in Heratio.
- It reuses the existing `ahg-c2pa` signing core (`ManifestBuilder`, `C2paSigner`, `Assertion`, `Claim`, JCS canonicalisation, the shared Ed25519 key from `ahg-inference-receipts`).
- **Signing is real.** The Ed25519 claim signature over the JCS-canonical claim is genuine. There is no stubbed/fake signing.
- **`c2patool` is only for media embedding.** When it is absent, the manifest is still signed and stored; only the "embed C2PA bytes inside the JPEG/JUMBF" step is unavailable.

## Components

- **Table `ahg_c2pa_provenance`** (`database/install_provenance.sql`): one row per digitisation event. Columns: `information_object_id`, `digital_object_id` (nullable), `captured_by`, `captured_at`, `capture_device`, `capture_software`, `notes`, `asset_sha256`, `inference_steps` (JSON), `manifest_id` (FK-ish to `ahg_c2pa_manifest`), `sign_status` (`signed` / `unsigned`). Auto-installed by the service provider boot (single try/catch, `Schema::hasTable` guard).
- **`ProvenanceRecordService`** (`src/Services/`): `record()`, `verifyRecord()`, `listForObject()`, `find()`, and `capability()` (the honest signing-capability report). `record()` builds a single `c2pa.actions.v2` assertion with a `c2pa.created` capture action followed by one `c2pa.edited` action per AI-inference step, signs it, writes a sidecar when a real file path is available, and persists to `ahg_c2pa_manifest`.
- **`ProvenanceController`** (`src/Controllers/`) + `routes/web.php`: mounted under `/admin/c2pa` with the `admin` middleware.
- **Views** (`resources/views/provenance/`): `index`, `create`, `show` (verify verdict + capture provenance + AI inference chain + signed-manifest JSON link), extending `theme::layouts.1col`.

## Routes (all under `admin` middleware)

- `GET  /admin/c2pa/object/{io}` - list provenance records (`c2pa.provenance.index`)
- `GET  /admin/c2pa/object/{io}/record` - record-a-digitisation form (`c2pa.provenance.create`)
- `POST /admin/c2pa/object/{io}/record` - persist + sign (`c2pa.provenance.store`)
- `GET  /admin/c2pa/object/{io}/record/{id}` - verify + display (`c2pa.provenance.show`)
- `GET  /admin/c2pa/object/{io}/record/{id}/manifest.json` - raw signed manifest (`c2pa.provenance.manifest`)

`/admin/c2pa/...` is multi-segment, so the locked IO `/{slug}` catch-all (which only matches single-segment paths) never intercepts it.

## Verification semantics

`verifyRecord()` loads the bound manifest from `ahg_c2pa_manifest`, re-hashes every assertion against the hash pinned in the signed claim, and verifies the Ed25519 claim signature under the public key resolved from `ai_inference_key` (shared with the EU AI Act Article 12 chain) or the on-disk `storage/keys/inference-signing.pk` fallback. Any byte-level tamper of a stored assertion or the claim fails verification with a clear "hash mismatch" / "signature did not verify" error. Confirmed by smoke test: a clean record returns `verified`; flipping one byte in the stored manifest returns `failed`.

## Capability / signing status

`ProvenanceRecordService::capability()` returns:

- `sodium` / `can_sign_manifest` - true on this host
- `c2patool` / `can_embed_media` - false on this host (`c2patool` not installed)
- `summary` - shown in the UI: "Signing requires c2patool only for media embedding (not installed). Manifests are still Ed25519-signed and stored as verifiable records + sidecars."

To enable media embedding, install the native `c2patool` binary at `/usr/local/bin/c2patool` (or anywhere on `PATH`); the existing `C2paService::embedInJpeg()` then takes over and the capability report flips to "Full".

## Relationship to other work

- Chains to **AI inference provenance** (#61, ADR-0002) via `inference_steps` and the per-step `c2pa.edited` actions.
- Reuses the **shared Ed25519 key** (`ahg-inference-receipts`) so a verifier resolves either an Article 12 chain entry or a C2PA manifest through the same `ai_inference_key` registry.
