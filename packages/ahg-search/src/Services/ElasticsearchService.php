<?php

/**
 * ElasticsearchService - Service for Heratio
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

namespace AhgSearch\Services;

use AhgCore\Support\TenantScope;
use AhgSearch\Support\SearchCursor;
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

    /**
     * Multi-tenant repository filter for an IO-only ES query. Returns null
     * when no scoping should apply (admin, feature off, no tenant, etc.) -
     * callers should simply not append.
     */
    protected function tenantIoFilter(): ?array
    {
        $repoId = TenantScope::getActiveRepoId();
        if ($repoId === null) {
            return null;
        }

        return ['term' => ['repository.id' => $repoId]];
    }

    /**
     * Multi-tenant scope clause for a multi-index search (IO + actor +
     * repository + term). IO docs are filtered by repository.id; non-IO
     * docs pass through (they have no repository.id field). Returns null
     * when scoping should NOT apply.
     */
    protected function tenantMultiIndexFilter(): ?array
    {
        $repoId = TenantScope::getActiveRepoId();
        if ($repoId === null) {
            return null;
        }

        return [
            'bool' => [
                'should' => [
                    // Non-IO docs (actor / term) - no repository.id field.
                    ['bool' => ['must_not' => [['exists' => ['field' => 'repository.id']]]]],
                    // IO docs scoped to the tenant.
                    ['term' => ['repository.id' => $repoId]],
                ],
                'minimum_should_match' => 1,
            ],
        ];
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

        $bool = [
            'must' => [
                [
                    'query_string' => [
                        'query' => $this->sanitizeQuery($query),
                        'fields' => [
                            "i18n.{$culture}.title^3",
                            "i18n.{$culture}.authorizedFormOfName^3",
                            "creators.i18n.{$culture}.authorizedFormOfName^2",
                            "i18n.{$culture}.scopeAndContent",
                            "i18n.{$culture}.history",
                            'identifier^2',
                            'referenceCode^2',
                            'isbn^3',
                            'callNumber^2',
                            'seriesTitle',
                            'seriesIssn',
                            'summary',
                            'contentsNote',
                        ],
                        'default_operator' => 'AND',
                    ],
                ],
            ],
        ];

        if ($tenantClause = $this->tenantMultiIndexFilter()) {
            $bool['filter'] = [$tenantClause];
        }

        $body = [
            'from' => $from,
            'size' => $size,
            'query' => ['bool' => $bool],
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

        $bool = [
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
                        "creators.i18n.{$culture}.authorizedFormOfName" => [
                            'query' => $query,
                            'boost' => 2,
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
                [
                    'match_phrase_prefix' => [
                        'isbn' => [
                            'query' => $query,
                            'boost' => 3,
                        ],
                    ],
                ],
                [
                    'match_phrase_prefix' => [
                        'callNumber' => [
                            'query' => $query,
                            'boost' => 2,
                        ],
                    ],
                ],
            ],
            'minimum_should_match' => 1,
        ];

        if ($tenantClause = $this->tenantMultiIndexFilter()) {
            $bool['filter'] = [$tenantClause];
        }

        $body = [
            'size' => $size,
            '_source' => ['slug', "i18n.{$culture}", 'identifier'],
            'query' => ['bool' => $bool],
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
     * @param  array  $params  Keys:
     *                         query (string), repository (int|null), level (int|null),
     *                         dateFrom (string|null), dateTo (string|null),
     *                         hasDigitalObject (bool|null), mediaType (int|null),
     *                         sort (string), page (int), limit (int), culture (string)
     */
    public function advancedSearch(array $params): array
    {
        $query = trim($params['query'] ?? '');
        $repo = $params['repository'] ?? null;
        $level = $params['level'] ?? null;
        $dateFrom = $params['dateFrom'] ?? null;
        $dateTo = $params['dateTo'] ?? null;
        $hasDo = $params['hasDigitalObject'] ?? null;
        $mediaType = $params['mediaType'] ?? null;
        $sort = $params['sort'] ?? 'relevance';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $culture = $params['culture'] ?? 'en';

        // Phase 3 (#650): cursor paging + geo filters. Both are opt-in - omitting
        // them leaves the legacy from/size + non-geo behaviour intact.
        $cursor = $params['cursor'] ?? null;
        $paging = $params['paging'] ?? null; // 'cursor' to force cursor mode even without a token
        $geo = $params['geo'] ?? null;       // ['center' => 'lat,lng', 'radius' => '5km'] OR ['box' => 'lat1,lng1,lat2,lng2']

        // #730 - PSIS parity facets. Each is an optional click-through filter
        // on a sidebar bucket. languages/collection ride on string ids; the
        // others are integer term/actor ids.
        $facets = [
            'languages' => $params['languages'] ?? null,
            'places' => $params['places'] ?? null,
            'subjects' => $params['subjects'] ?? null,
            'genres' => $params['genres'] ?? null,
            'names' => $params['names'] ?? null,
            'collection' => $params['collection'] ?? null,
        ];

        // Try Elasticsearch first, fall back to DB.
        // The DB fallback ignores cursor + geo + #730 facet filters (degraded
        // mode) - all three require ES.
        if ($this->isElasticsearchAvailable()) {
            return $this->advancedSearchEs(
                $query, $repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType,
                $sort, $page, $limit, $culture, $cursor, $paging, $geo, $facets
            );
        }

        return $this->advancedSearchDb($query, $repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $sort, $page, $limit, $culture);
    }

    /**
     * Search for recently updated descriptions.
     */
    public function descriptionUpdates(array $params): array
    {
        $dateOf = $params['dateOf'] ?? 'CREATED_AT';
        $startDate = $params['startDate'] ?? null;
        $endDate = $params['endDate'] ?? null;
        $repository = $params['repository'] ?? null;
        $limit = max(1, (int) ($params['limit'] ?? 30));
        $page = max(1, (int) ($params['page'] ?? 1));

        if (! $this->isElasticsearchAvailable()) {
            return ['hits' => [], 'total' => 0];
        }

        $must = [];

        if ($repository) {
            $must[] = ['term' => ['repository.id' => (int) $repository]];
        }

        if ($tenantClause = $this->tenantIoFilter()) {
            $must[] = $tenantClause;
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
            'query' => ['bool' => ['must' => ! empty($must) ? $must : [['match_all' => (object) []]]]],
        ];

        $url = "{$this->host}/{$this->indexPrefix}qubitinformationobject/_search";
        $response = Http::post($url, $body);
        $result = $response->json();

        if (! $result || ! isset($result['hits'])) {
            return ['hits' => [], 'total' => 0];
        }

        return [
            'hits' => $result['hits']['hits'] ?? [],
            'total' => $result['hits']['total']['value'] ?? 0,
        ];
    }

    /**
     * Build ES aggregation definitions for sidebar facets.
     *
     * Issue #730 (PSIS twin atom-ahg-plugins#76): mirrored the 6 facet
     * buckets PSIS (apps/qubit/modules/informationobject/actions/browseAction.class.php)
     * exposes that Heratio was missing - languages, places, subjects, genres,
     * names (creators sit on the GLAM browse view already), and collection
     * (partOf.id). PSIS aggregator total: 11 buckets. Heratio post-#730: 11
     * buckets (the missing-6 list above + the original 5: repositories,
     * levels, mediaTypes, hasDigitalObject, dateHistogram).
     */
    public function getAggregations(array $filters, string $culture = 'en'): array
    {
        return [
            'repositories' => [
                'terms' => [
                    'field' => 'repository.id',
                    'size' => 50,
                ],
            ],
            'levels' => [
                'terms' => [
                    'field' => 'levelOfDescriptionId',
                    'size' => 50,
                ],
            ],
            'mediaTypes' => [
                'terms' => [
                    'field' => 'digitalObject.mediaTypeId',
                    'size' => 20,
                ],
            ],
            'hasDigitalObject' => [
                'terms' => [
                    'field' => 'hasDigitalObject',
                    'size' => 2,
                ],
            ],
            'dateHistogram' => [
                'date_histogram' => [
                    'field' => 'createdAt',
                    'calendar_interval' => 'year',
                    'min_doc_count' => 1,
                ],
            ],

            // #730 - PSIS parity facets (the 6 buckets PSIS exposes that
            // Heratio was missing). Sizes match PSIS ($AGGS in
            // qubit/modules/informationobject/actions/browseAction.class.php).
            'languages' => [
                'terms' => [
                    'field' => 'i18n.languages',
                    'size' => 10,
                ],
            ],
            'places' => [
                'terms' => [
                    'field' => 'places.id',
                    'size' => 10,
                ],
            ],
            'subjects' => [
                'terms' => [
                    'field' => 'subjects.id',
                    'size' => 10,
                ],
            ],
            'genres' => [
                'terms' => [
                    'field' => 'genres.id',
                    'size' => 10,
                ],
            ],
            'names' => [
                'terms' => [
                    'field' => 'names.id',
                    'size' => 10,
                ],
            ],
            'collection' => [
                'terms' => [
                    'field' => 'partOf.id',
                    'size' => 10,
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
        if (! empty($rawAggs['repositories']['buckets'])) {
            $repoIds = array_column($rawAggs['repositories']['buckets'], 'key');
            $repoNames = $this->resolveRepositoryNames($repoIds, $culture);
            $resolved['repositories'] = [];
            foreach ($rawAggs['repositories']['buckets'] as $bucket) {
                $resolved['repositories'][] = [
                    'id' => $bucket['key'],
                    'label' => $repoNames[$bucket['key']] ?? '[Unknown]',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Resolve level of description names
        if (! empty($rawAggs['levels']['buckets'])) {
            $termIds = array_column($rawAggs['levels']['buckets'], 'key');
            $termNames = $this->resolveTermNames($termIds, $culture);
            $resolved['levels'] = [];
            foreach ($rawAggs['levels']['buckets'] as $bucket) {
                $resolved['levels'][] = [
                    'id' => $bucket['key'],
                    'label' => $termNames[$bucket['key']] ?? '[Unknown]',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Resolve media type names
        if (! empty($rawAggs['mediaTypes']['buckets'])) {
            $mtIds = array_column($rawAggs['mediaTypes']['buckets'], 'key');
            $mtNames = $this->resolveTermNames($mtIds, $culture);
            $resolved['mediaTypes'] = [];
            foreach ($rawAggs['mediaTypes']['buckets'] as $bucket) {
                $resolved['mediaTypes'][] = [
                    'id' => $bucket['key'],
                    'label' => $mtNames[$bucket['key']] ?? '[Unknown]',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Has digital object (no resolution needed)
        if (! empty($rawAggs['hasDigitalObject']['buckets'])) {
            $resolved['hasDigitalObject'] = [];
            foreach ($rawAggs['hasDigitalObject']['buckets'] as $bucket) {
                $resolved['hasDigitalObject'][] = [
                    'id' => $bucket['key'],
                    'label' => $bucket['key_as_string'] === 'true' ? 'With digital object' : 'Without digital object',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Date histogram (no resolution needed)
        if (! empty($rawAggs['dateHistogram']['buckets'])) {
            $resolved['dateHistogram'] = [];
            foreach ($rawAggs['dateHistogram']['buckets'] as $bucket) {
                $resolved['dateHistogram'][] = [
                    'label' => substr($bucket['key_as_string'] ?? '', 0, 4),
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // #730 - PSIS parity facets. Term-backed buckets (places, subjects,
        // genres) use term_i18n.name; actor-backed (names) uses actor_i18n.
        // collection resolves via information_object_i18n.title (partOf.id is
        // an IO id). Languages keep their raw ISO code as the id and resolve
        // to a human label via PHP's locale catalog.
        foreach (['places', 'subjects', 'genres'] as $termAgg) {
            if (! empty($rawAggs[$termAgg]['buckets'])) {
                $termIds = array_column($rawAggs[$termAgg]['buckets'], 'key');
                $termNames = $this->resolveTermNames($termIds, $culture);
                $resolved[$termAgg] = [];
                foreach ($rawAggs[$termAgg]['buckets'] as $bucket) {
                    $resolved[$termAgg][] = [
                        'id' => $bucket['key'],
                        'label' => $termNames[$bucket['key']] ?? '[Unknown]',
                        'count' => $bucket['doc_count'],
                    ];
                }
            }
        }

        if (! empty($rawAggs['names']['buckets'])) {
            $actorIds = array_column($rawAggs['names']['buckets'], 'key');
            $actorNames = $this->resolveRepositoryNames($actorIds, $culture);
            $resolved['names'] = [];
            foreach ($rawAggs['names']['buckets'] as $bucket) {
                $resolved['names'][] = [
                    'id' => $bucket['key'],
                    'label' => $actorNames[$bucket['key']] ?? '[Unknown]',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        if (! empty($rawAggs['collection']['buckets'])) {
            $ioIds = array_column($rawAggs['collection']['buckets'], 'key');
            $ioTitles = $this->resolveIoTitles($ioIds, $culture);
            $resolved['collection'] = [];
            foreach ($rawAggs['collection']['buckets'] as $bucket) {
                $resolved['collection'][] = [
                    'id' => $bucket['key'],
                    'label' => $ioTitles[$bucket['key']] ?? '[Unknown collection]',
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        if (! empty($rawAggs['languages']['buckets'])) {
            $resolved['languages'] = [];
            foreach ($rawAggs['languages']['buckets'] as $bucket) {
                $code = (string) $bucket['key'];
                $resolved['languages'][] = [
                    'id' => $code,
                    'label' => $this->resolveLanguageLabel($code, $culture),
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
        string $sort, int $page, int $limit, string $culture,
        ?string $cursor = null, ?string $paging = null, ?array $geo = null,
        ?array $facets = null
    ): array {
        $must = [];
        $filter = [];

        // Text query
        if ($query !== '') {
            $must[] = [
                'query_string' => [
                    'query' => $this->sanitizeQuery($query),
                    'fields' => [
                        "i18n.{$culture}.title^3",
                        "i18n.{$culture}.scopeAndContent",
                        "i18n.{$culture}.archivalHistory",
                        "i18n.{$culture}.extentAndMedium",
                        "i18n.{$culture}.accessConditions",
                        "i18n.{$culture}.locationOfOriginals",
                        'identifier^2',
                        'referenceCode^2',
                        "creators.i18n.{$culture}.authorizedFormOfName^2",
                        'isbn^3',
                        'callNumber^2',
                        'seriesTitle',
                        'seriesIssn',
                        'summary',
                        'contentsNote',
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

        // #730 - PSIS parity facet filters. Skipped silently when empty so the
        // bucket can still come back in the aggs (sidebar always shows what's
        // available to click). Term/actor/IO buckets cast to int; languages
        // stays a string ISO code; collection is partOf.id.
        if (! empty($facets['languages'])) {
            $filter[] = ['term' => ['i18n.languages' => (string) $facets['languages']]];
        }
        if (! empty($facets['places'])) {
            $filter[] = ['term' => ['places.id' => (int) $facets['places']]];
        }
        if (! empty($facets['subjects'])) {
            $filter[] = ['term' => ['subjects.id' => (int) $facets['subjects']]];
        }
        if (! empty($facets['genres'])) {
            $filter[] = ['term' => ['genres.id' => (int) $facets['genres']]];
        }
        if (! empty($facets['names'])) {
            $filter[] = ['term' => ['names.id' => (int) $facets['names']]];
        }
        if (! empty($facets['collection'])) {
            $filter[] = ['term' => ['partOf.id' => (int) $facets['collection']]];
        }

        // Geo filter (#650 Phase 3). Centre+radius and bounding box are mutually
        // exclusive - centre+radius wins when both are supplied. Bad coordinates
        // are silently dropped (we don't want a malformed URL to 500 the page).
        if ($geoClause = $this->buildGeoFilter($geo)) {
            $filter[] = $geoClause;
        }

        // Published only (status 160 = published)
        $filter[] = ['term' => ['publicationStatusId' => 160]];

        // Multi-tenant scope. Applied after the user-driven repository filter
        // so a tenant user can't widen scope via URL param - both ANDed, the
        // narrower wins. No-op when multi-tenancy is disabled or admin.
        if ($tenantClause = $this->tenantIoFilter()) {
            $filter[] = $tenantClause;
        }

        // Build query
        $boolQuery = [];
        if (! empty($must)) {
            $boolQuery['must'] = $must;
        }
        if (! empty($filter)) {
            $boolQuery['filter'] = $filter;
        }
        if (empty($boolQuery)) {
            $boolQuery['must'] = [['match_all' => (object) []]];
        }

        // Sorting. Always append `_id asc` as a unique tiebreaker so
        // `search_after` cursors are stable - duplicate sort keys would otherwise
        // skip / repeat hits across cursor pages. Harmless in legacy page-mode.
        $sortClause = $this->buildSortClause($sort, $culture);
        $sortClause[] = ['_id' => 'asc'];

        // Decide paging mode. Cursor mode kicks in when caller passes a token
        // (forward or backward) OR when caller explicitly sets paging=cursor
        // (lets the first page of a cursor-paged session emit a next_cursor).
        $decoded = SearchCursor::decode($cursor);
        $cursorMode = $decoded !== null || $paging === 'cursor';

        // Aggregations
        $aggs = $this->getAggregations([
            'repository' => $repo,
            'level' => $level,
            'mediaType' => $mediaType,
        ], $culture);

        $body = [
            'size' => $limit,
            'sort' => $sortClause,
            'query' => ['bool' => $boolQuery],
            'aggs' => $aggs,
            'highlight' => [
                'fields' => [
                    "i18n.{$culture}.title" => (object) [],
                    "i18n.{$culture}.scopeAndContent" => [
                        'fragment_size' => 200,
                        'number_of_fragments' => 1,
                    ],
                ],
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
        ];

        if ($cursorMode) {
            // search_after / search_before paging - no `from`, the cursor IS the
            // offset. Backward cursors invert the sort so the result-set is the
            // *previous* slice, which we re-reverse after the hit transform so
            // the user-facing order stays consistent.
            if ($decoded !== null) {
                if ($decoded['direction'] === SearchCursor::DIR_PREV) {
                    $body['sort'] = $this->reverseSortClause($sortClause);
                    $body['search_after'] = $decoded['sort'];
                } else {
                    $body['search_after'] = $decoded['sort'];
                }
            }
        } else {
            $body['from'] = ($page - 1) * $limit;
        }

        $url = "{$this->host}/{$this->indexPrefix}qubitinformationobject/_search";

        try {
            $response = Http::timeout(10)->post($url, $body);
            $result = $response->json();
        } catch (\Exception $e) {
            Log::warning('Elasticsearch request failed, falling back to DB: '.$e->getMessage());

            return $this->advancedSearchDb($query, $repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $sort, $page, $limit, $culture);
        }

        if (! $result || ! isset($result['hits'])) {
            return $this->advancedSearchDb($query, $repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $sort, $page, $limit, $culture);
        }

        $total = $result['hits']['total']['value'] ?? 0;
        $rawHits = $result['hits']['hits'] ?? [];

        // Backward cursor: ES returned the slice in reversed order. Flip it
        // back so the caller sees results in the same direction as a forward
        // page would have produced.
        $reversed = $cursorMode
            && $decoded !== null
            && $decoded['direction'] === SearchCursor::DIR_PREV;
        if ($reversed) {
            $rawHits = array_reverse($rawHits);
        }

        $hits = $this->transformIoHits($rawHits, $culture);

        // Resolve aggregation labels
        $aggregations = $this->resolveAggregationLabels($result['aggregations'] ?? [], $culture);

        $response = [
            'hits' => $hits,
            'total' => $total,
            'aggregations' => $aggregations,
            'page' => $page,
            'limit' => $limit,
        ];

        if ($cursorMode) {
            // Emit next/prev cursors built from the boundary hits' sort values.
            // - next_cursor is null when we got fewer hits than $limit (end of set)
            //   AND we're moving forward, OR when there are no hits at all.
            // - prev_cursor is null when no cursor was supplied (we're at start).
            $first = $rawHits[0] ?? null;
            $last = end($rawHits) ?: null;
            reset($rawHits);

            $atEnd = count($rawHits) < $limit;

            $response['paging'] = 'cursor';
            $response['next_cursor'] = ($last && ! $atEnd)
                ? SearchCursor::encode($last['sort'] ?? [], SearchCursor::DIR_NEXT)
                : null;
            $response['prev_cursor'] = ($first && $decoded !== null)
                ? SearchCursor::encode($first['sort'] ?? [], SearchCursor::DIR_PREV)
                : null;
        }

        return $response;
    }

    /**
     * Reverse every sort direction in an ES sort clause. Used by backward
     * cursor paging so `search_before` semantics work via a flipped
     * `search_after` query.
     */
    protected function reverseSortClause(array $sortClause): array
    {
        $flipped = [];
        foreach ($sortClause as $entry) {
            if (is_string($entry)) {
                // `_score` - reverse means worst-first, which is a perfectly
                // valid (if rarely useful) intent for a backward page.
                $flipped[] = [$entry => 'asc'];
                continue;
            }
            if (! is_array($entry)) {
                $flipped[] = $entry;
                continue;
            }
            $out = [];
            foreach ($entry as $k => $v) {
                if (is_string($v)) {
                    $out[$k] = strtolower($v) === 'asc' ? 'desc' : 'asc';
                } elseif (is_array($v) && isset($v['order'])) {
                    $copy = $v;
                    $copy['order'] = strtolower($v['order']) === 'asc' ? 'desc' : 'asc';
                    $out[$k] = $copy;
                } else {
                    $out[$k] = $v;
                }
            }
            $flipped[] = $out;
        }

        return $flipped;
    }

    /**
     * Build an ES geo_distance or geo_bounding_box filter clause from the
     * incoming `geo` parameter. Returns null when nothing useful is supplied.
     *
     * Accepted shapes (#650 Phase 3):
     *   ['center' => 'lat,lng', 'radius' => '5km']
     *   ['box' => 'lat1,lng1,lat2,lng2']  (top-left, bottom-right)
     */
    protected function buildGeoFilter(?array $geo): ?array
    {
        if (! is_array($geo)) {
            return null;
        }

        // geo_distance wins when both are supplied.
        if (! empty($geo['center'])) {
            $parts = array_map('trim', explode(',', (string) $geo['center']));
            if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                return null;
            }
            $lat = (float) $parts[0];
            $lng = (float) $parts[1];
            if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
                return null;
            }
            $radius = (string) ($geo['radius'] ?? '5km');
            if (! preg_match('/^\d+(\.\d+)?(km|m|mi|yd|ft|in|nmi)$/i', $radius)) {
                $radius = '5km';
            }

            return [
                'geo_distance' => [
                    'distance' => $radius,
                    'gis' => ['lat' => $lat, 'lon' => $lng],
                ],
            ];
        }

        if (! empty($geo['box'])) {
            $parts = array_map('trim', explode(',', (string) $geo['box']));
            if (count($parts) !== 4) {
                return null;
            }
            foreach ($parts as $p) {
                if (! is_numeric($p)) {
                    return null;
                }
            }
            [$lat1, $lng1, $lat2, $lng2] = array_map('floatval', $parts);
            if ($lat1 < -90 || $lat1 > 90 || $lat2 < -90 || $lat2 > 90
                || $lng1 < -180 || $lng1 > 180 || $lng2 < -180 || $lng2 > 180) {
                return null;
            }

            return [
                'geo_bounding_box' => [
                    'gis' => [
                        'top_left' => ['lat' => $lat1, 'lon' => $lng1],
                        'bottom_right' => ['lat' => $lat2, 'lon' => $lng2],
                    ],
                ],
            ];
        }

        return null;
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
            // information_object has no created_at/updated_at - they live on the
            // CTI parent `object` (object.id = io.id). Join it for date filter/sort.
            ->leftJoin('object', 'object.id', '=', 'io.id')
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
                    $like = '%'.$word.'%';
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

        // Multi-tenant scope (matches the ES side via the same gate).
        TenantScope::apply($qb, 'io.repository_id');

        // Level of description filter
        if ($level) {
            $qb->where('io.level_of_description_id', '=', $level);
        }

        // Date range filter
        if ($dateFrom) {
            $qb->where('object.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $qb->where('object.created_at', '<=', $dateTo);
        }

        // Has digital object filter
        if ($hasDo !== null) {
            if ($hasDo) {
                $qb->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('digital_object')
                        ->whereColumn('digital_object.object_id', 'io.id');
                });
            } else {
                $qb->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('digital_object')
                        ->whereColumn('digital_object.object_id', 'io.id');
                });
            }
        }

        // Media type filter
        if ($mediaType) {
            $qb->whereExists(function ($sub) use ($mediaType) {
                $sub->select(DB::raw(1))
                    ->from('digital_object')
                    ->whereColumn('digital_object.object_id', 'io.id')
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
                $qb->orderBy('object.created_at', 'asc');
                break;
            case 'dateDesc':
                $qb->orderBy('object.created_at', 'desc');
                break;
            case 'identifierAsc':
                $qb->orderBy('io.identifier', 'asc');
                break;
            case 'identifierDesc':
                $qb->orderBy('io.identifier', 'desc');
                break;
            case 'lastUpdated':
                $qb->orderBy('object.updated_at', 'desc');
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
                'object.created_at',
                'object.updated_at',
            ])
            // List every selected column so the dedupe groupBy satisfies
            // ONLY_FULL_GROUP_BY (matches the GLAM browse pattern).
            ->groupBy('io.id', 'io_i18n.title', 'slug.slug', 'io.identifier', 'io.level_of_description_id', 'io.repository_id', 'object.created_at', 'object.updated_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        // Resolve names for display
        $levelNames = $this->getLevelsOfDescription($culture);
        $repoNames = [];
        $repoIds = $rows->pluck('repository_id')->filter()->unique()->values()->toArray();
        if (! empty($repoIds)) {
            $repoNames = $this->resolveRepositoryNames($repoIds, $culture);
        }

        // Check digital objects
        $ioIds = $rows->pluck('id')->toArray();
        $doIds = [];
        $thumbMap = [];
        if (! empty($ioIds)) {
            $doIds = DB::table('digital_object')
                ->whereIn('object_id', $ioIds)
                ->pluck('object_id')
                ->toArray();

            // Real thumbnail per result: prefer the thumbnail derivative (142),
            // fall back to the reference (141); only when it is an image file.
            $thumbRows = DB::table('digital_object')
                ->whereIn('object_id', $ioIds)
                ->whereIn('usage_id', [142, 141])
                ->whereNotNull('path')
                ->where('name', 'REGEXP', '\\.(jpe?g|png|gif|webp|bmp|tiff?)$')
                ->orderByRaw('FIELD(usage_id, 142, 141)')
                ->get(['object_id', 'path', 'name']);
            $uploadsRoot = rtrim((string) config('heratio.storage_path', '/mnt/nas/heratio'), '/');
            foreach ($thumbRows as $tr) {
                if (isset($thumbMap[$tr->object_id])) {
                    continue;
                }
                $url = rtrim($tr->path, '/').'/'.$tr->name;
                // Only emit a thumbnail whose file actually exists on disk, so a
                // missing derivative falls back to the icon instead of a broken
                // <img> (CSP blocks an inline onerror fallback). /uploads/* maps
                // to {storage_path}/uploads/* via the nginx alias.
                if (str_starts_with($url, '/uploads/') && is_file($uploadsRoot.$url)) {
                    $thumbMap[$tr->object_id] = $url;
                }
            }
        }

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = [
                'id' => $row->id,
                'title' => $row->title ?: '[Untitled]',
                'slug' => $row->slug ?? '',
                'identifier' => $row->identifier,
                'levelName' => $levelNames[$row->level_of_description_id] ?? null,
                'repositoryName' => $repoNames[$row->repository_id] ?? null,
                'hasDigitalObject' => in_array($row->id, $doIds),
                'thumbnailPath' => $thumbMap[$row->id] ?? null,
                'dates' => null,
                'snippet' => null,
                'highlighted_title' => e($row->title ?: '[Untitled]'),
            ];
        }

        // Build aggregations from DB
        $aggregations = $this->buildDbAggregations($query, $repo, $level, $hasDo, $mediaType, $culture);

        return [
            'hits' => $hits,
            'total' => $total,
            'aggregations' => $aggregations,
            'page' => $page,
            'limit' => $limit,
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

        $aggregations['repositories'] = $repos->map(fn ($r) => [
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

        $aggregations['levels'] = $levels->map(fn ($l) => [
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

        $aggregations['mediaTypes'] = $media->map(fn ($m) => [
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
            $source = $hit['_source'] ?? [];
            $highlight = $hit['highlight'] ?? [];
            $i18n = $source['i18n'][$culture] ?? [];

            $title = $i18n['title'] ?? '[Untitled]';
            $hlTitle = $highlight["i18n.{$culture}.title"][0] ?? e($title);
            $snippet = $highlight["i18n.{$culture}.scopeAndContent"][0]
                          ?? mb_substr($i18n['scopeAndContent'] ?? '', 0, 200)
                          ?: null;

            // Lazy-load levels
            if ($levelNames === null) {
                $levelNames = $this->getLevelsOfDescription($culture);
            }

            $levelId = $source['levelOfDescriptionId'] ?? null;
            $levelName = $levelId ? ($levelNames[$levelId] ?? null) : null;

            $repoName = $source['repository']['i18n'][$culture]['authorizedFormOfName'] ?? null;

            // Dates
            $dateStr = null;
            if (! empty($source['dates'])) {
                $d = $source['dates'][0]['i18n'][$culture]['date'] ?? null;
                if ($d) {
                    $dateStr = $d;
                }
            }

            $results[] = [
                'id' => $hit['_id'],
                'title' => $title,
                'highlighted_title' => $hlTitle,
                'slug' => $source['slug'] ?? '',
                'identifier' => $source['identifier'] ?? null,
                'levelName' => $levelName,
                'repositoryName' => $repoName,
                'hasDigitalObject' => $source['hasDigitalObject'] ?? false,
                'dates' => $dateStr,
                'snippet' => $snippet,
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
            'titleAsc' => [["i18n.{$culture}.title.untouched" => 'asc'], '_score'],
            'titleDesc' => [["i18n.{$culture}.title.untouched" => 'desc'], '_score'],
            'dateAsc' => [['createdAt' => 'asc'], '_score'],
            'dateDesc' => [['createdAt' => 'desc'], '_score'],
            'identifierAsc' => [['identifier' => 'asc'], '_score'],
            'identifierDesc' => [['identifier' => 'desc'], '_score'],
            'lastUpdated' => [['updatedAt' => 'desc'], '_score'],
            default => ['_score', ['createdAt' => 'desc']],
        };
    }

    /**
     * "Did you mean ...?" phrase suggester (#650 Phase 1).
     *
     * Runs an ES `_suggest` query against the IO index using the
     * phrase_suggester on `i18n.{culture}.title` (the field with the best
     * recall for archival catalogues). Returns the top suggestion string
     * when one exists and differs (case-insensitive) from the original
     * query, or null otherwise. Safe to call even when ES is down — any
     * failure short-circuits to null so the caller never explodes.
     *
     * @param  string  $query  Raw user query (will be lower-cased for compare).
     * @param  string  $culture  2-letter culture for the title field.
     * @return string|null Suggested phrase or null.
     */
    public function suggest(string $query, string $culture = 'en'): ?string
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 3) {
            return null;
        }

        if (! $this->isElasticsearchAvailable()) {
            return null;
        }

        $field = "i18n.{$culture}.title";

        $body = [
            'size' => 0,
            'suggest' => [
                'text' => $query,
                'title_suggest' => [
                    'phrase' => [
                        'field' => $field,
                        'size' => 1,
                        'gram_size' => 3,
                        'direct_generator' => [[
                            'field' => $field,
                            'suggest_mode' => 'always',
                        ]],
                    ],
                ],
            ],
        ];

        $url = "{$this->host}/{$this->indexPrefix}qubitinformationobject/_search";

        try {
            $response = Http::timeout(5)->post($url, $body);
            $result = $response->json();
        } catch (\Exception $e) {
            Log::debug('Elasticsearch suggester request failed: '.$e->getMessage());

            return null;
        }

        $options = $result['suggest']['title_suggest'][0]['options'] ?? [];
        if (empty($options)) {
            return null;
        }

        $suggestion = trim((string) ($options[0]['text'] ?? ''));
        if ($suggestion === '') {
            return null;
        }

        // Only surface when meaningfully different from the original.
        if (mb_strtolower($suggestion) === mb_strtolower($query)) {
            return null;
        }

        return $suggestion;
    }

    /**
     * Sanitize query string for ES. Strips bare wildcards that would expand
     * to match-all (a UX foot-gun in user-typed queries) then defers to
     * EscapeQueriesHelper for the Lucene-reserved-char escape pass, which
     * is gated on GlobalSettings::escapeQueries() per #111.
     */
    protected function sanitizeQuery(string $query): string
    {
        $stripped = strtr($query, ['*' => '', '?' => '']);

        return \AhgCore\Support\EscapeQueriesHelper::escapeForElasticsearch($stripped);
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

    /**
     * Resolve information_object IDs to titles via DB. Used by the
     * #730 collection facet (partOf.id buckets). Falls back to the
     * source-culture row when the requested culture has no title.
     */
    protected function resolveIoTitles(array $ids, string $culture): array
    {
        if (empty($ids)) {
            return [];
        }

        $titles = DB::table('information_object_i18n')
            ->whereIn('id', $ids)
            ->where('culture', '=', $culture)
            ->pluck('title', 'id')
            ->toArray();

        // Fill blanks via source culture (matches PSIS/AtoM behaviour for
        // i18n fallback - never show '[Unknown]' just because a row is
        // missing the active culture).
        $missing = array_diff($ids, array_keys($titles));
        if (! empty($missing)) {
            $fallback = DB::table('information_object_i18n as ioi')
                ->join('information_object as io', 'io.id', '=', 'ioi.id')
                ->whereIn('ioi.id', $missing)
                ->whereColumn('ioi.culture', 'io.source_culture')
                ->pluck('ioi.title', 'ioi.id')
                ->toArray();
            $titles = $titles + $fallback;
        }

        return $titles;
    }

    /**
     * Resolve a language ISO code (e.g. 'en', 'af', 'zu') to a
     * human-readable label in the active culture. Mirrors PSIS's
     * sfCultureInfo::getLanguage() in browseAction.class.php.
     */
    protected function resolveLanguageLabel(string $code, string $culture = 'en'): string
    {
        if ($code === '') {
            return '[Unknown language]';
        }

        // PHP's Locale extension is the cheapest path; fall back to the raw
        // code if intl is unavailable in some weird container build.
        if (class_exists(\Locale::class)) {
            $label = \Locale::getDisplayLanguage($code, $culture);
            if ($label !== '' && strcasecmp($label, $code) !== 0) {
                return ucfirst($label);
            }
        }

        return strtoupper($code);
    }
}
