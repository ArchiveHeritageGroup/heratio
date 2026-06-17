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
 * Security: cross-peer HTTP is an SSRF risk. The SSRF host-guard here is a
 * replica of the guard in
 * packages/ahg-federation/src/Services/FederatedSearchService.php
 * (createSearchRequest, around line 384) - that file is locked, so the guard
 * is replicated rather than imported. Any change to the guard there should be
 * mirrored here. A peer that fails the guard, errors, or times out is SKIPPED
 * and noted in `warnings`; it is never fatal (fail soft - a dead peer or zero
 * peers returns just the local graph + warnings, never an exception).
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

use Illuminate\Support\Facades\Cache;
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

        // 2. Active peers with a usable base_url.
        $peers = $this->graphPeers();
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
     * per (peer, ref); rate-limited per peer. Returns peerId => result array:
     *   ['status' => 'success'|'cached'|'error'|'skipped', 'body' => string|null,
     *    'error' => string|null, 'cached' => bool, 'duration_ms' => float]
     *
     * Mirrors the curl_multi loop in FederatedSearchService::runOaiPeers().
     *
     * @param  array<int,object>  $peers
     * @param  array<int,string>  $warnings  (by reference)
     * @return array<int,array<string,mixed>>
     */
    protected function fetchPeersParallel(array $peers, string $ref, array &$warnings): array
    {
        $results = [];
        $toFetch = [];

        // Cache-first + rate-limit gate. A peer with a fresh cache entry is
        // served from cache and never re-fetched.
        foreach ($peers as $peerId => $peer) {
            $cacheKey = $this->cacheKey($peerId, $ref);
            $cached = Cache::get($cacheKey);
            if (is_string($cached)) {
                $results[$peerId] = [
                    'status'      => 'success',
                    'body'        => $cached,
                    'error'       => null,
                    'cached'      => true,
                    'duration_ms' => 0,
                ];
                continue;
            }

            // Per-peer rate limit: refuse a live fetch inside the cool-down
            // window when there is no cached value to serve.
            $rlKey = $this->rateLimitKey($peerId);
            if (Cache::get($rlKey)) {
                $results[$peerId] = [
                    'status'      => 'skipped',
                    'body'        => null,
                    'error'       => 'rate-limited (no cached graph available)',
                    'cached'      => false,
                    'duration_ms' => 0,
                ];
                $warnings[] = sprintf('Peer %d (%s): rate-limited, skipped', $peer->id, $peer->name);
                continue;
            }

            // SSRF guard (replica of FederatedSearchService::createSearchRequest,
            // see file header). A blocked host is skipped, never fetched.
            if (! $this->hostAllowed($peer->base_url)) {
                $results[$peerId] = [
                    'status'      => 'error',
                    'body'        => null,
                    'error'       => 'blocked by SSRF guard',
                    'cached'      => false,
                    'duration_ms' => 0,
                ];
                $warnings[] = sprintf('Peer %d (%s): blocked by SSRF guard', $peer->id, $peer->name);
                continue;
            }

            $toFetch[$peerId] = $peer;
            // Arm the rate-limit window now so concurrent walks cooperate.
            Cache::put($rlKey, 1, $this->peerMinIntervalSeconds);
        }

        if (empty($toFetch)) {
            return $results;
        }

        $multi = curl_multi_init();
        $handles = [];

        foreach ($toFetch as $peerId => $peer) {
            $url = $this->peerGraphUrl($peer->base_url, $ref);
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER     => true,
                CURLOPT_TIMEOUT_MS         => $this->peerTimeoutMs,
                CURLOPT_CONNECTTIMEOUT_MS  => min($this->peerConnectTimeoutMs, $this->peerTimeoutMs),
                CURLOPT_FOLLOWLOCATION     => false, // do NOT follow redirects: a 30x could bounce to an internal host past the guard
                CURLOPT_SSL_VERIFYPEER     => true,
                CURLOPT_SSL_VERIFYHOST     => 2,
                CURLOPT_HTTPHEADER         => [
                    'Accept: application/ld+json, application/json',
                    'User-Agent: Heratio-Federation-Graph/1.0',
                ],
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$peerId] = ['handle' => $handle, 'peer' => $peer, 'start' => microtime(true)];
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);
        } while ($running > 0);

        foreach ($handles as $peerId => $data) {
            $handle = $data['handle'];
            $peer = $data['peer'];
            $duration = (microtime(true) - $data['start']) * 1000;

            $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            $body = curl_multi_getcontent($handle);

            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);

            if ($error !== '') {
                $results[$peerId] = [
                    'status'      => 'error',
                    'body'        => null,
                    'error'       => $error,
                    'cached'      => false,
                    'duration_ms' => round($duration, 2),
                ];
                continue;
            }

            if ($httpCode !== 200) {
                $results[$peerId] = [
                    'status'      => 'error',
                    'body'        => null,
                    'error'       => 'HTTP ' . $httpCode,
                    'cached'      => false,
                    'duration_ms' => round($duration, 2),
                ];
                continue;
            }

            // Cache the raw body per (peer, ref) for the short TTL.
            Cache::put($this->cacheKey($peerId, $ref), (string) $body, $this->cacheTtlSeconds);

            $results[$peerId] = [
                'status'      => 'success',
                'body'        => (string) $body,
                'error'       => null,
                'cached'      => false,
                'duration_ms' => round($duration, 2),
            ];
        }

        curl_multi_close($multi);

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

    // -----------------------------------------------------------------
    // SSRF guard - REPLICA of FederatedSearchService (locked file)
    // -----------------------------------------------------------------

    /**
     * SSRF host-guard. Replica of the block in
     * FederatedSearchService::createSearchRequest (around line 384): it rejects
     * the well-known cloud-metadata hostnames/IPs. Extended (defence in depth)
     * to also reject loopback, link-local, and obviously-internal/private
     * targets, since a peer graph URL is fetched server-side and is a textbook
     * SSRF sink. A peer whose base_url fails this guard is SKIPPED.
     *
     * Mirror any change to the source guard here (the source file is locked).
     */
    protected function hostAllowed(string $baseUrl): bool
    {
        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        // Cloud-metadata endpoints - the exact set FederatedSearchService blocks.
        $blockedHosts = ['169.254.169.254', 'metadata.google.internal', 'metadata.internal'];
        if (in_array($host, $blockedHosts, true)) {
            return false;
        }

        // Loopback / unspecified by name.
        if (in_array($host, ['localhost', 'ip6-localhost', '0.0.0.0', '::1', '[::1]'], true)) {
            return false;
        }

        // If the host is a literal IP, reject private / loopback / link-local /
        // reserved ranges (a peer should be a public catalogue host).
        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $publicOnly = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($publicOnly === false) {
                return false;
            }
        }

        return true;
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

    protected function cacheKey(int $peerId, string $ref): string
    {
        return 'fedgraph:peer:' . $peerId . ':ref:' . sha1($ref);
    }

    protected function rateLimitKey(int $peerId): string
    {
        return 'fedgraph:peer:' . $peerId . ':rl';
    }
}
