<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Authorize the caller and load a research project by direct query, returning
 * [$project, $researcher] (403/404 on failure, #1308-parity membership check).
 * Byte-identical `context()` in 6 research "studio" controllers (WritingStudio,
 * ClaimLedger, ArgumentBuilder, ContradictionEngine, ReviewStudio,
 * AiDisclosure). Requires the host to expose $this->research and
 * $this->assertProjectMember (all 6 do).
 *
 * See ResolvesProjectContext (projectContext()) for the findProject-based
 * variant; kept separate to preserve each one's exact behaviour.
 */
trait AuthorizesProjectContext
{
    protected function context(int $projectId): array
    {
        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            abort(403);
        }
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (! $project) {
            abort(404, 'Project not found');
        }
        // SECURITY (#1308-parity): authorize the caller against the resolved project.
        $this->assertProjectMember($projectId, (int) $researcher->id);

        return [$project, $researcher];
    }
}
