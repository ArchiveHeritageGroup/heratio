<?php

/**
 * ResearchCopilotController - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgResearch\Controllers;

use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\CollaborationService;
use AhgResearch\Services\ResearchCopilotService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * heratio#1198 - researcher copilot. Ask a research question and get an annotated source set
 * plus a grounded, cited synthesis drawn from the catalogue, which the researcher can review
 * and save (with its citations) into one of their research workspaces.
 */
class ResearchCopilotController extends Controller
{
    use LogsResearchActivity;

    public function __construct(
        private ResearchCopilotService $service,
        private ResearchService $research,
        private CollaborationService $collab,
    ) {}

    public function index()
    {
        return view('research::copilot', ['workspaces' => $this->myWorkspaces()]);
    }

    public function askAjax(Request $request)
    {
        $data = $request->validate(['question' => 'required|string|max:300']);

        return response()->json($this->service->ask($data['question']));
    }

    /** Save a reviewed answer + its citations into a workspace the researcher can edit. */
    public function saveAjax(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => 'required|integer|min:1',
            'project_id' => 'nullable|integer|min:1',
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:20000',
            'sources' => 'nullable|array|max:50',
            'sources.*.id' => 'nullable|integer',
            'sources.*.title' => 'nullable|string|max:300',
            'sources.*.slug' => 'nullable|string|max:255',
        ]);

        $researcherId = $this->researcherId();
        if (! $researcherId || ! $this->collab->canAccess((int) $data['workspace_id'], $researcherId, 'editor')) {
            return response()->json(['ok' => false, 'error' => 'You cannot save to that workspace.'], 403);
        }

        $result = $this->service->saveAnswer(
            (int) $data['workspace_id'], $researcherId,
            $data['question'], $data['answer'], $data['sources'] ?? [],
            isset($data['project_id']) ? (int) $data['project_id'] : null
        );

        $this->logResearchActivity(
            'update',
            'copilot',
            (int) $data['workspace_id'],
            $data['question'],
            ['method' => 'ResearchCopilotController@saveAjax'],
            isset($data['project_id']) ? (int) $data['project_id'] : null
        );

        return response()->json($result);
    }

    /** Saved answers for a workspace the researcher can see. */
    public function answersAjax(Request $request)
    {
        $workspaceId = (int) $request->query('workspace_id', 0);
        $researcherId = $this->researcherId();
        if (! $researcherId || ! $workspaceId || ! $this->collab->canAccess($workspaceId, $researcherId)) {
            return response()->json(['ok' => false, 'answers' => []], 403);
        }

        return response()->json(['ok' => true, 'answers' => $this->service->listAnswers($workspaceId)]);
    }

    /** The current user's researcher id, or null when they aren't a registered researcher. */
    private function researcherId(): ?int
    {
        $researcher = Auth::check() ? $this->research->getResearcherByUserId(Auth::id()) : null;

        return $researcher ? (int) $researcher->id : null;
    }

    /** Workspaces the researcher can save into (id + name for the picker). */
    private function myWorkspaces(): array
    {
        $researcherId = $this->researcherId();
        if (! $researcherId) {
            return [];
        }

        return array_map(
            fn ($w) => ['id' => (int) $w->id, 'name' => (string) $w->name, 'role' => $w->my_role ?? null],
            $this->collab->getWorkspaces($researcherId)
        );
    }
}
