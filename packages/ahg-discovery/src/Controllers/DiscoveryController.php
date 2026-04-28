<?php

/**
 * DiscoveryController - Controller for Heratio
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



namespace AhgDiscovery\Controllers;

use AhgCore\Constants\TermId;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Discovery Controller -- unified search/discovery across all entity types.
 * Migrated from ahgDiscoveryPlugin (actions.class.php + all service classes).
 */
class DiscoveryController extends Controller
{
    /** Cache TTL in seconds (1 hour) */
    private const CACHE_TTL = 3600;

    /**
     * Discovery landing page with search.
     * Supports both the original browse-style view (type filter, paginated)
     * and the AtoM plugin discovery mode (natural language, AJAX-driven).
     */
    public function index(Request $request)
    {
        $query = trim($request->input('q', ''));
        $type = $request->input('type', 'all');
        $page = max(1, (int) $request->input('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;
        $results = collect();
        $total = 0;
        $culture = app()->getLocale() ?: 'en';

        // Popular topics for the discovery landing
        $popularTopics = $this->getPopularTopics(8);

        if ($query !== '') {
            if ($type === 'all' || $type === 'information_object') {
                $ioResults = $this->searchInformationObjects($query, $culture, $limit, $offset);
                if ($type === 'information_object') {
                    $results = $ioResults['results'];
                    $total = $ioResults['total'];
                }
            }

            if ($type === 'all' || $type === 'actor') {
                $actorResults = $this->searchActors($query, $culture, $limit, $offset);
                if ($type === 'actor') {
                    $results = $actorResults['results'];
                    $total = $actorResults['total'];
                }
            }

            if ($type === 'all' || $type === 'repository') {
                $repoResults = $this->searchRepositories($query, $culture, $limit, $offset);
                if ($type === 'repository') {
                    $results = $repoResults['results'];
                    $total = $repoResults['total'];
                }
            }

            if ($type === 'all') {
                $combined = collect();
                foreach (['information_object' => 'Archival description', 'actor' => 'Authority record', 'repository' => 'Repository'] as $t => $label) {
                    $r = $t === 'information_object' ? ($ioResults ?? ['results' => collect()])
                       : ($t === 'actor' ? ($actorResults ?? ['results' => collect()])
                       : ($repoResults ?? ['results' => collect()]));
                    foreach ($r['results'] as $item) {
                        $item->entity_type = $label;
                        $combined->push($item);
                    }
                }
                $results = $combined->take($limit);
                $total = ($ioResults['total'] ?? 0) + ($actorResults['total'] ?? 0) + ($repoResults['total'] ?? 0);
            }
        }

        // Counts per type for sidebar
        $counts = [];
        if ($query !== '') {
            $counts['information_object'] = $ioResults['total'] ?? 0;
            $counts['actor'] = $actorResults['total'] ?? 0;
            $counts['repository'] = $repoResults['total'] ?? 0;
        }

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return view('ahg-discovery::index', compact(
            'results', 'query', 'type', 'total', 'page', 'totalPages', 'counts', 'popularTopics'
        ));
    }

    /**
     * AJAX search endpoint -- runs the full discovery search pipeline.
     *
     * GET /discovery/search?q=...&page=1&limit=20&mode=standard|semantic|vector
     *
     * Modes:
     *   standard  -- Keyword + hierarchical only (default)
     *   semantic  -- Keyword + entity/NER + hierarchical
     *   vector    -- Keyword + entity + vector + hierarchical (full pipeline)
     */
    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(50, max(5, (int) $request->input('limit', 20)));
        $mode = $request->input('mode', 'standard');

        if (!in_array($mode, ['standard', 'semantic', 'vector'])) {
            $mode = 'standard';
        }

        if (empty($query)) {
            return response()->json([
                'success' => true,
                'total' => 0,
                'collections' => [],
                'results' => [],
                'expanded' => null,
                'mode' => $mode,
            ]);
        }

        $culture = app()->getLocale() ?: 'en';
        $startTime = microtime(true);

        // Check cache first
        $cacheKey = md5($query . '|' . $culture . '|' . $page . '|' . $limit . '|' . $mode);
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            $this->logSearch($query, $cached['expanded'] ?? null, $cached['total'] ?? 0, $startTime);
            return response()->json($cached);
        }

        // Per-strategy telemetry capture — populated as we run each strategy below.
        // Schema: ['strategy_name' => ['hits' => [...], 'ms' => N]]
        $strategyResults = [];

        // Step 1: Query Expansion
        $t0 = microtime(true);
        $expanded = $this->expandQuery($query);
        $strategyResults['expansion'] = ['hits' => [], 'ms' => (int) ((microtime(true) - $t0) * 1000)];

        // Step 2: Keyword search (DB-based)
        $t0 = microtime(true);
        $keywordResults = $this->keywordSearch($expanded, $culture, 100);
        $strategyResults['keyword'] = ['hits' => $keywordResults, 'ms' => (int) ((microtime(true) - $t0) * 1000)];

        // Step 3: Entity search (semantic mode)
        $entityResults = [];
        if (in_array($mode, ['semantic', 'vector'])) {
            $t0 = microtime(true);
            $entityResults = $this->entitySearch($expanded, 200);
            $strategyResults['entity'] = ['hits' => $entityResults, 'ms' => (int) ((microtime(true) - $t0) * 1000)];
        }

        // Step 3b (NEW): Vector similarity via Qdrant — run when in semantic/vector mode
        // and the strategy is enabled; failure is silent (returns []).
        $vectorResults = [];
        if (in_array($mode, ['semantic', 'vector'])) {
            $t0 = microtime(true);
            try {
                $vectorStrategy = app(\AhgDiscovery\Services\Search\VectorSearchStrategy::class);
                if ($vectorStrategy->isEnabled()) {
                    $vectorResults = $vectorStrategy->search($query, ['culture' => $culture, 'limit' => 100]);
                }
            } catch (\Throwable $e) {
                // Strategy unavailable; carry on with non-vector results.
            }
            $strategyResults['vector'] = ['hits' => $vectorResults, 'ms' => (int) ((microtime(true) - $t0) * 1000)];
        }

        // Step 4: Hierarchical walk on top results
        $t0 = microtime(true);
        $topResults = array_merge(
            array_slice($keywordResults, 0, 10),
            array_slice($entityResults, 0, 10)
        );
        $allFoundIds = array_unique(array_merge(
            array_column($keywordResults, 'object_id'),
            array_column($entityResults, 'object_id'),
            array_column($vectorResults, 'object_id')
        ));
        $hierarchicalResults = $this->hierarchicalSearch($topResults, $allFoundIds, 20);
        $strategyResults['hierarchical'] = ['hits' => $hierarchicalResults, 'ms' => (int) ((microtime(true) - $t0) * 1000)];

        // Step 5: Merge & Rank (existing 3-way merger)
        $t0 = microtime(true);
        $merged = $this->mergeResults($keywordResults, $entityResults, $hierarchicalResults);

        // Step 5b (NEW): Reciprocal Rank Fusion with vector results — boosts items
        // that appear in both the merged keyword pipeline AND the vector index.
        if (! empty($vectorResults)) {
            $merged = $this->rrfBoostWithVector($merged, $vectorResults);
        }
        $strategyResults['merge'] = ['hits' => $merged, 'ms' => (int) ((microtime(true) - $t0) * 1000)];

        // Step 6: Enrich with metadata (paginated slice)
        $totalResults = count($merged);
        $offset = ($page - 1) * $limit;
        $pageResults = array_slice($merged, $offset, $limit);
        $enrichedResults = $this->enrichResults($pageResults, $culture, $limit);

        // Group enriched results by fonds
        $collections = $this->groupByFonds($enrichedResults, $culture);

        $response = [
            'success' => true,
            'total' => $totalResults,
            'page' => $page,
            'limit' => $limit,
            'pages' => max(1, (int) ceil($totalResults / $limit)),
            'mode' => $mode,
            'collections' => $collections,
            'results' => $enrichedResults,
            'expanded' => [
                'keywords' => $expanded['keywords'],
                'phrases' => $expanded['phrases'],
                'synonyms' => $expanded['synonyms'],
                'dateRange' => $expanded['dateRange'],
                'entityTerms' => array_column($expanded['entityTerms'], 'value'),
            ],
        ];

        $this->putInCache($cacheKey, $query, $response);

        // Rich telemetry — captures per-strategy timings + ranks for ablation.
        try {
            $logId = app(\AhgDiscovery\Services\DiscoveryQueryLogger::class)->logQuery([
                'query'            => $query,
                'user_id'          => auth()->id(),
                'session_id'       => $request->session()->getId(),
                'expanded'         => $expanded,
                'keywords'         => $expanded['keywords'] ?? [],
                'strategy_results' => $strategyResults,
                'merged_ids'       => array_column($merged, 'object_id'),
                'final_ids'        => array_column($enrichedResults, 'object_id'),
                'response_ms'      => (int) ((microtime(true) - $startTime) * 1000),
            ]);
            if ($logId) {
                $response['log_id'] = $logId; // exposed so the JS click handler can correlate
            }
        } catch (\Throwable $e) {
            $this->logSearch($query, $expanded, $totalResults, $startTime); // legacy fallback
        }

        return response()->json($response);
    }

