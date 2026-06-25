<?php

/**
 * SuggestedConnectionsService - Service for Heratio
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

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Suggested Connections Service - North Star: generative scholarship (#1210).
 *
 * First slice of "AI finds connections no human spotted". Surfaces NON-OBVIOUS
 * links between catalogue records: pairs of information objects that share two
 * or more access points (subjects / places / genres via object_term_relation,
 * or an indirect bridge through the generic `relation` table) but are NOT
 * directly linked to each other. Candidates are ranked by shared-signal
 * strength, then the gateway LLM (LlmService::complete) writes a one-paragraph
 * hypothesis of WHY two records might be connected - grounded ONLY in their
 * titles and shared access-points, never invented facts.
 *
 * All AI dispatch goes through LlmService (the AHG gateway). No direct node
 * calls. The candidate computation is pure SQL over the existing graph and
 * costs nothing; only the explanation step touches the LLM, and only on
 * demand (caller decides whether to explain).
 */
class SuggestedConnectionsService
{
    /** Taxonomy ids that count as "access points" for the shared-signal score. */
    public const ACCESS_POINT_TAXONOMIES = [
        35, // subjects
        42, // places
        78, // genres / form
    ];

    /** Cache table for generated explanations (keyed by ordered pair). */
    private const CACHE_TABLE = 'ahg_suggested_connection';

    public function __construct(private LlmService $llm)
    {
    }

    // ─── Candidate discovery ────────────────────────────────────────────

