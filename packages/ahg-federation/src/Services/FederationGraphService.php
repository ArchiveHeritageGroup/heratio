<?php

/**
 * FederationGraphService - live cross-peer GRAPH aggregation.
 *
 * First increment of the Federation Query Protocol (north-star #1204, the
 * "world heritage graph"). Given a LOCAL record, this service:
 *
 *   1. Builds the record's LOCAL graph neighbourhood (the same shape the
 *      public Open Memory Protocol endpoint serves - node + cross-collection
 *      neighbours), reusing ahg-ric's RelationshipService.
 *   2. For each ACTIVE federation peer (federation_member, is_enabled=1,
 *      is_self=0, with a usable base_url), fetches that peer's graph
 *      neighbourhood for the same reference LIVE over HTTP - in parallel via
 *      curl_multi, mirroring FederatedSearchService.
 *   3. Tags every remote node/edge with its source_peer (member id + name +
 *      base_url) and merges everything into one node/edge set, deduped by
 *      entity URI.
 *
 * Product decision: this is LIVE cross-peer querying, not harvest-and-cache.
 * Peers are queried on demand; a short per-(peer, ref) cache only protects the
 * peers from being hammered during a graph walk.
 *
 * Security: cross-peer HTTP is an SSRF risk. As of the F1 unification
 * (heratio#1314) the SSRF host-guard + curl_multi parallel fetch + cache +
 * per-peer rate-limit + peer cap live in the shared
 * AhgFederation\Services\FederationClient; this service delegates all HTTP to
 * it and keeps only its own graph parsing / merge / provenance logic. The
 * guard semantics are unchanged (cloud-metadata / loopback / link-local /
 * private / reserved-IP rejection, FOLLOWLOCATION=false). A peer that fails
 * the guard, errors, or times out is SKIPPED and noted in `warnings`; it is
 * never fatal (fail soft - a dead peer or zero peers returns just the local
 * graph + warnings, never an exception).
 *
 * Rights/scope: a peer's /api/v1/graph endpoint already returns only its
 * PUBLIC, published data (its own publication-status gate). We trust the
 * peer's filtering and never attempt to fetch non-public peer data.
 *
 * Scope of this increment (deliberately small): live graph aggregation for ONE
 * local entity. Peer-discovery crawling, protocol negotiation, and a dedicated
 * federation_peer_config table are explicit FOLLOW-UPS, not built here.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgFederation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FederationGraphService
{
    /** Per-peer HTTP timeout (ms). Mirrors FederatedSearchService default. */
    protected int $peerTimeoutMs = 5000;

    /** Per-peer connect timeout (ms). */
    protected int $peerConnectTimeoutMs = 2000;

    /** Per-(peer, ref) cache TTL (seconds). Short - this is live querying. */
    protected int $cacheTtlSeconds = 300;

    /**
     * Per-peer rate limit: minimum seconds between two LIVE fetches of the same
     * peer (any ref). A request inside the window is served from cache only; if
     * there is no cached value it is skipped with a rate-limit warning. Cheap
     * protection so a graph walk cannot hammer a peer.
     */
    protected int $peerMinIntervalSeconds = 2;

    /** Hard cap on peers queried in one aggregation, so the door stays cheap. */
    protected int $maxPeers = 25;

    /**
     * Aggregate the local + cross-peer graph neighbourhood for one local
     * record.
     *
     * @return array{
     *   '@context': array<string,mixed>,
     *   '@graph': array<int,array<string,mixed>>,
     *   federation: array<string,mixed>
     * }
     */
    public function aggregate(string $idOrSlug): array
    {
        $warnings = [];

        // 1. Local graph (reuse the canonical builder; never duplicate it).
        $local = $this->localGraph($idOrSlug);

        // The reference we hand to peers is the SAME idOrSlug the caller used.
        // Peers resolve it against their own slug/id space; a miss there is a
        // clean per-peer 404, surfaced as a warning, not an error.
        $ref = $idOrSlug;

        // Index local nodes by @id so peer nodes can be merged/deduped.
        $nodesByUri = [];
        foreach (($local['@graph'] ?? []) as $node) {
            $uri = $node['@id'] ?? null;
            if ($uri === null) {
                continue;
            }
            $node['source_peer'] = null; // local origin
            $nodesByUri[$uri] = $node;
        }

        // 2. Active peers with a usable base_url, then apply the F2 surface gate
        // (#1317): only peers that are federation_enabled AND allowed for the
        // 'graph' surface are queried. A peer that is gated out is recorded in
        // warnings (never silently dropped) and never contacted.
        $governance = new FederationGovernance();
        $allPeers = $this->graphPeers();
        $peers = [];
        foreach ($allPeers as $peerId => $peer) {
            $verdict = $governance->peerAllowedFor((string) $peer->base_url, 'graph', true);
            if ($verdict['allowed']) {
                $peers[$peerId] = $peer;
            } else {
                $warnings[] = sprintf(
                    'Peer %d (%s): skipped for graph - %s',
                    $peer->id,
                    $peer->name,
                    $verdict['reason']
                );
            }
        }

        // Aggregate trust accounting for the require-verified policy + notice.
        $requireVerified = $governance->requireVerified();
        $unverifiedNodeCount = 0; // nodes included but unverified (policy OFF)
        $droppedUnverifiedCount = 0; // nodes dropped (policy ON)

        $peerStats = [];

        if (! empty($peers)) {
            $peerResponses = $this->fetchPeersParallel($peers, $ref, $warnings);

            foreach ($peerResponses as $peerId => $resp) {
                $peer = $peers[$peerId];
                $sourcePeer = [
                    'id'       => (int) $peer->id,
                    'name'     => (string) $peer->name,
                    'base_url' => (string) $peer->base_url,
                ];

                $stat = [
                    'id'          => (int) $peer->id,
                    'name'        => (string) $peer->name,
                    'base_url'    => (string) $peer->base_url,
                    'status'      => $resp['status'],
                    'node_count'  => 0,
                    'cached'      => $resp['cached'] ?? false,
                    'duration_ms' => $resp['duration_ms'] ?? 0,
                ];

                if ($resp['status'] === 'success') {
                    // Federation trust handshake (T1 #1316): verify the peer's
                    // detached signature over the EXACT received bytes and pin
                    // its key TOFU. The verdict (verified + key_fingerprint) is
                    // stamped onto source_peer so every peer node carries its own
                    // cryptographic-trust provenance. Best-effort: an unsigned /
                    // unverifiable peer stays verified=false and still merges
                    // (T1 establishes trust; T2 decides what to do with it).
                    $verdict = $this->verifyPeer($resp['body'] ?? '', $resp['headers'] ?? [], (string) $peer->base_url);
                    $sourcePeer['verified'] = $verdict['verified'];
                    $sourcePeer['key_fingerprint'] = $verdict['key_fingerprint'];
                    $stat['verified'] = $verdict['verified'];
                    $stat['key_fingerprint'] = $verdict['key_fingerprint'];
                    $stat['trust_reason'] = $verdict['reason'];

                    // Require-verified policy (#1317): when ON, an unverified peer
                    // contributes NOTHING to the merged graph (its nodes are
                    // dropped); the drop is counted + surfaced in warnings. Local
                    // data is unaffected (it is never a peer node).
                    if ($governance->shouldDropUnverified($sourcePeer)) {
                        $stat['dropped_unverified'] = true;
                        $peerNodes = $this->parsePeerGraph($resp['body'] ?? '', $sourcePeer, $warnings);
                        $dropped = count($peerNodes);
                        $droppedUnverifiedCount += $dropped;
                        $stat['node_count'] = 0;
                        $warnings[] = sprintf(
                            'Peer %d (%s): %d node(s) dropped - unverified and federation_require_verified is ON',
                            $peer->id,
                            $peer->name,
                            $dropped
                        );
                        $peerStats[] = $stat;
                        continue;
                    }

                    // Authenticity-chain link (#1317): point each borrowed node back
                    // to the PEER's own trust dossier / authenticity report for the
                    // same reference, so a consumer can follow the lineage.
                    $sourcePeer['trust_dossier_url'] = $governance->trustDossierUrl((string) $peer->base_url, $ref);
                    $sourcePeer['authenticity_url'] = $governance->authenticityUrl((string) $peer->base_url, $ref);

                    if ($sourcePeer['verified'] !== true) {
                        $unverifiedNodeCount++;
                    }

                    $peerNodes = $this->parsePeerGraph($resp['body'] ?? '', $sourcePeer, $warnings);
                    foreach ($peerNodes as $uri => $node) {
                        if (isset($nodesByUri[$uri])) {
                            // Already known (local or another peer wins) - record
                            // that this peer also has the node, do not overwrite.
                            $nodesByUri[$uri] = $this->noteAlsoPresent($nodesByUri[$uri], $sourcePeer);
                            continue;
                        }
                        $nodesByUri[$uri] = $node;
                    }
                    $stat['node_count'] = count($peerNodes);
                } elseif (! empty($resp['error'])) {
                    $warnings[] = sprintf(
                        'Peer %d (%s): %s',
                        $peer->id,
                        $peer->name,
                        $resp['error']
                    );
                }

                $peerStats[] = $stat;
            }
        }

        $graph = array_values($nodesByUri);

        return [
            '@context'   => $local['@context'] ?? ['schema' => 'https://schema.org/'],
            '@graph'     => $graph,
            'federation' => [
                'mode'            => 'live',
                'reference'       => $ref,
                'local_node_count' => count($local['@graph'] ?? []),
                'total_node_count' => count($graph),
                'peers_queried'   => count($peerStats),
                'peers'           => $peerStats,
                // Trust-threshold policy (#1317). require_verified reflects the
                // per-instance federation_require_verified setting. When OFF,
                // unverified peer nodes are INCLUDED but flagged
                // (source_peer.verified=false) and counted here. When ON, they
                // are DROPPED and the dropped count is reported.
                'trust' => [
                    'require_verified'         => $requireVerified,
                    'unverified_node_count'    => $unverifiedNodeCount,
                    'dropped_unverified_count' => $droppedUnverifiedCount,
                    'notice'                   => $this->trustNotice($requireVerified, $unverifiedNodeCount, $droppedUnverifiedCount),
                ],
                'warnings'        => $warnings,
            ],
        ];
    }

    // -----------------------------------------------------------------
    // Local graph (reuse the canonical Open Memory Protocol builder)
    // -----------------------------------------------------------------

    /**
     * Build the LOCAL graph neighbourhood for the record by reusing the public
     * GraphController logic (which itself reuses ahg-ric). We do not duplicate
     * the neighbour query - we ask the controller for its neutral graph array.
     *
     * Resilient: if the controller/serializer is unavailable for any reason we
     * fall back to a minimal RiC-relations query so federation still returns a
     * usable local graph rather than throwing.
     *
     * @return array{'@context': array<string,mixed>, '@graph': array<int,array<string,mixed>>}
     */
    protected function localGraph(string $idOrSlug): array
    {
        $controllerClass = \AhgApi\Controllers\GraphController::class;
        if (class_exists($controllerClass)) {
            try {
                /** @var \AhgApi\Controllers\GraphController $controller */
                $controller = app($controllerClass);
                $request = \Illuminate\Http\Request::create('/api/v1/graph/' . $idOrSlug, 'GET');
                $response = $controller->show($request, $idOrSlug);
                $decoded = json_decode((string) $response->getContent(), true);
                if (is_array($decoded) && isset($decoded['@graph'])) {
                    return [
                        '@context' => $decoded['@context'] ?? ['schema' => 'https://schema.org/'],
                        '@graph'   => $decoded['@graph'],
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[federation-graph] local graph via GraphController failed', [
                    'ref'   => $idOrSlug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Minimal fallback: just the node itself, no neighbours.
        return $this->minimalLocalGraph($idOrSlug);
    }

    /**
     * Minimal local graph fallback - the central node only. Used only when the
     * canonical builder is unavailable, so federation never hard-fails.
     *
     * @return array{'@context': array<string,mixed>, '@graph': array<int,array<string,mixed>>}
     */
    protected function minimalLocalGraph(string $idOrSlug): array
    {
        $base = rtrim((string) url('/'), '/');
        $objectId = is_numeric($idOrSlug)
            ? (int) $idOrSlug
            : (int) (Schema::hasTable('slug')
                ? DB::table('slug')->where('slug', $idOrSlug)->value('object_id')
                : 0);

        $graph = [];
        if ($objectId > 0) {
            $graph[] = [
                '@id'         => $base . '/api/v1/graph/' . $objectId,
                '@type'       => 'schema:Thing',
                'name'        => (string) $idOrSlug,
                'source_peer' => null,
            ];
        }

        return [
            '@context' => ['schema' => 'https://schema.org/'],
            '@graph'   => $graph,
        ];
    }

    // -----------------------------------------------------------------
    // Peer registry (federation_member) - active graph peers
    // -----------------------------------------------------------------

    /**
     * Active federation members usable as graph peers: enabled, not the self
     * member, and carrying a non-empty base_url. Keyed by member id, capped.
     *
     * @return array<int,object>
     */
    protected function graphPeers(): array
    {
        if (! Schema::hasTable('federation_member')) {
            return [];
        }

        try {
            $rows = DB::table('federation_member')
                ->where('is_enabled', 1)
                ->where('is_self', 0)
                ->whereNotNull('base_url')
                ->where('base_url', '!=', '')
                ->orderBy('id')
                ->limit($this->maxPeers)
                ->select('id', 'name', 'base_url')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[federation-graph] peer registry query failed', ['error' => $e->getMessage()]);
            return [];
        }

        $peers = [];
        foreach ($rows as $row) {
            $peers[(int) $row->id] = $row;
        }

        return $peers;
    }

    // -----------------------------------------------------------------
    // Live cross-peer fetch (curl_multi, mirrors FederatedSearchService)
    // -----------------------------------------------------------------

    /**
     * Fetch each peer's graph neighbourhood for $ref in parallel. Cache-first
     * per (peer, ref); rate-limited per peer; SSRF-guarded per peer. The HTTP,
     * guard, cache and rate-limit machinery now lives in the shared
     * FederationClient (heratio#1314 F1); this method only builds the per-peer
     * request specs and translates the client's per-peer results into the
     * warnings this service surfaces. Returns peerId => result array:
     *   ['status' => 'success'|'error'|'skipped', 'body' => string|null,
     *    'error' => string|null, 'cached' => bool, 'duration_ms' => float]
     *
     * @param  array<int,object>  $peers
     * @param  array<int,string>  $warnings  (by reference)
     * @return array<int,array<string,mixed>>
     */
    protected function fetchPeersParallel(array $peers, string $ref, array &$warnings): array
    {
        $client = (new FederationClient())
            ->withTimeouts($this->peerTimeoutMs, $this->peerConnectTimeoutMs)
            ->withCacheTtl($this->cacheTtlSeconds)
            ->withRateLimit($this->peerMinIntervalSeconds)
            ->withMaxPeers($this->maxPeers)
            ->withHeaders([
                'Accept: application/ld+json, application/json',
                'User-Agent: Heratio-Federation-Graph/1.0',
            ]);

        $specs = [];
        foreach ($peers as $peerId => $peer) {
            $specs[$peerId] = [
                'url'       => $this->peerGraphUrl($peer->base_url, $ref),
                'base_url'  => $peer->base_url,
                'cache_key' => $this->cacheKey($peerId, $ref),
                'rate_key'  => $this->rateLimitKey($peerId),
            ];
        }

        $results = $client->fetchMany($specs);

        // Surface the same warnings the inline loop used to add.
        foreach ($results as $peerId => $resp) {
            if (! isset($peers[$peerId])) {
                continue;
            }
            $peer = $peers[$peerId];
            if ($resp['status'] === 'skipped' && ! empty($resp['error']) && str_contains((string) $resp['error'], 'rate-limited')) {
                $warnings[] = sprintf('Peer %d (%s): rate-limited, skipped', $peer->id, $peer->name);
            } elseif ($resp['status'] === 'error' && ($resp['error'] ?? '') === 'blocked by SSRF guard') {
                $warnings[] = sprintf('Peer %d (%s): blocked by SSRF guard', $peer->id, $peer->name);
            }
        }

        return $results;
    }

    /**
     * A peer's graph endpoint for a reference. We request the JSON-LD form so
     * the body is parseable as the @context/@graph shape this service consumes.
     */
    protected function peerGraphUrl(string $baseUrl, string $ref): string
    {
        return rtrim($baseUrl, '/') . '/api/v1/graph/' . rawurlencode($ref) . '.jsonld';
    }

    /**
     * Verify a peer response via the federation trust handshake (T1 #1316) and
     * pin its key TOFU. Delegates to FederationVerifier (the SSRF-guarded key
     * fetch + Ed25519 verify + TOFU pin). Best-effort: any failure yields a
     * verified=false verdict, never an exception.
     *
     * @param  array<string,string>  $headers  peer response headers (lower-cased)
     * @return array{verified:bool,key_fingerprint:?string,reason:string}
     */
    protected function verifyPeer(string $body, array $headers, string $baseUrl): array
    {
        try {
            return (new FederationVerifier())->verifyResponse($body, $headers, $baseUrl);
        } catch (\Throwable $e) {
            return ['verified' => false, 'key_fingerprint' => null, 'reason' => 'error'];
        }
    }

    // -----------------------------------------------------------------
    // Peer graph parsing + merge helpers
    // -----------------------------------------------------------------

    /**
     * Parse a peer's JSON-LD graph response into node-by-uri, tagging each node
     * with its source_peer. Understands the Heratio Open Memory Protocol shape
     * (top-level @graph array of nodes each carrying @id). A peer whose body is
     * not parseable as that shape contributes nothing and adds a warning.
     *
     * @param  array{id:int,name:string,base_url:string}  $sourcePeer
     * @param  array<int,string>  $warnings  (by reference)
     * @return array<string,array<string,mixed>>  uri => node
     */
    protected function parsePeerGraph(string $body, array $sourcePeer, array &$warnings): array
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            $warnings[] = sprintf('Peer %d (%s): invalid JSON graph response', $sourcePeer['id'], $sourcePeer['name']);
            return [];
        }

        $graph = $decoded['@graph'] ?? null;
        if (! is_array($graph)) {
            // Some peers may return a single node object rather than an @graph.
            if (isset($decoded['@id'])) {
                $graph = [$decoded];
            } else {
                $warnings[] = sprintf('Peer %d (%s): response has no @graph', $sourcePeer['id'], $sourcePeer['name']);
                return [];
            }
        }

        $nodes = [];
        foreach ($graph as $node) {
            if (! is_array($node) || empty($node['@id'])) {
                continue;
            }
            $uri = (string) $node['@id'];
            $node['source_peer'] = $sourcePeer;
            $nodes[$uri] = $node;
        }

        return $nodes;
    }

    /**
     * Record that a peer also holds an already-known node (local or another
     * peer wins the node body; we just append to also_present_in).
     *
     * @param  array<string,mixed>  $node
     * @param  array{id:int,name:string,base_url:string}  $sourcePeer
     * @return array<string,mixed>
     */
    protected function noteAlsoPresent(array $node, array $sourcePeer): array
    {
        $also = $node['also_present_in'] ?? [];
        $seen = false;
        foreach ($also as $p) {
            if (($p['id'] ?? null) === $sourcePeer['id']) {
                $seen = true;
                break;
            }
        }
        if (! $seen) {
            $also[] = $sourcePeer;
        }
        $node['also_present_in'] = $also;

        return $node;
    }

    // -----------------------------------------------------------------
    // Cache keys
    // -----------------------------------------------------------------

    /**
     * Human-readable aggregate trust notice for the federation block (#1317).
     * OFF + unverified present: "N node(s) from unverified peers ...". ON +
     * dropped: "N node(s) from unverified peers were excluded ...". Otherwise
     * null (nothing to say).
     */
    protected function trustNotice(bool $requireVerified, int $unverified, int $dropped): ?string
    {
        if ($requireVerified) {
            if ($dropped > 0) {
                return sprintf('%d node(s) from unverified peers were excluded (federation_require_verified is ON).', $dropped);
            }

            return null;
        }

        if ($unverified > 0) {
            return sprintf('%d node(s) from unverified peers are included and flagged (federation_require_verified is OFF).', $unverified);
        }

        return null;
    }

    protected function cacheKey(int $peerId, string $ref): string
    {
        return 'fedgraph:peer:' . $peerId . ':ref:' . sha1($ref);
    }

    protected function rateLimitKey(int $peerId): string
    {
        return 'fedgraph:peer:' . $peerId . ':rl';
    }
}
