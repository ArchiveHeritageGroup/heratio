<?php

/**
 * FixityService - Heratio ahg-core
 *
 * heratio#1244 (fixity slice). The actionable "Integrity" functional area of the
 * NDSA Levels of Digital Preservation: it both REPORTS fixity coverage and
 * VERIFIES a bounded batch of digital objects against their stored checksum
 * baseline.
 *
 *  - coverage(): cheap, read-only aggregate over digital_object - how many objects
 *    exist, how many carry a stored checksum + algorithm (a verifiable baseline),
 *    how many have never been verified, plus a roll-up of the most recent sweep's
 *    results (match / mismatch / missing_file / ...). Never throws; a missing
 *    table yields an honest empty report rather than a 500.
 *
 *  - verifyBatch(): re-computes the checksum of each file on disk and compares it
 *    to the stored baseline (digital_object.checksum + checksum_type), writing one
 *    append-only row per object into core_fixity_check_log. It is:
 *      * BOUNDED   - default 100 objects, hard-capped (MAX_LIMIT) so an operator
 *                    can never launch an unbounded all-files hash from a web page.
 *      * RESILIENT - a missing / unreadable file becomes a missing_file / error
 *                    row; it never throws out of the loop.
 *      * SIZE-AWARE - files above MAX_BYTES are skipped (skipped_oversize) so a
 *                    single huge master cannot make the sweep run unreasonably long.
 *
 * READ-ONLY contract: the service reads digital_object only; the ONLY writes it
 * performs anywhere are INSERTs into the NEW core_fixity_check_log table. It never
 * ALTERs an existing table, never touches AtoM base tables, and makes no AI calls.
 * File paths are resolved exclusively through config('heratio.uploads_path') /
 * config('heratio.storage_path') - never hardcoded - and every candidate path is
 * traversal-guarded so a crafted digital_object.path can never escape the storage
 * root.
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
    /** The append-only fixity log table this service owns. */
    public const LOG_TABLE = 'core_fixity_check_log';

    /** Hard cap on how many objects a single verify run may hash. */
    public const MAX_LIMIT = 1000;

    /** Default batch size when no --limit is given. */
    public const DEFAULT_LIMIT = 100;

    /**
     * Files larger than this (bytes) are skipped + logged rather than hashed, so a
     * single huge master cannot make a bounded sweep run unreasonably long. Default
     * 4 GiB; operator-tunable via config('heratio.fixity.max_bytes') if ever needed.
     */
    public const MAX_BYTES = 4294967296;

    /**
     * Coverage report - cheap read-only aggregate, never throws.
     *
     * @return array{
     *   total:int, with_baseline:int, without_baseline:int, never_verified:int,
     *   algorithms:array<string,int>, last_sweep:?array, recent:array<int,array>,
     *   results:array<string,int>, generated_at:string, available:bool
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

            // "Never verified" = has a baseline but no row in the fixity log yet.
            if (Schema::hasTable(self::LOG_TABLE)) {
                $verifiedIds = DB::table(self::LOG_TABLE)
                    ->select('digital_object_id')
                    ->distinct();
                $report['never_verified'] = (clone $base)
                    ->whereNotNull('checksum')->where('checksum', '<>', '')
                    ->whereNotIn('id', $verifiedIds)
                    ->count();

                // Result roll-up over the LATEST check per object (so re-checks do
                // not double-count). MySQL: pick the max-id row per digital_object.
                $latest = DB::table(self::LOG_TABLE)
                    ->select('digital_object_id', DB::raw('MAX(id) as max_id'))
                    ->groupBy('digital_object_id');

                $results = DB::table(self::LOG_TABLE.' as l')
                    ->joinSub($latest, 'lt', function ($j) {
                        $j->on('l.id', '=', 'lt.max_id');
                    })
                    ->select('l.result', DB::raw('COUNT(*) as n'))
                    ->groupBy('l.result')
                    ->get();
                foreach ($results as $r) {
                    $report['results'][(string) $r->result] = (int) $r->n;
                }

                // Recent individual checks (newest first).
                $report['recent'] = DB::table(self::LOG_TABLE)
                    ->orderByDesc('id')
                    ->limit(max(1, min(100, $recentLimit)))
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();

                // Last-sweep summary: the most recent checked_at minute, with its
                // per-result counts. Cheap and good enough for the dashboard header.
                $lastAt = DB::table(self::LOG_TABLE)->max('checked_at');
                if ($lastAt) {
                    $sweepRows = DB::table(self::LOG_TABLE)
                        ->where('checked_at', $lastAt)
                        ->select('result', DB::raw('COUNT(*) as n'))
                        ->groupBy('result')
                        ->get();
                    $counts = [];
                    $total = 0;
                    foreach ($sweepRows as $r) {
                        $counts[(string) $r->result] = (int) $r->n;
                        $total += (int) $r->n;
                    }
                    $report['last_sweep'] = [
                        'checked_at' => (string) $lastAt,
                        'counts'     => $counts,
                        'total'      => $total,
                    ];
                }
            } else {
                // No log table yet - everything with a baseline is unverified.
                $report['never_verified'] = $report['with_baseline'];
            }
        } catch (Throwable $e) {
            Log::warning('[ahg-core] FixityService::coverage failed: '.$e->getMessage());
        }

        return $report;
    }

    /**
     * Verify a bounded batch of digital objects against their stored checksum.
     *
     * Selection order: objects with a baseline that have NEVER been verified first
     * (so a repeated sweep makes steady forward progress), then by id. Each result
     * is logged to core_fixity_check_log. The method never throws; per-object
     * failures become missing_file / error / skipped_oversize rows.
     *
     * @return array{
     *   limit:int, checked:int, match:int, mismatch:int, missing_file:int,
     *   no_baseline:int, skipped_oversize:int, error:int, results:array<int,array>,
     *   logged:bool, available:bool, generated_at:string
     * }
     */
    public function verifyBatch(int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = $this->boundLimit($limit);

        $summary = [
            'limit'            => $limit,
            'checked'          => 0,
            'match'            => 0,
            'mismatch'         => 0,
            'missing_file'     => 0,
            'no_baseline'      => 0,
            'skipped_oversize' => 0,
            'error'            => 0,
            'results'          => [],
            'logged'           => false,
            'available'        => false,
            'generated_at'     => now()->toDateTimeString(),
        ];

        try {
            if (! Schema::hasTable('digital_object')) {
                return $summary;
            }
            $summary['available'] = true;

            $canLog = Schema::hasTable(self::LOG_TABLE);
            $summary['logged'] = $canLog;

            $verifiedIds = $canLog
                ? DB::table(self::LOG_TABLE)->select('digital_object_id')->distinct()
                : null;

            $query = DB::table('digital_object')
                ->whereNotNull('path')
                ->where('path', '<>', '')
                ->where('path', 'not like', 'http://%')
                ->where('path', 'not like', 'https://%')
                ->whereNotNull('checksum')->where('checksum', '<>', '')
                ->whereNotNull('checksum_type')->where('checksum_type', '<>', '');

            if ($verifiedIds !== null) {
                // Prefer never-verified rows first; fall back to oldest-checked.
                $query->orderByRaw('CASE WHEN id IN ('.
                    'SELECT digital_object_id FROM '.self::LOG_TABLE.
                    ') THEN 1 ELSE 0 END ASC');
            }
            $rows = $query->orderBy('id')->limit($limit)->get();

            foreach ($rows as $do) {
                $result = $this->verifyOne($do);
                $summary['checked']++;
                $key = $result['result'];
                if (isset($summary[$key])) {
                    $summary[$key]++;
                }
                $summary['results'][] = $result;

                if ($canLog) {
                    $this->log($result);
                }
            }
        } catch (Throwable $e) {
            Log::warning('[ahg-core] FixityService::verifyBatch failed: '.$e->getMessage());
        }

        return $summary;
    }

    /**
     * Verify ONE digital object. Pure - it never writes; it returns a structured
     * result the caller logs. Never throws.
     *
     * @return array{digital_object_id:int, expected_checksum:?string,
     *   expected_algo:?string, computed_checksum:?string, result:string,
     *   byte_size:?int, detail:string, checked_at:string}
     */
    public function verifyOne(object $do): array
    {
        $now = now()->toDateTimeString();
        $out = [
            'digital_object_id' => (int) ($do->id ?? 0),
            'expected_checksum' => isset($do->checksum) ? (string) $do->checksum : null,
            'expected_algo'     => isset($do->checksum_type) ? (string) $do->checksum_type : null,
            'computed_checksum' => null,
            'result'            => 'error',
            'byte_size'         => null,
            'detail'            => '',
            'checked_at'        => $now,
        ];

        try {
            $expected = (string) ($do->checksum ?? '');
            $algo     = (string) ($do->checksum_type ?? '');

            if ($expected === '' || $algo === '') {
                $out['result'] = 'no_baseline';
                $out['detail'] = 'No stored checksum/algorithm to verify against.';

                return $out;
            }

            $file = $this->resolveFile($do);
            if ($file === null) {
                $out['result'] = 'missing_file';
                $out['detail'] = 'File not found on disk for any resolved storage path.';

                return $out;
            }

            $size = @filesize($file);
            if ($size !== false) {
                $out['byte_size'] = (int) $size;
            }

            $cap = (int) config('heratio.fixity.max_bytes', self::MAX_BYTES);
            if ($cap > 0 && $size !== false && $size > $cap) {
                $out['result'] = 'skipped_oversize';
                $out['detail'] = sprintf(
                    'File %s bytes exceeds the %s-byte sweep cap; skipped (verify out-of-band).',
                    number_format((int) $size), number_format($cap)
                );

                return $out;
            }

            $php = $this->normaliseAlgo($algo);
            if (! in_array($php, hash_algos(), true)) {
                $out['result'] = 'error';
                $out['detail'] = 'Unsupported hash algorithm: '.$algo;

                return $out;
            }

            $computed = @hash_file($php, $file);
            if ($computed === false || $computed === null) {
                $out['result'] = 'error';
                $out['detail'] = 'File present but unreadable (hash_file failed).';

                return $out;
            }

            $out['computed_checksum'] = $computed;
            if (hash_equals(strtolower($expected), strtolower((string) $computed))) {
                $out['result'] = 'match';
                $out['detail'] = 'Computed checksum matches the stored baseline.';
            } else {
                $out['result'] = 'mismatch';
                $out['detail'] = 'Computed checksum DIFFERS from the stored baseline.';
            }
        } catch (Throwable $e) {
            $out['result'] = 'error';
            $out['detail'] = 'Verification error: '.substr($e->getMessage(), 0, 180);
        }

        // Trim detail to the column width.
        $out['detail'] = substr($out['detail'], 0, 255);

        return $out;
    }

    /**
     * Resolve the on-disk path of a digital object's file, trying the known
     * storage conventions and returning the FIRST that exists (and is a real file
     * inside the storage root). Returns null when nothing resolves. Read-only.
     *
     * Conventions seen in the data:
     *   - digital_object.path is a web-relative DIRECTORY ending in '/', with the
     *     filename in digital_object.name -> base + path + name
     *   - some legacy rows store the full file in .path directly -> base + path
     *   - .path may begin with a leading '/uploads/' that is NOT present under the
     *     resolved uploads root -> also try with that prefix stripped
     */
    public function resolveFile(object $do): ?string
    {
        $base = (string) (config('heratio.uploads_path')
            ?: config('heratio.storage_path')
            ?: '');
        if ($base === '') {
            return null;
        }
        $base = rtrim($base, '/');
        $realBase = realpath($base) ?: $base;

        $rel  = (string) ($do->path ?? '');
        $name = (string) ($do->name ?? '');
        $relNoUploads = preg_replace('#^/?uploads/#', '', $rel) ?? $rel;

        $candidates = [];
        // 1) path-as-directory + name
        $candidates[] = $base.'/'.ltrim($rel, '/').$name;
        // 2) path-as-directory (with /uploads stripped) + name
        $candidates[] = $base.'/'.ltrim($relNoUploads, '/').$name;
        // 3) path-as-full-file (PreservationService convention)
        $candidates[] = $base.'/'.ltrim($rel, '/');
        // 4) path-as-full-file with /uploads stripped
        $candidates[] = $base.'/'.ltrim($relNoUploads, '/');

        foreach ($candidates as $candidate) {
            // Normalise duplicate slashes that creep in when name/path are empty.
            $candidate = preg_replace('#(?<!:)//+#', '/', $candidate);
            if ($candidate === '' || str_ends_with($candidate, '/')) {
                continue; // a directory, not a file
            }
            if (! @is_file($candidate)) {
                continue;
            }
            // Traversal guard: the resolved real path MUST sit inside the storage
            // root. Defends against a crafted path containing '../'.
            $real = realpath($candidate);
            if ($real === false) {
                continue;
            }
            if (str_starts_with($real, $realBase.'/') || $real === $realBase) {
                return $real;
            }
        }

        return null;
    }

    /** Insert one fixity result row. Best-effort; never throws. */
    protected function log(array $result): void
    {
        try {
            DB::table(self::LOG_TABLE)->insert([
                'digital_object_id' => (int) $result['digital_object_id'],
                'expected_checksum' => $result['expected_checksum'] !== null
                    ? substr((string) $result['expected_checksum'], 0, 255) : null,
                'expected_algo'     => $result['expected_algo'] !== null
                    ? substr((string) $result['expected_algo'], 0, 50) : null,
                'computed_checksum' => $result['computed_checksum'] !== null
                    ? substr((string) $result['computed_checksum'], 0, 255) : null,
                'result'            => substr((string) $result['result'], 0, 40),
                'byte_size'         => $result['byte_size'],
                'detail'            => substr((string) $result['detail'], 0, 255),
                'checked_at'        => $result['checked_at'],
            ]);
        } catch (Throwable $e) {
            Log::warning('[ahg-core] FixityService log insert failed: '.$e->getMessage());
        }
    }

    /** Clamp a requested limit into [1, MAX_LIMIT]. */
    public function boundLimit(int $limit): int
    {
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        return max(1, min(self::MAX_LIMIT, $limit));
    }

    /** Normalise a stored algorithm name (e.g. "SHA-256") to a PHP hash id. */
    protected function normaliseAlgo(string $algo): string
    {
        return str_replace('-', '', strtolower(trim($algo)));
    }
}