    /**
     * Find candidate connections for a single seed record.
     *
     * Returns the records that share >= $minShared access points with the seed
     * but are NOT directly linked to it (neither side of the `relation` table,
     * and not an explicit object_term_relation peer). Ranked by shared count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function candidatesForObject(int $objectId, int $minShared = 2, int $limit = 25): array
    {
        $directly = $this->directlyLinkedIds($objectId);
        $directly[] = $objectId;

        $rows = DB::table('object_term_relation as a')
            ->join('object_term_relation as b', 'a.term_id', '=', 'b.term_id')
            ->join('term as t', 't.id', '=', 'a.term_id')
            ->where('a.object_id', $objectId)
            ->whereColumn('b.object_id', '!=', 'a.object_id')
            ->whereIn('t.taxonomy_id', self::ACCESS_POINT_TAXONOMIES)
            ->whereNotIn('b.object_id', $directly)
            ->groupBy('b.object_id')
            ->havingRaw('COUNT(DISTINCT a.term_id) >= ?', [$minShared])
            ->orderByRaw('COUNT(DISTINCT a.term_id) DESC')
            ->limit($limit)
            ->get(['b.object_id as object_id', DB::raw('COUNT(DISTINCT a.term_id) as shared')]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydratePair($objectId, (int) $row->object_id, (int) $row->shared);
        }

        return $out;
    }

    /**
     * Collection-wide scan: the strongest non-obvious pairs across the whole
     * catalogue (or a sub-tree when $ancestorId is supplied). Each pair is
     * returned once (object_id_1 < object_id_2) ranked by shared count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topPairs(int $minShared = 2, int $limit = 25, ?int $ancestorId = null): array
    {
        $query = DB::table('object_term_relation as a')
            ->join('object_term_relation as b', function ($j) {
                $j->on('a.term_id', '=', 'b.term_id')
                  ->whereColumn('a.object_id', '<', 'b.object_id');
            })
            ->join('term as t', 't.id', '=', 'a.term_id')
            ->whereIn('t.taxonomy_id', self::ACCESS_POINT_TAXONOMIES);

        if ($ancestorId !== null) {
            // Both objects must be under the ancestor (incl. self). Closure when
            // built (also catches null-lft orphans), else lft/rgt. heratio#1333.
            $descIds = app(\AhgCore\Services\HierarchyQueryService::class)
                ->descendantIds('information_object', $ancestorId, true);
            if (! empty($descIds)) {
                $query->whereIn('a.object_id', $descIds)
                      ->whereIn('b.object_id', $descIds);
            }
        }

        $rows = $query
            ->groupBy('a.object_id', 'b.object_id')
            ->havingRaw('COUNT(DISTINCT a.term_id) >= ?', [$minShared])
            ->orderByRaw('COUNT(DISTINCT a.term_id) DESC')
            // Pull extra so we can drop directly-linked pairs and still fill the page.
            ->limit($limit * 4)
            ->get([
                'a.object_id as o1',
                'b.object_id as o2',
                DB::raw('COUNT(DISTINCT a.term_id) as shared'),
            ]);

        $linkedPairs = $this->directlyLinkedPairSet();

        $out = [];
        foreach ($rows as $row) {
            $key = $row->o1 . ':' . $row->o2;
            if (isset($linkedPairs[$key])) {
                continue; // already an obvious link - not interesting
            }
            $out[] = $this->hydratePair((int) $row->o1, (int) $row->o2, (int) $row->shared);
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    // ─── LLM explanation ─────────────────────────────────────────────────

    /**
     * Generate (or fetch cached) a one-paragraph hypothesis of why the two
     * records might be connected. Grounded ONLY in the supplied titles and the
     * shared access-point names; the prompt forbids invented facts.
     *
     * @param array<string, mixed> $pair Output of hydratePair().
     * @return array{success: bool, explanation?: string, cached?: bool, model?: string, error?: string}
     */
    public function explainPair(array $pair, bool $useCache = true): array
    {
        $id1 = (int) $pair['object_id_1'];
        $id2 = (int) $pair['object_id_2'];
        [$lo, $hi] = $id1 < $id2 ? [$id1, $id2] : [$id2, $id1];

        if ($useCache) {
            $cached = $this->cachedExplanation($lo, $hi);
            if ($cached !== null) {
                return ['success' => true, 'explanation' => $cached->explanation, 'cached' => true, 'model' => $cached->model];
            }
        }

        $shared = array_values(array_filter(array_map('strval', $pair['shared_terms'] ?? [])));
        $sharedList = $shared === [] ? '(none named)' : implode(', ', $shared);

        $title1 = trim((string) ($pair['title_1'] ?? '')) ?: '(untitled)';
        $title2 = trim((string) ($pair['title_2'] ?? '')) ?: '(untitled)';

        $system = 'You are an archival research assistant. You propose plausible, '
            . 'clearly-hedged hypotheses about why two catalogue records might be '
            . 'historically connected. You ground every statement ONLY in the record '
            . 'titles and shared access points provided. You NEVER invent names, dates, '
            . 'events, or facts that are not derivable from that input. If the only honest '
            . 'thing to say is that they share access points, say exactly that. Use '
            . 'tentative language (may, might, could, suggests). Plain hyphens only, no '
            . 'em-dashes. One paragraph, 2 to 4 sentences. No preamble, no headings.';

        $user = "Record A: \"{$title1}\"\n"
            . "Record B: \"{$title2}\"\n"
            . "Shared access points (subjects / places / genres): {$sharedList}\n\n"
            . 'These two records are NOT directly linked in the catalogue, yet they share '
            . 'the access points above. In one short paragraph, propose a hypothesis for '
            . 'why a researcher might find a connection between them worth investigating. '
            . 'Stay strictly within the titles and shared access points given above.';

        try {
            $text = $this->llm->complete($user, [
                'system_prompt'   => $system,
                'temperature'     => 0.3,
                'purpose'         => 'suggested_connection',
                'data_scope'      => 'internal',
                'context_sources' => array_values(array_filter([$title1, $title2, $sharedList])),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] suggested-connection explain failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (!is_string($text) || trim($text) === '') {
            return ['success' => false, 'error' => 'LLM returned no text'];
        }

        $text = trim($text);
        $model = $this->llm->getDefaultConfig()->model ?? 'unknown';

        $this->storeExplanation($lo, $hi, (int) ($pair['shared'] ?? 0), $sharedList, $text, (string) $model);

        return ['success' => true, 'explanation' => $text, 'cached' => false, 'model' => (string) $model];
    }

    // ─── Internals ───────────────────────────────────────────────────────

    /**
     * Assemble titles, slugs and shared-term names for a pair.
     *
     * @return array<string, mixed>
     */
    private function hydratePair(int $id1, int $id2, int $shared): array
    {
        $meta = $this->objectMeta([$id1, $id2]);

        return [
            'object_id_1'  => $id1,
            'object_id_2'  => $id2,
            'title_1'      => $meta[$id1]['title'] ?? null,
            'title_2'      => $meta[$id2]['title'] ?? null,
            'slug_1'       => $meta[$id1]['slug'] ?? null,
            'slug_2'       => $meta[$id2]['slug'] ?? null,
            'shared'       => $shared,
            'shared_terms' => $this->sharedTermNames($id1, $id2),
        ];
    }

    /**
     * Title + slug for a set of information-object ids, in the active locale
     * (falling back to any culture row when the locale has none).
     *
     * @param array<int> $ids
     * @return array<int, array{title: ?string, slug: ?string}>
     */
    private function objectMeta(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $locale = app()->getLocale();

        $rows = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', function ($j) use ($locale) {
                $j->on('i.id', '=', 'io.id')->where('i.culture', '=', $locale);
            })
            ->leftJoin('information_object_i18n as ifb', 'ifb.id', '=', 'io.id')
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->whereIn('io.id', $ids)
            ->groupBy('io.id')
            ->get([
                'io.id',
                DB::raw('COALESCE(MAX(i.title), MAX(ifb.title)) as title'),
                DB::raw('MAX(s.slug) as slug'),
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = ['title' => $row->title, 'slug' => $row->slug];
        }

        return $out;
    }

    /**
     * Distinct names of the access-point terms two records share, in the
     * active locale.
     *
     * @return array<int, string>
     */
    private function sharedTermNames(int $id1, int $id2): array
    {
        $locale = app()->getLocale();

        return DB::table('object_term_relation as a')
            ->join('object_term_relation as b', 'a.term_id', '=', 'b.term_id')
            ->join('term as t', 't.id', '=', 'a.term_id')
            ->leftJoin('term_i18n as ti', function ($j) use ($locale) {
                $j->on('ti.id', '=', 't.id')->where('ti.culture', '=', $locale);
            })
            ->where('a.object_id', $id1)
            ->where('b.object_id', $id2)
            ->whereIn('t.taxonomy_id', self::ACCESS_POINT_TAXONOMIES)
            ->whereNotNull('ti.name')
            ->distinct()
            ->orderBy('ti.name')
            ->pluck('ti.name')
            ->all();
    }

    /**
     * Ids directly linked to $objectId: either side of the generic `relation`
     * table. These are the OBVIOUS links we deliberately exclude.
     *
     * @return array<int>
     */
    private function directlyLinkedIds(int $objectId): array
    {
        $a = DB::table('relation')->where('subject_id', $objectId)->pluck('object_id');
        $b = DB::table('relation')->where('object_id', $objectId)->pluck('subject_id');

        return $a->merge($b)->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * Set of all directly-linked ordered pairs ("lo:hi" => true) from the
     * generic `relation` table, for fast exclusion in the collection scan.
     *
     * @return array<string, bool>
     */
    private function directlyLinkedPairSet(): array
    {
        $set = [];
        DB::table('relation')
            ->select('subject_id', 'object_id')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$set) {
                foreach ($rows as $r) {
                    $lo = min((int) $r->subject_id, (int) $r->object_id);
                    $hi = max((int) $r->subject_id, (int) $r->object_id);
                    $set[$lo . ':' . $hi] = true;
                }
            });

        return $set;
    }

    private function cachedExplanation(int $lo, int $hi): ?object
    {
        try {
            return DB::table(self::CACHE_TABLE)
                ->where('object_id_1', $lo)
                ->where('object_id_2', $hi)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function storeExplanation(int $lo, int $hi, int $shared, string $sharedList, string $explanation, string $model): void
    {
        try {
            DB::table(self::CACHE_TABLE)->updateOrInsert(
                ['object_id_1' => $lo, 'object_id_2' => $hi],
                [
                    'shared_count' => $shared,
                    'shared_terms' => mb_substr($sharedList, 0, 2000),
                    'explanation'  => $explanation,
                    'model'        => mb_substr($model, 0, 100),
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] suggested-connection cache write failed: ' . $e->getMessage());
        }
    }
}
