<?php

/**
 * HeritageSearchService - Service for Heratio
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



namespace AhgHeritageManage\Services;

use Illuminate\Support\Facades\DB;

/**
 * Heritage Search Service.
 *
 * Provides full-text search across information objects with faceted filtering,
 * pagination, thumbnail resolution, and term match tracking.
 */
class HeritageSearchService
{
    private string $culture = 'en';
    private int $limit = 10;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
    }

    /**
     * Execute a search query with optional filters and pagination.
     *
     * @param string      $query   The search query string
     * @param array       $filters Associative array of filter_code => [values]
     * @param int         $page    Page number (1-based)
     * @param int         $limit   Results per page
     * @return array      Search results with total, page, pages, results, facets, term_matches, search_id
     */
    public function search(string $query, array $filters = [], int $page = 1, int $limit = 10): array
    {
        $this->limit = max(1, min(100, $limit));
        $page = max(1, $page);

        // Parse query into individual terms
        $terms = $this->parseQueryTerms($query);

        // Build the base query
        $baseQuery = $this->buildBaseQuery();

        // Apply keyword search if query is not empty
        if (!empty($terms)) {
            $baseQuery = $this->applyKeywordSearch($baseQuery, $terms);
        }

        // Apply filters
        $baseQuery = $this->applyFilters($baseQuery, $filters);

        // Get total count via a count query
        $countQuery = clone $baseQuery;
        $total = $countQuery->count();

        // Calculate pagination
        $totalPages = $total > 0 ? (int) ceil($total / $this->limit) : 0;
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $this->limit;

        // Order and paginate
        if (!empty($terms)) {
            // Relevance ordering: title matches first, then by updated_at
            $baseQuery->orderByRaw("CASE WHEN ioi.title LIKE ? THEN 0 ELSE 1 END", ['%' . $terms[0] . '%']);
        }
        $baseQuery->orderByDesc('o.updated_at');

        $rows = $baseQuery->offset($offset)->limit($this->limit)->get();

        // Format results
        $results = $this->formatResults($rows);

        // Analyze term matches
        $termMatches = $this->analyzeTermMatches($terms, $query);

        // Build facets from the full result set (without pagination)
        $facets = $this->buildFacets($query, $filters);

        return [
            'total'        => $total,
            'page'         => $page,
            'pages'        => $totalPages,
            'results'      => $results,
            'facets'       => $facets,
            'term_matches' => $termMatches,
            'search_id'    => 0,
            'suggestions'  => [],
        ];
    }

    /**
     * Parse a query string into individual search terms.
     */
    private function parseQueryTerms(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        // Extract quoted phrases and individual words
        $terms = [];
        if (preg_match_all('/"([^"]+)"/', $query, $matches)) {
            $terms = array_merge($terms, $matches[1]);
            $query = preg_replace('/"[^"]*"/', '', $query);
        }

        $words = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        $terms = array_merge($terms, $words);

        return array_filter($terms, fn ($t) => strlen($t) > 0);
    }

    /**
     * Build the base query with all standard joins.
     */
    private function buildBaseQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('slug as sl', 'io.id', '=', 'sl.object_id')
            ->join('status as pub_status', function ($join) {
                $join->on('io.id', '=', 'pub_status.object_id')
                    ->where('pub_status.type_id', '=', 158);
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('digital_object as do_master', function ($join) {
                $join->on('io.id', '=', 'do_master.object_id')
                    ->where('do_master.usage_id', '=', 140);
            })
            ->leftJoin('digital_object as do_thumb', function ($join) {
                $join->on('do_thumb.parent_id', '=', 'do_master.id')
                    ->where('do_thumb.usage_id', '=', 142);
            })
            ->leftJoin('actor_i18n as repo_ai', function ($join) {
                $join->on('io.repository_id', '=', 'repo_ai.id')
                    ->where('repo_ai.culture', '=', $this->culture);
            })
            ->leftJoin('term_i18n as lod_ti', function ($join) {
                $join->on('io.level_of_description_id', '=', 'lod_ti.id')
                    ->where('lod_ti.culture', '=', $this->culture);
            })
            ->where('pub_status.status_id', 160) // Published only
            ->where('io.id', '!=', 1)             // Exclude root
            ->select(
                'io.id',
                'sl.slug',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                'ioi.title',
                'ioi.scope_and_content',
                'ioi.alternate_title',
                'ioi.archival_history',
                'ioi.arrangement',
                DB::raw("(SELECT YEAR(ev.start_date) FROM event ev WHERE ev.object_id = io.id LIMIT 1) as io_start_year"),
                DB::raw("(SELECT YEAR(ev.end_date) FROM event ev WHERE ev.object_id = io.id LIMIT 1) as io_end_year"),
                'do_master.path as image_path',
                'do_master.name as image_name',
                'do_master.mime_type',
                'do_thumb.path as thumb_child_path',
                'do_thumb.name as thumb_child_name',
                'repo_ai.authorized_form_of_name as repository_name',
                'lod_ti.name as level_of_description',
                'o.created_at',
                'o.updated_at'
            );
    }

    /**
     * Apply keyword search conditions across multiple i18n columns.
     */
    private function applyKeywordSearch(\Illuminate\Database\Query\Builder $query, array $terms): \Illuminate\Database\Query\Builder
    {
        // OR logic: match ANY term across text columns, taxonomy terms, creator names, and identifier
        $query->where(function ($q) use ($terms) {
            foreach ($terms as $term) {
                $pattern = '%' . addcslashes($term, '%_') . '%';
                $q->orWhere(function ($inner) use ($pattern, $term) {
                    // Text column search
                    $inner->where('ioi.title', 'LIKE', $pattern)
                        ->orWhere('ioi.scope_and_content', 'LIKE', $pattern)
                        ->orWhere('ioi.alternate_title', 'LIKE', $pattern)
                        ->orWhere('ioi.archival_history', 'LIKE', $pattern)
                        ->orWhere('ioi.arrangement', 'LIKE', $pattern)
                        ->orWhere('io.identifier', 'LIKE', $pattern)
                        // Taxonomy term search (subjects, places, genres, etc.)
                        ->orWhereExists(function ($sub) use ($pattern) {
                            $sub->select(DB::raw(1))
                                ->from('object_term_relation as otr_kw')
                                ->join('term_i18n as ti_kw', 'otr_kw.term_id', '=', 'ti_kw.id')
                                ->whereColumn('otr_kw.object_id', 'io.id')
                                ->where('ti_kw.culture', $this->culture)
                                ->where('ti_kw.name', 'LIKE', $pattern);
                        })
                        // Creator/actor name search
                        ->orWhereExists(function ($sub) use ($pattern) {
                            $sub->select(DB::raw(1))
                                ->from('relation as rel_kw')
                                ->join('actor_i18n as ai_kw', 'rel_kw.object_id', '=', 'ai_kw.id')
                                ->whereColumn('rel_kw.subject_id', 'io.id')
                                ->where('ai_kw.culture', $this->culture)
                                ->where('ai_kw.authorized_form_of_name', 'LIKE', $pattern);
                        });
                });
            }
        });

        return $query;
    }

    /**
     * Apply user-selected filters to the query.
     */
    private function applyFilters(\Illuminate\Database\Query\Builder $query, array $filters): \Illuminate\Database\Query\Builder
    {
        $aliasCounter = 0;

        foreach ($filters as $filterCode => $values) {
            $values = array_filter((array) $values, fn ($v) => $v !== '' && $v !== null);
            if (empty($values)) {
                continue;
            }

            // Filter values are IDs (matching AtoM URL pattern)
            $intValues = array_map('intval', $values);

            switch ($filterCode) {
                case 'place':
                    $alias = 'otr_place_' . $aliasCounter++;

                    $query->join("object_term_relation as {$alias}", 'io.id', '=', "{$alias}.object_id")
                        ->whereIn("{$alias}.term_id", $intValues);
                    break;

                case 'subject':
                    $alias = 'otr_subj_' . $aliasCounter++;

                    $query->join("object_term_relation as {$alias}", 'io.id', '=', "{$alias}.object_id")
                        ->whereIn("{$alias}.term_id", $intValues);
                    break;

                case 'creator':
                    $relAlias = 'rel_creator_' . $aliasCounter++;

                    $query->join("relation as {$relAlias}", 'io.id', '=', "{$relAlias}.subject_id")
                        ->whereIn("{$relAlias}.object_id", $intValues);
                    break;

                case 'collection':
                    $query->whereIn('io.repository_id', $intValues);
                    break;
            }
        }

        return $query;
    }

    /**
     * Format raw database rows into result arrays.
     */
    private function formatResults($rows): array
    {
        $results = [];
        $uploadsPath = config('heratio.uploads_path', '/usr/share/nginx/archive');

        foreach ($rows as $row) {
            // Resolve thumbnail
            $thumbnail = null;
            if ($row->thumb_child_path && $row->thumb_child_name) {
                $thumbnail = rtrim($row->thumb_child_path, '/') . '/' . $row->thumb_child_name;
            } elseif ($row->image_path && $row->image_name) {
                $candidate = rtrim($row->image_path, '/') . '/' . pathinfo($row->image_name, PATHINFO_FILENAME) . '_142.jpg';
                if (file_exists($uploadsPath . $candidate)) {
                    $thumbnail = $candidate;
                }
            }

            // Build snippet from scope_and_content
            $snippet = '';
            if (!empty($row->scope_and_content)) {
                $plain = strip_tags($row->scope_and_content);
                $snippet = mb_strlen($plain) > 250 ? mb_substr($plain, 0, 250) . '...' : $plain;
            }

            // Determine media type from mime_type
            $mediaType = null;
            if ($row->mime_type) {
                if (str_starts_with($row->mime_type, 'image/')) {
                    $mediaType = 'image';
                } elseif (str_starts_with($row->mime_type, 'video/')) {
                    $mediaType = 'video';
                } elseif (str_starts_with($row->mime_type, 'audio/')) {
                    $mediaType = 'audio';
                } elseif (str_contains($row->mime_type, 'pdf')) {
                    $mediaType = 'document';
                } elseif (str_starts_with($row->mime_type, 'text/')) {
                    $mediaType = 'text';
                } elseif (str_contains($row->mime_type, 'model')) {
                    $mediaType = 'model';
                }
            }

            $results[] = [
                'id'         => $row->id,
                'title'      => $row->title ?? '[Untitled]',
                'slug'       => $row->slug,
                'url'        => url('/' . $row->slug),
                'thumbnail'  => $thumbnail,
                'snippet'    => $snippet,
                'type'       => $row->level_of_description ?? '',
                'date'       => $this->formatDateRange($row->io_start_year ?? null, $row->io_end_year ?? null),
                'collection' => $row->repository_name ?? '',
                'media_type' => $mediaType,
            ];
        }

        return $results;
    }

    /**
     * Format a date range from start/end years like AtoM does.
     */
    private function formatDateRange(?int $startYear, ?int $endYear): string
    {
        if (!$startYear && !$endYear) {
            return '';
        }
        $start = $startYear ?: 0;
        $end = $endYear ?: 0;

        // Format: "2001" or "0001-0030"
        $startStr = str_pad(abs($start), 4, '0', STR_PAD_LEFT);
        $endStr = str_pad(abs($end), 4, '0', STR_PAD_LEFT);

        if ($start === $end || !$end) {
            return $startStr;
        }
        return $startStr . '-' . $endStr;
    }

    /**
     * Analyze which search terms actually matched results in the database.
     */
    private function analyzeTermMatches(array $terms, string $query): array
    {
        if (empty($terms)) {
            return [];
        }

        $matches = [];

        foreach ($terms as $term) {
            $pattern = '%' . addcslashes($term, '%_') . '%';

            // Count how many published IOs match this term
            $count = DB::table('information_object as io')
                ->join('status as pub_status', function ($join) {
                    $join->on('io.id', '=', 'pub_status.object_id')
                        ->where('pub_status.type_id', '=', 158);
                })
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('io.id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', $this->culture);
                })
                ->where('pub_status.status_id', 160)
                ->where('io.id', '!=', 1)
                ->where(function ($q) use ($pattern) {
                    $q->where('ioi.title', 'LIKE', $pattern)
                        ->orWhere('ioi.scope_and_content', 'LIKE', $pattern)
                        ->orWhere('ioi.alternate_title', 'LIKE', $pattern)
                        ->orWhere('ioi.archival_history', 'LIKE', $pattern)
                        ->orWhere('ioi.arrangement', 'LIKE', $pattern)
                        ->orWhere('io.identifier', 'LIKE', $pattern)
                        ->orWhereExists(function ($sub) use ($pattern) {
                            $sub->select(DB::raw(1))
                                ->from('object_term_relation as otr_tm')
                                ->join('term_i18n as ti_tm', 'otr_tm.term_id', '=', 'ti_tm.id')
                                ->whereColumn('otr_tm.object_id', 'io.id')
                                ->where('ti_tm.culture', 'en')
                                ->where('ti_tm.name', 'LIKE', $pattern);
                        })
                        ->orWhereExists(function ($sub) use ($pattern) {
                            $sub->select(DB::raw(1))
                                ->from('relation as rel_tm')
                                ->join('actor_i18n as ai_tm', 'rel_tm.object_id', '=', 'ai_tm.id')
                                ->whereColumn('rel_tm.subject_id', 'io.id')
                                ->where('ai_tm.culture', 'en')
                                ->where('ai_tm.authorized_form_of_name', 'LIKE', $pattern);
                        });
                })
                ->count();

            $matches[] = [
                'term'    => $term,
                'count'   => $count,
                'matched' => $count > 0,
            ];
        }

        return $matches;
    }

    /**
     * Build facets from ALL published items (matches AtoM FilterService behaviour).
     *
     * AtoM computes facets across the entire published corpus, not just search results.
     * This ensures the sidebar always shows all available filter options.
     */
    private function buildFacets(string $query, array $currentFilters): array
    {
        // Get ALL published IO ids for facet computation (matching AtoM)
        $allIds = DB::table('information_object as io')
            ->join('status as pub_status', function ($join) {
                $join->on('io.id', '=', 'pub_status.object_id')
                    ->where('pub_status.type_id', '=', 158);
            })
            ->where('pub_status.status_id', 160)
            ->where('io.id', '!=', 1)
            ->pluck('io.id')
            ->toArray();

        if (empty($allIds)) {
            return [
                'place'      => ['label' => 'Place',      'icon' => 'bi-geo-alt',    'code' => 'place',      'show_in_search' => true, 'values' => []],
                'subject'    => ['label' => 'Subject',    'icon' => 'bi-tag',        'code' => 'subject',    'show_in_search' => true, 'values' => []],
                'creator'    => ['label' => 'Creator',    'icon' => 'bi-person',     'code' => 'creator',    'show_in_search' => true, 'values' => []],
                'collection' => ['label' => 'Collection', 'icon' => 'bi-collection', 'code' => 'collection', 'show_in_search' => true, 'values' => []],
            ];
        }

        // Chunk IDs for large result sets
        $idChunks = array_chunk($allIds, 5000);

        // Place facet (taxonomy_id=42)
        $placeFacet = $this->buildTaxonomyFacet($idChunks, 42);

        // Subject facet (taxonomy_id=35)
        $subjectFacet = $this->buildTaxonomyFacet($idChunks, 35);

        // Creator facet (from relation table)
        $creatorFacet = $this->buildCreatorFacet($idChunks);

        // Collection facet (from repository_id)
        $collectionFacet = $this->buildCollectionFacet($idChunks);

        return [
            'place'      => ['label' => 'Place',      'icon' => 'bi-geo-alt',    'code' => 'place',      'show_in_search' => true, 'values' => $placeFacet],
            'subject'    => ['label' => 'Subject',    'icon' => 'bi-tag',        'code' => 'subject',    'show_in_search' => true, 'values' => $subjectFacet],
            'creator'    => ['label' => 'Creator',    'icon' => 'bi-person',     'code' => 'creator',    'show_in_search' => true, 'values' => $creatorFacet],
            'collection' => ['label' => 'Collection', 'icon' => 'bi-collection', 'code' => 'collection', 'show_in_search' => true, 'values' => $collectionFacet],
        ];
    }

    /**
     * Build a taxonomy-based facet (Place or Subject).
     */
    private function buildTaxonomyFacet(array $idChunks, int $taxonomyId): array
    {
        $counts = collect();

        foreach ($idChunks as $chunk) {
            $partial = DB::table('object_term_relation as otr')
                ->join('term as t', 'otr.term_id', '=', 't.id')
                ->join('term_i18n as ti', 't.id', '=', 'ti.id')
                ->where('t.taxonomy_id', $taxonomyId)
                ->where('ti.culture', $this->culture)
                ->whereIn('otr.object_id', $chunk)
                ->select('t.id', 'ti.name', DB::raw('COUNT(DISTINCT otr.object_id) as cnt'))
                ->groupBy('t.id', 'ti.name')
                ->get();

            foreach ($partial as $row) {
                $key = $row->id;
                $existing = $counts->get($key, ['id' => $row->id, 'name' => $row->name, 'cnt' => 0]);
                $existing['cnt'] += $row->cnt;
                $counts->put($key, $existing);
            }
        }

        return $counts->sortByDesc(fn ($v) => $v['cnt'])
            ->take(6)
            ->map(fn ($item) => ['value' => (string) $item['id'], 'label' => $item['name'], 'count' => $item['cnt']])
            ->values()
            ->toArray();
    }

    /**
     * Build the Creator facet from the relation table.
     */
    private function buildCreatorFacet(array $idChunks): array
    {
        $counts = collect();

        foreach ($idChunks as $chunk) {
            $partial = DB::table('relation as r')
                ->join('actor_i18n as ai', 'r.object_id', '=', 'ai.id')
                ->where('ai.culture', $this->culture)
                ->whereNotNull('ai.authorized_form_of_name')
                ->where('ai.authorized_form_of_name', '!=', '')
                ->whereIn('r.subject_id', $chunk)
                ->select('ai.id', 'ai.authorized_form_of_name as name', DB::raw('COUNT(DISTINCT r.subject_id) as cnt'))
                ->groupBy('ai.id', 'ai.authorized_form_of_name')
                ->get();

            foreach ($partial as $row) {
                $key = $row->id;
                $existing = $counts->get($key, ['id' => $row->id, 'name' => $row->name, 'cnt' => 0]);
                $existing['cnt'] += $row->cnt;
                $counts->put($key, $existing);
            }
        }

        return $counts->sortByDesc(fn ($v) => $v['cnt'])
            ->take(6)
            ->map(fn ($item) => ['value' => (string) $item['id'], 'label' => $item['name'], 'count' => $item['cnt']])
            ->values()
            ->toArray();
    }

    /**
     * Build the Collection (repository) facet.
     */
    private function buildCollectionFacet(array $idChunks): array
    {
        $counts = collect();

        foreach ($idChunks as $chunk) {
            $partial = DB::table('information_object as io')
                ->join('actor_i18n as ai', function ($join) {
                    $join->on('io.repository_id', '=', 'ai.id')
                        ->where('ai.culture', '=', $this->culture);
                })
                ->whereNotNull('io.repository_id')
                ->whereNotNull('ai.authorized_form_of_name')
                ->where('ai.authorized_form_of_name', '!=', '')
                ->whereIn('io.id', $chunk)
                ->select('io.repository_id', 'ai.authorized_form_of_name as name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                ->groupBy('io.repository_id', 'ai.authorized_form_of_name')
                ->get();

            foreach ($partial as $row) {
                $key = $row->repository_id;
                $existing = $counts->get($key, ['id' => $row->repository_id, 'name' => $row->name, 'cnt' => 0]);
                $existing['cnt'] += $row->cnt;
                $counts->put($key, $existing);
            }
        }

        return $counts->sortByDesc(fn ($v) => $v['cnt'])
            ->take(6)
            ->map(fn ($item) => ['value' => (string) $item['id'], 'label' => $item['name'], 'count' => $item['cnt']])
            ->values()
            ->toArray();
    }
}
