> Heratio Help Center article. Category: Compliance.

# Audit Trail Tamper Evidence

## User Guide

From Heratio v1.95.0 every new row written to `ahg_audit_log` is linked into a
cryptographic hash chain that lets you prove, after the fact, that no record
of who-did-what has been altered or removed.

---

## Why this matters

The audit trail is only useful if you can trust that nobody has rewritten
it. Without tamper evidence, an administrator with database access could
quietly delete an embarrassing row and you would have no way to tell.

Heratio now signs every audit row with the same Ed25519 signing key used for
EU AI Act Article 12 inference receipts, and links each row to the one before
it. If anything in the chain is changed or removed, the verifier can detect
exactly where and stop.

---

## How it works (at a glance)

```
+---------+     +---------+     +---------+
| row 0   | <-- | row 1   | <-- | row 2   |   ... and so on
| sha256  |     | sha256  |     | sha256  |
| ed25519 |     | ed25519 |     | ed25519 |
+---------+     +---------+     +---------+
```

Each row stores five extra columns: `seq`, `prev_hash`, `entry_hash`,
`signature`, `kid`. The `prev_hash` is literally the previous row's
`entry_hash`, so the chain is unbreakable without re-signing every row from
the break point onward. The `signature` proves that the row was written by
someone holding the platform's private signing key. The `kid` (key id) lets
the verifier look up which public key to check against.

---

## Verifying the chain

Run from the application directory:

```bash
php artisan auditlog:verify-chain
```

You will see something like:

```
ahg_audit_log: 12,544 chained row(s), 87,210 legacy row(s, seq IS NULL, skipped).
PASS - 12544 receipts verified in 4283.1 ms
```

Legacy rows are entries that existed before the upgrade; they are not
chained. New rows from v1.95.0 onward are.

To verify a slice:

```bash
php artisan auditlog:verify-chain --from=5000 --to=5500
php artisan auditlog:verify-chain --limit=10        # spot-check the first 10
```

On failure the verifier prints the broken seq and a hint:

```
FAIL at seq 7421: entry_hash mismatch (row tampered or canonicalisation differs)

Investigation pointers:
  - inspect: SELECT id, seq, kid, created_at FROM ahg_audit_log WHERE seq = 7421
  - tampering further down the chain is masked until this is resolved
  - if the kid is unknown, confirm ai_inference_key has a row for it
```

---

## What is protected

- **Edits.** Changing any column on a chained row breaks the row's
  `entry_hash`; the verifier reports FAIL at that seq.
- **Deletions.** Removing a chained row leaves a `seq` gap and a broken
  `prev_hash` on the row after.
- **Forged inserts.** A row inserted by hand without a valid Ed25519
  signature under a known kid fails verification.
- **Direct mysql UPDATE / DELETE.** A database trigger refuses such
  statements on chained rows and raises:
  ```
  SQLSTATE[45000]: ahg_audit_log chained rows are append-only (issue #676)
  ```

---

## What is NOT (yet) protected

- An attacker who can both rewrite the database AND rotate the signing key
  AND re-sign every downstream row. Future phases anchor the chain head to
  an off-host witness so this becomes detectable.
- Multi-tenant isolation. Today there is one global chain.

---

## Where the signing key lives

The signing keypair is shared with the AI inference receipts (EU AI Act
Article 12). You manage it with:

```bash
php artisan ai-compliance:install-key            # provision a fresh key
php artisan ai-compliance:install-key --rotate   # rotate; old key kept for verifying old rows
```

The public key is served at `/.well-known/ai-inference-pubkey` for external
verifiers.

---

## Frequently asked questions

**My install upgraded mid-day. Are my pre-upgrade audit rows protected?**

No. Existing rows have `seq IS NULL` and are not chained. They remain in the
table as legacy entries. From the moment of the upgrade onward, every new
row is chained.

**Does the chain slow down audit writes?**

Each chained insert takes one extra row-lock + one hash + one signature.
Measurable in microbenchmarks, invisible in normal request paths.

**What happens if the signing key file is missing?**

The audit writer falls back to writing rows with NULL chain columns and logs
one warning. Audit logging itself never fails - the existing "never break
the call path" contract is preserved. Run
`php artisan ai-compliance:install-key` to fix.

**Can I prune old chained rows?**

No - the trigger refuses DELETE on chained rows by design (that is the
tamper evidence). Pre-chain legacy rows continue to honour the existing
retention policy.

---

## See also

- Audit Trail (`/help/audit-trail-user-guide`)
- AI Compliance - Article 12 (`/help/ai-compliance-article-12`)
- Integration API events audit (`/help/integration-api-events-audit`)
