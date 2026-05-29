<?php

/**
 * DiscoveryController - JSON discovery API.
 *
 *   POST /api/discovery/search    {q, filters?, limit?, offset?}
 *       -> {results, total, facets, time_ms}
 *   POST /api/discovery/recommend {io_id, limit?, user_id?}
 *       -> {items, reason, source}
 *
 * Wraps ElasticsearchService::advancedSearch() (full-text + facets) and the
 * Qdrant vector-similarity path (recommendations). Both endpoints are
 * read-only and never throw - a degraded backend yields an empty result set
 * with a clear `source` marker rather than a 500.
 *
 * Optional enrichment (all config-gated, default off so prod is never slowed):
 *   - Ollama query expansion  (QueryExpansionService, ahg-ai-services)
 *   - History re-ranking       (HistoryRerankService, ahg-semantic-search)
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgSearch\Controllers;

use AhgSearch\Services\ElasticsearchService;
use AhgSearch\Services\SearchAnalyticsService;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class DiscoveryController extends Controller
{
    public function __construct(
        protected ElasticsearchService $es,
        protected VectorSearchService $vector,
        protected SearchAnalyticsService $analytics,
    ) {}

    /**
     * POST /api/discovery/search
     *
     * Input : {q: string, filters?: object, limit?: int, offset?: int, culture?: string}
     * Output: {results: array, total: int, facets: object, time_ms: int}
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'       => 'nullable|string|max:1000',
            'filters' => 'nullable|array',
            'limit'   => 'nullable|integer|min:1|max:100',
            'offset'  => 'nullable|integer|min:0',
            'culture' => 'nullable|string|max:12',
            'user_id' => 'nullable|integer|min:1',
            'expand'  => 'nullable|boolean',
        ]);

        $t0 = microtime(true);

        $q       = trim((string) ($data['q'] ?? ''));
        $filters = (array) ($data['filters'] ?? []);
        $limit   = (int) ($data['limit'] ?? 30);
        $offset  = (int) ($data['offset'] ?? 0);
        $culture = $data['culture'] ?? app()->getLocale();
        $userId  = $data['user_id'] ?? (auth()->id() ?: null);

        // Offset -> page (advancedSearch is page-based). Round down to the
        // nearest whole page that contains the requested offset.
        $page = (int) floor($offset / max(1, $limit)) + 1;

        // ── Optional query expansion (Ollama). Config-gated, default off. The
        // caller can also force-enable per request with expand=true, but only
        // when the operator has the feature switched on at all.
        $effectiveQuery = $q;
        $expansionApplied = false;
        if ($q !== '' && config('ahg-search.discovery.query_expansion', false)) {
            if (($data['expand'] ?? true) !== false) {
                $effectiveQuery = $this->expandQuery($q, $culture, $expansionApplied);
            }
        }

        $params = $this->mapFilters($filters);
        $params['query']   = $effectiveQuery;
        $params['limit']   = $limit;
        $params['page']    = $page;
        $params['culture'] = $culture;

        $result = [];
        $source = 'elasticsearch';
        try {
            $result = $this->es->advancedSearch($params);
        } catch (\Throwable $e) {
            Log::warning('discovery/search advancedSearch failed: '.$e->getMessage());
            $result = ['hits' => [], 'total' => 0, 'aggregations' => []];
            $source = 'degraded';
        }

        $results = $result['hits'] ?? [];
        $total   = (int) ($result['total'] ?? 0);
        $facets  = $result['aggregations'] ?? [];

        // ── Optional history-based re-ranking. Only when a user is known and
        // the feature is enabled. Pure in-memory reorder of the current page.
        $rerankApplied = false;
        if ($userId && config('ahg-search.discovery.history_rerank', false)) {
            $results = $this->rerank($results, (int) $userId, $rerankApplied);
        }

        $timeMs = (int) round((microtime(true) - $t0) * 1000);

        // Best-effort analytics log (never blocks the response).
        try {
            $this->analytics->recordQuery($q, $filters, $total, $timeMs, $request->ip());
        } catch (\Throwable $e) {
            // swallow - logging must never break discovery
        }

        return response()->json([
            'results' => array_values($results),
            'total'   => $total,
            'facets'  => $facets,
            'time_ms' => $timeMs,
            'meta'    => [
                'source'            => $source,
                'limit'             => $limit,
                'offset'            => $offset,
                'page'              => $page,
                'query_expanded'    => $expansionApplied,
                'history_reranked'  => $rerankApplied,
            ],
        ]);
    }

    /**
     * POST /api/discovery/recommend
     *
     * Input : {io_id: int, limit?: int, user_id?: int}
     * Output: {items: array, reason: string, source: string}
     *
     * Reuses the Qdrant vector-similarity "more like this" path
     * (VectorSearchService::searchSimilarToPoint), the same engine behind
     * GET /api/search/semantic/similar/{ioId}.
     */
    public function recommend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'io_id'   => 'required|integer|min:1',
            'limit'   => 'nullable|integer|min:1|max:50',
            'user_id' => 'nullable|integer|min:1',
        ]);

        $ioId  = (int) $data['io_id'];
        $limit = (int) ($data['limit'] ?? 12);

        $result = $this->vector->searchSimilarToPoint($ioId, $limit);

        if (! ($result['ok'] ?? false)) {
            // Vector backend unavailable - degrade gracefully (empty, not 503,
            // so a recommendations widget can hide itself silently).
            return response()->json([
                'items'  => [],
                'reason' => 'Recommendations are temporarily unavailable.',
                'source' => 'degraded',
            ]);
        }

        $items = $result['results'] ?? $result['hits'] ?? [];

        return response()->json([
            'items'  => array_values($items),
            'reason' => 'Items semantically similar to record #'.$ioId.', ranked by vector similarity.',
            'source' => 'qdrant',
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────

    /**
     * Translate the JSON `filters` object into the param keys that
     * ElasticsearchService::advancedSearch() understands. Unknown keys are
     * ignored. Both the public discovery name and the internal name are
     * accepted so callers can use whichever they know.
     */
    protected function mapFilters(array $filters): array
    {
        $out = [];

        $map = [
            'repository'        => 'repository',
            'repo'              => 'repository',
            'level'             => 'level',
            'levelOfDescription' => 'level',
            'dateFrom'          => 'dateFrom',
            'date_from'         => 'dateFrom',
            'dateTo'            => 'dateTo',
            'date_to'           => 'dateTo',
            'hasDigitalObject'  => 'hasDigitalObject',
            'has_digital_object' => 'hasDigitalObject',
            'mediaType'         => 'mediaType',
            'media_type'        => 'mediaType',
            'sort'              => 'sort',
            'languages'         => 'languages',
            'places'            => 'places',
            'subjects'          => 'subjects',
            'genres'            => 'genres',
            'names'             => 'names',
            'collection'        => 'collection',
            'geo'               => 'geo',
        ];

        foreach ($filters as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (isset($map[$k])) {
                $out[$map[$k]] = $v;
            }
        }

        // Normalise the boolean-ish digital-object filter.
        if (array_key_exists('hasDigitalObject', $out)) {
            $out['hasDigitalObject'] = filter_var($out['hasDigitalObject'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return $out;
    }

    /**
     * Run the Ollama-backed query expansion (thesaurus fallback baked into the
     * service). Soft-fails to the original query on any error so the search
     * request is never blocked by the optional enrichment.
     */
    protected function expandQuery(string $q, string $culture, bool &$applied): string
    {
        try {
            $svc = app(\AhgAiServices\Services\QueryExpansionService::class);
            $expanded = $svc->expand($q, $culture);
            if (! empty($expanded['expanded_query']) && $expanded['expanded_query'] !== $q) {
                $applied = true;

                return $expanded['expanded_query'];
            }
        } catch (\Throwable $e) {
            Log::debug('discovery query expansion skipped: '.$e->getMessage());
        }

        return $q;
    }

    /**
     * Apply history-based re-ranking via the semantic-search package service.
     * Soft-fails to the original ordering.
     */
    protected function rerank(array $results, int $userId, bool &$applied): array
    {
        try {
            $svc = app(\AhgSemanticSearch\Services\HistoryRerankService::class);
            $reranked = $svc->rerank($results, $userId);
            $applied = true;

            return $reranked;
        } catch (\Throwable $e) {
            Log::debug('discovery history re-rank skipped: '.$e->getMessage());

            return $results;
        }
    }
}
