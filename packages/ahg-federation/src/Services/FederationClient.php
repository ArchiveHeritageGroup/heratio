<?php

/**
 * FederationClient - the single shared cross-peer HTTP fetch layer for the
 * Federation Query Protocol (epic heratio#1313, issue heratio#1314 "F1").
 *
 * Three live federated services used to each replicate the same curl_multi
 * parallel fetch + hardened SSRF host-guard + short per-(peer, ref) cache +
 * per-peer rate-limit + peer cap + fail-soft loop:
 *
 *   - AhgFederation\Services\FederationGraphService          (graph aggregation)
 *   - AhgSemanticSearch\Services\FederatedEndangeredService  (at-risk register)
 *   - AhgFederation\Services\FederatedSearchService          (cross-peer search)
 *
 * This class lifts that duplicated machinery into ONE reusable place so the
 * SSRF guard, the timeouts, FOLLOWLOCATION=false, the cache/rate-limit gates
 * and the curl_multi loop live in a single audited spot. The consuming
 * services keep their own parsing / merging / provenance logic and only
 * delegate the HTTP to this client.
 *
 * NOTE: FederatedSearchService is currently a LOCKED file (.locked-paths) and
 * is NOT refactored in this pass. It should adopt FederationClient the next
 * time it is unlocked - the curl_multi + SSRF guard it carries is the same
 * pattern lifted here and can be deleted in favour of this client.
 *
 * Behaviour is functionality-preserving: same per-peer connect+total timeouts,
 * same SSRF guard rules (scheme allowlist; cloud-metadata / loopback /
 * link-local / private / reserved-IP rejection), same FOLLOWLOCATION=false,
 * same SSL verification, same short cache, same per-peer rate-limit window,
 * same peer cap, same fail-soft (a blocked / errored / timed-out / rate-limited
 * peer is SKIPPED and reported, never fatal).
 *
 * Security: cross-peer HTTP is a textbook SSRF sink. hostAllowed() is the
 * canonical guard - a peer whose base_url fails it is never fetched.
 * FOLLOWLOCATION is OFF so a 30x cannot bounce a fetch onto an internal host
 * past the guard.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace AhgFederation\Services;

use Illuminate\Support\Facades\Cache;

class FederationClient
{
    /** Per-peer total HTTP timeout (ms). */
    protected int $timeoutMs = 5000;

    /** Per-peer connect timeout (ms). */
    protected int $connectTimeoutMs = 2000;

    /** Per-(peer, ref) cache TTL (seconds). Short - this is live querying. */
    protected int $cacheTtlSeconds = 300;

    /**
     * Per-peer rate limit: minimum seconds between two LIVE fetches of the same
     * peer. A request inside the window with no cached value is skipped with a
     * rate-limit note. Cheap protection so a walk cannot hammer a peer.
     */
    protected int $rateLimitSeconds = 2;

    /** Hard cap on peers fetched in one call, so the door stays cheap. */
    protected int $maxPeers = 25;

    /** Default request headers (overridable per call). */
    protected array $defaultHeaders = [
        'Accept: application/ld+json, application/json',
        'User-Agent: Heratio-Federation/1.0',
    ];

    // -----------------------------------------------------------------
    // Tunables (fluent setters - callers preserve their own constants)
    // -----------------------------------------------------------------

    public function withTimeouts(int $timeoutMs, int $connectTimeoutMs): self
    {
        $this->timeoutMs = $timeoutMs;
        $this->connectTimeoutMs = $connectTimeoutMs;

        return $this;
    }

    public function withCacheTtl(int $seconds): self
    {
        $this->cacheTtlSeconds = $seconds;

        return $this;
    }

    public function withRateLimit(int $seconds): self
    {
        $this->rateLimitSeconds = $seconds;

        return $this;
    }

    public function withMaxPeers(int $maxPeers): self
    {
        $this->maxPeers = $maxPeers;

        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->defaultHeaders = $headers;

        return $this;
    }

    public function maxPeers(): int
    {
        return $this->maxPeers;
    }

    // -----------------------------------------------------------------
    // SSRF guard (the canonical guard for all federated fetches)
    // -----------------------------------------------------------------

    /**
     * SSRF host-guard. Rejects the well-known cloud-metadata hosts plus
     * loopback / link-local / private / reserved targets, and any non
     * http(s) scheme. A peer whose base_url fails this guard must be SKIPPED,
     * never fetched.
     *
     * This is the single source of truth for the guard semantics that used to
     * be replicated in FederationGraphService::hostAllowed and
     * FederatedEndangeredService::hostAllowed (both of which now delegate here),
     * and which itself mirrors the guard in the locked FederatedSearchService.
     * Any change to the guard belongs HERE.
     */
    public function hostAllowed(string $baseUrl): bool
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
    // Parallel fetch (curl_multi)
    // -----------------------------------------------------------------

    /**
     * Fetch many peer URLs in parallel via curl_multi, cache-first and
     * rate-limited per peer, SSRF-guarded per peer, fail-soft per peer.
     *
     * Each $spec is keyed by peer id and is an array:
     *   [
     *     'url'         => string  full request URL (REQUIRED),
     *     'base_url'    => string  the peer base_url for the SSRF guard
     *                              (defaults to 'url' when omitted),
     *     'cache_key'   => string  per-(peer, ref) cache key (REQUIRED for caching;
     *                              omit to bypass the cache),
     *     'rate_key'    => string  per-peer rate-limit key (REQUIRED for the
     *                              rate-limit gate; omit to bypass it),
     *     'headers'     => array   request headers (defaults to withHeaders()),
     *   ]
     *
     * Returns, keyed by the same peer id, a result array:
     *   [
     *     'status'      => 'success'|'error'|'skipped',
     *     'body'        => string|null,
     *     'error'       => string|null,
     *     'cached'      => bool,
     *     'duration_ms' => float,
     *     'http_code'   => int,
     *   ]
     *
     * The first $this->maxPeers entries (preserving key order) are considered.
     *
     * @param  array<int|string,array<string,mixed>>  $specs
     * @return array<int|string,array<string,mixed>>
     */
    public function fetchMany(array $specs): array
    {
        // Honour the peer cap, preserving order.
        if (count($specs) > $this->maxPeers) {
            $specs = array_slice($specs, 0, $this->maxPeers, true);
        }

        $results = [];
        $toFetch = [];

        // Cache-first + rate-limit + SSRF gate.
        foreach ($specs as $id => $spec) {
            $cacheKey = $spec['cache_key'] ?? null;
            if ($cacheKey !== null) {
                $cached = Cache::get($cacheKey);
                if (is_string($cached)) {
                    $results[$id] = $this->result('success', $cached, null, true, 0, 200);
                    continue;
                }
            }

            // Per-peer rate limit: refuse a live fetch inside the cool-down
            // window when there is no cached value to serve.
            $rateKey = $spec['rate_key'] ?? null;
            if ($rateKey !== null && Cache::get($rateKey)) {
                $results[$id] = $this->result('skipped', null, 'rate-limited (no cached value available)', false, 0, 0);
                continue;
            }

            // SSRF guard. A blocked host is skipped, never fetched.
            $base = (string) ($spec['base_url'] ?? $spec['url'] ?? '');
            if (! $this->hostAllowed($base)) {
                $results[$id] = $this->result('error', null, 'blocked by SSRF guard', false, 0, 0);
                continue;
            }

            $toFetch[$id] = $spec;
            // Arm the rate-limit window now so concurrent walks cooperate.
            if ($rateKey !== null) {
                Cache::put($rateKey, 1, $this->rateLimitSeconds);
            }
        }

        if (empty($toFetch)) {
            return $results;
        }

        $multi = curl_multi_init();
        $handles = [];

        foreach ($toFetch as $id => $spec) {
            $handle = curl_init((string) $spec['url']);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER    => true,
                CURLOPT_TIMEOUT_MS        => $this->timeoutMs,
                CURLOPT_CONNECTTIMEOUT_MS => min($this->connectTimeoutMs, $this->timeoutMs),
                CURLOPT_FOLLOWLOCATION    => false, // do NOT follow redirects: a 30x could bounce to an internal host past the guard
                CURLOPT_SSL_VERIFYPEER    => true,
                CURLOPT_SSL_VERIFYHOST    => 2,
                CURLOPT_HTTPHEADER        => $spec['headers'] ?? $this->defaultHeaders,
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$id] = ['handle' => $handle, 'spec' => $spec, 'start' => microtime(true)];
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);
        } while ($running > 0);

        foreach ($handles as $id => $data) {
            $handle = $data['handle'];
            $spec = $data['spec'];
            $duration = round((microtime(true) - $data['start']) * 1000, 2);

            $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            $body = curl_multi_getcontent($handle);

            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);

            if ($error !== '') {
                $results[$id] = $this->result('error', null, $error, false, $duration, $httpCode);
                continue;
            }

            if ($httpCode !== 200) {
                $results[$id] = $this->result('error', null, 'HTTP '.$httpCode, false, $duration, $httpCode);
                continue;
            }

            // Cache the raw body per (peer, ref) for the short TTL.
            $cacheKey = $spec['cache_key'] ?? null;
            if ($cacheKey !== null) {
                Cache::put($cacheKey, (string) $body, $this->cacheTtlSeconds);
            }

            $results[$id] = $this->result('success', (string) $body, null, false, $duration, $httpCode);
        }

        curl_multi_close($multi);

        return $results;
    }

    /**
     * Fetch a single peer URL through the same guard + cache + rate-limit path
     * as fetchMany. Convenience wrapper; returns the single result array.
     *
     * @param  array<string,mixed>  $spec  same shape as a fetchMany spec
     *                                      (url is REQUIRED); base_url defaults to url.
     * @return array<string,mixed>
     */
    public function fetchOne(string $url, array $spec = []): array
    {
        $spec['url'] = $url;
        if (! isset($spec['base_url'])) {
            $spec['base_url'] = $url;
        }

        $out = $this->fetchMany(['_one' => $spec]);

        return $out['_one'] ?? $this->result('error', null, 'no result', false, 0, 0);
    }

    /**
     * Shape a single per-peer result array consistently.
     *
     * @return array<string,mixed>
     */
    protected function result(string $status, ?string $body, ?string $error, bool $cached, float $durationMs, int $httpCode): array
    {
        return [
            'status'      => $status,
            'body'        => $body,
            'error'       => $error,
            'cached'      => $cached,
            'duration_ms' => $durationMs,
            'http_code'   => $httpCode,
        ];
    }
}
