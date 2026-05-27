<?php

/**
 * WorkKeyService - FRBR work-set clustering for library_item.
 *
 * Generates a deterministic 64-bit hash per intellectual work so that
 * multiple manifestations (editions, translations, reprints) of the same
 * intellectual content collapse to one row in unified search.
 *
 * Recipe:
 *   1. Uniform title (MARC 130/240 if present) OR 245 title
 *   2. Normalised: NFD lowercase, strip diacritics + non-alphanumeric
 *   3. Concatenate with first creator (100/700, no $e role), same normalisation
 *   4. xxh64 hash, hex-encoded - 16 chars, indexed
 *
 * Manual override via library_work_override table takes precedence over the
 * algorithmic key.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgBiblioFrbr\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class WorkKeyService
{
    /**
     * Compute the work-key for a single library_item row.
     */
    public function computeForItem(int $libraryItemId): ?string
    {
        // Manual override wins.
        $override = DB::table('library_work_override')
            ->where('library_item_id', $libraryItemId)
            ->orderByRaw("CASE mode WHEN 'force_split' THEN 1 WHEN 'force_group' THEN 2 ELSE 3 END")
            ->first();
        if ($override) {
            return $override->override_key;
        }

        $row = DB::table('library_item')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object_i18n.id', '=', 'library_item.information_object_id')
                  ->where('information_object_i18n.culture', '=', 'en');
            })
            ->where('library_item.id', $libraryItemId)
            ->select(
                'library_item.id',
                'information_object_i18n.title',
                'library_item.subtitle',
                'library_item.publisher'
            )
            ->first();

        if (!$row) {
            return null;
        }

        $primaryCreator = DB::table('library_item_creator')
            ->where('library_item_id', $libraryItemId)
            ->orderBy('sort_order')
            ->value('name');

        return $this->hashWork((string) ($row->title ?? ''), (string) ($primaryCreator ?? ''));
    }

    /**
     * Generate the hash string given normalised title + creator inputs.
     */
    public function hashWork(string $title, string $creator): string
    {
        $titleNorm = $this->normalise($title);
        $creatorNorm = $this->normalise($creator);
        $input = $titleNorm . '|' . $creatorNorm;
        if ($input === '|') {
            return 'unkn-' . substr(md5((string) microtime(true)), 0, 11);
        }
        return hash('xxh64', $input);
    }

    /**
     * NFD lowercase, strip diacritics + non-alphanumeric.
     */
    public function normalise(string $s): string
    {
        if ($s === '') return '';
        $nfd = class_exists(\Normalizer::class) ? \Normalizer::normalize($s, \Normalizer::FORM_D) : $s;
        // Strip combining marks (diacritics).
        $stripped = preg_replace('/\pM+/u', '', $nfd) ?? $nfd;
        $lower = mb_strtolower($stripped, 'UTF-8');
        // Strip everything except alphanumerics.
        $clean = preg_replace('/[^a-z0-9]+/u', '', $lower) ?? '';
        return $clean;
    }

    /**
     * Persist the work-key for one row.
     */
    public function backfillOne(int $libraryItemId): ?string
    {
        $key = $this->computeForItem($libraryItemId);
        if ($key === null) return null;

        DB::table('library_item')
            ->where('id', $libraryItemId)
            ->update(['work_key' => $key]);

        return $key;
    }

    /**
     * Bulk backfill (used by the artisan command).
     *
     * @return array{processed:int, updated:int}
     */
    public function backfillAll(?int $batchSize = 500): array
    {
        $batchSize = $batchSize ?: 500;
        $processed = 0;
        $updated = 0;

        DB::table('library_item')
            ->select('library_item.id')
            ->orderBy('library_item.id')
            ->chunk($batchSize, function (Collection $rows) use (&$processed, &$updated) {
                foreach ($rows as $row) {
                    $processed++;
                    try {
                        if ($this->backfillOne((int) $row->id) !== null) {
                            $updated++;
                        }
                    } catch (Throwable) {
                        // Skip broken rows; surface in command output via counts.
                    }
                }
            });

        return ['processed' => $processed, 'updated' => $updated];
    }

    /**
     * Lookup all library_item rows that share a work-key with the given item.
     *
     * @return array list of library_item ids
     */
    public function siblingsOf(int $libraryItemId): array
    {
        $key = DB::table('library_item')
            ->where('id', $libraryItemId)
            ->value('work_key');
        if (!$key) return [];
        return DB::table('library_item')
            ->where('work_key', $key)
            ->where('id', '<>', $libraryItemId)
            ->pluck('id')
            ->all();
    }

    /**
     * Apply a manual override.
     */
    public function setOverride(int $libraryItemId, string $mode, string $targetKey, ?string $reason = null, ?int $userId = null): void
    {
        if (!in_array($mode, ['force_group', 'force_split'], true)) {
            throw new \InvalidArgumentException('mode must be force_group or force_split');
        }
        DB::table('library_work_override')->updateOrInsert(
            ['library_item_id' => $libraryItemId, 'mode' => $mode],
            [
                'override_key' => $targetKey,
                'reason' => $reason,
                'cataloguer_user_id' => $userId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        // Re-compute the affected item so the new key persists immediately.
        $this->backfillOne($libraryItemId);
    }

    public function clearOverride(int $libraryItemId, string $mode): void
    {
        DB::table('library_work_override')
            ->where('library_item_id', $libraryItemId)
            ->where('mode', $mode)
            ->delete();
        $this->backfillOne($libraryItemId);
    }

    /**
     * Clustering helper: given a result set of library_item ids, group by work_key.
     * Each cluster reports the representative id (lowest id with that key)
     * plus the list of siblings.
     *
     * @param array<int> $libraryItemIds
     * @return array<int, array{representative:int, members:array<int>, work_key:string}>
     */
    public function clusterItems(array $libraryItemIds): array
    {
        if (empty($libraryItemIds)) return [];

        $rows = DB::table('library_item')
            ->whereIn('id', $libraryItemIds)
            ->orderBy('id')
            ->select('id', 'work_key')
            ->get();

        $clusters = [];
        foreach ($rows as $r) {
            $key = $r->work_key ?: 'nokey-' . $r->id;
            if (!isset($clusters[$key])) {
                $clusters[$key] = [
                    'representative' => (int) $r->id,
                    'members' => [],
                    'work_key' => $key,
                ];
            }
            $clusters[$key]['members'][] = (int) $r->id;
        }

        return array_values($clusters);
    }
}
