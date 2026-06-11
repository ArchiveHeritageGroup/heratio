<?php

/**
 * ResearchMemoryController - Heratio ahg-research
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
use AhgResearch\Services\ResearchMemoryService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1233 - Research OS Stage 16: Research Memory.
 *
 * Retains the researcher's intellectual memory after a project so the next one
 * starts smarter. Per-project, the researcher curates memory items (unresolved
 * questions, future articles, unused sources, abandoned hypotheses, reusable
 * datasets, collaboration / conference / grant leads) and can ACCEPT read-only
 * suggestions drawn from the Decision Log - accepting is the only write that a
 * suggestion produces. Cross-project, a Carry Forward aggregate lists every
 * open / carried-forward item across the researcher's projects so a new project
 * can start from them.
 *
 * Auth-gated. Access to a project's memory is restricted to the project owner,
 * its collaborators, and admins. Every action degrades to an empty state rather
 * than a 500.
 */
class ResearchMemoryController extends Controller
{
    public function __construct(
        private ResearchMemoryService $service,
        private ResearchService $research,
    ) {}

    // =========================================================================
    // Per-project memory
    // =========================================================================

    /** Per-project Memory view: curated items grouped by kind + suggestions. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return redirect()->route('researcher.register');
        }

        $project = $this->loadProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        $access = $this->access($project, $researcher);
        if (! $access['can_view']) {
            abort(403, 'You do not have access to this project.');
        }

        return view('research::research.research-memory', array_merge(
            $this->sidebar(),
            [
                'project'     => $project,
                'researcher'  => $researcher,
                'grouped'     => $this->service->groupedForProject($projectId),
                'kinds'       => $this->service->kinds(),
                'statuses'    => $this->service->statuses(),
                'suggestions' => $access['can_edit'] ? $this->service->suggestionsForProject($projectId) : [],
                'canEdit'     => $access['can_edit'],
            ]
        ));
    }

    /** Add-item form. */
    public function create(Request $request, int $projectId)
    {
        [$project, $researcher] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        return view('research::research.research-memory-form', array_merge(
            $this->sidebar(),
            [
                'project'    => $project,
                'researcher' => $researcher,
                'item'       => null,
                'kinds'      => $this->service->kinds(),
                'statuses'   => $this->service->statuses(),
                'isNew'      => true,
                'presetKind' => (string) $request->query('kind', ''),
            ]
        ));
    }

    /** Persist a new item. */
    public function store(Request $request, int $projectId)
    {
        [$project, $researcher] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $data = $this->validatePayload($request, $researcher);

        $id = $this->service->create((int) $researcher->id, $projectId, $data);

        if ($id === null) {
            return redirect()->route('research.memory.index', $projectId)
                ->with('error', __('Could not save the memory item. Please try again.'));
        }

        return redirect()->route('research.memory.index', $projectId)
            ->with('success', __('Memory item saved.'));
    }

    /** Edit-item form. */
    public function edit(Request $request, int $projectId, int $id)
    {
        [$project, $researcher] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $item = $this->service->findForResearcher((int) $researcher->id, $id);
        if (! $item || (int) ($item->project_id ?? 0) !== $projectId) {
            abort(404, 'Memory item not found');
        }

        return view('research::research.research-memory-form', array_merge(
            $this->sidebar(),
            [
                'project'    => $project,
                'researcher' => $researcher,
                'item'       => $item,
                'kinds'      => $this->service->kinds(),
                'statuses'   => $this->service->statuses(),
                'isNew'      => false,
                'presetKind' => '',
            ]
        ));
    }

    /** Persist an edit. */
    public function update(Request $request, int $projectId, int $id)
    {
        [$project, $researcher] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $item = $this->service->findForResearcher((int) $researcher->id, $id);
        if (! $item || (int) ($item->project_id ?? 0) !== $projectId) {
            abort(404, 'Memory item not found');
        }

        $data = $this->validatePayload($request, $researcher);

        $this->service->update((int) $researcher->id, $id, $data);

        return redirect()->route('research.memory.index', $projectId)
            ->with('success', __('Memory item updated.'));
    }

    /** Quick status change (carry forward / done / dropped / reopen). */
    public function status(Request $request, int $projectId, int $id)
    {
        [$project, $researcher] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $status = (string) $request->input('status', '');
        if (! in_array($status, $this->service->statusCodes(), true)) {
            return redirect()->route('research.memory.index', $projectId)
                ->with('error', __('Unknown status.'));
        }

        $item = $this->service->findForResearcher((int) $researcher->id, $id);
        if (! $item || (int) ($item->project_id ?? 0) !== $projectId) {
            abort(404, 'Memory item not found');
        }

        $this->service->setStatus((int) $researcher->id, $id, $status);

        return redirect()->route('research.memory.index', $projectId)
            ->with('success', __('Status updated.'));
    }

    /** Delete an item. */
    public function destroy(Request $request, int $projectId, int $id)
    {
        [$project, $researcher] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $this->service->delete((int) $researcher->id, $id);

        return redirect()->route('research.memory.index', $projectId)
            ->with('success', __('Memory item removed.'));
    }

