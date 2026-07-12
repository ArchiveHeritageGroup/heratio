<?php

/**
 * ResearchMilestoneController - Heratio ahg-research
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

use AhgResearch\Concerns\AuthorizesProjectAccess;
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\ResearchMilestoneService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1222 - Research OS: Research Milestones & Deliverables tracker.
 *
 * List / create / edit / show / delete a project's planned milestones and
 * deliverables (title, type, due date, status, progress), show a per-project
 * summary (counts by status, overall progress, an overdue warning and the next
 * upcoming milestone), and export the project's plan as a machine-readable .json
 * document. Each list row carries the derived overdue / due-soon flags.
 *
 * Self-contained: resolves project + researcher locally, mirroring
 * ResearchTeamController, never edits getSidebarData. Every action is empty-state
 * safe and degrades cleanly when the slice is not installed. This is the PLAN
 * register; it is distinct from the Research Outputs register, which records what
 * has actually been produced.
 */
class ResearchMilestoneController extends Controller
{
    use LogsResearchActivity;
    use AuthorizesProjectAccess;

    public function __construct(
        private ResearchMilestoneService $milestones,
        private ResearchService $research,
    ) {}

    /** Milestones on a project + per-project summary. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $milestones    = $this->milestones->listMilestones($projectId);
        $summary       = $this->milestones->summary($projectId);
        $typeOptions   = $this->milestones->typeOptions();
        $statusOptions = $this->milestones->statusOptions();

        return view('research::milestones.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'milestones', 'summary', 'typeOptions', 'statusOptions')
        ));
    }

    /** New-milestone form. */
    public function create(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $milestone     = null;
        $typeOptions   = $this->milestones->typeOptions();
        $statusOptions = $this->milestones->statusOptions();

        return view('research::milestones.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'milestone', 'typeOptions', 'statusOptions')
        ));
    }

    /** Persist a new milestone. */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->projectContext($projectId);

        $data = $this->validateMilestone($request);

        $id = $this->milestones->createMilestone($projectId, $researcher ? (int) $researcher->id : null, $data);

        if (! $id) {
            return redirect()->route('research.milestones.index', $projectId)
                ->with('error', 'Could not add the milestone. Please try again.');
        }

        $this->logResearchActivity('create', 'milestone', (int) $id, $data['title'] ?? null, ['method' => 'ResearchMilestoneController@store'], $projectId);

        return redirect()->route('research.milestones.show', [$projectId, $id])
            ->with('success', 'Milestone saved.');
    }

    /** Edit-milestone form. */
    public function edit(int $projectId, int $milestoneId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $milestone = $this->milestones->getMilestone($milestoneId, $projectId);
        if (! $milestone) {
            return redirect()->route('research.milestones.index', $projectId)
                ->with('error', 'Milestone not found.');
        }

        $typeOptions   = $this->milestones->typeOptions();
        $statusOptions = $this->milestones->statusOptions();

        return view('research::milestones.edit', array_merge(
            $this->sidebar('projects'),
            compact('project', 'milestone', 'typeOptions', 'statusOptions')
        ));
    }

    /** Persist edits to a milestone. */
    public function update(Request $request, int $projectId, int $milestoneId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $existing = $this->milestones->getMilestone($milestoneId, $projectId);
        if (! $existing) {
            return redirect()->route('research.milestones.index', $projectId)
                ->with('error', 'Milestone not found.');
        }

        $data = $this->validateMilestone($request);

        $ok = $this->milestones->updateMilestone($milestoneId, $projectId, $data);

        if (! $ok) {
            return redirect()->route('research.milestones.index', $projectId)
                ->with('error', 'Could not save the milestone.');
        }

        $this->logResearchActivity('update', 'milestone', $milestoneId, $data['title'] ?? null, ['method' => 'ResearchMilestoneController@update'], $projectId);

        return redirect()->route('research.milestones.show', [$projectId, $milestoneId])
            ->with('success', 'Milestone saved.');
    }

    /** Read-only milestone detail. */
    public function show(int $projectId, int $milestoneId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $milestone = $this->milestones->getMilestone($milestoneId, $projectId);
        if (! $milestone) {
            return redirect()->route('research.milestones.index', $projectId)
                ->with('error', 'Milestone not found.');
        }

        $typeOptions   = $this->milestones->typeOptions();
        $statusOptions = $this->milestones->statusOptions();

        return view('research::milestones.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'milestone', 'typeOptions', 'statusOptions')
        ));
    }

    /** Delete a milestone. */
    public function destroy(int $projectId, int $milestoneId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->projectContext($projectId);

        $ok = $this->milestones->deleteMilestone($milestoneId, $projectId);

        if ($ok) {
            $this->logResearchActivity('delete', 'milestone', $milestoneId, null, ['method' => 'ResearchMilestoneController@destroy'], $projectId);
        }

        return redirect()->route('research.milestones.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Milestone removed.' : 'Could not remove the milestone.');
    }

    /**
     * Machine-readable export of the project's plan. Returns a downloadable JSON
     * document - each milestone with type, due / completed dates, status,
     * progress, deliverable and the derived overdue / due-soon flags, plus a
     * summary with counts by status, overall progress, the overdue count and the
     * next upcoming milestone.
     */
    public function exportJson(int $projectId)
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }
        [$project] = $this->projectContext($projectId);

        $milestones = $this->milestones->listMilestones($projectId);
        $payload    = $this->milestones->buildExport($milestones, $project);

        $filename = 'research-milestones-project-' . $projectId . '.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function validateMilestone(Request $request): array
    {
        return $request->validate([
            'title'          => 'required|string|max:512',
            'milestone_type' => 'required|string|max:32',
            'description'    => 'nullable|string|max:65000',
            'due_date'       => 'nullable|date',
            'completed_date' => 'nullable|date',
            'status'         => 'required|string|max:32',
            'progress_pct'   => 'nullable|integer|min:0|max:100',
            'deliverable'    => 'nullable|string|max:512',
        ]);
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
        // SECURITY (#1308-parity): authorize the caller against the resolved project.
        $this->assertProjectMember($projectId, (int) $researcher->id);

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
