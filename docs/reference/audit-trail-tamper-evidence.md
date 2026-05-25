# Audit-trail tamper evidence (hash chain on ahg_audit_log)

Heratio v1.95.0 closes the long-standing "tamper evidence (hash chain)"
deliverable named in the title of issue #676. The Phase 4 close (`AuditLogger`
helper) populated `old_values` / `new_values` / `changed_fields` properly but
left the rows themselves mutable. Phase 5 wraps every newly written
`ahg_audit_log` row in the same Ed25519 + JCS + SHA-256 chain that the
ahg/inference-receipts library provides for EU AI Act Article 12 (issue
#693). One protocol, one signing key, two chains.

## TL;DR

- Five new columns on `ahg_audit_log`: `seq`, `prev_hash`, `entry_hash`,
  `signature`, `kid`.
- New rows are written through `AhgAuditTrail\Services\ChainedAuditWriter`,
  which JCS-canonicalises the row's payload, SHA-256 hashes it, and Ed25519
  signs the digest.
- The signing key is the same `storage/keys/inference-signing.{sk,pk}` pair
  used by `ahg-ai-compliance`. The kid that travels with each audit row maps
  to the same `ai_inference_key` row that AI inference receipts use.
- A MySQL trigger refuses `UPDATE` / `DELETE` on chained rows. Legacy rows
  (`seq IS NULL`, pre-upgrade) remain mutable so existing prune / archive
  flows keep working on the backlog.
- `php artisan auditlog:verify-chain` walks the chain end-to-end.
- If the signing key is missing the writer falls back to unsigned-mode and
  logs one warning - audit writes never break the calling code path.

## Chain layout

Every chained row stores both the legacy audit columns (user, action,
entity, old / new / changed json, metadata, ip / ua / session) and five chain
columns:

| Column        | Type                      | Meaning                                                          |
|---------------|---------------------------|------------------------------------------------------------------|
| `seq`         | `BIGINT UNSIGNED` UNIQUE  | monotonic counter; 0 for the genesis row                         |
| `prev_hash`   | `CHAR(64)` (hex)          | `entry_hash` of seq-1 row, or 64 zero chars for genesis          |
| `entry_hash`  | `CHAR(64)` UNIQUE (hex)   | SHA-256 over `JCS(signing_view)`                                 |
| `signature`   | `VARCHAR(128)` (base64)   | Ed25519 detached signature over `hex2bin(entry_hash)`            |
| `kid`         | `VARCHAR(32)` (hex)       | first 16 hex of `SHA-256(publicKey)`, indexed against `ai_inference_key.kid` |

The signing view is exactly the receipt shape from
`AhgInferenceReceipts\Receipt`:

```
{
  "v":         1,
  "seq":       <int>,
  "ts":        "2026-05-25T13:45:01Z",
  "prev_hash": "<64 hex>",
  "payload":   { ... everything that is NOT a chain or row-id column ... },
  "kid":       "<16 hex>",
  "alg":       "ed25519"
}
```

The `ts` is a second-precision UTC ISO-8601 string. The writer pins the row's
`created_at` to the same instant so the verifier can reconstruct the signing
view from on-disk data alone, no separate `signed_ts` column required.

Payload composition: every column on the row except `id`, `seq`, `prev_hash`,
`entry_hash`, `signature`, `kid`, `created_at`. JSON columns
(`old_values`, `new_values`, `changed_fields`, `metadata`) are decoded back to
PHP structures before canonicalisation so that JCS sees structured data, not
opaque strings.

## Key share with EU AI Act Article 12 (#693)

We deliberately reuse the existing `storage/keys/inference-signing.{sk,pk}`
keypair rather than provisioning a separate audit-trail key. Reasons:

1. **One source of truth.** A reviewer asking "who signed this row?" gets a
   single answer rooted in the `ai_inference_key` registry.
2. **One rotation procedure.** `php artisan ai-compliance:install-key --rotate`
   rolls the key for both chains at once. Old rows remain verifiable because
   the old kid stays registered.
3. **One operator runbook.** No "where is the audit-trail public key" question.

`ChainedAuditWriter` reads the key directly via `KeyPair::loadFrom()` instead
of consuming `\AhgAiCompliance\Services\KeyResolver`. That keeps the audit
package's hard dependency surface to just `ahg/inference-receipts` (the
protocol library) - we do NOT depend on `ahg/ai-compliance` at compile time.
The verifier reads `ai_inference_key` directly for kid -> public-key lookup
because that table is the canonical registry; if the table is missing the
verifier returns FAIL with a hint to run the inference-key installer.

## Append-only enforcement (database trigger)

`packages/ahg-audit-trail/database/install-trigger.sql` installs two MySQL
triggers (`BEFORE UPDATE` + `BEFORE DELETE`) that `SIGNAL SQLSTATE '45000'`
when `OLD.seq IS NOT NULL`. Defense in depth: even direct mysql access or a
future contributor who reaches for `DB::table()->update()` on a chained row
will be refused with:

