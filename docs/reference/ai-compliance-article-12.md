# AI Compliance - EU AI Act Article 12 record-keeping

Implementation reference for the tamper-evident inference receipt chain in Heratio. Closes issue #693 (Phase 1 + Phase 2 partial).

## What it is

Every AI inference call in Heratio writes one immutable row to `ai_inference_log`. Each row is hash-chained to its predecessor and signed under an Ed25519 key controlled by the operator. Modifying or deleting any row breaks the chain at a detectable point.

Designed for the EU AI Act Article 12 "logs that cannot be modified" requirement (enforcement: 2026-08-02).

## Architecture

```
+-------------------------+         +-----------------------------+
|  ahg-ai-services        |         |  ahg/inference-receipts     |
|  (LlmService,           | calls   |  (standalone PHP library,   |
|   HtrService,           | ----->  |   Apache-2.0, publishable   |
|   NerService, ...)      |         |   to Packagist)             |
+-------------------------+         +-----------------------------+
            |                                     ^
            v                                     | (used by)
+-------------------------+                       |
|  ahg-ai-compliance      |  Eloquent             |
|  InferenceLogger        |  chain store ---------+
|  ReceiptChain wiring    |  (ai_inference_log)
|  /.well-known endpoint  |
|  artisan verifier CLI   |
+-------------------------+
```

Two packages ship together:

- **`packages/ahg-inference-receipts/`** - protocol primitives only (JCS encoder, Ed25519 signer, receipt chain orchestrator). Pure PHP, zero Laravel deps, Apache-2.0. Will be published to Packagist as a standalone library so the PHP ecosystem has a peer to the TypeScript / Python `nobulex` reference. 49 unit tests + 1 byte-compatibility test against nobulex vectors.
- **`packages/ahg-ai-compliance/`** - Heratio glue. `ai_inference_log` table, Eloquent model, `EloquentChainStore`, `InferenceLogger` service, `/.well-known/ai-inference-pubkey` endpoint, `ai-compliance:install-key` + `ai-compliance:verify-inference-log` + `ai-compliance:prune` artisan commands. AGPL-3.0.

## On-wire receipt format

```json
{
  "v": 1,
  "seq": 0,
  "ts": "2026-05-25T13:45:01.234Z",
  "prev_hash": "0000000000000000000000000000000000000000000000000000000000000000",
  "payload": {
    "service": "llm",
    "model_id": "mistral:7b-instruct-v0.2",
    "model_version": "mistral-7b@2024-01",
    "input_fingerprint": "<sha256 of input>",
    "output_fingerprint": "<sha256 of output>",
    "request_id": "req_...",
    "user_id": 42,
    "tenant_id": 1
  },
  "kid": "a1b2c3d4e5f60718",
  "alg": "ed25519",
  "entry_hash": "<sha256 hex of JCS(signing_view)>",
  "signature": "<base64 ed25519 over entry_hash bytes>"
}
```

Signing view (what goes into the hash): everything except `entry_hash` and `signature`.

## Database schema

`ai_inference_log` columns:

- `seq` BIGINT UNSIGNED UNIQUE NOT NULL - 0-based monotonic counter
- `prev_hash` CHAR(64) - SHA-256 hex of previous row's `entry_hash`, or genesis (64 zeros)
- `entry_hash` CHAR(64) UNIQUE - SHA-256 of JCS-canonicalized signing view
- `signature` VARCHAR(128) - base64 Ed25519 detached signature
- `kid` VARCHAR(32) - signing key id
- `service` / `model_id` / `model_version` - which inference service + model fired
- `input_fingerprint` / `output_fingerprint` - SHA-256 of the input/output bodies
- `request_id` / `user_id` / `tenant_id` - tracing context
- `payload_json` JSON - full canonical payload, re-hashed during verification
- `payload_pruned_at` - set when retention prune nulls payload_json (chain stays verifiable)

`ai_inference_key` columns:

