<?php

/**
 * LibraryOpacSearchService - Elasticsearch-backed faceted OPAC search
 *
 * Searches the dedicated library_item ES index (built by ahg:library-reindex).
 * Falls back to MySQL when ES is unavailable.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgLibrary\Services;

use AhgLibrary\Support\LibrarySettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LibraryOpacSearchService
{
    protected string $host;

    protected string $prefix;

    protected string $index;

    protected bool $esAvailable;

    public function __construct()
    {
        $this->host    = config('services.elasticsearch.host', 'http://localhost:9200');
        $this->prefix  = config('services.elasticsearch.prefix', 'heratio_');
        $this->index   = $this->prefix . 'library_item';
        $this->esAvailable = $this->checkEsAvailable();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Public API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Faceted search across library items.
     *
     * Accepted $filters keys:
     *   q              (string)       Free-text query
     *   material_type  (string|null)  e.g. monograph, periodical
     *   language       (string|null)   ISO 639-1 code, e.g. en, af
     *   creator        (string|null)   Creator/author name fragment
     *   publisher      (string|null)   Publisher name fragment
     *   year_from      (int|null)      Publication year lower bound
     *   year_to        (int|null)      Publication year upper bound
     *   sort           (string)        relevance | title_asc | title_desc |
     *                                   year_desc | year_asc | popular
     *   page           (int)           1-based page
     *   per_page       (int)           results per page (max 100)
     *
     * Returns array:
     *   results   (LengthAwarePaginator)  Item rows
     *   facets    (array)                 Sidebar aggregation buckets
     *   es_mode   (bool)                  Whether ES was used (vs MySQL fallback)
     */
    public function search(array $filters = []): array
    {
        $query      = trim($filters['q'] ?? '');
        $sort       = $filters['sort'] ?? 'relevance';
        $page       = max(1, (int) ($filters['page'] ?? 1));
        $perPage    = max(1, min(100, (int) ($filters['per_page'] ?? LibrarySettings::opacResultsPerPage())));

        if ($this->esAvailable) {
            return $this->searchEs($query, $filters, $sort, $page, $perPage);
        }

        return $this->searchDb($query, $filters, $sort, $page, $perPage);
    }

    /**
     * Check if ES is up (used by healthchecks).
     */
    public function isAvailable(): bool
    {
        return $this->esAvailable;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Elasticsearch path
    // ─────────────────────────────────────────────────────────────────────

    protected function searchEs(string $query, array $filters, string $sort, int $page, int $perPage): array
    {
        $culture   = app()->getLocale();
        $from      = ($page - 1) * $perPage;
        $must      = [];
        $filter    = [];

        // Free-text query
        if ($query !== '') {
            $must[] = [
                'bool' => [
                    'should' => [
                        [
                            'multi_match' => [
                                'query'  => $this->sanitizeQuery($query),
                                'fields' => [
                                    'title^4',
                                    'subtitle^2',
                                    'creators.name^3',
                                    'isbn^3',
                                    'call_number^2',
                                    'publisher^2',
                                    'series_title^2',
                                    'subjects^1',
                                    'summary',
                                    'responsibility_statement',
                                ],
                                'type'   => 'best_fields',
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                        [
                            'match_phrase_prefix' => [
                                'title' => [
                                    'query' => $query,
                                    'boost' => 5,
                                ],
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ];
        }

        // Filters
        if (! empty($filters['material_type'])) {
            $filter[] = ['term' => ['material_type.keyword' => $filters['material_type']]];
        }
        if (! empty($filters['language'])) {
            $filter[] = ['term' => ['language.keyword' => $filters['language']]];
        }
        if (! empty($filters['creator'])) {
            $filter[] = [
                'match' => [
                    'creators.name' => [
                        'query' => $filters['creator'],
                        'fuzziness' => 'AUTO',
                    ],
                ],
            ];
        }
        if (! empty($filters['publisher'])) {
            $filter[] = [
                'match' => [
                    'publisher' => [
                        'query' => $filters['publisher'],
                        'fuzziness' => 'AUTO',
                    ],
                ],
            ];
        }
        if (! empty($filters['year_from'])) {
            $filter[] = ['range' => ['publication_year' => ['gte' => (int) $filters['year_from']]]];
        }
        if (! empty($filters['year_to'])) {
            $filter[] = ['range' => ['publication_year' => ['lte' => (int) $filters['year_to']]]];
        }

        // Build query
        $boolQuery = [];
        if (! empty($must))   { $boolQuery['must']   = $must; }
        if (! empty($filter)) { $boolQuery['filter'] = $filter; }
        if (empty($boolQuery)) { $boolQuery['must'] = [['match_all' => (object) []]]; }

        // Sorting
        $sortClause = $this->buildSortClause($sort, $query);

        // Aggregations (sidebar facets — always computed across the full result set
        // by running them outside the query scope)
        $aggs = [
            'material_types' => [
                'terms' => ['field' => 'material_type.keyword', 'size' => 15],
            ],
            'languages' => [
                'terms' => ['field' => 'language.keyword', 'size' => 20],
            ],
            'creators' => [
                'terms' => ['field' => 'creators.name.keyword', 'size' => 20],
            ],
            'publishers' => [
                'terms' => ['field' => 'publisher.keyword', 'size' => 20],
            ],
            'publication_years' => [
                'date_histogram' => [
                    'field'             => 'publication_date',
                    'calendar_interval' => 'year',
                    'min_doc_count'     => 1,
                    'format'            => 'yyyy',
                ],
            ],
            'availability' => [
                'terms' => ['field' => 'availability_status.keyword', 'size' => 4],
            ],
        ];

        $body = [
            'size'  => $perPage,
            'from'  => $from,
            'sort'  => $sortClause,
            'query' => ['bool' => $boolQuery],
            'aggs'  => $aggs,
            'highlight' => [
                'fields' => [
                    'title'    => (object) [],
                    'subtitle' => (object) [],
                    'summary'  => ['fragment_size' => 150, 'number_of_fragments' => 2],
                ],
                'pre_tags'  => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
        ];

        $url = "{$this->host}/{$this->index}/_search";

        try {
            $response = Http::timeout(10)->post($url, $body);
            $result   = $response->json();
        } catch (\Exception $e) {
            Log::warning('LibraryOpacSearch ES request failed, falling back to DB: ' . $e->getMessage());

            return $this->searchDb($query, $filters, $sort, $page, $perPage);
        }

        if (! $result || ! isset($result['hits'])) {
            return $this->searchDb($query, $filters, $sort, $page, $perPage);
        }

        $total    = $result['hits']['total']['value'] ?? 0;
        $rawHits  = $result['hits']['hits'] ?? [];

        // Resolve aggregation labels
        $facets   = $this->resolveFacets($result['aggregations'] ?? []);

        // Transform hits into result objects
        $results  = $this->transformHits($rawHits, $culture);

        // Build paginator manually (ES handles its own pagination)
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'results'  => $paginator,
            'facets'   => $facets,
            'es_mode'  => true,
        ];
    }

    /**
     * Transform raw ES hits into standardised result rows.
     */
    protected function transformHits(array $hits, string $culture): array
    {
        $results = [];

        foreach ($hits as $hit) {
            $src       = $hit['_source'] ?? [];
            $hl        = $hit['highlight'] ?? [];
            $meta      = $hit['_id'];

            $title     = $src['title'] ?? '[Untitled]';
            $hlTitle   = $hl['title'][0] ?? e($title);
            $hlSummary = implode(' … ', $hl['summary'] ?? []);

            // Primary creator
            $creators = $src['creators'] ?? [];
            $primaryCreator = $creators[0]['name'] ?? null;

            // Availability from ES (indexed by ahg:library-reindex)
            $avail = $src['availability_status'] ?? 'unknown';
            $totalCopies = $src['total_copies'] ?? 0;
            $availableCopies = $src['available_copies'] ?? 0;

            $results[] = (object) [
                'id'                     => (int) $meta,
                'title'                  => $title,
                'highlighted_title'       => $hlTitle,
                'subtitle'               => $src['subtitle'] ?? null,
                'creator'                => $primaryCreator,
                'creators'               => $creators,
                'isbn'                   => $src['isbn'] ?? null,
                'issn'                   => $src['issn'] ?? null,
                'publisher'              => $src['publisher'] ?? null,
                'publication_date'       => $src['publication_date'] ?? null,
                'publication_year'       => $src['publication_year'] ?? null,
                'language'               => $src['language'] ?? null,
                'material_type'          => $src['material_type'] ?? null,
                'call_number'            => $src['call_number'] ?? null,
                'pagination'             => $src['pagination'] ?? null,
                'cover_url'              => $src['cover_url'] ?? null,
                'slug'                   => $src['slug'] ?? null,
                'summary'                => $src['summary'] ?? null,
                'highlighted_summary'    => $hlSummary ?: ($src['summary'] ?? null),
                'availability'          => $avail,
                'total_copies'          => $totalCopies,
                'available_copies'       => $availableCopies,
            ];
        }

        return $results;
    }

    /**
     * Resolve aggregation buckets into human-readable sidebar data.
     */
    protected function resolveFacets(array $rawAggs): array
    {
        $facets = [];

        // Material types
        if (! empty($rawAggs['material_types']['buckets'])) {
            $facets['material_types'] = array_map(fn ($b) => [
                'value' => $b['key'],
                'label' => ucfirst($b['key']),
                'count' => $b['doc_count'],
            ], $rawAggs['material_types']['buckets']);
        }

        // Languages — resolve ISO codes to human labels
        if (! empty($rawAggs['languages']['buckets'])) {
            $facets['languages'] = array_map(fn ($b) => [
                'value' => $b['key'],
                'label' => $this->resolveLanguageLabel($b['key']),
                'count' => $b['doc_count'],
            ], $rawAggs['languages']['buckets']);
        }

        // Creators (already string names)
        if (! empty($rawAggs['creators']['buckets'])) {
            $facets['creators'] = array_map(fn ($b) => [
                'value' => $b['key'],
                'label' => $b['key'],
                'count' => $b['doc_count'],
            ], $rawAggs['creators']['buckets']);
        }

        // Publishers (already string names)
        if (! empty($rawAggs['publishers']['buckets'])) {
            $facets['publishers'] = array_map(fn ($b) => [
                'value' => $b['key'],
                'label' => $b['key'],
                'count' => $b['doc_count'],
            ], $rawAggs['publishers']['buckets']);
        }

        // Publication years (date histogram)
        if (! empty($rawAggs['publication_years']['buckets'])) {
            $facets['publication_years'] = array_map(fn ($b) => [
                'value' => (int) substr($b['key_as_string'] ?? '', 0, 4),
                'label' => substr($b['key_as_string'] ?? '', 0, 4),
                'count' => $b['doc_count'],
            ], $rawAggs['publication_years']['buckets']);
        }

        // Availability
        if (! empty($rawAggs['availability']['buckets'])) {
            $labelMap = [
                'available'    => __('Available'),
                'checked_out'   => __('Checked out'),
                'on_hold'       => __('On hold'),
                'lost'          => __('Lost'),
                'unknown'       => __('Unknown'),
            ];
            $facets['availability'] = array_map(fn ($b) => [
                'value' => $b['key'],
                'label' => $labelMap[$b['key']] ?? ucfirst($b['key']),
                'count' => $b['doc_count'],
            ], $rawAggs['availability']['buckets']);
        }

        return $facets;
    }

    /**
     * Build ES sort clause.
     */
    protected function buildSortClause(string $sort, string $query): array
    {
        return match ($sort) {
            'title_asc'  => [['title.keyword' => 'asc'], '_score'],
            'title_desc' => [['title.keyword' => 'desc'], '_score'],
            'year_desc'  => [['publication_year' => 'desc'], '_score'],
            'year_asc'   => [['publication_year' => 'asc'], '_score'],
            'popular'    => [['checkout_count' => 'desc'], '_score'],
            default      => $query !== ''
                               ? ['_score', ['title.keyword' => 'asc']]
                               : [['title.keyword' => 'asc']],
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    //  MySQL fallback path
    // ─────────────────────────────────────────────────────────────────────

    protected function searchDb(string $query, array $filters, string $sort, int $page, int $perPage): array
    {
        $culture = app()->getLocale();

        $q = DB::table('library_item as li')
            ->leftJoin('information_object as io', 'li.information_object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('library_copy as cp', 'li.id', '=', 'cp.library_item_id')
            ->select([
                'li.id',
                'i18n.title',
                'li.subtitle',
                'li.responsibility_statement as creator',
                'li.isbn',
                'li.issn',
                'li.publisher',
                'li.publication_date',
                'li.publication_year',
                'li.language',
                'li.material_type',
                'li.call_number',
                'li.pagination',
                'li.cover_url',
                'slug.slug',
                'li.summary',
                DB::raw('COUNT(cp.id) as total_copies'),
                DB::raw("SUM(CASE WHEN cp.status = 'available' THEN 1 ELSE 0 END) as available_copies"),
            ])
            ->groupBy('li.id', 'i18n.title', 'li.subtitle', 'li.responsibility_statement',
                      'li.isbn', 'li.issn', 'li.publisher', 'li.publication_date',
                      'li.publication_year', 'li.language', 'li.material_type',
                      'li.call_number', 'li.pagination', 'li.cover_url', 'slug.slug', 'li.summary');

        // Text query
        if ($query !== '') {
            $like = '%' . $query . '%';
            $q->where(function ($w) use ($like) {
                $w->where('i18n.title', 'LIKE', $like)
                  ->orWhere('li.isbn', 'LIKE', $like)
                  ->orWhere('li.call_number', 'LIKE', $like)
                  ->orWhere('li.publisher', 'LIKE', $like)
                  ->orWhere('li.responsibility_statement', 'LIKE', $like)
                  ->orWhere('li.summary', 'LIKE', $like);
            });
        }

        // Filters
        if (! empty($filters['material_type'])) {
            $q->where('li.material_type', $filters['material_type']);
        }
        if (! empty($filters['language'])) {
            $q->where('li.language', $filters['language']);
        }
        if (! empty($filters['creator'])) {
            $q->where('li.responsibility_statement', 'LIKE', '%' . $filters['creator'] . '%');
        }
        if (! empty($filters['publisher'])) {
            $q->where('li.publisher', 'LIKE', '%' . $filters['publisher'] . '%');
        }
        if (! empty($filters['year_from'])) {
            $q->where('li.publication_year', '>=', (int) $filters['year_from']);
        }
        if (! empty($filters['year_to'])) {
            $q->where('li.publication_year', '<=', (int) $filters['year_to']);
        }

        // Sorting
        switch ($sort) {
            case 'title_asc':  $q->orderBy('i18n.title', 'asc');  break;
            case 'title_desc': $q->orderBy('i18n.title', 'desc'); break;
            case 'year_desc':  $q->orderBy('li.publication_year', 'desc'); break;
            case 'year_asc':   $q->orderBy('li.publication_year', 'asc');  break;
            case 'popular':
                $q->orderByRaw('total_copies DESC');
                break;
            default:
                $q->orderBy('i18n.title', 'asc');
        }

        // Build aggregations from DB (sidebar facets - independent queries)
        $facets = $this->buildDbFacets($query, $filters);

        // Paginate
        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        // Attach computed fields
        $results = $paginator->getCollection()->map(function ($row) {
            $avail = (int) $row->available_copies;
            $total = (int) $row->total_copies;

            return (object) [
                'id'                  => (int) $row->id,
                'title'               => $row->title ?? '[Untitled]',
                'highlighted_title'   => e($row->title ?? '[Untitled]'),
                'subtitle'            => $row->subtitle ?? null,
                'creator'             => $row->creator ?? null,
                'creators'            => $row->creator ? [['name' => $row->creator]] : [],
                'isbn'                => $row->isbn ?? null,
                'issn'                => $row->issn ?? null,
                'publisher'           => $row->publisher ?? null,
                'publication_date'   => $row->publication_date ?? null,
                'publication_year'    => $row->publication_year ?? null,
                'language'            => $row->language ?? null,
                'material_type'       => $row->material_type ?? null,
                'call_number'         => $row->call_number ?? null,
                'pagination'          => $row->pagination ?? null,
                'cover_url'           => $row->cover_url ?? null,
                'slug'                => $row->slug ?? null,
                'summary'             => $row->summary ?? null,
                'highlighted_summary' => $row->summary ?? null,
                'availability'        => $total === 0 ? 'unknown'
                                            : ($avail > 0 ? 'available' : 'checked_out'),
                'total_copies'       => $total,
                'available_copies'   => $avail,
            ];
        });

        $paginator->setCollection($results);

        return [
            'results' => $paginator,
            'facets'  => $facets,
            'es_mode' => false,
        ];
    }

    /**
     * Build sidebar facet counts from MySQL (independent queries, applied
     * after the main query's filters are removed so the user always sees
     * what facets are available from the full catalogue).
     */
    protected function buildDbFacets(string $query, array $filters): array
    {
        $base = DB::table('library_item as li')
            ->leftJoin('information_object as io', 'li.information_object_id', '=', 'io.id')
            ->leftJoin('library_copy as cp', 'li.id', '=', 'cp.library_item_id')
            ->whereNull('io.deleted_at');

        // Material types
        $mt = (clone $base)
            ->select('li.material_type as value', DB::raw('COUNT(DISTINCT li.id) as cnt'))
            ->whereNotNull('li.material_type')
            ->groupBy('li.material_type')
            ->orderByDesc('cnt')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'value' => $r->value,
                'label' => ucfirst($r->value),
                'count' => (int) $r->cnt,
            ])
            ->toArray();

        // Languages
        $lang = (clone $base)
            ->select('li.language as value', DB::raw('COUNT(DISTINCT li.id) as cnt'))
            ->whereNotNull('li.language')
            ->groupBy('li.language')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'value' => $r->value,
                'label' => $this->resolveLanguageLabel($r->value),
                'count' => (int) $r->cnt,
            ])
            ->toArray();

        // Top creators
        $cr = (clone $base)
            ->select('li.responsibility_statement as value', DB::raw('COUNT(DISTINCT li.id) as cnt'))
            ->whereNotNull('li.responsibility_statement')
            ->groupBy('li.responsibility_statement')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->value,
                'count' => (int) $r->cnt,
            ])
            ->toArray();

        // Top publishers
        $pub = (clone $base)
            ->select('li.publisher as value', DB::raw('COUNT(DISTINCT li.id) as cnt'))
            ->whereNotNull('li.publisher')
            ->groupBy('li.publisher')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->value,
                'count' => (int) $r->cnt,
            ])
            ->toArray();

        // Publication years
        $years = (clone $base)
            ->select('li.publication_year as value', DB::raw('COUNT(DISTINCT li.id) as cnt'))
            ->whereNotNull('li.publication_year')
            ->groupBy('li.publication_year')
            ->orderBy('li.publication_year', 'desc')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'value' => (int) $r->value,
                'label' => (string) $r->value,
                'count' => (int) $r->cnt,
            ])
            ->toArray();

        // Availability
        $avail = (clone $base)
            ->select(DB::raw("CASE
                WHEN SUM(CASE WHEN cp.status = 'available' THEN 1 ELSE 0 END) > 0 THEN 'available'
                WHEN SUM(CASE WHEN cp.status = 'on_hold' THEN 1 ELSE 0 END) > 0 THEN 'on_hold'
                WHEN COUNT(cp.id) > 0 THEN 'checked_out'
                ELSE 'unknown'
            END as value"), DB::raw('COUNT(DISTINCT li.id) as cnt'))
            ->groupByRaw("CASE
                WHEN SUM(CASE WHEN cp.status = 'available' THEN 1 ELSE 0 END) > 0 THEN 'available'
                WHEN SUM(CASE WHEN cp.status = 'on_hold' THEN 1 ELSE 0 END) > 0 THEN 'on_hold'
                WHEN COUNT(cp.id) > 0 THEN 'checked_out'
                ELSE 'unknown'
            END")
            ->get()
            ->map(fn ($r) => [
                'value' => $r->value,
                'label' => match ($r->value) {
                    'available'  => __('Available'),
                    'checked_out'=> __('Checked out'),
                    'on_hold'   => __('On hold'),
                    default     => __('Unknown'),
                },
                'count' => (int) $r->cnt,
            ])
            ->toArray();

        return [
            'material_types'   => $mt,
            'languages'        => $lang,
            'creators'        => $cr,
            'publishers'      => $pub,
            'publication_years' => $years,
            'availability'    => $avail,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────

    protected function checkEsAvailable(): bool
    {
        try {
            $r = Http::timeout(2)->get($this->host);

            return $r->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function sanitizeQuery(string $query): string
    {
        // Remove characters that have special meaning in query_string
        return preg_replace('/[+\-=&|><!(){}[\]^"~*?:\\/\\\\]/', ' ', $query);
    }

    protected function resolveLanguageLabel(string $code): string
    {
        static $map = [
            'en' => 'English', 'af' => 'Afrikaans', 'zu' => 'Zulu', 'xh' => 'Xhosa',
            'nso' => 'Northern Sotho', 'tn' => 'Tswana', 'st' => 'Southern Sotho',
            'ts' => 'Tsonga', 'ss' => 'Swati', 've' => 'Venda', 'nr' => 'Ndebele',
            'ss' => 'SiSwati', 'lo' => 'Sesotho', 'sem' => 'Sesotho',
            'fr' => 'French', 'de' => 'German', 'es' => 'Spanish', 'pt' => 'Portuguese',
            'it' => 'Italian', 'nl' => 'Dutch', 'ru' => 'Russian', 'zh' => 'Chinese',
            'ja' => 'Japanese', 'ko' => 'Korean', 'ar' => 'Arabic', 'he' => 'Hebrew',
            'hi' => 'Hindi', 'ta' => 'Tamil', 'ur' => 'Urdu',
        ];

        return $map[strtolower($code)] ?? strtoupper($code);
    }
}
