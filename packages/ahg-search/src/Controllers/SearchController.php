<?php

namespace AhgSearch\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgSearch\Services\ElasticsearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SearchController extends Controller
{
    public function __construct(
        protected ElasticsearchService $elasticsearch,
    ) {}

    /**
     * Full-text search results page with faceted filtering.
     */
    public function search(Request $request)
    {
        $query   = trim($request->input('q', ''));
        $page    = max(1, (int) $request->input('page', 1));
        $limit   = 30;
        $repo    = $request->input('repository') ? (int) $request->input('repository') : null;
        $level   = $request->input('level') ? (int) $request->input('level') : null;
        $dateFrom = $request->input('dateFrom') ?: null;
        $dateTo  = $request->input('dateTo') ?: null;
        $hasDo   = $request->has('hasDigitalObject') ? (bool) $request->input('hasDigitalObject') : null;
        $mediaType = $request->input('mediaType') ? (int) $request->input('mediaType') : null;
        $sort    = $request->input('sort', 'relevance');

        $hasFilters = $repo || $level || $dateFrom || $dateTo || $hasDo !== null || $mediaType;

        // If no query and no filters, show empty search page
        if ($query === '' && !$hasFilters) {
            return view('ahg-search::search', [
                'query'        => '',
                'pager'        => new SimplePager(['hits' => [], 'total' => 0, 'page' => 1, 'limit' => $limit]),
                'aggregations' => [],
                'activeFilters' => [],
                'sort'         => $sort,
            ]);
        }

        // Use advanced search with facets
        $results = $this->elasticsearch->advancedSearch([
            'query'           => $query,
            'repository'      => $repo,
            'level'           => $level,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'hasDigitalObject' => $hasDo,
            'mediaType'       => $mediaType,
            'sort'            => $sort,
            'page'            => $page,
            'limit'           => $limit,
        ]);

        $pager = new SimplePager([
            'hits'  => $results['hits'],
            'total' => $results['total'],
            'page'  => $page,
            'limit' => $limit,
        ]);

        // Build active filter labels for display
        $activeFilters = $this->buildActiveFilters($repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $results['aggregations'] ?? []);

        return view('ahg-search::search', [
            'query'        => $query,
            'pager'        => $pager,
            'aggregations' => $results['aggregations'] ?? [],
            'activeFilters' => $activeFilters,
            'sort'         => $sort,
        ]);
    }

    /**
     * Dedicated advanced search page with full filter form.
     */
    public function advanced(Request $request)
    {
        $repositories = $this->elasticsearch->getRepositoryList();
        $levels       = $this->elasticsearch->getLevelsOfDescription();
        $mediaTypes   = $this->elasticsearch->getMediaTypes();

        // If the form was submitted, redirect to the main search with params
        if ($request->has('submitted')) {
            $params = array_filter([
                'q'               => $request->input('q'),
                'repository'      => $request->input('repository'),
                'level'           => $request->input('level'),
                'dateFrom'        => $request->input('dateFrom'),
                'dateTo'          => $request->input('dateTo'),
                'hasDigitalObject' => $request->input('hasDigitalObject'),
                'mediaType'       => $request->input('mediaType'),
                'sort'            => $request->input('sort'),
            ], fn($v) => $v !== null && $v !== '');

            return redirect()->route('search', $params);
        }

        return view('ahg-search::advanced', [
            'repositories' => $repositories,
            'levels'       => $levels,
            'mediaTypes'   => $mediaTypes,
            'query'        => $request->input('q', ''),
            'sort'         => $request->input('sort', 'relevance'),
        ]);
    }

    /**
     * Autocomplete JSON endpoint.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $raw = $this->elasticsearch->autocomplete($query);
        $results = [];

        foreach ($raw['hits']['hits'] ?? [] as $hit) {
            $type = $this->resolveType($hit['_index']);
            $source = $hit['_source'] ?? [];
            $i18n = $source['i18n']['en'] ?? [];

            $results[] = [
                'title'      => $i18n['title'] ?? $i18n['authorizedFormOfName'] ?? '[Untitled]',
                'slug'       => $source['slug'] ?? '',
                'type'       => $type,
                'identifier' => $source['identifier'] ?? null,
            ];
        }

        return response()->json($results);
    }

    /**
     * Build active filter labels for the result page.
     */
    protected function buildActiveFilters(
        ?int $repo, ?int $level, ?string $dateFrom, ?string $dateTo,
        ?bool $hasDo, ?int $mediaType, array $aggregations
    ): array {
        $filters = [];

        if ($repo) {
            $label = '[Unknown repository]';
            foreach ($aggregations['repositories'] ?? [] as $r) {
                if ((int) $r['id'] === $repo) {
                    $label = $r['label'];
                    break;
                }
            }
            // If not found in aggs, look up directly
            if ($label === '[Unknown repository]') {
                $repos = $this->elasticsearch->getRepositoryList();
                $label = $repos[$repo] ?? $label;
            }
            $filters[] = ['param' => 'repository', 'label' => 'Repository: ' . $label];
        }

        if ($level) {
            $label = '[Unknown level]';
            foreach ($aggregations['levels'] ?? [] as $l) {
                if ((int) $l['id'] === $level) {
                    $label = $l['label'];
                    break;
                }
            }
            if ($label === '[Unknown level]') {
                $levels = $this->elasticsearch->getLevelsOfDescription();
                $label = $levels[$level] ?? $label;
            }
            $filters[] = ['param' => 'level', 'label' => 'Level: ' . $label];
        }

        if ($dateFrom) {
            $filters[] = ['param' => 'dateFrom', 'label' => 'From: ' . $dateFrom];
        }

        if ($dateTo) {
            $filters[] = ['param' => 'dateTo', 'label' => 'To: ' . $dateTo];
        }

        if ($hasDo !== null) {
            $filters[] = ['param' => 'hasDigitalObject', 'label' => $hasDo ? 'Has digital object' : 'No digital object'];
        }

        if ($mediaType) {
            $label = '[Unknown media type]';
            foreach ($aggregations['mediaTypes'] ?? [] as $m) {
                if ((int) $m['id'] === $mediaType) {
                    $label = $m['label'];
                    break;
                }
            }
            $filters[] = ['param' => 'mediaType', 'label' => 'Media: ' . $label];
        }

        return $filters;
    }

    /**
     * Determine entity type from ES index name.
     */
    protected function resolveType(string $index): string
    {
        if (str_contains($index, 'qubitinformationobject')) {
            return 'informationobject';
        }
        if (str_contains($index, 'qubitactor')) {
            return 'actor';
        }
        if (str_contains($index, 'qubitrepository')) {
            return 'repository';
        }
        if (str_contains($index, 'qubitterm')) {
            return 'term';
        }

        return 'unknown';
    }
}
