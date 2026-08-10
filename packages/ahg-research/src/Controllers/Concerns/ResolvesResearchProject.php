<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Load a research_project row by id (fail-safe null). This 6-line body was
 * copy-pasted into 14 research controllers under two names - findProject (9)
 * and loadProject (5). Both names are provided here (findProject delegates)
 * so every existing call site keeps working.
 */
trait ResolvesResearchProject
{
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

    private function findProject(int $projectId): ?object
    {
        return $this->loadProject($projectId);
    }
}
