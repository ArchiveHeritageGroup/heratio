<?php

/**
 * AnalysisBridgeController - Controller for Heratio
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
use AhgResearch\Services\AnalysisBridgeService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AnalysisBridgeController - Research OS Stage 11 (heratio#1234).
 *
 * Registers the PROVENANCE of analysis results produced elsewhere (Jupyter, R,
 * QDA, stats) and links each to the project claim(s) it supports/weakens/
 * contextualises. It does NOT run analysis engines. Auth-gated like the rest of
 * the portal; every action resolves the researcher + project context defensively
 * and degrades to an empty state rather than 500.
 */
class AnalysisBridgeController extends Controller
{
    use LogsResearchActivity;

    protected AnalysisBridgeService $bridge;
    protected ResearchService $research;

    public function __construct()
    {
        $this->bridge = new AnalysisBridgeService();
        $this->research = new ResearchService();
    }

    /** Resolve [project, researcher] for a project id, mirroring loadProjectContext. */
    protected function context(int $projectId): array
    {
        $researcher = $this->research->getResearcherByUserId((int) Auth::id());
        if (! $researcher) {
            abort(403);
        }
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (! $project) {
            abort(404, 'Project not found');
        }
        return [$project, $researcher];
    }

    /** Build the shared sidebar payload (matches the rest of the research portal). */
    protected function sidebar(string $active = 'projects'): array
    {
        $unread = 0;
        try {
            $researcher = $this->research->getResearcherByUserId((int) Auth::id());
            if ($researcher) {
                $unread = (int) DB::table('research_notification')
                    ->where('researcher_id', $researcher->id)
                    ->where('is_read', 0)->count();
            }
        } catch (\Throwable $e) {
            // table may not exist yet
        }
        return ['sidebarActive' => $active, 'unreadNotifications' => $unread];
    }

    /** Shared dropdown payload for the register/detail views. */
    protected function lookups(): array
    {
        return [
            'resultTypes'        => AnalysisBridgeService::RESULT_TYPES,
            'resultTypeBadges'   => AnalysisBridgeService::RESULT_TYPE_BADGES,
            'relationships'      => AnalysisBridgeService::RELATIONSHIPS,
            'relationshipBadges' => AnalysisBridgeService::RELATIONSHIP_BADGES,
            'codeKinds'          => AnalysisBridgeService::CODE_KINDS,
        ];
    }

