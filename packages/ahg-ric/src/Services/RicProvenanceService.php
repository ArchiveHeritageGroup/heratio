<?php

/**
 * RicProvenanceService - #1321 AI-assertion provenance register.
 *
 * The governance pin (section 6) requires that AI-asserted data is never passed
 * off as documented fact: every machine-inferred entity/edge must carry
 * provenance and be visibly distinguishable from asserted fact in export.
 *
 * This register records which RiC entities (or edges) were produced by an AI
 * inference - the model, a confidence, and the AhgInferenceReceipt id - so the
 * serializer can stamp PROV-O (`prov:wasGeneratedBy` a software agent) on them.
 * Entities NOT in this register are, by absence, asserted fact: that asymmetry
 * is the distinguishability guarantee.
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

class RicProvenanceService
{
    private const TABLE = 'ric_inferred_assertion';

    /** Provenance record for an entity, or null if it is asserted fact. */
    public function forEntity(string $entityType, int $entityId): ?object
    {
        return $this->table()
            ?->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id', 'desc')
            ->first();
    }

    public function isInferred(string $entityType, int $entityId): bool
    {
        return $this->table()
            ?->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->exists() ?? false;
    }

    /**
     * Record that an entity (or one of its edges) was AI-asserted (idempotent
     * per entity+predicate). Never alters the entity itself.
     */
    public function markInferred(
        string $entityType,
        int $entityId,
        string $model,
        ?float $confidence = null,
        ?string $predicate = null,
        ?string $receiptId = null,
        ?string $humanConfirmed = null
    ): bool {
        if (! $this->available()) {
            return false;
        }

        DB::table(self::TABLE)->updateOrInsert(
            ['entity_type' => $entityType, 'entity_id' => $entityId, 'predicate' => $predicate],
            [
                'model'           => $model,
                'confidence'      => $confidence,
                'receipt_id'      => $receiptId,
                'human_confirmed' => $humanConfirmed,
                'created_at'      => now(),
            ]
        );

        return true;
    }

    /** All AI-asserted entities (for the dataset descriptor / audit). */
    public function all(): array
    {
        return $this->table()?->orderBy('created_at', 'desc')->get()->all() ?? [];
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
