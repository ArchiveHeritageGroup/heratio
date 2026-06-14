<?php

/**
 * ResearchStudioController - Controller for Heratio
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
use AhgResearch\Services\ResearchStudioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchStudioController - NotebookLM-style artefact generator (Studio).
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1253 / #1269). All five endpoints are auth-gated and operate on the
 * artefacts of a given research project via ResearchStudioService. No cross-calls
 * to other ResearchController methods existed - the methods used only the shared
 * trait helper (getSidebarData), the injected ResearchService
 * (getResearcherByUserId), and the resolved ResearchStudioService, so the move
 * is a verbatim lift.
 */
class ResearchStudioController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function studio(Request $request, int $projectId)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (!$project) abort(404, 'Project not found');

        $studio = app(\AhgResearch\Services\ResearchStudioService::class);

        $artefacts = $studio->listForProject($projectId);

        $availableSources = DB::table('research_collection as c')
            ->leftJoin('research_collection_item as ci', 'c.id', '=', 'ci.collection_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ci.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->where('c.project_id', $projectId)
            ->select('ci.object_id', 'ioi.title', 'c.name as collection_name')
            ->whereNotNull('ci.object_id')
            ->orderBy('c.name')
            ->orderBy('ioi.title')
            ->get()
            ->toArray();

        $supportedTypes = \AhgResearch\Services\ResearchStudioService::SUPPORTED_TYPES;

        return view('research::research.studio', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'researcher', 'artefacts', 'availableSources', 'supportedTypes')
        ));
    }

    public function studioGenerate(Request $request, int $projectId)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $validated = $request->validate([
            'output_type'          => 'required|string|in:briefing,study_guide,faq,timeline,diagram,video_script,spreadsheet,audio',
            'source_object_ids'    => 'required|array|min:1',
            'source_object_ids.*'  => 'integer|min:1',
            'title'                => 'nullable|string|max:500',
            'columns_request'      => 'nullable|string|max:500',
            'voice_id'             => 'nullable|string|max:120',
        ]);

        $studio = app(\AhgResearch\Services\ResearchStudioService::class);

        $options = array_filter([
            'title'           => $validated['title'] ?? null,
            'columns_request' => $validated['columns_request'] ?? null,
            'voice_id'        => $validated['voice_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $artefactId = $studio->generate(
            $projectId,
            $validated['source_object_ids'],
            $validated['output_type'],
            $options,
            (int) ($researcher->id ?? 0)
        );

        return redirect()->route('research.studioShow', ['projectId' => $projectId, 'artefactId' => $artefactId])
            ->with('success', 'Studio artefact generated.');
    }

    public function studioShow(Request $request, int $projectId, int $artefactId)
    {
        if (!Auth::check()) return redirect()->route('login');
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (!$project) abort(404);

        $artefact = app(\AhgResearch\Services\ResearchStudioService::class)->get($artefactId);
        if (!$artefact || (int) $artefact->project_id !== $projectId) abort(404);

        $citations = is_string($artefact->citations) ? json_decode($artefact->citations, true) : [];

        return view('research::research.studio-show', array_merge(
            $this->getSidebarData('projects'),
            compact('project', 'artefact', 'citations')
        ));
    }

    public function studioDownload(Request $request, int $projectId, int $artefactId)
    {
        if (!Auth::check()) return redirect()->route('login');
        $artefact = app(\AhgResearch\Services\ResearchStudioService::class)->get($artefactId);
        if (!$artefact || (int) $artefact->project_id !== $projectId) abort(404);

        if ($artefact->output_type === 'spreadsheet' && $artefact->xlsx_path && is_file($artefact->xlsx_path)) {
            return response()->download($artefact->xlsx_path, basename($artefact->xlsx_path));
        }

        if ($artefact->output_type === 'audio' && $artefact->audio_url) {
            return redirect()->away($artefact->audio_url);
        }

        abort(404, 'No downloadable file for this artefact.');
    }

    public function studioDelete(Request $request, int $projectId, int $artefactId)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $artefact = app(\AhgResearch\Services\ResearchStudioService::class)->get($artefactId);
        if (!$artefact || (int) $artefact->project_id !== $projectId) abort(404);

        app(\AhgResearch\Services\ResearchStudioService::class)->delete($artefactId);

        return redirect()->route('research.studio', $projectId)->with('success', 'Studio artefact removed.');
    }
}