    /** Results register for a project. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $filters = [
            'result_type' => $request->input('type'),
            'search'      => $request->input('q'),
        ];

        $results    = $this->bridge->listResults($projectId, $filters);
        $typeCounts = $this->bridge->typeCounts($projectId);
        $codes      = $this->bridge->getCodes($projectId);

        return view('research::research.analysis-bridge.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'results', 'typeCounts', 'codes', 'filters'),
            $this->lookups()
        ));
    }

    /** Detail of one registered result: provenance + linked claims. */
    public function show(Request $request, int $projectId, int $resultId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $result = $this->bridge->getResult($projectId, $resultId);
        if (! $result) {
            return redirect()->route('research.analysis.index', $projectId)
                ->with('error', 'Result not found.');
        }

        $linkedClaims    = $this->bridge->getLinkedClaims($resultId);
        $availableClaims = $this->bridge->availableClaims($projectId);

        return view('research::research.analysis-bridge.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'result', 'linkedClaims', 'availableClaims'),
            $this->lookups()
        ));
    }

    /** Register a new external result with provenance + optional artifact. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $validated = $request->validate([
            'title'               => 'required|string|max:500',
            'result_type'         => 'nullable|string|max:40',
            'source_data_ref'     => 'nullable|string|max:1000',
            'source_data_version' => 'nullable|string|max:120',
            'method'              => 'nullable|string|max:5000',
            'code_ref'            => 'nullable|string|max:1000',
            'generated_at'        => 'nullable|string|max:40',
            'researcher_decision' => 'nullable|string|max:5000',
            'artifact'            => 'nullable|file|max:51200',
        ]);

        $artifact = $request->hasFile('artifact') ? $request->file('artifact') : null;

        $id = $this->bridge->createResult($projectId, (int) Auth::id(), $validated, $artifact);
        if (! $id) {
            return redirect()->route('research.analysis.index', $projectId)
                ->with('error', 'Could not register the result.');
        }
        $this->logResearchActivity('create', 'analysis_bridge', (int) $id, $validated['title'] ?? null, ['method' => 'AnalysisBridgeController@store'], $projectId);
        return redirect()->route('research.analysis.show', [$projectId, $id])
            ->with('success', 'Result registered with its provenance.');
    }

    /** Update a result's provenance metadata. */
    public function update(Request $request, int $projectId, int $resultId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'title'               => 'required|string|max:500',
            'result_type'         => 'nullable|string|max:40',
            'source_data_ref'     => 'nullable|string|max:1000',
            'source_data_version' => 'nullable|string|max:120',
            'method'              => 'nullable|string|max:5000',
            'code_ref'            => 'nullable|string|max:1000',
            'generated_at'        => 'nullable|string|max:40',
            'researcher_decision' => 'nullable|string|max:5000',
            'artifact'            => 'nullable|file|max:51200',
        ]);

        $artifact = $request->hasFile('artifact') ? $request->file('artifact') : null;

        $ok = $this->bridge->updateResult($projectId, $resultId, $validated, $artifact);
        if ($ok) {
            $this->logResearchActivity('update', 'analysis_bridge', $resultId, $validated['title'] ?? null, ['method' => 'AnalysisBridgeController@update'], $projectId);
        }
        return redirect()->route('research.analysis.show', [$projectId, $resultId])
            ->with($ok ? 'success' : 'error', $ok ? 'Result updated.' : 'Could not update the result.');
    }

    /** Delete a result. */
    public function destroy(Request $request, int $projectId, int $resultId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->bridge->deleteResult($projectId, $resultId);
        if ($ok) {
            $this->logResearchActivity('delete', 'analysis_bridge', $resultId, null, ['method' => 'AnalysisBridgeController@destroy'], $projectId);
        }
        return redirect()->route('research.analysis.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Result deleted.' : 'Could not delete the result.');
    }

    /** Download a result's artifact (traversal-guarded, project-scoped). */
    public function downloadArtifact(Request $request, int $projectId, int $resultId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $result = $this->bridge->getResult($projectId, $resultId);
        if (! $result) {
            abort(404);
        }
        $abs = $this->bridge->artifactAbsolutePath($result);
        if ($abs === null) {
            return redirect()->route('research.analysis.show', [$projectId, $resultId])
                ->with('error', 'Artifact not available.');
        }
        return response()->download($abs);
    }

    /** Link a result to a project claim with a relationship. */
    public function linkClaim(Request $request, int $projectId, int $resultId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'assertion_id' => 'required|integer',
            'relationship' => 'required|string|max:40',
            'note'         => 'nullable|string|max:2000',
        ]);

        $ok = $this->bridge->linkClaim(
            $projectId,
            $resultId,
            (int) $validated['assertion_id'],
            (string) $validated['relationship'],
            $validated['note'] ?? null
        );
        if ($ok) {
            $this->logResearchActivity('update', 'analysis_bridge', $resultId, null, ['method' => 'AnalysisBridgeController@linkClaim'], $projectId);
        }
        return redirect()->route('research.analysis.show', [$projectId, $resultId])
            ->with($ok ? 'success' : 'error', $ok ? 'Claim linked.' : 'Could not link the claim.');
    }

    /** Remove a result-claim link. */
    public function unlinkClaim(Request $request, int $projectId, int $resultId, int $linkId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->bridge->unlinkClaim($projectId, $resultId, $linkId);
        if ($ok) {
            $this->logResearchActivity('delete', 'analysis_bridge', $resultId, null, ['method' => 'AnalysisBridgeController@unlinkClaim'], $projectId);
        }
        return redirect()->route('research.analysis.show', [$projectId, $resultId])
            ->with($ok ? 'success' : 'error', $ok ? 'Link removed.' : 'Could not remove the link.');
    }

    /** Add a thematic-coding tag or memo (project-level). */
    public function addCode(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'kind'  => 'required|string|max:40',
            'label' => 'required|string|max:255',
            'body'  => 'nullable|string|max:20000',
        ]);

        $id = $this->bridge->addCode(
            $projectId,
            (int) Auth::id(),
            (string) $validated['kind'],
            (string) $validated['label'],
            $validated['body'] ?? null
        );
        if ($id) {
            $this->logResearchActivity('create', 'analysis_bridge', (int) $id, $validated['label'] ?? null, ['method' => 'AnalysisBridgeController@addCode'], $projectId);
        }
        return redirect()->route('research.analysis.index', $projectId)
            ->with($id ? 'success' : 'error', $id ? 'Saved.' : 'Could not save.');
    }

    /** Delete a thematic-coding tag or memo. */
    public function deleteCode(Request $request, int $projectId, int $codeId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->bridge->deleteCode($projectId, $codeId);
        if ($ok) {
            $this->logResearchActivity('delete', 'analysis_bridge', $codeId, null, ['method' => 'AnalysisBridgeController@deleteCode'], $projectId);
        }
        return redirect()->route('research.analysis.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Removed.' : 'Could not remove.');
    }
}
