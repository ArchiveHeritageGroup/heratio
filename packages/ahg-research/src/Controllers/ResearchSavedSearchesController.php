<?php

/**
 * ResearchSavedSearchesController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchSavedSearchesController - Researcher saved-search management.
 *
 * Extracted from ResearchController as a stage of the monolith decomposition
 * (issue #1269). All endpoints are auth-gated and operate on the current
 * researcher's own saved searches via the research_saved_search table and the
 * injected ResearchService (saveSearch / deleteSavedSearch / getSavedSearches).
 *
 * Cross-call analysis at extraction time:
 *  (a) extractSearchKeyword() - the only caller was searchSnapshot() (a
 *      saved-search method), so it MOVED here as a private helper and was
 *      deleted from the monolith.
 *  (b) publicRegister() - NOT a saved-search method and stays in the monolith.
 *      The only $this->publicRegister() call lives in storePublicRegistration()
 *      (also a monolith method); no saved-search method touches it, so there
 *      was no tangle to resolve for this extraction.
 */
class ResearchSavedSearchesController extends Controller
{
    use LogsResearchActivity;
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function savedSearches(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');
            if ($action === 'save') {
                $this->service->saveSearch($researcher->id, [
                    'name' => $request->input('name'),
                    'search_query' => $request->input('search_query'),
                ]);
                $this->logResearchActivity('create', 'saved_search', null, $request->input('name'), ['method' => 'ResearchSavedSearchesController@savedSearches']);
            } elseif ($action === 'delete') {
                $this->service->deleteSavedSearch((int) $request->input('id'), $researcher->id);
                $this->logResearchActivity('delete', 'saved_search', (int) $request->input('id'), null, ['method' => 'ResearchSavedSearchesController@savedSearches']);
            }
            return redirect()->route('research.savedSearches');
        }

        $savedSearches = $this->service->getSavedSearches($researcher->id);

        return view('research::research.saved-searches', array_merge(
            $this->getSidebarData('savedSearches'),
            compact('researcher', 'savedSearches')
        ));
    }

    public function storeSavedSearch(Request $request)
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) return response()->json(['error' => 'Not authenticated'], 401);
            return redirect()->route('login');
        }
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) {
            if ($request->expectsJson()) return response()->json(['error' => 'Not a researcher'], 403);
            return redirect()->route('researcher.register');
        }

        $isAjax = $request->expectsJson() || $request->isJson();

        // Accept search_query OR search_params (from GLAM browse AJAX)
        $searchQuery = $request->input('search_query')
            ?: $request->input('search_params')
            ?: $request->input('query')
            ?: '';

        if (!$searchQuery) {
            if ($isAjax) return response()->json(['success' => false, 'error' => 'Search query is required'], 422);
            return redirect()->route('research.savedSearches')->with('error', 'Search query is required');
        }

        $this->service->saveSearch($researcher->id, [
            'name' => $request->input('name'),
            'search_query' => $searchQuery,
        ]);

        $this->logResearchActivity('create', 'saved_search', null, $request->input('name'), ['method' => 'ResearchSavedSearchesController@storeSavedSearch']);

        if ($isAjax) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('research.savedSearches')->with('success', 'Search saved');
    }

    /**
     * Snapshot current search results for diff comparison.
     */
    public function searchSnapshot(Request $request, int $id)
    {
        if (!Auth::check()) return response()->json(['error' => 'Not authenticated'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $search = DB::table('research_saved_search')
            ->where('id', $id)->where('researcher_id', $researcher->id)->first();
        if (!$search) return response()->json(['error' => 'Search not found'], 404);

        // Run the search query against DB to get current result IDs
        $keyword = $this->extractSearchKeyword($search->search_query);
        $results = [];
        if ($keyword) {
            $results = DB::table('information_object as io')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                })
                ->where('io.id', '!=', 1)
                ->where(function ($q) use ($keyword) {
                    $q->where('i18n.title', 'LIKE', "%{$keyword}%")
                      ->orWhere('i18n.scope_and_content', 'LIKE', "%{$keyword}%")
                      ->orWhere('io.identifier', 'LIKE', "%{$keyword}%");
                })
                ->pluck('io.id')->toArray();
        }

        DB::table('research_saved_search')->where('id', $id)->update([
            'result_snapshot_json' => json_encode($results),
            'last_result_count' => count($results),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'count' => count($results)]);
    }

    /**
     * Diff current search results against last snapshot.
     */
    public function searchDiff(Request $request, int $id)
    {
        if (!Auth::check()) return response()->json(['error' => 'Not authenticated'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $search = DB::table('research_saved_search')
            ->where('id', $id)->where('researcher_id', $researcher->id)->first();
        if (!$search) return response()->json(['error' => 'Search not found'], 404);

        $previousIds = json_decode($search->result_snapshot_json ?? '[]', true) ?: [];
        if (empty($previousIds)) {
            return response()->json(['error' => 'No previous snapshot. Take a snapshot first.']);
        }

        // Run current search
        $query = $search->search_query;
        $currentIds = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('io.id', '!=', 1)
            ->where(function ($q) use ($query) {
                $q->where('i18n.title', 'LIKE', "%{$query}%")
                  ->orWhere('io.identifier', 'LIKE', "%{$query}%");
            })
            ->pluck('io.id')->toArray();

        $added = array_values(array_diff($currentIds, $previousIds));
        $removed = array_values(array_diff($previousIds, $currentIds));
        $unchanged = count(array_intersect($currentIds, $previousIds));

        return response()->json([
            'previous_count' => count($previousIds),
            'current_count' => count($currentIds),
            'unchanged_count' => $unchanged,
            'added' => $added,
            'removed' => $removed,
        ]);
    }

    /**
     * Extract the search keyword from a saved search query string.
     * Handles both plain keywords ("ai") and URL params ("query=ai&title=&...").
     */
    private function extractSearchKeyword(string $searchQuery): string
    {
        if (str_contains($searchQuery, '=')) {
            parse_str($searchQuery, $params);
            return trim($params['query'] ?? $params['sq0'] ?? $params['subquery'] ?? '');
        }
        return trim($searchQuery);
    }

    public function runSavedSearch(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $search = DB::table('research_saved_search')
            ->where('id', $id)
            ->where('researcher_id', $researcher->id)
            ->first();

        if (!$search) abort(404, 'Saved search not found');

        // Update last_run_at
        DB::table('research_saved_search')->where('id', $id)->update([
            'last_run_at' => date('Y-m-d H:i:s'),
        ]);

        // Redirect to search results with the saved query
        return redirect('/informationobject/browse?query=' . urlencode($search->search_query));
    }

    public function destroySavedSearch(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $this->service->deleteSavedSearch($id, $researcher->id);

        $this->logResearchActivity('delete', 'saved_search', $id, null, ['method' => 'ResearchSavedSearchesController@destroySavedSearch']);

        return redirect()->route('research.savedSearches')->with('success', 'Saved search deleted');
    }
}