    /**
     * Accept a read-only suggestion into the project's memory. This is the ONLY
     * write a suggestion produces - it materialises the suggested artefact (e.g.
     * a Decision Log unresolved question) as a curated memory item. The source
     * artefact is never modified.
     */
    public function accept(Request $request, int $projectId)
    {
        [$project, $researcher] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $signature = (string) $request->input('signature', '');
        if ($signature === '') {
            return redirect()->route('research.memory.index', $projectId)
                ->with('error', __('Nothing to accept.'));
        }

        $suggestion = $this->service->findSuggestion($projectId, $signature);
        if ($suggestion === null) {
            return redirect()->route('research.memory.index', $projectId)
                ->with('error', __('That suggestion is no longer available.'));
        }

        $createdBy = trim(
            ($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')
        ) ?: ($researcher->email ?? null);

        $id = $this->service->create((int) $researcher->id, $projectId, [
            'kind'       => $suggestion['kind'],
            'title'      => $suggestion['title'],
            'body'       => $suggestion['body'],
            'source_ref' => $suggestion['source_ref'],
            'status'     => 'open',
            'created_by' => $createdBy,
        ]);

        if ($id === null) {
            return redirect()->route('research.memory.index', $projectId)
                ->with('error', __('Could not accept the suggestion. Please try again.'));
        }

        return redirect()->route('research.memory.index', $projectId)
            ->with('success', __('Suggestion accepted into memory.'));
    }

    // =========================================================================
    // Cross-project carry forward
    // =========================================================================

    /** The researcher's cross-project carry-forward pool. */
    public function carryForward(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return redirect()->route('researcher.register');
        }

        return view('research::research.research-memory-carry-forward', array_merge(
            $this->sidebar(),
            [
                'researcher' => $researcher,
                'items'      => $this->service->carryForwardForResearcher((int) $researcher->id),
                'kinds'      => $this->service->kinds(),
                'statuses'   => $this->service->statuses(),
                'counts'     => $this->service->carryForwardCountsByKind((int) $researcher->id),
            ]
        ));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Load the project row, or null. Never throws. */
    private function loadProject(int $projectId): ?object
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

    /**
     * Decide what this researcher may do with this project's memory. Owner +
     * collaborators may view; owner, editor-collaborators and admins may edit.
     * Resilient: any failure yields no access.
     *
     * @return array{can_view:bool,can_edit:bool}
     */
    private function access(object $project, object $researcher): array
    {
        $isAdmin = Auth::check() && \AhgCore\Services\AclService::canAdmin(Auth::id());
        $isOwner = (int) ($project->owner_id ?? 0) === (int) ($researcher->id ?? 0);

        $isCollaborator = false;
        $isEditor       = false;
        try {
            if (Schema::hasTable('research_project_collaborator')) {
                $collab = DB::table('research_project_collaborator')
                    ->where('project_id', $project->id)
                    ->where('researcher_id', $researcher->id)
                    ->first();
                if ($collab) {
                    $isCollaborator = true;
                    $isEditor = in_array($collab->role ?? '', ['owner', 'editor', 'admin'], true);
                }
            }
        } catch (\Throwable $e) {
            // No collaborator access on error.
        }

        return [
            'can_view' => $isAdmin || $isOwner || $isCollaborator,
            'can_edit' => $isAdmin || $isOwner || $isEditor,
        ];
    }

    /**
     * Common gate for the mutating actions. Returns [project, researcher] on
     * success, or [RedirectResponse, null] when the request must bounce.
     *
     * @return array{0:object|\Illuminate\Http\RedirectResponse,1:object|null}
     */
    private function guardEdit(int $projectId): array
    {
        if (! Auth::check()) {
            return [redirect()->route('login'), null];
        }

        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return [redirect()->route('researcher.register'), null];
        }

        $project = $this->loadProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        $access = $this->access($project, $researcher);
        if (! $access['can_edit']) {
            abort(403, 'You cannot edit this project\'s memory.');
        }

        return [$project, $researcher];
    }

    /**
     * Validate + normalise the form payload, defaulting created_by to the acting
     * researcher's name.
     *
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request, object $researcher): array
    {
        $validated = $request->validate([
            'kind'       => 'required|string|max:64|in:' . implode(',', $this->service->kindCodes()),
            'title'      => 'required|string|max:500',
            'body'       => 'nullable|string|max:20000',
            'source_ref' => 'nullable|string|max:500',
            'status'     => 'nullable|string|max:32|in:' . implode(',', $this->service->statusCodes()),
        ]);

        $validated['created_by'] = trim(
            ($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')
        ) ?: ($researcher->email ?? null);

        return $validated;
    }

    /** Sidebar data without touching ResearchController::getSidebarData. */
    private function sidebar(): array
    {
        $unread = 0;
        try {
            $researcher = $this->research->getResearcherByUserId(Auth::id());
            if ($researcher && Schema::hasTable('research_notification')) {
                $unread = (int) DB::table('research_notification')
                    ->where('researcher_id', $researcher->id)
                    ->where('is_read', 0)
                    ->count();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'sidebarActive'       => 'projects',
            'unreadNotifications' => $unread,
        ];
    }
}