- `kid` VARCHAR(32) UNIQUE - public-key id
- `public_key` VARBINARY(64) - raw 32-byte Ed25519 public key
- `active` TINYINT - current signing key (only one row at a time has active=1)
- `rotated_at` - when this key was rotated out

## Operator workflow

```bash
# One-time install (creates 0600 secret key + 0644 public key under storage/keys/)
php artisan ai-compliance:install-key

# Verify the full chain (auditor view)
php artisan ai-compliance:verify-inference-log

# Restrict by service or date range
php artisan ai-compliance:verify-inference-log --service=htr --from=2026-01-01

# Retention prune (run daily; nulls payload_json older than retention window,
# preserves seq/prev_hash/entry_hash/signature so chain remains verifiable)
php artisan ai-compliance:prune

# Rotate the signing key (old kids stay verifiable through ai_inference_key)
php artisan ai-compliance:install-key --rotate
```

## Public-key endpoint

`GET /.well-known/ai-inference-pubkey` returns a JSON document listing every kid this site has ever signed under. External auditors / regulators pin this on first fetch.

Response shape:

```json
{
  "issuer": "https://heratio.example",
  "purpose": "EU AI Act Article 12 record-keeping ...",
  "spec": "https://github.com/ArchiveHeritageGroup/heratio/blob/main/packages/ahg-inference-receipts/README.md",
  "keys": [
    {
      "kid": "...",
      "alg": "ed25519",
      "active": true,
      "public_key": { "hex": "...", "base64": "...", "base64url": "..." },
      "jwk": { "kty": "OKP", "crv": "Ed25519", "kid": "...", "x": "..." },
      "rotated_at": null,
      "created_at": "..."
    }
  ]
}
```

## Phase 1 deliverables (this release)

- [x] Standalone PHP library (`ahg/inference-receipts`)
- [x] Heratio plug-in (`ahg/ai-compliance`)
- [x] `ai_inference_log` + `ai_inference_key` tables
- [x] `ai-compliance:install-key` + `:verify-inference-log` + `:prune` CLIs
- [x] `/.well-known/ai-inference-pubkey` endpoint
- [x] 49 unit tests + conformance suite skeleton (nobulex vectors)

## Phase 2 deliverables (this release)

- [x] LlmService::complete() instrumented (both cloud-override and local-provider paths)
- [x] HtrService::extract() instrumented
- [x] NerService::extract() instrumented (API path; LLM fallback inherits from LlmService receipt)

## Phase 2 follow-ups

- [ ] DonutService::extract() instrumentation
- [ ] GuardrailService::summarize() instrumentation
- [ ] LlmService::completeFull() (a second entry point separate from complete())
- [ ] LlmService::translate() direct-MzansiLM path (not via complete())

## Threat model

Detects:
- Modification of any field of any stored row.
- Re-ordering, splicing, or deletion of rows.
- Replay of a row signed under an unknown / rotated-out key.

Does not detect:
- Operator with simultaneous write access to `ai_inference_log` AND control of the signing key can append fraudulent rows that verify cleanly. Mitigation: replicate the chain head to an off-host append-only log on a different trust boundary; RFC 3161 trusted timestamping is a Phase 3 consideration.
- Replay of a stale receipt across tenants. Mitigation: `tenant_id` is part of the payload, so a cross-tenant replay verifies but is detectable by content.

## Conformance with nobulex

Receipts are byte-compatible with the [nobulex](https://github.com/arian-gogani/nobulex) protocol where the receipt shape overlaps. The library treats nobulex test vectors as conformance fixtures - run `./tools/fetch-nobulex-vectors.sh` from `packages/ahg-inference-receipts/` to populate them.

Heratio does NOT adopt nobulex's Trust Capital tier model (Restricted / Standard / Trusted / Sovereign). That layer is closer to EU AI Act Article 14 (human oversight) than Article 12 - tracked separately under issue #726.

## Related EU AI Act work

- #693 Article 12 (logging) - this issue
- #724 Article 9 (risk management system)
- #725 Article 11 (technical documentation / Annex IV)
- #726 Article 14 (human oversight)
