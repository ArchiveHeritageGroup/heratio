<?php

/**
 * HeritageController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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
     * Heritage timeline — shows all periods or redirects to browse with date filter.
     */
    public function timeline(Request $request)
    {
        $culture = 'en';
        $periods = collect();

        try {
            if (Schema::hasTable('heritage_timeline_period')) {
                $periods = DB::table('heritage_timeline_period')
                    ->where('is_enabled', 1)
                    ->orderBy('start_year')
                    ->get();
            }
        } catch (\Exception $e) {
            $periods = collect();
        }

        return view('ahg-heritage-manage::timeline', [
            'periods' => $periods,
            'currentPeriod' => null,
            'items' => collect(),
            'totalItems' => 0,
            'page' => 1,
            'totalPages' => 1,
        ]);
    }

    /**
     * Heritage timeline for a specific period.
     */
    public function timelinePeriod(Request $request, int $period_id)
    {
        $culture = 'en';
        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $periods = collect();
        $currentPeriod = null;
        $items = collect();
        $totalItems = 0;

        try {
            if (Schema::hasTable('heritage_timeline_period')) {
                $periods = DB::table('heritage_timeline_period')
                    ->where('is_enabled', 1)
                    ->orderBy('start_year')
                    ->get();

                $currentPeriod = DB::table('heritage_timeline_period')
                    ->where('id', $period_id)
                    ->first();

                if ($currentPeriod) {
                    $baseQuery = DB::table('information_object as io')
                        ->join('object', 'io.id', '=', 'object.id')
                        ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                            $join->on('io.id', '=', 'ioi.id')
                                ->where('ioi.culture', '=', $culture);
                        })
                        ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                        ->join('status as pub', function ($join) {
                            $join->on('io.id', '=', 'pub.object_id')
                                ->where('pub.type_id', '=', 158);
                        })
                        ->where('pub.status_id', 160)
                        ->where('io.id', '!=', 1);

                    // Filter by event dates within period range
                    if (Schema::hasTable('event')) {
                        $baseQuery->join('event as ev', 'io.id', '=', 'ev.object_id');
                        if ($currentPeriod->start_year) {
                            $baseQuery->where('ev.start_date', '>=', $currentPeriod->start_year . '-01-01');
                        }
                        if ($currentPeriod->end_year) {
                            $baseQuery->where('ev.start_date', '<=', $currentPeriod->end_year . '-12-31');
                        }
                    }

                    $totalItems = (clone $baseQuery)->distinct('io.id')->count('io.id');

                    $items = $baseQuery
                        ->select('io.id', 'ioi.title', 's.slug')
                        ->distinct()
                        ->offset($offset)
                        ->limit($limit)
                        ->get();
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $limit) : 1;

        return view('ahg-heritage-manage::timeline', [
            'periods' => $periods,
            'currentPeriod' => $currentPeriod,
            'items' => $items,
            'totalItems' => $totalItems,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * Heritage creators — redirects to actor browse.
     */
    public function creators()
    {
        return redirect()->route('actor.browse');
    }

    /**
     * Creators autocomplete for search (JSON).
     */
    public function creatorsAutocomplete(Request $request)
    {
        $query = trim($request->input('q', ''));
        $culture = 'en';
        $results = [];

        if (strlen($query) >= 2) {
            try {
                $creators = DB::table('actor as a')
                    ->join('actor_i18n as ai', function ($join) use ($culture) {
                        $join->on('a.id', '=', 'ai.id')
                            ->where('ai.culture', '=', $culture);
                    })
                    ->join('slug as s', 'a.id', '=', 's.object_id')
                    ->leftJoin('relation as r', function ($join) {
                        $join->on('a.id', '=', 'r.object_id');
                    })
                    ->where('ai.authorized_form_of_name', 'LIKE', "%{$query}%")
                    ->where('a.id', '!=', 3) // Exclude root actor
                    ->select(
                        'a.id',
                        'ai.authorized_form_of_name as name',
                        's.slug',
                        DB::raw('COUNT(DISTINCT r.id) as item_count')
                    )
                    ->groupBy('a.id', 'ai.authorized_form_of_name', 's.slug')
                    ->orderByRaw('COUNT(DISTINCT r.id) DESC')
                    ->limit(15)
                    ->get();

                foreach ($creators as $creator) {
                    $results[] = [
                        'id' => $creator->id,
                        'name' => $creator->name,
                        'slug' => $creator->slug,
                        'count' => (int) $creator->item_count,
                    ];
                }
            } catch (\Exception $e) {
                // Fallback
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Heritage explore — redirects to GLAM browse with category filter.
     */
    public function explore(Request $request)
    {
        return redirect()->route('informationobject.browse', $request->query());
    }

    /**
     * Explore a specific category.
     */
    public function exploreCategory(Request $request, string $category)
    {
        $culture = 'en';
        $page = max(1, (int) $request->input('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $categories = collect();
        $currentCategory = null;
        $items = collect();
        $totalItems = 0;

        try {
            if (Schema::hasTable('heritage_explore_category')) {
                $categories = DB::table('heritage_explore_category')
                    ->where('is_enabled', 1)
                    ->orderBy('display_order')
                    ->get();

                $currentCategory = DB::table('heritage_explore_category')
                    ->where('code', $category)
                    ->where('is_enabled', 1)
                    ->first();

                if ($currentCategory && $currentCategory->query_type === 'taxonomy') {
                    $baseQuery = DB::table('information_object as io')
                        ->join('object_term_relation as otr', 'io.id', '=', 'otr.object_id')
                        ->join('term_i18n as ti', function ($join) use ($culture) {
                            $join->on('otr.term_id', '=', 'ti.id')
                                ->where('ti.culture', '=', $culture);
                        })
                        ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                            $join->on('io.id', '=', 'ioi.id')
                                ->where('ioi.culture', '=', $culture);
                        })
                        ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                        ->join('status as pub', function ($join) {
                            $join->on('io.id', '=', 'pub.object_id')
                                ->where('pub.type_id', '=', 158);
                        })
                        ->where('pub.status_id', 160)
                        ->where('io.id', '!=', 1);

                    if ($currentCategory->query_value) {
                        $baseQuery->where('ti.name', $currentCategory->query_value);
                    }

                    $totalItems = (clone $baseQuery)->distinct('io.id')->count('io.id');

                    $items = $baseQuery
                        ->select('io.id', 'ioi.title', 's.slug')
                        ->distinct()
                        ->offset($offset)
                        ->limit($limit)
                        ->get();
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $limit) : 1;

        return view('ahg-heritage-manage::explore', [
            'categories' => $categories,
            'currentCategory' => $currentCategory,
            'items' => $items,
            'totalItems' => $totalItems,
            'page' => $page,
            'totalPages' => $totalPages,
            'category' => $category,
        ]);
    }

    /**
     * Heritage knowledge graph visualization page.
     */
    public function graph()
    {
        $entityTypes = ['person', 'organization', 'place', 'date', 'event', 'work'];
        $stats = [];

        try {
            if (Schema::hasTable('heritage_entity_graph_node')) {
                $stats['total_nodes'] = DB::table('heritage_entity_graph_node')->count();
                $stats['total_edges'] = Schema::hasTable('heritage_entity_graph_edge')
                    ? DB::table('heritage_entity_graph_edge')->count() : 0;
                $stats['by_type'] = DB::table('heritage_entity_graph_node')
                    ->select('entity_type', DB::raw('COUNT(*) as count'))
                    ->groupBy('entity_type')
                    ->pluck('count', 'entity_type')
                    ->toArray();
            }
        } catch (\Exception $e) {
            $stats = ['total_nodes' => 0, 'total_edges' => 0, 'by_type' => []];
        }

        return view('ahg-heritage-manage::graph', [
            'entityTypes' => $entityTypes,
            'stats' => $stats,
        ]);
    }

    /**
     * Knowledge graph data endpoint (JSON for D3.js).
     */
    public function graphData(Request $request)
    {
        $filters = [
            'entity_type' => $request->input('entity_type'),
            'search' => $request->input('search'),
            'min_occurrences' => (int) $request->input('min_occurrences', 1),
        ];
        $limit = min(200, max(10, (int) $request->input('limit', 100)));

        $nodes = [];
        $links = [];
        $stats = [];

        try {
            if (Schema::hasTable('heritage_entity_graph_node')) {
                $nodeQuery = DB::table('heritage_entity_graph_node')
                    ->where('occurrence_count', '>=', $filters['min_occurrences']);

                if ($filters['entity_type']) {
                    $nodeQuery->where('entity_type', $filters['entity_type']);
                }
                if ($filters['search']) {
                    $nodeQuery->where('canonical_value', 'LIKE', '%' . $filters['search'] . '%');
                }

                $nodes = $nodeQuery
                    ->select('id', 'entity_type', 'canonical_value as value', 'display_label as label', 'occurrence_count')
                    ->orderByDesc('occurrence_count')
                    ->limit($limit)
                    ->get()
                    ->toArray();

                $nodeIds = array_column($nodes, 'id');

                if (!empty($nodeIds) && Schema::hasTable('heritage_entity_graph_edge')) {
                    $links = DB::table('heritage_entity_graph_edge')
                        ->whereIn('source_node_id', $nodeIds)
                        ->whereIn('target_node_id', $nodeIds)
                        ->select('source_node_id as source', 'target_node_id as target', 'relation_type', 'weight')
                        ->get()
                        ->toArray();
                }

                $stats = [
                    'total_nodes' => DB::table('heritage_entity_graph_node')->count(),
                    'total_edges' => Schema::hasTable('heritage_entity_graph_edge')
                        ? DB::table('heritage_entity_graph_edge')->count() : 0,
                    'displayed_nodes' => count($nodes),
                    'displayed_edges' => count($links),
                ];
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'nodes' => $nodes,
            'links' => $links,
            'stats' => $stats,
        ]);
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

        // Trends: daily search and click counts from audit log
        $trendSearches = [];
        $trendClicks = [];
        if (Schema::hasTable('ahg_audit_log')) {
            $searchTrends = DB::table('ahg_audit_log')
                ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as cnt'))
                ->where('created_at', '>=', $since)
                ->where('action', 'search')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('day')
                ->pluck('cnt', 'day')
                ->toArray();

            $clickTrends = DB::table('ahg_audit_log')
                ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as cnt'))
                ->where('created_at', '>=', $since)
                ->where('action', 'view')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('day')
                ->pluck('cnt', 'day')
                ->toArray();

            // Fill all dates in range so chart has continuous x-axis
            $current = $since->copy();
            $end = now();
            while ($current->lte($end)) {
                $dateStr = $current->toDateString();
                $trendSearches[$dateStr] = $searchTrends[$dateStr] ?? 0;
                $trendClicks[$dateStr] = $clickTrends[$dateStr] ?? 0;
                $current->addDay();
            }
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

    // ──────────────────────────────────────────────────────────────────────
    // Public heritage pages
    // ──────────────────────────────────────────────────────────────────────

    public function collections() { return view('ahg-heritage-manage::collections', ['items' => collect()]); }

    /**
     * Single collection detail page.
     */
    public function collectionDetail(int $id)
    {
        $culture = 'en';
        $collection = null;

        try {
            if (Schema::hasTable('heritage_featured_collection')) {
                $fc = DB::table('heritage_featured_collection')
                    ->where('id', $id)
                    ->where('is_enabled', 1)
                    ->first();

                if ($fc) {
                    if ($fc->source_type === 'iiif' && Schema::hasTable('iiif_collection')) {
                        $source = DB::table('iiif_collection as c')
                            ->leftJoin('iiif_collection_i18n as ci', function ($join) use ($culture) {
                                $join->on('c.id', '=', 'ci.collection_id')
                                    ->where('ci.culture', '=', $culture);
                            })
                            ->where('c.id', $fc->source_id)
                            ->select('c.*', DB::raw('COALESCE(ci.name, c.name) as display_name'), DB::raw('COALESCE(ci.description, c.description) as display_description'))
                            ->first();

                        $items = $source ? DB::table('iiif_collection_item')
                            ->where('collection_id', $source->id)
                            ->orderBy('sort_order')
                            ->get() : collect();

                        $collection = (object) [
                            'featured' => $fc,
                            'source' => $source,
                            'type' => 'iiif',
                            'items' => $items,
                        ];
                    } else {
                        $source = DB::table('information_object as io')
                            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                                $join->on('io.id', '=', 'ioi.id')
                                    ->where('ioi.culture', '=', $culture);
                            })
                            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                            ->where('io.id', $fc->source_id)
                            ->select('io.*', 'ioi.title', 'ioi.scope_and_content as description', 's.slug')
                            ->first();

                        $collection = (object) [
                            'featured' => $fc,
                            'source' => $source,
                            'type' => 'archival',
                            'items' => collect(),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        if (!$collection) {
            abort(404);
        }

        return view('ahg-heritage-manage::collection-detail', ['collection' => $collection]);
    }

    /**
     * Entity page by type and value (knowledge graph).
     */
    public function entityByTypeValue(string $type, string $value)
    {
        $value = urldecode($value);
        $entity = null;
        $relatedEntities = collect();
        $objects = collect();

        try {
            if (Schema::hasTable('heritage_entity_graph_node')) {
                $entity = DB::table('heritage_entity_graph_node')
                    ->where('entity_type', $type)
                    ->where('canonical_value', $value)
                    ->first();

                if ($entity && Schema::hasTable('heritage_entity_graph_edge')) {
                    $relatedEntities = DB::table('heritage_entity_graph_edge as e')
                        ->join('heritage_entity_graph_node as n', function ($join) use ($entity) {
                            $join->on(DB::raw("CASE WHEN e.source_node_id = {$entity->id} THEN e.target_node_id ELSE e.source_node_id END"), '=', 'n.id');
                        })
                        ->where(function ($q) use ($entity) {
                            $q->where('e.source_node_id', $entity->id)
                              ->orWhere('e.target_node_id', $entity->id);
                        })
                        ->select('n.*', 'e.relation_type', 'e.weight')
                        ->orderByDesc('e.weight')
                        ->limit(20)
                        ->get();
                }

                if ($entity && Schema::hasTable('heritage_entity_graph_mention')) {
                    $objects = DB::table('heritage_entity_graph_mention as m')
                        ->join('information_object_i18n as ioi', function ($join) {
                            $join->on('m.object_id', '=', 'ioi.id')
                                ->where('ioi.culture', '=', 'en');
                        })
                        ->leftJoin('slug as s', 'm.object_id', '=', 's.object_id')
                        ->where('m.node_id', $entity->id)
                        ->select('m.object_id', 'ioi.title', 's.slug', 'm.context_snippet', 'm.confidence')
                        ->orderByDesc('m.confidence')
                        ->limit(20)
                        ->get();
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        if (!$entity) {
            abort(404);
        }

        return view('ahg-heritage-manage::entity', [
            'entity' => $entity,
            'relatedEntities' => $relatedEntities,
            'objects' => $objects,
        ]);
    }

    public function entity(int $id) { return view('ahg-heritage-manage::entity', ['items' => collect()]); }
    public function landingError() { return view('ahg-heritage-manage::landing-error'); }
    public function searchError() { return view('ahg-heritage-manage::search-error'); }
    public function contribute() { return view('ahg-heritage-manage::contribute', ['items' => collect()]); }

    /**
     * Contribute to a specific item by slug.
     */
    public function contributeToItem(Request $request, string $slug)
    {
        $culture = 'en';

        $item = DB::table('information_object as io')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('digital_object as do', function ($join) {
                $join->on('io.id', '=', 'do.object_id')
                    ->where('do.usage_id', '=', 140);
            })
            ->where('s.slug', $slug)
            ->select(
                'io.id',
                's.slug',
                'ioi.title',
                'ioi.scope_and_content',
                'do.path as thumbnail_path',
                'do.name as thumbnail_name',
                'do.mime_type'
            )
            ->first();

        if (!$item) {
            abort(404);
        }

        $thumbnail = null;
        if ($item->thumbnail_path && $item->thumbnail_name) {
            $path = rtrim($item->thumbnail_path, '/');
            $basename = pathinfo($item->thumbnail_name, PATHINFO_FILENAME);
            $thumbnail = $path . '/' . $basename . '_142.jpg';
        }

        // Handle POST submission
        if ($request->isMethod('post')) {
            $request->validate([
                'type_code' => 'required|string',
                'content' => 'required|string',
            ]);

            try {
                if (Schema::hasTable('heritage_contribution')) {
                    DB::table('heritage_contribution')->insert([
                        'object_id' => $item->id,
                        'contributor_id' => auth()->id(),
                        'type_code' => $request->input('type_code'),
                        'content' => $request->input('content'),
                        'status' => 'pending',
                        'created_at' => now(),
                    ]);
                }

                return redirect()->route('heritage.my-contributions')
                    ->with('success', 'Contribution submitted successfully');
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to submit contribution: ' . $e->getMessage());
            }
        }

        return view('ahg-heritage-manage::contribute', [
            'item' => $item,
            'slug' => $slug,
            'thumbnail' => $thumbnail,
        ]);
    }

    public function myContributions() { return view('ahg-heritage-manage::my-contributions', ['items' => collect()]); }
    public function myAccessRequests() { return view('ahg-heritage-manage::my-access-requests', ['items' => collect()]); }
    public function requestAccess(int $id = null) { return view('ahg-heritage-manage::request-access', ['items' => collect()]); }

    /**
     * Request access to an item by slug (GET shows form, POST submits).
     */
    public function requestAccessBySlug(Request $request, string $slug)
    {
        $culture = 'en';

        $object = DB::table('information_object as io')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->where('s.slug', $slug)
            ->select('io.*', 'ioi.title', 's.slug')
            ->first();

        if (!$object) {
            abort(404);
        }

        $purposes = [];
        try {
            if (Schema::hasTable('heritage_access_purpose')) {
                $purposes = DB::table('heritage_access_purpose')
                    ->where('is_active', 1)
                    ->orderBy('display_order')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            $purposes = [];
        }

        if ($request->isMethod('post') && auth()->check()) {
            $request->validate([
                'purpose_id' => 'required|integer',
                'justification' => 'required|string',
            ]);

            try {
                if (Schema::hasTable('heritage_access_request')) {
                    DB::table('heritage_access_request')->insert([
                        'user_id' => auth()->id(),
                        'object_id' => $object->id,
                        'purpose_id' => $request->input('purpose_id'),
                        'justification' => $request->input('justification'),
                        'research_description' => $request->input('research_description'),
                        'institution_affiliation' => $request->input('institution_affiliation'),
                        'status' => 'pending',
                        'created_at' => now(),
                    ]);
                }

                return redirect()->route('heritage.my-access-requests')
                    ->with('success', 'Access request submitted successfully');
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to submit request');
            }
        }

        return view('ahg-heritage-manage::request-access', [
            'resource' => $object,
            'purposes' => $purposes,
        ]);
    }

    public function contributorLogin() { return redirect()->route('login'); }

    /**
     * Contributor registration (GET shows form, POST submits).
     */
    public function contributorRegister(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'email' => 'required|email',
                'display_name' => 'required|string|max:255',
                'password' => 'required|string|min:8',
                'confirm_password' => 'required|same:password',
                'agree_terms' => 'accepted',
            ]);

            try {
                if (Schema::hasTable('heritage_contributor')) {
                    $token = bin2hex(random_bytes(32));

                    DB::table('heritage_contributor')->insert([
                        'email' => $request->input('email'),
                        'display_name' => $request->input('display_name'),
                        'password_hash' => bcrypt($request->input('password')),
                        'verify_token' => $token,
                        'is_active' => 0,
                        'is_verified' => 0,
                        'points' => 0,
                        'created_at' => now(),
                    ]);

                    return view('ahg-heritage-manage::contributor-register', [
                        'success' => true,
                        'items' => collect(),
                    ]);
                }
            } catch (\Exception $e) {
                return back()->with('error', 'Registration failed: ' . $e->getMessage());
            }
        }

        return view('ahg-heritage-manage::contributor-register', [
            'items' => collect(),
            'success' => false,
        ]);
    }

    /**
     * Contributor logout — clear session and redirect to landing.
     */
    public function contributorLogout()
    {
        session()->forget(['contributor_id', 'contributor_token', 'contributor_name']);

        return redirect()->route('heritage.landing')
            ->with('success', 'You have been logged out');
    }

    /**
     * Verify contributor email with token.
     */
    public function contributorVerifyToken(string $token)
    {
        $success = false;
        $error = null;

        try {
            if (Schema::hasTable('heritage_contributor')) {
                $contributor = DB::table('heritage_contributor')
                    ->where('verify_token', $token)
                    ->where('is_verified', 0)
                    ->first();

                if ($contributor) {
                    DB::table('heritage_contributor')
                        ->where('id', $contributor->id)
                        ->update([
                            'is_verified' => 1,
                            'is_active' => 1,
                            'verify_token' => null,
                            'verified_at' => now(),
                        ]);
                    $success = true;
                } else {
                    $error = 'Invalid or expired verification token';
                }
            }
        } catch (\Exception $e) {
            $error = 'Verification failed';
        }

        return view('ahg-heritage-manage::contributor-verify', [
            'success' => $success,
            'error' => $error,
            'items' => collect(),
        ]);
    }

    public function contributorVerify() { return view('ahg-heritage-manage::contributor-verify', ['items' => collect()]); }

    /**
     * Contributor profile by ID.
     */
    public function contributorProfileById(int $id)
    {
        $contributor = null;
        $contributions = collect();

        try {
            if (Schema::hasTable('heritage_contributor')) {
                $contributor = DB::table('heritage_contributor')
                    ->where('id', $id)
                    ->where('is_active', 1)
                    ->first();
            }

            if ($contributor && Schema::hasTable('heritage_contribution')) {
                $contributions = DB::table('heritage_contribution')
                    ->where('contributor_id', $id)
                    ->where('status', 'approved')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get();
            }
        } catch (\Exception $e) {
            // Fallback
        }

        if (!$contributor) {
            abort(404);
        }

        return view('ahg-heritage-manage::contributor-profile', [
            'contributor' => $contributor,
            'contributions' => $contributions,
        ]);
    }

    public function contributorProfile() { return view('ahg-heritage-manage::contributor-profile', ['items' => collect()]); }

    // ──────────────────────────────────────────────────────────────────────
    // Admin heritage pages
    // ──────────────────────────────────────────────────────────────────────

    public function adminAccessRequests() { return view('ahg-heritage-manage::admin-access-requests', ['items' => collect()]); }
    public function adminBranding() { return view('ahg-heritage-manage::admin-branding', ['items' => collect()]); }
    public function adminConfig() { return view('ahg-heritage-manage::admin-config', ['items' => collect()]); }
    public function adminEmbargoes() { return view('ahg-heritage-manage::admin-embargoes', ['items' => collect()]); }
    public function adminFeaturedCollections() { return view('ahg-heritage-manage::admin-featured-collections', ['items' => collect()]); }
    public function adminFeatures() { return view('ahg-heritage-manage::admin-features', ['items' => collect()]); }
    public function adminHeroSlides() { return view('ahg-heritage-manage::admin-hero-slides', ['items' => collect()]); }
    public function adminPopia() { return view('ahg-heritage-manage::admin-popia', ['items' => collect()]); }
    public function adminUsers() { return view('ahg-heritage-manage::admin-users', ['items' => collect()]); }
    public function analyticsAlerts() { return view('ahg-heritage-manage::analytics-alerts', ['stats' => [], 'records' => []]); }
    public function analyticsContent() { return view('ahg-heritage-manage::analytics-content', ['stats' => [], 'records' => []]); }
    public function analyticsSearch() { return view('ahg-heritage-manage::analytics-search', ['stats' => [], 'records' => []]); }
    public function custodianBatch() { return view('ahg-heritage-manage::custodian-batch', ['items' => collect()]); }
    public function custodianHistory() { return view('ahg-heritage-manage::custodian-history', ['items' => collect()]); }
    public function custodianItem(int $id) { return view('ahg-heritage-manage::custodian-item', ['items' => collect()]); }

    /**
     * Custodian item by slug.
     */
    public function custodianItemBySlug(string $slug)
    {
        $culture = 'en';

        $item = DB::table('information_object as io')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->where('s.slug', $slug)
            ->select('io.*', 'ioi.title', 's.slug')
            ->first();

        if (!$item) {
            abort(404);
        }

        return view('ahg-heritage-manage::custodian-item', [
            'item' => $item,
            'items' => collect(),
        ]);
    }
    public function reviewQueue() { return view('ahg-heritage-manage::review-queue', ['items' => collect()]); }
    public function reviewContribution(int $id) { return view('ahg-heritage-manage::review-contribution', ['items' => collect()]); }
    public function leaderboard() { return view('ahg-heritage-manage::leaderboard', ['items' => collect()]); }

    // ────��────────────────────────────────────��────────────────────────────
    // API Endpoints (JSON responses)
    // ───────────��──────────────────────────────────────────────────────────

    /**
     * API: Landing page data (JSON).
     */
    public function apiLanding(Request $request)
    {
        $culture = 'en';
        $data = [];

        try {
            // Config
            $config = null;
            if (Schema::hasTable('heritage_landing_config')) {
                $config = DB::table('heritage_landing_config')->first();
            }
            $data['config'] = $config ? (array) $config : [];

            // Hero images
            if (Schema::hasTable('heritage_hero_slide')) {
                $data['hero_images'] = DB::table('heritage_hero_slide')
                    ->where('is_enabled', 1)
                    ->orderBy('display_order')
                    ->get()
                    ->toArray();
            } else {
                $data['hero_images'] = [];
            }

            // Stats
            $data['stats'] = [
                'total_items' => DB::table('information_object')->where('id', '!=', 1)->count(),
                'total_digital_objects' => DB::table('digital_object')->where('usage_id', 140)->count(),
                'total_creators' => DB::table('actor')->where('id', '!=', 3)->count(),
            ];

            // Curated collections
            $data['curated_collections'] = $this->getCuratedCollections($culture, 12);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Search/discover (JSON).
     */
    public function apiDiscover(Request $request)
    {
        $culture = 'en';
        $query = trim($request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 20)));
        $filters = $request->input('filters', []);
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?: [];
        }

        try {
            $searchService = new \AhgHeritageManage\Services\HeritageSearchService($culture);
            $results = $searchService->search($query, $filters, $page, $limit);

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Autocomplete suggestions (JSON).
     */
    public function apiAutocomplete(Request $request)
    {
        $query = trim($request->input('q', ''));
        $limit = min(20, max(1, (int) $request->input('limit', 10)));
        $culture = 'en';

        if (strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        try {
            $results = DB::table('information_object_i18n')
                ->where('culture', $culture)
                ->where('title', 'LIKE', '%' . $query . '%')
                ->whereNotNull('title')
                ->where('title', '!=', '')
                ->select('id', 'title')
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    $slug = DB::table('slug')->where('object_id', $row->id)->value('slug');
                    return [
                        'id' => $row->id,
                        'title' => $row->title,
                        'slug' => $slug,
                    ];
                })
                ->toArray();

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Log a click on a search result (POST, JSON).
     */
    public function apiClick(Request $request)
    {
        $data = $request->json()->all();

        try {
            if (Schema::hasTable('heritage_search_click')) {
                DB::table('heritage_search_click')->insert([
                    'search_id' => $data['search_id'] ?? null,
                    'object_id' => $data['object_id'] ?? null,
                    'position' => $data['position'] ?? null,
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Update dwell time for a click (POST, JSON).
     */
    public function apiDwell(Request $request)
    {
        $data = $request->json()->all();

        try {
            if (Schema::hasTable('heritage_search_click') && !empty($data['click_id'])) {
                DB::table('heritage_search_click')
                    ->where('id', $data['click_id'])
                    ->update([
                        'dwell_time_ms' => $data['dwell_time_ms'] ?? 0,
                    ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get search analytics (admin only, JSON).
     */
    public function apiAnalytics(Request $request)
    {
        $days = min(90, max(1, (int) $request->input('days', 30)));
        $since = now()->subDays($days);

        $data = [];

        try {
            if (Schema::hasTable('ahg_audit_log')) {
                $data['searches'] = DB::table('ahg_audit_log')
                    ->where('action', 'search')
                    ->where('created_at', '>=', $since)
                    ->count();

                $data['page_views'] = DB::table('ahg_audit_log')
                    ->whereIn('action', ['view', 'browse', 'index'])
                    ->where('created_at', '>=', $since)
                    ->count();

                $data['unique_visitors'] = DB::table('ahg_audit_log')
                    ->where('created_at', '>=', $since)
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id');
            }

            if (Schema::hasTable('heritage_analytics_daily')) {
                $data['daily_metrics'] = DB::table('heritage_analytics_daily')
                    ->where('date', '>=', $since->toDateString())
                    ->orderBy('date')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Hero slides (JSON).
     */
    public function apiHeroSlides(Request $request)
    {
        $slides = [];

        try {
            if (Schema::hasTable('heritage_hero_slide')) {
                $slides = DB::table('heritage_hero_slide')
                    ->where('is_enabled', 1)
                    ->orderBy('display_order')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'data' => $slides]);
    }

    /**
     * API: Featured collections (JSON).
     */
    public function apiFeaturedCollections(Request $request)
    {
        $culture = 'en';
        $limit = min(20, max(1, (int) $request->input('limit', 6)));

        try {
            $collections = $this->getCuratedCollections($culture, $limit);
            return response()->json(['success' => true, 'data' => $collections]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Explore categories (JSON).
     */
    public function apiExploreCategories(Request $request)
    {
        $categories = [];

        try {
            if (Schema::hasTable('heritage_explore_category')) {
                $categories = DB::table('heritage_explore_category')
                    ->where('is_enabled', 1)
                    ->orderBy('display_order')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * API: Explore category items (JSON).
     */
    public function apiExploreCategoryItems(Request $request, string $category)
    {
        $culture = 'en';
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 24)));
        $offset = ($page - 1) * $limit;

        try {
            $items = [];
            $total = 0;

            if (Schema::hasTable('heritage_explore_category')) {
                $cat = DB::table('heritage_explore_category')
                    ->where('code', $category)
                    ->where('is_enabled', 1)
                    ->first();

                if ($cat) {
                    $baseQuery = DB::table('information_object as io')
                        ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                            $join->on('io.id', '=', 'ioi.id')
                                ->where('ioi.culture', '=', $culture);
                        })
                        ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                        ->join('status as pub', function ($join) {
                            $join->on('io.id', '=', 'pub.object_id')
                                ->where('pub.type_id', '=', 158);
                        })
                        ->where('pub.status_id', 160)
                        ->where('io.id', '!=', 1);

                    if ($cat->query_type === 'taxonomy' && $cat->query_value) {
                        $baseQuery->join('object_term_relation as otr', 'io.id', '=', 'otr.object_id')
                            ->join('term_i18n as ti', function ($join) use ($culture, $cat) {
                                $join->on('otr.term_id', '=', 'ti.id')
                                    ->where('ti.culture', '=', $culture)
                                    ->where('ti.name', '=', $cat->query_value);
                            });
                    }

                    $total = (clone $baseQuery)->distinct('io.id')->count('io.id');
                    $items = $baseQuery
                        ->select('io.id', 'ioi.title', 's.slug')
                        ->distinct()
                        ->offset($offset)
                        ->limit($limit)
                        ->get()
                        ->toArray();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 1,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Timeline periods (JSON).
     */
    public function apiTimelinePeriods(Request $request)
    {
        $periods = [];

        try {
            if (Schema::hasTable('heritage_timeline_period')) {
                $periods = DB::table('heritage_timeline_period')
                    ->where('is_enabled', 1)
                    ->orderBy('start_year')
                    ->get()
                    ->toArray();
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'data' => $periods]);
    }

    /**
     * API: Timeline period items (JSON).
     */
    public function apiTimelinePeriodItems(Request $request, int $period_id)
    {
        $culture = 'en';
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 24)));
        $offset = ($page - 1) * $limit;

        try {
            $period = null;
            $items = [];
            $total = 0;

            if (Schema::hasTable('heritage_timeline_period')) {
                $period = DB::table('heritage_timeline_period')
                    ->where('id', $period_id)
                    ->first();

                if ($period) {
                    $baseQuery = DB::table('information_object as io')
                        ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                            $join->on('io.id', '=', 'ioi.id')
                                ->where('ioi.culture', '=', $culture);
                        })
                        ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
                        ->join('status as pub', function ($join) {
                            $join->on('io.id', '=', 'pub.object_id')
                                ->where('pub.type_id', '=', 158);
                        })
                        ->where('pub.status_id', 160)
                        ->where('io.id', '!=', 1);

                    if (Schema::hasTable('event')) {
                        $baseQuery->join('event as ev', 'io.id', '=', 'ev.object_id');
                        if ($period->start_year) {
                            $baseQuery->where('ev.start_date', '>=', $period->start_year . '-01-01');
                        }
                        if ($period->end_year) {
                            $baseQuery->where('ev.start_date', '<=', $period->end_year . '-12-31');
                        }
                    }

                    $total = (clone $baseQuery)->distinct('io.id')->count('io.id');
                    $items = $baseQuery
                        ->select('io.id', 'ioi.title', 's.slug')
                        ->distinct()
                        ->offset($offset)
                        ->limit($limit)
                        ->get()
                        ->toArray();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $items,
                'period' => $period,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => $total > 0 ? (int) ceil($total / $limit) : 1,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Submit contribution (POST, JSON).
     */
    public function apiSubmitContribution(Request $request)
    {
        $data = $request->json()->all();

        if (empty($data['item_id']) || empty($data['type_code']) || empty($data['content'])) {
            return response()->json(['success' => false, 'error' => 'Missing required fields: item_id, type_code, content']);
        }

        try {
            $id = null;
            if (Schema::hasTable('heritage_contribution')) {
                $id = DB::table('heritage_contribution')->insertGetId([
                    'object_id' => (int) $data['item_id'],
                    'contributor_id' => auth()->id(),
                    'type_code' => $data['type_code'],
                    'content' => $data['content'],
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => ['id' => $id, 'status' => 'pending'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get contribution status (JSON).
     */
    public function apiContributionStatus(Request $request, int $id)
    {
        try {
            $contribution = null;
            if (Schema::hasTable('heritage_contribution')) {
                $contribution = DB::table('heritage_contribution')
                    ->where('id', $id)
                    ->first();
            }

            if (!$contribution) {
                return response()->json(['success' => false, 'error' => 'Contribution not found']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $contribution->id,
                    'status' => $contribution->status,
                    'created_at' => $contribution->created_at ?? null,
                    'reviewed_at' => $contribution->reviewed_at ?? null,
                    'review_notes' => $contribution->review_notes ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Suggest tags (JSON).
     */
    public function apiSuggestTags(Request $request)
    {
        $query = trim($request->input('q', ''));
        $limit = min(20, max(1, (int) $request->input('limit', 10)));
        $culture = 'en';

        if (strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        try {
            $terms = DB::table('term_i18n as ti')
                ->join('term as t', 'ti.id', '=', 't.id')
                ->where('t.taxonomy_id', 35) // Subjects taxonomy
                ->where('ti.culture', $culture)
                ->where('ti.name', 'LIKE', '%' . $query . '%')
                ->orderBy('ti.name')
                ->limit($limit)
                ->pluck('ti.name')
                ->toArray();

            return response()->json(['success' => true, 'data' => $terms]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Entity detail by type and value (JSON).
     */
    public function apiEntity(Request $request, string $type, string $value)
    {
        $value = urldecode($value);

        try {
            $entity = null;
            $relatedEntities = [];
            $objects = [];

            if (Schema::hasTable('heritage_entity_graph_node')) {
                $entity = DB::table('heritage_entity_graph_node')
                    ->where('entity_type', $type)
                    ->where('canonical_value', $value)
                    ->first();

                if ($entity) {
                    if (Schema::hasTable('heritage_entity_graph_edge')) {
                        $relatedEntities = DB::table('heritage_entity_graph_edge as e')
                            ->join('heritage_entity_graph_node as n', function ($join) use ($entity) {
                                $join->on(DB::raw("CASE WHEN e.source_node_id = {$entity->id} THEN e.target_node_id ELSE e.source_node_id END"), '=', 'n.id');
                            })
                            ->where(function ($q) use ($entity) {
                                $q->where('e.source_node_id', $entity->id)
                                  ->orWhere('e.target_node_id', $entity->id);
                            })
                            ->select('n.*', 'e.relation_type', 'e.weight')
                            ->orderByDesc('e.weight')
                            ->limit(20)
                            ->get()
                            ->toArray();
                    }

                    if (Schema::hasTable('heritage_entity_graph_mention')) {
                        $objects = DB::table('heritage_entity_graph_mention as m')
                            ->join('information_object_i18n as ioi', function ($join) {
                                $join->on('m.object_id', '=', 'ioi.id')
                                    ->where('ioi.culture', '=', 'en');
                            })
                            ->leftJoin('slug as s', 'm.object_id', '=', 's.object_id')
                            ->where('m.node_id', $entity->id)
                            ->select('m.object_id', 'ioi.title', 's.slug', 'm.context_snippet', 'm.confidence')
                            ->orderByDesc('m.confidence')
                            ->limit(20)
                            ->get()
                            ->toArray();
                    }
                }
            }

            if (!$entity) {
                return response()->json(['success' => false, 'error' => 'Entity not found']);
            }

            return response()->json([
                'success' => true,
                'entity' => $entity,
                'related_entities' => $relatedEntities,
                'objects' => $objects,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Related entities for a node (JSON).
     */
    public function apiEntityRelated(Request $request, int $id)
    {
        $depth = min(3, max(1, (int) $request->input('depth', 1)));
        $limit = min(50, max(5, (int) $request->input('limit', 20)));

        try {
            $related = [];

            if (Schema::hasTable('heritage_entity_graph_edge') && Schema::hasTable('heritage_entity_graph_node')) {
                $related = DB::table('heritage_entity_graph_edge as e')
                    ->join('heritage_entity_graph_node as n', function ($join) use ($id) {
                        $join->on(DB::raw("CASE WHEN e.source_node_id = {$id} THEN e.target_node_id ELSE e.source_node_id END"), '=', 'n.id');
                    })
                    ->where(function ($q) use ($id) {
                        $q->where('e.source_node_id', $id)
                          ->orWhere('e.target_node_id', $id);
                    })
                    ->select('n.*', 'e.relation_type', 'e.weight')
                    ->orderByDesc('e.weight')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            }

            return response()->json(['success' => true, 'related_entities' => $related]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Entity search/autocomplete (JSON).
     */
    public function apiEntitySearch(Request $request)
    {
        $query = trim($request->input('q', ''));
        $type = $request->input('type');
        $limit = min(20, max(5, (int) $request->input('limit', 10)));

        if (strlen($query) < 2) {
            return response()->json(['success' => true, 'results' => []]);
        }

        try {
            $queryBuilder = DB::table('heritage_entity_graph_node')
                ->where('canonical_value', 'LIKE', '%' . $query . '%')
                ->orderByDesc('occurrence_count')
                ->limit($limit);

            if ($type) {
                $queryBuilder->where('entity_type', $type);
            }

            $results = $queryBuilder->select(
                'id',
                'entity_type',
                'canonical_value as value',
                'display_label as label',
                'occurrence_count'
            )->get()->toArray();

            return response()->json(['success' => true, 'results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Graph statistics (JSON).
     */
    public function apiGraphStats(Request $request)
    {
        try {
            $stats = [];
            $topEntities = [];

            if (Schema::hasTable('heritage_entity_graph_node')) {
                $stats['total_nodes'] = DB::table('heritage_entity_graph_node')->count();
                $stats['total_edges'] = Schema::hasTable('heritage_entity_graph_edge')
                    ? DB::table('heritage_entity_graph_edge')->count() : 0;
                $stats['by_type'] = DB::table('heritage_entity_graph_node')
                    ->select('entity_type', DB::raw('COUNT(*) as count'))
                    ->groupBy('entity_type')
                    ->pluck('count', 'entity_type')
                    ->toArray();

                $topEntities = DB::table('heritage_entity_graph_node')
                    ->orderByDesc('occurrence_count')
                    ->limit(10)
                    ->select('id', 'entity_type', 'canonical_value as value', 'display_label as label', 'occurrence_count')
                    ->get()
                    ->toArray();
            }

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'top_entities' => $topEntities,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
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