    /**
     * Reciprocal Rank Fusion boost. Reorders the existing merged-results array
     * by adding a 1/(60+rank_in_vector) bonus to items that also appeared in the
     * vector hit list. Items with no vector signal keep their original score.
     *
     * Standard RRF k=60 (Cormack et al., SIGIR 2009).
     *
     * @param array $merged          Output of {@see mergeResults()}
     * @param array $vectorResults   Output of VectorSearchStrategy::search()
     * @return array reordered merged-results array
     */
    private function rrfBoostWithVector(array $merged, array $vectorResults): array
    {
        if (empty($vectorResults)) {
            return $merged;
        }
        $k = 60;
        $vectorRank = [];
        foreach ($vectorResults as $i => $v) {
            $vectorRank[(int) $v['object_id']] = $i;
        }

        // Add vector items the merger missed entirely so they can rank into the result set.
        $existing = array_column($merged, 'object_id');
        $existingSet = array_flip(array_map('intval', $existing));
        foreach ($vectorResults as $v) {
            $id = (int) $v['object_id'];
            if (! isset($existingSet[$id])) {
                $merged[] = [
                    'object_id'     => $id,
                    'score'         => 0.0,
                    'match_reasons' => ['VECTOR'],
                    'highlights'    => [],
                    'slug'          => $v['slug'] ?? null,
                ];
            }
        }

        // Recompute scores with vector boost, then re-sort.
        foreach ($merged as &$row) {
            $id = (int) $row['object_id'];
            if (isset($vectorRank[$id])) {
                $row['score'] = (float) $row['score'] + (1.0 / ($k + $vectorRank[$id] + 1));
                if (! in_array('VECTOR', $row['match_reasons'] ?? [])) {
                    $row['match_reasons'][] = 'VECTOR';
                }
            }
        }
        unset($row);

        usort($merged, fn($a, $b) => $b['score'] <=> $a['score']);
        return $merged;
    }

