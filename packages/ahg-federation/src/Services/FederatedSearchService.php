<?php

/**
 * FederatedSearchService - real-time search across federation peers
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederatedSearchService
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_TIMEOUT = 'timeout';
    public const STATUS_ERROR = 'error';

    protected int $defaultTimeout = 5000;
    protected int $cacheMinutes = 15;
    protected int $maxResultsPerPeer = 50;

    /**
     * Run a federated search across every active peer that has search enabled.
     */
    public function search(string $query, array $options = []): FederatedSearchResult
    {
        $startTime = microtime(true);
        $queryHash = hash('sha256', $query . json_encode($options));

        $peers = $this->getSearchPeers();
        if ($peers->isEmpty()) {
            return new FederatedSearchResult(
                query: $query,
                queryHash: $queryHash,
                results: [],
                peerStats: [],
                totalResults: 0,
                duration: 0,
                fromCache: false,
            );
        }

        $useCache = $options['cache'] ?? true;
        if ($useCache) {
            $cached = $this->getCachedResults($queryHash, $peers->pluck('peer_id')->toArray());
            if ($cached !== null) {
                return new FederatedSearchResult(
                    query: $query,
                    queryHash: $queryHash,
                    results: $cached['results'],
                    peerStats: $cached['peerStats'],
                    totalResults: count($cached['results']),
                    duration: (microtime(true) - $startTime) * 1000,
                    fromCache: true,
                );
            }
        }

        $peerResults = $this->executeParallelSearches($peers, $query, $options);
        $merged = $this->mergeResults($peerResults, $options);
        $peerStats = $this->calculatePeerStats($peerResults);

        if ($useCache) {
            $this->cacheResults($queryHash, $peerResults);
        }

        $this->logSearch($query, $queryHash, $peerStats, count($merged), microtime(true) - $startTime);

        return new FederatedSearchResult(
            query: $query,
            queryHash: $queryHash,
            results: $merged,
            peerStats: $peerStats,
            totalResults: count($merged),
            duration: (microtime(true) - $startTime) * 1000,
            fromCache: false,
        );
    }

    public function searchPeer(int $peerId, string $query, array $options = []): array
    {
        $peer = DB::table('federation_peer as p')
            ->leftJoin('federation_peer_search as ps', 'p.id', '=', 'ps.peer_id')
            ->where('p.id', $peerId)
            ->where('p.is_active', 1)
            ->select('p.*', 'ps.*', 'p.id as peer_id', 'p.name as peer_name')
            ->first();

        if (!$peer) {
            return ['success' => false, 'error' => 'Peer not found or inactive'];
        }

        // Mirror the same defaults the parallel path applies.
        $peer->search_url = $peer->search_api_url ?: rtrim($peer->base_url, '/') . '/api/search';
        $peer->search_api_key = $peer->search_api_key ?: $peer->api_key;
        $peer->timeout_ms = $peer->search_timeout_ms ?: $this->defaultTimeout;
        $peer->max_results = $peer->search_max_results ?: $this->maxResultsPerPeer;

        $startTime = microtime(true);
        $results = $this->executeParallelSearches(collect([$peer]), $query, $options);
        $result = $results[(int) $peer->peer_id] ?? null;
        if (!$result) {
            return ['success' => false, 'error' => 'Search request failed'];
        }
        $result['duration'] = (microtime(true) - $startTime) * 1000;
        return $result;
    }

    protected function getSearchPeers(): Collection
    {
        $defaultTimeout = $this->defaultTimeout;
        $maxResults = $this->maxResultsPerPeer;

        return DB::table('federation_peer as p')
            ->leftJoin('federation_peer_search as ps', 'p.id', '=', 'ps.peer_id')
            ->where('p.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('ps.search_enabled')->orWhere('ps.search_enabled', 1);
            })
            ->select(
                'p.id as peer_id',
                'p.name as peer_name',
                'p.base_url',
                'p.api_key',
                DB::raw("COALESCE(ps.search_api_url, CONCAT(p.base_url, '/api/search')) as search_url"),
                DB::raw('COALESCE(ps.search_api_key, p.api_key) as search_api_key'),
                DB::raw("COALESCE(ps.search_timeout_ms, $defaultTimeout) as timeout_ms"),
                DB::raw("COALESCE(ps.search_max_results, $maxResults) as max_results"),
                DB::raw('COALESCE(ps.search_priority, 100) as priority'),
            )
            ->orderBy('priority')
            ->get();
    }

    protected function executeParallelSearches(Collection $peers, string $query, array $options): array
    {
        $results = [];
        $multi = curl_multi_init();
        $handles = [];

        foreach ($peers as $peer) {
            $handle = $this->createSearchRequest($peer, $query, $options);
            curl_multi_add_handle($multi, $handle);
            $handles[(int) $peer->peer_id] = [
                'handle' => $handle,
                'peer' => $peer,
                'startTime' => microtime(true),
            ];
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);
        } while ($running > 0);

        foreach ($handles as $peerId => $data) {
            $handle = $data['handle'];
            $peer = $data['peer'];
            $duration = (microtime(true) - $data['startTime']) * 1000;

            $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            $response = curl_multi_getcontent($handle);

            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);

            $results[$peerId] = $this->processPeerResponse($peer, $response, $httpCode, $error, $duration);
            $results[$peerId]['priority'] = (int) ($peer->priority ?? 100);
        }

        curl_multi_close($multi);

        $this->updatePeerStats($results);

        return $results;
    }

    protected function createSearchRequest(object $peer, string $query, array $options): \CurlHandle
    {
        $params = [
            'q' => $query,
            'limit' => (int) $peer->max_results,
            'format' => 'json',
        ];
        foreach (['type', 'repository', 'dateFrom', 'dateTo'] as $opt) {
            if (!empty($options[$opt])) {
                $params[$opt] = $options[$opt];
            }
        }

        $url = $peer->search_url . '?' . http_build_query($params);

        // SSRF-block obvious metadata endpoints. Peer URLs are operator-supplied
        // but this catches a misconfigured peer pointing at the cloud-metadata IP.
        $parsedHost = strtolower(parse_url($peer->base_url, PHP_URL_HOST) ?? '');
        if (in_array($parsedHost, ['169.254.169.254', 'metadata.google.internal', 'metadata.internal'], true)) {
            // Force a fast-failing handle so the peer is reported as error.
            $url = 'http://0.0.0.0:1';
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: Heratio-Federation-Search/1.0',
        ];
        if (!empty($peer->search_api_key)) {
            $headers[] = 'X-API-Key: ' . $peer->search_api_key;
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) $peer->timeout_ms,
            CURLOPT_CONNECTTIMEOUT_MS => (int) min(2000, $peer->timeout_ms),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        return $handle;
    }

    protected function processPeerResponse(
        object $peer,
        ?string $response,
        int $httpCode,
        string $error,
        float $duration,
    ): array {
        $result = [
            'peerId' => (int) $peer->peer_id,
            'peerName' => $peer->peer_name,
            'peerUrl' => $peer->base_url,
            'duration' => $duration,
            'status' => self::STATUS_ERROR,
            'results' => [],
            'totalCount' => 0,
            'error' => null,
        ];

        if (!empty($error)) {
            $result['error'] = $error;
            $result['status'] = stripos($error, 'timed out') !== false ? self::STATUS_TIMEOUT : self::STATUS_ERROR;
            return $result;
        }

        if ($httpCode !== 200) {
            $result['error'] = "HTTP $httpCode";
            return $result;
        }

        $data = json_decode((string) $response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['error'] = 'Invalid JSON response';
            return $result;
        }

        $items = $data['results'] ?? $data['items'] ?? $data['records'] ?? [];
        $totalCount = $data['total'] ?? $data['totalCount'] ?? count($items);

        $transformed = [];
        foreach ($items as $item) {
            $transformed[] = $this->transformSearchResult($item, $peer);
        }

        $result['status'] = self::STATUS_SUCCESS;
        $result['results'] = $transformed;
        $result['totalCount'] = (int) $totalCount;

        return $result;
    }

    protected function transformSearchResult(array $item, object $peer): array
    {
        return [
            'id' => $item['id'] ?? $item['identifier'] ?? null,
            'title' => $item['title'] ?? $item['name'] ?? 'Untitled',
            'description' => $item['description'] ?? $item['scopeAndContent'] ?? null,
            'identifier' => $item['referenceCode'] ?? $item['identifier'] ?? null,
            'level' => $item['levelOfDescription'] ?? $item['level'] ?? null,
            'date' => $item['date'] ?? $item['dateDisplay'] ?? null,
            'type' => $item['type'] ?? $item['objectType'] ?? null,
            'thumbnailUrl' => $item['thumbnailUrl'] ?? $item['thumbnail'] ?? null,
            'source' => [
                'peerId' => (int) $peer->peer_id,
                'peerName' => $peer->peer_name,
                'peerUrl' => $peer->base_url,
                'originalUrl' => $item['url'] ?? $item['permalink'] ?? null,
                'originalId' => $item['id'] ?? null,
            ],
            'score' => (float) ($item['score'] ?? $item['relevance'] ?? 1.0),
            '_original' => $item,
        ];
    }

    protected function mergeResults(array $peerResults, array $options): array
    {
        $allResults = [];
        $priorityByPeer = [];

        foreach ($peerResults as $peerId => $peerResult) {
            if ($peerResult['status'] !== self::STATUS_SUCCESS) {
                continue;
            }
            $priorityByPeer[$peerId] = $peerResult['priority'] ?? 100;
            foreach ($peerResult['results'] as $r) {
                $allResults[] = $r;
            }
        }

        usort($allResults, function ($a, $b) use ($priorityByPeer) {
            $scoreA = $a['score'] ?? 1.0;
            $scoreB = $b['score'] ?? 1.0;
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }
            $pa = $priorityByPeer[$a['source']['peerId']] ?? 100;
            $pb = $priorityByPeer[$b['source']['peerId']] ?? 100;
            return $pa <=> $pb;
        });

        $limit = (int) ($options['limit'] ?? 100);
        return array_slice($allResults, 0, $limit);
    }

    protected function calculatePeerStats(array $peerResults): array
    {
        $stats = [
            'queried' => count($peerResults),
            'responded' => 0,
            'timeout' => 0,
            'error' => 0,
            'peers' => [],
        ];

        foreach ($peerResults as $r) {
            $stats['peers'][] = [
                'id' => $r['peerId'],
                'name' => $r['peerName'],
                'status' => $r['status'],
                'resultCount' => count($r['results']),
                'totalCount' => $r['totalCount'],
                'duration' => $r['duration'],
                'error' => $r['error'],
            ];
            switch ($r['status']) {
                case self::STATUS_SUCCESS: $stats['responded']++; break;
                case self::STATUS_TIMEOUT: $stats['timeout']++; break;
                default:                   $stats['error']++;
            }
        }

        return $stats;
    }

    protected function updatePeerStats(array $results): void
    {
        foreach ($results as $peerId => $r) {
            DB::table('federation_peer_search')->updateOrInsert(
                ['peer_id' => $peerId],
                [
                    'last_search_at' => now(),
                    'last_search_status' => $r['status'],
                    'avg_response_time_ms' => DB::raw(
                        'CASE WHEN avg_response_time_ms = 0 THEN ' . (int) $r['duration'] .
                        ' ELSE (avg_response_time_ms + ' . (int) $r['duration'] . ') / 2 END'
                    ),
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function getCachedResults(string $queryHash, array $peerIds): ?array
    {
        if (empty($peerIds)) {
            return null;
        }

        $cached = DB::table('federation_search_cache')
            ->where('query_hash', $queryHash)
            ->whereIn('peer_id', $peerIds)
            ->where('expires_at', '>', now())
            ->get();

        if ($cached->isEmpty()) {
            return null;
        }

        $results = [];
        $peerStats = [
            'queried' => count($peerIds),
            'responded' => $cached->count(),
            'timeout' => 0,
            'error' => count($peerIds) - $cached->count(),
            'peers' => [],
        ];

        foreach ($cached as $row) {
            $data = json_decode($row->results_json, true);
            if (is_array($data)) {
                $results = array_merge($results, $data);
            }
        }

        return ['results' => $results, 'peerStats' => $peerStats];
    }

    protected function cacheResults(string $queryHash, array $peerResults): void
    {
        $expiresAt = now()->addMinutes($this->cacheMinutes);

        foreach ($peerResults as $peerId => $r) {
            if ($r['status'] !== self::STATUS_SUCCESS) {
                continue;
            }
            DB::table('federation_search_cache')->updateOrInsert(
                ['query_hash' => $queryHash, 'peer_id' => $peerId],
                [
                    'results_json' => json_encode($r['results']),
                    'result_count' => count($r['results']),
                    'created_at' => now(),
                    'expires_at' => $expiresAt,
                ]
            );
        }
    }

    protected function logSearch(
        string $query,
        string $queryHash,
        array $peerStats,
        int $totalResults,
        float $duration,
    ): void {
        try {
            DB::table('federation_search_log')->insert([
                'query_text' => substr($query, 0, 500),
                'query_hash' => $queryHash,
                'user_id' => auth()->id(),
                'peers_queried' => $peerStats['queried'],
                'peers_responded' => $peerStats['responded'],
                'peers_timeout' => $peerStats['timeout'],
                'peers_error' => $peerStats['error'],
                'total_results' => $totalResults,
                'total_time_ms' => (int) ($duration * 1000),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[federation] search log failed', ['error' => $e->getMessage()]);
        }
    }

    public function clearExpiredCache(): int
    {
        return DB::table('federation_search_cache')
            ->where('expires_at', '<', now())
            ->delete();
    }

    public function clearQueryCache(string $query): int
    {
        $queryHash = hash('sha256', $query);
        return DB::table('federation_search_cache')
            ->where('query_hash', $queryHash)
            ->delete();
    }

    public function configurePeerSearch(int $peerId, array $settings): bool
    {
        $allowedFields = [
            'search_api_url', 'search_api_key', 'search_enabled',
            'search_timeout_ms', 'search_max_results', 'search_priority',
        ];

        $data = ['peer_id' => $peerId, 'updated_at' => now()];
        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $settings)) {
                $data[$f] = $settings[$f];
            }
        }

        return (bool) DB::table('federation_peer_search')->updateOrInsert(['peer_id' => $peerId], $data);
    }
}

class FederatedSearchResult
{
    public function __construct(
        public readonly string $query,
        public readonly string $queryHash,
        public readonly array $results,
        public readonly array $peerStats,
        public readonly int $totalResults,
        public readonly float $duration,
        public readonly bool $fromCache,
    ) {}

    public function getResultsByPeer(): array
    {
        $grouped = [];
        foreach ($this->results as $r) {
            $peerId = $r['source']['peerId'];
            if (!isset($grouped[$peerId])) {
                $grouped[$peerId] = ['peer' => $r['source'], 'results' => []];
            }
            $grouped[$peerId]['results'][] = $r;
        }
        return $grouped;
    }

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'totalResults' => $this->totalResults,
            'duration' => $this->duration,
            'fromCache' => $this->fromCache,
            'peerStats' => $this->peerStats,
            'results' => $this->results,
        ];
    }

    public function toJsonResponse(): array
    {
        return [
            'success' => true,
            'data' => [
                'query' => $this->query,
                'total' => $this->totalResults,
                'duration_ms' => round($this->duration, 2),
                'cached' => $this->fromCache,
                'peers' => $this->peerStats,
                'results' => array_map(function ($r) {
                    unset($r['_original']);
                    return $r;
                }, $this->results),
            ],
        ];
    }
}
