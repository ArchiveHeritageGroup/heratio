<?php

/**
 * AuthorityIdentifierService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use Illuminate\Support\Facades\DB;

/**
 * External Authority Identifier CRUD.
 *
 * Manages external identifiers (Wikidata, VIAF, ULAN, LCNAF, ISNI, ORCID, GND)
 * for actor records. Handles auto-URI construction and verification.
 */
class AuthorityIdentifierService
{
    /**
     * Known authority source URI patterns.
     */
    public const URI_PATTERNS = [
        'wikidata' => 'https://www.wikidata.org/wiki/%s',
        'viaf'     => 'https://viaf.org/viaf/%s',
        'ulan'     => 'https://vocab.getty.edu/ulan/%s',
        'lcnaf'    => 'https://id.loc.gov/authorities/names/%s',
        'isni'     => 'https://isni.org/isni/%s',
        'orcid'    => 'https://orcid.org/%s',
        'gnd'      => 'https://d-nb.info/gnd/%s',
    ];

    /**
     * Get all identifiers for an actor.
     */
    public function getIdentifiers(int $actorId): array
    {
        return DB::table('ahg_actor_identifier')
            ->where('actor_id', $actorId)
            ->orderBy('identifier_type')
            ->get()
            ->all();
    }

    /**
     * Get a single identifier by ID.
     */
    public function getById(int $id): ?object
    {
        return DB::table('ahg_actor_identifier')
            ->where('id', $id)
            ->first();
    }

    /**
     * Create or update an external identifier.
     */
    public function save(int $actorId, array $data): int
    {
        $type  = $data['identifier_type'] ?? '';
        $value = trim($data['identifier_value'] ?? '');

        // Auto-construct URI if not provided
        $uri = $data['uri'] ?? null;
        if (empty($uri) && isset(self::URI_PATTERNS[$type]) && !empty($value)) {
            $uri = sprintf(self::URI_PATTERNS[$type], $value);
        }

        $row = [
            'actor_id'         => $actorId,
            'identifier_type'  => $type,
            'identifier_value' => $value,
            'uri'              => $uri,
            'label'            => $data['label'] ?? null,
            'source'           => $data['source'] ?? 'manual',
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        // Upsert: unique on (actor_id, identifier_type)
        $existing = DB::table('ahg_actor_identifier')
            ->where('actor_id', $actorId)
            ->where('identifier_type', $type)
            ->first();

        if ($existing) {
            DB::table('ahg_actor_identifier')
                ->where('id', $existing->id)
                ->update($row);

            return (int) $existing->id;
        }

        $row['created_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('ahg_actor_identifier')->insertGetId($row);
    }

    /**
     * Delete an identifier.
     */
    public function delete(int $id): bool
    {
        return DB::table('ahg_actor_identifier')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Mark an identifier as verified.
     */
    public function verify(int $id, int $userId): bool
    {
        return DB::table('ahg_actor_identifier')
            ->where('id', $id)
            ->update([
                'is_verified' => 1,
                'verified_at' => date('Y-m-d H:i:s'),
                'verified_by' => $userId,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Check if an actor has any external identifiers.
     */
    public function hasIdentifiers(int $actorId): bool
    {
        return DB::table('ahg_actor_identifier')
            ->where('actor_id', $actorId)
            ->exists();
    }

    /**
     * Count identifiers by type across all actors.
     */
    public function getStats(): array
    {
        return DB::table('ahg_actor_identifier')
            ->select('identifier_type', DB::raw('COUNT(*) as count'))
            ->groupBy('identifier_type')
            ->orderBy('count', 'desc')
            ->get()
            ->all();
    }

    /**
     * Build the canonical URI for an identifier type + value.
     */
    public static function buildUri(string $type, string $value): ?string
    {
        if (isset(self::URI_PATTERNS[$type]) && !empty($value)) {
            return sprintf(self::URI_PATTERNS[$type], $value);
        }

        return null;
    }
}
