<?php

/**
 * QuestionBuilderController - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 *
 * heratio#1226 - Research OS #4: Question Builder (ROS Stage 2, epic #1222).
 *
 * Per-project Research Design Brief. Refines the research question into a
 * structured, VERSIONED brief before deep source collection. Every save
 * appends a new immutable version that retains the reason for the change.
 */

namespace AhgResearch\Controllers;

use AhgResearch\Concerns\AuthorizesProjectAccess;
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\QuestionBuilderService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuestionBuilderController extends Controller
{
    use LogsResearchActivity;
    use AuthorizesProjectAccess;

    public function __construct(
        private QuestionBuilderService $service,
        private ResearchService $research,
    ) {}

    /**
     * Resolve the project for the current user, or redirect/abort. Returns the
     * project row. Access is allowed to the owner and any accepted collaborator.
     */
    private function resolveProject(int $projectId): ?object
    {
        if (! Auth::check()) {
            return null;
        }

        $project = null;
        try {
            $project = DB::table('research_project')->where('id', $projectId)->first();
        } catch (\Throwable $e) {
            $project = null;
        }

        // SECURITY (#1308-parity): authorize the caller against the resolved project.
        if ($project) {
            $this->assertProjectAccess($projectId);
        }

        return $project ?: null;
    }

    /** Show the builder form + diagnosis for a project. */
    public function builder(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $project = $this->resolveProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        $ready    = $this->service->isReady();
        $brief    = $this->service->getBrief($projectId);
        $current  = $this->service->getCurrentVersion($projectId);
        $versions = $this->service->getVersions($projectId);

        // Build the form payload from the current version (or empty defaults).
        $values = [];
        foreach (QuestionBuilderService::FIELDS as $f) {
            $values[$f] = $current->$f ?? '';
        }

        // Diagnosis runs on the current saved state (empty when no version yet).
        $diagnosis   = $current ? $this->service->diagnose((array) $current) : [];
        $aiAvailable = $this->service->aiAvailable();

        return view('research::question-builder.builder', [
            'project'      => $project,
            'ready'        => $ready,
            'brief'        => $brief,
            'current'      => $current,
            'versions'     => $versions,
            'values'       => $values,
            'fields'       => QuestionBuilderService::FIELDS,
            'diagnosis'    => $diagnosis,
            'aiAvailable'  => $aiAvailable,
        ]);
    }

    /** Save the brief: appends a new version with a change reason. */
    public function save(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $project = $this->resolveProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        $validated = $request->validate([
            'broad_topic'         => 'nullable|string|max:2000',
            'problem_statement'   => 'nullable|string|max:5000',
            'research_gap'        => 'nullable|string|max:5000',
            'primary_question'    => 'nullable|string|max:2000',
            'secondary_questions' => 'nullable|string|max:5000',
            'hypothesis'          => 'nullable|string|max:3000',
            'scope_boundaries'    => 'nullable|string|max:3000',
            'key_definitions'     => 'nullable|string|max:5000',
            'assumptions'         => 'nullable|string|max:3000',
            'bias_risks'          => 'nullable|string|max:3000',
            'status'              => 'nullable|string|max:50',
            'change_reason'       => 'nullable|string|max:500',
        ]);

        $result = $this->service->saveVersion(
            $projectId,
            $validated,
            $validated['change_reason'] ?? null,
            Auth::id()
        );

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['error'] ?? 'Could not save the brief.');
        }

        $this->logResearchActivity(
            'update',
            'question',
            null,
            $validated['primary_question'] ?? null,
            ['method' => 'QuestionBuilderController@save', 'version_no' => $result['version_no'] ?? null],
            $projectId
        );

        return redirect()
            ->route('research.question.builder', $projectId)
            ->with('success', 'Saved as version ' . $result['version_no'] . '.');
    }

    /** Version history for the project's brief. */
    public function history(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $project = $this->resolveProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        $brief    = $this->service->getBrief($projectId);
        $versions = $this->service->getVersions($projectId);

        return view('research::question-builder.history', [
            'project'  => $project,
            'brief'    => $brief,
            'versions' => $versions,
            'fields'   => QuestionBuilderService::FIELDS,
        ]);
    }

    /**
     * AJAX: run the diagnosis against the values currently in the form (before
     * saving), optionally including the AI-assisted note. Heuristic flags
     * always returned; AI note only when available + requested. JSON.
     */
    public function diagnose(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false], 401);
        }

        $project = $this->resolveProject($projectId);
        if (! $project) {
            return response()->json(['ok' => false], 404);
        }

        $payload = [];
        foreach (QuestionBuilderService::FIELDS as $f) {
            $payload[$f] = (string) $request->input($f, '');
        }

        $flags  = $this->service->diagnose($payload);
        $useAi  = filter_var($request->input('use_ai', false), FILTER_VALIDATE_BOOLEAN);
        $aiNote = ($useAi && $this->service->aiAvailable()) ? $this->service->aiDiagnosis($payload, $projectId) : null;

        return response()->json([
            'ok'           => true,
            'flags'        => $flags,
            'ai_note'      => $aiNote,
            'ai_available' => $this->service->aiAvailable(),
        ]);
    }
}
