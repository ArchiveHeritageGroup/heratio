<?php

/**
 * CollectionMapService - the text side of the collection map (heratio#1567).
 *
 * The map itself is laid out in the browser from the explorer's own data (the
 * records and the subjects, places, names, genres and materials they share).
 * When explorer_map_embeddings is on, this adds what semantic search already
 * knows about each record's text: the stored vectors are read back from the
 * vector index (no embedding call, no AI call), reduced to a few dimensions
 * and sent as one compact string, so records with similar text can sit
 * together even where nobody catalogued a shared access point.
 *
 * Only the ids given are read, and those are the ids the viewer may already
 * see, so the map can never place a record the list would not show.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgApi\Services;

use AhgCore\Services\AhgSettingsService;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CollectionMapService
{
    /** Dimensions sent to the browser: enough to tell neighbours apart, small enough to send. */
    public const DIM = 32;

    /**
     * @param  list<int>  $ids  records already filtered to what the viewer may see
     * @return array{dim:int, ids:list<string>, q:string}|null null when switched off or nothing is stored
     */
    public static function textVectors(array $ids): ?array
    {
        if (count($ids) < 3 || ! class_exists(VectorSearchService::class)
            || AhgSettingsService::get('explorer_map_embeddings', '0') !== '1') {
            return null;
        }
        $key = 'explorer-map-vec:'.md5(implode(',', $ids));
        if (($hit = Cache::get($key)) !== null) {
            return $hit ?: null;
        }
        try {
            $vectors = app(VectorSearchService::class)->pointVectors($ids);
        } catch (\Throwable $e) {
            Log::info('collection map: vectors unavailable - '.$e->getMessage());

            return null; // not cached: try again on the next view
        }
        $out = count($vectors) >= 3 ? self::reduce($vectors) : [];
        Cache::put($key, $out, now()->addDay());

        return $out ?: null;
    }

    /**
     * Centre the vectors and project them onto an approximate top-DIM subspace
     * (a randomised range finder: Xt times a fixed random sign matrix, then
     * Gram-Schmidt), so distances keep the main structure of the text. Each
     * row is then unit length and quantised to signed bytes.
     *
     * ponytail: one pass, no power iteration and no SVD - good enough to find
     * neighbours; add a power iteration if maps look noisy on large collections.
     *
     * @param  array<int, list<float>>  $vectors
     * @return array{dim:int, ids:list<string>, q:string}
     */
    public static function reduce(array $vectors): array
    {
        $ids = array_keys($vectors);
        $X = array_values($vectors);
        $n = count($X);
        $d = count($X[0]);
        $k = min(self::DIM, $d, $n);

        $mean = array_fill(0, $d, 0.0);
        foreach ($X as $row) {
            for ($j = 0; $j < $d; $j++) {
                $mean[$j] += ($row[$j] ?? 0.0) / $n;
            }
        }
        foreach ($X as $i => $row) {
            for ($j = 0; $j < $d; $j++) {
                $X[$i][$j] = ($row[$j] ?? 0.0) - $mean[$j];
            }
        }

        // Y = Xt * Omega, Omega a fixed pseudo-random sign matrix (n x k): the same map every build.
        $Y = array_fill(0, $k, array_fill(0, $d, 0.0));
        $seed = 1567;
        foreach ($X as $row) {
            for ($c = 0; $c < $k; $c++) {
                $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
                $s = ($seed >> 16) & 1 ? 1.0 : -1.0;
                $col = &$Y[$c];
                for ($j = 0; $j < $d; $j++) {
                    $col[$j] += $s * $row[$j];
                }
                unset($col);
            }
        }

        // Orthonormal basis of Y's columns.
        $Q = [];
        foreach ($Y as $v) {
            foreach ($Q as $q) {
                $dot = 0.0;
                for ($j = 0; $j < $d; $j++) {
                    $dot += $v[$j] * $q[$j];
                }
                for ($j = 0; $j < $d; $j++) {
                    $v[$j] -= $dot * $q[$j];
                }
            }
            $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));
            if ($norm > 1e-9) {
                $Q[] = array_map(fn ($x) => $x / $norm, $v);
            }
        }

        $bytes = '';
        $k = count($Q);
        foreach ($X as $row) {
            $z = [];
            foreach ($Q as $q) {
                $dot = 0.0;
                for ($j = 0; $j < $d; $j++) {
                    $dot += $row[$j] * $q[$j];
                }
                $z[] = $dot;
            }
            $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $z))) ?: 1.0;
            $bytes .= pack('c*', ...array_map(fn ($x) => (int) round(127 * $x / $norm), $z));
        }

        return ['dim' => $k, 'ids' => array_map('strval', $ids), 'q' => base64_encode($bytes)];
    }
}
