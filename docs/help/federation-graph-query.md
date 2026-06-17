# Federation Graph Query (live cross-peer graph aggregation)

**Summary:** Given one local record, Heratio fetches that record's
graph-neighbourhood live from every active federation peer and merges them into
a single aggregated "global graph" view, with provenance showing which peer
each node came from. This is the first increment of the Federation Query
Protocol (north-star #1204, the "world heritage graph").

## Endpoint

```
GET /api/v1/graph/{idOrSlug}/federated
```

- `{idOrSlug}` - a numeric local information-object id or its slug.
- Returns JSON-LD-ish JSON: a shared `@context`, an `@graph` array of nodes,
  and a `federation` block.
- Open data, no API key, permissive CORS - the same stance as the rest of the
  Open Memory Protocol graph endpoints.

### Response shape

```jsonc
{
  "@context": { "schema": "https://schema.org/", "...": "..." },
  "@graph": [
    { "@id": "https://this-host/api/v1/graph/123", "name": "...", "source_peer": null },
    { "@id": "https://peer-a/api/v1/graph/9",   "name": "...",
      "source_peer": { "id": 4, "name": "Peer A", "base_url": "https://peer-a" } }
  ],
  "federation": {
    "mode": "live",
    "reference": "123",
    "local_node_count": 7,
    "total_node_count": 19,
    "peers_queried": 3,
    "peers": [
      { "id": 4, "name": "Peer A", "base_url": "https://peer-a",
        "status": "success", "node_count": 8, "cached": false, "duration_ms": 412.3 }
    ],
    "warnings": []
  }
}
```

- `source_peer: null` means the node is LOCAL. A non-null `source_peer` records
  the federation member it came from.
- If the same entity URI is contributed by more than one source, the first
  contributor wins the node body and the others are listed under
  `also_present_in`.

## The live-query model

This is **live cross-peer querying, not harvest-and-cache.** Peers are queried
on demand at request time. For each active peer, Heratio fetches
`{peer.base_url}/api/v1/graph/{ref}.jsonld` and parses the returned
neighbourhood. There is **no** local copy of the peer graph kept between
requests beyond a short protective cache (below).

Active peers come from the `federation_member` registry: members that are
enabled (`is_enabled = 1`), are not the local self-member (`is_self = 0`), and
carry a usable `base_url`.

## Rights and scope

Each peer's `/api/v1/graph` endpoint already returns only its **public,
published** data (its own publication-status gate). Heratio trusts the peer's
filtering and never attempts to fetch non-public peer data.

## Safety

- **SSRF guard.** Every peer URL is checked before any HTTP call. The guard
  rejects the cloud-metadata endpoints (`169.254.169.254`,
  `metadata.google.internal`, `metadata.internal`), loopback, link-local, and
  private/reserved IP ranges, and only allows `http`/`https`. Redirects are
  **not** followed, so a peer cannot bounce the fetch to an internal host. The
  guard is a replica of the one in the federated **search** service.
- **Per-peer timeout.** Each peer fetch has a short timeout (5s). A peer that
  errors, times out, or is blocked is **skipped** and noted in `warnings` - it
  is never fatal.
- **Fail soft.** A dead peer, zero peers, or an internal error returns just the
  local graph plus warnings - never a 500.
- **Rate limit.** A short per-peer cool-down (a couple of seconds) plus a short
  per-(peer, reference) cache (5 minutes) stop a graph walk from hammering a
  peer. A peer inside its cool-down with no cached graph is skipped with a
  rate-limit warning.

## Scope of this increment / follow-ups

This first increment is deliberately limited to **live graph aggregation for
one entity**. The following are explicit follow-ups, not built here:

- Peer-discovery crawling and protocol negotiation.
- A dedicated `federation_peer_config` table (per-peer graph settings).
- Multi-hop / transitive graph expansion across peers.
