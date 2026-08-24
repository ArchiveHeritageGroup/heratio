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
use Illuminate\Support\Facades\Schema;

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

    /**
     * Source Assessment for one description - #1481.
     *
     * The view, the form and the table have existed all along; there was no
     * route and no action, so the "Source Assessment" link in the Research
     * Tools sidebar returned 404. GET renders, POST saves - one row per
     * (object, researcher), updated in place rather than appended, because an
     * assessment is a current judgement and not a log.
     */
    public function sourceAssessment(Request $request, int $objectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return redirect()->route('researcher.register');
        }

        $culture = app()->getLocale();

        $source = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n as ri', function ($j) use ($culture) {
                $j->on('ri.id', '=', 'io.repository_id')->where('ri.culture', '=', $culture);
            })
            ->where('io.id', $objectId)
            ->select('io.id', 'ioi.title', 'ri.authorized_form_of_name as repository')
            // Creation dates live on event/event_i18n, not on the description
            // row - selecting a dates_of_creation column that does not exist
            // would have made the Date line permanently 'N/A', which is the
            // #1478 defect wearing a different hat.
            ->selectSub(
                DB::table('event as ev')
                    ->leftJoin('event_i18n as evi', function ($j) use ($culture) {
                        $j->on('evi.id', '=', 'ev.id')->where('evi.culture', '=', $culture);
                    })
                    ->whereColumn('ev.object_id', 'io.id')
                    ->orderBy('ev.id')
                    ->limit(1)
                    ->selectRaw('COALESCE(evi.date, ev.start_date)'),
                'date'
            )
            ->first();
        abort_unless($source, 404);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'source_type'        => 'required|string|max:36',
                'completeness'       => 'nullable|string|max:58',
                'provenance'         => 'nullable|string|max:65535',
                'authenticity_notes' => 'nullable|string|max:65535',
                'reliability'        => 'nullable|integer|min:1|max:5',
                'bias'               => 'nullable|string|max:20',
            ]);

            DB::table('research_source_assessment')->updateOrInsert(
                ['object_id' => $objectId, 'researcher_id' => $researcher->id],
                $data + ['assessed_at' => now()]
            );

            return redirect()
                ->route('research.source-assessment', ['objectId' => $objectId])
                ->with('success', __('Assessment saved.'));
        }

        $assessment = DB::table('research_source_assessment')
            ->where('object_id', $objectId)
            ->where('researcher_id', $researcher->id)
            ->first();

        return view('research::research.source-assessment', array_merge(
            $this->getSidebarData('assessments'),
            compact('objectId', 'source', 'assessment')
        ));
    }

    /**
     * Trust Score for one description - #1481.
     *
     * View and table existed; no route, no action, so the sidebar link 404d.
     * The three dimensions are derived from what the platform actually knows -
     * a recorded source assessment, the description's own completeness, and
     * whether any quality metric has been computed - rather than from a stored
     * score, because nothing stores one.
     */
    public function trustScore(int $objectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $culture = app()->getLocale();

        $objectInfo = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $objectId)
            ->select('io.id', 'ioi.title', 's.slug')
            ->first();
        abort_unless($objectInfo, 404);

        $assessment = DB::table('research_source_assessment')->where('object_id', $objectId)->first();

        $qualityMetrics = Schema::hasTable('research_quality_metric')
            ? DB::table('research_quality_metric')
                ->where('object_id', $objectId)
                ->select('*', 'metric_type as metric_name')
                ->orderBy('metric_type')
                ->get()
            : collect();

        // Each dimension is out of the maximum the view prints beside it.
        $source = $assessment
            ? (int) round((((int) ($assessment->reliability ?? 0)) / 5) * 40)
            : 0;
        $completeness = match ($assessment->completeness ?? null) {
            'complete' => 30, 'partial' => 18, 'redacted' => 12,
            'fragment' => 8, 'missing_pages' => 8, default => 0,
        };
        $verification = $qualityMetrics->isEmpty() ? 0 : 30;

        $dimensions = compact('source', 'completeness', 'verification');
        $score = $source + $completeness + $verification;

        [$scoreLabel, $scoreColor] = match (true) {
            $score >= 80 => [__('High'), 'success'],
            $score >= 50 => [__('Moderate'), 'warning'],
            $score > 0   => [__('Low'), 'danger'],
            default      => [__('Not assessed'), 'secondary'],
        };

        return view('research::research.trust-score', array_merge(
            $this->getSidebarData('assessments'),
            compact('objectId', 'objectInfo', 'dimensions', 'score', 'scoreLabel', 'scoreColor', 'qualityMetrics')
        ));
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
