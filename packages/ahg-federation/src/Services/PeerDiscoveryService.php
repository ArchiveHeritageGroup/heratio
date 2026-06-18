<?php

/**
 * PeerDiscoveryService - the Federation Query Protocol DISCOVERY crawl
 * (F2, heratio#1315; epic heratio#1313 "federation backbone hardening").
 *
 * For each federation_peer with a usable base_url, this service fetches the
 * peer's published capabilities document - {base_url}/open-data/protocol - plus
 * its open-data maturity scorecard - {base_url}/open-data/maturity - over the
 * shared, SSRF-guarded FederationClient (F1, #1314). It validates that the peer
 * advertises a `federation` block (the Federation Query Protocol descriptor),
 * extracts the protocol_version, the declared surfaces (graph / endangered /
 * search) and the maturity grade, and records the outcome back onto the peer
 * row:
 *
 *   discovery_status   ok | unreachable | non_compliant
 *   protocol_version   from the federation block, if advertised
 *   declared_surfaces  JSON array of the surface keys the peer advertises
 *   maturity_grade     headline grade from /open-data/maturity, if any
 *   capabilities_json  the cached federation block + maturity summary
 *   last_probed_at     now
 *
 * Fail-soft by construction: a dead, blocked or non-compliant peer is RECORDED,
 * never fatal. Zero peers -> an empty summary, no exception. All peer HTTP goes
 * through FederationClient (never a raw curl), so the SSRF guard, timeouts,
 * cache and rate-limit all apply.
 *
 * Read-mostly: the only writes are to the discovery-cache columns on
 * federation_peer (added idempotently by install_governance.sql). It does not
 * mutate catalogue data.
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PeerDiscoveryService
{
    /** Surface keys the Federation Query Protocol may advertise. */
    public const KNOWN_SURFACES = ['graph', 'endangered', 'search', 'exhibition'];

    public function __construct(private FederationClient $client)
    {
    }

    /**
     * Crawl every discoverable peer and record the outcome on each peer row.
     *
     * @param  bool  $enabledOnly  when true, only probe federation_enabled peers
     * @return array<string,mixed>  summary: {probed, ok, unreachable, non_compliant, results[]}
     */
    public function discoverAll(bool $enabledOnly = false): array
    {
        $summary = [
            'probed' => 0,
            'ok' => 0,
            'unreachable' => 0,
            'non_compliant' => 0,
            'results' => [],
        ];

        $peers = $this->discoverablePeers($enabledOnly);
        if ($peers->isEmpty()) {
            return $summary;
        }

        // Build one fetchMany spec set for the protocol documents, keyed by
        // peer id, then a second pass for maturity. FederationClient handles
        // the SSRF guard, the parallel fetch, the cache and the rate limit.
        $protocolSpecs = [];
        $maturitySpecs = [];
        foreach ($peers as $peer) {
            $base = rtrim((string) $peer->base_url, '/');
            $protocolSpecs[$peer->id] = [
                'url' => $base.'/open-data/protocol.json',
                'base_url' => $base,
                'cache_key' => 'fed.discover.protocol.'.md5($base),
                'rate_key' => 'fed.discover.rate.'.md5($base),
            ];
            $maturitySpecs[$peer->id] = [
                'url' => $base.'/open-data/maturity.json',
                'base_url' => $base,
                'cache_key' => 'fed.discover.maturity.'.md5($base),
                // no rate_key: the protocol fetch already armed the per-peer
                // window; maturity is best-effort and skipped if rate-limited
                // by reusing the same base, which is fine (it is optional).
            ];
        }

        $client = $this->client
            ->withMaxPeers(max(count($protocolSpecs), 25))
            ->withTimeouts(5000, 2000);

        $protocolResults = $client->fetchMany($protocolSpecs);
        $maturityResults = $client->fetchMany($maturitySpecs);

        foreach ($peers as $peer) {
            $proto = $protocolResults[$peer->id] ?? null;
            $mat = $maturityResults[$peer->id] ?? null;

            $record = $this->evaluate($proto, $mat);
            $this->persist((int) $peer->id, $record);

            $summary['probed']++;
            $summary[$record['discovery_status']] = ($summary[$record['discovery_status']] ?? 0) + 1;
            $summary['results'][] = [
                'id' => (int) $peer->id,
                'name' => (string) $peer->name,
                'base_url' => (string) $peer->base_url,
                'status' => $record['discovery_status'],
                'protocol_version' => $record['protocol_version'],
                'surfaces' => $record['declared_surfaces'],
                'maturity' => $record['maturity_grade'],
            ];
        }

        return $summary;
    }

    /**
     * Peers eligible for a probe: a real http(s) base_url (the self-peer / OAI
     * placeholders use '-' and are skipped). Optionally only federation_enabled.
     */
    protected function discoverablePeers(bool $enabledOnly): \Illuminate\Support\Collection
    {
        if (! $this->tableReady('federation_peer')) {
            return collect();
        }

        try {
            $q = DB::table('federation_peer')
                ->whereNotNull('base_url')
                ->where('base_url', '!=', '')
                ->where('base_url', '!=', '-');

            if ($enabledOnly && $this->columnReady('federation_peer', 'federation_enabled')) {
                $q->where('federation_enabled', 1);
            }

            return $q->orderBy('id')->limit(100)->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Turn the two raw fetch results into the discovery-cache record.
     *
     * @param  array<string,mixed>|null  $proto
     * @param  array<string,mixed>|null  $maturity
     * @return array<string,mixed>
     */
    protected function evaluate(?array $proto, ?array $maturity): array
    {
        $record = [
            'discovery_status' => 'unreachable',
            'protocol_version' => null,
            'declared_surfaces' => null,
            'maturity_grade' => null,
            'capabilities_json' => null,
        ];

        // Unreachable: no successful protocol fetch (error / skipped / no body).
        if (! is_array($proto) || ($proto['status'] ?? '') !== 'success' || ! is_string($proto['body'] ?? null)) {
            return $record;
        }

        $doc = json_decode((string) $proto['body'], true);
        if (! is_array($doc)) {
            // Reachable but not parseable JSON -> non-compliant.
            $record['discovery_status'] = 'non_compliant';

            return $record;
        }

        $federation = $doc['federation'] ?? null;
        if (! is_array($federation) || empty($federation['surfaces']) || ! is_array($federation['surfaces'])) {
            // Reachable, valid JSON, but no federation block -> non-compliant.
            $record['discovery_status'] = 'non_compliant';

            return $record;
        }

        // Compliant.
        $surfaces = array_values(array_intersect(
            self::KNOWN_SURFACES,
            array_keys($federation['surfaces'])
        ));

        $maturityGrade = $this->extractMaturityGrade($maturity);

        $record['discovery_status'] = 'ok';
        $record['protocol_version'] = isset($federation['protocol_version'])
            ? (string) $federation['protocol_version']
            : null;
        $record['declared_surfaces'] = $surfaces;
        $record['maturity_grade'] = $maturityGrade;
        $record['capabilities_json'] = [
            'federation' => $federation,
            'maturity' => $maturityGrade,
            'probed_at' => now()->toIso8601String(),
        ];

        return $record;
    }

    /**
     * Best-effort headline grade from a /open-data/maturity.json fetch. The
     * scorecard shape varies by version, so probe a few common keys; absent ->
     * null (never fatal).
     *
     * @param  array<string,mixed>|null  $maturity
     */
    protected function extractMaturityGrade(?array $maturity): ?string
    {
        if (! is_array($maturity) || ($maturity['status'] ?? '') !== 'success' || ! is_string($maturity['body'] ?? null)) {
            return null;
        }

        $doc = json_decode((string) $maturity['body'], true);
        if (! is_array($doc)) {
            return null;
        }

        foreach (['grade', 'stars', 'score', 'rating', 'level'] as $key) {
            if (isset($doc[$key]) && (is_string($doc[$key]) || is_numeric($doc[$key]))) {
                return (string) $doc[$key];
            }
        }

        // Nested scorecard {scorecard:{grade}} / {maturity:{stars}}.
        foreach (['scorecard', 'maturity', 'summary'] as $wrap) {
            if (isset($doc[$wrap]) && is_array($doc[$wrap])) {
                foreach (['grade', 'stars', 'score', 'rating', 'level'] as $key) {
                    if (isset($doc[$wrap][$key]) && (is_string($doc[$wrap][$key]) || is_numeric($doc[$wrap][$key]))) {
                        return (string) $doc[$wrap][$key];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Write the discovery-cache record back onto the peer row. Fail-soft.
     *
     * @param  array<string,mixed>  $record
     */
    protected function persist(int $peerId, array $record): void
    {
        if (! $this->columnReady('federation_peer', 'discovery_status')) {
            return;
        }

        try {
            DB::table('federation_peer')->where('id', $peerId)->update([
                'discovery_status' => $record['discovery_status'],
                'protocol_version' => $record['protocol_version'],
                'declared_surfaces' => $record['declared_surfaces'] !== null
                    ? json_encode($record['declared_surfaces'])
                    : null,
                'maturity_grade' => $record['maturity_grade'],
                'capabilities_json' => $record['capabilities_json'] !== null
                    ? json_encode($record['capabilities_json'])
                    : null,
                'last_probed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never fatal: a probe outcome that cannot be persisted is dropped.
        }
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    protected function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function columnReady(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
