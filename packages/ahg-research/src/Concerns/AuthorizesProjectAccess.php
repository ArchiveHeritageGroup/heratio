<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Concerns;

use AhgCore\Services\AclService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Project-membership authorization for project-scoped research controllers.
 *
 * The project sub-controllers (ClaimLedger, WritingStudio, PublicationStudio,
 * etc.) each resolve a project from a {projectId} URL segment. Resolving the
 * row is not the same as authorizing the caller: without a membership check any
 * researcher can read/mutate another researcher's private project data simply
 * by changing the id in the URL (cross-researcher IDOR).
 *
 * This trait carries the canonical gate, extracted verbatim from
 * ResearchProjectsController::loadProjectContext() (issue #1308). The project
 * owner is stored as a collaborator row (role='owner', status='accepted'), so a
 * single accepted-membership check covers owner + collaborators and excludes
 * pending invites. Administrators bypass. Non-members are refused with 403.
 */
trait AuthorizesProjectAccess
{
    /**
     * Abort with 403 unless the given researcher is an accepted collaborator on
     * (or the owner of) the project, or the current user is an administrator.
     *
     * Mirrors ResearchProjectsController::loadProjectContext() (#1308) exactly:
     * same table, same accepted-status predicate, same admin bypass.
     */
    protected function assertProjectMember(int $projectId, int $researcherId): void
    {
        $hasAccess = DB::table('research_project_collaborator')
            ->where('project_id', $projectId)
            ->where('researcher_id', $researcherId)
            ->where('status', 'accepted')
            ->exists();
        if (!$hasAccess && !AclService::isAdministrator(Auth::user())) {
            abort(403, 'You do not have access to this project.');
        }
    }

    /**
     * Convenience wrapper for controllers whose project-resolution helper does
     * not already carry the acting researcher. Resolves the researcher from the
     * authenticated user, then applies the same accepted-membership gate.
     * A user without a researcher profile is refused unless they are an admin.
     */
    protected function assertProjectAccess(int $projectId): void
    {
        $researcher = DB::table('research_researcher')->where('user_id', Auth::id())->first();
        if ($researcher) {
            $this->assertProjectMember($projectId, (int) $researcher->id);
            return;
        }
        if (!AclService::isAdministrator(Auth::user())) {
            abort(403, 'You do not have access to this project.');
        }
    }
}