    /**
     * AJAX autocomplete suggestions.
     *
     * GET /discovery/suggest?q=...&limit=10
     */
    public function suggest(Request $request)
    {
        $query = trim($request->input('q', ''));
        $culture = app()->getLocale() ?: 'en';
        $limit = min(20, max(1, (int) $request->input('limit', 10)));

        if (strlen($query) < 2) {
            return response()->json(['success' => true, 'suggestions' => []]);
        }

        $suggestions = collect();

        // IO titles
        $ios = DB::table('information_object_i18n as ioi')
            ->leftJoin('slug', 'ioi.id', '=', 'slug.object_id')
            ->where('ioi.culture', $culture)
            ->where('ioi.title', 'like', "%{$query}%")
            ->where('ioi.id', '!=', 1)
            ->select('ioi.title as label', 'slug.slug', DB::raw("'Archival description' as type"))
            ->orderByRaw("CASE WHEN ioi.title LIKE ? THEN 0 ELSE 1 END", [$query . '%'])
            ->limit(5)->get();
        $suggestions = $suggestions->merge($ios);

        // Actor names
        $actors = DB::table('actor_i18n as ai')
            ->leftJoin('slug', 'ai.id', '=', 'slug.object_id')
            ->where('ai.culture', $culture)
            ->where('ai.authorized_form_of_name', 'like', "%{$query}%")
            ->where('ai.id', '!=', 1)
            ->select('ai.authorized_form_of_name as label', 'slug.slug', DB::raw("'Authority record' as type"))
            ->limit(5)->get();
        $suggestions = $suggestions->merge($actors);

        // Subject/Place terms
        if ($suggestions->count() < $limit) {
            $remaining = $limit - $suggestions->count();
            $terms = DB::table('term_i18n')
                ->join('term', 'term.id', '=', 'term_i18n.id')
                ->leftJoin('slug', 'term.id', '=', 'slug.object_id')
                ->where('term_i18n.culture', $culture)
                ->where('term_i18n.name', 'like', "%{$query}%")
                ->whereIn('term.taxonomy_id', [35, 42])
                ->select('term_i18n.name as label', 'slug.slug', DB::raw("'Term' as type"))
                ->limit($remaining)->get();
            $suggestions = $suggestions->merge($terms);
        }

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions->take($limit)->values(),
        ]);
    }

    /**
     * AJAX click tracking.
     *
     * POST /discovery/click
     */
    public function click(Request $request)
    {
        $query     = trim($request->input('query', ''));
        $objectId  = (int) $request->input('object_id', 0);
        $sessionId = $request->input('session_id', '');
        $logId     = (int) $request->input('log_id', 0);
        $dwellMs   = $request->has('dwell_ms') ? (int) $request->input('dwell_ms') : null;

        if ($objectId <= 0) {
            return response()->json(['success' => false, 'error' => 'Missing object_id'], 400);
        }

        $logger = app(\AhgDiscovery\Services\DiscoveryQueryLogger::class);
        $ok = $logger->logClick(
            $logId > 0 ? $logId : null,
            $objectId,
            $sessionId !== '' ? $sessionId : null
        );

        // Legacy path — older callers that only pass query+session and no log_id.
        // Falls through to a query-text match for back-compat. New rows now
        // record clicked_at as well as clicked_object.
        if (! $ok && $query !== '' && $sessionId !== '') {
            try {
                DB::table('ahg_discovery_log')
                    ->where('query_text', $query)
                    ->where('session_id', $sessionId)
                    ->whereNull('clicked_object')
                    ->orderByDesc('created_at')
                    ->limit(1)
                    ->update([
                        'clicked_object' => $objectId,
                        'clicked_at'     => now(),
                    ]);
            } catch (\Throwable $e) {
                // Table may not exist yet
            }
        }

        if ($dwellMs !== null && $logId > 0) {
            $logger->logDwell($logId, $dwellMs);
        }

        return response()->json(['success' => true]);
    }

    /**
     * AJAX popular topics.
     *
     * GET /discovery/popular?limit=8
     */
    public function popular(Request $request)
    {
        $limit = min(20, max(1, (int) $request->input('limit', 8)));
        return response()->json([
            'success' => true,
            'topics' => $this->getPopularTopics($limit),
        ]);
    }

    // =====================================================================
    // PageIndex LLM Retrieval Methods
    // =====================================================================

    /**
     * PageIndex search page.
     *
     * GET /discovery/pageindex?q=...&type=ead|pdf|rico|all
     */
    public function pageindex(Request $request)
    {
        $query = trim($request->input('q', ''));
        $type = $request->input('type', 'all');
        $results = [];
        $totalMatches = 0;

        if (!empty($query)) {
            $service = new \AhgDiscovery\Services\PageIndexService();
            $objectType = ($type && $type !== 'all') ? $type : null;
            $searchResult = $service->searchAll($query, $objectType);
            $results = $searchResult['results'] ?? [];
            $totalMatches = $searchResult['total_matches'] ?? 0;

            // Enrich results with IO titles from information_object_i18n
            $culture = app()->getLocale();
            foreach ($results as &$treeResult) {
                $title = DB::table('information_object_i18n')
                    ->where('id', $treeResult['object_id'])
                    ->where('culture', $culture)
                    ->value('title');
                $treeResult['record_title'] = $title ?? "Record #{$treeResult['object_id']}";
            }
            unset($treeResult);
        }

        // Get index stats
        $stats = [
            'total' => 0,
            'by_type' => [],
            'by_status' => [],
        ];

        try {
            $stats['total'] = DB::table('ahg_pageindex_tree')->count();

            $byType = DB::table('ahg_pageindex_tree')
                ->selectRaw('object_type, COUNT(*) as cnt')
                ->groupBy('object_type')
                ->pluck('cnt', 'object_type')
                ->toArray();
            $stats['by_type'] = $byType;

            $byStatus = DB::table('ahg_pageindex_tree')
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();
            $stats['by_status'] = $byStatus;
        } catch (\Exception $e) {
            // Table may not exist
        }

        return view('ahg-discovery::pageindex', [
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'totalMatches' => $totalMatches,
            'stats' => $stats,
        ]);
    }

    /**
     * PageIndex API endpoint (JSON).
     *
     * GET/POST /discovery/pageindex/api
     * Accepts: {query, tree_id} or {query, object_id, object_type}
     */
    public function pageindexApi(Request $request)
    {
        $query = trim($request->input('query', ''));
        $treeId = (int) $request->input('tree_id', 0);
        $objectId = (int) $request->input('object_id', 0);
        $objectType = $request->input('object_type', '');

        if (empty($query)) {
            return response()->json(['success' => false, 'error' => 'Query is required'], 400);
        }

        $service = new \AhgDiscovery\Services\PageIndexService();
        $userId = auth()->id();

        if ($treeId > 0) {
            // Query a specific tree
            $result = $service->query($treeId, $query, $userId);

            return response()->json($result);
        }

        if ($objectId > 0 && !empty($objectType)) {
            // Find tree for this object, then query it
            $status = $service->getStatus($objectId, $objectType);

            if (!$status || $status['status'] !== 'ready') {
                return response()->json([
                    'success' => false,
                    'error' => 'No ready index found for this object. Build the index first.',
                ]);
            }

            $result = $service->query($status['tree_id'], $query, $userId);

            return response()->json($result);
        }

        // Search all trees
        $type = $request->input('type');
        $objectTypeFilter = ($type && $type !== 'all') ? $type : null;
        $result = $service->searchAll($query, $objectTypeFilter, 20, $userId);

        // Enrich with titles
        $culture = app()->getLocale();
        foreach ($result['results'] as &$treeResult) {
            $title = DB::table('information_object_i18n')
                ->where('id', $treeResult['object_id'])
                ->where('culture', $culture)
                ->value('title');
            $treeResult['record_title'] = $title ?? "Record #{$treeResult['object_id']}";
        }
        unset($treeResult);

        return response()->json($result);
    }

    /**
     * Build index status page.
     *
     * GET /discovery/build?id=N&type=ead
     */
    public function build(Request $request)
    {
        $objectId = (int) $request->input('id', 0);
        $objectType = $request->input('type', 'ead');

        if ($objectId <= 0) {
            return redirect()->route('ahgdiscovery.pageindex')
                ->with('error', 'Object ID is required.');
        }

        $service = new \AhgDiscovery\Services\PageIndexService();
        $status = $service->getStatus($objectId, $objectType);
        $tree = null;

        if ($status && $status['status'] === 'ready') {
            $tree = $service->getTree($objectId, $objectType);
        }

        // Get the record title
        $culture = app()->getLocale();
        $title = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', $culture)
            ->value('title');

        $identifier = DB::table('information_object')
            ->where('id', $objectId)
            ->value('identifier');

        return view('ahg-discovery::build', [
            'objectId' => $objectId,
            'objectType' => $objectType,
            'status' => $status,
            'tree' => $tree,
            'title' => $title ?? "Record #{$objectId}",
            'identifier' => $identifier ?? '',
        ]);
    }

    /**
     * Trigger index build (AJAX).
     *
     * POST /discovery/build
     */
    public function buildStore(Request $request)
    {
        $objectId = (int) $request->input('object_id', 0);
        $objectType = $request->input('object_type', 'ead');

        if ($objectId <= 0) {
            return response()->json(['success' => false, 'error' => 'Object ID is required'], 400);
        }

        if (!in_array($objectType, ['ead', 'pdf', 'rico'], true)) {
            return response()->json(['success' => false, 'error' => 'Invalid object type'], 400);
        }

        $service = new \AhgDiscovery\Services\PageIndexService();
        $culture = app()->getLocale();
        $result = $service->buildTree($objectId, $objectType, $culture);

        return response()->json($result);
    }

    // =====================================================================
    // Entity-type search methods (browse-style search)
    // =====================================================================

    private function searchInformationObjects(string $query, string $culture, int $limit, int $offset): array
    {
        $base = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', '!=', 1)
            ->where(function ($q) use ($query) {
                $q->where('ioi.title', 'like', "%{$query}%")
                  ->orWhere('io.identifier', 'like', "%{$query}%")
                  ->orWhere('ioi.scope_and_content', 'like', "%{$query}%");
            });

        $total = (clone $base)->count();
        $results = $base->select('io.id', 'io.identifier', 'ioi.title as label', 'slug.slug',
                                 DB::raw("'Archival description' as entity_type"))
            ->orderBy('ioi.title')->offset($offset)->limit($limit)->get();

        return ['results' => $results, 'total' => $total];
    }

    private function searchActors(string $query, string $culture, int $limit, int $offset): array
    {
        $base = DB::table('actor as a')
            ->join('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', '!=', 1)
            ->where('ai.authorized_form_of_name', 'like', "%{$query}%");

        $total = (clone $base)->count();
        $results = $base->select('a.id', 'ai.authorized_form_of_name as label', 'slug.slug',
                                 DB::raw("'Authority record' as entity_type"))
            ->orderBy('ai.authorized_form_of_name')->offset($offset)->limit($limit)->get();

        return ['results' => $results, 'total' => $total];
    }

    private function searchRepositories(string $query, string $culture, int $limit, int $offset): array
    {
        $base = DB::table('repository as r')
            ->join('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('r.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
            ->where('ai.authorized_form_of_name', 'like', "%{$query}%");

        $total = (clone $base)->count();
        $results = $base->select('r.id', 'ai.authorized_form_of_name as label', 'slug.slug',
                                 DB::raw("'Repository' as entity_type"))
            ->orderBy('ai.authorized_form_of_name')->offset($offset)->limit($limit)->get();

        return ['results' => $results, 'total' => $total];
    }

    // =====================================================================
    // Discovery pipeline: Query expansion, keyword search, NER, hierarchy
    // Migrated from ahgDiscoveryPlugin service classes
    // =====================================================================

    private const STOP_WORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'is', 'was', 'are', 'were', 'be', 'been',
        'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'may', 'might', 'shall', 'can', 'this', 'that',
        'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
        'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'its', 'our',
        'their', 'what', 'which', 'who', 'whom', 'when', 'where', 'why', 'how',
        'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some',
        'such', 'no', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very',
        'just', 'about', 'above', 'after', 'again', 'against', 'any', 'because',
        'before', 'below', 'between', 'during', 'into', 'through', 'under',
        'until', 'up', 'down', 'out', 'off', 'over', 'then', 'once', 'here',
        'there', 'also', 'if', 'tell', 'show', 'find', 'get', 'give',
        'know', 'look', 'make', 'want', 'let', 'like',
        'anything', 'related', 'regarding', 'concerning', 'records',
        'documents', 'materials', 'collections', 'information', 'details',
    ];

    /**
     * Expand a natural language query into structured search terms.
     * Migrated from AhgDiscovery\Services\QueryExpander.
     */
    private function expandQuery(string $query): array
    {
        $original = trim($query);
        $normalized = mb_strtolower($original);

        $dateRange = $this->extractDateRange($normalized);
        $phrases = $this->extractPhrases($original);
        $keywords = $this->extractKeywords($normalized, $dateRange);
        $entityTerms = $this->identifyEntityTerms($original, $phrases);
        $synonyms = $this->lookupSynonyms(array_merge($keywords, array_map('strtolower', $phrases)));

        return [
            'original' => $original,
            'keywords' => $keywords,
            'phrases' => $phrases,
            'synonyms' => $synonyms,
            'dateRange' => $dateRange,
            'entityTerms' => $entityTerms,
        ];
    }

    private function extractDateRange(string $text): ?array
    {
        if (preg_match('/\b(\d{3})0s\b/', $text, $m)) {
            $decade = (int) ($m[1] . '0');
            return ['start' => $decade, 'end' => $decade + 9, 'label' => $m[0]];
        }
        if (preg_match('/\b(\d{1,2})(st|nd|rd|th)\s+century\b/i', $text, $m)) {
            $c = (int) $m[1];
            return ['start' => ($c - 1) * 100, 'end' => ($c * 100) - 1, 'label' => $m[0]];
        }
        if (preg_match('/\b(\d{4})\s*[-\x{2013}\x{2014}]\s*(\d{4})\b/u', $text, $m) ||
            preg_match('/\b(\d{4})\s+to\s+(\d{4})\b/', $text, $m)) {
            return ['start' => (int) $m[1], 'end' => (int) $m[2], 'label' => $m[0]];
        }
        if (preg_match('/\bbefore\s+(\d{4})\b/', $text, $m)) {
            return ['start' => null, 'end' => (int) $m[1], 'label' => $m[0]];
        }
        if (preg_match('/\bafter\s+(\d{4})\b/', $text, $m)) {
            return ['start' => (int) $m[1], 'end' => null, 'label' => $m[0]];
        }
        if (preg_match('/\b(1[0-9]{3}|20[0-2]\d)\b/', $text, $m)) {
            return ['start' => (int) $m[1], 'end' => (int) $m[1], 'label' => $m[0]];
        }
        return null;
    }

    private function extractPhrases(string $text): array
    {
        $phrases = [];
        if (preg_match_all('/"([^"]+)"/', $text, $matches)) {
            $phrases = array_merge($phrases, $matches[1]);
        }
        if (preg_match_all('/\b([A-Z][a-z]+(?:\s+(?:[A-Z][a-z]+|of|the|and|for|in|de|van|von|du))*\s+[A-Z][a-z]+)\b/', $text, $matches)) {
            foreach ($matches[1] as $phrase) {
                $capCount = preg_match_all('/[A-Z][a-z]+/', $phrase);
                if ($capCount >= 2 && !in_array($phrase, $phrases)) {
                    $phrases[] = $phrase;
                }
            }
        }
        return array_values(array_unique($phrases));
    }

    private function extractKeywords(string $normalized, ?array $dateRange): array
    {
        $text = $normalized;
        if ($dateRange) {
            $text = str_ireplace($dateRange['label'], '', $text);
        }
        $text = preg_replace('/[^\w\s-]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        $keywords = [];
        foreach (explode(' ', $text) as $token) {
            $token = trim($token, '-');
            if (strlen($token) < 2 || in_array($token, self::STOP_WORDS) || is_numeric($token)) {
                continue;
            }
            $keywords[] = $token;
        }
        return array_values(array_unique($keywords));
    }

    private function identifyEntityTerms(string $original, array $phrases): array
    {
        $terms = [];
        foreach ($phrases as $phrase) {
            $terms[] = ['value' => $phrase, 'type' => null];
        }
        $words = preg_split('/\s+/', $original);
        for ($i = 1; $i < count($words); $i++) {
            $word = trim($words[$i], '.,;:!?"\'()[]');
            if (strlen($word) > 2 && preg_match('/^[A-Z]/', $word) && !in_array(strtolower($word), self::STOP_WORDS)) {
                $inPhrase = false;
                foreach ($phrases as $phrase) {
                    if (stripos($phrase, $word) !== false) { $inPhrase = true; break; }
                }
                if (!$inPhrase) {
                    $terms[] = ['value' => $word, 'type' => null];
                }
            }
        }
        return $terms;
    }

    private function lookupSynonyms(array $terms): array
    {
        if (empty($terms)) {
            return [];
        }
        try {
            $exists = DB::select("SHOW TABLES LIKE 'ahg_thesaurus_synonym'");
            if (empty($exists)) {
                return [];
            }
            $synonyms = [];
            foreach ($terms as $term) {
                $term = trim($term);
                if (strlen($term) < 2) {
                    continue;
                }
                $termRow = DB::table('ahg_thesaurus_term')->where('term', $term)->first();
                if (!$termRow) {
                    continue;
                }
                $syns = DB::table('ahg_thesaurus_synonym')
                    ->where('term_id', $termRow->id)
                    ->whereIn('relationship', ['synonym', 'use_for', 'related'])
                    ->orderByDesc('weight')->limit(5)->get();
                foreach ($syns as $syn) {
                    $synTerm = DB::table('ahg_thesaurus_term')->where('id', $syn->synonym_term_id)->value('term');
                    if ($synTerm && !in_array($synTerm, $synonyms) && !in_array($synTerm, $terms)) {
                        $synonyms[] = $synTerm;
                    }
                }
                $reverseSyns = DB::table('ahg_thesaurus_synonym')
                    ->where('synonym_term_id', $termRow->id)->where('is_bidirectional', 1)
                    ->whereIn('relationship', ['synonym', 'use_for', 'related'])
                    ->orderByDesc('weight')->limit(5)->get();
                foreach ($reverseSyns as $syn) {
                    $synTerm = DB::table('ahg_thesaurus_term')->where('id', $syn->term_id)->value('term');
                    if ($synTerm && !in_array($synTerm, $synonyms) && !in_array($synTerm, $terms)) {
                        $synonyms[] = $synTerm;
                    }
                }
            }
            return $synonyms;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * DB-based keyword search (no Elasticsearch dependency).
     * Searches information_object_i18n title + scope_and_content using LIKE
     * with relevance scoring.
     */
    private function keywordSearch(array $expanded, string $culture, int $limit = 100): array
    {
        $searchTerms = array_merge($expanded['keywords'], $expanded['phrases']);
        if (empty($searchTerms)) {
            return [];
        }

        try {
            $query = DB::table('information_object_i18n as ioi')
                ->join('information_object as io', 'ioi.id', '=', 'io.id')
                ->select('ioi.id as object_id')
                ->where('ioi.culture', $culture)
                ->where('io.parent_id', '>', 1);

            $query->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('ioi.title', 'LIKE', '%' . $term . '%');
                    $q->orWhere('ioi.scope_and_content', 'LIKE', '%' . $term . '%');
                }
            });

            // Score: title matches weighted higher
            $scoreParts = [];
            foreach ($searchTerms as $term) {
                $escaped = addslashes($term);
                $scoreParts[] = "(CASE WHEN ioi.title LIKE '%" . $escaped . "%' THEN 3 ELSE 0 END)";
                $scoreParts[] = "(CASE WHEN ioi.scope_and_content LIKE '%" . $escaped . "%' THEN 1 ELSE 0 END)";
            }
            $scoreExpr = implode(' + ', $scoreParts);

            $results = $query
                ->selectRaw("({$scoreExpr}) as relevance_score")
                ->orderByDesc('relevance_score')
                ->limit($limit)
                ->get();

            return $results->map(function ($row) {
                return [
                    'object_id' => (int) $row->object_id,
                    'es_score' => (float) $row->relevance_score,
                    'highlights' => [],
                    'slug' => null,
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * NER Entity search -- searches ahg_ner_entity table.
     * Migrated from AhgDiscovery\Services\EntitySearchStrategy.
     */
    private function entitySearch(array $expanded, int $limit = 200): array
    {
        $searchTerms = [];
        if (!empty($expanded['entityTerms'])) {
            foreach ($expanded['entityTerms'] as $entity) {
                $searchTerms[] = $entity['value'];
            }
        }
        if (!empty($expanded['phrases'])) {
            foreach ($expanded['phrases'] as $phrase) {
                if (!in_array($phrase, $searchTerms)) {
                    $searchTerms[] = $phrase;
                }
            }
        }
        if (!empty($expanded['keywords'])) {
            foreach ($expanded['keywords'] as $keyword) {
                if (strlen($keyword) > 4 && !in_array($keyword, $searchTerms)) {
                    $searchTerms[] = $keyword;
                }
            }
        }
        if (empty($searchTerms)) {
            return [];
        }

        try {
            $exists = DB::select("SHOW TABLES LIKE 'ahg_ner_entity'");
            if (empty($exists)) {
                return [];
            }
            return DB::table('ahg_ner_entity')
                ->select(
                    'object_id',
                    DB::raw('COUNT(*) as match_count'),
                    DB::raw("GROUP_CONCAT(DISTINCT entity_type SEPARATOR ',') as entity_types"),
                    DB::raw("GROUP_CONCAT(DISTINCT entity_value SEPARATOR '||') as matched_values")
                )
                ->where(function ($q) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $q->orWhere('entity_value', 'LIKE', '%' . $term . '%');
                    }
                })
                ->whereIn('status', ['approved', 'pending'])
                ->groupBy('object_id')
                ->orderByDesc('match_count')
                ->limit($limit)
                ->get()
                ->map(fn($row) => [
                    'object_id' => (int) $row->object_id,
                    'match_count' => (int) $row->match_count,
                    'entity_types' => $row->entity_types,
                    'matched_values' => explode('||', $row->matched_values),
                ])
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Hierarchical walk: find siblings/children of top results.
     * Migrated from AhgDiscovery\Services\HierarchicalStrategy.
     */
    private function hierarchicalSearch(array $topResults, array $alreadyFound = [], int $topN = 20): array
    {
        $results = [];
        $processed = array_flip($alreadyFound);
        $toWalk = array_slice($topResults, 0, $topN);
        if (empty($toWalk)) {
            return [];
        }

        $walkIds = array_column($toWalk, 'object_id');
        $highLevels = $this->getHighLevelIds();

        try {
            $nodes = DB::table('information_object')
                ->select('id', 'parent_id', 'level_of_description_id')
                ->whereIn('id', $walkIds)
                ->get()->keyBy('id');

            foreach ($nodes as $node) {
                $objectId = (int) $node->id;
                $parentId = (int) $node->parent_id;
                if ($parentId <= 1) {
                    continue;
                }

                $siblings = DB::table('information_object')
                    ->where('parent_id', $parentId)
                    ->where('id', '!=', $objectId)
                    ->whereNotIn('id', $alreadyFound)
                    ->limit(5)->pluck('id')->toArray();
                foreach ($siblings as $sibId) {
                    $sibId = (int) $sibId;
                    if (!isset($processed[$sibId])) {
                        $results[] = ['object_id' => $sibId, 'relationship_type' => 'sibling', 'via_object_id' => $objectId];
                        $processed[$sibId] = true;
                    }
                }

                if (in_array((int) $node->level_of_description_id, $highLevels)) {
                    $children = DB::table('information_object')
                        ->where('parent_id', $objectId)
                        ->whereNotIn('id', $alreadyFound)
                        ->limit(10)->pluck('id')->toArray();
                    foreach ($children as $childId) {
                        $childId = (int) $childId;
                        if (!isset($processed[$childId])) {
                            $results[] = ['object_id' => $childId, 'relationship_type' => 'child', 'via_object_id' => $objectId];
                            $processed[$childId] = true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Degrade gracefully
        }
        return $results;
    }

    private function getHighLevelIds(): array
    {
        static $ids = null;
        if ($ids !== null) {
            return $ids;
        }
        try {
            $ids = DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', 34)
                ->whereIn('term_i18n.name', ['Fonds', 'Sub-fonds', 'Series', 'Collection', 'Sub-series'])
                ->pluck('term.id')->toArray();
        } catch (\Exception $e) {
            $ids = [227, 228, 229, 231];
        }
        return $ids;
    }

    /**
     * Merge & rank results from all strategies.
     * Migrated from AhgDiscovery\Services\ResultMerger.
     */
    private function mergeResults(array $keywordResults, array $entityResults, array $hierarchicalResults): array
    {
        $map = [];

        foreach ($keywordResults as $r) {
            $id = $r['object_id'];
            if (!isset($map[$id])) {
                $map[$id] = ['sources' => [], 'reasons' => [], 'data' => []];
            }
            $map[$id]['sources']['keyword'] = $r;
            $map[$id]['reasons'][] = 'KEYWORD';
            if (!empty($r['highlights'])) { $map[$id]['data']['highlights'] = $r['highlights']; }
            if (!empty($r['slug'])) { $map[$id]['data']['slug'] = $r['slug']; }
        }

        foreach ($entityResults as $r) {
            $id = $r['object_id'];
            if (!isset($map[$id])) {
                $map[$id] = ['sources' => [], 'reasons' => [], 'data' => []];
            }
            $map[$id]['sources']['entity'] = $r;
            if (!empty($r['matched_values'])) {
                foreach (array_slice($r['matched_values'], 0, 3) as $val) {
                    $reason = 'ENTITY:' . $val;
                    if (!in_array($reason, $map[$id]['reasons'])) { $map[$id]['reasons'][] = $reason; }
                }
            } elseif (!in_array('ENTITY', $map[$id]['reasons'])) {
                $map[$id]['reasons'][] = 'ENTITY';
            }
        }

        foreach ($hierarchicalResults as $r) {
            $id = $r['object_id'];
            if (!isset($map[$id])) {
                $map[$id] = ['sources' => [], 'reasons' => [], 'data' => []];
            }
            $map[$id]['sources']['hierarchical'] = $r;
            $map[$id]['reasons'][] = strtoupper($r['relationship_type'] ?? 'RELATED');
        }

        if (empty($map)) {
            return [];
        }

        // Normalize & score
        $maxEsScore = max(1, max(array_column($keywordResults, 'es_score') ?: [0]));
        $maxEntityCount = max(1, max(array_column($entityResults, 'match_count') ?: [0]));

        $hasEntity = !empty($entityResults);
        $wKeyword = $hasEntity ? 0.35 : 0.70;
        $wEntity = $hasEntity ? 0.40 : 0;
        $wHierarchy = $hasEntity ? 0.25 : 0.30;

        $scored = [];
        foreach ($map as $objectId => $entry) {
            $kn = isset($entry['sources']['keyword']) ? $entry['sources']['keyword']['es_score'] / $maxEsScore : 0;
            $en = isset($entry['sources']['entity']) ? $entry['sources']['entity']['match_count'] / $maxEntityCount : 0;
            $hn = 0;
            if (isset($entry['sources']['hierarchical'])) {
                $hn = ($entry['sources']['hierarchical']['relationship_type'] ?? '') === 'sibling' ? 0.5 : 0.3;
            }

            $score = ($kn * $wKeyword) + ($en * $wEntity) + ($hn * $wHierarchy);
            $sourceCount = count($entry['sources']);
            if ($sourceCount > 1) {
                $score *= (1 + ($sourceCount - 1) * 0.1);
            }

            $scored[] = [
                'object_id' => (int) $objectId,
                'score' => round($score, 4),
                'match_reasons' => array_values(array_unique($entry['reasons'])),
                'highlights' => $entry['data']['highlights'] ?? [],
                'slug' => $entry['data']['slug'] ?? null,
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /**
     * Enrich results with metadata (titles, dates, creators, thumbnails, NER entities).
     * Migrated from AhgDiscovery\Services\ResultEnricher.
     */
    private function enrichResults(array $results, string $culture, int $maxItems = 100): array
    {
        $results = array_slice($results, 0, $maxItems);
        $ids = array_column($results, 'object_id');
        if (empty($ids)) {
            return [];
        }

        $titles = DB::table('information_object_i18n')
            ->select('id', 'title', 'scope_and_content')
            ->whereIn('id', $ids)->where('culture', $culture)
            ->get()->keyBy('id');

        $slugs = DB::table('slug')->whereIn('object_id', $ids)->pluck('slug', 'object_id');

        $levels = DB::table('information_object as io')
            ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->select('io.id', 'ti.name as level')
            ->whereIn('io.id', $ids)->get()->pluck('level', 'id');

        $dates = DB::table('event')
            ->select('object_id', 'start_date', 'end_date')
            ->whereIn('object_id', $ids)->where('type_id', TermId::EVENT_TYPE_CREATION)
            ->get()->keyBy('object_id');

        $creators = DB::table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->select('event.object_id', 'actor_i18n.authorized_form_of_name as creator')
            ->whereIn('event.object_id', $ids)->where('event.type_id', TermId::EVENT_TYPE_CREATION)
            ->where('actor_i18n.culture', $culture)
            ->get()->pluck('creator', 'object_id');

        $repos = DB::table('information_object as io')
            ->join('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('io.repository_id', '=', 'ai.id')->where('ai.culture', '=', $culture);
            })
            ->select('io.id', 'ai.authorized_form_of_name as repository')
            ->whereIn('io.id', $ids)->whereNotNull('io.repository_id')
            ->get()->pluck('repository', 'id');

        $thumbnails = DB::table('digital_object')
            ->select('object_id', 'path', 'name')
            ->whereIn('object_id', $ids)->where('usage_id', 142)
            ->get()->keyBy('object_id');

        $entities = [];
        try {
            $exists = DB::select("SHOW TABLES LIKE 'ahg_ner_entity'");
            if (!empty($exists)) {
                $entRows = DB::table('ahg_ner_entity')
                    ->select('object_id', 'entity_type', 'entity_value')
                    ->whereIn('object_id', $ids)->whereIn('status', ['approved', 'pending'])->get();
                foreach ($entRows as $row) {
                    $oid = (int) $row->object_id;
                    if (!isset($entities[$oid])) { $entities[$oid] = []; }
                    if (count($entities[$oid]) < 10) {
                        $entities[$oid][] = ['type' => $row->entity_type, 'value' => $row->entity_value];
                    }
                }
            }
        } catch (\Exception $e) {}

        foreach ($results as &$result) {
            $id = $result['object_id'];
            $titleRow = $titles->get($id);
            $result['title'] = $titleRow ? ($titleRow->title ?: 'Untitled') : 'Untitled';
            $result['scope_and_content'] = $titleRow ? $this->trimToSentences($titleRow->scope_and_content ?? '', 2) : '';
            if (empty($result['slug'])) { $result['slug'] = $slugs[$id] ?? ''; }
            $result['level_of_description'] = $levels[$id] ?? '';
            $result['creator'] = $creators[$id] ?? '';
            $result['repository'] = $repos[$id] ?? '';
            $result['entities'] = $entities[$id] ?? [];
            $result['date_range'] = '';
            if (isset($dates[$id])) {
                $d = $dates[$id];
                $s = $d->start_date ? substr($d->start_date, 0, 4) : '';
                $e = $d->end_date ? substr($d->end_date, 0, 4) : '';
                $result['date_range'] = ($s && $e && $s !== $e) ? ($s . "\u{2013}" . $e) : $s;
            }
            $result['thumbnail_url'] = null;
            if (isset($thumbnails[$id])) {
                $t = $thumbnails[$id];
                $result['thumbnail_url'] = '/uploads/' . ltrim($t->path, '/') . $t->name;
            }
        }

        return $results;
    }

    private function groupByFonds(array $results, string $culture): array
    {
        $groups = [];
        foreach ($results as $result) {
            $fonds = $this->findRootFonds($result['object_id'], $culture);
            $key = $fonds ? $fonds['id'] : 0;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'fonds_id' => $fonds['id'] ?? 0,
                    'fonds_title' => $fonds['title'] ?? 'Ungrouped',
                    'fonds_slug' => $fonds['slug'] ?? '',
                    'records' => [],
                ];
            }
            $groups[$key]['records'][] = $result;
        }
        return array_values($groups);
    }

    private function findRootFonds(int $objectId, string $culture = 'en'): ?array
    {
        static $cache = [];
        if (isset($cache[$objectId])) {
            return $cache[$objectId];
        }
        try {
            $current = $objectId;
            $maxDepth = 20;
            while ($maxDepth-- > 0) {
                $node = DB::table('information_object')->select('id', 'parent_id')->where('id', $current)->first();
                if (!$node || (int) $node->parent_id <= 1) {
                    $title = DB::table('information_object_i18n')->where('id', $current)->where('culture', $culture)->value('title');
                    $slug = DB::table('slug')->where('object_id', $current)->value('slug');
                    $result = ['id' => (int) $current, 'title' => $title ?: 'Untitled', 'slug' => $slug ?: ''];
                    $cache[$objectId] = $result;
                    return $result;
                }
                $current = (int) $node->parent_id;
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function trimToSentences(string $text, int $count): string
    {
        if (empty($text)) { return ''; }
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = trim($text);
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, $count + 1);
        return count($sentences) <= $count ? $text : implode(' ', array_slice($sentences, 0, $count));
    }

    // =====================================================================
    // Cache, logging, popular topics
    // =====================================================================

    private function getPopularTopics(int $limit): array
    {
        try {
            $exists = DB::select("SHOW TABLES LIKE 'ahg_discovery_log'");
            if (empty($exists)) { return []; }

            return DB::table('ahg_discovery_log')
                ->select('query_text', DB::raw('COUNT(*) as search_count'), DB::raw('AVG(result_count) as avg_results'))
                ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL 30 DAY)"))
                ->groupBy('query_text')
                ->having('search_count', '>=', 2)
                ->orderByDesc('search_count')
                ->limit($limit)->get()
                ->map(fn($row) => [
                    'query' => $row->query_text,
                    'count' => (int) $row->search_count,
                    'avg_results' => (int) $row->avg_results,
                ])->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getFromCache(string $hash): ?array
    {
        try {
            $exists = DB::select("SHOW TABLES LIKE 'ahg_discovery_cache'");
            if (empty($exists)) { return null; }
            $row = DB::table('ahg_discovery_cache')
                ->where('query_hash', $hash)->where('expires_at', '>', DB::raw('NOW()'))->first();
            return $row ? json_decode($row->result_json, true) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function putInCache(string $hash, string $query, array $response): void
    {
        try {
            $exists = DB::select("SHOW TABLES LIKE 'ahg_discovery_cache'");
            if (empty($exists)) { return; }
            DB::table('ahg_discovery_cache')->updateOrInsert(
                ['query_hash' => $hash],
                [
                    'query_text' => mb_substr($query, 0, 500),
                    'result_json' => json_encode($response),
                    'result_count' => $response['total'] ?? 0,
                    'created_at' => DB::raw('NOW()'),
                    'expires_at' => DB::raw('DATE_ADD(NOW(), INTERVAL ' . self::CACHE_TTL . ' SECOND)'),
                ]
            );
        } catch (\Exception $e) {}
    }

    private function logSearch(string $query, ?array $expanded, int $resultCount, float $startTime): void
    {
        try {
            $exists = DB::select("SHOW TABLES LIKE 'ahg_discovery_log'");
            if (empty($exists)) { return; }
            DB::table('ahg_discovery_log')->insert([
                'user_id' => auth()->id(),
                'query_text' => mb_substr($query, 0, 500),
                'expanded_terms' => $expanded ? json_encode([
                    'keywords' => $expanded['keywords'] ?? [],
                    'synonyms' => $expanded['synonyms'] ?? [],
                    'entityTerms' => array_column($expanded['entityTerms'] ?? [], 'value'),
                ]) : null,
                'result_count' => $resultCount,
                'response_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'session_id' => session()->getId() ?: null,
                'created_at' => DB::raw('NOW()'),
            ]);
        } catch (\Exception $e) {}
    }
}
