# ahg/inference-receipts

Tamper-evident receipt chains for AI inference calls. Pure PHP, zero framework dependencies.

Implements the three primitives that EU AI Act Article 12 ("logs that cannot be modified") needs in practice:

1. **SHA-256 hash chain** - each receipt embeds the hash of its predecessor, so modifying or deleting an entry breaks the chain at a detectable point.
2. **RFC 8785 JSON Canonicalization Scheme (JCS)** - deterministic byte serialization of the receipt payload, so two implementations always agree on what to hash and sign.
3. **Ed25519 detached signatures** - each entry is signed under a publisher key; verifiers can confirm authenticity offline given the public key.

Built for EU AI Act / NIST AI RMF / ISO 42001 record-keeping requirements, but the primitives are generic. Anywhere you need an append-only signed audit trail for AI agent actions, this fits.

## Why

The AI Act enforces from 2 August 2026. Article 12 requires high-risk AI systems to keep automatic event logs across their lifecycle, in a form that ensures traceability and cannot be silently modified. Plain database rows do not meet that bar; tamper-evident logs do.

This library is byte-compatible with the [nobulex](https://github.com/arian-gogani/nobulex) TypeScript / Python reference where the on-wire format overlaps, so receipts can be cross-verified by any implementation that consumes the same vectors.

## Installation

```bash
composer require ahg/inference-receipts
```

Requirements: PHP 8.2+, `ext-sodium`, `ext-json`, `ext-mbstring`.

## Quick start

```php
use AhgInferenceReceipts\KeyPair;
use AhgInferenceReceipts\Signer;
use AhgInferenceReceipts\ReceiptChain;
use AhgInferenceReceipts\Storage\ArrayChainStore;

$keyPair = KeyPair::generate();
$keyPair->saveTo('/etc/ai-keys/signing.sk', '/etc/ai-keys/signing.pk');

$store = new ArrayChainStore();
$chain = ReceiptChain::withSingleKey($store, new Signer($keyPair));

$receipt = $chain->append([
    'service'            => 'llm',
    'model_id'           => 'mistral:7b-instruct-v0.2',
    'model_version'      => 'mistral-7b@2024-01',
    'input_fingerprint'  => hash('sha256', $userPrompt),
    'output_fingerprint' => hash('sha256', $modelResponse),
    'request_id'         => $requestId,
    'user_id'            => $userId,
    'tenant_id'          => $tenantId,
]);

// later, audit time
$result = $chain->verify();
echo $result;  // "PASS (1 receipts verified)" or "FAIL at seq N: <reason>"
```

## On-wire format

```json
{
  "v": 1,
  "seq": 0,
  "ts": "2026-05-25T12:00:00.000Z",
  "prev_hash": "0000000000000000000000000000000000000000000000000000000000000000",
  "payload": { ... arbitrary caller-supplied JSON-able data ... },
  "kid": "a1b2c3d4e5f60718",
  "alg": "ed25519",
  "entry_hash": "<sha256 hex of JCS(toSigningView())>",
  "signature":  "<base64 ed25519 over the entry_hash bytes>"
}
```

- `v`: format version (currently `1`)
- `seq`: 0-based monotonic counter within the chain
- `ts`: RFC 3339 with millisecond precision in UTC
- `prev_hash`: `entry_hash` of the previous receipt, or 64 zero bytes for genesis
- `payload`: caller payload (input/output fingerprints, model id, etc)
- `kid`: stable 16-hex-char id derived from `SHA-256(publicKey)`
- `alg`: signature algorithm name (`ed25519`)
- `entry_hash`: SHA-256 of `JcsEncoder::encode(toSigningView())` where the signing view is `{v, seq, ts, prev_hash, payload, kid, alg}`
- `signature`: base64-encoded Ed25519 signature over the raw 32-byte `entry_hash` digest

## Storage adapters

The library ships `ArrayChainStore` for tests and ephemeral use. Production callers implement `ChainStore`:

```php
interface ChainStore {
    public function append(Receipt $receipt): void;
    public function head(): ?Receipt;
    public function count(): int;
    public function range(int $fromSeq = 0, ?int $toSeq = null): iterable;
}
```

Most production deployments back this with a relational table that has a `UNIQUE` constraint on `entry_hash` (so concurrent writers cannot both win the same `seq`). See the [`ahg/ai-compliance`](https://github.com/ArchiveHeritageGroup/heratio) Laravel package for an Eloquent-backed reference implementation.

## Key rotation

`ReceiptChain` accepts a `publicKeyResolver` callable so verifiers can support multiple historical keys:

```php
$resolver = function (string $kid): ?string {
    return match ($kid) {
        '2025-key-id' => file_get_contents('/etc/ai-keys/2025-signing.pk'),
        '2026-key-id' => file_get_contents('/etc/ai-keys/2026-signing.pk'),
        default       => null,
    };
};

$chain = new ReceiptChain($store, new Signer($currentKeyPair), $resolver);
```

A receipt signed under a rotated-out key still verifies as long as its `kid` resolves to the public-key bytes that were valid at signing time.

## Verification

```php
$result = $chain->verify();
if (!$result->ok) {
    fprintf(STDERR, "Chain broken at seq %d: %s\n", $result->brokenAtSeq, $result->reason);
    exit(1);
}
echo "Verified {$result->checkedCount} receipts\n";
```

`verify()` walks the chain in order and confirms, for each entry:

1. `seq` increments monotonically from `fromSeq`
2. `prev_hash` matches the previous receipt's `entry_hash` (or the genesis hash for `seq == 0`)
3. `entry_hash` equals `SHA-256(JcsEncoder::encode(receipt.toSigningView()))`
4. `signature` validates under the public key resolved from `kid`

If any check fails, verification stops and returns the first failure point.

## Conformance

The conformance suite under `tests/Conformance/` consumes the [nobulex](https://github.com/arian-gogani/nobulex) test vectors as fixtures, so this implementation stays byte-compatible with the broader ecosystem.

```bash
./tools/fetch-nobulex-vectors.sh
vendor/bin/phpunit --testsuite conformance
```

If the fixtures are absent the relevant tests are skipped, so the default suite stays green for downstream consumers who do not pull external vectors.

## Threat model

Detected:

- Modification of any field of any stored receipt (payload, ts, seq, kid, alg, prev_hash).
- Re-signing a tampered receipt under a different key (if the verifier's `publicKeyResolver` rejects unknown kids).
- Re-ordering, splicing, or deleting receipts (the chain's `prev_hash` linkage breaks).

Not detected (out of scope for this library):

- Operator with both write access to the chain AND control of the signing key can append fraudulent entries that verify cleanly. Mitigation: anchor chain head to an off-host append-only log on a different trust boundary; consider RFC 3161 trusted timestamping for high-value chains.
- Replay of a stale receipt to a different chain or tenant. Mitigation: include `tenant_id` and `request_id` in the payload and check them at verification time.

## License

Apache-2.0. See `LICENSE` and `NOTICE`.

## Provenance

Built for [Heratio](https://github.com/ArchiveHeritageGroup/heratio) (a Laravel 12 GLAM platform by The Archive and Heritage Group) to satisfy EU AI Act Article 12 record-keeping. Released standalone because the PHP ecosystem needs a peer to the TypeScript / Python `nobulex` implementation; receipts produced here are byte-compatible with the nobulex format where the shapes overlap.

Tracking the IETF draft `draft-gogani-nobulex-proof-of-behavior` for on-wire format alignment as the spec progresses.
