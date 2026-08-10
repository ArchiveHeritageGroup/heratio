<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Authorize the caller and load a research project via the Schema-guarded
 * findProject() (ResolvesResearchProject), returning [$project, $researcher]
 * (403/404 on failure, #1308-parity membership check). Byte-identical
 * `projectContext()` in 8 research controllers (GrantEngine, ResearchFunding,
 * ResearchOutput, MethodStudio, ResearchEthics, ResearchMilestone,
 * ResearchTeam, Dmp). Requires the host to expose $this->research,
 * $this->findProject and $this->assertProjectMember (all 8 do).
 *
 * The near-identical direct-query variant is AuthorizesProjectContext
 * (context()); they are deliberately kept separate to preserve each one's
 * exact behaviour (findProject's Schema guard vs a direct query).
 */
trait ResolvesProjectContext
{
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
}
