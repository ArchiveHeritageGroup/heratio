<?php

/**
 * DocumentPriorService - Service for Heratio
 *
 * Task-4 fonds-level prior distribution of resolved places. For a given
 * information_object, walks the parent chain up to the topmost ancestor
 * (the fonds) and computes a frequency map of every term_id that has
 * already been LINKED (state = 'linked') as a PLACE in any mention under
 * that fonds.
 *
 * The result is cached in ahg_settings under the key
 *   authority_resolution.prior.<fonds_id>
 * with TTL 24h. Cache value is a JSON object:
 *   { "computed_at": "2026-05-19T12:34:56Z",
 *     "fonds_id": 903585,
 *     "io_count": 12,
 *     "distribution": { "901059": 7, "901897": 4, ... } }
 *
 * The PriorEvaluator wraps this and turns the distribution into a signal.
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

namespace AhgAuthorityResolution\Services\Evidence;

use Illuminate\Support\Facades\DB;

class DocumentPriorService
{
    private const PLACE_TYPES = ['GPE', 'PLACE', 'LOC'];

    private const CACHE_TTL_SECONDS = 86400; // 24h

    private const SETTING_PREFIX = 'authority_resolution.prior.';

    /**
     * Return the fonds-level prior for the IO that owns this mention.
     *
     * @return array{
     *   fonds_id: int|null,
     *   io_count: int,
     *   distribution: array<int,int>,
     *   cached: bool
     * }
     */
    public function priorFor(int $objectId): array
    {
        $fondsId = $this->findFondsId($objectId);
        if ($fondsId === null) {
            return [
                'fonds_id' => null,
                'io_count' => 0,
                'distribution' => [],
                'cached' => false,
            ];
        }

        $cached = $this->loadCached($fondsId);
        if ($cached !== null) {
            $cached['cached'] = true;

            return $cached;
        }

        $computed = $this->compute($fondsId);
        $this->saveCached($fondsId, $computed);
        $computed['cached'] = false;

        return $computed;
    }

    /**
     * Walk information_object.parent_id up to the topmost non-root ancestor.
     * Root in AtoM has id = 1, parent_id IS NULL. Fonds is the child of root,
     * or the IO itself if it already sits one level below root.
     */
    private function findFondsId(int $objectId): ?int
    {
        $maxDepth = 32;
        $currentId = $objectId;
        $parentId = null;

        for ($i = 0; $i < $maxDepth; $i++) {
            $row = DB::table('information_object')->where('id', $currentId)->first(['id', 'parent_id']);
            if (! $row) {
                return null;
            }
            $parentId = $row->parent_id !== null ? (int) $row->parent_id : null;
            if ($parentId === null || $parentId === 1) {
                return $currentId;
            }
            $currentId = $parentId;
        }

        return $currentId;
    }

    /**
     * @return array{fonds_id:int,io_count:int,distribution:array<int,int>}
     */
    private function compute(int $fondsId): array
    {
        // Find every IO under the fonds via MPTT lft/rgt range.
        $fonds = DB::table('information_object')->where('id', $fondsId)->first(['id', 'lft', 'rgt']);
        if (! $fonds) {
            return ['fonds_id' => $fondsId, 'io_count' => 0, 'distribution' => []];
        }

        $lft = (int) ($fonds->lft ?? 0);
        $rgt = (int) ($fonds->rgt ?? 0);

        if ($lft <= 0 || $rgt <= 0) {
            // Fall back to a direct parent_id walk (rare; happens if MPTT not rebuilt).
            $ioIds = $this->descendantsByParentWalk($fondsId);
        } else {
            $ioIds = DB::table('information_object')
                ->whereBetween('lft', [$lft, $rgt])
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        if (empty($ioIds)) {
            return ['fonds_id' => $fondsId, 'io_count' => 0, 'distribution' => []];
        }

        // Count linked PLACE mentions whose candidate_authority_id is the
        // resolved term, grouped by candidate_authority_id.
        $rows = DB::table('ahg_mention as m')
            ->join('ahg_mention_candidate as c', 'c.mention_id', '=', 'm.id')
            ->whereIn('m.object_id', $ioIds)
            ->where('m.state', 'linked')
            ->whereIn('m.entity_type', self::PLACE_TYPES)
            ->where('c.rank_position', 1)  // accepted resolution by convention
            ->whereNotNull('c.candidate_authority_id')
            ->where('c.candidate_source', 'mysql_term')
            ->groupBy('c.candidate_authority_id')
            ->selectRaw('c.candidate_authority_id AS term_id, COUNT(*) AS hit_count')
            ->get();

        $distribution = [];
        foreach ($rows as $r) {
            $distribution[(int) $r->term_id] = (int) $r->hit_count;
        }

        return [
            'fonds_id' => $fondsId,
            'io_count' => count($ioIds),
            'distribution' => $distribution,
        ];
    }

    /**
     * @return list<int>
     */
    private function descendantsByParentWalk(int $rootId): array
    {
        $out = [$rootId];
        $frontier = [$rootId];
        $hops = 0;
        while (! empty($frontier) && $hops < 32) {
            $children = DB::table('information_object')->whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = array_map('intval', $children);
            $out = array_merge($out, $frontier);
            $hops++;
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{fonds_id:int,io_count:int,distribution:array<int,int>}|null
     */
    private function loadCached(int $fondsId): ?array
    {
        $row = DB::table('ahg_settings')
            ->where('setting_key', self::SETTING_PREFIX.$fondsId)
            ->first();
        if (! $row) {
            return null;
        }
        $age = $row->updated_at ? (time() - strtotime((string) $row->updated_at)) : null;
        if ($age === null || $age > self::CACHE_TTL_SECONDS) {
            return null;
        }
        $decoded = json_decode((string) ($row->setting_value ?? ''), true);
        if (! is_array($decoded)) {
            return null;
        }
        $dist = isset($decoded['distribution']) && is_array($decoded['distribution']) ? $decoded['distribution'] : [];
        $distInt = [];
        foreach ($dist as $k => $v) {
            $distInt[(int) $k] = (int) $v;
        }

        return [
            'fonds_id' => (int) ($decoded['fonds_id'] ?? $fondsId),
            'io_count' => (int) ($decoded['io_count'] ?? 0),
            'distribution' => $distInt,
        ];
    }

    /**
     * @param  array{fonds_id:int,io_count:int,distribution:array<int,int>}  $payload
     */
    private function saveCached(int $fondsId, array $payload): void
    {
        $value = json_encode([
            'computed_at' => gmdate('c'),
            'fonds_id' => $payload['fonds_id'],
            'io_count' => $payload['io_count'],
            'distribution' => (object) $payload['distribution'],  // force {} when empty so JSON shape stays stable
        ], JSON_UNESCAPED_UNICODE);

        $key = self::SETTING_PREFIX.$fondsId;
        $existing = DB::table('ahg_settings')->where('setting_key', $key)->first(['id']);
        if ($existing) {
            DB::table('ahg_settings')->where('id', $existing->id)->update([
                'setting_value' => $value,
                'updated_at' => now(),
            ]);

            return;
        }
        DB::table('ahg_settings')->insert([
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_type' => 'json',
            'setting_group' => 'authority_resolution',
            'description' => "Cached fonds-level resolved-place prior distribution (fonds={$fondsId}). 24h TTL.",
            'is_sensitive' => 0,
            'is_locked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
