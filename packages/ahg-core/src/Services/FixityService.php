<?php

/**
 * FixityService - Heratio ahg-core
 *
 * heratio#1244 (fixity slice), CONSOLIDATED. The actionable "Integrity" functional
 * area of the NDSA Levels - a cheap, READ-ONLY coverage report.
 *
 * Consolidation note (heratio#1244 / architecture review): the operational fixity
 * ENGINE - the thing that re-hashes files and records results - lives in
 * ahg-preservation (FixityScanService, the preservation_fixity_check store, the
 * /admin/preservation/fixity-log surface and ahg:preservation-fixity-run). This
 * service no longer keeps its own parallel store or sweep command; it is a thin
 * read-only DASHBOARD over the SAME canonical store, so there is one source of
 * truth. It reads:
 *   - digital_object   - the checksum BASELINE (how many objects can be verified)
 *   - preservation_fixity_check - the verification RESULTS (the real engine's log)
 *
 * Never throws: a missing table yields an honest empty report rather than a 500.
 * No writes, no ALTER, no AI.
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
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FixityService
{
    /** The CANONICAL fixity results store (owned by ahg-preservation). Read-only here. */
    public const RESULTS_TABLE = 'preservation_fixity_check';

    /**
     * Coverage report - cheap read-only aggregate over the baseline (digital_object)
     * and the canonical results store (preservation_fixity_check). Never throws.
     *
     * @return array{
     *   total:int, with_baseline:int, without_baseline:int, never_verified:int,
     *   algorithms:array<string,int>, last_sweep:?array, recent:array<int,array>,
     *   results:array<string,int>, generated_at:string, available:bool,
     *   results_store:bool
     * }
     */
    public function coverage(int $recentLimit = 20): array
    {
        $report = [
            'total'            => 0,
            'with_baseline'    => 0,
            'without_baseline' => 0,
            'never_verified'   => 0,
            'algorithms'       => [],
            'last_sweep'       => null,
            'recent'           => [],
            'results'          => [],
            'generated_at'     => now()->toDateTimeString(),
            'available'        => false,
            'results_store'    => false,
        ];

        try {
            if (! Schema::hasTable('digital_object')) {
                return $report;
            }
            $report['available'] = true;

            // Only count rows that point at a real local file (path set, not a
            // remote http(s) reference) - those are the ones fixity can verify.
            $base = DB::table('digital_object')
                ->whereNotNull('path')
                ->where('path', '<>', '')
                ->where('path', 'not like', 'http://%')
                ->where('path', 'not like', 'https://%');

            $report['total'] = (clone $base)->count();

            $report['with_baseline'] = (clone $base)
                ->whereNotNull('checksum')->where('checksum', '<>', '')
                ->whereNotNull('checksum_type')->where('checksum_type', '<>', '')
                ->count();

            $report['without_baseline'] = max(0, $report['total'] - $report['with_baseline']);

            // Algorithm breakdown over the rows that carry a baseline.
            $algoRows = (clone $base)
                ->whereNotNull('checksum_type')->where('checksum_type', '<>', '')
                ->select('checksum_type', DB::raw('COUNT(*) as n'))
                ->groupBy('checksum_type')
                ->orderByDesc('n')
                ->get();
            foreach ($algoRows as $r) {
                $report['algorithms'][(string) $r->checksum_type] = (int) $r->n;
            }

            // Verification results from the CANONICAL ahg-preservation store.
            if (Schema::hasTable(self::RESULTS_TABLE)) {
                $report['results_store'] = true;
                $t = self::RESULTS_TABLE;

                $verifiedIds = DB::table($t)->select('digital_object_id')->distinct();
                $report['never_verified'] = (clone $base)
                    ->whereNotNull('checksum')->where('checksum', '<>', '')
                    ->whereNotIn('id', $verifiedIds)
                    ->count();

                // Result roll-up over the LATEST check per object so re-checks do
                // not double-count. (preservation_fixity_check.status is the result.)
                $latest = DB::table($t)
                    ->select('digital_object_id', DB::raw('MAX(id) as max_id'))
                    ->groupBy('digital_object_id');
                $results = DB::table($t.' as l')
                    ->joinSub($latest, 'lt', fn ($j) => $j->on('l.id', '=', 'lt.max_id'))
                    ->select('l.status', DB::raw('COUNT(*) as n'))
                    ->groupBy('l.status')
                    ->get();
                foreach ($results as $r) {
                    $report['results'][(string) $r->status] = (int) $r->n;
                }

                // Recent individual checks (newest first), mapped to the report shape.
                $report['recent'] = DB::table($t)
                    ->orderByDesc('id')
                    ->limit(max(1, min(100, $recentLimit)))
                    ->get()
                    ->map(fn ($r) => [
                        'digital_object_id' => (int) ($r->digital_object_id ?? 0),
                        'result'            => (string) ($r->status ?? ''),
                        'expected_algo'     => (string) ($r->algorithm ?? ''),
                        'expected_checksum' => (string) ($r->expected_value ?? ''),
                        'computed_checksum' => (string) ($r->actual_value ?? ''),
                        'detail'            => (string) ($r->error_message ?? ''),
                        'byte_size'         => null,
                        'checked_at'        => (string) ($r->checked_at ?? ''),
                    ])
                    ->all();

                // Last-run summary: the most recent checked_at value + its results.
                $lastAt = DB::table($t)->max('checked_at');
                if ($lastAt) {
                    $sweepRows = DB::table($t)
                        ->where('checked_at', $lastAt)
                        ->select('status', DB::raw('COUNT(*) as n'))
                        ->groupBy('status')
                        ->get();
                    $counts = [];
                    $total = 0;
                    foreach ($sweepRows as $r) {
                        $counts[(string) $r->status] = (int) $r->n;
                        $total += (int) $r->n;
                    }
                    $report['last_sweep'] = [
                        'checked_at' => (string) $lastAt,
                        'counts'     => $counts,
                        'total'      => $total,
                    ];
                }
            } else {
                // No canonical results store - everything with a baseline is unverified.
                $report['never_verified'] = $report['with_baseline'];
            }
        } catch (Throwable $e) {
            Log::warning('[ahg-core] FixityService::coverage failed: '.$e->getMessage());
        }

        return $report;
    }
}
