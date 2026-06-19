<?php

/**
 * RicDeprecationService - #1321 "deprecate, don't delete" register.
 *
 * The governance pin (docs/reference/ontology-governance-pin.md, section 2)
 * guarantees a stable IRI policy: a superseded entity is marked owl:deprecated
 * and (optionally) points at its replacement, rather than being deleted and its
 * IRI recycled. This register records that supersession so the serializer can
 * emit owl:deprecated / dcterms:isReplacedBy in every export.
 *
 * It is also the home for a destroyed/vanished place in the Lost Places POC
 * (#1323) - the node persists, flagged deprecated, never deleted.
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

namespace AhgRic\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RicDeprecationService
{
    private const TABLE = 'ric_deprecated_entity';

    /** Is this entity marked deprecated? */
    public function isDeprecated(string $entityType, int $entityId): bool
    {
        return $this->table()
            ?->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->exists() ?? false;
    }

    /**
     * Deprecation record for one entity, or null if live.
     *
     * @return object|null {reason, superseded_by_iri, deprecated_at}
     */
    public function info(string $entityType, int $entityId): ?object
    {
        return $this->table()
            ?->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();
    }

    /**
     * Mark an entity deprecated (idempotent upsert). Never deletes the entity.
     *
     * @param  string|null  $supersededByIri  IRI of the replacement entity, if any
     */
    public function markDeprecated(
        string $entityType,
        int $entityId,
        ?string $reason = null,
        ?string $supersededByIri = null,
        ?string $deprecatedBy = null
    ): bool {
        if (! $this->available()) {
            return false;
        }

        DB::table(self::TABLE)->updateOrInsert(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            [
                'reason'            => $reason,
                'superseded_by_iri' => $supersededByIri,
                'deprecated_by'     => $deprecatedBy,
                'deprecated_at'     => now(),
            ]
        );

        return true;
    }

    /** Lift a deprecation (e.g. a record reinstated). */
    public function reinstate(string $entityType, int $entityId): void
    {
        $this->table()
            ?->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->delete();
    }

    /** Every deprecation record (for the dataset descriptor / audit). */
    public function all(): array
    {
        return $this->table()?->orderBy('deprecated_at', 'desc')->get()->all() ?? [];
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function table(): ?\Illuminate\Database\Query\Builder
    {
        return $this->available() ? DB::table(self::TABLE) : null;
    }
}
