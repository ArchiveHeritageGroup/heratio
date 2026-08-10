<?php

/**
 * DropdownOptions - shared ahg_dropdown / research_dmp option loaders.
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

namespace AhgCore\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared, table-backed option loaders. The body of `resolve()` was
 * copy-pasted verbatim into seven ahg-research services' private
 * dropdownOptions() method; `forProjectDmp()` into three of them as
 * dmpOptions(). They now delegate here (signatures unchanged, so call
 * sites are untouched). Deliberately NOT cached - two of the callers
 * (DmpService, ResearchOutputService) are container singletons, and a
 * per-instance cache on a singleton could serve stale vocabulary inside
 * a long-running queue worker.
 */
class DropdownOptions
{
    /**
     * ahg_dropdown rows for a taxonomy as code => label, in sort order.
     * Returns $fallback if the table is absent, the taxonomy is empty,
     * or anything throws.
     *
     * @param  array<string,string>  $fallback
     * @return array<string,string>
     */
    public static function resolve(string $taxonomy, array $fallback): array
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return $fallback;
            }
            $rows = DB::table('ahg_dropdown')
                ->where('taxonomy', $taxonomy)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get(['code', 'label']);

            if ($rows->isEmpty()) {
                return $fallback;
            }

            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r->code] = (string) $r->label;
            }

            return $out;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /**
     * research_dmp rows for a project as id => title, newest first.
     * Returns [] if the table is absent or anything throws.
     *
     * @return array<int,string>
     */
    public static function forProjectDmp(int $projectId): array
    {
        try {
            if (! Schema::hasTable('research_dmp')) {
                return [];
            }
            $rows = DB::table('research_dmp')
                ->where('project_id', $projectId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id', 'title']);

            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r->id] = (string) $r->title;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
