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
- `?hops=N` (optional, default 1) - multi-hop / transitive expansion. `1` is the
  single-hop neighbourhood (unchanged). `N>1` walks the graph outward, breadth-
  first, to `N` rounds: each local neighbour found is itself aggregated across the
  federation, so the result reaches "records connected to my record's neighbours,
  across the whole federation," not just direct hits. Capped server-side (depth,
  total nodes, and per-hop frontier width).
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
- With `?hops>1`, every node also carries a `hop` field - the distance (in
  rounds) at which it was first reached - and the `federation` block reports
  `mode: "live-multihop"`, `max_hops`, and `hop_node_counts` (new nodes added per
  round).

## Multi-hop / transitive expansion (`?hops=N`)

Single-hop (`hops=1`, the default) returns the record's own neighbourhood plus
what each peer holds for the SAME reference. `?hops=N` (N>1) walks **outward**:
it takes the local neighbours just discovered and aggregates each of them in turn
(local + cross-peer), breadth-first, up to N rounds. So a two-hop query answers
"what is connected to the things my record is connected to, across the whole
federation."

Honest scope of this increment: the spine that is expanded is the **local**
graph - each newly-found local node is re-aggregated (which federates across
peers at every step). A **remote peer node** is merged into the result but is not
chased on its own peer, because resolving a peer node's opaque URI back to a
reference that peer understands is a separate cross-peer identity-resolution
problem (a tracked follow-up). So the walk is transitive across our catalogue and
one hop into each peer at every step - broad and safe, without guessing
peer-local identifiers.

The walk is hard-bounded: a maximum hop depth, a maximum total node count, and a
maximum per-hop frontier width (so a hub node cannot explode it). It reuses the
single-hop SSRF guard, per-peer cache and rate-limit, so deeper queries can never
hammer a peer. Like single-hop, it is fail-soft and never errors.

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
