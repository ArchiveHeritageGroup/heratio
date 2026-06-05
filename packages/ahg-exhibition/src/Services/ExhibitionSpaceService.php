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
        'gallery' => 'Gallery',
        'hall' => 'Hall',
        'display_case' => 'Display case',
        'plinth' => 'Plinth',
        'vitrine' => 'Vitrine',
    ];

    public const CAPACITY_UNITS = [
        'linear_wall_meters' => 'Linear wall metres',
        'display_cases' => 'Display cases',
        'plinths' => 'Plinths',
        'square_meters' => 'Square metres',
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

        if ($search !== '') {
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
        if (! $space || $space->capacity_value === null) {
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
        if (! $space || $space->capacity_value === null) {
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
        if ($name === '') {
            throw new \InvalidArgumentException('Exhibition space name is required.');
        }
        $now = now();

        return (int) DB::table('ahg_exhibition_space')->insertGetId([
            'slug' => $this->generateUniqueSlug($name),
            'name' => $name,
            'space_type' => $this->normalizeSpaceType($data['space_type'] ?? null),
            'building' => $data['building'] ?? null,
            'floor' => $data['floor'] ?? null,
            'capacity_value' => isset($data['capacity_value']) && $data['capacity_value'] !== '' ? (float) $data['capacity_value'] : null,
            'capacity_unit' => $this->normalizeCapacityUnit($data['capacity_unit'] ?? null),
            'lighting_lux_target' => isset($data['lighting_lux_target']) && $data['lighting_lux_target'] !== '' ? (float) $data['lighting_lux_target'] : null,
            'notes' => $data['notes'] ?? null,
            'room_w' => isset($data['room_w']) && $data['room_w'] !== '' ? (float) $data['room_w'] : null,
            'room_d' => isset($data['room_d']) && $data['room_d'] !== '' ? (float) $data['room_d'] : null,
            'room_h' => isset($data['room_h']) && $data['room_h'] !== '' ? (float) $data['room_h'] : null,
            'building_id' => isset($data['building_id']) && trim((string) $data['building_id']) !== '' ? trim((string) $data['building_id']) : null,
            'building_seq' => isset($data['building_seq']) && $data['building_seq'] !== '' ? (int) $data['building_seq'] : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $payload = [
            'name' => $data['name'] ?? null,
            'space_type' => isset($data['space_type']) ? $this->normalizeSpaceType($data['space_type']) : null,
            'building' => $data['building'] ?? null,
            'floor' => $data['floor'] ?? null,
            'capacity_value' => isset($data['capacity_value']) && $data['capacity_value'] !== '' ? (float) $data['capacity_value'] : null,
            'capacity_unit' => isset($data['capacity_unit']) ? $this->normalizeCapacityUnit($data['capacity_unit']) : null,
            'lighting_lux_target' => isset($data['lighting_lux_target']) && $data['lighting_lux_target'] !== '' ? (float) $data['lighting_lux_target'] : null,
            'notes' => $data['notes'] ?? null,
            'room_w' => isset($data['room_w']) && $data['room_w'] !== '' ? (float) $data['room_w'] : null,
            'room_d' => isset($data['room_d']) && $data['room_d'] !== '' ? (float) $data['room_d'] : null,
            'room_h' => isset($data['room_h']) && $data['room_h'] !== '' ? (float) $data['room_h'] : null,
            'building_id' => isset($data['building_id']) && trim((string) $data['building_id']) !== '' ? trim((string) $data['building_id']) : null,
            'building_seq' => isset($data['building_seq']) && $data['building_seq'] !== '' ? (int) $data['building_seq'] : null,
            'updated_at' => now(),
        ];
        $payload = array_filter($payload, fn ($v) => $v !== null || in_array($payload, ['notes', 'lighting_lux_target', 'capacity_value', 'building', 'floor'], true));
        if (empty($payload)) {
            return;
        }
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
            'exhibition_space_id' => $spaceId,
            'exhibition_id' => isset($data['exhibition_id']) && $data['exhibition_id'] !== '' ? (int) $data['exhibition_id'] : null,
            'size_units_used' => $size,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'notes' => $data['notes'] ?? null,
            'updated_at' => now(),
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

    // -------- Digital twin / builder (heratio#1138) --------

    /**
     * Placements for the drag-and-drop builder canvas: spatial coordinates plus a
     * best-effort thumbnail URL for each information object. Falls back gracefully
     * where no derivative exists (the canvas draws a labelled placeholder).
     */
    public function getPlacementsForBuilder(int $exhibitionSpaceId): array
    {
        $rows = DB::table('ahg_exhibition_placement as ep')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->where('ep.exhibition_space_id', $exhibitionSpaceId)
            ->select(
                'ep.id', 'ep.information_object_id',
                'ep.pos_x', 'ep.pos_y', 'ep.rotation_deg', 'ep.scale', 'ep.z_order',
                'ep.wall_or_zone', 'ep.label_visible', 'ep.size_units_used',
                'ep.model_tilt_x', 'ep.model_tilt_z', 'ep.wall_u', 'ep.wall_v',
                'ioi.title as information_object_title'
            )
            ->orderBy('ep.z_order')
            ->get();

        return $rows->map(function ($r) {
            $media = $this->getObjectMedia((int) $r->information_object_id);

            return [
                'id' => (int) $r->id,
                'information_object_id' => (int) $r->information_object_id,
                'title' => $r->information_object_title ?: ('#'.$r->information_object_id),
                'pos_x' => $r->pos_x !== null ? (float) $r->pos_x : null,
                'pos_y' => $r->pos_y !== null ? (float) $r->pos_y : null,
                'rotation_deg' => (float) ($r->rotation_deg ?? 0),
                'scale' => (float) ($r->scale ?? 1),
                'z_order' => (int) ($r->z_order ?? 0),
                'wall_or_zone' => $r->wall_or_zone,
                'label_visible' => (int) ($r->label_visible ?? 1),
                'size_units_used' => (float) ($r->size_units_used ?? 0),
                'kind' => $media['kind'],
                'tilt_x' => $r->model_tilt_x !== null ? (float) $r->model_tilt_x : null,
                'tilt_z' => $r->model_tilt_z !== null ? (float) $r->model_tilt_z : null,
                'wall_u' => $r->wall_u !== null ? (float) $r->wall_u : null,
                'wall_v' => $r->wall_v !== null ? (float) $r->wall_v : null,
                'thumb_url' => $media['image_url'] ?? $this->thumbnailUrl((int) $r->information_object_id),
            ];
        })->all();
    }

    /**
     * Best-effort thumbnail URL for an information object. The stored path already
     * begins with /uploads/r/..., so it is returned verbatim. Prefers the smaller
     * reference derivative, then thumbnail, then master.
     */
    public function thumbnailUrl(int $informationObjectId): ?string
    {
        return $this->bestImageUrl($informationObjectId);
    }

    /**
     * Best browser-renderable image URL for an object. Searches the object's own
     * digital objects AND their child derivatives - AtoM stores a web-friendly
     * JPEG as a child of a TIFF/RAW master - and prefers reference > master usage.
     * Returns null when only non-browser formats (tiff, raw, etc.) exist.
     */
    private function bestImageUrl(int $informationObjectId): ?string
    {
        $imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $direct = DB::table('digital_object')
            ->where('object_id', $informationObjectId)
            ->select('id', 'usage_id', 'path', 'name')->get();
        $ids = $direct->pluck('id')->all();
        $children = empty($ids) ? collect() : DB::table('digital_object')
            ->whereIn('parent_id', $ids)
            ->select('id', 'usage_id', 'path', 'name')->get();

        $candidates = $direct->concat($children)->filter(function ($r) use ($imgExts) {
            if (empty($r->path)) {
                return false;
            }
            $ext = strtolower(pathinfo((string) ($r->name ?: $r->path), PATHINFO_EXTENSION));

            return in_array($ext, $imgExts, true);
        });
        if ($candidates->isEmpty()) {
            return null;
        }
        $rank = [141 => 0, 142 => 1, 140 => 2];
        $best = $candidates->sortBy(fn ($r) => $rank[$r->usage_id] ?? 9)->first();

        return $this->buildDoUrl($best->path, $best->name);
    }

    /**
     * Build a public URL from a digital_object row. AtoM stores `path` as the
     * directory and `name` as the filename, so when `path` has no file extension
     * the filename is appended.
     */
    private function buildDoUrl(string $path, ?string $name): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        $hasExt = pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION) !== '';
        if (! $hasExt && ! empty($name)) {
            $path = rtrim($path, '/').'/'.ltrim($name, '/');
        }
        if (! str_starts_with($path, '/') && ! str_starts_with($path, 'http') && ! str_starts_with($path, 'uploads')) {
            $path = '/uploads/r/'.$path;
        }

        return $this->normalizeUploadPath($path);
    }

    /**
     * Resolve the display media for an information object so the 3D walkthrough can
     * pick the right renderer: a real 3D model (model-viewer / glTF in the scene)
     * or a flat image (framed plane). Returns kind = 3d|image|other plus URLs.
     *
     * @return array{kind:string,model_url:?string,image_url:?string,format:?string}
     */
    public function getObjectMedia(int $informationObjectId): array
    {
        // 1) Dedicated 3D model row wins.
        $model = DB::table('object_3d_model')
            ->where('object_id', $informationObjectId)
            ->orderByDesc('is_primary')
            ->first();
        if ($model && ! empty($model->file_path)) {
            return [
                'kind' => '3d',
                'model_url' => $this->normalizeUploadPath($model->file_path),
                'image_url' => $this->normalizeUploadPath($model->poster_image ?: $model->thumbnail) ?: $this->thumbnailUrl($informationObjectId),
                'format' => $model->format ?: 'glb',
            ];
        }

        // 2) Inspect the primary digital object to detect 3D / PDF masters.
        $do = DB::table('digital_object')
            ->where('object_id', $informationObjectId)
            ->whereIn('usage_id', [141, 142, 140])
            ->orderByRaw('FIELD(usage_id, 141, 142, 140)')
            ->select('path', 'name')
            ->first();
        if ($do && ! empty($do->path)) {
            $ext = strtolower(pathinfo((string) ($do->name ?: $do->path), PATHINFO_EXTENSION));
            $url = $this->buildDoUrl($do->path, $do->name);
            $threeD = ['glb', 'gltf', 'obj', 'stl', 'usdz', 'ply'];
            if (in_array($ext, $threeD, true)) {
                return ['kind' => '3d', 'model_url' => $url, 'image_url' => $this->bestImageUrl($informationObjectId), 'doc_url' => null, 'format' => $ext];
            }
            if ($ext === 'pdf') {
                return ['kind' => 'pdf', 'model_url' => null, 'image_url' => $this->bestImageUrl($informationObjectId), 'doc_url' => $url, 'format' => 'pdf'];
            }
        }

        // 3) Otherwise a flat image, using the best browser-renderable derivative
        //    (e.g. a TIFF master's JPEG child).
        $img = $this->bestImageUrl($informationObjectId);
        if ($img) {
            return ['kind' => 'image', 'model_url' => null, 'image_url' => $img, 'doc_url' => null, 'format' => 'image'];
        }

        return ['kind' => 'other', 'model_url' => null, 'image_url' => null, 'doc_url' => null, 'format' => null];
    }

    private function normalizeUploadPath(?string $p): ?string
    {
        if ($p === null || $p === '') {
            return null;
        }
        if (str_starts_with($p, 'http') || str_starts_with($p, '/')) {
            return $p;
        }
        if (str_starts_with($p, 'uploads')) {
            return '/'.$p;
        }

        return '/uploads/'.$p;
    }

    /**
     * Persist canvas positions. Only placements that belong to the given space are
     * updated, so a forged placement id from another space is ignored.
     *
     * @param  array<int,array<string,mixed>>  $positions  each: id,pos_x,pos_y,rotation_deg,scale,z_order
     */
    public function saveLayout(int $exhibitionSpaceId, array $positions): int
    {
        $valid = DB::table('ahg_exhibition_placement')
            ->where('exhibition_space_id', $exhibitionSpaceId)
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
        $valid = array_flip($valid);

        $saved = 0;
        foreach ($positions as $p) {
            $id = (int) ($p['id'] ?? 0);
            if ($id <= 0 || ! isset($valid[$id])) {
                continue;
            }
            DB::table('ahg_exhibition_placement')->where('id', $id)->update([
                'pos_x' => isset($p['pos_x']) ? max(0, min(1, (float) $p['pos_x'])) : null,
                'pos_y' => isset($p['pos_y']) ? max(0, min(1, (float) $p['pos_y'])) : null,
                'rotation_deg' => (float) ($p['rotation_deg'] ?? 0),
                'scale' => (float) ($p['scale'] ?? 1),
                'z_order' => (int) ($p['z_order'] ?? 0),
                'updated_at' => now(),
            ]);
            $saved++;
        }

        return $saved;
    }

    /**
     * Create a placement dropped onto the canvas (no date range, so no capacity
     * gate) and return its full builder row for immediate rendering.
     *
     * @return array<string,mixed>
     */
    public function createPlacementAt(int $exhibitionSpaceId, int $informationObjectId, float $posX, float $posY, float $sizeUnits = 0): array
    {
        if ($exhibitionSpaceId <= 0 || $informationObjectId <= 0) {
            throw new \InvalidArgumentException('exhibition_space_id and information_object_id are required.');
        }
        $sizeUnits = max(0, $sizeUnits);
        $now = now();
        $id = (int) DB::table('ahg_exhibition_placement')->insertGetId([
            'information_object_id' => $informationObjectId,
            'exhibition_space_id' => $exhibitionSpaceId,
            'size_units_used' => $sizeUnits,
            'pos_x' => max(0, min(1, $posX)),
            'pos_y' => max(0, min(1, $posY)),
            'rotation_deg' => 0,
            'scale' => 1,
            'z_order' => 0,
            'label_visible' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $title = DB::table('information_object_i18n')
            ->where('id', $informationObjectId)->where('culture', 'en')->value('title');

        return [
            'id' => $id,
            'information_object_id' => $informationObjectId,
            'title' => $title ?: ('#'.$informationObjectId),
            'pos_x' => max(0, min(1, $posX)),
            'pos_y' => max(0, min(1, $posY)),
            'rotation_deg' => 0.0,
            'scale' => 1.0,
            'z_order' => 0,
            'wall_or_zone' => null,
            'label_visible' => 1,
            'size_units_used' => $sizeUnits,
            'thumb_url' => $this->thumbnailUrl($informationObjectId),
        ];
    }

    /** Update just the capacity size of a placement (builder size editor). */
    public function updatePlacementSize(int $exhibitionSpaceId, int $placementId, float $sizeUnits): bool
    {
        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)
            ->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['size_units_used' => max(0, $sizeUnits), 'updated_at' => now()]) > 0;
    }

    /**
     * Per-object 3D orientation. Pass null for an axis to fall back to the
     * automatic up-axis guess; pass a number (degrees) to override it.
     */
    public function updatePlacementTilt(int $exhibitionSpaceId, int $placementId, ?float $tiltX, ?float $tiltZ): bool
    {
        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)
            ->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['model_tilt_x' => $tiltX, 'model_tilt_z' => $tiltZ, 'updated_at' => now()]) > 0;
    }

    // -------- Interior walls (heratio#1138, room dividers to hang on) --------

    /**
     * Interior wall segments for a space, in normalized floorplan coords (0-1).
     * Each: ['id'=>string,'x1','z1','x2','z2'].
     *
     * @return array<int,array<string,mixed>>
     */
    public function getWalls(int $exhibitionSpaceId): array
    {
        $space = $this->getById($exhibitionSpaceId);
        if (! $space || empty($space->walls_json)) {
            return [];
        }
        $walls = json_decode((string) $space->walls_json, true);

        return is_array($walls) ? array_values($walls) : [];
    }

    /**
     * Persist interior wall segments (normalized 0-1 coords). Sanitised + clamped.
     *
     * @param  array<int,array<string,mixed>>  $walls
     */
    public function saveWalls(int $exhibitionSpaceId, array $walls): void
    {
        $clean = [];
        foreach ($walls as $i => $w) {
            $x1 = isset($w['x1']) ? max(0, min(1, (float) $w['x1'])) : null;
            $z1 = isset($w['z1']) ? max(0, min(1, (float) $w['z1'])) : null;
            $x2 = isset($w['x2']) ? max(0, min(1, (float) $w['x2'])) : null;
            $z2 = isset($w['z2']) ? max(0, min(1, (float) $w['z2'])) : null;
            if ($x1 === null || $z1 === null || $x2 === null || $z2 === null) {
                continue;
            }
            $clean[] = [
                'id' => isset($w['id']) && $w['id'] !== '' ? (string) $w['id'] : ('wall-'.$i),
                'x1' => $x1, 'z1' => $z1, 'x2' => $x2, 'z2' => $z2,
            ];
        }
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['walls_json' => json_encode($clean), 'updated_at' => now()]);
    }

    /** Assign a placement to a specific wall (null/'' = auto nearest). */
    public function updatePlacementWall(int $exhibitionSpaceId, int $placementId, ?string $wall): bool
    {
        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)
            ->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['wall_or_zone' => ($wall !== null && $wall !== '') ? $wall : null, 'updated_at' => now()]) > 0;
    }

    // -------- Wall-elevation editor (hang objects directly on a wall) --------

    /** Create a placement hung on a specific wall at along-wall u + height v (0-1). */
    public function placeOnWall(int $exhibitionSpaceId, int $informationObjectId, string $wall, float $u, float $v): array
    {
        if ($exhibitionSpaceId <= 0 || $informationObjectId <= 0 || $wall === '') {
            throw new \InvalidArgumentException('space, object and wall are required.');
        }
        $u = max(0, min(1, $u));
        $v = max(0, min(1, $v));
        $floor = $this->wallToFloor($wall, $u);    // a sensible floor spot so it isn't stacked in Floor view
        $now = now();
        $id = (int) DB::table('ahg_exhibition_placement')->insertGetId([
            'information_object_id' => $informationObjectId,
            'exhibition_space_id' => $exhibitionSpaceId,
            'size_units_used' => 0,
            'wall_or_zone' => $wall,
            'wall_u' => $u,
            'wall_v' => $v,
            'pos_x' => $floor[0],
            'pos_y' => $floor[1],
            'rotation_deg' => 0, 'scale' => 1, 'z_order' => 0, 'label_visible' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $title = DB::table('information_object_i18n')->where('id', $informationObjectId)->where('culture', 'en')->value('title');
        $media = $this->getObjectMedia($informationObjectId);

        return [
            'id' => $id, 'information_object_id' => $informationObjectId,
            'title' => $title ?: ('#'.$informationObjectId),
            'pos_x' => $floor[0], 'pos_y' => $floor[1], 'rotation_deg' => 0.0, 'scale' => 1.0, 'z_order' => 0,
            'wall_or_zone' => $wall, 'label_visible' => 1, 'size_units_used' => 0.0,
            'kind' => $media['kind'], 'tilt_x' => null, 'tilt_z' => null,
            'wall_u' => max(0, min(1, $u)), 'wall_v' => max(0, min(1, $v)),
            'thumb_url' => $media['image_url'] ?? $this->thumbnailUrl($informationObjectId),
        ];
    }

    /** A floor (pos_x,pos_y) spot near a wall at along-wall fraction u (keeps Floor view tidy). */
    public function wallToFloor(string $wall, float $u): array
    {
        $u = max(0.05, min(0.95, $u));
        switch ($wall) {
            case 'north': return [$u, 0.08];
            case 'south': return [$u, 0.92];
            case 'west': return [0.08, $u];
            case 'east': return [0.92, $u];
            default: return [0.3 + 0.4 * $u, 0.5];   // interior wall - spread along centre
        }
    }

    /** Set room dimensions (metres) - width, depth, wall height. Nulls are skipped. */
    public function updateRoomDims(int $exhibitionSpaceId, ?float $w, ?float $d, ?float $h): void
    {
        $p = [];
        if ($w !== null) {
            $p['room_w'] = max(1, min(200, $w));
        }
        if ($d !== null) {
            $p['room_d'] = max(1, min(200, $d));
        }
        if ($h !== null) {
            $p['room_h'] = max(1, min(30, $h));
        }
        if ($p) {
            $p['updated_at'] = now();
            DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)->update($p);
        }
    }

    /** Update an object's along-wall (u) + height (v) position on its wall. */
    public function updateWallPos(int $exhibitionSpaceId, int $placementId, float $u, float $v): bool
    {
        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['wall_u' => max(0, min(1, $u)), 'wall_v' => max(0, min(1, $v)), 'updated_at' => now()]) > 0;
    }

    // -------- Multi-room building (heratio#1143/#1144) --------

    public function roomDims(object $space): array
    {
        return [
            'w' => $space->room_w !== null ? (float) $space->room_w : 18.0,
            'd' => $space->room_d !== null ? (float) $space->room_d : 14.0,
            'h' => $space->room_h !== null ? (float) $space->room_h : 4.0,
        ];
    }

    /**
     * Assemble the building for the walkthrough: all rooms sharing this space's
     * building_id (or just this space when ungrouped), laid out in a row along X
     * with each room sized by its own room_w/room_d/room_h. Each room carries its
     * own stops + interior walls + floorplan + x-offset (world units).
     *
     * @return array{rooms:array<int,array<string,mixed>>,total_w:float,max_d:float,max_h:float}
     */
    public function getWalkthroughBuilding(object $space): array
    {
        $rooms = (! empty($space->building_id))
            ? DB::table('ahg_exhibition_space')->where('building_id', $space->building_id)
                ->orderBy('building_seq')->orderBy('id')->get()->all()
            : [$space];

        // Plan mode = at least one room has explicit plan coordinates.
        $planMode = false;
        foreach ($rooms as $r) {
            if ($r->bld_x !== null && $r->bld_y !== null) {
                $planMode = true;
                break;
            }
        }

        $out = [];
        $xCursor = 0.0;
        $maxH = 0.0;
        $minX = null;
        $maxX = null;
        $minZ = null;
        $maxZ = null;
        foreach ($rooms as $r) {
            $dim = $this->roomDims($r);
            if ($planMode && $r->bld_x !== null && $r->bld_y !== null) {
                $x = (float) $r->bld_x;
                $z = (float) $r->bld_y;          // top-left origin on the plan
            } else {
                $x = $xCursor;
                $z = -$dim['d'] / 2;             // auto-row, centred in z
                $xCursor += $dim['w'];
            }
            $out[] = [
                'id' => (int) $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'w' => $dim['w'], 'd' => $dim['d'], 'h' => $dim['h'],
                'x_offset' => $x, 'z_offset' => $z,
                'is_current' => (int) $r->id === (int) $space->id,
                'floorplan' => $r->floorplan_image_path ?? null,
                'ceiling' => $r->ceiling_image_path ?? null,
                'wall_image' => $r->wall_image_path ?? null,
                'stops' => $this->getWalkthroughStops((int) $r->id),
                'walls' => $this->getWalls((int) $r->id),
            ];
            $minX = $minX === null ? $x : min($minX, $x);
            $maxX = $maxX === null ? $x + $dim['w'] : max($maxX, $x + $dim['w']);
            $minZ = $minZ === null ? $z : min($minZ, $z);
            $maxZ = $maxZ === null ? $z + $dim['d'] : max($maxZ, $z + $dim['d']);
            $maxH = max($maxH, $dim['h']);
        }

        return [
            'rooms' => $out, 'plan_mode' => $planMode,
            'min_x' => $minX ?? 0, 'max_x' => $maxX ?? 0, 'min_z' => $minZ ?? 0, 'max_z' => $maxZ ?? 0,
            'total_w' => ($maxX ?? 0) - ($minX ?? 0), 'max_d' => ($maxZ ?? 0) - ($minZ ?? 0), 'max_h' => $maxH,
        ];
    }

    // -------- Building plan editor (#1143: floor-plan layout) --------

    /** Rooms of a building for the plan editor + the plan image (from any room that has one). */
    public function getBuildingPlan(object $space): array
    {
        $rooms = (! empty($space->building_id))
            ? DB::table('ahg_exhibition_space')->where('building_id', $space->building_id)->orderBy('building_seq')->orderBy('id')->get()->all()
            : [$space];
        $plan = null;
        $list = [];
        foreach ($rooms as $r) {
            if (! $plan && ! empty($r->building_plan_image)) {
                $plan = $r->building_plan_image;
            }
            $dim = $this->roomDims($r);
            $list[] = [
                'id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug,
                'w' => $dim['w'], 'd' => $dim['d'],
                'bld_x' => $r->bld_x !== null ? (float) $r->bld_x : null,
                'bld_y' => $r->bld_y !== null ? (float) $r->bld_y : null,
                'is_current' => (int) $r->id === (int) $space->id,
            ];
        }

        return ['rooms' => $list, 'plan_image' => $plan];
    }

    /** Save a room's plan position + size (metres). */
    public function savePlanRoom(int $buildingMemberId, int $roomId, float $x, float $y, ?float $w, ?float $d): bool
    {
        $member = $this->getById($buildingMemberId);
        $room = $this->getById($roomId);
        if (! $member || ! $room) {
            return false;
        }
        // Only allow editing rooms in the same building (or the room itself).
        if (($member->building_id ?? null) !== ($room->building_id ?? null) && $roomId !== $buildingMemberId) {
            return false;
        }
        $p = ['bld_x' => $x, 'bld_y' => $y, 'updated_at' => now()];
        if ($w !== null) {
            $p['room_w'] = max(1, min(200, $w));
        }
        if ($d !== null) {
            $p['room_d'] = max(1, min(200, $d));
        }

        return DB::table('ahg_exhibition_space')->where('id', $roomId)->update($p) > 0;
    }

    /** Set/clear the building plan (blueprint) image, stored on every room of the building. */
    public function setBuildingPlanImage(object $space, ?string $publicPath): void
    {
        $q = DB::table('ahg_exhibition_space');
        if (! empty($space->building_id)) {
            $q->where('building_id', $space->building_id);
        } else {
            $q->where('id', $space->id);
        }
        $q->update(['building_plan_image' => $publicPath, 'updated_at' => now()]);
    }

    public function setFloorplan(int $exhibitionSpaceId, string $publicPath, ?float $widthM = null, ?float $heightM = null): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)->update(array_filter([
            'floorplan_image_path' => $publicPath,
            'floorplan_width_m' => $widthM,
            'floorplan_height_m' => $heightM,
            'updated_at' => now(),
        ], fn ($v) => $v !== null));
    }

    /** Set or clear the room ceiling image (pass null to clear). */
    public function setCeiling(int $exhibitionSpaceId, ?string $publicPath): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['ceiling_image_path' => $publicPath, 'updated_at' => now()]);
    }

    /** Set or clear the room wall (painted/decorated) image, applied to all walls. */
    public function setWallImage(int $exhibitionSpaceId, ?string $publicPath): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['wall_image_path' => $publicPath, 'updated_at' => now()]);
    }

    /**
     * Persist the ordered guided-route of placement ids for the walkthrough.
     *
     * @param  array<int,int>  $placementIds
     */
    public function saveWalkthroughPath(int $exhibitionSpaceId, array $placementIds): void
    {
        $valid = DB::table('ahg_exhibition_placement')
            ->where('exhibition_space_id', $exhibitionSpaceId)
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
        $valid = array_flip($valid);
        $ordered = array_values(array_filter(array_map('intval', $placementIds), fn ($id) => isset($valid[$id])));

        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)->update([
            'walkthrough_path_json' => json_encode($ordered),
            'updated_at' => now(),
        ]);
    }

    // -------- Digital twin / walkthrough (heratio#1138, Phase 2) --------

    /**
     * Ordered stops for the 2.5D pannable walkthrough. Each stop carries spatial
     * coordinates, thumbnail + full image, a short description and a link to the
     * full archival record. Order follows the saved guided route when present,
     * otherwise natural reading order (top-to-bottom, left-to-right).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getWalkthroughStops(int $exhibitionSpaceId): array
    {
        $space = $this->getById($exhibitionSpaceId);

        $rows = DB::table('ahg_exhibition_placement as ep')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'ep.information_object_id')
            ->where('ep.exhibition_space_id', $exhibitionSpaceId)
            ->select(
                'ep.id', 'ep.information_object_id', 'ep.pos_x', 'ep.pos_y',
                'ep.rotation_deg', 'ep.scale', 'ep.wall_or_zone',
                'ep.model_tilt_x', 'ep.model_tilt_z', 'ep.wall_u', 'ep.wall_v',
                'ioi.title as title', 'ioi.scope_and_content as description', 'sl.slug as slug'
            )
            ->get();

        $byId = [];
        $stops = [];
        foreach ($rows as $r) {
            $desc = trim(strip_tags((string) ($r->description ?? '')));
            $media = $this->getObjectMedia((int) $r->information_object_id);
            $stop = [
                'id' => (int) $r->id,
                'information_object_id' => (int) $r->information_object_id,
                'title' => $r->title ?: ('#'.$r->information_object_id),
                'description' => mb_strlen($desc) > 400 ? mb_substr($desc, 0, 400).'...' : $desc,
                'pos_x' => $r->pos_x !== null ? (float) $r->pos_x : 0.5,
                'pos_y' => $r->pos_y !== null ? (float) $r->pos_y : 0.5,
                'rotation_deg' => (float) ($r->rotation_deg ?? 0),
                'scale' => (float) ($r->scale ?? 1),
                'wall_or_zone' => $r->wall_or_zone,
                'kind' => $media['kind'],
                'model_url' => $media['model_url'],
                'model_format' => $media['format'],
                'tilt_x' => $r->model_tilt_x !== null ? (float) $r->model_tilt_x : null,
                'tilt_z' => $r->model_tilt_z !== null ? (float) $r->model_tilt_z : null,
                'wall_u' => $r->wall_u !== null ? (float) $r->wall_u : null,
                'wall_v' => $r->wall_v !== null ? (float) $r->wall_v : null,
                'image_url' => $media['image_url'],
                'doc_url' => $media['doc_url'] ?? null,
                'thumb_url' => $media['image_url'] ?? $this->thumbnailUrl((int) $r->information_object_id),
                'record_url' => $r->slug ? '/'.$r->slug : null,
            ];
            $byId[$stop['id']] = $stop;
            $stops[] = $stop;
        }

        // Apply saved guided route if present.
        $path = [];
        if ($space && ! empty($space->walkthrough_path_json)) {
            $decoded = json_decode((string) $space->walkthrough_path_json, true);
            if (is_array($decoded)) {
                $path = $decoded;
            }
        }
        if (! empty($path)) {
            $ordered = [];
            foreach ($path as $pid) {
                if (isset($byId[(int) $pid])) {
                    $ordered[] = $byId[(int) $pid];
                    unset($byId[(int) $pid]);
                }
            }
            foreach ($byId as $remaining) {
                $ordered[] = $remaining;
            }

            return $ordered;
        }

        // Default: reading order (top-to-bottom, then left-to-right).
        usort($stops, function ($a, $b) {
            if (abs($a['pos_y'] - $b['pos_y']) > 0.08) {
                return $a['pos_y'] <=> $b['pos_y'];
            }

            return $a['pos_x'] <=> $b['pos_x'];
        });

        return $stops;
    }

    // -------- Helpers --------

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'exhibition-space';
        }
        $slug = $base;
        $i = 2;
        while (DB::table('ahg_exhibition_space')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function normalizeSpaceType(?string $type): string
    {
        if ($type === null || trim((string) $type) === '') {
            return 'gallery';
        }
        $type = trim((string) $type);

        return isset(self::SPACE_TYPES[$type]) ? $type : 'gallery';
    }

    private function normalizeCapacityUnit(?string $unit): string
    {
        if ($unit === null || trim((string) $unit) === '') {
            return 'linear_wall_meters';
        }
        $unit = trim((string) $unit);

        return isset(self::CAPACITY_UNITS[$unit]) ? $unit : 'linear_wall_meters';
    }
}
