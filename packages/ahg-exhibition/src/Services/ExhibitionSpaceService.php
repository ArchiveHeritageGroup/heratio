<?php

/**
 * ExhibitionSpaceService - heratio#146 — front-of-house space allocation.
 *
 * Mirrors StrongroomService but adds date-bounded placements: capacity
 * conflicts only matter when date ranges overlap. Optional FK to a curated
 * exhibition row lets placements tie into an exhibition's lifecycle.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgExhibition\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExhibitionSpaceService
{
    public const SPACE_TYPES = [
        'gallery'      => 'Gallery',
        'hall'         => 'Hall',
        'display_case' => 'Display case',
        'plinth'       => 'Plinth',
        'vitrine'      => 'Vitrine',
    ];

    public const CAPACITY_UNITS = [
        'linear_wall_meters' => 'Linear wall metres',
        'display_cases'      => 'Display cases',
        'plinths'            => 'Plinths',
        'square_meters'      => 'Square metres',
    ];

    // -------- Read --------

    public function getBySlug(string $slug): ?object
    {
        return DB::table('ahg_exhibition_space')->where('slug', $slug)->first();
    }

    public function getById(int $id): ?object
    {
        return DB::table('ahg_exhibition_space')->where('id', $id)->first();
    }

    /**
     * Paginated browse with current-utilisation summary (placements active TODAY).
     */
    public function browse(string $search = '', int $perPage = 25): LengthAwarePaginator
    {
        $today = date('Y-m-d');
        $query = DB::table('ahg_exhibition_space as sp')
            ->leftJoin('ahg_exhibition_placement as ep', function ($j) use ($today) {
                $j->on('ep.exhibition_space_id', '=', 'sp.id')
                    ->where(function ($q) use ($today) {
                        $q->whereNull('ep.starts_at')->orWhere('ep.starts_at', '<=', $today);
                    })
                    ->where(function ($q) use ($today) {
                        $q->whereNull('ep.ends_at')->orWhere('ep.ends_at', '>=', $today);
                    });
            })
            ->select(
                'sp.id', 'sp.slug', 'sp.name', 'sp.space_type', 'sp.building', 'sp.floor',
                'sp.capacity_value', 'sp.capacity_unit',
                DB::raw('COALESCE(SUM(ep.size_units_used), 0) AS used_units_today'),
                DB::raw('COUNT(DISTINCT ep.information_object_id) AS current_placements')
            )
            ->groupBy('sp.id', 'sp.slug', 'sp.name', 'sp.space_type', 'sp.building', 'sp.floor', 'sp.capacity_value', 'sp.capacity_unit');

        if ('' !== $search) {
            $query->where(function ($q) use ($search) {
                $q->where('sp.name', 'like', '%'.$search.'%')
                  ->orWhere('sp.building', 'like', '%'.$search.'%');
            });
        }

        return $query->orderBy('sp.name')->paginate($perPage);
    }

    public function dropdownChoices(): array
    {
        $rows = DB::table('ahg_exhibition_space')->orderBy('name')->get(['id', 'name', 'capacity_unit']);
        $out = [];
        foreach ($rows as $r) {
            $unit = self::CAPACITY_UNITS[$r->capacity_unit] ?? $r->capacity_unit;
            $out[(int) $r->id] = $r->name.' ('.$unit.')';
        }
        return $out;
    }

    /**
     * All placements for a space, with information object titles where available.
     */
    public function getPlacements(int $exhibitionSpaceId)
    {
        return DB::table('ahg_exhibition_placement as ep')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->where('ep.exhibition_space_id', $exhibitionSpaceId)
            ->orderBy('ep.starts_at')
            ->select(
                'ep.id', 'ep.information_object_id', 'ep.exhibition_id',
                'ep.size_units_used', 'ep.starts_at', 'ep.ends_at', 'ep.notes',
                'ioi.title as information_object_title'
            )
            ->get();
    }

    /**
     * Total units used during a date range (defaults to today).
     */
    public function getUsedCapacity(int $exhibitionSpaceId, ?string $startsAt = null, ?string $endsAt = null): float
    {
        $startsAt = $startsAt ?? date('Y-m-d');
        $endsAt = $endsAt ?? $startsAt;

        $q = DB::table('ahg_exhibition_placement')
            ->where('exhibition_space_id', $exhibitionSpaceId);
        $this->applyDateOverlap($q, $startsAt, $endsAt);
        return (float) ($q->sum('size_units_used') ?? 0);
    }

    public function getRemainingCapacity(int $exhibitionSpaceId, ?string $startsAt = null, ?string $endsAt = null): ?float
    {
        $space = $this->getById($exhibitionSpaceId);
        if (!$space || $space->capacity_value === null) {
            return null;
        }
        return max(0, ((float) $space->capacity_value) - $this->getUsedCapacity($exhibitionSpaceId, $startsAt, $endsAt));
    }

    /**
     * Date-overlap aware capacity check. Returns the amount by which the proposed
     * placement would exceed capacity for any day in the range, or 0 if fits, or
     * null when the space has no capacity set.
     */
    public function capacityOverflow(int $exhibitionSpaceId, float $newSize, string $startsAt, string $endsAt, ?int $excludePlacementId = null): ?float
    {
        $space = $this->getById($exhibitionSpaceId);
        if (!$space || $space->capacity_value === null) {
            return null;
        }

        $q = DB::table('ahg_exhibition_placement')
            ->where('exhibition_space_id', $exhibitionSpaceId);
        if ($excludePlacementId !== null) {
            $q->where('id', '!=', $excludePlacementId);
        }
        $this->applyDateOverlap($q, $startsAt, $endsAt);
        $used = (float) ($q->sum('size_units_used') ?? 0);

        $overflow = ($used + $newSize) - (float) $space->capacity_value;
        return $overflow > 0 ? $overflow : 0.0;
    }

    private function applyDateOverlap($query, string $startsAt, string $endsAt): void
    {
        // Existing placement (a..b) overlaps (startsAt..endsAt) when a <= endsAt AND b >= startsAt
        // null starts_at => unbounded past; null ends_at => unbounded future
        $query
            ->where(function ($q) use ($endsAt) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $endsAt);
            })
            ->where(function ($q) use ($startsAt) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $startsAt);
            });
    }

    // -------- Write --------

    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ('' === $name) {
            throw new \InvalidArgumentException('Exhibition space name is required.');
        }
        $now = now();
        return (int) DB::table('ahg_exhibition_space')->insertGetId([
            'slug'                => $this->generateUniqueSlug($name),
            'name'                => $name,
            'space_type'          => $this->normalizeSpaceType($data['space_type'] ?? null),
            'building'            => $data['building'] ?? null,
            'floor'               => $data['floor'] ?? null,
            'capacity_value'      => isset($data['capacity_value']) && $data['capacity_value'] !== '' ? (float) $data['capacity_value'] : null,
            'capacity_unit'       => $this->normalizeCapacityUnit($data['capacity_unit'] ?? null),
            'lighting_lux_target' => isset($data['lighting_lux_target']) && $data['lighting_lux_target'] !== '' ? (float) $data['lighting_lux_target'] : null,
            'notes'               => $data['notes'] ?? null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $payload = [
            'name'                => $data['name'] ?? null,
            'space_type'          => isset($data['space_type']) ? $this->normalizeSpaceType($data['space_type']) : null,
            'building'            => $data['building'] ?? null,
            'floor'               => $data['floor'] ?? null,
            'capacity_value'      => isset($data['capacity_value']) && $data['capacity_value'] !== '' ? (float) $data['capacity_value'] : null,
            'capacity_unit'       => isset($data['capacity_unit']) ? $this->normalizeCapacityUnit($data['capacity_unit']) : null,
            'lighting_lux_target' => isset($data['lighting_lux_target']) && $data['lighting_lux_target'] !== '' ? (float) $data['lighting_lux_target'] : null,
            'notes'               => $data['notes'] ?? null,
            'updated_at'          => now(),
        ];
        $payload = array_filter($payload, fn ($v) => $v !== null || in_array($payload, ['notes', 'lighting_lux_target', 'capacity_value', 'building', 'floor'], true));
        if (empty($payload)) return;
        DB::table('ahg_exhibition_space')->where('id', $id)->update($payload);
    }

    public function delete(int $id): void
    {
        $placements = DB::table('ahg_exhibition_placement')->where('exhibition_space_id', $id)->count();
        if ($placements > 0) {
            throw new \RuntimeException("Cannot delete: {$placements} placement(s) still reference this exhibition space.");
        }
        DB::table('ahg_exhibition_space')->where('id', $id)->delete();
    }

    /**
     * Insert / update a placement. Date-overlap capacity check is enforced.
     */
    public function placePlacement(array $data): int
    {
        $placementId = (int) ($data['id'] ?? 0);
        $spaceId = (int) ($data['exhibition_space_id'] ?? 0);
        $ioId = (int) ($data['information_object_id'] ?? 0);
        $size = (float) ($data['size_units_used'] ?? 0);
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;

        if ($spaceId <= 0 || $ioId <= 0) {
            throw new \InvalidArgumentException('exhibition_space_id and information_object_id are required.');
        }
        if ($startsAt && $endsAt && $startsAt > $endsAt) {
            throw new \InvalidArgumentException('starts_at must be on or before ends_at.');
        }
        if ($startsAt && $endsAt) {
            $overflow = $this->capacityOverflow($spaceId, $size, $startsAt, $endsAt, $placementId > 0 ? $placementId : null);
            if ($overflow !== null && $overflow > 0) {
                throw new \RuntimeException(sprintf(
                    'Placement would exceed capacity by %s units between %s and %s.',
                    number_format($overflow, 2), $startsAt, $endsAt
                ));
            }
        }

        $payload = [
            'information_object_id' => $ioId,
            'exhibition_space_id'   => $spaceId,
            'exhibition_id'         => isset($data['exhibition_id']) && $data['exhibition_id'] !== '' ? (int) $data['exhibition_id'] : null,
            'size_units_used'       => $size,
            'starts_at'             => $startsAt,
            'ends_at'               => $endsAt,
            'notes'                 => $data['notes'] ?? null,
            'updated_at'            => now(),
        ];

        if ($placementId > 0) {
            DB::table('ahg_exhibition_placement')->where('id', $placementId)->update($payload);
            return $placementId;
        }
        $payload['created_at'] = now();
        return (int) DB::table('ahg_exhibition_placement')->insertGetId($payload);
    }

    public function removePlacement(int $placementId): bool
    {
        return DB::table('ahg_exhibition_placement')->where('id', $placementId)->delete() > 0;
    }

    // -------- Helpers --------

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ('' === $base) $base = 'exhibition-space';
        $slug = $base;
        $i = 2;
        while (DB::table('ahg_exhibition_space')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }

    private function normalizeSpaceType(?string $type): string
    {
        if ($type === null || trim((string) $type) === '') return 'gallery';
        $type = trim((string) $type);
        return isset(self::SPACE_TYPES[$type]) ? $type : 'gallery';
    }

    private function normalizeCapacityUnit(?string $unit): string
    {
        if ($unit === null || trim((string) $unit) === '') return 'linear_wall_meters';
        $unit = trim((string) $unit);
        return isset(self::CAPACITY_UNITS[$unit]) ? $unit : 'linear_wall_meters';
    }
}
