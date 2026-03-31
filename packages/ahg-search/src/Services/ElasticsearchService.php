<?php

/**
 * ElasticsearchService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElasticsearchService
{
    protected string $host;
    protected string $indexPrefix;

    public function __construct()
    {
        $this->host = config('services.elasticsearch.host', 'http://localhost:9200');
        $this->indexPrefix = config('services.elasticsearch.prefix', 'archive_');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Existing methods (unchanged)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Run a raw search against a specific index.
     */
    public function search(string $index, array $body, int $from = 0, int $size = 30): array
    {
        $url = "{$this->host}/{$this->indexPrefix}{$index}/_search";
        $response = Http::post($url, array_merge($body, ['from' => $from, 'size' => $size]));

        return $response->json();
    }

    /**
     * Search across all main indices (IO, actor, repository, term).
     */
    public function globalSearch(string $query, string $culture = 'en', int $from = 0, int $size = 30): array
    {
        $indices = implode(',', [
            "{$this->indexPrefix}qubitinformationobject",
            "{$this->indexPrefix}qubitactor",
            "{$this->indexPrefix}qubitrepository",
            "{$this->indexPrefix}qubitterm",
        ]);

        $url = "{$this->host}/{$indices}/_search";

        $body = [
            'from' => $from,
            'size' => $size,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'query_string' => [
                                'query' => $query,
                                'fields' => [
                                    "i18n.{$culture}.title^3",
                                    "i18n.{$culture}.authorizedFormOfName^3",
                                    "i18n.{$culture}.scopeAndContent",
                                    "i18n.{$culture}.history",
                                    "identifier^2",
                                    "referenceCode^2",
                                ],
                                'default_operator' => 'AND',
                            ],
                        ],
                    ],
                ],
            ],
            'highlight' => [
                'fields' => [
                    "i18n.{$culture}.title" => (object) [],
                    "i18n.{$culture}.authorizedFormOfName" => (object) [],
                    "i18n.{$culture}.scopeAndContent" => [
                        'fragment_size' => 200,
                        'number_of_fragments' => 1,
                    ],
                ],
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
        ];

        $response = Http::post($url, $body);

        return $response->json();
    }

    /**
     * Prefix-based autocomplete across all main indices.
     */
    public function autocomplete(string $query, string $culture = 'en', int $size = 10): array
    {
        $indices = implode(',', [
            "{$this->indexPrefix}qubitinformationobject",
            "{$this->indexPrefix}qubitactor",
            "{$this->indexPrefix}qubitrepository",
            "{$this->indexPrefix}qubitterm",
        ]);

        $url = "{$this->host}/{$indices}/_search";

        $body = [
            'size' => $size,
            '_source' => ['slug', "i18n.{$culture}", 'identifier'],
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'match_phrase_prefix' => [
                                "i18n.{$culture}.title" => [
                                    'query' => $query,
                                    'boost' => 3,
                                ],
                            ],
                        ],
                        [
                            'match_phrase_prefix' => [
                                "i18n.{$culture}.authorizedFormOfName" => [
                                    'query' => $query,
                                    'boost' => 3,
                                ],
                            ],
                        ],
                        [
                            'match_phrase_prefix' => [
                                'identifier' => [
                                    'query' => $query,
                                    'boost' => 2,
                                ],
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
        ];

        $response = Http::post($url, $body);

        return $response->json();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Advanced faceted search
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Full faceted search on information objects with filters, aggregations,
     * sorting, and pagination.
     *
     * Falls back to MySQL when Elasticsearch is not available.
     *
     * @param array $params Keys:
     *   query (string), repository (int|null), level (int|null),
     *   dateFrom (string|null), dateTo (string|null),
     *   hasDigitalObject (bool|null), mediaType (int|null),
     *   sort (string), page (int), limit (int), culture (string)
     */
    public function advancedSearch(array $params): array
    {
        $query   = trim($params['query'] ?? '');
        $repo    = $params['repository'] ?? null;
        $level   = $params['level'] ?? null;
        $dateFrom = $params['dateFrom'] ?? null;
        $dateTo  = $params['dateTo'] ?? null;
        $hasDo   = $params['hasDigitalObject'] ?? null;
        $mediaType = $params['mediaType'] ?? null;
        $sort    = $params['sort'] ?? 'relevance';
        $page    = max(1, (int) ($params['page'] ?? 1));
        $limit   = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $culture = $params['culture'] ?? 'en';

        // Try Elasticsearch first, fall back to DB
        if ($this->isElasticsearchAvailable()) {
            return $this->advancedSearchEs($query, $repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $sort, $page, $limit, $culture);
        }

        return $this->advancedSearchDb($query, $repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $sort, $page, $limit, $culture);
    }

    /**
     * Search for recently updated descriptions.
     */
    public function descriptionUpdates(array $params): array
    {
        $dateOf    = $params['dateOf'] ?? 'CREATED_AT';
        $startDate = $params['startDate'] ?? null;
        $endDate   = $params['endDate'] ?? null;
        $repository = $params['repository'] ?? null;
        $limit     = max(1, (int) ($params['limit'] ?? 30));
        $page      = max(1, (int) ($params['page'] ?? 1));

        if (!$this->isElasticsearchAvailable()) {
            return ['hits' => [], 'total' => 0];
        }

        $must = [];

        if ($repository) {
            $must[] = ['term' => ['repository.id' => (int) $repository]];
        }

        // Date range
        $dateField = $dateOf === 'UPDATED_AT' ? 'updatedAt' : 'createdAt';
        if ($startDate) {
            $must[] = ['range' => [$dateField => ['gte' => $startDate]]];
        }
        if ($endDate) {
            $must[] = ['range' => [$dateField => ['lte' => $endDate]]];
        }

        $body = [
            'size' => $limit,
            'from' => ($page - 1) * $limit,
            'sort' => [[$dateField => 'desc']],
            'query' => ['bool' => ['must' => !empty($must) ? $must : [['match_all' => (object) []]]]],
        ];

        $url = "{$this->host}/{$this->indexPrefix}qubitinformationobject/_search";
        $response = Http::post($url, $body);
        $result = $response->json();

        if (!$result || !isset($result['hits'])) {
            return ['hits' => [], 'total' => 0];
        }

        return [
            'hits'  => $result['hits']['hits'] ?? [],
            'total' => $result['hits']['total']['value'] ?? 0,
        ];
    }

    /**
     * Build ES aggregation definitions for sidebar facets.
     */
    public function getAggregations(array $filters, string $culture = 'en'): array
    {
        return [
            'repositories' => [
                'terms' => [
                    'field' => 'repository.id',
                    'size'  => 50,
                ],
            ],
            'levels' => [
                'terms' => [
                    'field' => 'levelOfDescriptionId',
                    'size'  => 50,
                ],
            ],
            'mediaTypes' => [
                'terms' => [
                    'field' => 'digitalObject.mediaTypeId',
                    'size'  => 20,
                ],
            ],
            'hasDigitalObject' => [
                'terms' => [
                    'field' => 'hasDigitalObject',
                    'size'  => 2,
                ],
            ],
            'dateHistogram' => [
                'date_histogram' => [
                    'field'             => 'createdAt',
                    'calendar_interval' => 'year',
                    'min_doc_count'     => 1,
                ],
            ],
        ];
    }

    /**
     * Resolve term/repository IDs in aggregation buckets to human-readable names.
     */
    public function resolveAggregationLabels(array $rawAggs, string $culture = 'en'): array
    {
        $resolved = [];

        // Resolve repository names
        if (!empty($rawAggs['repositories']['buckets'])) {
            $repoIds = array_column($rawAggs['repositories']['buckets'], 'key');
            $repoNames = $this->resolveRepositoryNames($repoIds, $culture);
            $resolved['repositories'] = [];
            foreach ($rawAggs['repositories']['buckets'] as $bucket) {
                $resolved['repositories'][] = [
                    'id'    => $bucket['key'],
                    'label' => $repoNames[$bucket['key']] ?? '[Unknown]',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Resolve level of description names
        if (!empty($rawAggs['levels']['buckets'])) {
            $termIds = array_column($rawAggs['levels']['buckets'], 'key');
            $termNames = $this->resolveTermNames($termIds, $culture);
            $resolved['levels'] = [];
            foreach ($rawAggs['levels']['buckets'] as $bucket) {
                $resolved['levels'][] = [
                    'id'    => $bucket['key'],
                    'label' => $termNames[$bucket['key']] ?? '[Unknown]',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Resolve media type names
        if (!empty($rawAggs['mediaTypes']['buckets'])) {
            $mtIds = array_column($rawAggs['mediaTypes']['buckets'], 'key');
            $mtNames = $this->resolveTermNames($mtIds, $culture);
            $resolved['mediaTypes'] = [];
            foreach ($rawAggs['mediaTypes']['buckets'] as $bucket) {
                $resolved['mediaTypes'][] = [
                    'id'    => $bucket['key'],
                    'label' => $mtNames[$bucket['key']] ?? '[Unknown]',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Has digital object (no resolution needed)
        if (!empty($rawAggs['hasDigitalObject']['buckets'])) {
            $resolved['hasDigitalObject'] = [];
            foreach ($rawAggs['hasDigitalObject']['buckets'] as $bucket) {
                $resolved['hasDigitalObject'][] = [
                    'id'    => $bucket['key'],
                    'label' => $bucket['key_as_string'] === 'true' ? 'With digital object' : 'Without digital object',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Date histogram (no resolution needed)
        if (!empty($rawAggs['dateHistogram']['buckets'])) {
            $resolved['dateHistogram'] = [];
            foreach ($rawAggs['dateHistogram']['buckets'] as $bucket) {
                $resolved['dateHistogram'][] = [
                    'label' => substr($bucket['key_as_string'] ?? '', 0, 4),
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        return $resolved;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Lookup helpers (for filters and aggregation labels)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get repository list for dropdowns.
     */
    public function getRepositoryList(string $culture = 'en'): array
    {
        return DB::table('actor')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->join('repository', 'repository.id', '=', 'actor.id')
            ->where('actor.id', '!=', 6) // ROOT_ID
            ->orderBy('actor_i18n.authorized_form_of_name', 'asc')
            ->pluck('actor_i18n.authorized_form_of_name', 'actor.id')
            ->toArray();
    }

    /**
     * Get levels of description for dropdowns.
     */
    public function getLevelsOfDescription(string $culture = 'en'): array
    {
        // Taxonomy ID 34 = level of description
        return DB::table('term')
            ->leftJoin('term_i18n', function ($join) use ($culture) {
                $join->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', '=', 34)
            ->pluck('term_i18n.name', 'term.id')
            ->filter()
            ->toArray();
    }

    /**
     * Get media types for dropdowns.
     */
    public function getMediaTypes(string $culture = 'en'): array
    {
        // Taxonomy ID 46 = media type
        return DB::table('term')
            ->leftJoin('term_i18n', function ($join) use ($culture) {
                $join->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', '=', 46)
            ->pluck('term_i18n.name', 'term.id')
            ->filter()
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Check if Elasticsearch is reachable.
     */
    protected function isElasticsearchAvailable(): bool
    {
        try {
            $response = Http::timeout(2)->get($this->host);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Advanced search via Elasticsearch.
     */
    protected function advancedSearchEs(
        string $query, ?int $repo, ?int $level,
        ?string $dateFrom, ?string $dateTo, ?bool $hasDo, ?int $mediaType,
        string $sort, int $page, int $limit, string $culture
    ): array {
        $must   = [];
        $filter = [];

        // Text query
        if ($query !== '') {
            $must[] = [
                'query_string' => [
                    'query'            => $this->sanitizeQuery($query),
                    'fields'           => [
                        "i18n.{$culture}.title^3",
                        "i18n.{$culture}.scopeAndContent",
                        "i18n.{$culture}.archivalHistory",
                        "i18n.{$culture}.extentAndMedium",
                        "i18n.{$culture}.accessConditions",
                        "i18n.{$culture}.locationOfOriginals",
                        "identifier^2",
                        "referenceCode^2",
                        "creators.i18n.{$culture}.authorizedFormOfName^2",
                    ],
                    'default_operator' => 'AND',
                ],
            ];
        }

        // Filters
        if ($repo) {
            $filter[] = ['term' => ['repository.id' => (int) $repo]];
        }
        if ($level) {
            $filter[] = ['term' => ['levelOfDescriptionId' => (int) $level]];
        }
        if ($hasDo !== null) {
            $filter[] = ['term' => ['hasDigitalObject' => $hasDo]];
        }
        if ($mediaType) {
            $filter[] = ['term' => ['digitalObject.mediaTypeId' => (int) $mediaType]];
        }
        if ($dateFrom) {
            $filter[] = ['range' => ['createdAt' => ['gte' => $dateFrom]]];
        }
        if ($dateTo) {
            $filter[] = ['range' => ['createdAt' => ['lte' => $dateTo]]];
        }

        // Published only (status 160 = published)
        $filter[] = ['term' => ['publicationStatusId' => 160]];

        // Build query
        $boolQuery = [];
        if (!empty($must)) {
            $boolQuery['must'] = $must;
        }
        if (!empty($filter)) {
            $boolQuery['filter'] = $filter;
        }
        if (empty($boolQuery)) {
            $boolQuery['must'] = [['match_all' => (object) []]];
        }

        // Sorting
        $sortClause = $this->buildSortClause($sort, $culture);

        // Aggregations
        $aggs = $this->getAggregations([
            'repository' => $repo,
            'level'      => $level,
            'mediaType'  => $mediaType,
        ], $culture);

        $body = [
            'size'  => $limit,
            'from'  => ($page - 1) * $limit,
            'sort'  => $sortClause,
            'query' => ['bool' => $boolQuery],
            'aggs'  => $aggs,
            'highlight' => [
                'fields' => [
                    "i18n.{$culture}.title" => (object) [],
                    "i18n.{$culture}.scopeAndContent" => [
                        'fragment_size'      => 200,
                        'number_of_fragments' => 1,
                    ],
                ],
                'pre_tags'  => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
        ];

        $url = "{$this->host}/{$this->indexPrefix}qubitinformationobject/_search";

        try {
            $response = Http::timeout(10)->post($url, $body);
            $result = $response->json();
        } catch (\Exception $e) {
            Log::warning('Elasticsearch request failed, falling back to DB: ' . $e->getMessage());
            return $this->advancedSearchDb($query, $repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $sort, $page, $limit, $culture);
        }

        if (!$result || !isset($result['hits'])) {
            return $this->advancedSearchDb($query, $repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $sort, $page, $limit, $culture);
        }

        $total = $result['hits']['total']['value'] ?? 0;
        $hits  = $this->transformIoHits($result['hits']['hits'] ?? [], $culture);

        // Resolve aggregation labels
        $aggregations = $this->resolveAggregationLabels($result['aggregations'] ?? [], $culture);

        return [
            'hits'         => $hits,
            'total'        => $total,
            'aggregations' => $aggregations,
            'page'         => $page,
            'limit'        => $limit,
        ];
    }

    /**
     * Database fallback for advanced search when ES is unavailable.
     */
    protected function advancedSearchDb(
        string $query, ?int $repo, ?int $level,
        ?string $dateFrom, ?string $dateTo, ?bool $hasDo, ?int $mediaType,
        string $sort, int $page, int $limit, string $culture
    ): array {
        $qb = DB::table('information_object as io')
            ->join('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', function ($join) {
                $join->on('slug.object_id', '=', 'io.id');
            })
            ->leftJoin('status', function ($join) {
                $join->on('status.object_id', '=', 'io.id')
                    ->where('status.type_id', '=', 158);
            })
            ->where('io.id', '!=', 1) // exclude root
            ->where(function ($q) {
                $q->where('status.status_id', '=', 160)
                  ->orWhereNull('status.status_id');
            });

        // Text search
        if ($query !== '') {
            $qb->where(function ($q) use ($query) {
                $words = explode(' ', $query);
                foreach ($words as $word) {
                    $like = '%' . $word . '%';
                    $q->where(function ($inner) use ($like) {
                        $inner->orWhere('io_i18n.title', 'LIKE', $like)
                              ->orWhere('io_i18n.scope_and_content', 'LIKE', $like)
                              ->orWhere('io_i18n.archival_history', 'LIKE', $like)
                              ->orWhere('io.identifier', 'LIKE', $like);
                    });
                }
            });
        }

        // Repository filter
        if ($repo) {
            $qb->where('io.repository_id', '=', $repo);
        }

        // Level of description filter
        if ($level) {
            $qb->where('io.level_of_description_id', '=', $level);
        }

        // Date range filter
        if ($dateFrom) {
            $qb->where('io.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $qb->where('io.created_at', '<=', $dateTo);
        }

        // Has digital object filter
        if ($hasDo !== null) {
            if ($hasDo) {
                $qb->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('digital_object')
                        ->whereColumn('digital_object.information_object_id', 'io.id');
                });
            } else {
                $qb->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('digital_object')
                        ->whereColumn('digital_object.information_object_id', 'io.id');
                });
            }
        }

        // Media type filter
        if ($mediaType) {
            $qb->whereExists(function ($sub) use ($mediaType) {
                $sub->select(DB::raw(1))
                    ->from('digital_object')
                    ->whereColumn('digital_object.information_object_id', 'io.id')
                    ->where('digital_object.media_type_id', '=', $mediaType);
            });
        }

        // Count total before pagination
        $totalQb = clone $qb;
        $total = $totalQb->count(DB::raw('DISTINCT io.id'));

        // Sorting
        switch ($sort) {
            case 'titleAsc':
                $qb->orderBy('io_i18n.title', 'asc');
                break;
            case 'titleDesc':
                $qb->orderBy('io_i18n.title', 'desc');
                break;
            case 'dateAsc':
                $qb->orderBy('io.created_at', 'asc');
                break;
            case 'dateDesc':
                $qb->orderBy('io.created_at', 'desc');
                break;
            case 'identifierAsc':
                $qb->orderBy('io.identifier', 'asc');
                break;
            case 'identifierDesc':
                $qb->orderBy('io.identifier', 'desc');
                break;
            case 'lastUpdated':
                $qb->orderBy('io.updated_at', 'desc');
                break;
            default: // relevance — for DB just use title
                $qb->orderBy('io_i18n.title', 'asc');
                break;
        }

        $rows = $qb
            ->select([
                'io.id',
                'io_i18n.title',
                'slug.slug',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                'io.created_at',
                'io.updated_at',
            ])
            ->groupBy('io.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        // Resolve names for display
        $levelNames = $this->getLevelsOfDescription($culture);
        $repoNames  = [];
        $repoIds = $rows->pluck('repository_id')->filter()->unique()->values()->toArray();
        if (!empty($repoIds)) {
            $repoNames = $this->resolveRepositoryNames($repoIds, $culture);
        }

        // Check digital objects
        $ioIds = $rows->pluck('id')->toArray();
        $doIds = [];
        if (!empty($ioIds)) {
            $doIds = DB::table('digital_object')
                ->whereIn('information_object_id', $ioIds)
                ->pluck('information_object_id')
                ->toArray();
        }

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = [
                'id'               => $row->id,
                'title'            => $row->title ?: '[Untitled]',
                'slug'             => $row->slug ?? '',
                'identifier'       => $row->identifier,
                'levelName'        => $levelNames[$row->level_of_description_id] ?? null,
                'repositoryName'   => $repoNames[$row->repository_id] ?? null,
                'hasDigitalObject' => in_array($row->id, $doIds),
                'dates'            => null,
                'snippet'          => null,
                'highlighted_title' => e($row->title ?: '[Untitled]'),
            ];
        }

        // Build aggregations from DB
        $aggregations = $this->buildDbAggregations($query, $repo, $level, $hasDo, $mediaType, $culture);

        return [
            'hits'         => $hits,
            'total'        => $total,
            'aggregations' => $aggregations,
            'page'         => $page,
            'limit'        => $limit,
        ];
    }

    /**
     * Build aggregations via DB queries (fallback when ES unavailable).
     */
    protected function buildDbAggregations(
        string $query, ?int $repo, ?int $level, ?bool $hasDo, ?int $mediaType, string $culture
    ): array {
        $aggregations = [];

        // Repository aggregation
        $repos = DB::table('information_object as io')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('io.repository_id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->where('io.id', '!=', 1)
            ->whereNotNull('io.repository_id')
            ->select('io.repository_id as id', 'actor_i18n.authorized_form_of_name as label', DB::raw('COUNT(*) as cnt'))
            ->groupBy('io.repository_id', 'actor_i18n.authorized_form_of_name')
            ->orderByDesc('cnt')
            ->limit(50)
            ->get();

        $aggregations['repositories'] = $repos->map(fn($r) => [
            'id' => $r->id, 'label' => $r->label ?: '[Unknown]', 'count' => $r->cnt,
        ])->toArray();

        // Level aggregation
        $levels = DB::table('information_object as io')
            ->leftJoin('term_i18n', function ($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('io.id', '!=', 1)
            ->whereNotNull('io.level_of_description_id')
            ->select('io.level_of_description_id as id', 'term_i18n.name as label', DB::raw('COUNT(*) as cnt'))
            ->groupBy('io.level_of_description_id', 'term_i18n.name')
            ->orderByDesc('cnt')
            ->limit(50)
            ->get();

        $aggregations['levels'] = $levels->map(fn($l) => [
            'id' => $l->id, 'label' => $l->label ?: '[Unknown]', 'count' => $l->cnt,
        ])->toArray();

        // Media type aggregation
        $media = DB::table('digital_object as do_tbl')
            ->leftJoin('term_i18n', function ($join) use ($culture) {
                $join->on('do_tbl.media_type_id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->whereNotNull('do_tbl.media_type_id')
            ->select('do_tbl.media_type_id as id', 'term_i18n.name as label', DB::raw('COUNT(*) as cnt'))
            ->groupBy('do_tbl.media_type_id', 'term_i18n.name')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get();

        $aggregations['mediaTypes'] = $media->map(fn($m) => [
            'id' => $m->id, 'label' => $m->label ?: '[Unknown]', 'count' => $m->cnt,
        ])->toArray();

        return $aggregations;
    }

    /**
     * Transform ES IO hits into a standardized result format.
     */
    protected function transformIoHits(array $hits, string $culture): array
    {
        $levelNames = null;
        $results = [];

        foreach ($hits as $hit) {
            $source    = $hit['_source'] ?? [];
            $highlight = $hit['highlight'] ?? [];
            $i18n      = $source['i18n'][$culture] ?? [];

            $title      = $i18n['title'] ?? '[Untitled]';
            $hlTitle    = $highlight["i18n.{$culture}.title"][0] ?? e($title);
            $snippet    = $highlight["i18n.{$culture}.scopeAndContent"][0]
                          ?? mb_substr($i18n['scopeAndContent'] ?? '', 0, 200)
                          ?: null;

            // Lazy-load levels
            if ($levelNames === null) {
                $levelNames = $this->getLevelsOfDescription($culture);
            }

            $levelId   = $source['levelOfDescriptionId'] ?? null;
            $levelName = $levelId ? ($levelNames[$levelId] ?? null) : null;

            $repoName = $source['repository']['i18n'][$culture]['authorizedFormOfName'] ?? null;

            // Dates
            $dateStr = null;
            if (!empty($source['dates'])) {
                $d = $source['dates'][0]['i18n'][$culture]['date'] ?? null;
                if ($d) {
                    $dateStr = $d;
                }
            }

            $results[] = [
                'id'                => $hit['_id'],
                'title'             => $title,
                'highlighted_title' => $hlTitle,
                'slug'              => $source['slug'] ?? '',
                'identifier'        => $source['identifier'] ?? null,
                'levelName'         => $levelName,
                'repositoryName'    => $repoName,
                'hasDigitalObject'  => $source['hasDigitalObject'] ?? false,
                'dates'             => $dateStr,
                'snippet'           => $snippet,
            ];
        }

        return $results;
    }

    /**
     * Build sort clause for ES.
     */
    protected function buildSortClause(string $sort, string $culture): array
    {
        return match ($sort) {
            'titleAsc'       => [["i18n.{$culture}.title.untouched" => 'asc'], '_score'],
            'titleDesc'      => [["i18n.{$culture}.title.untouched" => 'desc'], '_score'],
            'dateAsc'        => [['createdAt' => 'asc'], '_score'],
            'dateDesc'       => [['createdAt' => 'desc'], '_score'],
            'identifierAsc'  => [['identifier' => 'asc'], '_score'],
            'identifierDesc' => [['identifier' => 'desc'], '_score'],
            'lastUpdated'    => [['updatedAt' => 'desc'], '_score'],
            default          => ['_score', ['createdAt' => 'desc']],
        };
    }

    /**
     * Sanitize query string for ES.
     */
    protected function sanitizeQuery(string $query): string
    {
        return strtr($query, ['*' => '', '?' => '']);
    }

    /**
     * Resolve repository IDs to names via DB.
     */
    protected function resolveRepositoryNames(array $ids, string $culture): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::table('actor_i18n')
            ->whereIn('id', $ids)
            ->where('culture', '=', $culture)
            ->pluck('authorized_form_of_name', 'id')
            ->toArray();
    }

    /**
     * Resolve term IDs to names via DB.
     */
    protected function resolveTermNames(array $ids, string $culture): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::table('term_i18n')
            ->whereIn('id', $ids)
            ->where('culture', '=', $culture)
            ->pluck('name', 'id')
            ->toArray();
    }
}
