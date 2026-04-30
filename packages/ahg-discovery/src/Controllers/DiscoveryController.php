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

    /** All retrieval-strategy keys recognised by the ablation switch (#28). */
    public const VALID_STRATEGIES = ['keyword', 'entity', 'hierarchical', 'vector', 'image'];

    /**
     * Cached resolved connection name, so the per-request lookup hits ahg_settings once.
     * Cleared at the boundary of each request via Laravel's normal lifecycle.
     */
    private ?string $discoveryConn = null;

    /**
     * Cached fusion-config block — merge weights + RRF k + hierarchical-tier scores +
     * multi-source bonus. Loaded once per controller instance from ahg_settings
     * (group=discovery, keys ahg_discovery_weight_*, ahg_discovery_rrf_k, etc.).
     * Issue #21.
     */
    private ?array $fusionConfig = null;

    /**
     * Per-instance cache for termIds() lookups. Issue #22.
     * Key: "$taxonomyId:" . sorted-csv-of-names → int[]
     */
    private array $termIdCache = [];

    /**
     * Default fusion weights — replicate the AtoM ResultMerger constants so
     * a fresh install with no settings rows behaves identically to pre-#21.
     */
    private const FUSION_DEFAULTS = [
        'weight_keyword_3way'  => 0.35,  // when keyword + entity + hier all present
        'weight_entity_3way'   => 0.40,
        'weight_hier_3way'     => 0.25,
        'weight_keyword_2way'  => 0.70,  // when entity absent (keyword + hier only)
        'weight_hier_2way'     => 0.30,
        'hier_sibling_score'   => 0.5,
        'hier_child_score'     => 0.3,
        'multi_source_bonus'   => 0.10,  // per-extra-source multiplier (1 + (n-1)*bonus)
        'rrf_k'                => 60,    // Cormack et al. SIGIR 2009 default
    ];

    /**
     * Resolve term IDs by taxonomy + name list. Honours the discoveryDb()
     * connection (#14) so the lookup hits the same DB the rest of the pipeline
     * reads. Cached per controller instance. Issue #22.
     *
     * Names are matched case-insensitively against term_i18n.name in the en
     * culture. Pass synonyms (e.g. ['Subfonds', 'Sub-fonds']) to handle the
     * AtoM/Heratio orthography drift — the lookup unions them all.
     *
     * @return int[] term IDs (may be empty if no matches)
     */
    protected function termIds(int $taxonomyId, array $names): array
    {
        $names = array_values(array_filter(array_map('strval', $names)));
        if (empty($names)) return [];
        $cacheKey = $taxonomyId . ':' . implode(',', $names);
        if (array_key_exists($cacheKey, $this->termIdCache)) {
            return $this->termIdCache[$cacheKey];
        }
        try {
            $ids = $this->discoveryDb()->table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', $taxonomyId)
                ->whereIn(DB::raw('LOWER(term_i18n.name)'), array_map('strtolower', $names))
                ->where('term_i18n.culture', 'en')
                ->pluck('term.id')->map(fn($v) => (int) $v)->toArray();
            return $this->termIdCache[$cacheKey] = $ids;
        } catch (\Throwable $e) {
            return $this->termIdCache[$cacheKey] = [];
        }
    }

    /**
     * Single-term variant of termIds(). Returns first match, or $default if no
     * matches. Used where the caller wants a scalar (e.g. thumbnail usage_id).
     */
    protected function termId(int $taxonomyId, array $names, ?int $default = null): ?int
    {
        $ids = $this->termIds($taxonomyId, $names);
        return $ids[0] ?? $default;
    }

    /**
     * Read fusion config from ahg_settings, falling back to FUSION_DEFAULTS for
     * any key that's missing. One DB hit per controller instance.
     */
    protected function fusionConfig(): array
    {
        if ($this->fusionConfig !== null) {
            return $this->fusionConfig;
        }
        $cfg = self::FUSION_DEFAULTS;
        try {
            $rows = DB::table('ahg_settings')
                ->where('setting_group', 'discovery')
                ->whereIn('setting_key', [
                    'ahg_discovery_weight_keyword_3way',
                    'ahg_discovery_weight_entity_3way',
                    'ahg_discovery_weight_hier_3way',
                    'ahg_discovery_weight_keyword_2way',
                    'ahg_discovery_weight_hier_2way',
                    'ahg_discovery_hier_sibling_score',
                    'ahg_discovery_hier_child_score',
                    'ahg_discovery_multi_source_bonus',
                    'ahg_discovery_rrf_k',
                ])
                ->pluck('setting_value', 'setting_key');
            foreach ($rows as $key => $value) {
                $short = preg_replace('/^ahg_discovery_/', '', $key);
                if (array_key_exists($short, $cfg)) {
                    $cfg[$short] = is_numeric($value) ? (float) $value : $cfg[$short];
                }
            }
            // rrf_k is integer; cast back
            $cfg['rrf_k'] = (int) $cfg['rrf_k'];
        } catch (\Throwable $e) {
            // Settings unreadable — defaults stand.
        }
        return $this->fusionConfig = $cfg;
    }

    /**
     * Connection name for non-ES strategies — entity NER lookups, hierarchical
     * walks, and enrich/group joins. Reads ahg_settings.discovery_db_connection;
     * defaults to 'atom' (the ANC corpus). Falls back to the framework default
     * connection if the requested one is missing — e.g. fresh installs without
     * an `atom` DB will keep working in degraded mode (smaller heratio sample).
     *
     * Issue #14.
     */
    protected function discoveryDb(): \Illuminate\Database\ConnectionInterface
    {
        if ($this->discoveryConn === null) {
            $name = (string) (DB::table('ahg_settings')
                ->where('setting_key', 'discovery_db_connection')
                ->value('setting_value') ?? 'atom');
            $this->discoveryConn = $name !== '' ? $name : 'atom';
        }
        try {
            return DB::connection($this->discoveryConn);
        } catch (\Throwable $e) {
            // Configured connection missing — degrade to default rather than fatal.
            return DB::connection();
        }
    }

    /**
     * Map a request's `strategies` param + legacy `mode` into the canonical
     * list of enabled retrieval strategies.
     *
     * - When `strategies` is non-empty, it wins outright (whitelist filtered
     *   against {@see VALID_STRATEGIES}, deduplicated, order preserved).
     * - When `strategies` is absent / empty, fall back to the legacy mode
     *   mapping so existing callers see byte-identical behaviour:
     *     mode=standard            → keyword + hierarchical
     *     mode=semantic | vector   → keyword + entity + hierarchical + vector
     *
     * @param  string|array|null  $strategiesInput
     * @param  string             $mode
     * @return string[]
     */
    public static function resolveEnabledStrategies($strategiesInput, string $mode): array
    {
        if (is_array($strategiesInput)) {
            $tokens = $strategiesInput;
        } elseif (is_string($strategiesInput) && trim($strategiesInput) !== '') {
            $tokens = preg_split('/[,\s]+/', trim($strategiesInput)) ?: [];
        } else {
            $tokens = [];
        }

        if (! empty($tokens)) {
            $clean = [];
            foreach ($tokens as $t) {
                $t = strtolower(trim((string) $t));
                if ($t !== '' && in_array($t, self::VALID_STRATEGIES, true) && ! in_array($t, $clean, true)) {
                    $clean[] = $t;
                }
            }
            if (! empty($clean)) {
                return $clean;
            }
        }

        return $mode === 'standard'
            ? ['keyword', 'hierarchical']
            : ['keyword', 'entity', 'hierarchical', 'vector'];
    }

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

        // Ablation switch (#28): explicit strategies list overrides mode-based gating.
        // ?strategies=keyword,entity,hierarchical,vector — comma-separated whitelist.
        // Absent → fall back to legacy mode mapping (full back-compat).
        $enabledStrategies = self::resolveEnabledStrategies(
            $request->input('strategies'),
            $mode
        );

        if (empty($query)) {
            return response()->json([
                'success' => true,
                'total' => 0,
                'collections' => [],
                'results' => [],
                'expanded' => null,
                'mode' => $mode,
                'strategies' => array_values($enabledStrategies),
            ]);
        }

        $culture = app()->getLocale() ?: 'en';
        $startTime = microtime(true);

        // Cache key includes the resolved strategies list so two ablation configs
        // never read each other's cached responses. ?nocache=1 bypasses the cache
        // entirely — used by the eval harness (#17) and verify twin (#20B) to
        // measure the actual retrieval pipeline rather than cached responses.
        $bypassCache = $request->boolean('nocache');
        $strategiesKey = implode(',', $enabledStrategies);
        $cacheKey = md5($query . '|' . $culture . '|' . $page . '|' . $limit . '|' . $mode . '|' . $strategiesKey);
        if (! $bypassCache) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                $this->logSearch($query, $cached['expanded'] ?? null, $cached['total'] ?? 0, $startTime);
                return response()->json($cached);
            }
        }

        // Per-strategy telemetry capture. All 5 retrieval-strategy keys are
        // pre-populated with {hits:[], ms:0} so the JSON schema is stable
        // across configs — disabled strategies still appear in the log row
        // with zero hits/ms instead of being absent.
        $strategyResults = [
            'keyword'      => ['hits' => [], 'ms' => 0],
            'entity'       => ['hits' => [], 'ms' => 0],
            'hierarchical' => ['hits' => [], 'ms' => 0],
            'vector'       => ['hits' => [], 'ms' => 0],
            'image'        => ['hits' => [], 'ms' => 0],
        ];

        // Step 1: Query Expansion (always — not a retrieval strategy, just preprocessing)
        $t0 = microtime(true);
        $expanded = $this->expandQuery($query);
        $strategyResults['expansion'] = ['hits' => [], 'ms' => (int) ((microtime(true) - $t0) * 1000)];

        // Step 2: Keyword search (DB-based)
        $keywordResults = [];
        if (in_array('keyword', $enabledStrategies, true)) {
            $t0 = microtime(true);
            $keywordResults = $this->keywordSearch($expanded, $culture, 100);
            $strategyResults['keyword'] = ['hits' => $keywordResults, 'ms' => (int) ((microtime(true) - $t0) * 1000)];
        }

        // Step 3: Entity search
        $entityResults = [];
        if (in_array('entity', $enabledStrategies, true)) {
            $t0 = microtime(true);
            $entityResults = $this->entitySearch($expanded, 200);
            $strategyResults['entity'] = ['hits' => $entityResults, 'ms' => (int) ((microtime(true) - $t0) * 1000)];
        }

        // Step 3b: Vector similarity via Qdrant — failure is silent (returns []).
        $vectorResults = [];
        if (in_array('vector', $enabledStrategies, true)) {
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
        $hierarchicalResults = [];
        if (in_array('hierarchical', $enabledStrategies, true)) {
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
        }

        // Step 5: Merge & Rank (existing 3-way merger)
        $t0 = microtime(true);
        $merged = $this->mergeResults($keywordResults, $entityResults, $hierarchicalResults);

        // Step 5b: RRF boost with vector results.
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
            'strategies' => array_values($enabledStrategies),
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

        if (! $bypassCache) {
            $this->putInCache($cacheKey, $query, $response);
        }

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
        // RRF k from ahg_settings (issue #21); default 60 = Cormack et al. SIGIR 2009.
        $k = $this->fusionConfig()['rrf_k'];
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
     * Elasticsearch-backed keyword search (issue #13).
     *
     * Hits {prefix}qubitinformationobject directly via AhgSearch's
     * ElasticsearchService::search(). Title is weighted 3× over scope/history.
     * Returns [object_id, es_score, highlights, slug] in the same shape the
     * downstream merger expects.
     *
     * Previous implementation was a MySQL `LIKE %term%` scan with hand-rolled
     * relevance scoring (~240 ms cold, 176 ms warm against atom 455k rows).
     * ES on the same query lands in 6–9 ms wall time per the Apr-2026 status doc.
     *
     * If Elasticsearch is unreachable, returns []; the rest of the pipeline
     * (entity, hierarchical, vector) keeps running.
     */
    private function keywordSearch(array $expanded, string $culture, int $limit = 100): array
    {
        $searchTerms = array_merge($expanded['keywords'], $expanded['phrases']);
        if (empty($searchTerms)) {
            return [];
        }
        $queryString = trim(implode(' ', $searchTerms));
        if ($queryString === '') {
            return [];
        }

        try {
            $es = app(\AhgSearch\Services\ElasticsearchService::class);
            $body = [
                'query' => [
                    'query_string' => [
                        'query'            => $queryString,
                        'fields'           => [
                            "i18n.{$culture}.title^3",
                            "i18n.{$culture}.scopeAndContent",
                            "i18n.{$culture}.history",
                            "identifier^2",
                        ],
                        'default_operator' => 'OR',
                    ],
                ],
                '_source' => ['slug', "i18n.{$culture}.title"],
                'highlight' => [
                    'fields' => [
                        "i18n.{$culture}.title"            => (object) [],
                        "i18n.{$culture}.scopeAndContent"  => [
                            'fragment_size'       => 200,
                            'number_of_fragments' => 1,
                        ],
                    ],
                    'pre_tags'  => ['<mark>'],
                    'post_tags' => ['</mark>'],
                ],
            ];

            $resp = $es->search('qubitinformationobject', $body, 0, $limit);

            $hits = [];
            foreach (($resp['hits']['hits'] ?? []) as $hit) {
                $hits[] = [
                    'object_id'  => (int) ($hit['_id'] ?? 0),
                    'es_score'   => (float) ($hit['_score'] ?? 0),
                    'highlights' => $hit['highlight'] ?? [],
                    'slug'       => $hit['_source']['slug'] ?? null,
                ];
            }
            return $hits;
        } catch (\Throwable $e) {
            // ES unavailable — let the pipeline carry on with the other strategies.
            return [];
        }
    }

    /**
     * NER Entity search — Elasticsearch BM25 over the denormalised
     * `nerEntityValues` field on `heratio_qubitinformationobject`.
     *
     * Issue #24 — replaces the prior MySQL `LIKE %term%` over the 9.79M-row
     * `ahg_ner_entity` table (28 s warm) with an ES bool/should query that
     * lands in tens of milliseconds. The denormalisation is built by
     * `bin/discovery-ner-reindex.php`; status filter ('approved' / 'pending')
     * is applied at reindex time, so the live query path doesn't see
     * unapproved entities.
     *
     * Returns the same shape the merger expects:
     *   [{object_id, match_count, entity_types, matched_values}, ...]
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
            $es = app(\AhgSearch\Services\ElasticsearchService::class);
            // term on nerEntityValues.raw — non-tokenised exact match against
            // the keyword subfield of each entity label. The previous
            // bool/should of match_phrase + match was a token-OR that pulled
            // in any document sharing any token with the query (e.g. "South
            // Africa" → "South Korea" / "South African Communist Party"),
            // driving precision to 1.4% in isolation per the #32 audit.
            // The #33 A/B showed term-on-raw lifts kw_entity nDCG@10 +82.3%,
            // MRR +107.3%, recall@10 +17.0%, with entity-step latency 36→10ms.
            $should = [];
            foreach ($searchTerms as $term) {
                $should[] = ['term' => ['nerEntityValues.raw' => ['value' => $term, 'boost' => 1.0]]];
            }
            $body = [
                'query' => [
                    'bool' => [
                        'should'               => $should,
                        'minimum_should_match' => 1,
                    ],
                ],
                '_source' => ['nerEntityTypes', 'nerEntityValues'],
                'highlight' => [
                    'fields'    => ['nerEntityValues' => (object) []],
                    'pre_tags'  => ['<mark>'],
                    'post_tags' => ['</mark>'],
                ],
            ];
            $resp = $es->search('qubitinformationobject', $body, 0, $limit);

            $hits = [];
            foreach (($resp['hits']['hits'] ?? []) as $hit) {
                $highlightFragments = $hit['highlight']['nerEntityValues'] ?? [];
                $matchedValues = [];
                foreach ($highlightFragments as $frag) {
                    $clean = trim(strip_tags($frag));
                    if ($clean !== '' && ! in_array($clean, $matchedValues, true)) {
                        $matchedValues[] = $clean;
                    }
                }
                if (empty($matchedValues)) {
                    // Fallback if highlighter is disabled — pull the source values.
                    $vals = $hit['_source']['nerEntityValues'] ?? [];
                    if (is_array($vals)) {
                        $matchedValues = array_slice($vals, 0, 5);
                    }
                }
                $hits[] = [
                    'object_id'      => (int) ($hit['_id'] ?? 0),
                    // ES BM25 score → keep raw; merger normalises against max.
                    'match_count'    => (float) ($hit['_score'] ?? 0),
                    'entity_types'   => implode(',', (array) ($hit['_source']['nerEntityTypes'] ?? [])),
                    'matched_values' => $matchedValues,
                ];
            }
            return $hits;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Hierarchical walk: find siblings/children of top results.
     * Migrated from AhgDiscovery\Services\HierarchicalStrategy.
     */
    /**
     * Hierarchical walk — find siblings + children-of-fonds for the top N
     * results from keyword/entity. Issue #29: was 1 + 20 sibling + up to 20
     * children = up to 41 round-trips per request (~412 ms warm). Now
     * batches into 3 IN-clause queries — the parent_id index does the work
     * once instead of N times.
     *
     * Per-parent cap of 5 siblings / 10 children is preserved post-fetch in
     * PHP (was previously baked into per-node \limit(5)\ / \limit(10)\ on
     * each individual query).
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
            $db = $this->discoveryDb();

            // Q1: pull the walk-target nodes themselves (one IN query).
            $nodes = $db->table('information_object')
                ->select('id', 'parent_id', 'level_of_description_id')
                ->whereIn('id', $walkIds)
                ->get()->keyBy('id');

            // Collect parents to fetch siblings against, and high-level node IDs
            // whose children we want to enumerate.
            $parentIds       = [];
            $highLevelNodeIds = [];
            foreach ($nodes as $node) {
                $objectId = (int) $node->id;
                $parentId = (int) $node->parent_id;
                if ($parentId > 1 && ! in_array($parentId, $parentIds, true)) {
                    $parentIds[] = $parentId;
                }
                if (in_array((int) $node->level_of_description_id, $highLevels, true)) {
                    $highLevelNodeIds[] = $objectId;
                }
            }

            // Q2: siblings — ONE query for all parent_ids at once.
            // Per-parent cap (5) is enforced in PHP after the fetch since
            // ROW_NUMBER() partitioning would complicate the query plan.
            $siblingsByParent = [];
            if (! empty($parentIds)) {
                $sibRows = $db->table('information_object')
                    ->select('id', 'parent_id')
                    ->whereIn('parent_id', $parentIds)
                    ->whereNotIn('id', $walkIds)            // not the walk-targets themselves
                    ->whereNotIn('id', $alreadyFound)
                    ->limit(count($parentIds) * 5 * 4)      // 4× headroom; per-parent cap below
                    ->get();
                foreach ($sibRows as $r) {
                    $pid = (int) $r->parent_id;
                    $sid = (int) $r->id;
                    if (! isset($siblingsByParent[$pid])) {
                        $siblingsByParent[$pid] = [];
                    }
                    if (count($siblingsByParent[$pid]) < 5) {
                        $siblingsByParent[$pid][] = $sid;
                    }
                }
            }

            // Q3: children of high-level walk targets — ONE query for all of them.
            $childrenByParent = [];
            if (! empty($highLevelNodeIds)) {
                $childRows = $db->table('information_object')
                    ->select('id', 'parent_id')
                    ->whereIn('parent_id', $highLevelNodeIds)
                    ->whereNotIn('id', $alreadyFound)
                    ->limit(count($highLevelNodeIds) * 10 * 4)
                    ->get();
                foreach ($childRows as $r) {
                    $pid = (int) $r->parent_id;
                    $cid = (int) $r->id;
                    if (! isset($childrenByParent[$pid])) {
                        $childrenByParent[$pid] = [];
                    }
                    if (count($childrenByParent[$pid]) < 10) {
                        $childrenByParent[$pid][] = $cid;
                    }
                }
            }

            // Walk and emit results in the same order as before.
            foreach ($nodes as $node) {
                $objectId = (int) $node->id;
                $parentId = (int) $node->parent_id;
                if ($parentId <= 1) continue;

                foreach ($siblingsByParent[$parentId] ?? [] as $sibId) {
                    if (! isset($processed[$sibId])) {
                        $results[] = ['object_id' => $sibId, 'relationship_type' => 'sibling', 'via_object_id' => $objectId];
                        $processed[$sibId] = true;
                    }
                }

                if (in_array((int) $node->level_of_description_id, $highLevels, true)) {
                    foreach ($childrenByParent[$objectId] ?? [] as $childId) {
                        if (! isset($processed[$childId])) {
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

    /**
     * Resolve the level-of-description term IDs that count as "high-level"
     * for hierarchical walks (siblings + children-of-fonds). Issue #22 —
     * runtime taxonomy lookup with synonym support so atom/AtoM orthography
     * differences don't silently drop fonds-level matches.
     */
    private function getHighLevelIds(): array
    {
        // Taxonomy 34 = Levels of description. Includes both hyphenated AtoM
        // names ('Sub-fonds') and the Heratio/atom-DB unhyphenated form
        // ('Subfonds'); the lookup unions them so either spelling matches.
        $ids = $this->termIds(34, [
            'Fonds', 'Subfonds', 'Sub-fonds',
            'Series', 'Subseries', 'Sub-series',
            'Collection', 'Record group',
        ]);
        if (empty($ids)) {
            // Hard fallback: AtoM-derived constants. Will silently misbehave on
            // a fresh install where these IDs aren't fonds-level — log a warning.
            \Illuminate\Support\Facades\Log::warning(
                'DiscoveryController::getHighLevelIds() — no high-level terms in taxonomy 34; '
                . 'falling back to AtoM-default IDs. This is incorrect on this install.'
            );
            return [227, 228, 229, 231];
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

        // Fusion weights from ahg_settings (issue #21); fallback defaults match
        // the historical AtoM ResultMerger constants.
        $cfg = $this->fusionConfig();
        $hasEntity = !empty($entityResults);
        $wKeyword   = $hasEntity ? $cfg['weight_keyword_3way'] : $cfg['weight_keyword_2way'];
        $wEntity    = $hasEntity ? $cfg['weight_entity_3way']  : 0.0;
        $wHierarchy = $hasEntity ? $cfg['weight_hier_3way']    : $cfg['weight_hier_2way'];
        $sibScore   = $cfg['hier_sibling_score'];
        $childScore = $cfg['hier_child_score'];
        $bonus      = $cfg['multi_source_bonus'];

        $scored = [];
        foreach ($map as $objectId => $entry) {
            $kn = isset($entry['sources']['keyword']) ? $entry['sources']['keyword']['es_score'] / $maxEsScore : 0;
            $en = isset($entry['sources']['entity']) ? $entry['sources']['entity']['match_count'] / $maxEntityCount : 0;
            $hn = 0;
            if (isset($entry['sources']['hierarchical'])) {
                $hn = ($entry['sources']['hierarchical']['relationship_type'] ?? '') === 'sibling' ? $sibScore : $childScore;
            }

            $score = ($kn * $wKeyword) + ($en * $wEntity) + ($hn * $wHierarchy);
            $sourceCount = count($entry['sources']);
            if ($sourceCount > 1) {
                $score *= (1 + ($sourceCount - 1) * $bonus);
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

        $db = $this->discoveryDb();

        $titles = $db->table('information_object_i18n')
            ->select('id', 'title', 'scope_and_content')
            ->whereIn('id', $ids)->where('culture', $culture)
            ->get()->keyBy('id');

        $slugs = $db->table('slug')->whereIn('object_id', $ids)->pluck('slug', 'object_id');

        $levels = $db->table('information_object as io')
            ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->select('io.id', 'ti.name as level')
            ->whereIn('io.id', $ids)->get()->pluck('level', 'id');

        // Resolve "Creation" event type by taxonomy lookup (issue #22).
        // Falls back to the AtoM-derived constant if the taxonomy is unreachable.
        $creationTypeId = $this->termId(40, ['Creation'], TermId::EVENT_TYPE_CREATION);

        $dates = $db->table('event')
            ->select('object_id', 'start_date', 'end_date')
            ->whereIn('object_id', $ids)->where('type_id', $creationTypeId)
            ->get()->keyBy('object_id');

        $creators = $db->table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->select('event.object_id', 'actor_i18n.authorized_form_of_name as creator')
            ->whereIn('event.object_id', $ids)->where('event.type_id', $creationTypeId)
            ->where('actor_i18n.culture', $culture)
            ->get()->pluck('creator', 'object_id');

        $repos = $db->table('information_object as io')
            ->join('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('io.repository_id', '=', 'ai.id')->where('ai.culture', '=', $culture);
            })
            ->select('io.id', 'ai.authorized_form_of_name as repository')
            ->whereIn('io.id', $ids)->whereNotNull('io.repository_id')
            ->get()->pluck('repository', 'id');

        // Thumbnail digital_object usage — taxonomy 47 ('Digital Object Usages').
        // Issue #22: was hardcoded to 142; now resolved by name with the AtoM
        // default as fallback so first install on a stock AtoM keeps working.
        $thumbnailUsageId = $this->termId(47, ['Thumbnail'], 142);
        $thumbnails = $db->table('digital_object')
            ->select('object_id', 'path', 'name')
            ->whereIn('object_id', $ids)->where('usage_id', $thumbnailUsageId)
            ->get()->keyBy('object_id');

        $entities = [];
        try {
            $exists = $db->select("SHOW TABLES LIKE 'ahg_ner_entity'");
            if (!empty($exists)) {
                $entRows = $db->table('ahg_ner_entity')
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
            $db = $this->discoveryDb();
            $current = $objectId;
            $maxDepth = 20;
            while ($maxDepth-- > 0) {
                $node = $db->table('information_object')->select('id', 'parent_id')->where('id', $current)->first();
                if (!$node || (int) $node->parent_id <= 1) {
                    $title = $db->table('information_object_i18n')->where('id', $current)->where('culture', $culture)->value('title');
                    $slug = $db->table('slug')->where('object_id', $current)->value('slug');
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
