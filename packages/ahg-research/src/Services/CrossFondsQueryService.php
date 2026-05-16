<?php

/**
 * CrossFondsQueryService - Service for Heratio
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

namespace AhgResearch\Services;

use AhgSearch\Services\ElasticsearchService;
use Illuminate\Support\Facades\DB;

/**
 * CrossFondsQueryService
 *
 * Runs a single user query across N selected fonds (top-level IO descriptions
 * or repositories) and returns ranked passages with citations. The fan-out is
 * one Elasticsearch query per fonds; results are merged by score and rebased
 * 1..N as a single ranked list.
 *
 * Existing semantic-search expansion can be applied via the optional
 * 'expand' option, which routes the original query through ahg-semantic-search
 * first (if available) and joins the expansion terms into the ES query.
 */
class CrossFondsQueryService
{
    public function __construct(private ElasticsearchService $es) {}

    /**
     * Return the fonds available to the researcher as filter chips - top-level
     * IOs by level_of_description (the only sensible cross-fonds anchor).
     */
    public function availableFonds(int $limit = 200): array
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('term as t', 'io.level_of_description_id', '=', 't.id')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', 'en');
            })
            ->leftJoin('actor_i18n as repo', function ($j) {
                $j->on('io.repository_id', '=', 'repo.id')->where('repo.culture', '=', 'en');
            })
            ->where(function ($q) {
                $q->where('ti.name', 'fonds')
                  ->orWhere('ti.name', 'Fonds')
                  ->orWhere('ti.name', 'collection')
                  ->orWhere('ti.name', 'Collection');
            })
            ->orderBy('ioi.title')
            ->limit($limit)
            ->select(
                'io.id',
                'io.lft',
                'io.rgt',
                'io.identifier',
                'ioi.title',
                'repo.authorized_form_of_name as repository_name'
            )
            ->get()
            ->toArray();
    }

    /**
     * @param array<int> $fondsIds  Top-level IO ids serving as fonds anchors.
     * @return array{
     *     results: array<int, array{title:string,fonds:string,snippet:string,score:float,url:string,object_id:int}>,
     *     total: int,
     *     elapsed_ms: int
     * }
     */
    public function query(string $query, array $fondsIds, ?int $researcherId = null, array $options = []): array
    {
        $start = microtime(true);

        $fonds = empty($fondsIds)
            ? $this->availableFonds(50)
            : DB::table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->whereIn('io.id', $fondsIds)
                ->select('io.id', 'io.lft', 'io.rgt', 'io.identifier', 'ioi.title')
                ->get()
                ->toArray();

        $perFondsSize = (int) ($options['per_fonds_size'] ?? 10);
        $appUrl = rtrim(config('app.url', ''), '/');

        $expandedQuery = $this->maybeExpand($query, $options);

        $merged = [];

        foreach ($fonds as $f) {
            $body = [
                'query' => [
                    'bool' => [
                        'must' => [[
                            'query_string' => [
                                'query'  => $expandedQuery,
                                'fields' => [
                                    'i18n.en.title^3',
                                    'i18n.en.scopeAndContent',
                                    'i18n.en.archivalHistory',
                                    'identifier^2',
                                    'referenceCode^2',
                                ],
                                'default_operator' => 'AND',
                            ],
                        ]],
                        'filter' => [
                            ['range' => ['lft' => ['gte' => (int) $f->lft]]],
                            ['range' => ['rgt' => ['lte' => (int) $f->rgt]]],
                        ],
                    ],
                ],
                'highlight' => [
                    'fields' => [
                        'i18n.en.scopeAndContent' => (object) ['fragment_size' => 220, 'number_of_fragments' => 1],
                        'i18n.en.title'           => (object) [],
                    ],
                ],
                'sort' => [['_score' => 'desc']],
            ];

            try {
                $resp = $this->es->search('qubitinformationobject', $body, 0, $perFondsSize);
            } catch (\Throwable $e) {
                continue;
            }

            foreach (($resp['hits']['hits'] ?? []) as $hit) {
                $src = $hit['_source'] ?? [];
                $highlight = $hit['highlight'] ?? [];
                $snippet = $highlight['i18n.en.scopeAndContent'][0] ?? null;
                if (!$snippet) {
                    $snippet = mb_substr((string) ($src['i18n']['en']['scopeAndContent'] ?? ''), 0, 220);
                }

                $slug = $src['slug'] ?? null;
                $objectId = (int) ($src['id'] ?? $hit['_id'] ?? 0);

                $merged[] = [
                    'object_id' => $objectId,
                    'title'     => (string) ($src['i18n']['en']['title'] ?? 'Untitled'),
                    'snippet'   => $snippet,
                    'fonds'     => $f->title ?: ('Fonds #' . $f->id),
                    'fonds_id'  => (int) $f->id,
                    'score'     => (float) ($hit['_score'] ?? 0.0),
                    'url'       => $appUrl . '/' . ($slug ?: $objectId),
                ];
            }
        }

        usort($merged, fn ($a, $b) => $b['score'] <=> $a['score']);

        $topK = (int) ($options['top_k'] ?? 30);
        $results = array_slice($merged, 0, $topK);

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        try {
            DB::table('research_cross_fonds_query')->insert([
                'researcher_id' => $researcherId,
                'query_text'    => mb_substr($query, 0, 1000),
                'fonds_ids'     => json_encode(array_values(array_map('intval', $fondsIds))),
                'results_count' => count($results),
                'elapsed_ms'    => $elapsedMs,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // logging only - non-critical
        }

        return [
            'results'    => $results,
            'total'      => count($results),
            'elapsed_ms' => $elapsedMs,
            'expanded_query' => $expandedQuery,
        ];
    }

    private function maybeExpand(string $query, array $options): string
    {
        if (empty($options['expand'])) return $query;

        if (class_exists(\AhgSemanticSearch\Services\SemanticSearchService::class)) {
            try {
                $svc = app(\AhgSemanticSearch\Services\SemanticSearchService::class);
                if (method_exists($svc, 'expandQuery')) {
                    $expanded = $svc->expandQuery($query);
                    if (is_string($expanded) && $expanded !== '') {
                        return $expanded;
                    }
                    if (is_array($expanded) && !empty($expanded)) {
                        return $query . ' OR ' . implode(' OR ', array_map(fn ($t) => '"' . addslashes((string) $t) . '"', $expanded));
                    }
                }
            } catch (\Throwable $e) {
                // fall through, return original
            }
        }

        return $query;
    }
}
