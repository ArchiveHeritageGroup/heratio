<?php

/**
 * ImpactTrackingController - Heratio ahg-research
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
use AhgResearch\Services\ImpactTrackingService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1241 - Research OS #19 (moonshot 25): Impact Tracking.
 *
 * Per-project Impact panel: the downstream citations, mentions and dataset
 * reuse of a project's PUBLISHED outputs. The signals are produced by the
 * scheduled ahg:research-impact-refresh command (which polls the PUBLIC OpenAlex
 * and Crossref Event Data APIs directly, never the AI gateway); this controller
 * is the read/UI surface plus a manual refresh trigger.
 *
 * Auth-gated (routes carry the 'auth' middleware). Access mirrors the rest of
 * the research portal: owner + collaborators may view; owner, editor
 * collaborators and admins may trigger a refresh. Every action is resilient - a
 * missing table or DB hiccup degrades to an empty state, never a 500.
 */
class ImpactTrackingController extends Controller
{
    public function __construct(
        private ImpactTrackingService $service,
        private ResearchService $research,
    ) {}

    // =========================================================================
    // Impact panel
    // =========================================================================

    /** Per-project Impact panel grouping signals by type with counts. */
    public function index(Request $request, int $projectId)
    {
        [$project, $researcher, $access] = $this->guardView($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $type = (string) $request->query('type', '');

        $outputs = $this->service->publishedOutputs($projectId);
        $signals = $this->service->listSignals($projectId, $type !== '' ? $type : null);
        $counts  = $this->service->countsByType($projectId);

        // Group the visible signals by type for the panel sections.
        $grouped = [];
        foreach ($signals as $sig) {
            $code = (string) ($sig->signal_type ?? 'other');
            $grouped[$code][] = $sig;
        }

        return view('research::research.impact-tracking', array_merge(
            $this->sidebar(),
            [
                'project'      => $project,
                'researcher'   => $researcher,
                'outputs'      => $outputs,
                'signals'      => $signals,
                'grouped'      => $grouped,
                'types'        => ImpactTrackingService::TYPES,
                'counts'       => $counts,
                'citationCount'=> $this->service->citationCount($projectId),
                'totalCount'   => $this->service->totalCount($projectId),
                'lastScanned'  => $this->service->lastScannedAt($projectId),
                'activeType'   => $type,
                'canManage'    => $access['can_edit'],
            ]
        ));
    }

    /**
     * Manual refresh: scan this project's published outputs now. Resilient - a
     * failed fetch yields no new signals and never errors the request.
     */
    public function refresh(Request $request, int $projectId)
    {
        [$project, , $access] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        if (! $this->service->hasPublishedOutputs($projectId)) {
            return redirect()->route('research.impact.index', $projectId)
                ->with('error', __('No published outputs with a DOI yet. Record a published submission in Publication Studio first.'));
        }

        try {
            $summary = $this->service->scanProject($projectId);
            return redirect()->route('research.impact.index', $projectId)
                ->with('success', __('Impact refresh complete. New signals found: :n', ['n' => (int) $summary['signals']]));
        } catch (\Throwable $e) {
            // Network or API hiccup must never 500 the user.
            return redirect()->route('research.impact.index', $projectId)
                ->with('error', __('Could not reach the bibliographic services just now. Please try again later.'));
        }
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
     * Decide what this researcher may do with this project's impact panel.
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
     * Gate for read actions. Returns [project, researcher, access] on success,
     * or [RedirectResponse, null, null] when the request must bounce.
     *
     * @return array{0:object|\Illuminate\Http\RedirectResponse,1:object|null,2:array|null}
     */
    private function guardView(int $projectId): array
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
        if (! $access['can_view']) {
            abort(403, 'You do not have access to this project.');
        }

        return [$project, $researcher, $access];
    }

    /**
     * Gate for mutating actions (manage = can_edit).
     *
     * @return array{0:object|\Illuminate\Http\RedirectResponse,1:object|null,2:array|null}
     */
    private function guardEdit(int $projectId): array
    {
        [$project, $researcher, $access] = $this->guardView($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return [$project, null, null];
        }

        if (! $access['can_edit']) {
            abort(403, 'You cannot refresh this project\'s impact tracking.');
        }

        return [$project, $researcher, $access];
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
