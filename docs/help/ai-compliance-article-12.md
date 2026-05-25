> Heratio Help Center article. Category: AI / Compliance.

# EU AI Act Article 12 - Inference Receipt Chain

## Overview

Every AI inference call (HTR, NER, LLM summary / translate / spellcheck) writes one tamper-evident row to a signed, hash-chained log. The log is designed to meet the EU AI Act Article 12 "logs that cannot be modified" requirement that takes effect on 2 August 2026.

Operators can verify the entire chain on demand and surface the public key to outside auditors.

## What gets logged

Per inference call:
- service (`llm`, `htr`, `ner`, `donut`, `guardrail`)
- model id + version
- SHA-256 of the input
- SHA-256 of the output (not the raw text, so PII does not enter the chain)
- request id (from observability middleware)
- user id + tenant id
- latency + token counts where available
- timestamp (UTC, millisecond precision)

Each row's `entry_hash` includes the previous row's hash, so altering any field in any row breaks verification at that point.

## First-time setup

```bash
# Generate the Ed25519 signing keypair (one-time)
php artisan ai-compliance:install-key
```

This writes `storage/keys/inference-signing.sk` (0600) + `storage/keys/inference-signing.pk` (0644). The public key is also registered in the `ai_inference_key` table so the public-key endpoint can serve it.

## Verifying the chain

```bash
# Audit the full chain
php artisan ai-compliance:verify-inference-log
```

Output: `PASS - N receipts verified in M ms` on success, `FAIL at seq N: <reason>` on tampering.

Optional filters:

```bash
# Restrict to one service
php artisan ai-compliance:verify-inference-log --service=htr

# Date range
php artisan ai-compliance:verify-inference-log --from=2026-01-01 --to=2026-03-31
```

## Public-key endpoint

External auditors fetch your signing public key at:

```
GET https://<your-host>/.well-known/ai-inference-pubkey
```

The response is a JSON document listing every kid this site has ever signed under (current key, plus any that have been rotated out). Auditors pin this on first fetch and use it later to verify exported receipts.

## Retention

Default retention is 7 years. After that, the cron job `ai-compliance:prune` nulls the `payload_json` column on aged-out rows so PII does not linger forever. The chain's structural integrity (`seq`, `prev_hash`, `entry_hash`, `signature`) is preserved indefinitely so a regulator can always confirm "no row was inserted or removed during the retention window."

To change the retention window:

```sql
INSERT INTO ahg_setting (`key`, `value`) VALUES ('ai_compliance.retention_years', '10')
ON DUPLICATE KEY UPDATE value = '10';
```

## Rotating the signing key

```bash
php artisan ai-compliance:install-key --rotate
```

This generates a fresh keypair, marks the old key inactive (but keeps it registered for verifying old receipts), and the next inference call signs under the new kid.

## Threat model

The chain detects:
- Any modification of any stored row.
- Re-ordering, splicing, or deletion of rows.
- Replay under an unknown / rotated-out key.

The chain does **not** detect:
- An operator with simultaneous write access to `ai_inference_log` AND the secret key file. Mitigation: replicate the chain head to an off-host append-only log on a different trust boundary.
- Cross-tenant replay. The `tenant_id` is in the payload so a cross-tenant copy is detectable by content but the signature alone does not catch it.

## Related compliance

- EU AI Act Article 9 (risk management) - tracked separately, issue #724
- EU AI Act Article 11 (Annex IV technical documentation) - issue #725
- EU AI Act Article 14 (human oversight, including autonomy tiering if needed) - issue #726

## See also

- `docs/reference/ai-compliance-article-12.md` - implementation reference
- `packages/ahg-inference-receipts/README.md` - protocol details
- `packages/ahg-ai-compliance/` - Laravel plug-in source
