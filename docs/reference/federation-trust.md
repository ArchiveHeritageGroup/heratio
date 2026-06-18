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

# Federation Enforcement: Verifiable by Construction (T2, heratio#1317)

T1 (above) *established* trust - it stamped every cross-peer node/row with
`source_peer.verified` and a `key_fingerprint`. F2 (#1315) *stored* per-peer
governance on `federation_peer` (`federation_enabled`, `trust_level`,
`rate_limit_seconds`, `allowed_entity_types`). **T2 enforces both at query
time**, so the federation layer is verifiable by construction: what F2 stored
and T1 verified is now what the live services actually do. This is the FINAL
phase of epic #1313, which is now complete.

All of T2 is **additive and fail-soft**: the default policy reproduces today's
behaviour exactly, every lookup is guarded, local data is never at risk, and
zero peers degrades to local-only. No new crypto, no new keypair, no new table -
it reuses the F2 columns, the T1 verified flag, the existing settings mechanism
(`AhgCore\Services\AhgSettingsService`) and `AhgC2pa\Services\TrustDossierService`'s
published surface.

## The enforcement helper

`AhgFederation\Services\FederationGovernance` is the single read-side helper both
live services use (no duplicated governance query):

- `peerAllowedFor($baseUrl, $surface, $defaultEnabledWhenUnconfigured)` - the
  surface gate.
- `requireVerified()` / `shouldDropUnverified($sourcePeer)` - the trust-threshold
  policy.
- `trustDossierUrl($baseUrl, $ref)` / `authenticityUrl($baseUrl, $ref)` - the
  authenticity-chain links.

### The `federation_member` <-> `federation_peer` link

The live services (`FederationGraphService`, `FederatedEndangeredService`)
iterate **`federation_member`** (the lightweight registry: id / name / base_url /
is_enabled / is_self), while F2 governance lives on **`federation_peer`**. The
two tables share `base_url`, which is the natural and only stable join key, so
governance is looked up by normalised `base_url` (trim, lower-case, drop trailing
slash). A member with **no** matching `federation_peer` row is treated per the
caller's back-compat default (the services pass `true`: a member that already
passed `is_enabled=1` in its own table must not be retroactively disabled just
because governance was never configured).

## 1. Surface gate (`allowed_entity_types` + `federation_enabled`)

Before any peer is contacted, each candidate is checked with
`peerAllowedFor($peer->base_url, $surface, true)` where `$surface` is `'graph'`
(in `FederationGraphService`) or `'endangered'` (in `FederatedEndangeredService`).
A peer is queried only when:

- `federation_enabled = 1`, **AND**
- `allowed_entity_types` is null/empty (= all surfaces, see below) **OR** contains
  the surface.

A peer that is gated out is **skipped and noted in `warnings`** (never silently
dropped), e.g. `Peer 7 (Foo): skipped for graph - surface 'graph' not in this
peer's allowed_entity_types`. The peer is never contacted.

### Null / empty `allowed_entity_types` => ALL surfaces (the decision)

F2 added `allowed_entity_types` as a **nullable** column, so every pre-F2 row and
every freshly discovered peer starts `NULL`. Treating `NULL` as "no surfaces
allowed" would silently break every existing federation the moment T2 shipped - a
hard regression with no operator action. The governance UI already states *"No
surfaces ticked = all advertised surfaces allowed"*, so `NULL`/empty = **all** is
also the least-surprising reading of the stored state. The per-peer
`federation_enabled` flag (itself default `0` / opt-in) is the real gate;
`allowed_entity_types` is a *narrowing refinement* on a peer that is already
enabled. Therefore: **`NULL`/empty = all surfaces; a non-empty list = exactly
that subset.**

## 2. Trust-threshold policy (`federation_require_verified`)

A single per-instance setting in `ahg_settings`
(`setting_group = 'federation'`, key `federation_require_verified`), read/written
via `AhgSettingsService` - **not** a hardcoded constant. **Default OFF** for
back-compat.

- **OFF (default):** unverified peer data is **included** but flagged. T1 already
  sets `source_peer.verified=false`; T2 counts them and adds an aggregate notice
  to the response `federation.trust` block (graph) / `trust` block (endangered):
  *"N node(s)/result(s) from unverified peers are included and flagged ..."*.
- **ON:** peer nodes/rows whose `source_peer.verified` is not exactly `true` are
  **dropped** from the merged result before it is built, and the dropped count is
  recorded in `warnings` + the `trust` block (*"N ... were excluded ..."*).
  **Local data (`source_peer === null`) is always included** regardless of the
  policy.

The policy is exposed as a `trust` sub-block on both service responses:
`{require_verified, unverified_(node|row)_count, dropped_unverified_count,
notice}`.

## 3. Authenticity-chain link on borrowed records

For each remote node/row, T2 adds `source_peer.trust_dossier_url` =
`{peer.base_url}/trust-dossier/{ref}` and `source_peer.authenticity_url` =
`{peer.base_url}/authenticity/{ref}`, so a consumer can follow a borrowed
record's lineage to the **peer's own** trust dossier / authenticity report. The
ref is encoded **per path segment** (slashes preserved) so a multi-segment slug
(`fonds/series/item`) resolves to one record on the peer. For the endangered
board the link is built per-row against each row's own `item_ref`.

The `trust_dossier` + `authenticity` surfaces are now declared in
`/open-data/protocol` under `federation.surfaces` (alongside `graph`,
`endangered`, `search`), so the URL pattern is **discoverable** rather than
guessed.

## 4. Admin + consumer surfaces

- **Governance view** (`/federation/governance`, `FederationGovernanceController`
  + `governance.blade`, both unlocked): a **Trust-threshold policy** card with the
  `federation_require_verified` toggle + save (`POST
  /federation/governance/policy` -> `savePolicy()`), and a per-peer *"is this peer
  trusted + for what"* summary panel - federated on/off, allowed surfaces (or "all
  surfaces"), and key-pinned state, all at a glance, mirroring exactly what the
  live services enforce.
- **Consumer board** (`endangered.global` blade): when the policy is OFF, every
  peer card that failed verification carries an **"unverified"** badge plus a
  **"trust dossier"** link to the holding institution's authenticity chain; an
  aggregate trust notice sits above the board. (When the policy is ON, unverified
  rows are dropped upstream and never reach the view.)

## Still unadopted (locked)

`FederatedSearchService` is **locked** (F3 SharePoint) and was not edited - the
`'search'` surface gate is therefore not yet enforced there. It should adopt
`FederationGovernance` (the same `peerAllowedFor(..., 'search', ...)` +
`shouldDropUnverified` calls) the next time it is unlocked, exactly as it should
adopt `FederationVerifier` per T1.

## T2 enforcement smoke

```
peer fed_enabled=0                         -> skipped for graph (warning, not contacted)
peer allowed=[endangered], surface=graph   -> skipped for graph (warning)
peer allowed=NULL, fed_enabled=1           -> allowed (all surfaces)
no federation_peer row, default=true       -> allowed (back-compat)
no federation_peer row, default=false      -> denied
require_verified ON + peer verified=false  -> node/row dropped, counted
require_verified ON + local (source=null)  -> kept (local always included)
require_verified OFF + peer verified=false -> kept + flagged + counted
trustDossierUrl(base, "fonds/item")        -> base/trust-dossier/fonds/item (slashes preserved)
both services, zero peers                  -> local-only, trust block present, no throw
setting round-trip via AhgSettingsService  -> group=federation, default OFF
```
