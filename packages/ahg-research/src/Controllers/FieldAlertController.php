<?php

/**
 * FieldAlertController - Heratio ahg-research
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
use AhgResearch\Services\FieldAlertService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1235 - Research OS Stage 3: per-project Living Field Alerts.
 *
 * Surfaces RETRACTION / UPDATE / NEW-RELATED alerts for the works a project
 * cites, plus the watch list that drives them. The alerts are produced by the
 * scheduled ahg:research-field-alerts command; this controller is the read/UI
 * surface plus manual watch add/remove and mark-read.
 *
 * Auth-gated (routes carry the 'auth' middleware). Access mirrors the rest of
 * the research portal: owner + collaborators may view; owner, editor
 * collaborators and admins may manage. Every action is resilient - a missing
 * table or DB hiccup degrades to an empty state, never a 500.
 */
class FieldAlertController extends Controller
{
    public function __construct(
        private FieldAlertService $service,
        private ResearchService $research,
    ) {}

    // =========================================================================
    // Alerts panel
    // =========================================================================

    /** Per-project alerts panel (retractions prominent). */
    public function index(Request $request, int $projectId)
    {
        [$project, $researcher, $access] = $this->guardView($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $type = (string) $request->query('type', '');

        $alerts = $this->service->listAlerts($projectId, $type !== '' ? $type : null);
        $counts = $this->service->countsByType($projectId);
        $unread = $this->service->unreadCount($projectId);

        return view('research::research.field-alerts', array_merge(
            $this->sidebar(),
            [
                'project'    => $project,
                'researcher' => $researcher,
                'alerts'     => $alerts,
                'types'      => FieldAlertService::TYPES,
                'counts'     => $counts,
                'unread'     => $unread,
                'activeType' => $type,
                'canManage'  => $access['can_edit'],
            ]
        ));
    }

    /** Mark a single alert read, then back to the panel. */
    public function markRead(Request $request, int $projectId, int $id)
    {
        [$project] = $this->guardView($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $this->service->markRead($projectId, $id);

        return redirect()->route('research.alerts.index', $projectId);
    }

    /** Mark every alert read. */
    public function markAllRead(Request $request, int $projectId)
    {
        [$project] = $this->guardView($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $this->service->markAllRead($projectId);

        return redirect()->route('research.alerts.index', $projectId)
            ->with('success', __('All alerts marked read.'));
    }

    // =========================================================================
    // Watch list
    // =========================================================================

    /** The watch list: cited DOIs plus any manual watches. */
    public function watches(Request $request, int $projectId)
    {
        [$project, $researcher, $access] = $this->guardView($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        // Auto-seed from the bibliography (read-only source) so the list is
        // populated even before the first cron run.
        if ($access['can_edit']) {
            $this->service->seedWatchesFromBibliography($projectId);
        }

        $watches = $this->service->listWatches($projectId);

        return view('research::research.field-watch', array_merge(
            $this->sidebar(),
            [
                'project'    => $project,
                'researcher' => $researcher,
                'watches'    => $watches,
                'canManage'  => $access['can_edit'],
            ]
        ));
    }

    /** Manually add a watch (DOI and/or title). */
    public function addWatch(Request $request, int $projectId)
    {
        [$project, $researcher, $access] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $data = $request->validate([
            'doi'   => 'nullable|string|max:255',
            'title' => 'nullable|string|max:500',
        ]);

        $doi   = $data['doi'] ?? null;
        $title = $data['title'] ?? null;

        if ((trim((string) $doi) === '') && (trim((string) $title) === '')) {
            return redirect()->route('research.alerts.watches', $projectId)
                ->with('error', __('Enter a DOI or a title to watch.'));
        }

        $addedBy = trim(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) ?: ($researcher->email ?? null);

        $id = $this->service->addWatch($projectId, $doi, $title, $addedBy);

        if ($id === null) {
            return redirect()->route('research.alerts.watches', $projectId)
                ->with('error', __('Could not add the watch. Please try again.'));
        }

        return redirect()->route('research.alerts.watches', $projectId)
            ->with('success', __('Added to the watch list.'));
    }

    /** Remove a watch. */
    public function removeWatch(Request $request, int $projectId, int $id)
    {
        [$project] = $this->guardEdit($projectId);
        if ($project instanceof \Illuminate\Http\RedirectResponse) {
            return $project;
        }

        $this->service->removeWatch($projectId, $id);

        return redirect()->route('research.alerts.watches', $projectId)
            ->with('success', __('Removed from the watch list.'));
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
     * Decide what this researcher may do with this project's alerts.
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
            abort(403, 'You cannot manage this project\'s field alerts.');
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
