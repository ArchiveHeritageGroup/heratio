<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Authorize + load a research project for the current researcher, returning
 * [$project, $researcher] (403/404 on failure). Owner is an accepted
 * collaborator row; admins bypass (#1308). Was byte-identical in
 * ResearchCollaboration / ResearchProjectOutputs / ResearchProjects
 * controllers. The using controller must expose `$this->service` (the
 * research service with getResearcherByUserId()).
 */
trait LoadsProjectContext
{
    protected function loadProjectContext(int $id): array
    {
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $project = DB::table('research_project')->where('id', $id)->first();
        if (!$project) abort(404, 'Project not found');

        // SECURITY (#1308): authorize, do not just load. The owner is stored as a
        // collaborator row (role='owner', status='accepted'), so an accepted
        // membership check covers owner + collaborators and excludes pending
        // invites. Mirrors ProjectService::getProjects(). Admins bypass.
        $hasAccess = DB::table('research_project_collaborator')
            ->where('project_id', $id)
            ->where('researcher_id', $researcher->id)
            ->where('status', 'accepted')
            ->exists();
        if (!$hasAccess && !\AhgCore\Services\AclService::isAdministrator(Auth::user())) {
            abort(403, 'You do not have access to this project.');
        }

        return [$project, $researcher];
    }
}
