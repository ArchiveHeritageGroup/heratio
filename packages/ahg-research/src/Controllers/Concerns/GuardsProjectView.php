<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Guard a project-scoped view: return [$redirect, null, null] for
 * unauthenticated / unregistered users, else [$project, $researcher, $access]
 * (404/403 on missing/forbidden). Was byte-identical in FieldAlert /
 * ImpactTracking controllers. The using controller must expose
 * `$this->research`, `$this->loadProject()` and `$this->access()` (the
 * latter from ChecksProjectAccess).
 */
trait GuardsProjectView
{
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
}
