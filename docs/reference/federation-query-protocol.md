# Federation Query Protocol + the shared FederationClient (F1, heratio#1314)

**Summary:** Heratio's federated services (live cross-peer graph aggregation, the cross-institution endangered-heritage register, and cross-peer search) now share ONE hardened HTTP fetch layer - `AhgFederation\Services\FederationClient` - instead of each replicating the same curl_multi loop and SSRF guard. The platform also publishes a versioned **Federation Query Protocol** descriptor inside the existing `/open-data/protocol` document, so a peer can discover - from one resource - which surfaces this instance exposes for federation. Part of epic #1313 "federation backbone hardening". This was a functionality-preserving refactor: the two federated endpoints behave identically before and after.

## The shared client: FederationClient

`packages/ahg-federation/src/Services/FederationClient.php` is the single cross-peer fetch layer. It owns:

- **The SSRF host-guard** (`hostAllowed(string $baseUrl): bool`) - the canonical guard for every federated fetch. Rejects any non-`http(s)` scheme; the cloud-metadata hosts (`169.254.169.254`, `metadata.google.internal`, `metadata.internal`); loopback / unspecified names (`localhost`, `ip6-localhost`, `0.0.0.0`, `::1`); and any literal IP in a private / loopback / link-local / reserved range. A peer whose `base_url` fails the guard is never fetched.
- **`fetchMany(array $specs): array`** - curl_multi parallel fetch, cache-first and rate-limited per peer, SSRF-guarded per peer, fail-soft per peer. Each spec carries `url`, `base_url` (for the guard), `cache_key`, `rate_key`, and optional `headers`. Returns, keyed by the same id, `{status, body, error, cached, duration_ms, http_code}`.
- **`fetchOne(string $url, array $spec = []): array`** - single-URL convenience wrapper over the same path.
- Fluent tunables: `withTimeouts()`, `withCacheTtl()`, `withRateLimit()`, `withMaxPeers()`, `withHeaders()`, plus `maxPeers()`.

Hardened defaults preserved from the original services: 5000 ms total / 2000 ms connect timeout, `CURLOPT_FOLLOWLOCATION = false` (a 30x must not bounce a fetch onto an internal host past the guard), `SSL_VERIFYPEER` + `SSL_VERIFYHOST = 2`, a 300 s per-(peer, ref) cache, a 2 s per-peer rate-limit window, and a 25-peer cap.

### Consumers that now delegate to it

- `AhgFederation\Services\FederationGraphService` - keeps its graph parsing / merge / `source_peer` provenance; delegates all HTTP to `FederationClient` (User-Agent `Heratio-Federation-Graph/1.0`, cache namespace `fedgraph:`). Powers `GET /api/v1/graph/{idOrSlug}/federated`.
- `AhgSemanticSearch\Services\FederatedEndangeredService` - keeps its register parsing / ranking / provenance; delegates all HTTP to `FederationClient` (User-Agent `Heratio-Federation-Endangered/1.0`, cache namespace `fedendangered:`). Powers `GET /at-risk/global`.

### Still to migrate (locked)

- `AhgFederation\Services\FederatedSearchService` is in `.locked-paths` and was **not** touched in F1. It carries the same curl_multi + SSRF guard pattern that was lifted into `FederationClient`. When it is next unlocked, it should drop its inline guard/fetch and adopt `FederationClient` (it fetches peers at `{base_url}/api/search`).

## The Federation Query Protocol descriptor

`packages/ahg-api/src/Controllers/ProtocolController.php` (the `/open-data/protocol` capabilities document) now emits a `federation` block declaring the surfaces THIS instance exposes for peers plus a protocol version:

```json
"federation": {
  "protocol_version": "1.0",
  "description": "The queryable surfaces this instance exposes to federation peers ...",
  "surfaces": {
    "graph": "https://<host>/api/v1/graph/{idOrSlug}",
    "endangered": "https://<host>/api/v1/endangered",
    "search": "https://<host>/api/search"
  }
}
```

`graph` and `endangered` resolve from their named routes (`api.v1.graph.show`, `api.v1.endangered`); `search` is the cross-peer search contract a federating peer queries (`FederatedSearchService` fetches `{peer.base_url}/api/search`). Surfaces are resolved defensively, so a slimmer install advertises only what it serves. Reach it at `GET /open-data/protocol` (browser gets the human page) or `GET /open-data/protocol.json`.

## Verification

- `php -l` clean on all four changed/added PHP files.
- Guard parity: 11/11 host cases (public hosts allowed; ftp / cloud-metadata / localhost / 127.0.0.1 / 10.x / 192.168.x / `[::1]` blocked).
- Endpoint parity (no active peers in the registry): graph `local_node_count=1, peers_queried=0, total_node_count=1`; endangered `local_count=0, total_count=0, peers_queried=0` - identical before and after the refactor.
