# Federation peer discovery + governance (F2, heratio#1315)

**Summary:** Heratio can now DISCOVER its federation peers and GOVERN them per-peer. A crawl (`ahg:federation-discover`) probes each peer's published `/open-data/protocol` + `/open-data/maturity` over the shared SSRF-guarded `FederationClient` (F1, #1314), validates that the peer advertises the Federation Query Protocol `federation` block, and caches the outcome (reachable / protocol version / declared surfaces / maturity) onto the peer row. Each peer carries governance fields - `federation_enabled`, `trust_level`, `rate_limit_seconds`, `allowed_entity_types`. A public, machine-discoverable index at `/open-data/federation` lists this instance's federation-enabled peers so an external agent can bootstrap peer discovery without hardcoding. Part of epic #1313 "federation backbone hardening". Additive and fail-soft: zero peers gives an empty index, a dead peer is recorded not fatal.

## What already existed (reused, not rebuilt)

- **`FederationClient`** (F1, #1314) - the single SSRF-guarded `fetchMany()` / `fetchOne()` cross-peer fetch layer. The discovery crawl uses it for ALL peer HTTP (never a raw curl).
- **`/open-data/protocol`** - already emits the `federation` block (`protocol_version` + surfaces graph / endangered / search), via `AhgApi\Controllers\ProtocolController`. F2 only ADDS a `peer_index` link to it.
- **`/open-data/maturity`** - already exists (`AhgApi\Controllers\MaturityController`); the crawl reads its headline grade.
- **`federation_peer`** table - the established peer registry (base_url, peer_type/config connectors, is_active harvest gate). F2 ADDS columns here rather than a new table.
- **`NetworkDirectoryController` / `NetworkDirectoryService`** - the PUBLIC union-network directory over the *separate* `federation_member` registry (the union-catalogue concept, opt-in `is_enabled`). Untouched by F2 - that is a different registry from `federation_peer` and serves a different purpose (the network-effects roll, not the query-protocol peer crawl).

## The discovery crawl

`AhgFederation\Services\PeerDiscoveryService::discoverAll(bool $enabledOnly=false)`:

1. Selects `federation_peer` rows with a real `http(s)` base_url (skips the self-peer / OAI placeholders whose base_url is `-`). `$enabledOnly` restricts to `federation_enabled=1`.
2. Builds one `fetchMany` spec set for `{base}/open-data/protocol.json` and a second for `{base}/open-data/maturity.json`, each per-peer cache-keyed + rate-keyed. `FederationClient` does the SSRF guard, the parallel fetch, the cache and the rate-limit.
3. `evaluate()` classifies each peer:
   - `ok` - reachable, valid JSON, advertises a `federation` block with surfaces. Records `protocol_version`, the intersection of advertised surfaces with `{graph, endangered, search}`, the maturity grade, and a `capabilities_json` snapshot.
   - `non_compliant` - reachable but not parseable JSON, or no `federation` block.
   - `unreachable` - no successful protocol fetch (error / skipped / blocked by the guard).
4. `persist()` writes the outcome + `last_probed_at` back onto the peer row. Never throws.

Command: `php artisan ahg:federation-discover [--enabled]`. Scheduled daily at 03:30 by `AhgFederationServiceProvider`, gated on the global `federation_enabled` setting (same `when($enabled)` gate as harvest / vocab-sync). Zero discoverable peers prints an empty summary and exits 0.

## Governance fields (on `federation_peer`)

Added idempotently by `packages/ahg-federation/database/install_governance.sql` (guarded `ALTER` per column via `INFORMATION_SCHEMA`, the same pattern as the existing peer_type / config back-fill). Auto-applied on boot by `AhgFederationServiceProvider` (probe: `Schema::hasColumn('federation_peer','federation_enabled')`).

| Column | Purpose |
|---|---|
| `federation_enabled` TINYINT default 0 | Opt-in: peer participates in Federation Query Protocol discovery + queries. Distinct from `is_active` (the OAI-harvest gate). |
| `trust_level` VARCHAR default `basic` | Governance tier. Values from `ahg_dropdown` `federation_trust_level` (untrusted / basic / trusted / verified). No ENUM. |
| `rate_limit_seconds` INT NULL | Per-peer min seconds between live fetches; feeds `FederationClient->withRateLimit()`. NULL = client default. |
| `allowed_entity_types` JSON NULL | Array of surfaces this peer may be queried for (subset of graph / endangered / search). NULL = all advertised. |
| `discovery_status` VARCHAR NULL | Last probe: ok / unreachable / non_compliant / unknown. Values from `ahg_dropdown` `federation_discovery_status`. |
| `protocol_version` VARCHAR NULL | The peer's advertised `federation.protocol_version`. |
| `declared_surfaces` JSON NULL | Surfaces the peer advertises. |
| `maturity_grade` VARCHAR NULL | Headline grade from the peer's `/open-data/maturity`. |
| `capabilities_json` JSON NULL | Cached federation block + maturity summary from the last probe. |
| `last_probed_at` DATETIME NULL | Timestamp of the last probe. |

Dropdowns seeded by `packages/ahg-federation/database/seed_dropdowns_governance.sql` (`INSERT IGNORE`, taxonomies `federation_trust_level` + `federation_discovery_status`), auto-seeded on the same boot probe.

## Public peer index - `/open-data/federation`

`AhgApi\Controllers\FederationIndexController`. PUBLIC, CORS-open, content-negotiated (browser -> HTML table, everyone else -> JSON). Lists this instance's `federation_enabled` peers with their base_url, declared surfaces, last probe status / protocol version / maturity / trust level. Read-only - it reads the cached probe outcomes, performs no peer HTTP itself. Empty index (`peer_count: 0`, `peers: []`) when there are no enabled peers or before the governance columns exist (never 500s). Two-segment path, so the `/{slug}` catch-all cannot capture it.

Shape:
```json
{
  "protocol": "Federation Query Protocol",
  "protocol_version": "1.0",
  "self": "https://this-instance",
  "protocol_descriptor": "https://this-instance/open-data/protocol",
  "peer_count": 0,
  "peers": [
    {"name": "...", "base_url": "...", "protocol_descriptor": ".../open-data/protocol",
     "surfaces": ["graph","search"], "protocol_version": "1.0",
     "trust_level": "trusted", "discovery_status": "ok", "maturity": "...", "last_probed_at": "..."}
  ]
}
```

The `/open-data/protocol` `federation` block now also carries `peer_index` -> this endpoint, so the discovery chain is fully self-describing.

## Admin surface - `/federation/governance`

`AhgFederation\Controllers\FederationGovernanceController` (+ `resources/views/governance.blade.php`). Admin-only (auth + admin + `EnsureFederationEnabled`). Read view of every peer's discovery status (reachable / version / surfaces / maturity / last probed) plus per-peer controls to set `federation_enabled`, `trust_level`, `rate_limit_seconds`, `allowed_entity_types`. A "Run discovery now" button triggers the crawl on demand. Fresh, UNLOCKED controller + view - deliberately separate from the LOCKED `FederationController` / `edit-peer.blade.php`, which it never touches.

## Files

- `packages/ahg-federation/src/Services/PeerDiscoveryService.php` (new)
- `packages/ahg-federation/src/Console/PeerDiscoverCommand.php` (new) - `ahg:federation-discover`
- `packages/ahg-federation/src/Controllers/FederationGovernanceController.php` (new)
- `packages/ahg-federation/resources/views/governance.blade.php` (new)
- `packages/ahg-federation/database/install_governance.sql` (new)
- `packages/ahg-federation/database/seed_dropdowns_governance.sql` (new)
- `packages/ahg-federation/src/Providers/AhgFederationServiceProvider.php` (changed - register command, boot probe, schedule)
- `packages/ahg-federation/routes/web.php` (changed - governance routes)
- `packages/ahg-api/src/Controllers/FederationIndexController.php` (new)
- `packages/ahg-api/routes/api.php` (changed - `/open-data/federation` routes)
- `packages/ahg-api/src/Controllers/ProtocolController.php` (changed - `peer_index` link in the federation block)

## Deferred to later increments (noted, not built)

- Automatic trust scoring + peer reputation (derive trust_level from probe history).
- Mutual-auth handshake between peers - that is T1, heratio#1316.
- `allowed_entity_types` is stored + editable but not yet ENFORCED at query time in the consuming federated services (graph / endangered / search) - wiring the gate into those services is a follow-up.
