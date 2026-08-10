<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Services\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validate that a submitted DMP id belongs to the given project. Was
 * byte-identical in ResearchEthics/Funding/Output services.
 */
trait ValidatesDmpId
{
    private function validDmpId(mixed $value, int $projectId): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }
        try {
            if (! Schema::hasTable('research_dmp')) {
                return null;
            }
            $ok = DB::table('research_dmp')
                ->where('id', (int) $value)
                ->where('project_id', $projectId)
                ->exists();

            return $ok ? (int) $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
