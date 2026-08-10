<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fetch the research_dmp row referenced by $record['dmp_id'] for a project.
 * Was byte-identical in ResearchEthics/Funding controllers.
 */
trait ResolvesDmpRecord
{
    private function resolveDmp(array $record, int $projectId): ?object
    {
        $dmpId = $record['dmp_id'] ?? null;
        if (! $dmpId) {
            return null;
        }
        try {
            if (! Schema::hasTable('research_dmp')) {
                return null;
            }

            return DB::table('research_dmp')
                ->where('id', (int) $dmpId)
                ->where('project_id', $projectId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
