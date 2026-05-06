<?php

/**
 * HeritageController - Controller for Heratio
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
...
