<?php

/**
 * SpectrumBarcodeService - Heratio
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

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-object barcode management for #123. Centralised in the spectrum
 * module to avoid touching every sector form blade - operators scan a
 * barcode and jump to the matching object via a single spectrum-side
 * lookup route.
 */
class SpectrumBarcodeService
{
    public const TABLE = 'spectrum_object_barcode';

    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function ($t) {
            $t->id();
            $t->integer('object_id');
            $t->string('barcode', 255)->unique();
            $t->string('barcode_type', 40)->default('code128');
            $t->string('label', 255)->nullable();
            $t->boolean('is_primary')->default(true);
            $t->timestamp('created_at')->useCurrent();
            $t->integer('created_by')->nullable();
            $t->index('object_id');
        });
    }

    public static function findObjectByBarcode(string $barcode): ?int
    {
        self::ensureTable();
        $id = DB::table(self::TABLE)
            ->where('barcode', trim($barcode))
            ->value('object_id');
        return $id ? (int) $id : null;
    }

    public static function listForObject(int $objectId): \Illuminate\Support\Collection
    {
        self::ensureTable();
        return DB::table(self::TABLE)
            ->where('object_id', $objectId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
    }

    /**
     * Assign a barcode to an object. Returns the new row id. Throws when
     * the barcode is already taken (UNIQUE constraint on the table) so
     * the caller can surface a friendly "barcode in use" error.
     */
    public static function assign(int $objectId, string $barcode, ?string $label = null, ?int $userId = null, string $type = 'code128', bool $primary = true): int
    {
        self::ensureTable();

        $barcode = trim($barcode);
        if ($barcode === '') {
            throw new \InvalidArgumentException('assign: barcode cannot be empty.');
        }

        $existing = DB::table(self::TABLE)->where('barcode', $barcode)->first();
        if ($existing) {
            throw new \DomainException(
                'Barcode "' . $barcode . '" is already assigned to object #' . $existing->object_id . '.'
            );
        }

        // If marking primary, demote any existing primary row for this object.
        if ($primary) {
            DB::table(self::TABLE)
                ->where('object_id', $objectId)
                ->where('is_primary', 1)
                ->update(['is_primary' => 0]);
        }

        $newId = (int) DB::table(self::TABLE)->insertGetId([
            'object_id' => $objectId,
            'barcode' => $barcode,
            'barcode_type' => $type,
            'label' => $label,
            'is_primary' => $primary ? 1 : 0,
            'created_at' => now(),
            'created_by' => $userId,
        ]);

        \AhgCore\Support\AuditLog::captureMutation($newId, self::TABLE, 'assign', [
            'data' => [
                'object_id' => $objectId,
                'barcode' => $barcode,
                'barcode_type' => $type,
                'is_primary' => $primary,
            ],
        ]);

        return $newId;
    }

    public static function unassign(int $barcodeRowId, ?int $userId = null): bool
    {
        self::ensureTable();
        $row = DB::table(self::TABLE)->where('id', $barcodeRowId)->first();
        if (!$row) return false;
        $deleted = DB::table(self::TABLE)->where('id', $barcodeRowId)->delete() > 0;
        if ($deleted) {
            \AhgCore\Support\AuditLog::captureMutation($barcodeRowId, self::TABLE, 'unassign', [
                'data' => ['object_id' => (int) $row->object_id, 'barcode' => $row->barcode, 'unassigned_by' => $userId],
            ]);
        }
        return $deleted;
    }
}
