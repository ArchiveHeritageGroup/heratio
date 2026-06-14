<?php

/**
 * ResearchAssessmentsController - Controller for Heratio
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
use Illuminate\Support\Facades\DB;

/**
 * ResearchAssessmentsController - Researcher source-assessment listing.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1253 / #1269). The single endpoint is auth-gated and lists the most
 * recent source assessments (research_source_assessment) joined to the
 * archival description title/slug and the assessing researcher's name. No
 * cross-calls to other ResearchController methods existed - the method used
 * only the shared trait helper (getSidebarData) and the injected
 * ResearchService (getResearcherByUserId), so the move is a verbatim lift.
 */
class ResearchAssessmentsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function assessments(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $culture = app()->getLocale();

        $assessments = DB::table('research_source_assessment as sa')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('sa.object_id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'sa.object_id', '=', 's.object_id')
            ->leftJoin('research_researcher as r', 'sa.researcher_id', '=', 'r.id')
            ->select('sa.*', 'ioi.title as object_title', 's.slug as object_slug',
                DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"))
            ->orderByDesc('sa.assessed_at')
            ->limit(100)
            ->get()->toArray();

        return view('research::research.assessments', array_merge(
            $this->getSidebarData('assessments'),
            compact('assessments')
        ));
    }
}
