<?php

/**
 * DecisionLogController - Heratio ahg-research
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
use AhgResearch\Services\DecisionLogService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1224 - Research OS Stage 9: the per-project Decision Log.
 *
 * The audit trail of THINKING for a project: scope changes, exclusions,
 * hypothesis revisions, method pivots, question reformulations and supervisor
 * instructions acted on - each with its reason. Distinct from the system
 * research_activity_log. Answers the examiner's "why did you exclude X" with
 * receipts and feeds the limitations section.
 *
 * Auth-gated (routes carry the 'auth' middleware). Access to a project's log is
 * restricted to the project owner, its collaborators, and admins. Every action
 * is resilient: a missing table or DB hiccup degrades to an empty state, never
 * a 500.
 */
class DecisionLogController extends Controller
{
    public function __construct(
        private DecisionLogService $service,
        private ResearchService $research,
    ) {}

    // =========================================================================
    // Timeline (index)
    // =========================================================================

    /** Per-project Decision Log timeline. */
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

        $type = (string) $request->query('type', '');

        $entries  = $this->service->listForProject($projectId, $type !== '' ? $type : null);
        $types    = $this->service->types();
        $counts   = $this->service->countsByType($projectId);

        return view('research::research.decision-log', array_merge(
            $this->sidebar(),
            [
                'project'    => $project,
                'researcher' => $researcher,
                'entries'    => $entries,
                'types'      => $types,
                'counts'     => $counts,
                'activeType' => $type,
                'canEdit'    => $access['can_edit'],
            ]
        ));
    }

    // =========================================================================
    // Create
    // =========================================================================

    /** Add-decision form. */
    public function create(Request $request, int $projectId)
    {
        [$project, $researcher, $access] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project; // a redirect/abort surrogate
        }

        return view('research::research.decision-log-form', array_merge(
            $this->sidebar(),
            [
                'project'    => $project,
                'researcher' => $researcher,
                'entry'      => null,
                'types'      => $this->service->types(),
                'isNew'      => true,
            ]
        ));
    }

    /** Persist a new decision. */
    public function store(Request $request, int $projectId)
    {
        [$project, $researcher, $access] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $data = $this->validatePayload($request);

        $id = $this->service->create($projectId, $data);

        if ($id === null) {
            return redirect()->route('research.decisions.index', $projectId)
                ->with('error', __('Could not record the decision. Please try again.'));
        }

        return redirect()->route('research.decisions.index', $projectId)
            ->with('success', __('Decision recorded.'));
    }

    // =========================================================================
    // Edit
    // =========================================================================

    /** Edit-decision form. */
    public function edit(Request $request, int $projectId, int $id)
    {
        [$project, $researcher, $access] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $entry = $this->service->find($projectId, $id);
        if (! $entry) {
            abort(404, 'Decision not found');
        }

        return view('research::research.decision-log-form', array_merge(
            $this->sidebar(),
            [
                'project'    => $project,
                'researcher' => $researcher,
                'entry'      => $entry,
                'types'      => $this->service->types(),
                'isNew'      => false,
            ]
        ));
    }

    /** Persist an edit. */
    public function update(Request $request, int $projectId, int $id)
    {
        [$project, $researcher, $access] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $entry = $this->service->find($projectId, $id);
        if (! $entry) {
            abort(404, 'Decision not found');
        }

        $data = $this->validatePayload($request);

        $this->service->update($projectId, $id, $data);

        return redirect()->route('research.decisions.index', $projectId)
            ->with('success', __('Decision updated.'));
    }

    // =========================================================================
    // Delete
    // =========================================================================

    /** Delete a decision (POST/DELETE). */
    public function destroy(Request $request, int $projectId, int $id)
    {
        [$project, $researcher, $access] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $this->service->delete($projectId, $id);

        return redirect()->route('research.decisions.index', $projectId)
            ->with('success', __('Decision removed.'));
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
     * Decide what this researcher may do with this project's Decision Log.
     * Owner + collaborators may view; owner, editor-collaborators and admins may
     * edit. Resilient: any failure yields no access.
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

        $canView = $isAdmin || $isOwner || $isCollaborator;
        $canEdit = $isAdmin || $isOwner || $isEditor;

        return ['can_view' => $canView, 'can_edit' => $canEdit];
    }

    /**
     * Common gate for the mutating actions. Returns [project, researcher, access]
     * on success, or [RedirectResponse, null, null] when the request must bounce.
     *
     * @return array{0:object|\Illuminate\Http\RedirectResponse,1:object|null,2:array|null}
     */
    private function guardEdit(int $projectId): array
    {
        if (! Auth::check()) {
            return [redirect()->route('login'), null, null];
        }

        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return [redirect()->route('researcher.register'), null, null];
        }

        $project = $this->loadProject($projectId);
        if (! $project) {
            abort(404, 'Project not found');
        }

        $access = $this->access($project, $researcher);
        if (! $access['can_edit']) {
            abort(403, 'You cannot edit this project\'s Decision Log.');
        }

        return [$project, $researcher, $access];
    }

    /**
     * Validate + normalise the form payload, including the decided_by default
     * (the acting researcher's name) and the decision_type whitelist.
     *
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'decision_type' => 'required|string|max:64|in:' . implode(',', $this->service->typeCodes()),
            'summary'       => 'required|string|max:500',
            'reason'        => 'nullable|string|max:10000',
            'related_ref'   => 'nullable|string|max:500',
            'decided_by'    => 'nullable|string|max:255',
            'decided_at'    => 'nullable|date',
        ]);

        if (empty($validated['decided_by'])) {
            $researcher = $this->research->getResearcherByUserId(Auth::id());
            if ($researcher) {
                $validated['decided_by'] = trim(
                    ($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')
                ) ?: ($researcher->email ?? null);
            }
        }

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
