# Live F3 Federation of the Exhibition Twin (heratio#1246, on epic #1313)

This is the F3 track of the digital-twin phase-2 work (issue heratio#1246). It
brings the existing exhibition peer-borrow (#1277) onto the federation +
trust backbone (epic heratio#1313): a borrowed exhibition scene is now fetched
safely, signed by the providing peer, verified, key-pinned, and governed.

Before this, the peer-borrow predated the backbone: it fetched a peer's
`scene.json` with a raw `Http::get()` (no SSRF guard, no signature
verification) and placed whatever came back. F3 closes that gap by **reusing
the existing backbone services** - no new fetch path, no new crypto, no new
keypair.

## The flow end to end

```
PROVIDER (we own the exhibition)                 BORROWER (we want to borrow it)
--------------------------------                 ------------------------------
GET /exhibition-space/{slug}/scene.json          peerScene():
  ExhibitionSpaceController::sceneExport()          governance gate:
  body = sceneManifest()                              federation_enabled +
  FederationSigner::attach($response)                 peerAllowedFor(base,'exhibition')
    -> X-Federation-Signature (detached)            RemoteSceneFetchService::fetchScene()
    -> X-Federation-Key-Id (kid)                      FederationClient::fetchOne()  (SSRF guard,
    -> X-Federation-Sig-Alg                             cache, rate-limit, FOLLOWLOCATION=false)
  (body untouched; fail-soft if no signer)          FederationVerifier::verifyResponse()
                                                      -> {verified, key_fingerprint, reason}, TOFU pin
                                                    stamp verified + key_fingerprint + source_peer
                                                      onto every normalised object
                                                    require_verified policy (T2):
                                                      ON  + unverified -> withhold objects, flag
                                                      OFF + unverified -> return + flag
                                                  placeRemote() -> placeRemotePlacement()
                                                    persist verified/key_fingerprint/source_peer
                                                    on ahg_exhibition_placement.remote_payload
                                                    (re-checks require_verified server-side)
```

## Provider side: signing the scene export

`ExhibitionSpaceController::sceneExport()` builds the `ahg-exhibition-scene 1.0`
manifest, then calls `FederationSigner::attach($response)` - the SAME detached
Ed25519 scheme T1 (#1316) added to the graph / endangered surfaces. The
signature is over the EXACT response bytes; the body is never mutated. It is the
ONE platform key (`/.well-known/ai-inference-pubkey`), shared with inference
receipts and C2PA. Fail-soft: a slimmer install with no signer serves the scene
unsigned (a borrowing peer then treats it as unverified).

The surface is declared in the federation descriptor at
`/open-data/protocol` -> `federation.surfaces.exhibition` =
`{base}/exhibition-space/{slug}/scene.json`, so a peer discovers it rather than
guessing.

## Borrow side: consume via the backbone

`RemoteSceneFetchService::fetchScene()` (ahg-federation) no longer does a raw
`Http::get()`. It now:

1. `FederationClient::fetchOne($url, [...])` - SSRF host-guard (cloud-metadata /
   loopback / link-local / private / reserved-IP rejection), `FOLLOWLOCATION=false`,
   a short (120s) cache and a per-peer (5s) rate-limit. A blocked / timed-out /
   rate-limited peer returns a non-success status - never fatal.
2. `FederationVerifier::verifyResponse($body, $headers, $base)` - verifies the
   detached `X-Federation-Signature` over the exact received bytes and pins the
   peer key TOFU. Unsigned is NOT an error: `verified=false, reason="unsigned"`.
3. Stamps `verified`, `key_fingerprint` and a `source_peer` envelope onto every
   normalised object (mirroring `FederationGraphService`'s `source_peer` tagging),
   so a single borrowed object keeps its trust verdict after it is detached from
   the scene response.

## Governance (F2)

The borrow is gated in `ExhibitionSpaceController::peerScene()`:

- `FederationGovernance::peerAllowedFor($base, 'exhibition', true)` - only a
  federation-enabled peer whose allowed surfaces include `exhibition` (or which
  has no explicit allow-list) may be borrowed from. A gated-out peer returns a
  clear `ok=false` reason, never a 500.
- `exhibition` was added to the federation surface vocab in three places:
  - `PeerDiscoveryService::KNOWN_SURFACES` (drives both the governance UI
    checkbox list and surface probing),
  - `FederationGovernanceController::save()` validation
    (`in:graph,endangered,search,exhibition`),
  - `ProtocolController::federation()` descriptor (the declared scene.json URL
    pattern).

## Require-verified policy (T2)

`federation_require_verified` (default OFF for back-compat):

- **OFF** - today's behaviour: an unverified borrowed scene is returned and
  placed, but every object carries `verified=false` so the builder flags it.
- **ON** - an unsigned / unverified peer scene may not be placed:
  `peerScene()` withholds the objects with a notice, and `placeRemote()`
  re-checks server-side (defence in depth) so a client cannot re-POST a withheld
  payload.

Local data is never affected by the policy.

## Verified provenance surfacing

- The `peerScene` response carries `verified`, `key_fingerprint`, `trust_reason`,
  `source_peer`, `require_verified` and a human `notice`.
- The builder borrow-picker shows a green shield on a verified peer object and an
  amber warning on an unverified one, plus a scene-level trust line.
- A placed borrowed object renders an amber warning badge on the 3D canvas when
  unverified - consistent with the #1205/#1210 'unverified' treatment.
- The verdict is persisted on `ahg_exhibition_placement.remote_payload`
  (`verified` / `key_fingerprint` / `source_peer`) so a reloaded borrowed object
  is badged identically.

## Files touched

- `packages/ahg-exhibition/src/Controllers/ExhibitionSpaceController.php` -
  `sceneExport()` signs; `peerScene()` governs + require-verified; `placeRemote()`
  server-side require-verified re-check.
- `packages/ahg-exhibition/src/Services/ExhibitionSpaceService.php` -
  `placeRemotePlacement()` persists provenance; `getPlacementsForBuilder()` emits
  it.
- `packages/ahg-exhibition/resources/views/exhibition-space/builder.blade.php` -
  trust badges in the picker + on the canvas.
- `packages/ahg-federation/src/Services/RemoteSceneFetchService.php` - fetch via
  `FederationClient`, verify via `FederationVerifier`, stamp provenance.
- `packages/ahg-federation/src/Services/PeerDiscoveryService.php` -
  `KNOWN_SURFACES` += `exhibition`.
- `packages/ahg-federation/src/Controllers/FederationGovernanceController.php` -
  validation += `exhibition`.
- `packages/ahg-api/src/Controllers/ProtocolController.php` - federation
  descriptor += `exhibition` surface URL.

Fail-soft throughout: federation module absent -> `peerScene` returns its
existing graceful "not installed" response; unsigned peer scene -> `verified=false`
(flag, do not 500); `require_verified` OFF = today's behaviour + flag.
