<?php

/**
 * SemanticSearchDropdownSeeder - Idempotent seed for semantic-search controlled vocabularies
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

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the semantic-search "Add Term" controlled vocabularies into
 * ahg_dropdown so site admins can manage them via the Dropdown Manager
 * instead of the terms living as hardcoded <option> lists (#1355).
 * SemanticSearchController reads these taxonomies back with a hardcoded
 * fallback, so a site that hasn't seeded yet keeps working.
 */
class SemanticSearchDropdownSeeder
{
    public static function seed(): void
    {
        if (!Schema::hasTable('ahg_dropdown')) {
            return;
        }

        $rows = [];
        $vocabularies = [
            'semantic_term_domain' => ['Semantic Term Domain', [
                'general'       => 'General',
                'archival'      => 'Archival',
                'museum'        => 'Museum',
                'library'       => 'Library',
                'south_african' => 'South African',
            ]],
            'semantic_term_relationship' => ['Semantic Term Relationship', [
                'exact'    => 'Exact (synonym)',
                'related'  => 'Related',
                'broader'  => 'Broader',
                'narrower' => 'Narrower',
            ]],
        ];

        // Per-taxonomy sentinel: a taxonomy that already exists (e.g. curated
        // by a site admin) is admin-owned - never re-merge the shipped terms
        // into it. Only missing taxonomies are seeded.
        $existing = DB::table('ahg_dropdown')
            ->whereIn('taxonomy', array_keys($vocabularies))
            ->distinct()
            ->pluck('taxonomy')
            ->all();

        foreach ($vocabularies as $taxonomy => [$taxonomyLabel, $terms]) {
            if (in_array($taxonomy, $existing, true)) {
                continue;
            }
            $sort = 10;
            $first = true;
            foreach ($terms as $code => $label) {
                $rows[] = [
                    'taxonomy'         => $taxonomy,
                    'taxonomy_label'   => $taxonomyLabel,
                    'taxonomy_section' => 'semantic_search',
                    'code'             => $code,
                    'label'            => $label,
                    'sort_order'       => $sort,
                    'is_default'       => $first ? 1 : 0,
                    'is_active'        => 1,
                ];
                $sort += 10;
                $first = false;
            }
        }

        if ($rows !== []) {
            DB::table('ahg_dropdown')->insertOrIgnore($rows);
        }
    }
}
