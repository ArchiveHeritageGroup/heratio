<?php

/**
 * DipMatcher - match a parsed DIP to a Heratio information_object.
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

namespace AhgArchivematica\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the `information_object.id` a parsed DIP belongs to, using the
 * configured strategy (config('archivematica.am_dip_match_strategy')):
 *
 *   - 'uuid'       : an Archivematica/AtoM UUID present in the METS (mets/@OBJID
 *                    or a UUID-shaped Dublin Core / PREMIS identifier) that has
 *                    already been recorded against an object in `am_link`
 *                    (transfer/sip/aip/dip uuid). This is the round-trip path
 *                    for records Heratio itself sent to Archivematica (D2 -> D1).
 *   - 'identifier' : the DIP's Dublin Core identifier matched against
 *                    `information_object.identifier`.
 *   - 'slug'       : the DIP's Dublin Core identifier matched against the
 *                    `slug` table.
 *
 * Returns the object_id, or null when nothing matches.
 */
class DipMatcher
{
    /**
     * @param array<string,mixed> $parsed a MetsParser::parse* result
     * @param string|null         $strategy override; defaults to config
     */
    public function match(array $parsed, ?string $strategy = null): ?int
    {
        $strategy = $strategy
            ?: (string) config('archivematica.am_dip_match_strategy', 'identifier');

        return match ($strategy) {
            'uuid'  => $this->matchByUuid($parsed),
            'slug'  => $this->matchBySlug($parsed),
            default => $this->matchByIdentifier($parsed),
        };
    }

    /**
     * Candidate UUIDs found in the parsed DIP: the METS OBJID plus any
     * UUID-shaped Dublin Core / PREMIS identifier. Pure - no DB - so it is
     * unit-testable.
     *
     * @param array<string,mixed> $parsed
     *
     * @return array<int,string> lower-cased, de-duplicated UUIDs
     */
    public function extractUuidCandidates(array $parsed): array
    {
        $candidates = [];

        if (! empty($parsed['objid'])) {
            $candidates[] = (string) $parsed['objid'];
        }

        $dc = $parsed['dublin_core'] ?? [];
        if (is_array($dc)) {
            foreach (['identifier', 'source', 'relation'] as $key) {
                if (! empty($dc[$key])) {
                    foreach (preg_split('/\s+/', (string) $dc[$key]) as $token) {
                        $candidates[] = $token;
                    }
                }
            }
        }

        foreach (($parsed['premis'] ?? []) as $p) {
            if (! empty($p['object_identifier'])) {
                $candidates[] = (string) $p['object_identifier'];
            }
        }

        $uuids = [];
        foreach ($candidates as $c) {
            $c = strtolower(trim($c));
            if ($c !== '' && $this->looksLikeUuid($c)) {
                $uuids[$c] = true;
            }
        }

        return array_keys($uuids);
    }

    /**
     * The DIP's descriptive identifier (Dublin Core dc:identifier). Pure.
     *
     * @param array<string,mixed> $parsed
     */
    public function extractIdentifier(array $parsed): ?string
    {
        $dc = $parsed['dublin_core'] ?? [];
        if (! is_array($dc) || empty($dc['identifier'])) {
            return null;
        }
        // A single value; if newline-joined, take the first line.
        $id = trim((string) $dc['identifier']);
        $id = preg_split('/\r?\n/', $id)[0] ?? $id;

        return $id !== '' ? trim($id) : null;
    }

    private function matchByUuid(array $parsed): ?int
    {
        $uuids = $this->extractUuidCandidates($parsed);
        if (empty($uuids) || ! Schema::hasTable('am_link')) {
            return null;
        }

        $row = DB::table('am_link')
            ->where(function ($q) use ($uuids) {
                $q->whereIn('aip_uuid', $uuids)
                  ->orWhereIn('sip_uuid', $uuids)
                  ->orWhereIn('transfer_uuid', $uuids)
                  ->orWhereIn('dip_uuid', $uuids);
            })
            ->whereNotNull('object_id')
            ->orderByDesc('id')
            ->first();

        return $row && $row->object_id ? (int) $row->object_id : null;
    }

    private function matchByIdentifier(array $parsed): ?int
    {
        $identifier = $this->extractIdentifier($parsed);
        if ($identifier === null || ! Schema::hasTable('information_object')) {
            return null;
        }

        $id = DB::table('information_object')
            ->where('identifier', $identifier)
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function matchBySlug(array $parsed): ?int
    {
        // The slug can live in dc:identifier (AtoM DIP uploads target a slug).
        $slug = $this->extractIdentifier($parsed);
        if ($slug === null || ! Schema::hasTable('slug')) {
            return null;
        }
        // Normalise a possible URL tail into the bare slug.
        if (str_contains($slug, '/')) {
            $slug = rtrim(substr($slug, strrpos($slug, '/') + 1));
        }

        $objectId = DB::table('slug')
            ->where('slug', $slug)
            ->value('object_id');

        return $objectId ? (int) $objectId : null;
    }

    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $value
        );
    }
}
