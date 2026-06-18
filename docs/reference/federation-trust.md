# Federation Trust Handshake + Signed Peer Responses (T1, heratio#1316)

Heratio's federation backbone (epic heratio#1313) lets instances query each
other live: graph neighbourhoods (#1204), endangered-heritage registers (#1205)
and cross-peer search. T1 adds the **trust layer**: a peer can prove a federated
response really came from the instance that claims to have served it, and a
consuming instance pins each peer's key Trust-On-First-Use (TOFU) so a silent key
swap is caught.

It reuses the platform's **one** Ed25519 key. There is no federation keypair:
the key that signs federation responses is the same key that signs inference
receipts (EU AI Act Article 12) and C2PA manifests, served at
`/.well-known/ai-inference-pubkey`. One key, three uses.

## The one key

- Bound as the `AhgInferenceReceipts\Signer` singleton (authoritatively by
  `ahg-ai-compliance`, shared by `ahg-c2pa`). Secret key at
  `storage/keys/inference-signing.sk`; public half registered in
  `ai_inference_key` and served at `/.well-known/ai-inference-pubkey`.
- `kid` = first 16 hex chars of `SHA-256(publicKey)` - the stable short id that
  travels with every signed receipt, C2PA manifest and now federation response.
- `AhgFederation\Services\FederationSigner` resolves that singleton lazily and
  fails soft: on a slimmer install without the signer, responses go out
  **unsigned** rather than erroring.

## Signature scheme: `ed25519-sha256-hex`

Identical in spirit to the C2PA claim signer:

1. `digest = SHA-256(exact response body bytes)` - a fixed-length 32-byte input.
2. `sig = Ed25519_detached(digest, secretKey)` via ext-sodium.
3. Hex-encode `sig` for the HTTP header.

The signature is **detached** and travels as a **response header**, never inside
the JSON body, so a consumer that ignores the header sees a byte-for-byte
unchanged body (full back-compat). The bytes signed are the exact bytes
transmitted.

## Header contract (provider side)

Peer-facing federation responses carry:

| Header | Value |
|---|---|
| `X-Federation-Signature` | hex detached Ed25519 over `sha256(body)` |
| `X-Federation-Key-Id` | the signing `kid` (resolve via `public_key_url`) |
| `X-Federation-Sig-Alg` | `ed25519-sha256-hex` |

All three are added to `Access-Control-Expose-Headers` so a browser-side CORS
consumer can read them (the federation surfaces are CORS-open).

Signed surfaces (all in `ahg-api`, all unlocked):

- `GET /api/v1/graph/{idOrSlug}` (+ `.jsonld`/`.ttl`/`.rdf`, and `/federated`) -
  `GraphController`, signed via its `withCors()` -> `signFederation()` so every
  return path (JSON-LD / Turtle / RDF-XML) is covered.
- `GET /api/v1/endangered` - `EndangeredApiController`, signed at the `index()`
  return over the final serialised JSON.
- `GET /open-data/federation` (+ `.json`) - `FederationIndexController`, signed
  via its `withCors()`.

## Descriptor contract (`/open-data/protocol` federation block)

`ProtocolController::trust()` adds a `federation.trust` block so a consumer knows
how to verify without out-of-band knowledge:

```json
"federation": {
  "protocol_version": "1.0",
  "surfaces": { "graph": "...", "endangered": "...", "search": "..." },
  "peer_index": ".../open-data/federation",
  "trust": {
    "signed": true,
    "signature_scheme": "ed25519-sha256-hex",
    "public_key_url": "<host>/.well-known/ai-inference-pubkey",
    "key_fingerprint": "<kid>",
    "signature_header": "X-Federation-Signature",
    "key_id_header": "X-Federation-Key-Id"
  }
}
```

The `trust` block is **omitted** (not faked) when this instance has no resolvable
signer, so an instance never advertises verification it can't back.

## Verify + pin (consumer side, TOFU)

`AhgFederation\Services\FederationVerifier::verifyResponse($bytes, $headers,
$peerBaseUrl)` returns `{ verified, key_fingerprint, reason }`:

1. **No signature header** -> `verified=false, reason=unsigned` (NOT an error).
2. **TOFU pin check first.** If `federation_peer.pinned_key_fingerprint` is set
   for this peer and the presented `kid` differs -> `verified=false,
   reason=key_mismatch`. A changed key is **never auto-trusted**.
3. **Resolve the peer's public key.** Fetch the peer's `/open-data/protocol`
   (through the SSRF-guarded `FederationClient`), read
   `federation.public_key_url`, fetch that key document (again SSRF-guarded), and
   pick the key whose `kid` matches. Falls back to the conventional
   `/.well-known/ai-inference-pubkey` path when the descriptor field is absent.
   The key document is cached per peer (30 min).
4. **Verify** the detached Ed25519 signature over `sha256(received bytes)` using
   `AhgInferenceReceipts\Signer::verifyHex` (one verification primitive; falls
   back to ext-sodium directly if `ahg-inference-receipts` is absent).
5. **Pin TOFU.** On the first good verify, store the `kid` in
   `federation_peer.pinned_key_fingerprint` + `key_pinned_at` (idempotent guarded
   write - only fills an empty pin, so a concurrent verify can't stomp it).

Failure reasons: `unsigned`, `no_peer_key`, `bad_signature`, `key_mismatch`,
`error`, `verified`, `verified_pinned`. Verification is **best-effort on the
consume path**: it never throws, never blocks the response, never 500s. T1 only
*establishes* sign+verify+pin; what to *do* with an unverified peer is deferred
to T2 (the federated endpoints keep returning data regardless).

### Why the pin lives on `federation_peer`

The live consume path (graph / endangered) reads its active peers from
`federation_member`, but the F2 governance + discovery columns - and now the TOFU
pin columns - live on `federation_peer`. The two tables are joined on the
**base_url host** (the stable cross-table key). A `federation_member`-only peer
still verifies; its pin simply isn't persisted until it also exists in the
governance registry.

## Schema (idempotent guarded ALTER)

`packages/ahg-federation/database/install_trust.sql` adds two columns to
`federation_peer`, matching F2's `INFORMATION_SCHEMA`-guarded pattern (safe to
re-run on every boot; applied by `AhgFederationServiceProvider::boot()` when
`pinned_key_fingerprint` is missing):

- `pinned_key_fingerprint VARCHAR(64) NULL` (+ index) - the TOFU-pinned peer kid.
- `key_pinned_at DATETIME NULL` - the moment it was pinned.

## Where `verified` surfaces in provenance

`FederationGraphService` (#1204) and `FederatedEndangeredService` (#1205) already
tag every remote node/row with `source_peer = {id, name, base_url}`. T1 stamps
`verified` (bool) + `key_fingerprint` onto that same `source_peer` block, and onto
each entry in the `federation.peers` stats list (plus a `trust_reason`). So a
consumer or UI can tell exactly which peer's data is cryptographically verified.

`FederatedSearchService` is **locked** and was not edited; it should adopt
`FederationVerifier` the next time it is unlocked (same one-line `verifyPeer`
delegation the other two services use).

## Admin / governance surface

`/federation/governance` (`FederationGovernanceController` + `governance.blade`,
both unlocked) shows each peer's **pinned fingerprint** + **pinned-since**
timestamp, and a **Re-pin / clear** control
(`POST /federation/governance/{id}/clear-pin`). Clearing sets the pin back to
NULL so the next successful verify re-pins TOFU - the deliberate "the peer rotated
its key, trust the new one" action after a `key_mismatch`.

## Self-verify smoke

```
sign body with the local key -> verify over sha256(body) -> PASS
tamper one byte               -> verify -> rejected
unsigned response             -> verified=false, reason=unsigned (not error)
peer pubkey doc (keys[])       -> extractKey by kid -> matches local pubkey
/api/v1/endangered             -> carries X-Federation-* headers, self-verifies
/open-data/protocol            -> federation.trust block present + correct
```
