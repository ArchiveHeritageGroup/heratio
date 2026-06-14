<?php

/**
 * ResearchAnalyticsController - Controller for Heratio
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
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ResearchAnalyticsController - Research analytics dashboard + cross-fonds query.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Both endpoints are auth-gated (research auth route group) and
 * delegate the heavy lifting to dedicated services (ResearchAnalyticsService,
 * CrossFondsQueryService). No cross-calls to other ResearchController methods
 * existed - the methods use only the shared trait helper (getSidebarData) and
 * the injected ResearchService (getResearcherByUserId), so the move is a
 * verbatim lift.
 */
class ResearchAnalyticsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function analytics(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $from = $request->input('from');
        $to   = $request->input('to');

        $data = app(\AhgResearch\Services\ResearchAnalyticsService::class)->dashboard($from, $to);

        return view('research::research.analytics', array_merge(
            $this->getSidebarData('analytics'),
            compact('data')
        ));
    }

    public function crossFondsQuery(Request $request)
    {
        $researcher = Auth::check() ? $this->service->getResearcherByUserId(Auth::id()) : null;
        $svc = app(\AhgResearch\Services\CrossFondsQueryService::class);

        $fondsList = $svc->availableFonds();
        $query = trim((string) $request->input('q', ''));
        $selected = array_map('intval', (array) $request->input('fonds', []));
        $expand = (bool) $request->input('expand');

        $result = null;
        if ($query !== '') {
            $result = $svc->query($query, $selected, $researcher->id ?? null, [
                'expand' => $expand,
                'top_k'  => 30,
            ]);
        }

        return view('research::research.cross-fonds-query', array_merge(
            $this->getSidebarData('crossFonds'),
            compact('fondsList', 'query', 'selected', 'expand', 'result')
        ));
    }
}
