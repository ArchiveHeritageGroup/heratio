<?php

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

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
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
                DB::raw("(SELECT evi.date FROM event ev LEFT JOIN event_i18n evi ON ev.id = evi.id AND evi.culture = '{$this->culture}' WHERE ev.object_id = io.id LIMIT 1) as io_dates"),
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
        // OR logic: match ANY term across ANY searchable column (matches AtoM behaviour)
        $query->where(function ($q) use ($terms) {
            foreach ($terms as $term) {
                $pattern = '%' . addcslashes($term, '%_') . '%';
                $q->orWhere(function ($inner) use ($pattern) {
                    $inner->where('ioi.title', 'LIKE', $pattern)
                        ->orWhere('ioi.scope_and_content', 'LIKE', $pattern)
                        ->orWhere('ioi.alternate_title', 'LIKE', $pattern)
                        ->orWhere('ioi.archival_history', 'LIKE', $pattern)
                        ->orWhere('ioi.arrangement', 'LIKE', $pattern);
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

            switch ($filterCode) {
                case 'place':
                    $alias = 'otr_place_' . $aliasCounter++;
                    $tAlias = 't_place_' . $aliasCounter++;
                    $tiAlias = 'ti_place_' . $aliasCounter++;

                    $query->join("object_term_relation as {$alias}", 'io.id', '=', "{$alias}.object_id")
                        ->join("term as {$tAlias}", "{$alias}.term_id", '=', "{$tAlias}.id")
                        ->join("term_i18n as {$tiAlias}", "{$tAlias}.id", '=', "{$tiAlias}.id")
                        ->where("{$tAlias}.taxonomy_id", 42)
                        ->where("{$tiAlias}.culture", $this->culture)
                        ->whereIn("{$tiAlias}.name", $values);
                    break;

                case 'subject':
                    $alias = 'otr_subj_' . $aliasCounter++;
                    $tAlias = 't_subj_' . $aliasCounter++;
                    $tiAlias = 'ti_subj_' . $aliasCounter++;

                    $query->join("object_term_relation as {$alias}", 'io.id', '=', "{$alias}.object_id")
                        ->join("term as {$tAlias}", "{$alias}.term_id", '=', "{$tAlias}.id")
                        ->join("term_i18n as {$tiAlias}", "{$tAlias}.id", '=', "{$tiAlias}.id")
                        ->where("{$tAlias}.taxonomy_id", 35)
                        ->where("{$tiAlias}.culture", $this->culture)
                        ->whereIn("{$tiAlias}.name", $values);
                    break;

                case 'creator':
                    $relAlias = 'rel_creator_' . $aliasCounter++;
                    $aiAlias = 'ai_creator_' . $aliasCounter++;

                    $query->join("relation as {$relAlias}", 'io.id', '=', "{$relAlias}.subject_id")
                        ->join("actor_i18n as {$aiAlias}", "{$relAlias}.object_id", '=', "{$aiAlias}.id")
                        ->where("{$aiAlias}.culture", $this->culture)
                        ->whereIn("{$aiAlias}.authorized_form_of_name", $values);
                    break;

                case 'collection':
                    $repoAlias = 'repo_filter_' . $aliasCounter++;

                    $query->join("actor_i18n as {$repoAlias}", function ($join) use ($repoAlias) {
                        $join->on('io.repository_id', '=', "{$repoAlias}.id")
                            ->where("{$repoAlias}.culture", '=', $this->culture);
                    })
                    ->whereIn("{$repoAlias}.authorized_form_of_name", $values);
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
                'date'       => $row->io_dates ?? '',
                'collection' => $row->repository_name ?? '',
                'media_type' => $mediaType,
            ];
        }

        return $results;
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
                        ->orWhere('ioi.arrangement', 'LIKE', $pattern);
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
     * Build facets from the data for the current search context.
     *
     * Returns arrays of [label, count] for Place, Subject, Creator, Collection.
     */
    private function buildFacets(string $query, array $currentFilters): array
    {
        $terms = $this->parseQueryTerms($query);

        // Build a subquery of matching IO ids to compute facets against
        $ioQuery = DB::table('information_object as io')
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
            ->select('io.id');

        if (!empty($terms)) {
            $ioQuery->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $pattern = '%' . addcslashes($term, '%_') . '%';
                    $q->orWhere(function ($inner) use ($pattern) {
                        $inner->where('ioi.title', 'LIKE', $pattern)
                            ->orWhere('ioi.scope_and_content', 'LIKE', $pattern)
                            ->orWhere('ioi.alternate_title', 'LIKE', $pattern)
                            ->orWhere('ioi.archival_history', 'LIKE', $pattern)
                            ->orWhere('ioi.arrangement', 'LIKE', $pattern);
                    });
                }
            });
        }

        $matchingIds = $ioQuery->pluck('io.id')->toArray();

        if (empty($matchingIds)) {
            return [
                'place'      => ['label' => 'Place',      'icon' => 'bi-geo-alt',    'code' => 'place',      'show_in_search' => true, 'values' => []],
                'subject'    => ['label' => 'Subject',    'icon' => 'bi-tag',        'code' => 'subject',    'show_in_search' => true, 'values' => []],
                'creator'    => ['label' => 'Creator',    'icon' => 'bi-person',     'code' => 'creator',    'show_in_search' => true, 'values' => []],
                'collection' => ['label' => 'Collection', 'icon' => 'bi-collection', 'code' => 'collection', 'show_in_search' => true, 'values' => []],
            ];
        }

        // Chunk IDs for large result sets
        $idChunks = array_chunk($matchingIds, 5000);

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
                ->select('ti.name', DB::raw('COUNT(DISTINCT otr.object_id) as cnt'))
                ->groupBy('ti.name')
                ->get();

            foreach ($partial as $row) {
                $existing = $counts->get($row->name, 0);
                $counts->put($row->name, $existing + $row->cnt);
            }
        }

        return $counts->sortByDesc(fn ($v) => $v)
            ->take(6)
            ->map(fn ($count, $name) => ['value' => $name, 'label' => $name, 'count' => $count])
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
                ->select('ai.authorized_form_of_name as name', DB::raw('COUNT(DISTINCT r.subject_id) as cnt'))
                ->groupBy('ai.authorized_form_of_name')
                ->get();

            foreach ($partial as $row) {
                $existing = $counts->get($row->name, 0);
                $counts->put($row->name, $existing + $row->cnt);
            }
        }

        return $counts->sortByDesc(fn ($v) => $v)
            ->take(6)
            ->map(fn ($count, $name) => ['value' => $name, 'label' => $name, 'count' => $count])
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
                ->select('ai.authorized_form_of_name as name', DB::raw('COUNT(DISTINCT io.id) as cnt'))
                ->groupBy('ai.authorized_form_of_name')
                ->get();

            foreach ($partial as $row) {
                $existing = $counts->get($row->name, 0);
                $counts->put($row->name, $existing + $row->cnt);
            }
        }

        return $counts->sortByDesc(fn ($v) => $v)
            ->take(6)
            ->map(fn ($count, $name) => ['value' => $name, 'label' => $name, 'count' => $count])
            ->values()
            ->toArray();
    }
}
