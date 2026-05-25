<?php

/**
 * MysqlActorAdapter - Service for Heratio
 *
 * Candidate adapter that searches the local MySQL `actor` table for
 * PERSON / ORG candidates. Joins actor_i18n on id and matches against
 * authorized_form_of_name with a LIKE %query% predicate.
 *
 * entity_type_id mapping (Qubit-style):
 *   132 = PERSON (corporate body individuals)
 *   131 = ORG (corporate body collectives)
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

class MysqlActorAdapter implements CandidateAdapterInterface
{
    private const ENTITY_TYPE_PERSON = 132;

    private const ENTITY_TYPE_ORG = 131;

    public function supports(string $entityType): bool
    {
        return in_array($entityType, ['PERSON', 'ORG'], true);
    }

    public function search(string $query, string $entityType, int $limit): array
    {
        if (! $this->supports($entityType)) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $entityTypeId = $entityType === 'PERSON'
            ? self::ENTITY_TYPE_PERSON
            : self::ENTITY_TYPE_ORG;

        // De-duplicate across cultures: prefer source_culture row first,
        // fall back to any non-empty authorized_form_of_name for the actor.
        $rows = DB::table('actor as a')
            ->join('actor_i18n as ai', 'ai.id', '=', 'a.id')
            ->where('a.entity_type_id', $entityTypeId)
            ->whereNotNull('ai.authorized_form_of_name')
            ->where('ai.authorized_form_of_name', 'like', '%'.$query.'%')
            ->orderByRaw('CASE WHEN ai.culture = a.source_culture THEN 0 ELSE 1 END')
            ->orderBy('a.id')
            ->limit($limit)
            ->get(['a.id as actor_id', 'ai.authorized_form_of_name']);

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->actor_id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $name = trim((string) $row->authorized_form_of_name);
            if ($name === '') {
                continue;
            }

            $out[] = [
                'source' => 'mysql_actor',
                'authority_id' => $id,
                'fuseki_uri' => null,
                'display_name' => $name,
            ];
        }

        return $out;
    }
}
