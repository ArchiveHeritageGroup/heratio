<?php

/**
 * MysqlTermAdapter - Service for Heratio
 *
 * Candidate adapter that searches the local MySQL `term` table for
 * GPE / PLACE / LOC candidates. Targets the Places taxonomy
 * (taxonomy_id = 42 in the Qubit-style schema). Joins term_i18n on
 * id and matches against name with a LIKE %query% predicate.
 *
 * Returns at most $limit rows; the caller (CandidateGeneratorService)
 * scores them by name similarity and keeps the top-N.
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

namespace AhgAuthorityResolution\Services\Adapters;

use Illuminate\Support\Facades\DB;

class MysqlTermAdapter implements CandidateAdapterInterface
{
    private const PLACES_TAXONOMY_ID = 42;

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['GPE', 'PLACE', 'LOC'], true);
    }

    public function search(string $query, string $entityType, int $limit): array
    {
        if (!$this->supports($entityType)) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        // De-duplicate across cultures: prefer source_culture row first,
        // fall back to any non-empty name for the term.
        $rows = DB::table('term as t')
            ->join('term_i18n as ti', 'ti.id', '=', 't.id')
            ->where('t.taxonomy_id', self::PLACES_TAXONOMY_ID)
            ->whereNotNull('ti.name')
            ->where('ti.name', 'like', '%' . $query . '%')
            ->orderByRaw('CASE WHEN ti.culture = t.source_culture THEN 0 ELSE 1 END')
            ->orderBy('t.id')
            ->limit($limit)
            ->get(['t.id as term_id', 'ti.name']);

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->term_id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }

            $out[] = [
                'source' => 'mysql_term',
                'authority_id' => $id,
                'fuseki_uri' => null,
                'display_name' => $name,
            ];
        }

        return $out;
    }
}