```
SQLSTATE[45000]: ahg_audit_log chained rows are append-only (issue #676)
```

The service provider applies the trigger SQL via `DB::unprepared()` because
PDO::prepare cannot handle the `BEGIN ... END` block. Same workaround already
used in `packages/ahg-ric/database/seed_ric_from_existing.sql`.

Legacy rows (seq IS NULL) remain freely mutable. The existing
`audit:prune` retention sweep continues to delete them as before.

## Verifier CLI

```
php artisan auditlog:verify-chain
php artisan auditlog:verify-chain --from=5000
php artisan auditlog:verify-chain --from=5000 --to=5500
php artisan auditlog:verify-chain --limit=10 --quiet-pass
```

The command prints a summary header, then walks `ahg_audit_log` rows where
`seq IS NOT NULL` in ascending order, checks:

- `seq` is contiguous from the anchor
- `prev_hash` matches the previous row's `entry_hash`
- recomputed `SHA-256(JCS(signing_view))` matches the stored `entry_hash`
- `Ed25519(signature, hex2bin(entry_hash), publicKey(kid))` returns true

On FAIL it prints the broken seq, the reason, and SQL to inspect the row.
Tampering further down the chain is masked until the first break is resolved
- that is the whole point of a chain.

Exit codes: 0 on PASS, 1 on FAIL or missing prerequisites.

## Threat model

Goals (in scope):

- **Detection of after-the-fact edits.** Any change to a chained row's
  payload columns invalidates `entry_hash` and is detected by the verifier.
- **Detection of deletions.** Removing a row breaks `seq` contiguity at the
  hole and `prev_hash` continuity at the row after.
- **Detection of forged inserts.** A row inserted without a valid Ed25519
  signature under a known kid fails verification.
- **Insider DB write protection.** The append-only trigger raises an error
  on direct UPDATE / DELETE even by a privileged DB user, so casual tampering
  via mysql shell is not silent.

Non-goals (out of scope for this phase, listed for honesty):

- **Defending against a root-level attacker who can rewrite history AND
  rotate the signing key AND re-sign every downstream row.** Mitigations
  belong with the next phase (off-host witness, periodic anchoring of the
  chain head into an external store). Today the head is only inside the same
  database.
- **Cross-tenant isolation.** `tenant_id` propagation across all audit rows
  is Phase 6+ (per the #676 close comment). The current chain is global to
  the install.
- **Confidentiality of the payload.** Audit rows are not encrypted - the
  chain proves they have not been changed, not that they are secret.

## Conformance with ahg/inference-receipts

The chain follows the same on-wire protocol as the EU AI Act Article 12
inference log:

- RFC 8785 JCS for canonical bytes
- SHA-256 for the entry hash
- Ed25519 detached signatures over the 32-byte digest
- 64-zero-char genesis `prev_hash`
- `kid` = first 16 hex of SHA-256(publicKey)

Any verifier built against the inference receipts protocol can verify an
audit row given the row + the public key for its kid; the difference is only
the payload schema.

## Phase 5+ follow-ups (not in this issue)

- **Tenant scoping**: propagate `tenant_id` to every audit write site, add
  the column to `ahg_audit_log`, include it in the signing view.
- **External anchoring**: periodically write `(seq, entry_hash, signature)`
  to an off-host witness (KM, S3 object-lock bucket, public timestamp
  authority) so an attacker who compromises the database alone cannot rewrite
  history.
- **Per-tenant chains**: today one global chain. Tenant scoping naturally
  motivates one chain per tenant; needs a small `seq` namespacing patch.
- **API / CLI / job audit hooks**: the chain is wired through `AuditLogger`,
  which today is consumed by the controllers that opt in. Phase 6 covers the
  call-site sweep so every privileged action is chained.
- **Compliance report generators** and **anomaly detection** are tracked
  separately under the audit-trail umbrella (#676 close comment).

## Files

- `packages/ahg-audit-trail/database/install-chain.sql` - schema migration.
- `packages/ahg-audit-trail/database/install-trigger.sql` - append-only triggers.
- `packages/ahg-audit-trail/src/Services/ChainedAuditWriter.php` - chain writer.
- `packages/ahg-audit-trail/src/Services/AuditLogger.php` - existing helper,
  now routed through the writer.
- `packages/ahg-audit-trail/src/Console/Commands/VerifyChainCommand.php` -
  CLI verifier (`auditlog:verify-chain`).
- `packages/ahg-audit-trail/src/Providers/AhgAuditTrailServiceProvider.php` -
  schema + trigger probe, writer + command registration.

## Related

- ahg/inference-receipts (Composer library, the protocol primitives)
- ahg-ai-compliance (EU AI Act Article 12 chain on `ai_inference_log`)
- `php artisan ai-compliance:install-key` (provisions the shared keypair)
- `php artisan ai-compliance:verify-inference-log` (sibling verifier on
  `ai_inference_log`)
- `/.well-known/ai-inference-pubkey` (public key endpoint)
- Issue #676 (audit trail umbrella), #693 (Article 12 close), #677 (observability)
