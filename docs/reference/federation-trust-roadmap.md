# Heratio roadmap spine: federation + trust (the memory-layer backbone)

**Summary:** Heratio's North Star roadmap (#1213 "from catalogue to the memory layer of humanity") is organised around one spine: **federation + trust.** The differentiator vs other GLAM platforms is not a better catalogue but an **open memory protocol** - institutions querying each other's heritage graph live - made believable by **verifiable trust** (signed, provenance-bearing peer data). Three North Star pillars already sit on this spine (#1204 world heritage graph, #1205 endangered network, #1210 generative scholarship); #1209 truth anchor supplies the trust primitives. Decision taken 2026-06-18 (Johan): federation + trust is the spine; the next phase is hardening the backbone, not adding pillars.

## Why this is the spine

Building the North Star first increments revealed that three pillars all converged on the SAME primitive - live cross-peer querying - and that a single Heratio instance is already most of the way to being a full platform. The genuine frontier is turning one instance into a **node in a federated, verifiable memory network**. The other pillars are then either experiential (reconstruction, multilingual access) or ethical/mission (repatriation, language revival, universal access) layers on top.

## What exists today (the backbone, ad-hoc and unsigned)

- Three live federated surfaces, each duplicating the curl + SSRF guard: `FederationGraphService` (#1204), `FederatedSearchService` (#1210), `FederatedEndangeredService` (#1205).
- A manually-populated peer registry (`federation_member`), an open-data descriptor at `/open-data/protocol`, OAI-PMH harvest peers (`federation_peer`).
- Trust primitives present but not yet applied to federation: `C2paSigner`, the signed AI-inference chain + `/.well-known/ai-inference-pubkey`, `TrustDossierService`, the public `/verify` surface.

So the pieces exist but are ad-hoc and unsigned. Hardening = unify + discover + sign + verify.

## Backbone-hardening phases (sequence: F1 -> F2 -> T1 -> T2)

- **F1 - Unify the protocol.** One `FederationClient` (single SSRF-guarded, cached, rate-limited fetch layer; the three services currently each replicate the guard) + a versioned Federation Query Protocol descriptor at `/open-data/protocol` declaring the graph/search/endangered surfaces + version. Mostly unlocked; the keystone refactor everything else plugs into.
- **F2 - Peer discovery + governance.** A discovery crawl probing each peer's `/open-data/protocol` + maturity, validating compliance, caching capabilities; a `federation_peer_config` (trust level, rate limit, allowed entity types, federation-enabled flag); a machine-discoverable `/open-data/federation` index so peers bootstrap without hardcoding.
- **T1 - Trust handshake + signed responses.** Peer public-key exchange/pinning (extend the existing `/.well-known/ai-inference-pubkey` pattern to a federation key); peers sign their federation responses (reuse `C2paSigner` / inference-chain signing) so a consumer can verify origin + integrity of borrowed data.
- **T2 - Verifiable by construction.** Every federated node/edge carries or links its authenticity chain (`TrustDossier`); consumers flag peers/records that cannot prove lineage as "unverified"; rights/publication-status governance for borrowed entities is enforced.

## Mission framing

APEX (#1212) "that humanity never forgets" is the mission; the ethical pillars (repatriation #1207, language revival #1208, universal access #1211) are the "why". The federated+verifiable memory layer is the "how". Positioning: Heratio is the platform for the federated, verifiable memory of humanity.

Tracked as a "Federation backbone hardening" epic + F1-T2 child issues. This note is the living-roadmap reference.
