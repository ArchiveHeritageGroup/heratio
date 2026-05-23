<?php

/**
 * StrongroomService - Service for Heratio
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

namespace AhgStorageManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Strongroom CRUD + physical-object assignment + capacity helpers (heratio#144).
 *
 * Strongrooms are their own entity (`ahg_strongroom`) with their own slug,
 * NOT a physical_object subtype. A physical object can live in at most one
 * strongroom; the assignment + size used per object lives in the join table
 * `ahg_physical_object_storage`.
 */
class StrongroomService
{
    /**
     * Allowed capacity units. Label is shown in form selects + summaries.
     * Stored as a VARCHAR in the DB (no ENUM, per project convention) and
     * normalised back to this set on write.
     */
    public const CAPACITY_UNITS = [
        'linear_meters' => 'Linear meters',
        'shelves'       => 'Shelves',
        'boxes'         => 'Boxes',
        'cubic_meters'  => 'Cubic meters',
    ];

    // ---------- Read --------------------------------------------------

    public function getBySlug(string $slug): ?object
    {
        return DB::table('ahg_strongroom')->where('slug', $slug)->first();
    }

    public function getById(int $id): ?object
    {
        return DB::table('ahg_strongroom')->where('id', $id)->first();
    }

    /**
     * Browse-list rows with a utilisation summary per room. Each row carries
     * `used_units` (sum of size_units_used) and `occupant_count`.
     */
    public function browse(string $search = '', int $perPage = 25)
    {
        $query = DB::table('ahg_strongroom as sr')
            ->leftJoin('ahg_physical_object_storage as ps', 'ps.strongroom_id', '=', 'sr.id')
            ->select(
                'sr.id', 'sr.slug', 'sr.name', 'sr.location_description',
                'sr.capacity_value', 'sr.capacity_unit',
                DB::raw('COALESCE(SUM(ps.size_units_used), 0) AS used_units'),
                DB::raw('COUNT(DISTINCT ps.physical_object_id) AS occupant_count')
            )
            ->groupBy(
                'sr.id', 'sr.slug', 'sr.name', 'sr.location_description',
                'sr.capacity_value', 'sr.capacity_unit'
            );

        if ('' !== $search) {
            $query->where(function ($q) use ($search) {
                $q->where('sr.name', 'LIKE', "%{$search}%")
                    ->orWhere('sr.location_description', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('sr.name')->paginate($perPage);
    }

    /**
     * Physical objects assigned to a strongroom, with their slug, name and
     * size used. Ordered by name.
     */
    public function getOccupants(int $strongroomId)
    {
        return DB::table('ahg_physical_object_storage as ps')
            ->join('physical_object as po', 'po.id', '=', 'ps.physical_object_id')
            ->leftJoin('physical_object_i18n as po_i18n', function ($j) {
                $j->on('po_i18n.id', '=', 'po.id')->where('po_i18n.culture', 'en');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'po.id')
            ->where('ps.strongroom_id', $strongroomId)
            ->select(
                'po.id',
                'po_i18n.name',
                'po_i18n.location',
                'slug.slug',
                'ps.size_units_used'
            )
            ->orderBy('po_i18n.name')
            ->get();
    }

    /** Sum of size_units_used for a strongroom. */
    public function getUsedCapacity(int $strongroomId): float
    {
        return (float) DB::table('ahg_physical_object_storage')
            ->where('strongroom_id', $strongroomId)
            ->sum('size_units_used');
    }

    /**
     * Remaining capacity (capacity_value - used). NULL when the strongroom
     * has no capacity_value (i.e. no constraint set).
     */
    public function getRemainingCapacity(int $strongroomId): ?float
    {
        $room = $this->getById($strongroomId);
        if (null === $room || null === $room->capacity_value) {
            return null;
        }

        return (float) $room->capacity_value - $this->getUsedCapacity($strongroomId);
    }

    /**
     * Would assigning $newSize to this strongroom exceed its capacity?
     * Returns the projected over-usage (>0) or 0 when fine; NULL when the
     * strongroom has no capacity set (nothing to check against).
     *
     * Pass $excludePhysicalObjectId when re-assigning an object already
     * counted against the room, so its existing size isn't double-counted.
     */
    public function capacityOverflow(int $strongroomId, float $newSize, ?int $excludePhysicalObjectId = null): ?float
    {
        $room = $this->getById($strongroomId);
        if (null === $room || null === $room->capacity_value) {
            return null;
        }

        $q = DB::table('ahg_physical_object_storage')->where('strongroom_id', $strongroomId);
        if (null !== $excludePhysicalObjectId) {
            $q->where('physical_object_id', '!=', $excludePhysicalObjectId);
        }
        $usedByOthers = (float) $q->sum('size_units_used');
        $over = ($usedByOthers + $newSize) - (float) $room->capacity_value;

        return $over > 0 ? $over : 0.0;
    }

    /** Choices for <select> menus: [id => "Name (Unit label)"]. */
    public function dropdownChoices(): array
    {
        $rows = DB::table('ahg_strongroom')
            ->orderBy('name')
            ->get(['id', 'name', 'capacity_unit']);

        $out = [];
        foreach ($rows as $row) {
            $unitLabel = self::CAPACITY_UNITS[$row->capacity_unit] ?? $row->capacity_unit;
            $out[(int) $row->id] = $row->name . ' (' . $unitLabel . ')';
        }

        return $out;
    }

    // ---------- Write -------------------------------------------------

    /**
     * Create a strongroom and return its new id. Slug is derived from name
     * with collision-aware suffixing.
     */
    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ('' === $name) {
            throw new \InvalidArgumentException('Strongroom name is required');
        }

        $now = now();

        return DB::table('ahg_strongroom')->insertGetId([
            'slug'                 => $this->generateUniqueSlug($name),
            'name'                 => $name,
            'location_description' => $data['location_description'] ?? null,
            'capacity_value'       => $this->nullableDecimal($data['capacity_value'] ?? null),
            'capacity_unit'        => $this->normalizeCapacityUnit($data['capacity_unit'] ?? 'linear_meters'),
            'notes'                => $data['notes'] ?? null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
    }

    /** Update a strongroom in place. Slug stays sticky (no auto-rename on retitle). */
    public function update(int $id, array $data): void
    {
        $update = ['updated_at' => now()];

        if (isset($data['name']) && '' !== trim((string) $data['name'])) {
            $update['name'] = trim((string) $data['name']);
        }
        foreach (['location_description', 'notes'] as $key) {
            if (array_key_exists($key, $data)) {
                $update[$key] = $data[$key];
            }
        }
        if (array_key_exists('capacity_value', $data)) {
            $update['capacity_value'] = $this->nullableDecimal($data['capacity_value']);
        }
        if (array_key_exists('capacity_unit', $data)) {
            $update['capacity_unit'] = $this->normalizeCapacityUnit((string) $data['capacity_unit']);
        }

        DB::table('ahg_strongroom')->where('id', $id)->update($update);
    }

    /**
     * Delete a strongroom. Blocked when it still has occupants — the
     * operator must move them out first. (The DB FK is also ON DELETE
     * RESTRICT as a belt-and-braces backstop.)
     *
     * @throws \RuntimeException when the room still has occupants
     */
    public function delete(int $id): void
    {
        $occupants = (int) DB::table('ahg_physical_object_storage')
            ->where('strongroom_id', $id)
            ->count();

        if ($occupants > 0) {
            throw new \RuntimeException(sprintf(
                'Cannot delete strongroom: %d occupant(s) still assigned. Move them first.',
                $occupants
            ));
        }

        DB::table('ahg_strongroom')->where('id', $id)->delete();
    }

    // ---------- Physical-object assignment ----------------------------

    /**
     * Assign (or re-assign) a physical object to a strongroom, recording how
     * much of the room's capacity the object uses. Upsert by
     * physical_object_id (UNIQUE constraint).
     */
    public function assign(int $physicalObjectId, int $strongroomId, float $sizeUnitsUsed = 0.0): void
    {
        DB::table('ahg_physical_object_storage')->updateOrInsert(
            ['physical_object_id' => $physicalObjectId],
            [
                'strongroom_id'   => $strongroomId,
                'size_units_used' => max(0.0, $sizeUnitsUsed),
                'updated_at'      => now(),
                'created_at'      => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    /** Remove a physical object's strongroom assignment. No-op if not assigned. */
    public function unassign(int $physicalObjectId): void
    {
        DB::table('ahg_physical_object_storage')
            ->where('physical_object_id', $physicalObjectId)
            ->delete();
    }

    /**
     * The current strongroom assignment for a physical object, with a
     * pre-joined strongroom slug/name/unit for convenient display. NULL when
     * the object is not assigned to any strongroom.
     */
    public function getAssignment(int $physicalObjectId): ?object
    {
        return DB::table('ahg_physical_object_storage as ps')
            ->join('ahg_strongroom as sr', 'sr.id', '=', 'ps.strongroom_id')
            ->where('ps.physical_object_id', $physicalObjectId)
            ->select(
                'ps.id',
                'ps.physical_object_id',
                'ps.strongroom_id',
                'ps.size_units_used',
                'sr.slug as strongroom_slug',
                'sr.name as strongroom_name',
                'sr.capacity_unit'
            )
            ->first();
    }

    // ---------- Helpers -----------------------------------------------

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ('' === $base) {
            $base = 'strongroom';
        }
        $slug = $base;
        $n = 2;
        while (DB::table('ahg_strongroom')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n;
            ++$n;
        }

        return $slug;
    }

    private function normalizeCapacityUnit(string $unit): string
    {
        return isset(self::CAPACITY_UNITS[$unit]) ? $unit : 'linear_meters';
    }

    private function nullableDecimal($value): ?float
    {
        if (null === $value || '' === $value || false === $value) {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $v = (float) $value;

        return $v < 0 ? null : $v;
    }
}
