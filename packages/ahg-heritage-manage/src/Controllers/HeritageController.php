<?php

namespace AhgHeritageManage\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeritageController extends Controller
{
    /**
     * Heritage Landing Page.
     *
     * Queries hero images, config, curated collections, creators,
     * timeline periods, recently added items, and contributors.
     */
    public function landing()
    {
        $culture = 'en';

        // --- Hero Images (from heritage_hero_slide table) ---
        $heroImages = [];
        try {
            if (Schema::hasTable('heritage_hero_slide')) {
                $heroImages = DB::table('heritage_hero_slide')
                    ->where('is_enabled', 1)
                    ->orderBy('display_order')
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->toArray();
            }
        } catch (\Exception $e) {
            $heroImages = [];
        }

        // --- Config (heritage_landing_config is a single-row table with named columns) ---
        $config = null;
        try {
            if (Schema::hasTable('heritage_landing_config')) {
                $config = DB::table('heritage_landing_config')->first();
            }
        } catch (\Exception $e) {
            $config = null;
        }

        $tagline = $config->hero_tagline ?? 'Discover Our Heritage';
        $subtext = $config->hero_subtext ?? 'Explore collections spanning centuries of history, culture, and human achievement';
        $searchPlaceholder = $config->hero_search_placeholder ?? 'Try: Egyptian artifacts, Victorian letters, landscape photographs...';
        $suggestedSearches = $config->suggested_searches ?? '[]';
        if (is_string($suggestedSearches)) {
            $suggestedSearches = json_decode($suggestedSearches, true) ?: [];
        }
        $primaryColor = $config->primary_color ?? '#0d6efd';

        // --- Curated Collections ---
        $curatedCollections = $this->getCuratedCollections($culture, 12);

        // --- Creators ---
        $creators = collect();
        try {
            $creators = DB::table('actor')
                ->leftJoin('actor_i18n', function ($join) use ($culture) {
                    $join->on('actor.id', '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', $culture);
                })
                ->leftJoin('slug', function ($join) {
                    $join->on('actor.id', '=', 'slug.object_id');
                })
                ->leftJoin('relation', 'actor.id', '=', 'relation.object_id')
                ->select('actor.id', 'slug.slug', 'actor_i18n.authorized_form_of_name as name')
                ->selectRaw('COUNT(relation.id) as item_count')
                ->whereNotNull('actor_i18n.authorized_form_of_name')
                ->where('actor_i18n.authorized_form_of_name', '!=', '')
                ->groupBy('actor.id', 'slug.slug', 'actor_i18n.authorized_form_of_name')
                ->orderByDesc('item_count')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            $creators = collect();
        }

        // --- Timeline Periods ---
        $timelinePeriods = collect();
        try {
            if (Schema::hasTable('heritage_timeline_period')) {
                $timelinePeriods = DB::table('heritage_timeline_period')
                    ->where('is_enabled', 1)
                    ->where('show_on_landing', 1)
                    ->orderBy('start_year')
                    ->get();
            }
        } catch (\Exception $e) {
            $timelinePeriods = collect();
        }

        // --- Recently Added Items with Digital Objects ---
        $recentItems = collect();
        try {
            $recentItems = DB::table('information_object')
                ->join('object', 'information_object.id', '=', 'object.id')
                ->join('status as pub_status', function ($join) {
                    $join->on('information_object.id', '=', 'pub_status.object_id')
                        ->where('pub_status.type_id', '=', 158);
                })
                ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                    $join->on('information_object.id', '=', 'information_object_i18n.id')
                        ->where('information_object_i18n.culture', '=', $culture);
                })
                ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
                ->leftJoin('digital_object', function ($join) {
                    $join->on('information_object.id', '=', 'digital_object.object_id')
                        ->where('digital_object.usage_id', '=', 140);
                })
                ->leftJoin('digital_object as do_thumb', function ($join) {
                    $join->on('do_thumb.parent_id', '=', 'digital_object.id')
                        ->where('do_thumb.usage_id', '=', 142);
                })
                ->select(
                    'information_object.id',
                    'slug.slug',
                    'information_object_i18n.title',
                    'digital_object.path as image_path',
                    'digital_object.name as image_name',
                    'digital_object.mime_type',
                    'do_thumb.path as thumb_child_path',
                    'do_thumb.name as thumb_child_name'
                )
                ->where('pub_status.status_id', 160) // Published only
                ->whereNotNull('digital_object.id')
                ->where('information_object.id', '!=', 1)
                ->orderByDesc('object.created_at')
                ->limit(12)
                ->get();
        } catch (\Exception $e) {
            $recentItems = collect();
        }

        // --- Top Contributors ---
        $topContributors = collect();
        try {
            if (Schema::hasTable('heritage_contributor')) {
                $topContributors = DB::table('heritage_contributor')
                    ->where('is_active', 1)
                    ->orderByDesc('points')
                    ->limit(5)
                    ->get();
            }
        } catch (\Exception $e) {
            $topContributors = collect();
        }

        // --- Explore Categories (from heritage_explore_category table) ---
        $exploreCategories = collect();
        try {
            if (Schema::hasTable('heritage_explore_category')) {
                $exploreCategories = DB::table('heritage_explore_category')
                    ->where('is_enabled', 1)
                    ->orderBy('display_order')
                    ->get();
            }
        } catch (\Exception $e) {
            $exploreCategories = collect();
        }

        return view('ahg-heritage-manage::landing', compact(
            'heroImages',
            'tagline',
            'subtext',
            'searchPlaceholder',
            'suggestedSearches',
            'primaryColor',
            'curatedCollections',
            'creators',
            'timelinePeriods',
            'recentItems',
            'topContributors',
            'exploreCategories'
        ));
    }

    /**
     * Heritage search page with full-text search, faceted filtering, and pagination.
     */
    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));

        // Collect filters from request (place[], subject[], creator[], collection[])
        $filters = [];
        foreach (['place', 'subject', 'creator', 'collection'] as $filterCode) {
            $values = $request->input($filterCode, []);
            if (!empty($values)) {
                $filters[$filterCode] = (array) $values;
            }
        }

        $searchService = new \AhgHeritageManage\Services\HeritageSearchService('en');
        $results = $searchService->search($query, $filters, $page, 10);

        // Extract variables for the view
        $totalResults = $results['total'];
        $currentPage = $results['page'];
        $totalPages = $results['pages'];
        $searchResults = $results['results'];
        $facets = $results['facets'];
        $termMatches = $results['term_matches'];
        $searchId = $results['search_id'];
        $suggestions = $results['suggestions'];

        // Identify unmatched search terms
        $unmatchedTerms = [];
        $matchedTerms = [];
        foreach ($termMatches as $tm) {
            if (!($tm['matched'] ?? true)) {
                $unmatchedTerms[] = $tm['term'];
            } else {
                $matchedTerms[] = $tm['term'];
            }
        }

        // Build filterOptions array for the view (matching AtoM template structure)
        $filterOptions = [];
        $filterLabelMap = []; // Map filter_code => id => label for active filter display
        foreach ($facets as $code => $facet) {
            $filterOptions[] = [
                'code'           => $facet['code'],
                'label'          => $facet['label'],
                'icon'           => $facet['icon'],
                'show_in_search' => $facet['show_in_search'],
                'values'         => $facet['values'],
            ];
            foreach ($facet['values'] as $v) {
                $filterLabelMap[$facet['code']][$v['value']] = $v['label'];
            }
        }

        return view('ahg-heritage-manage::search', compact(
            'query',
            'totalResults',
            'currentPage',
            'totalPages',
            'searchResults',
            'filters',
            'filterOptions',
            'filterLabelMap',
            'unmatchedTerms',
            'matchedTerms',
            'searchId',
            'suggestions'
        ));
    }

    /**
     * Heritage timeline — redirects to GLAM browse sorted by date.
     */
    public function timeline(Request $request)
    {
        $params = ['sort' => 'date'];
        if ($request->has('period_id')) {
            $period = null;
            try {
                $period = DB::table('heritage_timeline_period')
                    ->where('id', $request->input('period_id'))
                    ->first();
            } catch (\Exception $e) {
                // ignore
            }
            if ($period) {
                $params['date_start'] = $period->start_year;
                if ($period->end_year) {
                    $params['date_end'] = $period->end_year;
                }
            }
        }
        return redirect()->route('informationobject.browse', $params);
    }

    /**
     * Heritage creators — redirects to actor browse.
     */
    public function creators()
    {
        return redirect()->route('actor.browse');
    }

    /**
     * Heritage explore — redirects to GLAM browse with category filter.
     */
    public function explore(Request $request)
    {
        return redirect()->route('informationobject.browse', $request->query());
    }

    /**
     * Heritage knowledge graph — redirects to GLAM browse with graph view.
     */
    public function graph()
    {
        return redirect()->route('informationobject.browse', ['view' => 'graph']);
    }

    /**
     * Heritage trending — redirects to GLAM browse sorted by popularity.
     */
    public function trending()
    {
        return redirect()->route('informationobject.browse', ['sort' => 'popular']);
    }

    /**
     * Heritage login — redirects to the login page.
     */
    public function login()
    {
        return redirect()->route('login');
    }

    /**
     * Get curated collections from the heritage_featured_collection table.
     * Only shows explicitly selected collections.
     */
    protected function getCuratedCollections(string $culture, int $limit = 12): array
    {
        $result = [];

        try {
            if (!Schema::hasTable('heritage_featured_collection')) {
                return [];
            }

            $featured = DB::table('heritage_featured_collection')
                ->where('is_enabled', 1)
                ->orderBy('display_order')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($featured as $item) {
                if ($item->source_type === 'iiif') {
                    // Get IIIF collection details
                    if (!Schema::hasTable('iiif_collection')) {
                        continue;
                    }

                    $collection = DB::table('iiif_collection as c')
                        ->leftJoin('iiif_collection_i18n as ci', function ($join) use ($culture) {
                            $join->on('c.id', '=', 'ci.collection_id')
                                ->where('ci.culture', '=', $culture);
                        })
                        ->where('c.id', $item->source_id)
                        ->select([
                            'c.id', 'c.name', 'c.slug', 'c.description', 'c.thumbnail_url',
                            DB::raw('COALESCE(ci.name, c.name) as display_name'),
                            DB::raw('COALESCE(ci.description, c.description) as display_description'),
                        ])
                        ->first();

                    if (!$collection) {
                        continue;
                    }

                    $itemCount = DB::table('iiif_collection_item')
                        ->where('collection_id', $collection->id)
                        ->count();

                    $thumbnail = $item->thumbnail_path ?? null;
                    if (!$thumbnail) {
                        $thumbnail = $collection->thumbnail_url ?? null;
                    }
                    if (!$thumbnail) {
                        $firstItem = DB::table('iiif_collection_item as ci')
                            ->leftJoin('digital_object as do', function ($join) {
                                $join->on('ci.object_id', '=', 'do.object_id')
                                    ->where('do.usage_id', '=', 140);
                            })
                            ->leftJoin('digital_object as do_thumb', function ($join) {
                                $join->on('do_thumb.parent_id', '=', 'do.id')
                                    ->where('do_thumb.usage_id', '=', 142);
                            })
                            ->where('ci.collection_id', $collection->id)
                            ->whereNotNull('ci.object_id')
                            ->select(['do.path', 'do.name', 'do_thumb.path as thumb_path', 'do_thumb.name as thumb_name'])
                            ->orderBy('ci.sort_order')
                            ->first();

                        if ($firstItem) {
                            if (!empty($firstItem->thumb_path) && !empty($firstItem->thumb_name)) {
                                $thumbnail = rtrim($firstItem->thumb_path, '/') . '/' . $firstItem->thumb_name;
                            } elseif (!empty($firstItem->path) && !empty($firstItem->name)) {
                                $candidate = rtrim($firstItem->path, '/') . '/' . pathinfo($firstItem->name, PATHINFO_FILENAME) . '_142.jpg';
                                $rootDir = config('heratio.uploads_path', '/usr/share/nginx/archive');
                                if (file_exists($rootDir . $candidate)) {
                                    $thumbnail = $candidate;
                                }
                            }
                        }
                    }

                    $result[] = [
                        'type' => 'iiif',
                        'id' => $collection->id,
                        'name' => $item->title ?? $collection->display_name ?? $collection->name,
                        'slug' => $collection->slug,
                        'description' => $item->description ?? $collection->display_description ?? $collection->description,
                        'thumbnail' => $thumbnail,
                        'item_count' => $itemCount,
                        'sort_order' => $item->display_order,
                    ];
                } else {
                    // Get archival collection (information_object) details
                    $collection = DB::table('information_object as io')
                        ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                            $join->on('io.id', '=', 'ioi.id')
                                ->where('ioi.culture', '=', $culture);
                        })
                        ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                        ->leftJoin('digital_object as do', function ($join) {
                            $join->on('io.id', '=', 'do.object_id')
                                ->where('do.usage_id', '=', 140);
                        })
                        ->leftJoin('digital_object as do_thumb', function ($join) {
                            $join->on('do_thumb.parent_id', '=', 'do.id')
                                ->where('do_thumb.usage_id', '=', 142);
                        })
                        ->where('io.id', $item->source_id)
                        ->select([
                            'io.id', 'ioi.title', 'ioi.scope_and_content as description',
                            's.slug', 'do.path as thumb_path', 'do.name as thumb_name',
                            'do_thumb.path as thumb_child_path', 'do_thumb.name as thumb_child_name',
                            'io.lft', 'io.rgt',
                        ])
                        ->first();

                    if (!$collection) {
                        continue;
                    }

                    $itemCount = (int) (($collection->rgt - $collection->lft - 1) / 2);

                    $thumbnail = $item->thumbnail_path ?? null;
                    if (!$thumbnail && !empty($collection->thumb_child_path) && !empty($collection->thumb_child_name)) {
                        $thumbnail = rtrim($collection->thumb_child_path, '/') . '/' . $collection->thumb_child_name;
                    }
                    if (!$thumbnail && !empty($collection->thumb_path) && !empty($collection->thumb_name)) {
                        $candidate = rtrim($collection->thumb_path, '/') . '/' . pathinfo($collection->thumb_name, PATHINFO_FILENAME) . '_142.jpg';
                        $rootDir = config('heratio.uploads_path', '/usr/share/nginx/archive');
                        if (file_exists($rootDir . $candidate)) {
                            $thumbnail = $candidate;
                        }
                    }
                    if (!$thumbnail) {
                        $firstChild = DB::table('information_object as io')
                            ->join('digital_object as do', function ($join) {
                                $join->on('io.id', '=', 'do.object_id')
                                    ->where('do.usage_id', '=', 140);
                            })
                            ->leftJoin('digital_object as do_thumb', function ($join) {
                                $join->on('do_thumb.parent_id', '=', 'do.id')
                                    ->where('do_thumb.usage_id', '=', 142);
                            })
                            ->where('io.lft', '>', $collection->lft)
                            ->where('io.rgt', '<', $collection->rgt)
                            ->select(['do.path', 'do.name', 'do_thumb.path as tp', 'do_thumb.name as tn'])
                            ->orderBy('io.lft')
                            ->first();

                        if ($firstChild) {
                            if (!empty($firstChild->tp) && !empty($firstChild->tn)) {
                                $thumbnail = rtrim($firstChild->tp, '/') . '/' . $firstChild->tn;
                            } elseif (!empty($firstChild->path) && !empty($firstChild->name)) {
                                $candidate = rtrim($firstChild->path, '/') . '/' . pathinfo($firstChild->name, PATHINFO_FILENAME) . '_142.jpg';
                                $rootDir = config('heratio.uploads_path', '/usr/share/nginx/archive');
                                if (file_exists($rootDir . $candidate)) {
                                    $thumbnail = $candidate;
                                }
                            }
                        }
                    }

                    $result[] = [
                        'type' => 'archival',
                        'id' => $collection->id,
                        'name' => $item->title ?? $collection->title,
                        'slug' => $collection->slug,
                        'description' => $item->description ?? $collection->description,
                        'thumbnail' => $thumbnail,
                        'item_count' => $itemCount,
                        'sort_order' => $item->display_order,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Tables may not exist yet
        }

        return $result;
    }

    /**
     * Heritage Admin Dashboard.
     */
    public function adminDashboard()
    {
        // User stats
        $totalUsers = 0;
        $activeUsers = 0;
        $newThisMonth = 0;

        if (Schema::hasTable('user')) {
            $totalUsers = DB::table('user')->count();
        }

        // Active users: users who have audit log entries in the last 30 days
        if (Schema::hasTable('ahg_audit_log')) {
            $activeUsers = DB::table('ahg_audit_log')
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');
        }

        // New users this month: users created this month via audit log (action = 'create', entity_type like user)
        if (Schema::hasTable('ahg_audit_log')) {
            $newThisMonth = DB::table('ahg_audit_log')
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('action', 'create')
                ->where('entity_type', 'LIKE', '%user%')
                ->count();
        }

        // Alert counts
        $activeAlerts = 0;
        if (Schema::hasTable('ahg_audit_log')) {
            $activeAlerts = DB::table('ahg_audit_log')
                ->where('status', '!=', 'success')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
        }

        // Heritage asset stats
        $totalAssets = 0;
        $totalAssetValue = 0;
        $pendingAssets = 0;
        $recognisedAssets = 0;
        if (Schema::hasTable('heritage_asset')) {
            $totalAssets = DB::table('heritage_asset')->count();
            $totalAssetValue = (float) DB::table('heritage_asset')->sum('current_carrying_amount');
            $pendingAssets = DB::table('heritage_asset')->where('recognition_status', 'pending')->count();
            $recognisedAssets = DB::table('heritage_asset')->where('recognition_status', 'recognised')->count();
        }

        // Heritage tenant info
        $tenants = collect();
        if (Schema::hasTable('heritage_tenant')) {
            $tenants = DB::table('heritage_tenant')->orderBy('name')->get();
        }

        return view('ahg-heritage-manage::admin-dashboard', compact(
            'totalUsers',
            'activeUsers',
            'newThisMonth',
            'activeAlerts',
            'totalAssets',
            'totalAssetValue',
            'pendingAssets',
            'recognisedAssets',
            'tenants'
        ));
    }

    /**
     * Heritage Analytics Dashboard.
     */
    public function analyticsDashboard(Request $request)
    {
        $days = (int) $request->input('days', 30);
        if (!in_array($days, [7, 30, 90])) {
            $days = 30;
        }

        $since = now()->subDays($days);

        $pageViews = 0;
        $searches = 0;
        $downloads = 0;
        $uniqueVisitors = 0;
        $avgResults = 0;
        $zeroResultRate = 0;
        $clickThroughRate = 0;
        $pendingRequests = 0;
        $approvalRate = 0;
        $popiaFlags = 0;

        if (Schema::hasTable('ahg_audit_log')) {
            // Page views: all entries with action 'view' or 'browse'
            $pageViews = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->whereIn('action', ['view', 'browse', 'index'])
                ->count();

            // Searches
            $searches = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->where('action', 'search')
                ->count();

            // Downloads
            $downloads = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->where('action', 'download')
                ->count();

            // Unique visitors by distinct user_id or ip_address
            $uniqueByUser = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');

            $uniqueByIp = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->whereNull('user_id')
                ->whereNotNull('ip_address')
                ->distinct('ip_address')
                ->count('ip_address');

            $uniqueVisitors = $uniqueByUser + $uniqueByIp;

            // Search performance from metadata JSON
            if ($searches > 0) {
                $searchLogs = DB::table('ahg_audit_log')
                    ->where('created_at', '>=', $since)
                    ->where('action', 'search')
                    ->whereNotNull('metadata')
                    ->select('metadata')
                    ->limit(1000)
                    ->get();

                $totalResults = 0;
                $zeroResults = 0;
                $clickedResults = 0;

                foreach ($searchLogs as $log) {
                    $meta = json_decode($log->metadata, true);
                    if (is_array($meta)) {
                        $resultCount = $meta['result_count'] ?? $meta['results'] ?? null;
                        if ($resultCount !== null) {
                            $totalResults += (int) $resultCount;
                            if ((int) $resultCount === 0) {
                                $zeroResults++;
                            }
                        }
                        if (!empty($meta['clicked'])) {
                            $clickedResults++;
                        }
                    }
                }

                $searchCount = $searchLogs->count();
                if ($searchCount > 0) {
                    $avgResults = round($totalResults / $searchCount, 1);
                    $zeroResultRate = round(($zeroResults / $searchCount) * 100, 1);
                    $clickThroughRate = round(($clickedResults / $searchCount) * 100, 1);
                }
            }

            // Access control stats
            $pendingRequests = DB::table('ahg_audit_log')
                ->where('action', 'access_request')
                ->where('status', 'pending')
                ->count();

            $totalAccessRequests = DB::table('ahg_audit_log')
                ->where('action', 'access_request')
                ->where('created_at', '>=', $since)
                ->count();

            $approvedRequests = DB::table('ahg_audit_log')
                ->where('action', 'access_request')
                ->where('status', 'success')
                ->where('created_at', '>=', $since)
                ->count();

            $approvalRate = $totalAccessRequests > 0
                ? round(($approvedRequests / $totalAccessRequests) * 100, 1)
                : 0;

            $popiaFlags = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->where('security_classification', 'popia')
                ->count();
        }

        // Heritage analytics daily metrics (supplement audit-log-based stats)
        $dailyMetrics = collect();
        $metricTotals = [];
        if (Schema::hasTable('heritage_analytics_daily')) {
            $dailyMetrics = DB::table('heritage_analytics_daily')
                ->where('date', '>=', $since->toDateString())
                ->orderBy('date')
                ->get();

            // Aggregate by metric_type
            $metricTotals = DB::table('heritage_analytics_daily')
                ->select('metric_type', DB::raw('SUM(metric_value) as total'), DB::raw('AVG(change_percent) as avg_change'))
                ->where('date', '>=', $since->toDateString())
                ->groupBy('metric_type')
                ->pluck('total', 'metric_type')
                ->toArray();
        }

        return view('ahg-heritage-manage::analytics-dashboard', compact(
            'days',
            'pageViews',
            'searches',
            'downloads',
            'uniqueVisitors',
            'avgResults',
            'zeroResultRate',
            'clickThroughRate',
            'pendingRequests',
            'approvalRate',
            'popiaFlags',
            'dailyMetrics',
            'metricTotals'
        ));
    }

    /**
     * Heritage Custodian Dashboard.
     */
    public function custodianDashboard()
    {
        $runningJobs = 0;
        $completedToday = 0;
        $itemsThisMonth = 0;

        if (Schema::hasTable('job')) {
            // Running jobs: status_id for running (typically status_id that is not completed)
            // AtoM job status: 1=running, 2=error, 3=completed
            $runningJobs = DB::table('job')
                ->where('status_id', 1)
                ->count();

            $completedToday = DB::table('job')
                ->whereNotNull('completed_at')
                ->whereDate('completed_at', today())
                ->count();

            $itemsThisMonth = DB::table('job')
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->startOfMonth())
                ->count();
        }

        // Recent activity from ahg_audit_log
        $recentActivity = collect();
        $topContributors = collect();
        $activityByCategory = collect();

        if (Schema::hasTable('ahg_audit_log')) {
            $recentActivity = DB::table('ahg_audit_log')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $topContributors = DB::table('ahg_audit_log')
                ->select('username', DB::raw('COUNT(*) as action_count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotNull('username')
                ->where('username', '!=', '')
                ->groupBy('username')
                ->orderByDesc('action_count')
                ->limit(10)
                ->get();

            $activityByCategory = DB::table('ahg_audit_log')
                ->select('action', DB::raw('COUNT(*) as total'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('action')
                ->orderByDesc('total')
                ->get();
        }

        return view('ahg-heritage-manage::custodian-dashboard', compact(
            'runningJobs',
            'completedToday',
            'itemsThisMonth',
            'recentActivity',
            'topContributors',
            'activityByCategory'
        ));
    }
}
