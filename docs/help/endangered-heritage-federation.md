# Endangered-heritage federation (the shared race against loss)

Heratio's endangered-heritage register (north-star #1205, "the race against
loss") lets curators flag catalogue items as at-risk and drives a capture-
priority worklist. This federation slice connects that single-instance register
to peer instances so a cross-institution at-risk board can be assembled LIVE -
the same federation pattern used for the world-heritage graph (#1204) and
generative scholarship (#1210).

## What it does

The federation is symmetric: every instance both EXPOSES its own register and
AGGREGATES its peers'.

- **Expose** - a public, read-only JSON endpoint serves THIS instance's
  published at-risk register so peers can query it.
- **Aggregate** - a federated service fetches every active peer's register live,
  merges it with the local register, deduplicates, and ranks the result into one
  leaderboard.
- **Surface** - a cross-institution board renders the merged leaderboard with a
  source-institution badge on every entry.

The single-instance public register at `/at-risk` is unchanged; the federated
board is additive.

## The expose endpoint

```
GET /api/v1/endangered
```

Returns THIS instance's PUBLISHED at-risk register as JSON. It serves the same
published-only, urgency-ordered register the public `/at-risk` page renders, so
it can never leak an unpublished or draft record (publication-status gate:
`status.type_id = 158`, `status_id = 160`; the synthetic root `id = 1` is
excluded).

- **No API key.** This is open at-risk data society should be able to see.
- **CORS-open** (`Access-Control-Allow-Origin: *`), like the other `/api/v1`
  open-data surfaces.
- **Fail-soft.** If the register table is absent, the endpoint returns a valid
  empty list (`available: false`), never a 500.
- **Light throttle** (120 requests/minute) keeps the open door cheap.

### Filters

| Query param | Effect |
|---|---|
| `risk`     | Narrow to one risk category (`conflict`, `climate`, `decay`, `funding`, `displacement`, `digitisation_gap`, `other`). |
| `urgency`  | Narrow to one urgency band (`critical`, `high`, `medium`, `low`). |
| `status`   | Narrow to one capture status (`flagged`, `in_progress`). |
| `limit`    | Cap rows returned (default 200, max 1000). |

### Response shape

```json
{
  "feature": "endangered-heritage-register",
  "north_star": "heratio#1205",
  "institution": "Example Archive",
  "base_url": "https://example.org",
  "available": true,
  "count": 12,
  "register_url": "https://example.org/at-risk",
  "items": [
    {
      "item_ref": "a-fragile-glass-plate-negative",
      "object_id": 4821,
      "title": "Glass-plate negative, harbour, 1911",
      "risk_category": "decay",
      "risk_label": "Material decay",
      "urgency": "critical",
      "urgency_label": "Critical",
      "capture_status": "flagged",
      "reason": "Emulsion lifting; no surrogate exists.",
      "priority_score": 1000,
      "flagged_at": "2026-06-10T08:14:00+00:00",
      "catalogue_url": "https://example.org/a-fragile-glass-plate-negative"
    }
  ]
}
```

## The aggregated board

```
GET /at-risk/global
```

The public cross-institution board. It merges this instance's published register
with a live fetch of every active federation peer's `/api/v1/endangered`,
ranks everything most-urgent first, and tags each entry with its holding
institution. Local items link to the local record; partner items link out to the
partner's catalogue.

Filters (`?risk=`, `?urgency=`, `?status=`) narrow the whole board and are
forwarded to peers so each partner narrows its own payload too.

### How aggregation works

- **Peers** come from the `federation_member` registry (the same registry the
  graph federation uses): rows that are enabled, not the self member, and carry a
  usable `base_url`. Capped at 25 peers per aggregation.
- **Live, parallel fetch** via `curl_multi`, mirroring the graph federation. A
  short per-peer cache (5 minutes) and a per-peer rate limit (one live fetch
  every 2 seconds) protect peers from being hammered.
- **Ranking** reuses `EndangeredHeritageService::priorityScore()` so local and
  remote items are scored identically; ties break on urgency weight then title.
- **Dedupe** is per `(peer, item_ref)`.

### Security: the SSRF guard

Every peer fetch passes a hardened SSRF host-guard (a replica of the guard in
`FederationGraphService`, itself a replica of the locked `FederatedSearchService`
guard):

- scheme allowlist (`http`/`https` only),
- rejection of cloud-metadata hosts, loopback, link-local, private and reserved
  IP ranges,
- `CURLOPT_FOLLOWLOCATION = false` so a 30x redirect cannot bounce a fetch onto
  an internal host past the guard,
- per-peer connect + total timeouts.

A peer that fails the guard, errors, times out, or returns unparseable JSON is
**skipped** and noted in `warnings`; it is never fatal. Federation absent, zero
peers, or every peer down all degrade cleanly to the local-only register plus a
plain "some partners could not be reached" notice - the board never 500s.

If the SSRF guard is ever changed in `FederationGraphService` or
`FederatedSearchService`, mirror the change in `FederatedEndangeredService`.

## Deferred follow-ups

This first increment delivers live aggregation plus the unified board.
Explicitly deferred:

- climate / conflict-zone risk overlays on the board,
- a push-model peer inbound (peers notifying us of new flags),
- a dedicated federation cache table (today the cache is the short-lived
  application cache only).
