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
     * Full-text search results page.
     */
    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $limit = 30;

        if ($query === '') {
            return view('ahg-search::search', [
                'query' => '',
                'pager' => new SimplePager(['hits' => [], 'total' => 0, 'page' => 1, 'limit' => $limit]),
            ]);
        }

        $from = ($page - 1) * $limit;
        $raw = $this->elasticsearch->globalSearch($query, 'en', $from, $limit);

        $total = $raw['hits']['total']['value'] ?? 0;
        $hits = $this->transformHits($raw['hits']['hits'] ?? []);

        $pager = new SimplePager([
            'hits' => $hits,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);

        return view('ahg-search::search', [
            'query' => $query,
            'pager' => $pager,
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
                'title' => $i18n['title'] ?? $i18n['authorizedFormOfName'] ?? '[Untitled]',
                'slug' => $source['slug'] ?? '',
                'type' => $type,
                'identifier' => $source['identifier'] ?? null,
            ];
        }

        return response()->json($results);
    }

    /**
     * Transform raw ES hits into a standardized result format.
     */
    protected function transformHits(array $hits): array
    {
        $results = [];

        foreach ($hits as $hit) {
            $type = $this->resolveType($hit['_index']);
            $source = $hit['_source'] ?? [];
            $highlight = $hit['highlight'] ?? [];
            $i18n = $source['i18n']['en'] ?? [];

            // Build the title from highlights or source
            $title = $highlight['i18n.en.title'][0]
                ?? $highlight['i18n.en.authorizedFormOfName'][0]
                ?? $i18n['title']
                ?? $i18n['authorizedFormOfName']
                ?? '[Untitled]';

            // Plain title for link text (no HTML)
            $plainTitle = $i18n['title'] ?? $i18n['authorizedFormOfName'] ?? '[Untitled]';

            // Snippet: prefer highlighted scopeAndContent, fall back to plain
            $snippet = $highlight['i18n.en.scopeAndContent'][0]
                ?? mb_substr($i18n['scopeAndContent'] ?? '', 0, 200)
                ?: null;

            // Repository name for IO results
            $repository = null;
            if ($type === 'informationobject' && !empty($source['repository']['i18n']['en']['authorizedFormOfName'])) {
                $repository = $source['repository']['i18n']['en']['authorizedFormOfName'];
            }

            $results[] = [
                'title' => $plainTitle,
                'highlighted_title' => $title,
                'type' => $type,
                'slug' => $source['slug'] ?? '',
                'identifier' => $source['identifier'] ?? $source['referenceCode'] ?? null,
                'snippet' => $snippet,
                'repository' => $repository,
                'score' => $hit['_score'] ?? 0,
            ];
        }

        return $results;
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
