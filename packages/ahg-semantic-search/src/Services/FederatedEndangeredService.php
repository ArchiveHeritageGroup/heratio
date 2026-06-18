<?php

/**
 * FederatedEndangeredService - LIVE cross-peer endangered-heritage aggregation
 * (north-star heratio#1205, the "race against loss").
 *
 * The federation AGGREGATE side. Where EndangeredHeritageService is the single-
 * instance register and EndangeredApiController EXPOSES it, this service ASKS
 * every active federation peer for its register and merges the answers into one
 * cross-institution at-risk leaderboard:
 *
 *   globalRegister(array $filters=[]): array
 *     - THIS instance's published at-risk register (via EndangeredHeritageService
 *       ::publicRegister, so it never leaks an unpublished record), PLUS, for each
 *       ACTIVE federation_member peer, a LIVE fetch of
 *       {peer.base_url}/api/v1/endangered, in parallel via curl_multi.
 *     - Each remote row is tagged source_peer={id,name,base_url}; local rows carry
 *       source_peer=null. Rows are merged, deduped (peer+ref), and ranked by
 *       EndangeredHeritageService::priorityScore() then urgency weight.
 *     - Returns {items, peers_queried, peers, warnings, local_count, total_count}.
 *
 * Product decision (matching FederationGraphService): this is LIVE querying, not
 * harvest-and-cache. A short per-peer cache + a per-peer rate limit only protect
 * peers from being hammered.
 *
 * Security: as of the F1 unification (heratio#1314) the SSRF host-guard +
 * curl_multi parallel fetch + cache + per-peer rate-limit + peer cap live in the
 * shared AhgFederation\Services\FederationClient; this service delegates all HTTP
 * to it and keeps only its own register parsing / merge / ranking / provenance
 * logic. The guard semantics are unchanged (cloud-metadata / loopback /
 * link-local / private / reserved-IP rejection, FOLLOWLOCATION=false). A peer
 * that fails the guard, errors, or times out is SKIPPED and noted in `warnings`;
 * it is never fatal. Federation absent / zero peers / a peer down / bad JSON all
 * degrade to local-only + warnings - this service NEVER throws and NEVER 500s.
 *
 * Scope of this first increment: live aggregation + the unified board it feeds.
 * Deferred follow-ups (noted, not built): climate / conflict-zone risk overlays,
 * a push-model peer inbound, and a dedicated federation cache table.
 *
 * @author     Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FederatedEndangeredService
{
    /** Per-peer HTTP timeout (ms). Mirrors FederationGraphService. */
    protected int $peerTimeoutMs = 5000;

    /** Per-peer connect timeout (ms). */
    protected int $peerConnectTimeoutMs = 2000;

    /** Per-(peer, filter) cache TTL (seconds). Short - this is live querying. */
    protected int $cacheTtlSeconds = 300;

    /** Per-peer rate limit: minimum seconds between two LIVE fetches of a peer. */
    protected int $peerMinIntervalSeconds = 2;

    /** Hard cap on peers queried in one aggregation, so the door stays cheap. */
    protected int $maxPeers = 25;

    /** Cap on rows merged into the unified board (after ranking). */
    protected int $maxItems = 500;

    protected EndangeredHeritageService $local;

    public function __construct(?EndangeredHeritageService $local = null)
    {
        $this->local = $local ?? new EndangeredHeritageService;
    }

    /**
     * Aggregate the local + cross-peer endangered register into one ranked board.
     *
     * @param  array<string,mixed>  $filters  optional: risk, urgency, status, limit
     * @return array{
     *   items:array<int,array<string,mixed>>,
     *   peers_queried:int,
     *   peers:array<int,array<string,mixed>>,
     *   warnings:array<int,string>,
     *   local_count:int,
     *   total_count:int
     * }
     */
    public function globalRegister(array $filters = []): array
    {
        $warnings = [];

        $risk = $this->cleanFilter($filters['risk'] ?? null);
        $urgency = $this->cleanFilter($filters['urgency'] ?? null);
        $status = $this->cleanFilter($filters['status'] ?? null);

        // 1. LOCAL register (published-only, never leaks a draft). Tagged null.
        $merged = [];
        $localCount = 0;
        foreach ($this->localRows($risk, $urgency, $status) as $row) {
            $key = $this->dedupeKey(null, (string) ($row['item_ref'] ?? ''));
            $row['source_peer'] = null;
            $merged[$key] = $row;
            $localCount++;
        }

        // 2. Active peers with a usable base_url.
        $peers = $this->peers();
        $peerStats = [];

        if (! empty($peers)) {
            $responses = $this->fetchPeersParallel($peers, $risk, $urgency, $status, $warnings);

            foreach ($responses as $peerId => $resp) {
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
                    'item_count'  => 0,
                    'cached'      => $resp['cached'] ?? false,
                    'duration_ms' => $resp['duration_ms'] ?? 0,
                ];

                if ($resp['status'] === 'success') {
                    // Federation trust handshake (T1 #1316): verify the peer's
                    // detached signature over the EXACT received bytes and pin
                    // its key TOFU. The verdict (verified + key_fingerprint) is
                    // stamped onto source_peer so every cross-peer at-risk row
                    // carries its own cryptographic-trust provenance. Best-effort:
                    // an unsigned / unverifiable peer stays verified=false and
                    // still merges (T1 establishes trust; T2 decides the policy).
                    $verdict = $this->verifyPeer($resp['body'] ?? '', $resp['headers'] ?? [], (string) $peer->base_url);
                    $sourcePeer['verified'] = $verdict['verified'];
                    $sourcePeer['key_fingerprint'] = $verdict['key_fingerprint'];
                    $stat['verified'] = $verdict['verified'];
                    $stat['key_fingerprint'] = $verdict['key_fingerprint'];
                    $stat['trust_reason'] = $verdict['reason'];

                    $peerRows = $this->parsePeerRegister($resp['body'] ?? '', $sourcePeer, $warnings);
                    foreach ($peerRows as $row) {
                        $key = $this->dedupeKey($sourcePeer['id'], (string) ($row['item_ref'] ?? ''));
                        if (isset($merged[$key])) {
                            continue; // already seen this peer+ref
                        }
                        $merged[$key] = $row;
                    }
                    $stat['item_count'] = count($peerRows);
                } elseif (! empty($resp['error'])) {
                    $warnings[] = sprintf('Peer %d (%s): %s', $peer->id, $peer->name, $resp['error']);
                }

                $peerStats[] = $stat;
            }
        }

        // 3. Rank: priority score (reuses the local scorer) desc, then urgency
        // weight desc, then title for a stable order.
        $items = array_values($merged);
        usort($items, function ($a, $b) {
            $sa = (int) ($a['priority_score'] ?? 0);
            $sb = (int) ($b['priority_score'] ?? 0);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            $wa = (int) ($a['urgency_weight'] ?? 0);
            $wb = (int) ($b['urgency_weight'] ?? 0);
            if ($wa !== $wb) {
                return $wb <=> $wa;
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        if (count($items) > $this->maxItems) {
            $items = array_slice($items, 0, $this->maxItems);
        }

        return [
            'items'         => $items,
            'peers_queried' => count($peerStats),
            'peers'         => $peerStats,
            'warnings'      => $warnings,
            'local_count'   => $localCount,
            'total_count'   => count($items),
        ];
    }

    // -----------------------------------------------------------------
    // Local register rows (reuse EndangeredHeritageService)
    // -----------------------------------------------------------------

    /**
     * THIS instance's published at-risk rows, normalised into the same shape as
     * the peer rows so they merge cleanly. Risk filter is pushed to the service;
     * urgency / status are applied here. Never throws.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function localRows(string $risk, string $urgency, string $status): array
    {
        if (! $this->local->available()) {
            return [];
        }

        try {
            $register = $this->local->publicRegister($risk !== '' ? $risk : null, 0);
        } catch (\Throwable $e) {
            Log::info('[fed-endangered] local register failed: '.$e->getMessage());

            return [];
        }

        $base = rtrim((string) url('/'), '/');
        $institution = (string) config('app.name', 'Heratio');

        $rows = [];
        foreach ($register as $r) {
            if ($urgency !== '' && strcasecmp((string) ($r['urgency'] ?? ''), $urgency) !== 0) {
                continue;
            }
            if ($status !== '' && strcasecmp((string) ($r['capture_status'] ?? ''), $status) !== 0) {
                continue;
            }

            $slug = $r['item_slug'] ?? null;
            $slug = ($slug !== null && $slug !== '') ? (string) $slug : null;
            $ref = $slug !== null ? $slug : (string) ((int) ($r['item_ref'] ?? 0));

            $rows[] = [
                'item_ref'             => $ref,
                'title'                => $r['item_title'] ?? null,
                'risk_category'        => (string) ($r['risk_category'] ?? 'other'),
                'risk_label'           => (string) ($r['risk_meta']['label'] ?? ''),
                'urgency'              => (string) ($r['urgency'] ?? 'medium'),
                'urgency_label'        => (string) ($r['urgency_meta']['label'] ?? ''),
                'urgency_weight'       => (int) ($r['urgency_meta']['weight'] ?? 0),
                'capture_status'       => (string) ($r['capture_status'] ?? 'flagged'),
                'capture_status_label' => (string) ($r['capture_meta']['label'] ?? ''),
                'reason'               => $r['reason'] ?? null,
                'priority_score'       => (int) ($r['priority_score'] ?? 0),
                'flagged_at'           => $r['created_at'] ?? null,
                'catalogue_url'        => $slug !== null ? $base.'/'.$slug : null,
                'institution'          => $institution,
            ];
        }

        return $rows;
    }

    // -----------------------------------------------------------------
    // Peer registry (federation_member) - active peers
    // -----------------------------------------------------------------

    /**
     * Active federation members usable as peers: enabled, not the self member,
     * with a non-empty base_url. Keyed by member id, capped. Never throws.
     *
     * @return array<int,object>
     */
    protected function peers(): array
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
            Log::warning('[fed-endangered] peer registry query failed', ['error' => $e->getMessage()]);

            return [];
        }

        $peers = [];
        foreach ($rows as $row) {
            $peers[(int) $row->id] = $row;
        }

        return $peers;
    }

    // -----------------------------------------------------------------
    // Live cross-peer fetch (curl_multi, mirrors FederationGraphService)
    // -----------------------------------------------------------------

    /**
     * Fetch each peer's /api/v1/endangered in parallel. Cache-first per
     * (peer, filter); rate-limited per peer; SSRF-guarded per peer. The HTTP,
     * guard, cache and rate-limit machinery now lives in the shared
     * FederationClient (heratio#1314 F1); this method only builds the per-peer
     * request specs and translates the client's per-peer results into the
     * warnings this service surfaces. Returns peerId => result array.
     *
     * @param  array<int,object>  $peers
     * @param  array<int,string>  $warnings  (by reference)
     * @return array<int,array<string,mixed>>
     */
    protected function fetchPeersParallel(array $peers, string $risk, string $urgency, string $status, array &$warnings): array
    {
        $filterSig = $this->filterSignature($risk, $urgency, $status);

        $client = (new \AhgFederation\Services\FederationClient())
            ->withTimeouts($this->peerTimeoutMs, $this->peerConnectTimeoutMs)
            ->withCacheTtl($this->cacheTtlSeconds)
            ->withRateLimit($this->peerMinIntervalSeconds)
            ->withMaxPeers($this->maxPeers)
            ->withHeaders([
                'Accept: application/json',
                'User-Agent: Heratio-Federation-Endangered/1.0',
            ]);

        $specs = [];
        foreach ($peers as $peerId => $peer) {
            $specs[$peerId] = [
                'url'       => $this->peerEndangeredUrl($peer->base_url, $risk, $urgency, $status),
                'base_url'  => $peer->base_url,
                'cache_key' => $this->cacheKey($peerId, $filterSig),
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
     * A peer's endangered endpoint for the active filters. Filters are forwarded
     * so the peer does the narrowing too (defence in depth + smaller payloads).
     */
    protected function peerEndangeredUrl(string $baseUrl, string $risk, string $urgency, string $status): string
    {
        $url = rtrim($baseUrl, '/').'/api/v1/endangered';

        $query = [];
        if ($risk !== '') {
            $query['risk'] = $risk;
        }
        if ($urgency !== '') {
            $query['urgency'] = $urgency;
        }
        if ($status !== '') {
            $query['status'] = $status;
        }

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * Verify a peer response via the federation trust handshake (T1 #1316) and
     * pin its key TOFU. Delegates to ahg-federation's FederationVerifier (the
     * SSRF-guarded key fetch + Ed25519 verify + TOFU pin). Best-effort: any
     * failure (or ahg-federation absent) yields a verified=false verdict, never
     * an exception - this service never throws.
     *
     * @param  array<string,string>  $headers  peer response headers (lower-cased)
     * @return array{verified:bool,key_fingerprint:?string,reason:string}
     */
    protected function verifyPeer(string $body, array $headers, string $baseUrl): array
    {
        $verifierClass = \AhgFederation\Services\FederationVerifier::class;
        if (! class_exists($verifierClass)) {
            return ['verified' => false, 'key_fingerprint' => null, 'reason' => 'unsigned'];
        }

        try {
            return (new $verifierClass)->verifyResponse($body, $headers, $baseUrl);
        } catch (\Throwable $e) {
            return ['verified' => false, 'key_fingerprint' => null, 'reason' => 'error'];
        }
    }

    // -----------------------------------------------------------------
    // Peer register parsing + normalisation
    // -----------------------------------------------------------------

    /**
     * Parse a peer's /api/v1/endangered JSON response into normalised rows, each
     * tagged with its source_peer. Understands the EndangeredApiController shape
     * (top-level "items" array). A body that is not parseable contributes nothing
     * and adds a warning. Never throws.
     *
     * @param  array{id:int,name:string,base_url:string}  $sourcePeer
     * @param  array<int,string>  $warnings  (by reference)
     * @return array<int,array<string,mixed>>
     */
    protected function parsePeerRegister(string $body, array $sourcePeer, array &$warnings): array
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            $warnings[] = sprintf('Peer %d (%s): invalid JSON register response', $sourcePeer['id'], $sourcePeer['name']);

            return [];
        }

        $items = $decoded['items'] ?? null;
        if (! is_array($items)) {
            $warnings[] = sprintf('Peer %d (%s): response has no items array', $sourcePeer['id'], $sourcePeer['name']);

            return [];
        }

        $peerInstitution = isset($decoded['institution']) && is_string($decoded['institution']) && $decoded['institution'] !== ''
            ? (string) $decoded['institution']
            : $sourcePeer['name'];

        $rows = [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }

            $risk = (string) ($it['risk_category'] ?? 'other');
            $urgency = (string) ($it['urgency'] ?? 'medium');
            $captureStatus = (string) ($it['capture_status'] ?? 'flagged');

            // Trust the peer's own priority_score when present; otherwise recompute
            // with the SAME scorer so cross-peer ranking stays consistent.
            $priority = isset($it['priority_score']) && is_numeric($it['priority_score'])
                ? (int) $it['priority_score']
                : $this->local->priorityScore([
                    'risk_category'  => $risk,
                    'urgency'        => $urgency,
                    'capture_status' => $captureStatus,
                ]);

            $urgencyMeta = $this->local->urgencyMeta($urgency);

            // Prefer the peer-provided absolute catalogue_url; never synthesise one
            // against our own host for a remote ref.
            $catalogueUrl = isset($it['catalogue_url']) && is_string($it['catalogue_url']) && $it['catalogue_url'] !== ''
                ? (string) $it['catalogue_url']
                : null;

            $rows[] = [
                'item_ref'             => (string) ($it['item_ref'] ?? ''),
                'title'                => $it['title'] ?? null,
                'risk_category'        => $risk,
                'risk_label'           => (string) ($it['risk_label'] ?? $this->local->riskMeta($risk)['label']),
                'urgency'              => $urgency,
                'urgency_label'        => (string) ($it['urgency_label'] ?? $urgencyMeta['label']),
                'urgency_weight'       => (int) ($urgencyMeta['weight'] ?? 0),
                'capture_status'       => $captureStatus,
                'capture_status_label' => (string) ($it['capture_status_label'] ?? $this->local->captureStatusMeta($captureStatus)['label']),
                'reason'               => $it['reason'] ?? null,
                'priority_score'       => $priority,
                'flagged_at'           => $it['flagged_at'] ?? null,
                'catalogue_url'        => $catalogueUrl,
                'institution'          => $peerInstitution,
                'source_peer'          => $sourcePeer,
            ];
        }

        return $rows;
    }

    // -----------------------------------------------------------------
    // Small helpers
    // -----------------------------------------------------------------

    /**
     * Dedupe key: a row is unique per (peer, ref). Local rows use peer id 0.
     */
    protected function dedupeKey(?int $peerId, string $ref): string
    {
        return ($peerId === null ? '0' : (string) $peerId).':'.$ref;
    }

    /**
     * Normalise a filter value to a trimmed lower-case string, or '' when blank.
     */
    protected function cleanFilter($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return strtolower(trim((string) $value));
    }

    protected function filterSignature(string $risk, string $urgency, string $status): string
    {
        return sha1($risk.'|'.$urgency.'|'.$status);
    }

    protected function cacheKey(int $peerId, string $filterSig): string
    {
        return 'fedendangered:peer:'.$peerId.':f:'.$filterSig;
    }

    protected function rateLimitKey(int $peerId): string
    {
        return 'fedendangered:peer:'.$peerId.':rl';
    }
}
