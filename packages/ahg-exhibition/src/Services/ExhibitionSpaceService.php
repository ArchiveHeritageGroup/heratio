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
            ->where(function ($q) {   // corridor objects are building-level, not per-room
                $q->whereNull('ep.wall_or_zone')->orWhere('ep.wall_or_zone', '!=', 'corridor');
            })
            ->select(
                'ep.id', 'ep.information_object_id',
                'ep.pos_x', 'ep.pos_y', 'ep.rotation_deg', 'ep.scale', 'ep.z_order',
                'ep.wall_or_zone', 'ep.label_visible', 'ep.size_units_used',
                'ep.model_tilt_x', 'ep.model_tilt_z', 'ep.wall_u', 'ep.wall_v', 'ep.spotlight', 'ep.display_case', 'ep.on_floor',
                'ep.view_x', 'ep.view_y',
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
                'view_x' => $r->view_x !== null ? (float) $r->view_x : null,
                'view_y' => $r->view_y !== null ? (float) $r->view_y : null,
                'kind' => $media['kind'],
                'tilt_x' => $r->model_tilt_x !== null ? (float) $r->model_tilt_x : null,
                'tilt_z' => $r->model_tilt_z !== null ? (float) $r->model_tilt_z : null,
                'wall_u' => $r->wall_u !== null ? (float) $r->wall_u : null,
                'wall_v' => $r->wall_v !== null ? (float) $r->wall_v : null,
                'spotlight' => (int) ($r->spotlight ?? 0),
                'display_case' => (int) ($r->display_case ?? 0), 'on_floor' => (int) ($r->on_floor ?? 0),   // #1174
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
    /**
     * Models above this size freeze the browser when parsed on the main thread
     * (e.g. a 66MB OBJ). The walkthrough shows a placeholder for these instead of loading them.
     */
    private const MAX_MODEL_BYTES = 20 * 1024 * 1024;   // 20 MB

    private function modelTooBig(?string $webPath): bool
    {
        if (! $webPath) {
            return false;
        }
        $f = rtrim((string) config('heratio.storage_path'), '/').'/'.ltrim($webPath, '/');

        return is_file($f) && filesize($f) > self::MAX_MODEL_BYTES;
    }

    public function getObjectMedia(int $informationObjectId): array
    {
        // 1) Dedicated 3D model row wins.
        $model = DB::table('object_3d_model')
            ->where('object_id', $informationObjectId)
            ->orderByDesc('is_primary')
            ->first();
        if ($model && ! empty($model->file_path)) {
            $murl = $this->normalizeUploadPath($model->file_path);

            return [
                'kind' => '3d',
                'model_url' => $murl,
                'model_oversize' => $this->modelTooBig($murl),
                'image_url' => $this->normalizeUploadPath($model->poster_image ?: $model->thumbnail) ?: $this->thumbnailUrl($informationObjectId),
                'format' => $model->format ?: 'glb',
                'splat_url' => null, 'splat_center' => null, 'splat_radius' => null,
            ];
        }

        // 2) Inspect the primary digital object to detect 3D / PDF / Gaussian-splat masters.
        $splatUrl = null;   // #1193 in-room splats: surfaced as a side-channel so the legacy
        $splatCenter = null; $splatRadius = null; $splatViewUrl = null;   // walkthrough still shows
                            // the flat thumbnail (kind=image) while the ESM/GaussianSplats3D
                            // walkthrough renders object-scale splats composited in-room (sized/
                            // centred from sidecar bounds) and links scene-scale ones to the viewer.
        $do = DB::table('digital_object')
            ->where('object_id', $informationObjectId)
            ->whereIn('usage_id', [141, 142, 140])
            ->orderByRaw('FIELD(usage_id, 141, 142, 140)')
            ->select('id', 'path', 'name')
            ->first();
        if ($do && ! empty($do->path)) {
            $ext = strtolower(pathinfo((string) ($do->name ?: $do->path), PATHINFO_EXTENSION));
            $url = $this->buildDoUrl($do->path, $do->name);
            $threeD = ['glb', 'gltf', 'obj', 'stl', 'usdz', 'ply'];
            if (in_array($ext, ['splat', 'ksplat'], true)) {
                $splatUrl = $url;   // fall through to the image fallback for `kind`/thumbnail
                $splatViewUrl = '/splat/do/'.$do->id;   // standalone full-page viewer (scene-scale splats link here)
                // Bounds (centre + radius) so the in-room renderer can fit the splat to a real-world
                // size instead of its native (often huge) capture scale. Sidecar-cached, so cheap.
                try {
                    $abs = rtrim((string) config('heratio.uploads_path'), '/').'/'.$informationObjectId.'/'.$do->name;
                    $b = app(\AhgCore\Services\GaussianSplatService::class)->computeBounds($abs, $ext);
                    if (is_array($b) && ! empty($b['radius'])) { $splatCenter = $b['center']; $splatRadius = (float) $b['radius']; }
                } catch (\Throwable $e) { /* fit falls back to a small default client-side */ }
            } elseif (in_array($ext, $threeD, true)) {
                return ['kind' => '3d', 'model_url' => $url, 'model_oversize' => $this->modelTooBig($url), 'image_url' => $this->bestImageUrl($informationObjectId), 'doc_url' => null, 'format' => $ext, 'splat_url' => null, 'splat_center' => null, 'splat_radius' => null];
            } elseif ($ext === 'pdf') {
                return ['kind' => 'pdf', 'model_url' => null, 'image_url' => $this->bestImageUrl($informationObjectId), 'doc_url' => $url, 'format' => 'pdf', 'splat_url' => null, 'splat_center' => null, 'splat_radius' => null];
            }
        }

        // 3) Otherwise a flat image, using the best browser-renderable derivative
        //    (e.g. a TIFF master's JPEG child). Splats land here too (thumbnail in-room for
        //    the legacy viewer) while carrying splat_url + bounds for the splat-capable walkthrough.
        $img = $this->bestImageUrl($informationObjectId);
        if ($img) {
            return ['kind' => 'image', 'model_url' => null, 'image_url' => $img, 'doc_url' => null, 'format' => $splatUrl ? 'splat' : 'image', 'splat_url' => $splatUrl, 'splat_center' => $splatCenter, 'splat_radius' => $splatRadius, 'splat_view_url' => $splatViewUrl];
        }

        return ['kind' => 'other', 'model_url' => null, 'image_url' => null, 'doc_url' => null, 'format' => $splatUrl ? 'splat' : null, 'splat_url' => $splatUrl, 'splat_center' => $splatCenter, 'splat_radius' => $splatRadius, 'splat_view_url' => $splatViewUrl];
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

    /** #1174: set a per-object spotlight mode. 0 = off, 1 = light on approach, 2 = always-on (object stays lit). */
    public function updatePlacementSpotlight(int $exhibitionSpaceId, int $placementId, int $mode): bool
    {
        $mode = max(0, min(2, $mode));
        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['spotlight' => $mode, 'updated_at' => now()]) > 0;
    }

    /** Toggle whether this item is shown inside a glass display case on a plinth. */
    public function updatePlacementDisplayCase(int $exhibitionSpaceId, int $placementId, bool $on): bool
    {
        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['display_case' => $on ? 1 : 0, 'updated_at' => now()]) > 0;
    }

    /** Toggle whether a 3D model stands directly on the floor (no pedestal). */
    public function updatePlacementOnFloor(int $exhibitionSpaceId, int $placementId, bool $on): bool
    {
        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['on_floor' => $on ? 1 : 0, 'updated_at' => now()]) > 0;
    }

    /**
     * Set (or clear) the curator-chosen viewing spot for a placement - the room-
     * local fraction (0-1) where the tour/walk stands to view this object. Pass
     * null x/y to clear (revert to the automatic in-front position).
     */
    public function updatePlacementView(int $exhibitionSpaceId, int $placementId, ?float $vx, ?float $vy): bool
    {
        $clamp = fn ($v) => $v === null ? null : max(0, min(1, (float) $v));

        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['view_x' => $clamp($vx), 'view_y' => $clamp($vy), 'updated_at' => now()]) > 0;
    }

    /** Bring-to-front / send-to-back: set a placement's z-order. */
    public function updatePlacementZOrder(int $exhibitionSpaceId, int $placementId, int $z): bool
    {
        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)->where('exhibition_space_id', $exhibitionSpaceId)
            ->update(['z_order' => $z, 'updated_at' => now()]) > 0;
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

    // -------- Doorways (manual, on plan-positioned rooms) --------

    /**
     * Doors for a room. Each: ['wall'=>north|south|east|west,'pos'=>0..1,'width'=>m].
     * pos = fraction along the wall (north/south: left->right; east/west: back->front).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getDoors(int $exhibitionSpaceId): array
    {
        $space = $this->getById($exhibitionSpaceId);
        if (! $space || empty($space->doors_json)) {
            return [];
        }
        $doors = json_decode((string) $space->doors_json, true);

        return is_array($doors) ? array_values($this->sanitizeDoors($doors)) : [];
    }

    /** heratio#1172 - windows on a room's walls: [{wall,pos,width,sill,height}]. */
    public function getWindows(int $exhibitionSpaceId): array
    {
        $space = $this->getById($exhibitionSpaceId);
        if (! $space || empty($space->windows_json)) {
            return [];
        }
        $w = json_decode((string) $space->windows_json, true);
        if (!is_array($w)) {
            return [];
        }
        $out = [];
        foreach ($w as $x) {
            $hasWall = ! empty($x['wall']);
            $hasEdge = isset($x['edge']) && is_numeric($x['edge']);   // #1172 windows on polygon edges
            if (! $hasWall && ! $hasEdge) {
                continue;
            }
            $row = [
                'pos' => isset($x['pos']) ? max(0.0, min(1.0, (float) $x['pos'])) : 0.5,
                'width' => isset($x['width']) ? max(0.4, min(6.0, (float) $x['width'])) : 1.4,
                'sill' => isset($x['sill']) ? max(0.2, min(2.0, (float) $x['sill'])) : 0.9,
                'height' => isset($x['height']) ? max(0.4, min(3.0, (float) $x['height'])) : 1.3,
            ];
            if ($hasEdge) {
                $row['edge'] = (int) $x['edge'];
            } else {
                $row['wall'] = (string) $x['wall'];
            }
            $out[] = $row;
        }

        return $out;
    }

    /** Persist a room's windows. */
    public function saveWindows(int $exhibitionSpaceId, array $windows): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['windows_json' => json_encode($windows), 'updated_at' => now()]);
    }

    /** Persist a room's doors (sanitised). */
    public function saveDoors(int $exhibitionSpaceId, array $doors): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['doors_json' => json_encode(array_values($this->sanitizeDoors($doors))), 'updated_at' => now()]);
    }

    /** @param array<int,mixed> $doors @return array<int,array<string,mixed>> */
    private function sanitizeDoors(array $doors): array
    {
        $walls = ['north', 'south', 'east', 'west'];
        // #1171 door leaf style. 'open' = bare doorway (flat panel, default). Others render a hinged
        // or sliding leaf that swings/slides open in the walkthrough.
        $types = ['open', 'single', 'double', 'glass', 'sliding', 'ornate'];
        $clean = [];
        foreach ($doors as $d) {
            if (! is_array($d)) {
                continue;
            }
            $pos = max(0.0, min(1.0, (float) ($d['pos'] ?? 0.5)));
            $width = max(0.5, min(6.0, (float) ($d['width'] ?? 1.6)));
            $type = isset($d['type']) && in_array((string) $d['type'], $types, true) ? (string) $d['type'] : 'open';
            // Polygon-edge door (edge index into the room's shape).
            if (isset($d['edge']) && is_numeric($d['edge'])) {
                $clean[] = ['edge' => max(0, (int) $d['edge']), 'pos' => $pos, 'width' => $width, 'type' => $type];

                continue;
            }
            // Rectangle named-wall door.
            $wall = isset($d['wall']) ? strtolower((string) $d['wall']) : '';
            if (! in_array($wall, $walls, true)) {
                continue;
            }
            $clean[] = ['wall' => $wall, 'pos' => $pos, 'width' => $width, 'type' => $type];
        }

        return $clean;
    }

    // -------- Custom room footprint (polygon shape) --------

    /**
     * A room's footprint polygon as normalized points [{x,z}] in 0-1 of the room's
     * bounding box (w x d). Null when the room is a plain rectangle.
     *
     * @return array<int,array{x:float,z:float}>|null
     */
    public function getShape(int $exhibitionSpaceId): ?array
    {
        $space = $this->getById($exhibitionSpaceId);
        if (! $space || empty($space->shape_json)) {
            return null;
        }
        $pts = json_decode((string) $space->shape_json, true);

        return $this->sanitizeShape(is_array($pts) ? $pts : []);
    }

    /** Persist a room footprint polygon (normalized 0-1). Null/<3 points clears it. */
    public function saveShape(int $exhibitionSpaceId, ?array $points): void
    {
        $clean = $points === null ? null : $this->sanitizeShape($points);
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['shape_json' => $clean ? json_encode($clean) : null, 'updated_at' => now()]);
    }

    /** @param array<int,mixed> $points @return array<int,array{x:float,z:float}>|null */
    private function sanitizeShape(array $points): ?array
    {
        $clean = [];
        foreach ($points as $p) {
            $x = null;
            $z = null;
            if (is_array($p)) {
                $x = $p['x'] ?? ($p[0] ?? null);
                $z = $p['z'] ?? ($p[1] ?? null);
            }
            if ($x === null || $z === null) {
                continue;
            }
            $clean[] = ['x' => max(0.0, min(1.0, (float) $x)), 'z' => max(0.0, min(1.0, (float) $z))];
        }

        return count($clean) >= 3 ? $clean : null;
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
                'rot' => ($planMode && $r->bld_rot !== null) ? (float) $r->bld_rot : 0.0,
                'is_current' => (int) $r->id === (int) $space->id,
                'floorplan' => $r->floorplan_image_path ?? null,
                'ceiling' => $r->ceiling_image_path ?? null,
                'wall_image' => $r->wall_image_path ?? null,
                'floor_image' => $r->floor_image_path ?? null,   // decorative floor picture (stretched)
                'floor_grout' => (int) ($r->floor_grout ?? 0),   // overlay a grout grid on the floor image
                'floor_tile_m' => (float) ($r->floor_tile_m ?? 2),   // floor tile size in metres (marble + floor-image grout)
                'floor_grout_mm' => (float) ($r->floor_grout_mm ?? 8),   // grout-line width (mm)

                'wall_color' => $r->wall_color ?? null,          // all-walls paint colour (#hex), used when no image
                'wall_colors' => (! empty($r->wall_colors_json) && is_array($wc = json_decode((string) $r->wall_colors_json, true))) ? $wc : new \stdClass,   // per-edge paint colours
                'wall_images' => (! empty($r->wall_images_json) && is_array($wi = json_decode((string) $r->wall_images_json, true))) ? $wi : new \stdClass,   // #wall-pictures per-edge overrides
                'furniture' => $this->getFurniture((int) $r->id),   // placeable furniture & fittings

                'stops' => $this->getWalkthroughStops((int) $r->id),
                'walls' => $this->getWalls((int) $r->id),
                'doors' => $this->getDoors((int) $r->id),
                'windows' => $this->getWindows((int) $r->id),   // #1172
                'shape' => $this->getShape((int) $r->id),
                'live' => $this->liveState($r),
                'floor' => (int) ($r->floor_level ?? 0),           // heratio#1169 building level (numeric; from floor_level)
                'is_outdoor' => (int) ($r->is_outdoor ?? 0) === 1, // heratio#1170 open-air space
                'scan_shell' => $r->scan_shell_path ?? null,       // heratio#1156 photoreal capture shell (glTF/OBJ/etc.) rendered as the room backdrop
                'scan_shell_scale' => (float) ($r->scan_shell_scale ?? 1),   // uniform fit-scale for the shell
                'scan_embed' => $r->scan_embed_url ?? null,        // 360/Matterport embed URL (overlay)
            ];
            $minX = $minX === null ? $x : min($minX, $x);
            $maxX = $maxX === null ? $x + $dim['w'] : max($maxX, $x + $dim['w']);
            $minZ = $minZ === null ? $z : min($minZ, $z);
            $maxZ = $maxZ === null ? $z + $dim['d'] : max($maxZ, $z + $dim['d']);
            $maxH = max($maxH, $dim['h']);
        }

        $hasOutdoor = false;
        foreach ($out as $rm) {
            if (!empty($rm['is_outdoor'])) { $hasOutdoor = true; break; }
        }
        $stairs = $space->stairs_json ?? null;
        if (is_string($stairs)) { $stairs = json_decode($stairs, true); }

        return [
            'rooms' => $out, 'plan_mode' => $planMode,
            'corridor' => $this->getBuildingCorridorObjects($space),
            'min_x' => $minX ?? 0, 'max_x' => $maxX ?? 0, 'min_z' => $minZ ?? 0, 'max_z' => $maxZ ?? 0,
            'total_w' => ($maxX ?? 0) - ($minX ?? 0), 'max_d' => ($maxZ ?? 0) - ($minZ ?? 0), 'max_h' => $maxH,
            'floor_height' => max(4.5, $maxH + 0.5),           // heratio#1169 metres between floors (clears the tallest walls so floors stack)
            'has_outdoor' => $hasOutdoor,                       // heratio#1170 sky + sun when true
            'stairs' => is_array($stairs) ? $stairs : [],       // heratio#1169 [{x,z,from_floor,to_floor}]
        ];
    }

    /** Space ids belonging to a space's building (or just the space when ungrouped). */
    private function buildingSpaceIds(object $space): array
    {
        if (! empty($space->building_id)) {
            return DB::table('ahg_exhibition_space')->where('building_id', $space->building_id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        return [(int) $space->id];
    }

    /**
     * Corridor objects for a building: placements flagged wall_or_zone='corridor'
     * whose pos_x/pos_y are fractions (0-1) of the building bounding box. Returns
     * walkthrough-ready stops (title, description, media, record link).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getBuildingCorridorObjects(object $space): array
    {
        $ids = $this->buildingSpaceIds($space);
        if (empty($ids)) {
            return [];
        }
        $rows = DB::table('ahg_exhibition_placement as ep')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'ep.information_object_id')
            ->whereIn('ep.exhibition_space_id', $ids)
            ->where('ep.wall_or_zone', 'corridor')
            ->select('ep.id', 'ep.information_object_id', 'ep.pos_x', 'ep.pos_y', 'ep.rotation_deg', 'ep.scale',
                'ep.model_tilt_x', 'ep.model_tilt_z', 'ioi.title as title', 'ioi.scope_and_content as description', 'sl.slug as slug')
            ->get();

        return $rows->map(function ($r) {
            $desc = trim(strip_tags((string) ($r->description ?? '')));
            $media = $this->getObjectMedia((int) $r->information_object_id);

            return [
                'id' => (int) $r->id,
                'information_object_id' => (int) $r->information_object_id,
                'title' => $r->title ?: ('#'.$r->information_object_id),
                'description' => mb_strlen($desc) > 400 ? mb_substr($desc, 0, 400).'...' : $desc,
                'pos_x' => $r->pos_x !== null ? (float) $r->pos_x : 0.5,
                'pos_y' => $r->pos_y !== null ? (float) $r->pos_y : 0.5,
                'rotation_deg' => (float) ($r->rotation_deg ?? 0),
                'scale' => (float) ($r->scale ?? 1),
                'kind' => $media['kind'],
                'model_url' => $media['model_url'],
                'model_oversize' => ! empty($media['model_oversize']),   // too big to load in the browser -> placeholder
                'model_format' => $media['format'],
                'splat_url' => $media['splat_url'] ?? null,   // #1193 Gaussian splat (in-room DropInViewer)
                'splat_center' => $media['splat_center'] ?? null,
                'splat_radius' => $media['splat_radius'] ?? null,
                'splat_view_url' => $media['splat_view_url'] ?? null,
                'tilt_x' => $r->model_tilt_x !== null ? (float) $r->model_tilt_x : null,
                'tilt_z' => $r->model_tilt_z !== null ? (float) $r->model_tilt_z : null,
                'image_url' => $media['image_url'],
                'doc_url' => $media['doc_url'] ?? null,
                'record_url' => $r->slug ? '/'.$r->slug : null,
            ];
        })->all();
    }

    // -------- Live data link + conservation status (heratio#1146) --------

    /** Append a sensor/occupancy reading for a space (the digital-twin data link). */
    public function recordReading(int $spaceId, string $metric, float $value, ?string $recordedAt = null): void
    {
        DB::table('ahg_exhibition_reading')->insert([
            'exhibition_space_id' => $spaceId,
            'metric' => substr($metric, 0, 32),
            'value' => $value,
            'recorded_at' => $recordedAt ?: now(),
        ]);
    }

    /** heratio#1188 - the per-space sensor token (created on first read). */
    public function getOrCreateSensorToken(int $spaceId): string
    {
        $tok = DB::table('ahg_exhibition_space')->where('id', $spaceId)->value('sensor_token');
        if (! $tok) {
            $tok = 'sx_'.bin2hex(random_bytes(20));
            DB::table('ahg_exhibition_space')->where('id', $spaceId)->update(['sensor_token' => $tok, 'updated_at' => now()]);
        }

        return $tok;
    }

    /** heratio#1188 - rotate the token (invalidates any device still using the old one). */
    public function regenerateSensorToken(int $spaceId): string
    {
        $tok = 'sx_'.bin2hex(random_bytes(20));
        DB::table('ahg_exhibition_space')->where('id', $spaceId)->update(['sensor_token' => $tok, 'updated_at' => now()]);

        return $tok;
    }

    /**
     * heratio#1188 - ingest readings from a real sensor/gateway authenticated by token.
     * Records each reading and raises a conservation alert when one is out of range.
     * $readings: [['metric'=>'temp_c','value'=>27.4], ...]. Returns a summary or null if the
     * token is unknown.
     */
    public function ingestSensor(string $token, array $readings): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $space = DB::table('ahg_exhibition_space')->where('sensor_token', $token)->first();
        if (! $space) {
            return null;
        }

        $recorded = 0;
        $alerts = [];
        foreach ($readings as $r) {
            $metric = isset($r['metric']) ? substr((string) $r['metric'], 0, 32) : '';
            if ($metric === '' || ! isset($r['value']) || ! is_numeric($r['value'])) {
                continue;
            }
            $value = (float) $r['value'];
            $this->recordReading((int) $space->id, $metric, $value, $r['recorded_at'] ?? null);
            $recorded++;

            $breach = $this->conservationThreshold($metric, $value, $space);
            if ($breach) {
                $alertId = (int) DB::table('ahg_exhibition_alert')->insertGetId([
                    'exhibition_space_id' => (int) $space->id,
                    'metric' => $metric, 'value' => $value,
                    'threshold' => $breach['threshold'], 'severity' => $breach['severity'],
                    'message' => $breach['message'], 'created_at' => now(),
                ]);
                $this->escalateAlert($space, $metric, $breach, $alertId);
                $alerts[] = $breach['message'];
            }
        }

        return ['space' => $space->name, 'recorded' => $recorded, 'alerts' => $alerts];
    }

    /**
     * heratio#1188 - escalate a threshold breach to staff (the admin notification bell),
     * throttled per space + metric so a sensor breaching every reading doesn't spam. Default
     * window 60 min; overridable via the `conservation_alert_throttle_min` setting.
     */
    private function escalateAlert(object $space, string $metric, array $breach, int $alertId): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('ahg_exhibition_alert', 'notified_at')) {
                return;
            }
            $windowMin = 60;
            try {
                $cfg = (int) DB::table('ahg_settings')->where('key', 'conservation_alert_throttle_min')->value('value');
                if ($cfg > 0) { $windowMin = $cfg; }
            } catch (\Throwable $e) { /* setting optional */ }

            // Already escalated for this space+metric inside the window? -> skip (throttle).
            $recent = DB::table('ahg_exhibition_alert')
                ->where('exhibition_space_id', (int) $space->id)->where('metric', $metric)
                ->whereNotNull('notified_at')
                ->where('notified_at', '>=', now()->subMinutes($windowMin))
                ->exists();
            if ($recent) {
                return;
            }

            $link = null;
            try { $link = route('exhibition-space.analytics', ['slug' => $space->slug]); } catch (\Throwable $e) {}
            app(\AhgCore\Services\NotificationService::class)->notifyAdmins(
                'conservation',
                'Conservation alert: '.$space->name,
                $breach['message'].' ('.strtoupper((string) $breach['severity']).')',
                $link,
                'exhibition_space',
                (int) $space->id
            );
            DB::table('ahg_exhibition_alert')->where('id', $alertId)->update(['notified_at' => now()]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ahg-exhibition] conservation alert escalation failed: '.$e->getMessage());
        }
    }

    /**
     * Conservation thresholds. Sensible museum defaults (per-space lux target honoured when
     * set). Returns ['severity','threshold','message'] when breached, else null.
     */
    private function conservationThreshold(string $metric, float $value, object $space): ?array
    {
        $name = $space->name ?? ('#'.($space->id ?? '?'));
        switch ($metric) {
            case 'temp_c':
                if ($value < 16 || $value > 24) {
                    return ['severity' => $value < 10 || $value > 28 ? 'critical' : 'warning', 'threshold' => '16-24 C',
                        'message' => "Temperature {$value} C in {$name} is outside the 16-24 C range."];
                }
                break;
            case 'humidity':
                if ($value < 40 || $value > 60) {
                    return ['severity' => $value < 30 || $value > 70 ? 'critical' : 'warning', 'threshold' => '40-60% RH',
                        'message' => "Humidity {$value}% in {$name} is outside the 40-60% range."];
                }
                break;
            case 'lux':
                $cap = isset($space->lighting_lux) && $space->lighting_lux ? (float) $space->lighting_lux : 200.0;
                if ($value > $cap) {
                    return ['severity' => $value > $cap * 2 ? 'critical' : 'warning', 'threshold' => '<= '.((int) $cap).' lux',
                        'message' => "Light {$value} lux in {$name} exceeds the ".((int) $cap)." lux limit."];
                }
                break;
        }

        return null;
    }

    /** heratio#1188 - recent conservation alerts for a space. */
    public function recentAlerts(object $space, int $limit = 20): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_alert')) {
            return [];
        }

        return DB::table('ahg_exhibition_alert')->where('exhibition_space_id', $space->id)
            ->orderByDesc('id')->limit($limit)->get()
            ->map(function ($a) {
                return ['metric' => $a->metric, 'value' => (float) $a->value, 'severity' => $a->severity,
                    'threshold' => $a->threshold, 'message' => $a->message, 'at' => $a->created_at];
            })->all();
    }

    /**
     * Latest value per metric for a space: ['lux'=>['value'=>..,'at'=>..], ...].
     *
     * @return array<string,array{value:float,at:string}>
     */
    public function latestReadings(int $spaceId): array
    {
        $rows = DB::table('ahg_exhibition_reading')
            ->where('exhibition_space_id', $spaceId)
            ->whereIn('id', function ($q) use ($spaceId) {
                $q->from('ahg_exhibition_reading')->selectRaw('MAX(id)')
                    ->where('exhibition_space_id', $spaceId)->groupBy('metric');
            })->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->metric] = ['value' => (float) $r->value, 'at' => (string) $r->recorded_at];
        }

        return $out;
    }

    /**
     * Conservation status from readings vs targets (international museum norms:
     * temp 16-24C, RH 40-60%, light vs the space's lux target). Worst metric wins.
     *
     * @return array{status:string,reasons:array<int,string>}
     */
    /**
     * heratio#1189 - conservation time-scrubber. For each building room and each daily bucket
     * (history + a flat forward projection), the conservation status (green/amber/red/none)
     * from the readings as-of that day. Drives a scrubbable plan on the forecast page.
     */
    public function conservationTimeline(object $space, int $days = 21, int $forecastDays = 10): array
    {
        $building = $this->getWalkthroughBuilding($space);
        $roomIds = array_map(fn ($r) => $r['id'], $building['rooms']);
        $byRoom = [];
        if ($roomIds && \Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_reading')) {
            $since = now()->subDays($days)->startOfDay();
            $rows = DB::table('ahg_exhibition_reading')->whereIn('exhibition_space_id', $roomIds)
                ->where('recorded_at', '>=', $since)->orderBy('recorded_at')
                ->select('exhibition_space_id', 'metric', 'value', 'recorded_at')->get();
            foreach ($rows as $r) {
                $byRoom[(int) $r->exhibition_space_id][$r->metric][] = [strtotime((string) $r->recorded_at), (float) $r->value];
            }
        }

        $buckets = [];
        for ($d = -$days; $d <= $forecastDays; $d++) {
            $t = now()->addDays($d)->endOfDay();
            $buckets[] = ['ts' => $t->timestamp, 'label' => $t->format('d M'), 'future' => $d > 0];
        }
        $nowTs = now()->timestamp;

        $status = [];
        foreach ($building['rooms'] as $rm) {
            $series = [];
            $ms = $byRoom[$rm['id']] ?? [];
            foreach ($buckets as $b) {
                // Past/now: actual readings as of the bucket. Future: project the recent trend
                // forward (#1189 forecast overlay) so the scrubber shows degradation/recovery,
                // not a flat carry of the current status.
                $readings = $b['future'] ? $this->projectReadings($ms, $b['ts']) : $this->readingsAsOf($ms, $b['ts']);
                $series[] = $readings ? $this->statusFromReadings($readings) : 'none';
            }
            $status[$rm['id']] = $series;
        }

        return [
            'rooms' => array_map(fn ($r) => ['id' => $r['id'], 'name' => $r['name'], 'x' => $r['x_offset'], 'z' => $r['z_offset'], 'w' => $r['w'], 'd' => $r['d']], $building['rooms']),
            'buckets' => $buckets, 'status' => $status,
            'min_x' => $building['min_x'] ?? 0, 'max_x' => $building['max_x'] ?? 0,
            'min_z' => $building['min_z'] ?? 0, 'max_z' => $building['max_z'] ?? 0,
        ];
    }

    /** Latest value per metric at or before $ts, from ascending [ts,value] series. */
    /**
     * heratio#1189 - forecast overlay: project each metric's recent trend to a future timestamp
     * (least-squares slope over the window), so the time-scrubber's future buckets show where
     * conditions are heading. Falls back to the last value with <2 points; clamps to plausible
     * physical bounds so a steep short-term slope can't extrapolate to absurd values.
     *
     * @param  array<string,array<int,array{0:int,1:float}>>  $metricSeries  metric => [[ts,value],...]
     * @return array<string,float>  metric => projected value
     */
    private function projectReadings(array $metricSeries, int $futureTs): array
    {
        $bounds = ['lux' => [0, 100000], 'temp_c' => [-20, 60], 'humidity' => [0, 100], 'visitors' => [0, 100000]];
        $out = [];
        foreach ($metricSeries as $metric => $pairs) {
            $n = count($pairs);
            if ($n === 0) {
                continue;
            }
            if ($n < 2) {
                $out[$metric] = (float) $pairs[0][1];

                continue;
            }
            // Least-squares value ~ a + b*x, x in days from the first point (keeps magnitudes sane).
            $t0 = $pairs[0][0];
            $sx = $sy = $sxx = $sxy = 0.0;
            foreach ($pairs as $p) {
                $x = ($p[0] - $t0) / 86400;
                $y = (float) $p[1];
                $sx += $x; $sy += $y; $sxx += $x * $x; $sxy += $x * $y;
            }
            $den = $n * $sxx - $sx * $sx;
            $last = (float) end($pairs)[1];
            if (abs($den) < 1e-9) {
                $out[$metric] = $last;

                continue;
            }
            $b = ($n * $sxy - $sx * $sy) / $den;
            $a = ($sy - $b * $sx) / $n;
            $val = $a + $b * (($futureTs - $t0) / 86400);
            if (isset($bounds[$metric])) {
                $val = max($bounds[$metric][0], min($bounds[$metric][1], $val));
            }
            $out[$metric] = $val;
        }

        return $out;
    }

    private function readingsAsOf(array $metricSeries, int $ts): array
    {
        $out = [];
        foreach ($metricSeries as $metric => $pairs) {
            $v = null;
            foreach ($pairs as $p) {
                if ($p[0] <= $ts) { $v = $p[1]; } else { break; }
            }
            if ($v !== null) { $out[$metric] = $v; }
        }

        return $out;
    }

    /** green/amber/red from metric values (museum norms; same thresholds as the live overlay). */
    private function statusFromReadings(array $r): string
    {
        $level = 0;
        if (isset($r['lux'])) { $level = max($level, $r['lux'] > 300 ? 2 : ($r['lux'] > 200 ? 1 : 0)); }
        if (isset($r['temp_c'])) { $t = $r['temp_c']; $level = max($level, ($t < 14 || $t > 26) ? 2 : (($t < 16 || $t > 24) ? 1 : 0)); }
        if (isset($r['humidity'])) { $h = $r['humidity']; $level = max($level, ($h < 35 || $h > 65) ? 2 : (($h < 40 || $h > 60) ? 1 : 0)); }

        return ['green', 'amber', 'red'][$level];
    }

    public function conservationStatus(object $space, array $readings): array
    {
        $level = 0;
        $reasons = [];
        $bump = function (int $l, string $msg) use (&$level, &$reasons) { $level = max($level, $l); $reasons[] = $msg; };
        if (isset($readings['lux'])) {
            $lux = $readings['lux']['value'];
            $target = $space->lighting_lux_target !== null ? (float) $space->lighting_lux_target : 200.0;
            if ($lux > $target * 1.5) {
                $bump(2, 'Light '.round($lux).' lux well above target '.round($target));
            } elseif ($lux > $target) {
                $bump(1, 'Light '.round($lux).' lux above target '.round($target));
            }
        }
        if (isset($readings['temp_c'])) {
            $t = $readings['temp_c']['value'];
            if ($t < 14 || $t > 26) {
                $bump(2, 'Temperature '.$t.'C out of safe range');
            } elseif ($t < 16 || $t > 24) {
                $bump(1, 'Temperature '.$t.'C outside ideal 16-24C');
            }
        }
        if (isset($readings['humidity'])) {
            $h = $readings['humidity']['value'];
            if ($h < 35 || $h > 65) {
                $bump(2, 'Humidity '.$h.'% out of safe range');
            } elseif ($h < 40 || $h > 60) {
                $bump(1, 'Humidity '.$h.'% outside ideal 40-60%');
            }
        }

        return ['status' => $level === 2 ? 'alert' : ($level === 1 ? 'warn' : 'ok'), 'reasons' => $reasons];
    }

    /** Combined live state for one room (readings + conservation status). */
    public function liveState(object $space): array
    {
        $readings = $this->latestReadings((int) $space->id);
        $cs = $this->conservationStatus($space, $readings);

        return [
            'readings' => $readings,
            'status' => empty($readings) ? 'none' : $cs['status'],
            'reasons' => $cs['reasons'],
            'lux_target' => $space->lighting_lux_target !== null ? (float) $space->lighting_lux_target : null,
        ];
    }

    // -------- Simulation & prediction (heratio#1147) --------

    /** Annual light-dose budget (lux-hours) by material sensitivity, inferred from the lux target. */
    public function lightBudget(?float $luxTarget): float
    {
        if ($luxTarget === null) {
            return 150000;
        }
        if ($luxTarget <= 50) {
            return 50000;    // very light-sensitive (textiles, works on paper, dyes)
        }
        if ($luxTarget <= 200) {
            return 150000;   // sensitive (oil/tempera, bone, ivory)
        }

        return 600000;       // durable (metal, stone, ceramic, glass)
    }

    /**
     * Conservation + occupancy forecast for one space from its readings:
     * projected annual light dose vs budget, days-to-budget, and visitor stats.
     * displayHoursPerDay defaults to 8; the open-year is ~312 days.
     *
     * @return array<string,mixed>
     */
    public function conservationForecast(object $space, float $displayHoursPerDay = 8.0): array
    {
        $displayDays = 312;
        $since = now()->subDays(30)->format('Y-m-d H:i:s');
        $lux = DB::table('ahg_exhibition_reading')->where('exhibition_space_id', $space->id)->where('metric', 'lux')->where('recorded_at', '>=', $since);
        $avgLux = $lux->avg('value');
        $avgLux = $avgLux !== null ? round((float) $avgLux, 1) : null;
        $budget = $this->lightBudget($space->lighting_lux_target !== null ? (float) $space->lighting_lux_target : null);
        $annual = $avgLux !== null ? $avgLux * $displayHoursPerDay * $displayDays : null;
        $pct = ($annual !== null && $budget > 0) ? $annual / $budget : null;
        $daysToBudget = ($avgLux !== null && $avgLux > 0) ? (int) round($budget / ($avgLux * $displayHoursPerDay)) : null;
        $risk = $pct === null ? 'none' : ($pct > 1.5 ? 'alert' : ($pct > 1.0 ? 'warn' : 'ok'));

        $vis = DB::table('ahg_exhibition_reading')->where('exhibition_space_id', $space->id)->where('metric', 'visitors')->where('recorded_at', '>=', $since);
        $avgVis = $vis->avg('value');
        $peakVis = $vis->max('value');

        return [
            'id' => (int) $space->id, 'name' => $space->name,
            'avg_lux' => $avgLux,
            'lux_target' => $space->lighting_lux_target !== null ? (float) $space->lighting_lux_target : null,
            'display_hours_per_day' => $displayHoursPerDay, 'display_days' => $displayDays,
            'budget' => $budget,
            'annual_dose' => $annual !== null ? (int) round($annual) : null,
            'pct_of_budget' => $pct !== null ? round($pct * 100, 1) : null,
            'days_to_budget' => $daysToBudget,
            'risk' => $risk,
            'avg_visitors' => $avgVis !== null ? round((float) $avgVis, 1) : null,
            'peak_visitors' => $peakVis !== null ? (float) $peakVis : null,
            'capacity' => $space->capacity_value !== null ? (float) $space->capacity_value : null,
        ];
    }

    /**
     * Historical analytics for the building (heratio#1148): readings bucketed by
     * hour (<=7 days) or day (longer), one aligned series per room per metric,
     * plus per-room/metric summaries. Shaped for charting on a category axis.
     *
     * @return array<string,mixed>
     */
    public function buildingAnalytics(object $space, int $days = 7): array
    {
        $days = max(1, min(365, $days));
        $ids = $this->buildingSpaceIds($space);
        $metrics = ['lux', 'temp_c', 'humidity', 'visitors'];
        $rooms = [];
        foreach ($ids as $id) {
            $sp = $this->getById($id);
            if ($sp) {
                $rooms[] = ['id' => (int) $id, 'name' => $sp->name];
            }
        }
        $byDay = $days > 7;
        $bucketExpr = $byDay ? "DATE_FORMAT(recorded_at,'%Y-%m-%d')" : "DATE_FORMAT(recorded_at,'%Y-%m-%d %H:00')";
        $since = now()->subDays($days)->format('Y-m-d H:i:s');
        $rows = empty($ids) ? collect() : DB::table('ahg_exhibition_reading')
            ->whereIn('exhibition_space_id', $ids)->where('recorded_at', '>=', $since)
            ->selectRaw("$bucketExpr as bucket, exhibition_space_id as sid, metric, AVG(value) as avgv, MIN(value) as minv, MAX(value) as maxv, COUNT(*) as cnt")
            ->groupBy('bucket', 'sid', 'metric')->orderBy('bucket')->get();

        $labelsSet = [];
        $map = [];        // map[metric][sid][bucket] = avg
        $agg = [];        // agg[sid][metric] = [sum,count,min,max]
        foreach ($rows as $r) {
            $labelsSet[$r->bucket] = true;
            $map[$r->metric][$r->sid][$r->bucket] = round((float) $r->avgv, 2);
            $a = $agg[$r->sid][$r->metric] ?? ['sum' => 0, 'n' => 0, 'min' => INF, 'max' => -INF];
            $a['sum'] += (float) $r->avgv * (int) $r->cnt;
            $a['n'] += (int) $r->cnt;
            $a['min'] = min($a['min'], (float) $r->minv);
            $a['max'] = max($a['max'], (float) $r->maxv);
            $agg[$r->sid][$r->metric] = $a;
        }
        $labels = array_keys($labelsSet);
        sort($labels);

        $series = [];
        foreach ($metrics as $m) {
            foreach ($rooms as $rm) {
                $sid = $rm['id'];
                $line = [];
                foreach ($labels as $b) {
                    $line[] = $map[$m][$sid][$b] ?? null;
                }
                $series[$m][$sid] = $line;
            }
        }
        $summary = [];
        foreach ($rooms as $rm) {
            $sid = $rm['id'];
            $latest = $this->latestReadings($sid);
            foreach ($metrics as $m) {
                $a = $agg[$sid][$m] ?? null;
                $summary[$sid][$m] = [
                    'avg' => $a && $a['n'] ? round($a['sum'] / $a['n'], 1) : null,
                    'min' => $a && $a['n'] ? round($a['min'], 1) : null,
                    'max' => $a && $a['n'] ? round($a['max'], 1) : null,
                    'latest' => isset($latest[$m]) ? $latest[$m]['value'] : null,
                    'count' => $a['n'] ?? 0,
                ];
            }
        }

        return ['days' => $days, 'bucket' => $byDay ? 'day' : 'hour', 'labels' => $labels, 'metrics' => $metrics, 'rooms' => $rooms, 'series' => $series, 'summary' => $summary];
    }

    /** Conservation forecast for every room in the building. @return array<int,array<string,mixed>> */
    public function buildingForecast(object $space): array
    {
        $ids = $this->buildingSpaceIds($space);
        $out = [];
        foreach ($ids as $id) {
            $sp = $this->getById($id);
            if ($sp) {
                $out[] = $this->conservationForecast($sp);
            }
        }

        return $out;
    }

    // -------- In-twin recommendations (heratio#1149) --------

    /** Meaningful lowercase tokens from a title (for content similarity). */
    private function titleTokens(string $s): array
    {
        $stop = ['the', 'and', 'for', 'with', 'from', 'a', 'an', 'of', 'in', 'on', 'to', 'no', 'by', 'at', 'is', 'as', 'or'];
        $s = strtolower(preg_replace('/[^a-z0-9 ]+/i', ' ', $s));
        $out = [];
        foreach (preg_split('/\s+/', $s) as $w) {
            if (strlen($w) >= 3 && ! in_array($w, $stop, true)) {
                $out[$w] = true;
            }
        }

        return array_keys($out);
    }

    /** Placements across the building with title + room + position (recommendation candidates). */
    private function buildingPlacementRows(object $space): array
    {
        $ids = $this->buildingSpaceIds($space);
        if (empty($ids)) {
            return [];
        }

        return DB::table('ahg_exhibition_placement as ep')
            ->join('ahg_exhibition_space as sp', 'sp.id', '=', 'ep.exhibition_space_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->whereIn('ep.exhibition_space_id', $ids)
            ->where(function ($q) { $q->whereNull('ep.wall_or_zone')->orWhere('ep.wall_or_zone', '!=', 'corridor'); })
            ->select('ep.id as placement_id', 'ep.information_object_id as io_id', 'ep.pos_x', 'ep.pos_y',
                'sp.id as room_id', 'sp.name as room_name', 'ioi.title as title')
            ->get()->map(function ($r) {
                return ['placement_id' => (int) $r->placement_id, 'io_id' => (int) $r->io_id,
                    'title' => $r->title ?: ('#'.$r->io_id), 'room_id' => (int) $r->room_id, 'room_name' => $r->room_name,
                    'pos_x' => $r->pos_x !== null ? (float) $r->pos_x : 0.5, 'pos_y' => $r->pos_y !== null ? (float) $r->pos_y : 0.5];
            })->all();
    }

    /** All AI/related suggestions stored across THIS room's placed objects (deduped). #1149 picker. */
    public function roomRecommendations(object $space, int $limit = 80): array
    {
        if (! $this->recsColumn()) {
            return [];
        }
        $byIo = [];
        foreach ($this->buildingPlacementRows($space) as $r) {
            $byIo[$r['io_id']] = $r;
        }
        $rows = DB::table('ahg_exhibition_placement as ep')
            ->leftJoin('information_object_i18n as ioi', function ($j) { $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en'); })
            ->whereIn('ep.exhibition_space_id', $this->buildingSpaceIds($space))->whereNotNull('ep.recommendations_json')
            ->select('ep.information_object_id as src_io', 'ep.recommendations_json as recs', 'ioi.title as src_title')->get();
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $recs = json_decode((string) $row->recs, true);
            if (! is_array($recs)) {
                continue;
            }
            foreach ($recs as $rec) {
                $rid = (int) ($rec['io_id'] ?? 0);
                if ($rid <= 0 || isset($seen[$rid])) {
                    continue;
                }
                $seen[$rid] = 1;
                $info = $byIo[$rid] ?? null;
                $out[] = [
                    'io_id' => $rid,
                    'title' => $info['title'] ?? ('#'.$rid),
                    'reason' => (string) ($rec['reason'] ?? ''),
                    'source' => $row->src_title ?: ('#'.$row->src_io),
                    'in_building' => $info !== null,
                    'thumb_url' => $this->thumbnailUrl($rid),
                ];
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /** Building-wide distinct objects (io_id + "title (room)") for the guided-tour picker. */
    public function buildingTourObjects(object $space): array
    {
        $seen = [];
        $out = [];
        foreach ($this->buildingPlacementRows($space) as $r) {
            if (isset($seen[$r['io_id']])) {
                continue;
            }
            $seen[$r['io_id']] = 1;
            $out[] = ['io_id' => $r['io_id'], 'title' => $r['title'].' ('.$r['room_name'].')', 'room_id' => $r['room_id'], 'room_name' => $r['room_name']];
        }

        return $out;
    }

    /**
     * heratio#1194 - ordered stops for the accessible (text + narration) tour: each placed
     * object once, in room order, with its description, a thumbnail (+ alt text) and a spoken
     * narration string. This is the screen-reader / keyboard / low-vision alternative to the
     * 3D walkthrough.
     *
     * @return array<int,array<string,mixed>>
     */
    public function accessibleTour(object $space): array
    {
        $ids = $this->buildingSpaceIds($space);
        if (empty($ids)) {
            return [];
        }

        $rows = DB::table('ahg_exhibition_placement as ep')
            ->join('ahg_exhibition_space as sp', 'sp.id', '=', 'ep.exhibition_space_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'ep.information_object_id')
            ->whereIn('ep.exhibition_space_id', $ids)
            ->whereNotNull('ep.information_object_id')
            ->where(function ($q) { $q->whereNull('ep.wall_or_zone')->orWhere('ep.wall_or_zone', '!=', 'corridor'); })
            ->orderBy('sp.building_seq')->orderBy('sp.id')->orderBy('ep.id')
            ->select('ep.information_object_id as io_id', 'ioi.title', 'ioi.scope_and_content', 'sp.name as room_name', 'sl.slug')
            ->get();

        $seen = [];
        $out = [];
        $n = 0;
        foreach ($rows as $r) {
            $io = (int) $r->io_id;
            if (isset($seen[$io])) {
                continue;
            }
            $seen[$io] = 1;
            $n++;
            $title = $r->title ?: ('#'.$io);
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($r->scope_and_content ?? ''))));
            $room = (string) $r->room_name;
            $narration = "Stop {$n}".($room !== '' ? ", in {$room}" : '').'. '.$title.'. '
                .($desc !== '' ? $desc : 'No description is recorded for this object.');
            $out[] = [
                'io_id' => $io, 'title' => $title, 'room' => $room, 'slug' => $r->slug,
                'description' => $desc, 'thumb_url' => $this->thumbnailUrl($io),
                'narration' => $narration,
            ];
        }

        return $out;
    }

    private function recsColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = \Illuminate\Support\Facades\Schema::hasColumn('ahg_exhibition_placement', 'recommendations_json');
        }

        return $has;
    }

    /**
     * Content-based related objects within the building (title-token similarity).
     * Prefers AI-precomputed recommendations when present. Always returns rows
     * (falls back to other building objects when nothing scores).
     *
     * @return array<int,array<string,mixed>>
     */
    public function recommendations(object $space, int $ioId, int $limit = 4): array
    {
        $rows = $this->buildingPlacementRows($space);
        if (empty($rows)) {
            return [];
        }
        $byIo = [];
        foreach ($rows as $r) {
            $byIo[$r['io_id']] = $r;
        }

        // 1) AI-precomputed recommendations for this object, if stored.
        if ($this->recsColumn()) {
            $src = DB::table('ahg_exhibition_placement')->whereIn('exhibition_space_id', $this->buildingSpaceIds($space))
                ->where('information_object_id', $ioId)->whereNotNull('recommendations_json')->value('recommendations_json');
            if ($src) {
                $recs = json_decode((string) $src, true);
                if (is_array($recs) && ! empty($recs)) {
                    $out = [];
                    foreach ($recs as $rec) {
                        $rid = (int) ($rec['io_id'] ?? 0);
                        if (isset($byIo[$rid]) && $rid !== $ioId) {
                            $out[] = $byIo[$rid] + ['reason' => (string) ($rec['reason'] ?? ''), 'ai' => true];
                        }
                        if (count($out) >= $limit) {
                            break;
                        }
                    }
                    if (! empty($out)) {
                        return $out;
                    }
                }
            }
        }

        // 2) Content-based: title-token Jaccard.
        $target = $byIo[$ioId] ?? null;
        if (! $target) {
            return [];
        }
        $tt = $this->titleTokens($target['title']);
        $scored = [];
        foreach ($rows as $r) {
            if ($r['io_id'] === $ioId) {
                continue;
            }
            $ct = $this->titleTokens($r['title']);
            $common = array_intersect($tt, $ct);
            $union = count(array_unique(array_merge($tt, $ct)));
            $score = $union > 0 ? count($common) / $union : 0;
            if ($score > 0) {
                $scored[] = $r + ['reason' => 'Shares: '.implode(', ', array_slice($common, 0, 4)), 'ai' => false, '_s' => $score];
            }
        }
        usort($scored, fn ($a, $b) => $b['_s'] <=> $a['_s']);
        $out = array_slice($scored, 0, $limit);
        // 3) Fallback: a couple of other objects so there is always something to discover.
        if (empty($out)) {
            foreach ($rows as $r) {
                if ($r['io_id'] !== $ioId) {
                    $out[] = $r + ['reason' => 'More in this exhibition', 'ai' => false];
                }
                if (count($out) >= 2) {
                    break;
                }
            }
        }

        return array_map(function ($r) { unset($r['_s']); return $r; }, $out);
    }

    /**
     * Precompute AI recommendations per object via the AI gateway (LlmService).
     * Best-effort: failures leave content-based recommendations in place.
     */
    public function generateAiRecommendations(object $space): array
    {
        if (! $this->recsColumn()) {
            return ['ok' => false, 'reason' => 'recommendations column missing'];
        }
        $rows = $this->buildingPlacementRows($space);
        if (count($rows) < 2) {
            return ['ok' => true, 'updated' => 0, 'total' => count($rows)];
        }
        $llm = app(\AhgAiServices\Services\LlmService::class);
        $list = implode("\n", array_map(fn ($r) => $r['io_id'].' | '.$r['title'], array_slice($rows, 0, 60)));
        // Incremental + capped per run so the request never times out on big buildings.
        $done = DB::table('ahg_exhibition_placement')->whereIn('exhibition_space_id', $this->buildingSpaceIds($space))
            ->whereNotNull('recommendations_json')->pluck('information_object_id')->map(fn ($v) => (int) $v)->all();
        $done = array_flip($done);
        $todo = array_values(array_filter($rows, fn ($r) => ! isset($done[$r['io_id']])));
        $candidates = array_slice($todo, 0, 15);
        $updated = 0;
        foreach ($candidates as $r) {
            $prompt = "You are a museum curator suggesting what a visitor should see next.\n".
                "The visitor is viewing: \"".$r['title']."\".\n".
                "From the list below (other objects in the same exhibition), choose up to 3 the visitor would most want to see next, each with a short reason.\n".
                "Return ONLY a JSON array like [{\"io_id\":123,\"reason\":\"...\"}]. Do not include the viewed object (io ".$r['io_id'].").\n\nLIST:\n".$list;
            try {
                $resp = $llm->complete($prompt, ['max_tokens' => 300, 'temperature' => 0.3]);
            } catch (\Throwable $e) {
                $resp = null;
            }
            if (! $resp) {
                continue;
            }
            if (preg_match('/\[.*\]/s', $resp, $m)) {
                $arr = json_decode($m[0], true);
                if (is_array($arr)) {
                    $clean = [];
                    foreach ($arr as $a) {
                        $iid = (int) ($a['io_id'] ?? 0);
                        if ($iid > 0 && $iid !== $r['io_id']) {
                            $clean[] = ['io_id' => $iid, 'reason' => mb_substr(trim((string) ($a['reason'] ?? '')), 0, 160)];
                        }
                    }
                    if (! empty($clean)) {
                        DB::table('ahg_exhibition_placement')->where('id', $r['placement_id'])
                            ->update(['recommendations_json' => json_encode(array_slice($clean, 0, 3)), 'updated_at' => now()]);
                        $updated++;
                    }
                }
            }
        }

        return ['ok' => true, 'updated' => $updated, 'processed' => count($candidates), 'remaining' => max(0, count($todo) - count($candidates))];
    }

    /** Seed plausible demo readings across the building (no physical sensors yet). */
    public function simulateReadings(object $space): int
    {
        $ids = $this->buildingSpaceIds($space);
        $n = 0;
        foreach ($ids as $i => $id) {
            $sp = $this->getById($id);
            if (! $sp) {
                continue;
            }
            $target = $sp->lighting_lux_target !== null ? (float) $sp->lighting_lux_target : 200.0;
            $this->recordReading($id, 'lux', round($target * (0.6 + ($i % 3) * 0.5), 1));   // ok / warn / alert mix
            $this->recordReading($id, 'temp_c', round(19 + ($i % 4) * 2.5, 1));
            $this->recordReading($id, 'humidity', round(45 + ($i % 5) * 6, 1));
            $this->recordReading($id, 'visitors', ($i % 6) * 3);
            $n += 4;
        }

        return $n;
    }

    /** Create a corridor object (building-space) at building-fraction (fx,fy). */
    public function createCorridorPlacement(object $space, int $informationObjectId, float $fx, float $fy): array
    {
        if ($informationObjectId <= 0) {
            throw new \InvalidArgumentException('information_object_id is required.');
        }
        $now = now();
        $id = (int) DB::table('ahg_exhibition_placement')->insertGetId([
            'information_object_id' => $informationObjectId,
            'exhibition_space_id' => (int) $space->id,
            'size_units_used' => 0,
            'wall_or_zone' => 'corridor',
            'pos_x' => max(0, min(1, $fx)),
            'pos_y' => max(0, min(1, $fy)),
            'rotation_deg' => 0, 'scale' => 1, 'z_order' => 0, 'label_visible' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $title = DB::table('information_object_i18n')->where('id', $informationObjectId)->where('culture', 'en')->value('title');
        $media = $this->getObjectMedia($informationObjectId);

        return [
            'id' => $id,
            'information_object_id' => $informationObjectId,
            'title' => $title ?: ('#'.$informationObjectId),
            'pos_x' => max(0, min(1, $fx)), 'pos_y' => max(0, min(1, $fy)),
            'kind' => $media['kind'],
            'thumb_url' => $media['image_url'] ?? $this->thumbnailUrl($informationObjectId),
        ];
    }

    /** Move a corridor object to building-fraction (fx,fy) within its building. */
    public function moveCorridorPlacement(object $space, int $placementId, float $fx, float $fy): bool
    {
        $ids = $this->buildingSpaceIds($space);

        return DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)->whereIn('exhibition_space_id', $ids)->where('wall_or_zone', 'corridor')
            ->update(['pos_x' => max(0, min(1, $fx)), 'pos_y' => max(0, min(1, $fy)), 'updated_at' => now()]) > 0;
    }

    // -------- Building plan editor (#1143: floor-plan layout) --------

    /** Rooms of a building for the plan editor + the plan image (from any room that has one). */
    public function getBuildingPlan(object $space): array
    {
        $rooms = (! empty($space->building_id))
            ? DB::table('ahg_exhibition_space')->where('building_id', $space->building_id)->orderBy('building_seq')->orderBy('id')->get()->all()
            : [$space];
        $plan = null;
        $planRect = null;
        $list = [];
        $minX = $maxX = $minZ = $maxZ = null;
        foreach ($rooms as $r) {
            if (! $plan && ! empty($r->building_plan_image)) {
                $plan = $r->building_plan_image;
                if ($r->building_plan_w !== null) {
                    $planRect = ['x' => (float) $r->building_plan_x, 'y' => (float) $r->building_plan_y, 'w' => (float) $r->building_plan_w, 'h' => (float) $r->building_plan_h];
                }
            }
            $dim = $this->roomDims($r);
            $list[] = [
                'id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug,
                'w' => $dim['w'], 'd' => $dim['d'],
                'bld_x' => $r->bld_x !== null ? (float) $r->bld_x : null,
                'bld_y' => $r->bld_y !== null ? (float) $r->bld_y : null,
                'rot' => $r->bld_rot !== null ? (float) $r->bld_rot : 0.0,
                'doors' => $this->getDoors((int) $r->id),
                'windows' => $this->getWindows((int) $r->id),   // #1172 authoring
                'shape' => $this->getShape((int) $r->id),
                'group' => $r->bld_group ?? null,   // #1143: move-as-one-unit group key
                'locked' => (int) ($r->bld_locked ?? 0) === 1,   // #1143: room "done" lock
                'floor' => (int) ($r->floor_level ?? 0),   // #1169: for name-based stair linking
                'is_current' => (int) $r->id === (int) $space->id,
            ];
            if ($r->bld_x !== null && $r->bld_y !== null) {
                $x = (float) $r->bld_x;
                $z = (float) $r->bld_y;
                $minX = $minX === null ? $x : min($minX, $x);
                $maxX = $maxX === null ? $x + $dim['w'] : max($maxX, $x + $dim['w']);
                $minZ = $minZ === null ? $z : min($minZ, $z);
                $maxZ = $maxZ === null ? $z + $dim['d'] : max($maxZ, $z + $dim['d']);
            }
        }

        // World-anchor the blueprint: if an image exists but has no rect yet,
        // default it to the building extent so it lines up with the rooms.
        if ($plan && ! $planRect) {
            $planRect = $this->defaultPlanRect($space);
        }

        return [
            'rooms' => $list, 'plan_image' => $plan, 'plan_rect' => $planRect,
            'corridor' => $this->getBuildingCorridorObjects($space),
            'stairs' => $this->getStairs($space),   // #1169 plan-editor stairs authoring
            'bbox' => ['min_x' => $minX ?? 0, 'max_x' => $maxX ?? 0, 'min_z' => $minZ ?? 0, 'max_z' => $maxZ ?? 0],
        ];
    }

    /** Stairs (#1169) decoded from the space's stairs_json. */
    public function getStairs(object $space): array
    {
        $s = $space->stairs_json ?? null;
        if (is_string($s)) {
            $s = json_decode($s, true);
        }

        return is_array($s) ? array_values($s) : [];
    }

    /**
     * Persist building stairs on EVERY room of the building (so any room's
     * walkthrough sees them - getWalkthroughBuilding reads the viewed space's
     * stairs_json). Each stair: {x, z, from_floor, to_floor, width} in metres.
     *
     * @param  array<int,array<string,mixed>>  $stairs
     */
    public function saveBuildingStairs(object $space, array $stairs): void
    {
        $clean = [];
        foreach ($stairs as $st) {
            if ((int) ($st['from_floor'] ?? 0) === (int) ($st['to_floor'] ?? 1)) {
                continue;   // a staircase must link two different floors
            }
            $clean[] = [
                'x' => round((float) ($st['x'] ?? 0), 2),
                'z' => round((float) ($st['z'] ?? 0), 2),
                'from_floor' => (int) ($st['from_floor'] ?? 0),
                'to_floor' => (int) ($st['to_floor'] ?? 1),
                'from_room' => isset($st['from_room']) ? (int) $st['from_room'] : null,   // #1169 name-based linking
                'to_room' => isset($st['to_room']) ? (int) $st['to_room'] : null,
                'width' => max(0.6, min(8, (float) ($st['width'] ?? 1.6))),
                'length' => max(1.5, min(30, (float) ($st['length'] ?? 3))),    // run of the first flight (metres)
                'length2' => max(1.5, min(30, (float) ($st['length2'] ?? ($st['length'] ?? 3)))),   // second flight (elbow)
                'rot' => ((int) round((float) ($st['rot'] ?? 0)) % 360 + 360) % 360,   // overall orientation (degrees)
                'hand' => in_array(($st['hand'] ?? 'right'), ['left', 'right'], true) ? $st['hand'] : 'right',   // elbow turn direction
                'kind' => in_array(($st['kind'] ?? 'straight'), ['straight', 'elbow'], true) ? $st['kind'] : 'straight',
            ];
        }
        $json = $clean ? json_encode($clean) : null;
        $q = DB::table('ahg_exhibition_space');
        if (! empty($space->building_id)) {
            $q->where('building_id', $space->building_id);
        } else {
            $q->where('id', $space->id);
        }
        $q->update(['stairs_json' => $json, 'updated_at' => now()]);
    }

    /** Default world rect for a blueprint image = the building's extent (metres). */
    private function defaultPlanRect(object $space): array
    {
        $rooms = (! empty($space->building_id))
            ? DB::table('ahg_exhibition_space')->where('building_id', $space->building_id)->get()->all()
            : [$space];
        $maxR = 0.0;
        $maxB = 0.0;
        foreach ($rooms as $r) {
            $dim = $this->roomDims($r);
            $x = $r->bld_x !== null ? (float) $r->bld_x : 0.0;
            $y = $r->bld_y !== null ? (float) $r->bld_y : 0.0;
            $maxR = max($maxR, $x + $dim['w']);
            $maxB = max($maxB, $y + $dim['d']);
        }

        return ['x' => 0.0, 'y' => 0.0, 'w' => max(30.0, $maxR + 2), 'h' => max(22.0, $maxB + 2)];
    }

    /** Persist an adjusted blueprint world rect (applied to all building rooms). */
    public function savePlanImageRect(object $space, float $x, float $y, float $w, float $h): void
    {
        $q = DB::table('ahg_exhibition_space');
        if (! empty($space->building_id)) {
            $q->where('building_id', $space->building_id);
        } else {
            $q->where('id', $space->id);
        }
        $q->update(['building_plan_x' => $x, 'building_plan_y' => $y, 'building_plan_w' => max(1, $w), 'building_plan_h' => max(1, $h), 'updated_at' => now()]);
    }

    /**
     * Create a new room in this space's building (creating a building from the
     * space if it has none) and place it to the right of the existing rooms.
     * Returns the plan-editor row for the new room.
     *
     * @return array<string,mixed>
     */
    public function addBuildingRoom(object $space, ?string $name = null): array
    {
        $bid = $space->building_id;
        if (empty($bid)) {
            $bid = $space->slug;
            DB::table('ahg_exhibition_space')->where('id', $space->id)->update(['building_id' => $bid, 'building_seq' => 0, 'updated_at' => now()]);
        }
        $rooms = DB::table('ahg_exhibition_space')->where('building_id', $bid)->get();
        $maxSeq = 0;
        $maxRight = 0.0;
        $topY = null;
        foreach ($rooms as $r) {
            $maxSeq = max($maxSeq, (int) ($r->building_seq ?? 0));
            $dim = $this->roomDims($r);
            if ($r->bld_x !== null && $r->bld_y !== null) {
                $maxRight = max($maxRight, (float) $r->bld_x + $dim['w']);
                $topY = $topY === null ? (float) $r->bld_y : min($topY, (float) $r->bld_y);
            }
        }
        $x = $maxRight > 0 ? $maxRight + 1 : 1.0;
        $y = $topY ?? 1.0;
        $nm = ($name !== null && trim($name) !== '') ? trim($name) : 'New Room';
        $id = (int) DB::table('ahg_exhibition_space')->insertGetId([
            'slug' => $this->generateUniqueSlug($nm),
            'name' => $nm,
            'space_type' => 'gallery',
            'capacity_unit' => 'linear_wall_meters',
            'building_id' => $bid,
            'building_seq' => $maxSeq + 1,
            'room_w' => 10, 'room_d' => 8, 'room_h' => 4,
            'bld_x' => $x, 'bld_y' => $y,
            // #1176: every room is a numbered-edge polygon. A new room starts as a unit rectangle
            // (4 walls = edge 0..3) so the whole stack (plan/builder/walkthrough) treats it the same.
            'shape_json' => json_encode([['x' => 0, 'z' => 0], ['x' => 1, 'z' => 0], ['x' => 1, 'z' => 1], ['x' => 0, 'z' => 1]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'id' => $id, 'name' => $nm, 'slug' => $this->getById($id)->slug,
            'w' => 10.0, 'd' => 8.0, 'bld_x' => (float) $x, 'bld_y' => (float) $y, 'rot' => 0.0,
            'doors' => [], 'shape' => [['x' => 0, 'z' => 0], ['x' => 1, 'z' => 0], ['x' => 1, 'z' => 1], ['x' => 0, 'z' => 1]], 'group' => null, 'is_current' => false,
        ];
    }

    /**
     * Set the plan group key on rooms (#1143). Rooms sharing a key move as one
     * unit in the editor and 3D walkthrough. A null key ungroups. Only rooms in
     * this space's building are touched.
     *
     * @param  array<int,array{room_id:int,group:?string}>  $groups
     */
    public function setRoomGroups(object $space, array $groups): int
    {
        $ids = $this->buildingSpaceIds($space);
        $n = 0;
        foreach ($groups as $g) {
            $rid = (int) ($g['room_id'] ?? 0);
            if ($rid <= 0 || ! in_array($rid, $ids, true)) {
                continue;
            }
            $key = (isset($g['group']) && $g['group'] !== '' && $g['group'] !== null) ? substr((string) $g['group'], 0, 40) : null;
            $n += DB::table('ahg_exhibition_space')->where('id', $rid)->update(['bld_group' => $key, 'updated_at' => now()]);
        }

        return $n;
    }

    /** Delete a room from the building (and its placements). Won't delete the room you're editing from. */
    public function deleteBuildingRoom(object $space, int $roomId): bool
    {
        $ids = $this->buildingSpaceIds($space);
        if (! in_array($roomId, $ids, true) || $roomId === (int) $space->id) {
            return false;
        }
        DB::table('ahg_exhibition_placement')->where('exhibition_space_id', $roomId)->delete();

        return DB::table('ahg_exhibition_space')->where('id', $roomId)->delete() > 0;
    }

    /** Set which floor a room sits on (#1169 multi-floor). 0 = ground. */
    public function setRoomLocked(object $space, int $roomId, bool $locked): bool
    {
        $ids = $this->buildingSpaceIds($space);
        if (! in_array($roomId, $ids, true)) {
            return false;
        }

        return DB::table('ahg_exhibition_space')->where('id', $roomId)
            ->update(['bld_locked' => $locked ? 1 : 0, 'updated_at' => now()]) > 0;
    }

    public function setRoomFloor(object $space, int $roomId, int $floor): bool
    {
        $ids = $this->buildingSpaceIds($space);
        if (! in_array($roomId, $ids, true)) {
            return false;
        }

        return DB::table('ahg_exhibition_space')->where('id', $roomId)
            ->update(['floor_level' => max(-5, min(20, $floor)), 'updated_at' => now()]) > 0;   // negative = basement
    }

    /** Save a room's plan position + size (metres) + rotation (degrees). */
    public function savePlanRoom(int $buildingMemberId, int $roomId, float $x, float $y, ?float $w, ?float $d, ?float $rot = null): bool
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
        if ($rot !== null) {
            $p['bld_rot'] = fmod($rot, 360);
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
        $payload = ['building_plan_image' => $publicPath, 'updated_at' => now()];
        if ($publicPath === null) {   // clearing: drop the world rect too
            $payload['building_plan_x'] = null;
            $payload['building_plan_y'] = null;
            $payload['building_plan_w'] = null;
            $payload['building_plan_h'] = null;
        } else {                       // new image: seed a default world rect = building extent
            $rect = $this->defaultPlanRect($space);
            $payload['building_plan_x'] = $rect['x'];
            $payload['building_plan_y'] = $rect['y'];
            $payload['building_plan_w'] = $rect['w'];
            $payload['building_plan_h'] = $rect['h'];
        }
        $q->update($payload);
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

    /** Set or clear the room wall (painted/decorated) image - the ALL-WALLS default. */
    public function setWallImage(int $exhibitionSpaceId, ?string $publicPath): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['wall_image_path' => $publicPath, 'updated_at' => now()]);
    }

    /** Set or clear the decorative floor picture (stretched over the whole room floor). */
    public function setFloorImage(int $exhibitionSpaceId, ?string $publicPath): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['floor_image_path' => $publicPath, 'updated_at' => now()]);
    }

    /** heratio#1156: set (or clear, with null) the photoreal capture shell for a room. */
    public function setScanShell(int $exhibitionSpaceId, ?string $publicPath): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['scan_shell_path' => $publicPath, 'updated_at' => now()]);
    }

    /** heratio#1156: fit-scale for the scan shell + the 360/Matterport embed URL. */
    public function setScanMeta(int $exhibitionSpaceId, ?float $scale, ?string $embedUrl): void
    {
        $patch = ['updated_at' => now()];
        if ($scale !== null) {
            $patch['scan_shell_scale'] = max(0.001, min(1000, $scale));
        }
        // embedUrl is set verbatim (empty string clears it); only http(s) URLs are honoured at render time.
        $patch['scan_embed_url'] = ($embedUrl !== null && trim($embedUrl) !== '') ? trim($embedUrl) : null;
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)->update($patch);
    }

    /** Set the floor tiling for a room: grout-grid on/off + tile size (m, 0.25-10) + grout width (mm, 0.5-100). */
    public function setFloorTiling(int $exhibitionSpaceId, bool $grout, ?float $tileM = null, ?float $groutMm = null): void
    {
        $upd = ['floor_grout' => $grout ? 1 : 0, 'updated_at' => now()];
        if ($tileM !== null) {
            $upd['floor_tile_m'] = max(0.25, min(10.0, $tileM));
        }
        if ($groutMm !== null) {
            $upd['floor_grout_mm'] = max(0.5, min(100.0, $groutMm));
        }
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)->update($upd);
    }

    /** Per-edge wall images: {edgeIndex: publicPath}. Falls back to the all-walls default per wall. */
    public function getWallImages(int $exhibitionSpaceId): array
    {
        $space = $this->getById($exhibitionSpaceId);
        if (! $space || empty($space->wall_images_json)) {
            return [];
        }
        $m = json_decode((string) $space->wall_images_json, true);

        return is_array($m) ? $m : [];
    }

    /** Set or clear the wall image for a single edge (null path removes the override). */
    public function setWallImageForEdge(int $exhibitionSpaceId, int $edge, ?string $publicPath): void
    {
        $map = $this->getWallImages($exhibitionSpaceId);
        if ($publicPath === null) {
            unset($map[(string) $edge]);
        } else {
            $map[(string) $edge] = $publicPath;
        }
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['wall_images_json' => empty($map) ? null : json_encode($map), 'updated_at' => now()]);
    }

    /** Set or clear the all-walls default paint colour (#hex, or null to revert to plaster/image). */
    public function setWallColor(int $exhibitionSpaceId, ?string $hex): void
    {
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['wall_color' => $hex, 'updated_at' => now()]);
    }

    /** Per-edge wall paint colours: {edgeIndex: '#hex'}. Falls back to the all-walls colour per wall. */
    public function getWallColors(int $exhibitionSpaceId): array
    {
        $space = $this->getById($exhibitionSpaceId);
        if (! $space || empty($space->wall_colors_json)) {
            return [];
        }
        $m = json_decode((string) $space->wall_colors_json, true);

        return is_array($m) ? $m : [];
    }

    /** Set or clear the paint colour for a single edge (null removes the override). */
    public function setWallColorForEdge(int $exhibitionSpaceId, int $edge, ?string $hex): void
    {
        $map = $this->getWallColors($exhibitionSpaceId);
        if ($hex === null) {
            unset($map[(string) $edge]);
        } else {
            $map[(string) $edge] = $hex;
        }
        DB::table('ahg_exhibition_space')->where('id', $exhibitionSpaceId)
            ->update(['wall_colors_json' => empty($map) ? null : json_encode($map), 'updated_at' => now()]);
    }

    // -------- Furniture & fittings (placeable props per room) --------
    public const FURNITURE_KINDS = ['bench', 'pedestal', 'case', 'planter', 'table', 'chair', 'railing', 'pillar-round', 'pillar-square', 'person-man', 'person-woman', 'arch'];

    /** Furniture placed in a room: [{id,kind,pos_x,pos_y,rotation_deg,scale}] (positions are 0-1 of the room). */
    public function getFurniture(int $exhibitionSpaceId): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_furniture')) {
            return [];
        }

        return DB::table('ahg_exhibition_furniture')->where('exhibition_space_id', $exhibitionSpaceId)
            ->orderBy('id')->get()->map(function ($r) {
                $poles = (! empty($r->pole_json) && is_array($pj = json_decode((string) $r->pole_json, true))) ? $pj : null;
                // Uploaded-asset furniture shows its filename (not the generic "asset"): strip dir, the upload hash and extension.
                $label = null;
                if (! empty($r->asset_path)) {
                    $base = pathinfo((string) $r->asset_path, PATHINFO_FILENAME);
                    $base = preg_replace('/-[0-9a-f]{8}$/i', '', $base);
                    $label = ucfirst(trim(str_replace(['-', '_'], ' ', $base)));
                }

                return ['id' => (int) $r->id, 'kind' => $r->kind, 'pos_x' => (float) $r->pos_x, 'pos_y' => (float) $r->pos_y, 'rotation_deg' => (float) $r->rotation_deg, 'scale' => (float) $r->scale, 'segments' => (int) ($r->segments ?? 2), 'poles' => $poles, 'asset_path' => $r->asset_path ?? null, 'asset_ext' => $r->asset_ext ?? null, 'label' => $label];
            })->all();
    }

    public function addFurniture(int $exhibitionSpaceId, string $kind, float $fx, float $fy, ?string $assetPath = null, ?string $assetExt = null): array
    {
        // Uploaded-asset furniture uses kind='asset'; procedural pieces must be a known kind.
        if ($assetPath === null && ! in_array($kind, self::FURNITURE_KINDS, true)) {
            $kind = 'pedestal';
        }
        $fx = max(0.0, min(1.0, $fx)); $fy = max(0.0, min(1.0, $fy));
        $id = (int) DB::table('ahg_exhibition_furniture')->insertGetId([
            'exhibition_space_id' => $exhibitionSpaceId, 'kind' => $kind,
            'pos_x' => $fx, 'pos_y' => $fy, 'rotation_deg' => 0, 'scale' => 1, 'segments' => 2,
            'asset_path' => $assetPath, 'asset_ext' => $assetExt,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'kind' => $kind, 'pos_x' => $fx, 'pos_y' => $fy, 'rotation_deg' => 0.0, 'scale' => 1.0, 'segments' => 2, 'asset_path' => $assetPath, 'asset_ext' => $assetExt];
    }

    // -------- Custom furniture library (uploaded models/images, reusable across rooms) --------

    /** All uploaded furniture assets (global library). */
    public function listFurnitureAssets(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_furniture_asset')) {
            return [];
        }

        return DB::table('ahg_exhibition_furniture_asset')->orderByDesc('id')->get()->map(function ($r) {
            return ['id' => (int) $r->id, 'label' => $r->label, 'file_path' => $r->file_path, 'ext' => $r->ext, 'asset_kind' => $r->asset_kind, 'description' => $r->description ?? null];
        })->all();
    }

    public function addFurnitureAsset(string $label, string $path, string $ext, string $kind, ?string $description = null): array
    {
        $id = (int) DB::table('ahg_exhibition_furniture_asset')->insertGetId([
            'label' => $label, 'file_path' => $path, 'ext' => $ext, 'asset_kind' => $kind, 'description' => $description,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'label' => $label, 'file_path' => $path, 'ext' => $ext, 'asset_kind' => $kind, 'description' => $description];
    }

    public function getFurnitureAsset(int $id): ?object
    {
        return DB::table('ahg_exhibition_furniture_asset')->where('id', $id)->first();
    }

    public function deleteFurnitureAsset(int $id): bool
    {
        return DB::table('ahg_exhibition_furniture_asset')->where('id', $id)->delete() > 0;
    }

    public function moveFurniture(int $id, float $fx, float $fy, ?float $rot = null, ?float $scale = null, ?int $segments = null): bool
    {
        $upd = ['pos_x' => max(0.0, min(1.0, $fx)), 'pos_y' => max(0.0, min(1.0, $fy)), 'updated_at' => now()];
        if ($rot !== null) { $upd['rotation_deg'] = $rot; }
        if ($scale !== null) { $upd['scale'] = max(0.3, min(4.0, $scale)); }
        if ($segments !== null) { $upd['segments'] = max(2, min(20, $segments)); }   // rope-railing pole count

        return DB::table('ahg_exhibition_furniture')->where('id', $id)->update($upd) > 0;
    }

    public function removeFurniture(int $id): bool
    {
        return DB::table('ahg_exhibition_furniture')->where('id', $id)->delete() > 0;
    }

    /**
     * Save explicit per-pole offsets for a rope railing (metres, relative to the railing centre).
     * Empty/short list clears it (the walkthrough then falls back to evenly-spaced `segments`).
     *
     * @param  array<int,array{x:float,z:float}>  $poles
     */
    public function saveFurniturePoles(int $id, array $poles): bool
    {
        $clean = [];
        foreach ($poles as $p) {
            if (! is_array($p) || ! isset($p['x'], $p['z'])) {
                continue;
            }
            $clean[] = ['x' => round((float) $p['x'], 3), 'z' => round((float) $p['z'], 3)];
        }
        $json = count($clean) >= 2 ? json_encode($clean) : null;

        return DB::table('ahg_exhibition_furniture')->where('id', $id)
            ->update(['pole_json' => $json, 'segments' => max(2, count($clean) ?: 2), 'updated_at' => now()]) > 0;
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
    /**
     * AI-generated description for an object that has no scope_and_content, used by
     * the walkthrough "T = talk" docent when there is no metadata to read. Routed
     * through the AI gateway (LlmService) and cached so repeat clicks are free.
     */
    public function aiDescribeObject(int $ioId): ?string
    {
        return \Illuminate\Support\Facades\Cache::remember('exh_ai_desc_'.$ioId, now()->addDays(30), function () use ($ioId) {
            $io = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'io.id')->where('i.culture', '=', 'en'); })
                ->where('io.id', $ioId)
                ->select('io.identifier', 'i.title')->first();
            if (!$io) {
                return null;
            }
            $parent = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'io.parent_id')->where('i.culture', '=', 'en'); })
                ->where('io.id', $ioId)->value('i.title');
            $title = $io->title ?: ($io->identifier ?: ('object #'.$ioId));
            $prompt = 'You are a museum docent speaking to a visitor standing in front of an exhibit. '
                . 'In 2 to 3 vivid sentences, describe this item: "' . $title . '".'
                . ($parent ? ' It is part of the collection "' . $parent . '".' : '')
                . ' Do not invent specific dates, names or provenance you cannot infer from the title; '
                . 'speak generally and evocatively about what such an item is and why it is worth looking at. '
                . 'Plain prose, no preamble, no markdown.';
            try {
                $resp = trim((string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 180, 'temperature' => 0.6]));
                return $resp !== '' ? $resp : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * heratio#1185 - AI docent Q&A. Answer a visitor's question about an object, grounded
     * ONLY in that object's catalogue record (title / reference / collection / scope), via
     * the AI gateway. Refuses to invent dates/names/provenance. Cached per (object, question).
     */
    public function aiAnswerAboutObject(int $ioId, string $question): ?string
    {
        $q = trim($question);
        if ($q === '') {
            return null;
        }
        $q = mb_substr($q, 0, 300);

        return \Illuminate\Support\Facades\Cache::remember('exh_ai_ask_'.$ioId.'_'.md5(mb_strtolower($q)), now()->addDays(7), function () use ($ioId, $q) {
            $io = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'io.id')->where('i.culture', '=', 'en'); })
                ->where('io.id', $ioId)
                ->select('io.identifier', 'i.title', 'i.scope_and_content')->first();
            if (! $io) {
                return null;
            }
            $parent = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as i', function ($j) { $j->on('i.id', '=', 'io.parent_id')->where('i.culture', '=', 'en'); })
                ->where('io.id', $ioId)->value('i.title');

            $rec = [];
            if (! empty($io->title)) { $rec[] = 'Title: '.$io->title; }
            if (! empty($io->identifier)) { $rec[] = 'Reference code: '.$io->identifier; }
            if (! empty($parent)) { $rec[] = 'Part of the collection: '.$parent; }
            if (! empty($io->scope_and_content)) { $rec[] = 'Description: '.trim(strip_tags((string) $io->scope_and_content)); }
            $record = $rec ? implode("\n", $rec) : ('Object #'.$ioId.' - no catalogue detail is recorded.');

            $prompt = "You are a knowledgeable, warm museum docent talking with a visitor standing in front of an exhibit. "
                ."Answer the visitor's question using ONLY the catalogue record below. "
                ."If the record does not contain the answer, say briefly that the record does not say, then add one general, non-fabricated sentence about what such an item is. "
                ."Never invent specific dates, names, places or provenance that are not in the record. "
                ."Reply in 2 to 4 sentences of plain spoken prose - no markdown, no preamble, no bullet points.\n\n"
                ."CATALOGUE RECORD:\n".$record."\n\nVISITOR QUESTION: ".$q;

            try {
                $resp = trim((string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 240, 'temperature' => 0.5]));

                return $resp !== '' ? $resp : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * heratio#1185 - AI docent, ROOM / whole-exhibition scope. Answer a visitor's free-form
     * question about the exhibition as a whole, grounded ONLY in the catalogue of the objects
     * actually placed in this building (title + a short scope snippet each). The model is told
     * to use nothing but the supplied object list - no invented dates, names or provenance -
     * and may point the visitor toward relevant pieces by name. Cached per (building, question).
     */
    public function aiAnswerAboutRoom(object $space, string $question): ?string
    {
        $q = trim($question);
        if ($q === '') {
            return null;
        }
        $q = mb_substr($q, 0, 300);

        $key = 'exh_ai_room_'.($space->building_id ?: $space->slug).'_'.md5(mb_strtolower($q));

        return \Illuminate\Support\Facades\Cache::remember($key, now()->addDays(7), function () use ($space, $q) {
            $objects = $this->roomGroundingObjects($space, 60);
            if (empty($objects)) {
                return null;
            }

            $name = trim((string) ($space->name ?? '')) ?: 'this exhibition';
            $lines = [];
            foreach ($objects as $o) {
                $line = '- '.$o['title'];
                if ($o['room_name'] !== '' && $o['room_name'] !== $o['title']) {
                    $line .= ' [room: '.$o['room_name'].']';
                }
                if ($o['scope'] !== '') {
                    $line .= ' - '.$o['scope'];
                }
                $lines[] = $line;
            }
            $catalogue = implode("\n", $lines);

            $prompt = "You are a knowledgeable, warm museum docent standing with a visitor inside the exhibition \"".$name."\". "
                ."The visitor asks about the exhibition as a whole. Answer using ONLY the list of objects on display below - "
                ."their titles and short descriptions are the only facts you know. "
                ."When it helps, point the visitor toward specific pieces by their exact title so they can go and look. "
                ."If the objects on display do not cover what was asked, say briefly that this exhibition does not seem to include that, and suggest the closest pieces that ARE on display. "
                ."Never invent objects, dates, names, places or provenance that are not in the list below. "
                ."Reply in 2 to 5 sentences of plain spoken prose - no markdown, no preamble, no bullet points.\n\n"
                ."OBJECTS ON DISPLAY:\n".$catalogue."\n\nVISITOR QUESTION: ".$q;

            try {
                $resp = trim((string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 300, 'temperature' => 0.5]));

                return $resp !== '' ? $resp : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * heratio#1185 - CONVERSATIONAL room docent. A multi-turn, room-aware guide: carries the
     * recent transcript + the visitor's current location into the same grounded prompt as
     * aiAnswerAboutRoom, resolves "this/that/it" from context, and ends with a concrete next
     * object to see. NOT cached - history makes every turn unique. Grounding stays strictly the
     * placed-object catalogue (no invention). Routes through the AI gateway via LlmService.
     *
     * @param array<int,array{q?:string,a?:string}> $turns prior turns, oldest first
     * @return array{answer:?string,suggest:?string}
     */
    public function aiConverseRoom(object $space, string $question, array $turns = [], ?int $nearObjectId = null, ?int $roomId = null): array
    {
        $q = mb_substr(trim($question), 0, 300);
        if ($q === '') {
            return ['answer' => null, 'suggest' => null];
        }
        $objects = $this->roomGroundingObjects($space, 60);
        if (empty($objects)) {
            return ['answer' => null, 'suggest' => null];
        }

        $name = trim((string) ($space->name ?? '')) ?: 'this exhibition';
        $titles = [];   // lowercase title => canonical title, for validating the model's suggestion
        $lines = [];
        $loc = '';
        foreach ($objects as $o) {
            $titles[mb_strtolower($o['title'])] = $o['title'];
            $line = '- '.$o['title'];
            if ($o['room_name'] !== '' && $o['room_name'] !== $o['title']) { $line .= ' [room: '.$o['room_name'].']'; }
            if ($o['scope'] !== '') { $line .= ' - '.$o['scope']; }
            $lines[] = $line;
            if ($nearObjectId && $o['io_id'] === $nearObjectId) {
                $loc = 'The visitor is standing next to "'.$o['title'].'"'.($o['room_name'] !== '' ? ' in '.$o['room_name'] : '').'. ';
            }
        }
        $catalogue = implode("\n", $lines);

        // OPTIONAL broader grounding from the KM knowledge base (heratio#1185). Fetched once per
        // turn, with a short timeout; if it is slow, empty, off, or unconfigured we proceed
        // catalogue-only. The placed-object catalogue above always stays authoritative - the KM
        // snippet is a clearly-labelled SECONDARY source the model may use only to enrich.
        $kmSnippet = null;
        if ((bool) config('heratio.exhibition_docent_km', true)) {
            try {
                $kmSnippet = app(\AhgExhibition\Services\KmContextService::class)
                    ->ask($q, (int) config('heratio.km.timeout_seconds', 6));
            } catch (\Throwable $e) {
                $kmSnippet = null;   // never let KM break the docent
            }
        }

        $hist = '';
        foreach (array_slice($turns, -6) as $t) {
            $tq = trim((string) ($t['q'] ?? '')); $ta = trim((string) ($t['a'] ?? ''));
            if ($tq !== '') { $hist .= 'Visitor: '.mb_substr($tq, 0, 240)."\n"; }
            if ($ta !== '') { $hist .= 'Docent: '.mb_substr($ta, 0, 320)."\n"; }
        }

        $prompt = 'You are a knowledgeable, warm museum docent walking a visitor through the exhibition "'.$name.'" in an ongoing spoken conversation. '
            .'Answer using ONLY the objects on display listed below - their titles and short descriptions are the only facts you know. '
            .$loc
            .'Resolve words like "this", "that one" and "it" from the conversation so far and the visitor\'s location. '
            .'Never invent objects, dates, names, places or provenance not in the list. '
            .'Reply in 2 to 4 sentences of plain spoken prose - no markdown, no preamble, no bullet points. '
            ."Then, on a FINAL separate line, write exactly 'NEXT: ' followed by the exact title of one object from the list the visitor might enjoy seeing next (different from what they just asked about), or 'NEXT: NONE'.\n\n"
            ."OBJECTS ON DISPLAY:\n".$catalogue."\n\n"
            .($kmSnippet !== null
                ? "BROADER COLLECTION CONTEXT (from the knowledge base, use only to enrich - the objects on display above are authoritative):\n"
                    .$kmSnippet."\n"
                    ."Use this background only to add colour or answer a broader question; it does NOT add objects to the room. "
                    ."Still prefer the objects on display, and never claim the room holds anything not listed above.\n\n"
                : '')
            .($hist !== '' ? "CONVERSATION SO FAR:\n".$hist."\n" : '')
            .'Visitor: '.$q;

        try {
            $resp = trim((string) app(\AhgAiServices\Services\LlmService::class)->complete($prompt, ['max_tokens' => 320, 'temperature' => 0.5]));
        } catch (\Throwable $e) {
            return ['answer' => null, 'suggest' => null];
        }
        if ($resp === '') {
            return ['answer' => null, 'suggest' => null];
        }

        // Pull the trailing NEXT: suggestion off the answer and validate it against the catalogue.
        $suggest = null;
        if (preg_match('/\bNEXT:\s*(.+?)\s*$/is', $resp, $m)) {
            $cand = trim($m[1]);
            $resp = trim((string) preg_replace('/\n?\s*NEXT:\s*.+?\s*$/is', '', $resp));
            $lc = mb_strtolower($cand);
            if ($lc !== '' && $lc !== 'none') {
                if (isset($titles[$lc])) {
                    $suggest = $titles[$lc];
                } else {
                    foreach ($titles as $k => $v) {
                        if ($k !== '' && (mb_strpos($k, $lc) !== false || mb_strpos($lc, $k) !== false)) { $suggest = $v; break; }
                    }
                }
            }
        }

        return ['answer' => $resp !== '' ? $resp : null, 'suggest' => $suggest];
    }

    /**
     * heratio#1185 - grounding set for the room docent: each distinct placed object in the
     * building once, with its title and a trimmed scope snippet. Pure catalogue data - no AI.
     *
     * @return array<int,array{io_id:int,title:string,room_name:string,scope:string}>
     */
    public function roomGroundingObjects(object $space, int $limit = 60): array
    {
        $ids = $this->buildingSpaceIds($space);
        if (empty($ids)) {
            return [];
        }

        $rows = DB::table('ahg_exhibition_placement as ep')
            ->join('ahg_exhibition_space as sp', 'sp.id', '=', 'ep.exhibition_space_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->whereIn('ep.exhibition_space_id', $ids)
            ->whereNotNull('ep.information_object_id')
            ->where(function ($qq) { $qq->whereNull('ep.wall_or_zone')->orWhere('ep.wall_or_zone', '!=', 'corridor'); })
            ->orderBy('sp.building_seq')->orderBy('sp.id')->orderBy('ep.id')
            ->select('ep.information_object_id as io_id', 'ioi.title', 'ioi.scope_and_content', 'sp.name as room_name')
            ->get();

        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $ioId = (int) $r->io_id;
            if ($ioId <= 0 || isset($seen[$ioId])) {
                continue;
            }
            $seen[$ioId] = 1;
            $scope = trim(strip_tags((string) ($r->scope_and_content ?? '')));
            $scope = preg_replace('/\s+/', ' ', $scope);
            if (mb_strlen($scope) > 220) {
                $scope = mb_substr($scope, 0, 220).'...';
            }
            $out[] = [
                'io_id' => $ioId,
                'title' => trim((string) ($r->title ?: ('#'.$ioId))),
                'room_name' => trim((string) ($r->room_name ?? '')),
                'scope' => $scope,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * heratio#1185 - suggested follow-up question chips for the room docent, grounded in the
     * exhibition's real objects (no AI, no invention). Returns short visitor-facing prompts that
     * name actual pieces on display, so every chip is answerable from the catalogue.
     *
     * @return array<int,string>
     */
    public function roomSuggestedQuestions(object $space, int $limit = 4): array
    {
        $objects = $this->roomGroundingObjects($space, 40);
        if (empty($objects)) {
            return [];
        }

        $chips = ['What is this exhibition about?'];
        // Name two real pieces so the suggestion is always grounded and answerable.
        $titled = array_values(array_filter($objects, fn ($o) => $o['title'] !== '' && $o['title'][0] !== '#'));
        if (isset($titled[0])) {
            $chips[] = 'Tell me about "'.$this->shortTitle($titled[0]['title']).'"';
        }
        if (isset($titled[1])) {
            $chips[] = 'Where can I find "'.$this->shortTitle($titled[1]['title']).'"?';
        }
        $chips[] = 'Which pieces should I not miss?';

        return array_slice(array_values(array_unique($chips)), 0, max(1, $limit));
    }

    /** Trim a catalogue title to a chip-friendly length without cutting mid-word. */
    private function shortTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));
        if (mb_strlen($title) <= 48) {
            return $title;
        }
        $cut = mb_substr($title, 0, 48);
        $sp = mb_strrpos($cut, ' ');

        return ($sp !== false ? mb_substr($cut, 0, $sp) : $cut).'...';
    }

    /**
     * heratio#1150 multi-user presence. Upsert this visitor's pose into the building's
     * presence table and return the other live co-visitors + the active docent's tour
     * state. Polled ~2-3x/sec by the walkthrough. $isDocent (decided by the controller
     * from auth) gates docent role + tour control; visitors can never set tour state.
     */
    public function presenceBeat(object $space, array $in, bool $isDocent = false): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_presence')) {
            return ['peers' => [], 'tour' => null];
        }
        $building = $space->building_id ?: $space->slug;
        $token = substr((string) ($in['token'] ?? ''), 0, 64);
        if ($token === '') {
            return ['peers' => [], 'tour' => null];
        }
        $now = now();
        $role = ($isDocent && ($in['role'] ?? '') === 'docent') ? 'docent' : 'visitor';
        $row = [
            'display_name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 120) ?: 'Visitor',
            'role' => $role,
            'color' => substr((string) ($in['color'] ?? ''), 0, 9) ?: null,
            'room_id' => (isset($in['room_id']) && $in['room_id'] !== null && $in['room_id'] !== '') ? (int) $in['room_id'] : null,
            'pos_x' => isset($in['x']) ? (float) $in['x'] : null,
            'pos_y' => isset($in['y']) ? (float) $in['y'] : null,
            'pos_z' => isset($in['z']) ? (float) $in['z'] : null,
            'yaw' => isset($in['yaw']) ? (float) $in['yaw'] : null,
            'last_seen' => $now,
        ];
        if ($role === 'docent') {   // only a docent may drive the guided tour
            $row['tour_active'] = !empty($in['tour_active']) ? 1 : 0;
            $row['focus_object_id'] = (isset($in['focus_object_id']) && $in['focus_object_id']) ? (int) $in['focus_object_id'] : null;
            $row['docent_msg'] = mb_substr(trim((string) ($in['docent_msg'] ?? '')), 0, 280) ?: null;
        }
        // Atomic upsert (INSERT .. ON DUPLICATE KEY UPDATE) - updateOrInsert races under
        // concurrent ~2/sec beats with the same token and threw 1062 duplicate-key errors.
        DB::table('ahg_exhibition_presence')->upsert(
            [array_merge(['building_id' => $building, 'session_token' => $token], $row)],
            ['building_id', 'session_token'],
            array_keys($row)
        );
        DB::table('ahg_exhibition_presence')->where('building_id', $building)
            ->where('last_seen', '<', $now->copy()->subSeconds(15))->delete();   // GC stale

        $peers = DB::table('ahg_exhibition_presence')
            ->where('building_id', $building)
            ->where('session_token', '!=', $token)
            ->where('last_seen', '>=', $now->copy()->subSeconds(12))
            ->get(['session_token', 'display_name', 'role', 'color', 'room_id', 'pos_x', 'pos_y', 'pos_z', 'yaw']);

        $docent = DB::table('ahg_exhibition_presence')
            ->where('building_id', $building)->where('role', 'docent')->where('tour_active', 1)
            ->where('last_seen', '>=', $now->copy()->subSeconds(12))
            ->orderBy('last_seen', 'desc')
            ->first(['session_token', 'display_name', 'room_id', 'pos_x', 'pos_y', 'pos_z', 'yaw', 'focus_object_id', 'docent_msg']);
        $tour = $docent ? [
            'docent_token' => $docent->session_token,
            'docent_name' => $docent->display_name,
            'room_id' => $docent->room_id,
            'x' => $docent->pos_x, 'y' => $docent->pos_y, 'z' => $docent->pos_z, 'yaw' => $docent->yaw,
            'focus_object_id' => $docent->focus_object_id,
            'msg' => $docent->docent_msg,
        ] : null;

        // heratio#1173 - capture the visit + per-room dwell from the same beat.
        // heratio#1187 - also bank per-object dwell from the object the visitor currently
        // has open (cur_object), so the heatmap can shade individual objects by attention.
        $curObject = (isset($in['cur_object']) && $in['cur_object']) ? (int) $in['cur_object'] : null;
        $this->recordVisitBeat($space, $token, $in['device'] ?? null, $row['room_id'], $curObject);

        return [
            'peers' => $peers->map(function ($p) {
                return [
                    'token' => $p->session_token, 'name' => $p->display_name, 'role' => $p->role, 'color' => $p->color,
                    'room_id' => $p->room_id, 'x' => $p->pos_x, 'y' => $p->pos_y, 'z' => $p->pos_z, 'yaw' => $p->yaw,
                ];
            })->all(),
            'tour' => $tour,
        ];
    }

    /** heratio#1150 - remove a visitor's presence row on exit (sendBeacon). */
    public function presenceLeave(object $space, string $token): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_presence')) {
            return;
        }
        $building = $space->building_id ?: $space->slug;
        DB::table('ahg_exhibition_presence')->where('building_id', $building)
            ->where('session_token', substr($token, 0, 64))->delete();
    }

    /**
     * heratio#1173 - upsert the visit row + accumulate per-room dwell (called from the beat).
     * heratio#1187 - $objectId is the object the visitor currently has open; per-object dwell
     * is banked into object_seconds_json the same way per-room dwell is banked into room_seconds_json.
     */
    public function recordVisitBeat(object $space, string $token, ?string $device, ?int $roomId, ?int $objectId = null): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_visit')) {
            return;
        }
        $token = substr($token, 0, 64);
        if ($token === '') {
            return;
        }
        $building = $space->building_id ?: $space->slug;
        $now = now();
        $v = DB::table('ahg_exhibition_visit')->where('building_id', $building)->where('session_token', $token)->first();
        if (!$v) {
            // insertOrIgnore: concurrent first beats with the same token must not 1062 on uq_visit.
            DB::table('ahg_exhibition_visit')->insertOrIgnore([
                'building_id' => $building, 'session_token' => $token, 'device' => substr((string) $device, 0, 16) ?: null,
                'cur_room' => $roomId, 'room_entered_at' => $now, 'room_seconds_json' => json_encode([]),
                'cur_object' => $objectId, 'object_entered_at' => $objectId ? $now : null, 'object_seconds_json' => json_encode([]),
                'started_at' => $now, 'last_seen' => $now,
            ]);

            return;
        }
        $upd = ['last_seen' => $now];
        if ((int) $v->cur_room !== (int) $roomId) {   // moved rooms: bank dwell on the one we left
            $secs = $v->room_seconds_json ? json_decode($v->room_seconds_json, true) : [];
            if (!is_array($secs)) { $secs = []; }
            if ($v->cur_room && $v->room_entered_at) {
                $d = min(3600, max(0, $now->getTimestamp() - strtotime($v->room_entered_at)));
                $secs[(string) $v->cur_room] = ($secs[(string) $v->cur_room] ?? 0) + $d;
            }
            $upd['room_seconds_json'] = json_encode($secs);
            $upd['cur_room'] = $roomId;
            $upd['room_entered_at'] = $now;
        }
        // heratio#1187 - focus changed (opened a different object, or closed the popup -> null):
        // bank the dwell accumulated on the object we just left.
        $prevObject = ($v->cur_object ?? null) ? (int) $v->cur_object : null;
        if ((int) $prevObject !== (int) $objectId) {
            $osecs = ($v->object_seconds_json ?? null) ? json_decode($v->object_seconds_json, true) : [];
            if (!is_array($osecs)) { $osecs = []; }
            if ($prevObject && ($v->object_entered_at ?? null)) {
                $od = min(3600, max(0, $now->getTimestamp() - strtotime($v->object_entered_at)));
                $osecs[(string) $prevObject] = ($osecs[(string) $prevObject] ?? 0) + $od;
            }
            $upd['object_seconds_json'] = json_encode($osecs);
            $upd['cur_object'] = $objectId ?: null;
            $upd['object_entered_at'] = $objectId ? $now : null;
        }
        DB::table('ahg_exhibition_visit')->where('id', $v->id)->update($upd);
    }

    /** heratio#1173 - log a visit event (object view / tour / door). */
    public function recordVisitEvent(object $space, string $token, string $type, ?int $roomId, ?int $objectId): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_visit_event')) {
            return;
        }
        $building = $space->building_id ?: $space->slug;
        DB::table('ahg_exhibition_visit_event')->insert([
            'building_id' => $building, 'session_token' => substr($token, 0, 64),
            'type' => substr($type, 0, 16), 'room_id' => $roomId ?: null, 'object_id' => $objectId ?: null, 'created_at' => now(),
        ]);
    }

    /** heratio#1173 - aggregate visitor analytics for the dashboard. */
    public function visitorAnalytics(object $space, int $days = 30): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_visit')) {
            return ['sessions' => 0, 'avg_seconds' => 0, 'devices' => [], 'dwell' => [], 'top_objects' => []];
        }
        $building = $space->building_id ?: $space->slug;
        $since = now()->subDays($days);
        $visits = DB::table('ahg_exhibition_visit')->where('building_id', $building)->where('started_at', '>=', $since)->get();
        $sessions = $visits->count();
        $durs = $visits->map(function ($v) { return max(0, strtotime($v->last_seen) - strtotime($v->started_at)); });
        $avg = $sessions ? (int) round($durs->avg()) : 0;
        $devices = $visits->groupBy(function ($v) { return $v->device ?: 'desktop'; })->map->count()->all();
        $roomSecs = [];
        $objSecs = [];   // #1187 per-object dwell, to show attention next to raw view counts
        foreach ($visits as $v) {
            $s = $v->room_seconds_json ? json_decode($v->room_seconds_json, true) : [];
            if (is_array($s)) { foreach ($s as $rid => $sec) { $roomSecs[$rid] = ($roomSecs[$rid] ?? 0) + $sec; } }
            $os = ($v->object_seconds_json ?? null) ? json_decode($v->object_seconds_json, true) : [];
            if (is_array($os)) { foreach ($os as $oid => $sec) { $objSecs[(int) $oid] = ($objSecs[(int) $oid] ?? 0) + (int) $sec; } }
        }
        $rids = array_keys($roomSecs);
        $names = $rids ? DB::table('ahg_exhibition_space')->whereIn('id', $rids)->pluck('name', 'id')->all() : [];
        $dwell = [];
        foreach ($roomSecs as $rid => $sec) { $dwell[] = ['room' => $names[$rid] ?? ('#'.$rid), 'seconds' => (int) $sec]; }
        usort($dwell, function ($a, $b) { return $b['seconds'] - $a['seconds']; });
        $top = DB::table('ahg_exhibition_visit_event')->where('building_id', $building)->where('type', 'object')
            ->where('created_at', '>=', $since)->whereNotNull('object_id')
            ->select('object_id', DB::raw('COUNT(*) as c'))->groupBy('object_id')->orderByDesc('c')->limit(10)->get();
        $oids = $top->pluck('object_id')->all();
        $titles = $oids ? DB::table('information_object_i18n')->whereIn('id', $oids)->where('culture', 'en')->pluck('title', 'id')->all() : [];
        $topObjects = $top->map(function ($r) use ($titles, $objSecs) {
            return [
                'title' => $titles[$r->object_id] ?? ('#'.$r->object_id),
                'views' => (int) $r->c,
                'seconds' => (int) ($objSecs[(int) $r->object_id] ?? 0),   // #1187 total attention dwell
            ];
        })->all();

        return ['sessions' => $sessions, 'avg_seconds' => $avg, 'devices' => $devices, 'dwell' => array_slice($dwell, 0, 10), 'top_objects' => $topObjects];
    }

    /**
     * heratio#1187 - visitor heatmap. Building room geometry + total dwell seconds per room
     * (from ahg_exhibition_visit.room_seconds_json) + per-object view counts (from
     * visit_event), so the analytics page can render a top-down dwell/attention heatmap.
     */
    public function visitorHeatmap(object $space, int $days = 30): array
    {
        $building = $this->getWalkthroughBuilding($space);
        $b = $space->building_id ?: $space->slug;
        $since = now()->subDays($days);

        // Dwell seconds per room id, plus per-object dwell seconds (#1187 attention).
        $roomSecs = [];
        $objSecs = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_visit')) {
            foreach (DB::table('ahg_exhibition_visit')->where('building_id', $b)->where('started_at', '>=', $since)->get() as $v) {
                $s = $v->room_seconds_json ? json_decode((string) $v->room_seconds_json, true) : [];
                if (is_array($s)) {
                    foreach ($s as $rid => $sec) { $roomSecs[(int) $rid] = ($roomSecs[(int) $rid] ?? 0) + (int) $sec; }
                }
                $os = ($v->object_seconds_json ?? null) ? json_decode((string) $v->object_seconds_json, true) : [];
                if (is_array($os)) {
                    foreach ($os as $oid => $sec) { $objSecs[(int) $oid] = ($objSecs[(int) $oid] ?? 0) + (int) $sec; }
                }
            }
        }

        // Object view counts (popups opened), keyed by object id - the companion signal to
        // dwell: many views + little dwell = an object that draws the eye but loses attention.
        $objViews = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_visit_event')) {
            $rows = DB::table('ahg_exhibition_visit_event')->where('building_id', $b)->where('type', 'object')
                ->where('created_at', '>=', $since)->whereNotNull('object_id')
                ->select('object_id', DB::raw('COUNT(*) as c'))->groupBy('object_id')->get();
            foreach ($rows as $r) { $objViews[(int) $r->object_id] = (int) $r->c; }
        }

        $rooms = [];
        $maxSec = 0;
        $objects = [];
        $maxViews = 0;
        $maxObjSec = 0;
        foreach ($building['rooms'] as $r) {
            $sec = (int) ($roomSecs[$r['id']] ?? 0);
            $maxSec = max($maxSec, $sec);
            $rooms[] = ['id' => $r['id'], 'name' => $r['name'], 'x' => $r['x_offset'], 'z' => $r['z_offset'], 'w' => $r['w'], 'd' => $r['d'], 'seconds' => $sec];
            foreach (($r['stops'] ?? []) as $s) {
                $oid = (int) ($s['information_object_id'] ?? 0);
                $views = $objViews[$oid] ?? 0;
                $osec = (int) ($objSecs[$oid] ?? 0);
                if ($oid && ($views > 0 || $osec > 0)) {
                    $maxViews = max($maxViews, $views);
                    $maxObjSec = max($maxObjSec, $osec);
                    $objects[] = [
                        'x' => $r['x_offset'] + (float) ($s['pos_x'] ?? 0.5) * $r['w'],
                        'z' => $r['z_offset'] + (float) ($s['pos_y'] ?? 0.5) * $r['d'],
                        'views' => $views, 'seconds' => $osec, 'title' => $s['title'] ?? ('#'.$oid),
                    ];
                }
            }
        }

        return [
            'rooms' => $rooms, 'objects' => $objects,
            'max_seconds' => $maxSec, 'max_views' => $maxViews, 'max_object_seconds' => $maxObjSec,
            'min_x' => $building['min_x'] ?? 0, 'max_x' => $building['max_x'] ?? 0,
            'min_z' => $building['min_z'] ?? 0, 'max_z' => $building['max_z'] ?? 0,
        ];
    }

    /** heratio#1165 - wall graffiti: list all annotations for the building. */
    public function listAnnotations(object $space): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_annotation')) {
            return [];
        }
        $building = $space->building_id ?: $space->slug;

        return DB::table('ahg_exhibition_annotation')->where('building_id', $building)
            ->orderBy('id')->get()->map(function ($r) {
                return [
                    'id' => (int) $r->id, 'room_id' => $r->room_id !== null ? (int) $r->room_id : null,
                    'x' => (float) $r->pos_x, 'y' => (float) $r->pos_y, 'z' => (float) $r->pos_z,
                    'text' => $r->text, 'color' => $r->color, 'author' => $r->author,
                    'yaw' => ($r->yaw !== null ? (float) $r->yaw : null),
                ];
            })->all();
    }

    /**
     * Authored audio guided tour for the exhibition: an ordered list of objects with a
     * narration script + dwell time each. Stored as guided_tour_json on the space.
     * Returns [{io_id, title, narration, dwell}] enriched with the object title.
     */
    /**
     * Rooms in $space's building, ordered by floor then Room order (building_seq).
     * Ungrouped spaces return just themselves. The FIRST entry is the main room
     * (shared-tour host + walkthrough spawn).
     */
    public function buildingRoomsOrdered(object $space): array
    {
        if (empty($space->building_id)) {
            return [$space];
        }

        return DB::table('ahg_exhibition_space')
            ->where('building_id', $space->building_id)
            ->orderBy('floor_level')->orderBy('building_seq')->orderBy('id')
            ->get()->all();
    }

    public function getGuidedTour(object $space): array
    {
        // Shared building-wide tours: gather from every room in the building
        // (main room / Room order first) so the same set appears in each room's
        // builder and in the walkthrough; de-duplicate by tour name (first wins).
        $raw = [];
        foreach ($this->buildingRoomsOrdered($space) as $rm) {
            $j = $rm->guided_tour_json ?? null;
            if (is_string($j)) {
                $j = json_decode($j, true);
            }
            if (!is_array($j) || empty($j)) {
                continue;
            }
            if (isset($j[0]['io_id'])) {   // legacy flat stop array
                $j = [['name' => 'Tour', 'stops' => $j]];
            }
            foreach ($j as $t) {
                if (is_array($t)) {
                    $raw[] = $t;
                }
            }
        }
        if (empty($raw)) {
            return [];
        }
        $seen = [];
        $deduped = [];
        foreach ($raw as $t) {
            $key = mb_strtolower(trim((string) ($t['name'] ?? 'Tour')));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $t;
        }
        $raw = $deduped;
        $ids = [];
        foreach ($raw as $t) {
            foreach (($t['stops'] ?? []) as $s) {
                if (!empty($s['io_id'])) {
                    $ids[] = (int) $s['io_id'];
                }
            }
        }
        $titles = $ids ? DB::table('information_object_i18n')->whereIn('id', $ids)->where('culture', 'en')
            ->pluck('title', 'id')->all() : [];
        $out = [];
        foreach ($raw as $t) {
            $stops = [];
            foreach (($t['stops'] ?? []) as $s) {
                if (empty($s['io_id'])) {
                    continue;
                }
                $id = (int) $s['io_id'];
                $stops[] = ['io_id' => $id, 'title' => $titles[$id] ?? ('#'.$id), 'narration' => (string) ($s['narration'] ?? ''), 'dwell' => (int) ($s['dwell'] ?? 6), 'audio' => (string) ($s['audio'] ?? '')];
            }
            $out[] = ['name' => (string) ($t['name'] ?? 'Tour'), 'stops' => $stops];
        }

        return $out;
    }

    /** Save the authored guided tours (list of {name, stops}); validates + clamps. */
    public function saveGuidedTour(object $space, array $tours): void
    {
        $clean = [];
        foreach ($tours as $t) {
            $stops = [];
            foreach (($t['stops'] ?? []) as $s) {
                if (empty($s['io_id'])) {
                    continue;
                }
                $stops[] = ['io_id' => (int) $s['io_id'], 'narration' => mb_substr(trim((string) ($s['narration'] ?? '')), 0, 1200), 'dwell' => max(2, min(60, (int) ($s['dwell'] ?? 6))), 'audio' => mb_substr(trim((string) ($s['audio'] ?? '')), 0, 500)];
            }
            if ($stops) {
                $clean[] = ['name' => (mb_substr(trim((string) ($t['name'] ?? 'Tour')), 0, 80) ?: 'Tour'), 'stops' => $stops];
            }
        }
        // Shared building-wide: store the one authoritative set on the MAIN room
        // (first in Room order) and clear the other rooms in the building, so a
        // multi-room building has a single tour list instead of per-room copies.
        $rooms = $this->buildingRoomsOrdered($space);
        $host = $rooms[0] ?? $space;
        DB::table('ahg_exhibition_space')->where('id', $host->id)->update(['guided_tour_json' => json_encode($clean)]);
        if (!empty($space->building_id)) {
            DB::table('ahg_exhibition_space')->where('building_id', $space->building_id)
                ->where('id', '!=', $host->id)->update(['guided_tour_json' => null]);
        }
    }

    /** heratio#1165 - add a graffiti annotation; returns the saved row payload. */
    public function addAnnotation(object $space, array $in): ?array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_annotation')) {
            return null;
        }
        $text = trim((string) ($in['text'] ?? ''));
        if ($text === '') {
            return null;
        }
        $building = $space->building_id ?: $space->slug;
        $id = DB::table('ahg_exhibition_annotation')->insertGetId([
            'building_id' => $building,
            'room_id' => (isset($in['room_id']) && $in['room_id'] !== null && $in['room_id'] !== '') ? (int) $in['room_id'] : null,
            'pos_x' => (float) ($in['x'] ?? 0), 'pos_y' => (float) ($in['y'] ?? 1.6), 'pos_z' => (float) ($in['z'] ?? 0),
            'text' => mb_substr($text, 0, 160),
            'color' => substr((string) ($in['color'] ?? ''), 0, 9) ?: null,
            'author' => mb_substr(trim((string) ($in['author'] ?? '')), 0, 40) ?: null,
            'yaw' => (isset($in['yaw']) && $in['yaw'] !== '' && is_numeric($in['yaw'])) ? (float) $in['yaw'] : null,
            'created_at' => now(),
        ]);

        return [
            'id' => $id, 'room_id' => (isset($in['room_id']) && $in['room_id'] !== '') ? (int) $in['room_id'] : null,
            'x' => (float) ($in['x'] ?? 0), 'y' => (float) ($in['y'] ?? 1.6), 'z' => (float) ($in['z'] ?? 0),
            'text' => mb_substr($text, 0, 160), 'color' => substr((string) ($in['color'] ?? ''), 0, 9) ?: null,
            'author' => mb_substr(trim((string) ($in['author'] ?? '')), 0, 40) ?: null,
            'yaw' => (isset($in['yaw']) && $in['yaw'] !== '' && is_numeric($in['yaw'])) ? (float) $in['yaw'] : null,
        ];
    }

    /** heratio#1165 - delete a graffiti annotation (click-to-delete in the walkthrough). */
    public function deleteAnnotation(object $space, int $id): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_annotation')) {
            return;
        }
        $building = $space->building_id ?: $space->slug;
        DB::table('ahg_exhibition_annotation')->where('building_id', $building)->where('id', $id)->delete();
    }

    // ===================================================================
    // heratio#1151 - interoperability: open-standard exports of the twin so
    // other institutions / viewers can consume it (no F3 coupling).
    // ===================================================================

    /** Absolutise an app-relative path (/uploads/.., /slug) for portable exports. */
    private function absUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        return url($path);
    }

    /**
     * Open, documented 3D scene manifest for the whole building: rooms (metres,
     * shape, rotation) + placed objects (world placement + media incl. glTF/GLB
     * model URLs). Any third-party 3D viewer can reconstruct the twin from this.
     */
    public function sceneManifest(object $space): array
    {
        $b = $this->getWalkthroughBuilding($space);
        $rooms = [];
        $objects = [];
        foreach ($b['rooms'] as $r) {
            $rooms[] = [
                'id' => $r['id'], 'name' => $r['name'],
                'width_m' => $r['w'], 'depth_m' => $r['d'], 'height_m' => $r['h'],
                'origin' => ['x' => $r['x_offset'], 'z' => $r['z_offset']],
                'rotation_deg' => $r['rot'] ?? 0,
                'shape' => $r['shape'] ?? null,   // normalised polygon [{x,z}] 0..1, null = rectangle
                'floor_image' => $this->absUrl($r['floorplan'] ?? null),
                'ceiling_image' => $this->absUrl($r['ceiling'] ?? null),
            ];
            foreach (($r['stops'] ?? []) as $s) {
                $objects[] = [
                    'information_object_id' => $s['information_object_id'],
                    'title' => $s['title'],
                    'description' => $s['description'],
                    'room_id' => $r['id'],
                    'placement' => [
                        'pos_x' => $s['pos_x'], 'pos_y' => $s['pos_y'],
                        'wall_or_zone' => $s['wall_or_zone'],
                        'wall_u' => $s['wall_u'], 'wall_v' => $s['wall_v'],
                        'rotation_deg' => $s['rotation_deg'], 'scale' => $s['scale'],
                        'tilt_x' => $s['tilt_x'], 'tilt_z' => $s['tilt_z'],
                    ],
                    'media_kind' => $s['kind'],
                    'model_url' => $this->absUrl($s['model_url']),
                    'model_format' => $s['model_format'],
                    'image_url' => $this->absUrl($s['image_url']),
                    'record_url' => $this->absUrl($s['record_url']),
                ];
            }
        }

        return [
            'format' => 'ahg-exhibition-scene',
            'version' => '1.0',
            'generator' => 'Heratio',
            'units' => 'metre',
            'coordinate_system' => 'y-up, metres; room origin is its top-left corner',
            'exhibition' => [
                'id' => (int) $space->id, 'name' => $space->name, 'slug' => $space->slug,
                'walkthrough_url' => $this->absUrl('/exhibition-space/'.$space->slug.'/walkthrough'),
            ],
            'rooms' => $rooms,
            'objects' => $objects,
        ];
    }

    /**
     * IIIF Presentation 3.0 Manifest for the exhibition: one Canvas per object with
     * an image painting annotation, rooms expressed as structural Ranges, and a
     * rendering link to any glTF/GLB model. Image dimensions default to 1000x1000
     * when not introspected (functional; strict validators may want real pixels).
     */
    public function iiifManifest(object $space): array
    {
        $base = $this->absUrl('/exhibition-space/'.$space->slug.'/manifest.json');
        $b = $this->getWalkthroughBuilding($space);
        $items = [];
        $ranges = [];
        $ci = 0;
        foreach ($b['rooms'] as $r) {
            $roomCanvases = [];
            foreach (($r['stops'] ?? []) as $s) {
                $img = $this->absUrl($s['image_url']);
                if (!$img) {
                    continue;   // a canvas needs a paintable body
                }
                $ci++;
                $canvasId = $base.'/canvas/'.$s['information_object_id'];
                $w = 1000; $h = 1000;
                $canvas = [
                    'id' => $canvasId, 'type' => 'Canvas',
                    'label' => ['en' => [$s['title']]],
                    'height' => $h, 'width' => $w,
                    'items' => [[
                        'id' => $canvasId.'/page', 'type' => 'AnnotationPage',
                        'items' => [[
                            'id' => $canvasId.'/annotation', 'type' => 'Annotation', 'motivation' => 'painting',
                            'body' => ['id' => $img, 'type' => 'Image', 'format' => 'image/jpeg', 'height' => $h, 'width' => $w],
                            'target' => $canvasId,
                        ]],
                    ]],
                ];
                if (!empty($s['description'])) {
                    $canvas['summary'] = ['en' => [$s['description']]];
                }
                if (!empty($s['record_url'])) {
                    $canvas['homepage'] = [['id' => $this->absUrl($s['record_url']), 'type' => 'Text', 'label' => ['en' => ['Full record']], 'format' => 'text/html']];
                }
                if (!empty($s['model_url'])) {
                    $canvas['rendering'] = [['id' => $this->absUrl($s['model_url']), 'type' => 'Model', 'label' => ['en' => ['3D model ('.($s['model_format'] ?: 'glb').')']], 'format' => 'model/gltf-binary']];
                }
                $items[] = $canvas;
                $roomCanvases[] = ['id' => $canvasId, 'type' => 'Canvas'];
            }
            if ($roomCanvases) {
                $ranges[] = ['id' => $base.'/range/'.$r['id'], 'type' => 'Range', 'label' => ['en' => [$r['name']]], 'items' => $roomCanvases];
            }
        }

        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $base, 'type' => 'Manifest',
            'label' => ['en' => [$space->name]],
            'summary' => ['en' => ['Virtual exhibition exported from Heratio ('.$ci.' objects).']],
            'rights' => 'http://rightsstatements.org/vocab/CNE/1.0/',
            'requiredStatement' => ['label' => ['en' => ['Source']], 'value' => ['en' => ['Heratio digital twin']]],
            'items' => $items,
        ];
        if ($ranges) {
            $manifest['structures'] = [['id' => $base.'/range', 'type' => 'Range', 'label' => ['en' => ['Rooms']], 'items' => $ranges]];
        }

        return $manifest;
    }

    /** schema.org ExhibitionEvent JSON-LD for linked-data discovery / harvesting. */
    public function exhibitionJsonLd(object $space): array
    {
        $b = $this->getWalkthroughBuilding($space);
        $featured = [];
        foreach ($b['rooms'] as $r) {
            foreach (($r['stops'] ?? []) as $s) {
                $work = ['@type' => 'CreativeWork', 'name' => $s['title']];
                if (!empty($s['description'])) {
                    $work['description'] = $s['description'];
                }
                if (!empty($s['record_url'])) {
                    $work['url'] = $this->absUrl($s['record_url']);
                }
                if (!empty($s['image_url'])) {
                    $work['image'] = $this->absUrl($s['image_url']);
                }
                $work['isPartOf'] = ['@type' => 'Collection', 'name' => $r['name']];
                $featured[] = $work;
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ExhibitionEvent',
            'name' => $space->name,
            'url' => $this->absUrl('/exhibition-space/'.$space->slug.'/walkthrough'),
            'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
            'description' => 'Virtual exhibition (digital twin) exported from Heratio.',
            'workFeatured' => $featured,
            'subjectOf' => [
                ['@type' => 'CreativeWork', 'name' => 'IIIF manifest', 'url' => $this->absUrl('/exhibition-space/'.$space->slug.'/manifest.json'), 'encodingFormat' => 'application/ld+json'],
                ['@type' => 'CreativeWork', 'name' => '3D scene manifest', 'url' => $this->absUrl('/exhibition-space/'.$space->slug.'/scene.json'), 'encodingFormat' => 'application/json'],
            ],
        ];
    }

    public function getWalkthroughStops(int $exhibitionSpaceId): array
    {
        $space = $this->getById($exhibitionSpaceId);

        $rows = DB::table('ahg_exhibition_placement as ep')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'ep.information_object_id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'ep.information_object_id')
            ->where('ep.exhibition_space_id', $exhibitionSpaceId)
            ->where(function ($q) {   // corridor objects render at building level, not in this room
                $q->whereNull('ep.wall_or_zone')->orWhere('ep.wall_or_zone', '!=', 'corridor');
            })
            ->select(
                'ep.id', 'ep.information_object_id', 'ep.pos_x', 'ep.pos_y',
                'ep.rotation_deg', 'ep.scale', 'ep.size_units_used', 'ep.wall_or_zone',
                'ep.model_tilt_x', 'ep.model_tilt_z', 'ep.wall_u', 'ep.wall_v', 'ep.spotlight', 'ep.display_case', 'ep.on_floor',
                'ep.view_x', 'ep.view_y',
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
                'size_units_used' => (float) ($r->size_units_used ?? 0),   // Builder "Size (units)" = metres (longest side); drives 3D model scale
                'view_x' => $r->view_x !== null ? (float) $r->view_x : null,   // curator viewing spot (room-local fraction)
                'view_y' => $r->view_y !== null ? (float) $r->view_y : null,
                'wall_or_zone' => $r->wall_or_zone,
                'kind' => $media['kind'],
                'model_url' => $media['model_url'],
                'model_oversize' => ! empty($media['model_oversize']),   // too big to load in the browser -> placeholder
                'model_format' => $media['format'],
                'splat_url' => $media['splat_url'] ?? null,   // #1193 Gaussian splat (in-room DropInViewer)
                'splat_center' => $media['splat_center'] ?? null,
                'splat_radius' => $media['splat_radius'] ?? null,
                'splat_view_url' => $media['splat_view_url'] ?? null,
                'tilt_x' => $r->model_tilt_x !== null ? (float) $r->model_tilt_x : null,
                'tilt_z' => $r->model_tilt_z !== null ? (float) $r->model_tilt_z : null,
                'wall_u' => $r->wall_u !== null ? (float) $r->wall_u : null,
                'wall_v' => $r->wall_v !== null ? (float) $r->wall_v : null,
                'spotlight' => (int) ($r->spotlight ?? 0),
                'display_case' => (int) ($r->display_case ?? 0), 'on_floor' => (int) ($r->on_floor ?? 0),   // #1174 proximity spotlight
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

    // -------- Printable catalogue (the classic museum deliverable) --------

    /**
     * Catalogue entries for a space: the placed objects in reading order, each with
     * a display title, best-effort creator + date, the wall text / caption (the
     * object's scope_and_content), a browser-renderable image (or null), and the
     * record link. Entries are grouped by their wall/zone so a multi-section show
     * prints with a clear structure.
     *
     * Built on getWalkthroughStops() so image + wall-text + record-link resolution
     * (and the curator's saved reading order) are shared with the walkthrough, then
     * enriched with creator/date from the standard AtoM creation event. Every lookup
     * degrades gracefully: a missing image, caption, creator or date simply yields
     * an empty value rather than an error.
     *
     * @return array{
     *   entries: array<int,array<string,mixed>>,
     *   sections: array<int,array{key:string,label:string,entries:array<int,array<string,mixed>>}>,
     *   has_sections: bool
     * }
     */
    public function getCatalogueEntries(int $exhibitionSpaceId): array
    {
        $stops = [];
        try {
            $stops = $this->getWalkthroughStops($exhibitionSpaceId);
        } catch (\Throwable $e) {
            $stops = [];
        }

        // One batched pass for creator + date keeps the per-entry work cheap even
        // for a large show. Missing tables/columns fall back to empty enrichment.
        $ioIds = [];
        foreach ($stops as $s) {
            $id = (int) ($s['information_object_id'] ?? 0);
            if ($id > 0) {
                $ioIds[$id] = true;
            }
        }
        $byId = $this->catalogueCreatorsAndDates(array_keys($ioIds));

        $entries = [];
        $sections = [];        // keyed by zone slug -> [label, entries]
        $position = 0;
        foreach ($stops as $s) {
            $ioId = (int) ($s['information_object_id'] ?? 0);
            $meta = $byId[$ioId] ?? ['creator' => null, 'date' => null];
            $caption = trim((string) ($s['description'] ?? ''));
            $entry = [
                'position' => ++$position,
                'information_object_id' => $ioId,
                'title' => $s['title'] ?: ('#'.$ioId),
                'creator' => $meta['creator'] ?: null,
                'date' => $meta['date'] ?: null,
                'caption' => $caption !== '' ? $caption : null,
                'image_url' => $s['image_url'] ?? ($s['thumb_url'] ?? null),
                'record_url' => $s['record_url'] ?? null,
                'zone' => $s['wall_or_zone'] ?? null,
            ];
            $entries[] = $entry;

            $zone = $s['wall_or_zone'] ?? null;
            $key = ($zone === null || trim((string) $zone) === '') ? '_general' : strtolower(trim((string) $zone));
            if (! isset($sections[$key])) {
                $sections[$key] = [
                    'key' => $key,
                    'label' => $this->catalogueZoneLabel($zone),
                    'entries' => [],
                ];
            }
            $sections[$key]['entries'][] = $entry;
        }

        // Only treat the catalogue as sectioned when objects genuinely split across
        // more than one named zone; a single bucket prints as one flat run.
        $hasSections = count($sections) > 1;

        return [
            'entries' => $entries,
            'sections' => array_values($sections),
            'has_sections' => $hasSections,
        ];
    }

    /**
     * Best-effort creator + display date for a set of information objects, keyed by
     * id. Creator = the actor on the creation event (type_id 111); date = that
     * event's display date, else any event date on the object. Tolerant of missing
     * tables/rows so the catalogue never 500s on sparse data.
     *
     * @param  array<int,int>  $ioIds
     * @return array<int,array{creator:?string,date:?string}>
     */
    private function catalogueCreatorsAndDates(array $ioIds): array
    {
        $out = [];
        if (empty($ioIds)) {
            return $out;
        }

        try {
            $events = DB::table('event')
                ->leftJoin('event_i18n', function ($j) {
                    $j->on('event_i18n.id', '=', 'event.id')->where('event_i18n.culture', '=', 'en');
                })
                ->leftJoin('actor_i18n', function ($j) {
                    $j->on('actor_i18n.id', '=', 'event.actor_id')->where('actor_i18n.culture', '=', 'en');
                })
                ->whereIn('event.object_id', $ioIds)
                ->select(
                    'event.object_id',
                    'event.type_id',
                    'event.start_date',
                    'event_i18n.date as date_display',
                    'actor_i18n.authorized_form_of_name as creator'
                )
                ->get();
        } catch (\Throwable $e) {
            return $out;
        }

        foreach ($events as $ev) {
            $oid = (int) $ev->object_id;
            if (! isset($out[$oid])) {
                $out[$oid] = ['creator' => null, 'date' => null];
            }
            $isCreation = (int) ($ev->type_id ?? 0) === 111;
            $creator = trim((string) ($ev->creator ?? ''));
            if ($creator !== '' && ($out[$oid]['creator'] === null || $isCreation)) {
                $out[$oid]['creator'] = $creator;
            }
            $date = trim((string) ($ev->date_display ?? ''));
            if ($date === '' && ! empty($ev->start_date)) {
                // Fall back to the year from a structured start date.
                $date = substr((string) $ev->start_date, 0, 4);
            }
            if ($date !== '' && ($out[$oid]['date'] === null || $isCreation)) {
                $out[$oid]['date'] = $date;
            }
        }

        return $out;
    }

    /** Human label for a catalogue section from a wall/zone key. */
    private function catalogueZoneLabel(?string $zone): string
    {
        $zone = trim((string) $zone);
        if ($zone === '') {
            return 'Catalogue';
        }
        $named = [
            'north' => 'North wall', 'south' => 'South wall',
            'east' => 'East wall', 'west' => 'West wall',
            'corridor' => 'Corridor',
        ];
        $key = strtolower($zone);
        if (isset($named[$key])) {
            return $named[$key];
        }

        return ucwords(str_replace(['-', '_'], ' ', $zone));
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
