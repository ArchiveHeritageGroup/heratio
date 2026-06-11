<?php

/**
 * GrantEngineController - Heratio ahg-research
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

use AhgResearch\Services\GrantEngineService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1239 - Research OS #17 (moonshot 24): Grant Engine.
 *
 * Browse funder templates, start a per-project grant DRAFT from a chosen
 * template (sections pre-filled READ-ONLY from the project's own material), edit
 * sections, optionally ask the AI gateway to draft a section (labelled,
 * researcher-approval required, never auto-submitted), and track matching calls.
 *
 * Self-contained: resolves project + researcher locally, mirroring
 * ResearchController::loadProjectContext, and never edits getSidebarData. Every
 * action is empty-state safe and degrades cleanly when a slice is not installed.
 */
class GrantEngineController extends Controller
{
    public function __construct(
        private GrantEngineService $grant,
        private ResearchService $research,
    ) {}

    // ---------------------------------------------------------------------
    // Funder template browse (not project-scoped)
    // ---------------------------------------------------------------------

    /** Funder template gallery. ?project={id} threads a project for "use this". */
    public function templates(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $templates = $this->grant->listTemplates();
        $projectId = (int) $request->query('project', 0);
        $project   = $projectId > 0 ? $this->findProject($projectId) : null;

        return view('research::grant.templates', array_merge(
            $this->sidebar('projects'),
            ['templates' => $templates, 'project' => $project]
        ));
    }

    // ---------------------------------------------------------------------
    // Project-scoped draft lifecycle
    // ---------------------------------------------------------------------

