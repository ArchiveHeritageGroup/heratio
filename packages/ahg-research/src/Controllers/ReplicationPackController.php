<?php

/**
 * ReplicationPackController - Heratio ahg-research
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

use AhgResearch\Services\ReplicationPackService;
use AhgResearch\Services\ResearchService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1238 - Research OS #16 (moonshot 22): Replication Pack.
 *
 * Per-project. The page (index) shows what a replication pack would contain and
 * offers one Build/Download button. The build action assembles the bundle
 * read-only from existing slices, writes a ZIP under config('heratio.storage_path'),
 * streams it back, and deletes the temp file afterwards. A small optional audit
 * line records who built each pack.
 *
 * Self-contained: project/researcher resolution is kept local (mirrors
 * ResearchController::loadProjectContext) so the slice does not depend on the
 * shared getSidebarData, which is NOT edited.
 */
class ReplicationPackController extends Controller
{
    public function __construct(
        private ReplicationPackService $packs,
        private ResearchService $research,
    ) {}

    /** Replication Pack page: what is included + a Build/Download button. */
    public function index(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        $summary = $this->packs->summary($projectId);
        $recent  = $this->packs->recentBuilds($projectId);

        return view('research::replication.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'summary', 'recent')
        ));
    }

    /**
     * Build the pack, stream the ZIP, and delete it on completion. On failure the
     * user is returned to the page with an error (never a 500).
     */
    public function build(int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project] = $this->projectContext($projectId);

        // #1325 - enforce the researcher's download quota before building.
        $rid = (int) ($this->packs->currentResearcherId() ?? 0);
        if ($rid > 0) {
            $check = app(\AhgResearch\Services\ResearchQuotaService::class)->checkDownload($rid, $projectId);
            if (! ($check['allowed'] ?? true)) {
                return redirect()->route('research.replication.index', $projectId)
                    ->with('error', $check['message'] ?? __('Download quota exceeded.'));
            }
        }

        $built = $this->packs->build($project);
        if ($built === null) {
            return redirect()->route('research.replication.index', $projectId)
                ->with('error', __('The replication pack could not be built. Check the storage configuration and try again.'));
        }

        [$absPath, $downloadName] = $built;

        // Best-effort audit line; never blocks the download.
        $this->packs->logBuild($projectId, $this->packs->currentResearcherId());
        // #1325 - count this download toward the researcher's quota.
        if ($rid > 0) {
            app(\AhgResearch\Services\ResearchQuotaService::class)
                ->logDownload($rid, $projectId, $projectId, 'Replication pack');
        }

        // Stream then delete the temp ZIP.
        return response()->download($absPath, $downloadName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    // ---------------------------------------------------------------------
    // Helpers (self-contained; getSidebarData is NOT used/edited)
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
        // #1395(G) — project private to owner / collaborators / admins (IDOR).
        $isAdmin = \AhgCore\Services\AclService::canAdmin((int) Auth::id());
        $isOwner = (int) ($project->owner_id ?? 0) === (int) ($researcher->id ?? 0);
        $isCollab = \Illuminate\Support\Facades\Schema::hasTable('research_project_collaborator')
            && DB::table('research_project_collaborator')
                ->where('project_id', $project->id)->where('researcher_id', $researcher->id)->exists();
        if (! ($isAdmin || $isOwner || $isCollab)) {
            abort(403, 'You do not have access to this project.');
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
