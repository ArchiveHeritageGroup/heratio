<?php

/**
 * MethodStudioController - Heratio ahg-research
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

use AhgResearch\Services\MethodStudioService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1231 - Research OS #9: Method Design Studio (ROS Stage 10).
 *
 * Browse discipline templates, start a per-project Method Protocol from a
 * chosen template (which pre-fills the guidance areas), edit/save the protocol
 * area by area, and view/print it. A clean reuse read endpoint (reuse) exposes
 * the protocol as structured data for downstream consumers (thesis methodology
 * chapter, grant, ethics application) - even though those consumers are future.
 *
 * Sidebar uses the shared 'projects' active key; getSidebarData is NOT edited.
 */
class MethodStudioController extends Controller
{
    public function __construct(
        private MethodStudioService $studio,
        private ResearchService $research,
    ) {}

    // ---------------------------------------------------------------------
    // Template browse (not project-scoped)
    // ---------------------------------------------------------------------

    /** Discipline template gallery. Empty-state safe. */
    public function templates(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $templates = $this->studio->listTemplates();
        $projectId = (int) $request->query('project', 0);
        $project = $projectId > 0 ? $this->findProject($projectId) : null;

        return view('research::method.templates', array_merge(
            $this->sidebar('projects'),
            ['templates' => $templates, 'project' => $project]
        ));
    }

    // ---------------------------------------------------------------------
    // Project-scoped protocol lifecycle
    // ---------------------------------------------------------------------

    /** List the Method Protocols on a project + a template picker entry point. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $protocols = $this->studio->listProtocols($projectId);
        $templates = $this->studio->listTemplates();
        $statusOptions = $this->studio->statusOptions();

        return view('research::method.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'protocols', 'templates', 'statusOptions')
        ));
    }

    /** Create a protocol from a chosen template, then open the editor. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $request->validate([
            'template_code' => 'required|string|max:64',
            'title'         => 'nullable|string|max:255',
        ]);

        if (! $this->studio->getTemplate($data['template_code'])) {
            return redirect()->route('research.method.index', $projectId)
                ->with('error', 'That method template is not available.');
        }

        $researcherId = $researcher ? (int) $researcher->id : null;
        $id = $this->studio->createFromTemplate(
            $projectId, $data['template_code'], $researcherId, $data['title'] ?? null
        );

        if (! $id) {
            return redirect()->route('research.method.index', $projectId)
                ->with('error', 'Could not start the method protocol. Please try again.');
        }

        return redirect()->route('research.method.edit', [$projectId, $id])
            ->with('success', 'Method protocol started from the template. Fill in each area below.');
    }

    /** Protocol editor - guidance area by area. */
    public function edit(int $projectId, int $protocolId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $protocol = $this->studio->getProtocol($protocolId, $projectId);
        if (! $protocol) {
            return redirect()->route('research.method.index', $projectId)
                ->with('error', 'Method protocol not found.');
        }

        $template = $this->studio->getTemplate($protocol['template_code']);
        $statusOptions = $this->studio->statusOptions();

        return view('research::method.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'protocol', 'template', 'statusOptions')
        ));
    }

    /** Persist editor changes (title, status, per-area answers). */
    public function update(Request $request, int $projectId, int $protocolId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $data = $request->validate([
            'title'    => 'nullable|string|max:255',
            'status'   => 'nullable|string|max:32',
            'fields'   => 'nullable|array',
            'fields.*' => 'nullable|string|max:20000',
        ]);

        $ok = $this->studio->saveProtocol(
            $protocolId,
            $projectId,
            $data['fields'] ?? [],
            $data['title'] ?? null,
            $data['status'] ?? null
        );

        if (! $ok) {
            return redirect()->route('research.method.index', $projectId)
                ->with('error', 'Could not save the method protocol.');
        }

        return redirect()->route('research.method.edit', [$projectId, $protocolId])
            ->with('success', 'Method protocol saved.');
    }

    /** Read-only summary / print view. */
    public function show(int $projectId, int $protocolId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $reuse = $this->studio->getProtocolForReuse($protocolId, $projectId);
        if (! $reuse) {
            return redirect()->route('research.method.index', $projectId)
                ->with('error', 'Method protocol not found.');
        }

        return view('research::method.show', array_merge(
            $this->sidebar('projects'),
            ['project' => $project, 'reuse' => $reuse]
        ));
    }

    /**
     * Reuse read endpoint: the protocol as clean structured JSON for downstream
     * consumers (thesis methodology chapter, grant, ethics application). Other
     * features call this once and reference the result. 404 (JSON) when missing.
     */
    public function reuse(int $projectId, int $protocolId)
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Authentication required.'], 401);
        }
        // Ownership / existence gate (aborts 403/404 cleanly).
        $this->projectContext($projectId);

        $reuse = $this->studio->getProtocolForReuse($protocolId, $projectId);
        if (! $reuse) {
            return response()->json(['ok' => false, 'error' => 'Method protocol not found.'], 404);
        }

        return response()->json(['ok' => true] + $reuse);
    }

    // ---------------------------------------------------------------------
    // Helpers (self-contained; getSidebarData on ResearchController is NOT used/edited)
    // ---------------------------------------------------------------------

    /**
     * Resolve project + current researcher. Mirrors ResearchController::loadProjectContext
     * but kept local so this slice is self-contained. Aborts 403 if the user is
     * not a registered researcher, 404 if the project is missing.
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