    /** Grant drafts on a project + a template picker + tracked calls summary. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $drafts        = $this->grant->listDrafts($projectId);
        $templates     = $this->grant->listTemplates();
        $statusOptions = $this->grant->draftStatusOptions();
        $calls         = $this->grant->listCalls($researcher ? (int) $researcher->id : null, $projectId);

        return view('research::grant.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'drafts', 'templates', 'statusOptions', 'calls')
        ));
    }

    /** Start a draft from a chosen funder template, then open the editor. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $request->validate([
            'funder_template' => 'required|string|max:64',
            'title'           => 'nullable|string|max:255',
        ]);

        if (! $this->grant->getTemplate($data['funder_template'])) {
            return redirect()->route('research.grant.index', $projectId)
                ->with('error', 'That funder template is not available.');
        }

        $id = $this->grant->startDraft(
            $projectId,
            $data['funder_template'],
            $researcher ? (int) $researcher->id : null,
            $data['title'] ?? null
        );

        if (! $id) {
            return redirect()->route('research.grant.index', $projectId)
                ->with('error', 'Could not start the grant draft. Please try again.');
        }

        return redirect()->route('research.grant.edit', [$projectId, $id])
            ->with('success', 'Grant draft started. Sections were pre-filled from your project material - edit each one below.');
    }

    /** Section-by-section draft editor. */
    public function edit(int $projectId, int $draftId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $draft = $this->grant->getDraft($draftId, $projectId);
        if (! $draft) {
            return redirect()->route('research.grant.index', $projectId)
                ->with('error', 'Grant draft not found.');
        }

        $sections      = $this->grant->getSections($draftId);
        $template      = $this->grant->getTemplate($draft['funder_template']);
        $statusOptions = $this->grant->draftStatusOptions();

        return view('research::grant.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'draft', 'sections', 'template', 'statusOptions')
        ));
    }

    /** Persist editor changes (title, status, per-section bodies). */
    public function update(Request $request, int $projectId, int $draftId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $data = $request->validate([
            'title'      => 'nullable|string|max:255',
            'status'     => 'nullable|string|max:32',
            'sections'   => 'nullable|array',
            'sections.*' => 'nullable|string|max:50000',
        ]);

        $this->grant->saveDraftMeta($draftId, $projectId, $data['title'] ?? null, $data['status'] ?? null);

        $bodies = [];
        foreach (($data['sections'] ?? []) as $sectionId => $text) {
            $bodies[(int) $sectionId] = is_string($text) ? $text : '';
        }
        $ok = $this->grant->saveSections($draftId, $projectId, $bodies);

        if (! $ok) {
            return redirect()->route('research.grant.index', $projectId)
                ->with('error', 'Could not save the grant draft.');
        }

        return redirect()->route('research.grant.edit', [$projectId, $draftId])
            ->with('success', 'Grant draft saved.');
    }

    /** Read-only assembled draft (print / review view). */
    public function show(int $projectId, int $draftId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $draft = $this->grant->getDraft($draftId, $projectId);
        if (! $draft) {
            return redirect()->route('research.grant.index', $projectId)
                ->with('error', 'Grant draft not found.');
        }

        $sections = $this->grant->getSections($draftId);
        $template = $this->grant->getTemplate($draft['funder_template']);

        return view('research::grant.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'draft', 'sections', 'template')
        ));
    }

    /**
     * AJAX: ask the AI gateway to draft a section. Returns a SUGGESTION the
     * researcher reviews and saves themselves - nothing is written or submitted.
     * Routes only through the LlmService gateway abstraction.
     */
    public function aiDraft(Request $request, int $projectId, int $draftId)
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Authentication required.'], 401);
        }
        $this->projectContext($projectId);

        $draft = $this->grant->getDraft($draftId, $projectId);
        if (! $draft) {
            return response()->json(['ok' => false, 'error' => 'Grant draft not found.'], 404);
        }

        $data = $request->validate([
            'section_key'   => 'required|string|max:64',
            'section_label' => 'required|string|max:190',
            'current_text'  => 'nullable|string|max:50000',
        ]);

        $template   = $this->grant->getTemplate($draft['funder_template']);
        $funderName = (string) ($template['funder'] ?? '');

        $result = $this->grant->draftSection(
            $projectId,
            $data['section_key'],
            $data['section_label'],
            (string) ($data['current_text'] ?? ''),
            $funderName
        );

        if (! $result['ok']) {
            return response()->json([
                'ok'    => false,
                'error' => 'AI drafting is unavailable right now. You can keep writing the section by hand.',
            ], 200);
        }

        return response()->json([
            'ok'    => true,
            'text'  => $result['text'],
            'label' => $result['label'],
        ]);
    }

    // ---------------------------------------------------------------------
    // Tracked calls
    // ---------------------------------------------------------------------

    /** Tracked grant calls list (researcher-scoped, optional project filter). */
    public function calls(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $calls         = $this->grant->listCalls($researcher ? (int) $researcher->id : null, $projectId);
        $statusOptions = $this->grant->callStatusOptions();

        return view('research::grant.calls', array_merge(
            $this->sidebar('projects'),
            compact('project', 'calls', 'statusOptions')
        ));
    }

    /** Track a new call. */
    public function storeCall(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $request->validate([
            'funder'   => 'required|string|max:255',
            'title'    => 'required|string|max:255',
            'url'      => 'nullable|string|max:500',
            'deadline' => 'nullable|date',
            'status'   => 'nullable|string|max:32',
            'notes'    => 'nullable|string|max:5000',
        ]);

        $id = $this->grant->addCall(
            $researcher ? (int) $researcher->id : null,
            $projectId,
            $data
        );

        return redirect()->route('research.grant.calls', $projectId)
            ->with($id ? 'success' : 'error', $id ? 'Grant call tracked.' : 'Could not track the call.');
    }

    /** Update a tracked call (status / notes). */
    public function updateCall(Request $request, int $projectId, int $callId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $request->validate([
            'status' => 'nullable|string|max:32',
            'notes'  => 'nullable|string|max:5000',
        ]);

        $ok = $this->grant->updateCall($callId, $researcher ? (int) $researcher->id : null, $data);

        return redirect()->route('research.grant.calls', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Call updated.' : 'Could not update the call.');
    }

    /** Stop tracking a call. */
    public function destroyCall(int $projectId, int $callId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $ok = $this->grant->deleteCall($callId, $researcher ? (int) $researcher->id : null);

        return redirect()->route('research.grant.calls', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Call removed.' : 'Could not remove the call.');
    }

    // ---------------------------------------------------------------------
    // Helpers (self-contained; getSidebarData is NOT used or edited)
    // ---------------------------------------------------------------------

    /**
     * Resolve project + current researcher. Aborts 403 if the user is not a
     * registered researcher, 404 if the project is missing.
     *
     * @return array{0:?object,1:?object}
     */
    private function projectContext(int $projectId): array
    {
        $researcher = Auth::check() ? $this->research->getResearcherByUserId(Auth::id()) : null;
        if (! $researcher) {
            abort(403);
        }
        $project = $this->findProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        return [$project, $researcher];
    }

    /** Project row, or null. Schema-guarded so a partial install never 500s. */
    private function findProject(int $projectId): ?object
    {
        try {
            if (! Schema::hasTable('research_project')) {
                return null;
            }

            return DB::table('research_project')->where('id', $projectId)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Sidebar payload matching the package convention, without touching getSidebarData. */
    private function sidebar(string $active): array
    {
        $unread = 0;
        try {
            if (Auth::check() && Schema::hasTable('research_notification')) {
                $researcher = $this->research->getResearcherByUserId(Auth::id());
                if ($researcher) {
                    $unread = (int) DB::table('research_notification')
                        ->where('researcher_id', $researcher->id)
                        ->where('is_read', 0)
                        ->count();
                }
            }
        } catch (\Throwable $e) {
            // table may not exist yet - leave unread at 0
        }

        return ['sidebarActive' => $active, 'unreadNotifications' => $unread];
    }
}
