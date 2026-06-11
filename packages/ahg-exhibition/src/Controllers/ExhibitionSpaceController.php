<?php

/**
 * ExhibitionSpaceController - heratio#146 — front-of-house space allocation HTTP layer.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgExhibition\Controllers;

use AhgExhibition\Services\ExhibitionSpaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExhibitionSpaceController extends Controller
{
    protected ExhibitionSpaceService $service;

    public function __construct()
    {
        $this->service = new ExhibitionSpaceService;
    }

    public function browse(Request $request)
    {
        $search = trim((string) $request->input('subquery', ''));
        $pager = $this->service->browse($search, 25);

        return view('ahg-exhibition::exhibition-space.browse', [
            'pager' => $pager,
            'search' => $search,
        ]);
    }

    public function show(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return view('ahg-exhibition::exhibition-space.show', [
            'space' => $space,
            'placements' => $this->service->getPlacements((int) $space->id),
            'spaceTypes' => ExhibitionSpaceService::SPACE_TYPES,
            'capacityUnits' => ExhibitionSpaceService::CAPACITY_UNITS,
        ]);
    }

    public function create()
    {
        return view('ahg-exhibition::exhibition-space.edit', [
            'space' => null,
            'spaceTypes' => ExhibitionSpaceService::SPACE_TYPES,
            'capacityUnits' => ExhibitionSpaceService::CAPACITY_UNITS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        try {
            $id = $this->service->create($validated);
            $space = $this->service->getById($id);

            return redirect()->route('exhibition-space.show', ['slug' => $space->slug])
                ->with('success', 'Exhibition space created.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['name' => $e->getMessage()])->withInput();
        }
    }

    public function edit(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return view('ahg-exhibition::exhibition-space.edit', [
            'space' => $space,
            'spaceTypes' => ExhibitionSpaceService::SPACE_TYPES,
            'capacityUnits' => ExhibitionSpaceService::CAPACITY_UNITS,
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $validated = $this->validated($request);
        $this->service->update((int) $space->id, $validated);

        return redirect()->route('exhibition-space.show', ['slug' => $space->slug])
            ->with('success', 'Exhibition space updated.');
    }

    /** heratio#1195 - publish this space into the RiC graph as a rico:Activity + link its objects. */
    public function syncRic(string $slug, \AhgExhibition\Services\ExhibitionRicService $ric)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $r = $ric->syncSpace($space);
        if (! $r['ok']) {
            return back()->with('error', $r['error'] ?: 'Could not publish to the RiC graph.');
        }
        $msg = "Published to the RiC graph as an Activity. Linked {$r['linked']} object(s)"
            .($r['already'] ? ", {$r['already']} already linked" : '').'.';

        return back()->with('success', $msg);
    }

    public function confirmDelete(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $placementCount = count($this->service->getPlacements((int) $space->id));

        return view('ahg-exhibition::exhibition-space.delete', [
            'space' => $space,
            'placementCount' => $placementCount,
        ]);
    }

    public function destroy(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        try {
            $this->service->delete((int) $space->id);

            return redirect()->route('exhibition-space.browse')->with('success', 'Exhibition space deleted.');
        } catch (\RuntimeException $e) {
            return redirect()->route('exhibition-space.show', ['slug' => $slug])->with('error', $e->getMessage());
        }
    }

    public function placePlacement(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $request->validate([
            'information_object_id' => 'required|integer|min:1',
            'size_units_used' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);
        try {
            $this->service->placePlacement([
                'id' => $request->input('placement_id'),
                'exhibition_space_id' => (int) $space->id,
                'information_object_id' => (int) $request->input('information_object_id'),
                'size_units_used' => (float) $request->input('size_units_used', 0),
                'starts_at' => $request->input('starts_at'),
                'ends_at' => $request->input('ends_at'),
                'exhibition_id' => $request->input('exhibition_id'),
                'notes' => $request->input('notes'),
            ]);

            return redirect()->route('exhibition-space.show', ['slug' => $slug])
                ->with('success', 'Placement saved.');
        } catch (\Throwable $e) {
            return redirect()->route('exhibition-space.show', ['slug' => $slug])
                ->with('error', $e->getMessage());
        }
    }

    public function removePlacement(int $placementId)
    {
        $row = \Illuminate\Support\Facades\DB::table('ahg_exhibition_placement')->where('id', $placementId)->first();
        if (! $row) {
            abort(404);
        }
        $space = $this->service->getById((int) $row->exhibition_space_id);
        $this->service->removePlacement($placementId);
        $slug = $space ? $space->slug : '';

        return redirect()->route('exhibition-space.show', ['slug' => $slug])
            ->with('success', 'Placement removed.');
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'space_type' => 'nullable|string|max:20',
            'capacity_value' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:20',
            'room_w' => 'nullable|numeric|min:1|max:200',
            'room_d' => 'nullable|numeric|min:1|max:200',
            'room_h' => 'nullable|numeric|min:1|max:30',
            'building_id' => 'nullable|string|max:64',
            'building_seq' => 'nullable|integer|min:0',
        ]);

        return $request->only([
            'name', 'space_type', 'building', 'floor',
            'capacity_value', 'capacity_unit',
            'lighting_lux_target', 'notes',
            'room_w', 'room_d', 'room_h', 'building_id', 'building_seq',
        ]);
    }

    // ===================================================================
    // Digital twin - virtual collection builder (heratio#1138, Phase 1)
    // ===================================================================

    public function builder(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        // Layout of sibling rooms so the builder can show doorways where this room
        // adjoins another (auto-openings the walkthrough cuts between adjacent rooms).
        $building = $this->service->getWalkthroughBuilding($space);
        $layout = null;
        if (! empty($building['plan_mode'])) {
            $self = null; $selfFloor = 0; $rects = [];
            foreach ($building['rooms'] as $r) {
                $rects[] = ['x' => $r['x_offset'], 'z' => $r['z_offset'], 'w' => $r['w'], 'd' => $r['d'], 'name' => $r['name'], 'id' => (int) $r['id'], 'floor' => (int) ($r['floor'] ?? 0)];
                if ((int) $r['id'] === (int) $space->id) { $selfFloor = (int) ($r['floor'] ?? 0); }
            }
            $others = [];
            foreach ($rects as $rect) {
                if ($rect['id'] === (int) $space->id) { $self = $rect; }
                elseif ($rect['floor'] === $selfFloor) { $others[] = $rect; }   // same-floor neighbours only (auto-doorways are per-floor)
            }
            if ($self) { $layout = ['self' => $self, 'others' => $others]; }
        }

        return view('ahg-exhibition::exhibition-space.builder', [
            'space' => $space,
            'placements' => $this->service->getPlacementsForBuilder((int) $space->id),
            'capacityUnits' => ExhibitionSpaceService::CAPACITY_UNITS,
            'walls' => $this->service->getWalls((int) $space->id),
            'doors' => $this->service->getDoors((int) $space->id),
            'windows' => $this->service->getWindows((int) $space->id),   // #1172 wall-view
            'shape' => $this->service->getShape((int) $space->id),
            'wallImages' => $this->service->getWallImages((int) $space->id),   // #wall-pictures per-edge overrides
            'furniture' => $this->service->getFurniture((int) $space->id),   // furniture & fittings picker
            'furnitureKinds' => ExhibitionSpaceService::FURNITURE_KINDS,
            'furnitureAssets' => $this->service->listFurnitureAssets(),   // uploaded custom furniture library

            'layout' => $layout,
            'guidedTour' => $this->service->getGuidedTour($space),   // authored audio tour stops
            'tourObjects' => $this->service->buildingTourObjects($space),   // building-wide objects for the tour picker
        ]);
    }

    // ===================================================================
    // Building plan editor (#1143) - arrange rooms on a blueprint
    // ===================================================================

    public function plan(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return view('ahg-exhibition::exhibition-space.plan', [
            'space' => $space,
            'plan' => $this->service->getBuildingPlan($space),
        ]);
    }

    // ===================================================================
    // Wayfinding floor plan + directory (#1217) - first slice of the
    // building-scale museum twin. A read-only 2D top-down "you are here /
    // take me to X" plan: rooms/zones as blocks, each placed object as a
    // labelled dot, plus a searchable directory that deep-links into the
    // 3D walkthrough. Public, like the walkthrough it feeds into.
    // ===================================================================

    public function wayfinding(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        // The whole building (every sibling room sharing building_id), each room
        // carrying its world rect (x_offset/z_offset/w/d) + its placed objects.
        // Guard the assembly so a schema/data hiccup degrades to an empty plan
        // rather than a 500.
        $building = ['rooms' => [], 'min_x' => 0, 'max_x' => 0, 'min_z' => 0, 'max_z' => 0];
        try {
            $building = $this->service->getWalkthroughBuilding($space) ?: $building;
        } catch (\Throwable $e) {
            $building = ['rooms' => [], 'min_x' => 0, 'max_x' => 0, 'min_z' => 0, 'max_z' => 0];
        }

        $plan = $this->buildWayfindingPlan($space, $building);

        return view('ahg-exhibition::exhibition-space.wayfinding', [
            'space' => $space,
            'plan' => $plan,
        ]);
    }

    /**
     * Derive a normalised 2D top-down layout from the building's room rects +
     * each room's object placements. Output is render-ready (SVG viewport coords
     * already scaled), so the Blade view is a thin renderer with no geometry.
     *
     * Geometry: rooms come from getWalkthroughBuilding() in world metres
     * (x_offset/z_offset = top-left, w x d = footprint). An object's world point
     * is room_offset + placement_fraction * room_dimension (pos_x along width,
     * pos_y along depth - the same convention the walkthrough/builder use). We take
     * the bounding box of every room rect (and any positioned object), then scale
     * it to fit a fixed SVG viewport with a margin. Objects with no usable
     * coordinate fall back to a tidy grid laid out under the plan.
     *
     * @param  array<string,mixed>  $building
     * @return array<string,mixed>
     */
    private function buildWayfindingPlan(object $space, array $building): array
    {
        $VIEW_W = 900.0;   // SVG viewport (the view scales it responsively)
        $VIEW_H = 620.0;
        $PAD = 28.0;       // inner margin (px) so labels near the edge are not clipped

        // Stable palette for wall/zone grouping (cycled).
        $palette = ['#0d6efd', '#198754', '#dc3545', '#fd7e14', '#6f42c1', '#0dcaf0', '#d63384', '#20c997'];
        $zoneColors = [];
        $zoneColor = function (?string $zone) use (&$zoneColors, $palette): string {
            $key = ($zone === null || $zone === '') ? '_floor' : strtolower($zone);
            if (! isset($zoneColors[$key])) {
                $zoneColors[$key] = $palette[count($zoneColors) % count($palette)];
            }

            return $zoneColors[$key];
        };
        $zoneLabel = function (?string $zone): string {
            $map = [
                'north' => 'North wall', 'south' => 'South wall',
                'east' => 'East wall', 'west' => 'West wall',
                'corridor' => 'Corridor', 'floor' => 'Floor',
            ];
            if ($zone === null || $zone === '') {
                return 'Floor';
            }
            $k = strtolower($zone);

            return $map[$k] ?? ucfirst($zone);
        };

        $rooms = is_array($building['rooms'] ?? null) ? $building['rooms'] : [];

        // ---- Bounding box of the building (world metres) ----
        $minX = $building['min_x'] ?? null;
        $maxX = $building['max_x'] ?? null;
        $minZ = $building['min_z'] ?? null;
        $maxZ = $building['max_z'] ?? null;
        // Recompute from room rects if the building did not supply a usable box
        // (keeps a single-room space and an ungrouped space both well-framed).
        if (! is_numeric($minX) || ! is_numeric($maxX) || $maxX <= $minX) {
            $minX = $minZ = INF;
            $maxX = $maxZ = -INF;
            foreach ($rooms as $r) {
                $x = (float) ($r['x_offset'] ?? 0);
                $z = (float) ($r['z_offset'] ?? 0);
                $w = max(0.1, (float) ($r['w'] ?? 1));
                $d = max(0.1, (float) ($r['d'] ?? 1));
                $minX = min($minX, $x);
                $maxX = max($maxX, $x + $w);
                $minZ = min($minZ, $z);
                $maxZ = max($maxZ, $z + $d);
            }
        }
        if (! is_finite((float) $minX) || ! is_finite((float) $maxX) || $maxX <= $minX) {
            // No rooms / no geometry at all: a unit box so the scale math is safe.
            $minX = 0.0;
            $maxX = 1.0;
            $minZ = 0.0;
            $maxZ = 1.0;
        }
        $spanX = max(0.001, (float) $maxX - (float) $minX);
        $spanZ = max(0.001, (float) $maxZ - (float) $minZ);
        $scale = min(($VIEW_W - 2 * $PAD) / $spanX, ($VIEW_H - 2 * $PAD) / $spanZ);
        // Centre the plan in the viewport.
        $offX = $PAD + (($VIEW_W - 2 * $PAD) - $spanX * $scale) / 2;
        $offZ = $PAD + (($VIEW_H - 2 * $PAD) - $spanZ * $scale) / 2;
        $px = fn (float $worldX): float => $offX + ($worldX - (float) $minX) * $scale;
        $pz = fn (float $worldZ): float => $offZ + ($worldZ - (float) $minZ) * $scale;

        // ---- Rooms as blocks + their object dots ----
        $svgRooms = [];
        $directory = [];   // flat searchable list (positioned + unpositioned)
        $dots = [];        // positioned object dots (id keyed for highlight)
        $unpositioned = []; // objects without usable coordinates

        foreach ($rooms as $ri => $r) {
            $rx = (float) ($r['x_offset'] ?? 0);
            $rz = (float) ($r['z_offset'] ?? 0);
            $rw = max(0.1, (float) ($r['w'] ?? 1));
            $rd = max(0.1, (float) ($r['d'] ?? 1));
            $roomName = (string) ($r['name'] ?? ('Room '.($ri + 1)));
            $svgRooms[] = [
                'name' => $roomName,
                'is_current' => ! empty($r['is_current']),
                'floor' => (int) ($r['floor'] ?? 0),
                'x' => round($px($rx), 1),
                'y' => round($pz($rz), 1),
                'w' => round($rw * $scale, 1),
                'h' => round($rd * $scale, 1),
                'cx' => round($px($rx + $rw / 2), 1),
                'cy' => round($pz($rz + $rd / 2), 1),
            ];

            $stops = is_array($r['stops'] ?? null) ? $r['stops'] : [];
            foreach ($stops as $s) {
                $ioId = (int) ($s['information_object_id'] ?? 0);
                $title = (string) ($s['title'] ?? ('#'.$ioId));
                $zone = $s['wall_or_zone'] ?? null;
                $entry = [
                    'dot_id' => 'd'.count($directory),
                    'io_id' => $ioId,
                    'title' => $title,
                    'room' => $roomName,
                    'zone' => $zone,
                    'zone_label' => $zoneLabel($zone),
                    'color' => $zoneColor($zone),
                    'record_url' => $s['record_url'] ?? null,
                    'positioned' => false,
                    'x' => null,
                    'y' => null,
                ];

                $fx = $s['pos_x'] ?? null;
                $fy = $s['pos_y'] ?? null;
                if (is_numeric($fx) && is_numeric($fy)) {
                    $wx = $rx + max(0.0, min(1.0, (float) $fx)) * $rw;
                    $wz = $rz + max(0.0, min(1.0, (float) $fy)) * $rd;
                    $entry['positioned'] = true;
                    $entry['x'] = round($px($wx), 1);
                    $entry['y'] = round($pz($wz), 1);
                    $dots[] = $entry;
                } else {
                    $unpositioned[] = $entry;
                }
                $directory[] = $entry;
            }
        }

        // ---- Fallback grid for objects with no usable coordinate ----
        // Laid out under the plan so the dot/entry highlight still works for them.
        if (! empty($unpositioned)) {
            $cols = max(1, (int) floor(($VIEW_W - 2 * $PAD) / 90));
            $gx0 = $PAD + 18;
            $gy0 = $VIEW_H + 34;   // below the plan band (the view extends the canvas)
            $i = 0;
            foreach ($directory as &$d) {
                if ($d['positioned']) {
                    continue;
                }
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $d['x'] = round($gx0 + $col * 90, 1);
                $d['y'] = round($gy0 + $row * 30, 1);
                $dots[] = $d;
                $i++;
            }
            unset($d);
        }

        // Sort the directory alphabetically for the "take me to X" list.
        usort($directory, fn ($a, $b) => strcasecmp($a['title'], $b['title']));

        // Distinct zone legend (only zones actually present).
        $legend = [];
        foreach ($zoneColors as $k => $c) {
            $legend[] = ['label' => $zoneLabel($k === '_floor' ? null : $k), 'color' => $c];
        }

        $gridRows = empty($unpositioned) ? 0 : (int) ceil(count($unpositioned) / max(1, (int) floor(($VIEW_W - 2 * $PAD) / 90)));

        return [
            'view_w' => $VIEW_W,
            'view_h' => $VIEW_H + ($gridRows > 0 ? ($gridRows * 30 + 48) : 0),
            'plan_h' => $VIEW_H,
            'rooms' => $svgRooms,
            'dots' => $dots,
            'directory' => $directory,
            'legend' => $legend,
            'has_grid_fallback' => ! empty($unpositioned),
            'object_count' => count($directory),
            'room_count' => count($svgRooms),
            'walkthrough_url' => route('exhibition-space.walkthrough', ['slug' => $space->slug]),
        ];
    }

    /** AJAX: save a room's plan position (+ optional size). */
    public function savePlanAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'room_id' => 'required|integer|min:1',
            'x' => 'required|numeric', 'y' => 'required|numeric',
            'w' => 'nullable|numeric', 'd' => 'nullable|numeric', 'rot' => 'nullable|numeric',
        ]);
        $ok = $this->service->savePlanRoom((int) $space->id, (int) $data['room_id'], (float) $data['x'], (float) $data['y'],
            isset($data['w']) ? (float) $data['w'] : null, isset($data['d']) ? (float) $data['d'] : null,
            isset($data['rot']) ? (float) $data['rot'] : null);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: set plan group keys on rooms (#1143 move-as-one-unit). */
    public function savePlanGroupAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'groups' => 'required|array',
            'groups.*.room_id' => 'required|integer|min:1',
            'groups.*.group' => 'nullable|string|max:40',
        ]);
        $n = $this->service->setRoomGroups($space, $data['groups']);

        return response()->json(['ok' => true, 'updated' => $n]);
    }

    /** AJAX: save building stairs (#1169 plan-editor authoring). */
    public function savePlanStairsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'stairs' => 'present|array',
            'stairs.*.x' => 'required|numeric',
            'stairs.*.z' => 'required|numeric',
            'stairs.*.from_floor' => 'nullable|integer',
            'stairs.*.to_floor' => 'nullable|integer',
            'stairs.*.from_room' => 'nullable|integer',
            'stairs.*.to_room' => 'nullable|integer',
            'stairs.*.width' => 'nullable|numeric',
            'stairs.*.length' => 'nullable|numeric',
            'stairs.*.length2' => 'nullable|numeric',
            'stairs.*.rot' => 'nullable|numeric',
            'stairs.*.hand' => 'nullable|string|in:left,right',
            'stairs.*.kind' => 'nullable|string|in:straight,elbow',
        ]);
        $this->service->saveBuildingStairs($space, $data['stairs']);

        return response()->json(['ok' => true]);
    }

    /** AJAX: set a room's floor level (#1169 multi-floor). */
    public function savePlanRoomFloorAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['room_id' => 'required|integer|min:1', 'floor' => 'required|integer|min:-5|max:20']);
        $ok = $this->service->setRoomFloor($space, (int) $data['room_id'], (int) $data['floor']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: lock/unlock a room (#1143). */
    public function savePlanRoomLockAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['room_id' => 'required|integer|min:1', 'locked' => 'required|boolean']);
        $ok = $this->service->setRoomLocked($space, (int) $data['room_id'], (bool) $data['locked']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: delete a room from the building. */
    public function deleteRoomAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['room_id' => 'required|integer|min:1']);
        $ok = $this->service->deleteBuildingRoom($space, (int) $data['room_id']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: save the blueprint's world rectangle (metres) after move/resize. */
    public function planImageRectAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'x' => 'required|numeric', 'y' => 'required|numeric',
            'w' => 'required|numeric|min:0.5', 'h' => 'required|numeric|min:0.5',
        ]);
        $this->service->savePlanImageRect($space, (float) $data['x'], (float) $data['y'], (float) $data['w'], (float) $data['h']);

        return response()->json(['ok' => true]);
    }

    /** In-twin recommendations (heratio#1149): related objects for an information object. */
    public function recommendAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $io = (int) $request->query('io', 0);
        if ($io <= 0) {
            return response()->json(['ok' => true, 'items' => $this->service->roomRecommendations($space)]);   // #1149 picker: all room suggestions
        }
        $items = $this->service->recommendations($space, $io);
        foreach ($items as &$it) {
            if (empty($it['thumb_url'])) {
                $it['thumb_url'] = $this->service->thumbnailUrl((int) $it['io_id']);
            }
        }

        return response()->json(['ok' => true, 'items' => $items]);
    }

    /** Public: AI-describe an object (walkthrough "T = talk" docent). ?fresh=1 forces a
     *  brand-new AI description even when one is cached (heratio#1167). */
    public function describeObjectAjax(Request $request, int $ioId)
    {
        if ($request->boolean('fresh')) {
            \Illuminate\Support\Facades\Cache::forget('exh_ai_desc_'.$ioId);
        }
        $desc = $this->service->aiDescribeObject($ioId);

        return response()->json(['ok' => $desc !== null, 'description' => $desc]);
    }

    /** heratio#1185 - AI docent: answer a visitor's question about an object, grounded in its catalogue record. */
    public function askObjectAjax(Request $request, int $ioId)
    {
        $data = $request->validate(['q' => 'required|string|max:300']);
        $answer = $this->service->aiAnswerAboutObject($ioId, $data['q']);

        return response()->json(['ok' => $answer !== null, 'answer' => $answer]);
    }

    /**
     * heratio#1185 - AI docent, ROOM scope: answer a visitor's free-form question about the
     * whole exhibition, grounded ONLY in the objects placed in this building. Public, read-only.
     */
    public function askRoomAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['q' => 'required|string|max:300']);
        $answer = $this->service->aiAnswerAboutRoom($space, $data['q']);

        return response()->json(['ok' => $answer !== null, 'answer' => $answer]);
    }

    /**
     * heratio#1185 - conversational room docent (multi-turn, room-aware). Carries the recent
     * transcript + the visitor's location, returns a grounded answer plus a suggested next
     * object to walk to. POST (history can exceed a query string). Public + read-only.
     */
    public function converseRoomAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'q' => 'required|string|max:300',
            'history' => 'sometimes|array',
            'history.*.q' => 'sometimes|nullable|string|max:300',
            'history.*.a' => 'sometimes|nullable|string|max:600',
            'near_io' => 'sometimes|nullable|integer',
            'room_id' => 'sometimes|nullable|integer',
        ]);
        $res = $this->service->aiConverseRoom(
            $space,
            $data['q'],
            $data['history'] ?? [],
            $data['near_io'] ?? null,
            $data['room_id'] ?? null
        );

        return response()->json(['ok' => $res['answer'] !== null, 'answer' => $res['answer'], 'suggest' => $res['suggest']]);
    }

    /** heratio#1185 - suggested follow-up question chips for the room docent (grounded in real objects). */
    public function roomQuestionsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json(['ok' => true, 'questions' => $this->service->roomSuggestedQuestions($space)]);
    }

    /**
     * #1168 - neural TTS for the walkthrough narration. Synthesises $text via the
     * AI gateway (Piper) and returns WAV; 502 if unavailable so the client falls
     * back to browser speech. Public (the walkthrough is public).
     */
    public function ttsAjax(Request $request)
    {
        $text = trim((string) $request->input('text', ''));
        if ($text === '') {
            return response()->noContent();
        }
        if (! class_exists(\AhgAiServices\Services\TtsService::class)) {
            return response('', 502);
        }
        $audio = app(\AhgAiServices\Services\TtsService::class)->synthesize($text, $request->input('voice'));
        if ($audio === null) {
            return response('', 502);
        }

        return response($audio, 200)
            ->header('Content-Type', 'audio/wav')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /** heratio#1165 - add a wall graffiti annotation (public, walkthrough). */
    public function annotationAddAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $saved = $this->service->addAnnotation($space, $request->all());

        return response()->json(['ok' => $saved !== null, 'annotation' => $saved]);
    }

    /** heratio#1165 - delete a graffiti annotation (click-to-delete). */
    public function annotationDeleteAjax(string $slug, int $id)
    {
        $space = $this->service->getBySlug($slug);
        if ($space) {
            $this->service->deleteAnnotation($space, $id);
        }

        return response()->json(['ok' => true]);
    }

    /** Save the authored audio guided tour (curator). */
    public function saveGuidedTourAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $this->service->saveGuidedTour($space, $request->input('tours', []));

        return response()->json(['ok' => true]);
    }

    /** AJAX: upload a pre-recorded narration clip for a tour stop; returns its web path. */
    public function uploadTourAudio(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        // Validate by extension (audio MIME varies by browser/OS); keep it small-ish.
        $request->validate([
            'audio' => 'required|file|extensions:mp3,wav,m4a,ogg,aac|max:25600',
        ]);
        $file = $request->file('audio');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        $dir = config('heratio.storage_path').'/uploads/exhibition-tour-audio';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $base = \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'narration';
        $filename = $base.'-'.substr(md5((string) microtime(true)), 0, 8).'.'.$ext;
        $file->move($dir, $filename);

        return response()->json(['ok' => true, 'path' => '/uploads/exhibition-tour-audio/'.$filename]);
    }

    /** heratio#1150 - multi-user presence heartbeat: upsert my pose, return live peers + tour state. */
    public function presenceBeatAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        // Only authenticated staff may act as a docent / drive the guided tour.
        $isDocent = auth()->check();
        $state = $this->service->presenceBeat($space, $request->all(), $isDocent);

        return response()->json(['ok' => true, 'can_docent' => $isDocent] + $state);
    }

    /** heratio#1150 - drop my presence row when I leave the walkthrough (sendBeacon). */
    public function presenceLeaveAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if ($space) {
            $this->service->presenceLeave($space, (string) $request->input('token', ''));
        }

        return response()->json(['ok' => true]);
    }

    /** heratio#1173 - log a visitor event (object view etc) from the walkthrough. */
    public function visitEventAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if ($space) {
            $this->service->recordVisitEvent(
                $space,
                (string) $request->input('token', ''),
                (string) $request->input('type', 'object'),
                $request->input('room_id') !== null ? (int) $request->input('room_id') : null,
                $request->input('object_id') !== null ? (int) $request->input('object_id') : null,
            );
        }

        return response()->json(['ok' => true]);
    }

    // ---- heratio#1151 interoperability: open-standard exports (public, read-only) ----

    /** IIIF Presentation 3.0 manifest for the exhibition. */
    public function iiifManifest(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return response()->json($this->service->iiifManifest($space), 200, ['Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Open 3D scene manifest (rooms + placements + media URLs) for any 3D viewer. */
    public function sceneExport(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return response()->json($this->service->sceneManifest($space), 200, ['Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** schema.org ExhibitionEvent JSON-LD for linked-data discovery. */
    public function exhibitionJsonLd(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return response()->json($this->service->exhibitionJsonLd($space), 200, ['Content-Type' => 'application/ld+json', 'Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Admin: precompute AI recommendations across the building via the AI gateway. */
    public function generateRecommendationsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json($this->service->generateAiRecommendations($space));
    }

    /** Analytics dashboard (heratio#1148): historical reading trends per room. */
    public function analytics(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $days = (int) $request->query('days', 7);

        return view('ahg-exhibition::exhibition-space.analytics', [
            'space' => $space,
            'days' => $days,
            'data' => $this->service->buildingAnalytics($space, $days),
            'visitors' => $this->service->visitorAnalytics($space, $days),   // #1173
            'heatmap' => $this->service->visitorHeatmap($space, $days),      // #1187
            'sensor' => auth()->check()                                       // #1188 (token only for logged-in staff)
                ? ['token' => $this->service->getOrCreateSensorToken((int) $space->id), 'alerts' => $this->service->recentAlerts($space)]
                : null,
        ]);
    }

    /** Conservation forecast page (heratio#1147): projected light dose, risk, visitors. */
    public function forecast(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return view('ahg-exhibition::exhibition-space.forecast', [
            'space' => $space,
            'rooms' => $this->service->buildingForecast($space),
            'timeline' => $this->service->conservationTimeline($space),   // #1189 time-scrubber
        ]);
    }

    /**
     * Live data link (heratio#1146): ingest sensor/occupancy readings for a space.
     * Accepts a single {metric,value,recorded_at?} or {readings:[...]} batch.
     */
    public function recordReadingsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $batch = $request->input('readings');
        if (! is_array($batch)) {
            $batch = [['metric' => $request->input('metric'), 'value' => $request->input('value'), 'recorded_at' => $request->input('recorded_at')]];
        }
        $n = 0;
        foreach ($batch as $r) {
            if (! isset($r['metric']) || ! isset($r['value']) || ! is_numeric($r['value'])) {
                continue;
            }
            $this->service->recordReading((int) $space->id, (string) $r['metric'], (float) $r['value'], $r['recorded_at'] ?? null);
            $n++;
        }

        return response()->json(['ok' => true, 'recorded' => $n, 'live' => $this->service->liveState($space)]);
    }

    /**
     * heratio#1188 - public sensor/gateway ingest, authenticated by a per-space token
     * (header X-Sensor-Token or body "token"). No session/CSRF, so real IoT devices can POST.
     */
    public function sensorIngestAjax(Request $request)
    {
        $token = (string) ($request->header('X-Sensor-Token') ?: $request->input('token', ''));
        $batch = $request->input('readings');
        if (! is_array($batch)) {
            $batch = [['metric' => $request->input('metric'), 'value' => $request->input('value'), 'recorded_at' => $request->input('recorded_at')]];
        }
        $result = $this->service->ingestSensor($token, $batch);
        if ($result === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid sensor token.'], 401);
        }

        return response()->json(['ok' => true] + $result);
    }

    /** heratio#1188 - rotate a space's sensor token (admin). */
    public function regenerateSensorTokenAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json(['ok' => true, 'token' => $this->service->regenerateSensorToken((int) $space->id)]);
    }

    /** Seed demo readings across the building so the live overlay is visible. */
    public function simulateReadingsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $n = $this->service->simulateReadings($space);

        return response()->json(['ok' => true, 'recorded' => $n]);
    }

    /** AJAX: add a new room to this space's building (from the plan editor). */
    public function addRoomAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $room = $this->service->addBuildingRoom($space, $request->input('name'));

        return response()->json(['ok' => true, 'room' => $room]);
    }

    /** AJAX: save a room's footprint polygon (normalized points), or clear it. */
    public function saveShapeAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'room_id' => 'required|integer|min:1',
            'points' => 'nullable|array',
        ]);
        $room = $this->service->getById((int) $data['room_id']);
        if (! $room || (($room->building_id ?? null) !== ($space->building_id ?? null) && (int) $room->id !== (int) $space->id)) {
            return response()->json(['ok' => false], 403);
        }
        $this->service->saveShape((int) $room->id, $data['points'] ?? null);

        return response()->json(['ok' => true, 'shape' => $this->service->getShape((int) $room->id)]);
    }

    /** AJAX: add a corridor object (building-space) at building-fraction (x,y). */
    public function corridorAddAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'information_object_id' => 'required|integer|min:1',
            'x' => 'required|numeric', 'y' => 'required|numeric',
        ]);
        $placement = $this->service->createCorridorPlacement($space, (int) $data['information_object_id'], (float) $data['x'], (float) $data['y']);

        return response()->json(['ok' => true, 'placement' => $placement]);
    }

    /** AJAX: move a corridor object to building-fraction (x,y). */
    public function corridorMoveAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'placement_id' => 'required|integer|min:1',
            'x' => 'required|numeric', 'y' => 'required|numeric',
        ]);
        $ok = $this->service->moveCorridorPlacement($space, (int) $data['placement_id'], (float) $data['x'], (float) $data['y']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: remove a corridor object. */
    public function corridorRemoveAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['placement_id' => 'required|integer|min:1']);
        // Scope: only remove a corridor placement that belongs to this building.
        $corridor = collect($this->service->getBuildingCorridorObjects($space))->firstWhere('id', (int) $data['placement_id']);
        if (! $corridor) {
            return response()->json(['ok' => false], 403);
        }
        $ok = $this->service->removePlacement((int) $data['placement_id']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: save the doors for one room of this building. */
    public function saveDoorsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'room_id' => 'required|integer|min:1',
            'doors' => 'present|array',
        ]);
        // Only allow editing rooms in the same building (or the space itself).
        $room = $this->service->getById((int) $data['room_id']);
        if (! $room || (($room->building_id ?? null) !== ($space->building_id ?? null) && (int) $room->id !== (int) $space->id)) {
            return response()->json(['ok' => false], 403);
        }
        $this->service->saveDoors((int) $room->id, $data['doors']);

        return response()->json(['ok' => true, 'doors' => $this->service->getDoors((int) $room->id)]);
    }

    /** heratio#1172 - save a room's windows from the plan editor. */
    public function saveWindowsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'room_id' => 'required|integer|min:1',
            'windows' => 'present|array',
        ]);
        $room = $this->service->getById((int) $data['room_id']);
        if (! $room || (($room->building_id ?? null) !== ($space->building_id ?? null) && (int) $room->id !== (int) $space->id)) {
            return response()->json(['ok' => false], 403);
        }
        $this->service->saveWindows((int) $room->id, $data['windows']);

        return response()->json(['ok' => true, 'windows' => $this->service->getWindows((int) $room->id)]);
    }

    /** Upload a building plan / blueprint image (background for the plan editor). */
    public function uploadBuildingPlan(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $request->validate(['plan_image' => 'required|image|mimes:jpeg,png,webp,svg|max:8192']);
        $file = $request->file('plan_image');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $dir = config('heratio.storage_path').'/uploads/exhibition-plans';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = ($space->building_id ?: $space->slug).'-'.substr(md5((string) microtime(true)), 0, 8).'.'.$ext;
        $file->move($dir, $filename);
        $this->service->setBuildingPlanImage($space, '/uploads/exhibition-plans/'.$filename);

        return redirect()->route('exhibition-space.plan', ['slug' => $slug])->with('success', 'Building plan uploaded.');
    }

    /** Clear the building plan image. */
    public function clearBuildingPlan(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $this->service->setBuildingPlanImage($space, null);

        return redirect()->route('exhibition-space.plan', ['slug' => $slug])->with('success', 'Building plan cleared.');
    }

    /** AJAX: persist interior wall segments for a space. */
    public function saveWallsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'walls' => 'present|array',
            'walls.*.x1' => 'required|numeric',
            'walls.*.z1' => 'required|numeric',
            'walls.*.x2' => 'required|numeric',
            'walls.*.z2' => 'required|numeric',
            'walls.*.id' => 'nullable|string|max:40',
        ]);
        $this->service->saveWalls((int) $space->id, $data['walls']);

        return response()->json(['ok' => true, 'walls' => $this->service->getWalls((int) $space->id)]);
    }

    /** AJAX: assign a placement to a specific wall (empty = auto nearest). */
    public function updateWallAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'placement_id' => 'required|integer|min:1',
            'wall' => 'nullable|string|max:40',
        ]);
        $ok = $this->service->updatePlacementWall((int) $space->id, (int) $data['placement_id'], $data['wall'] ?? null);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: current placements for the space (used to rebuild the wall view). */
    public function placementsJson(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json(['ok' => true, 'placements' => $this->service->getPlacementsForBuilder((int) $space->id)]);
    }

    /** AJAX: create a placement hung on a wall at along-wall u + height v (wall view). */
    public function placeWallAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'information_object_id' => 'required|integer|min:1',
            'wall' => 'required|string|max:40',
            'u' => 'required|numeric', 'v' => 'required|numeric',
        ]);
        try {
            $p = $this->service->placeOnWall((int) $space->id, (int) $data['information_object_id'], $data['wall'], (float) $data['u'], (float) $data['v']);

            return response()->json(['ok' => true, 'placement' => $p]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** AJAX: update an object's wall position (u along, v height) in wall view. */
    public function updateWallPosAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'placement_id' => 'required|integer|min:1',
            'u' => 'required|numeric', 'v' => 'required|numeric',
        ]);
        $ok = $this->service->updateWallPos((int) $space->id, (int) $data['placement_id'], (float) $data['u'], (float) $data['v']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: persist canvas positions for the whole layout. */
    public function saveLayout(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'positions' => 'required|array',
            'positions.*.id' => 'required|integer|min:1',
            'positions.*.pos_x' => 'nullable|numeric',
            'positions.*.pos_y' => 'nullable|numeric',
            'positions.*.rotation_deg' => 'nullable|numeric',
            'positions.*.scale' => 'nullable|numeric',
            'positions.*.z_order' => 'nullable|integer',
        ]);
        $saved = $this->service->saveLayout((int) $space->id, $data['positions']);

        return response()->json(['ok' => true, 'saved' => $saved]);
    }

    /** AJAX: create a placement dropped onto the canvas; returns its builder row. */
    public function placeAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'information_object_id' => 'required|integer|min:1',
            'pos_x' => 'required|numeric',
            'pos_y' => 'required|numeric',
            'size_units_used' => 'nullable|numeric|min:0',
        ]);
        try {
            $placement = $this->service->createPlacementAt(
                (int) $space->id,
                (int) $data['information_object_id'],
                (float) $data['pos_x'],
                (float) $data['pos_y'],
                (float) ($data['size_units_used'] ?? 0)
            );

            return response()->json(['ok' => true, 'placement' => $placement]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** AJAX: remove a placement from the builder. */
    public function removeAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $placementId = (int) $request->input('placement_id');
        $row = \Illuminate\Support\Facades\DB::table('ahg_exhibition_placement')
            ->where('id', $placementId)->where('exhibition_space_id', $space->id)->first();
        if (! $row) {
            return response()->json(['ok' => false, 'error' => 'Placement not found.'], 404);
        }
        $this->service->removePlacement($placementId);

        return response()->json(['ok' => true]);
    }

    /** AJAX: update a placement's capacity size from the builder size editor. */
    public function updateSizeAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'placement_id' => 'required|integer|min:1',
            'size_units_used' => 'required|numeric|min:0',
        ]);
        $ok = $this->service->updatePlacementSize((int) $space->id, (int) $data['placement_id'], (float) $data['size_units_used']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: per-object 3D orientation (tilt) from the builder. Null = auto. */
    public function updateTiltAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'placement_id' => 'required|integer|min:1',
            'tilt_x' => 'nullable|numeric',
            'tilt_z' => 'nullable|numeric',
        ]);
        $tx = isset($data['tilt_x']) && $data['tilt_x'] !== null ? (float) $data['tilt_x'] : null;
        $tz = isset($data['tilt_z']) && $data['tilt_z'] !== null ? (float) $data['tilt_z'] : null;
        $ok = $this->service->updatePlacementTilt((int) $space->id, (int) $data['placement_id'], $tx, $tz);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: toggle a per-object spotlight (#1174). */
    public function updateSpotlightAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'placement_id' => 'required|integer|min:1',
            'mode' => 'nullable|integer|min:0|max:2',
            'on' => 'nullable|boolean',
        ]);
        // mode: 0 off, 1 on-approach, 2 always-on. Fall back to the legacy boolean `on` (true => 1).
        $mode = array_key_exists('mode', $data) && $data['mode'] !== null
            ? (int) $data['mode']
            : (! empty($data['on']) ? 1 : 0);
        $ok = $this->service->updatePlacementSpotlight((int) $space->id, (int) $data['placement_id'], $mode);

        return response()->json(['ok' => $ok, 'mode' => $mode]);
    }

    /** AJAX: toggle showing this item inside a glass display case. */
    public function updateDisplayCaseAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['placement_id' => 'required|integer|min:1', 'on' => 'required|boolean']);
        $ok = $this->service->updatePlacementDisplayCase((int) $space->id, (int) $data['placement_id'], (bool) $data['on']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: toggle standing a 3D model directly on the floor (no pedestal). */
    public function updateOnFloorAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['placement_id' => 'required|integer|min:1', 'on' => 'required|boolean']);
        $ok = $this->service->updatePlacementOnFloor((int) $space->id, (int) $data['placement_id'], (bool) $data['on']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: set/clear the curator viewing spot for a placement (room-local fraction; null clears). */
    public function updatePlacementViewAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'placement_id' => 'required|integer|min:1',
            'view_x' => 'nullable|numeric',
            'view_y' => 'nullable|numeric',
        ]);
        $vx = array_key_exists('view_x', $data) && $data['view_x'] !== null ? (float) $data['view_x'] : null;
        $vy = array_key_exists('view_y', $data) && $data['view_y'] !== null ? (float) $data['view_y'] : null;
        $ok = $this->service->updatePlacementView((int) $space->id, (int) $data['placement_id'], $vx, $vy);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: bring-to-front / send-to-back (z-order). */
    public function updateZOrderAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['placement_id' => 'required|integer|min:1', 'z' => 'required|integer']);
        $ok = $this->service->updatePlacementZOrder((int) $space->id, (int) $data['placement_id'], (int) $data['z']);

        return response()->json(['ok' => $ok]);
    }

    /** Upload a floorplan background image for the builder canvas. */
    public function uploadFloorplan(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $request->validate([
            'floorplan' => 'required|image|mimes:jpeg,jpg,png,webp,svg|max:51200',
            'floorplan_width_m' => 'nullable|numeric|min:0',
            'floorplan_height_m' => 'nullable|numeric|min:0',
        ]);

        $file = $request->file('floorplan');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $dir = config('heratio.storage_path').'/uploads/exhibition-floorplans';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = $space->slug.'-'.substr(md5((string) microtime(true)), 0, 8).'.'.$ext;
        $file->move($dir, $filename);
        $publicPath = '/uploads/exhibition-floorplans/'.$filename;

        $this->service->setFloorplan(
            (int) $space->id,
            $publicPath,
            $request->input('floorplan_width_m') !== null ? (float) $request->input('floorplan_width_m') : null,
            $request->input('floorplan_height_m') !== null ? (float) $request->input('floorplan_height_m') : null
        );

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])
            ->with('success', 'Floorplan uploaded.');
    }

    /** Upload a ceiling image (painted ceiling) for the 3D room. */
    public function uploadCeiling(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $request->validate(['ceiling' => 'required|image|mimes:jpeg,jpg,png,webp|max:51200']);

        $file = $request->file('ceiling');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $dir = config('heratio.storage_path').'/uploads/exhibition-ceilings';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = $space->slug.'-'.substr(md5((string) microtime(true)), 0, 8).'.'.$ext;
        $file->move($dir, $filename);
        $this->service->setCeiling((int) $space->id, '/uploads/exhibition-ceilings/'.$filename);

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])
            ->with('success', 'Ceiling image uploaded.');
    }

    /** Clear the room ceiling image. */
    public function clearCeiling(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $this->service->setCeiling((int) $space->id, null);

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])
            ->with('success', 'Ceiling image cleared.');
    }

    /** Upload a decorated/painted wall image (applied to the room's walls). */
    public function uploadWallImage(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $request->validate([
            'wall_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:51200',
            'edge' => 'nullable|integer|min:0|max:99',   // target wall; absent or all=1 => all walls (room default)
            'all' => 'nullable|boolean',
        ]);
        $file = $request->file('wall_image');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $dir = config('heratio.storage_path').'/uploads/exhibition-walls';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = $space->slug.'-'.substr(md5((string) microtime(true)), 0, 8).'.'.$ext;
        $file->move($dir, $filename);
        $path = '/uploads/exhibition-walls/'.$filename;
        $allWalls = $request->boolean('all') || $request->input('edge', null) === null;
        if ($allWalls) {
            $this->service->setWallImage((int) $space->id, $path);
        } else {
            $this->service->setWallImageForEdge((int) $space->id, (int) $request->input('edge'), $path);
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'path' => $path, 'filename' => $filename, 'all' => $allWalls, 'edge' => $allWalls ? null : (int) $request->input('edge')]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Wall image uploaded.');
    }

    /** Clear the decorated wall image - the all-walls default, or one wall (edge). */
    public function clearWallImage(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $allWalls = $request->boolean('all') || $request->input('edge', null) === null;
        if ($allWalls) {
            $this->service->setWallImage((int) $space->id, null);
        } else {
            $this->service->setWallImageForEdge((int) $space->id, (int) $request->input('edge'), null);
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'all' => $allWalls, 'edge' => $allWalls ? null : (int) $request->input('edge')]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Wall image cleared.');
    }

    /** Upload a decorative floor picture (stretched over the whole room floor). Mirrors uploadWallImage. */
    public function uploadFloorImage(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $request->validate([
            'floor_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:51200',
        ]);
        $file = $request->file('floor_image');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $dir = config('heratio.storage_path').'/uploads/exhibition-floors';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = $space->slug.'-'.substr(md5((string) microtime(true)), 0, 8).'.'.$ext;
        $file->move($dir, $filename);
        $path = '/uploads/exhibition-floors/'.$filename;
        $this->service->setFloorImage((int) $space->id, $path);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'path' => $path, 'filename' => $filename]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Floor image uploaded.');
    }

    /** Set this room's floor tiling: grout grid on/off + tile size (m). */
    public function setFloorGroutAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['on' => 'required|boolean', 'tile_m' => 'nullable|numeric|min:0.25|max:10', 'grout_mm' => 'nullable|numeric|min:0.5|max:100']);
        $this->service->setFloorTiling((int) $space->id, (bool) $data['on'], isset($data['tile_m']) ? (float) $data['tile_m'] : null, isset($data['grout_mm']) ? (float) $data['grout_mm'] : null);

        return response()->json(['ok' => true, 'on' => (bool) $data['on'], 'tile_m' => isset($data['tile_m']) ? (float) $data['tile_m'] : null, 'grout_mm' => isset($data['grout_mm']) ? (float) $data['grout_mm'] : null]);
    }

    /** Clear the decorative floor picture (reverts to floorplan/marble). */
    public function clearFloorImage(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $this->service->setFloorImage((int) $space->id, null);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Floor image cleared.');
    }

    /**
     * heratio#1156: upload a photoreal capture shell (photogrammetry / glTF / OBJ /
     * scan export) to back the room in the 3D walkthrough. Mirrors uploadFloorImage:
     * stored under /uploads/exhibition-scans, rendered into the room group additively
     * so object placements and the live overlay still sit on top.
     */
    public function uploadScanShell(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        // Mesh shells (GLTFLoader / OBJ / STL / mesh-PLY, #1156) + point clouds (#1183:
        // .pcd via PCDLoader, point-cloud .ply via PLYLoader). Validate by extension - .pcd
        // has no registered MIME type, so the `mimes:` rule can't be used here.
        $request->validate(['scan_shell' => 'required|file|max:204800']);
        $file = $request->file('scan_shell');
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $allowed = ['glb', 'gltf', 'obj', 'stl', 'ply', 'pcd'];
        if (! in_array($ext, $allowed, true)) {
            $msg = 'Unsupported file type. Use glTF/GLB, OBJ, STL, PLY (mesh or point cloud), or PCD. For .las/.e57, export to PLY or PCD first.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $msg], 422);
            }

            return redirect()->route('exhibition-space.builder', ['slug' => $slug])->withErrors(['scan_shell' => $msg]);
        }
        $dir = config('heratio.storage_path').'/uploads/exhibition-scans';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = $space->slug.'-'.substr(md5((string) microtime(true)), 0, 8).'.'.$ext;
        $file->move($dir, $filename);
        $path = '/uploads/exhibition-scans/'.$filename;
        $this->service->setScanShell((int) $space->id, $path);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'path' => $path, 'filename' => $filename, 'ext' => $ext]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Scan shell uploaded.');
    }

    /** heratio#1156: remove the room's photoreal capture shell. */
    public function clearScanShell(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $this->service->setScanShell((int) $space->id, null);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Scan shell cleared.');
    }

    /** heratio#1156: persist the scan fit-scale + 360/Matterport embed URL. */
    public function setScanMetaAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $data = $request->validate([
            'scale' => 'nullable|numeric|min:0.001|max:1000',
            'embed_url' => 'nullable|string|max:500|url',
        ]);
        $this->service->setScanMeta(
            (int) $space->id,
            isset($data['scale']) ? (float) $data['scale'] : null,
            $data['embed_url'] ?? ''
        );
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Scan settings saved.');
    }

    /** Save a wall paint colour - the all-walls default, or one wall (edge). Used when no wall image is set. */
    public function saveWallColor(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $data = $request->validate([
            'color' => 'required|string|regex:/^#?[0-9a-fA-F]{6}$/',
            'edge' => 'nullable|integer|min:0|max:99',
            'all' => 'nullable|boolean',
        ]);
        $hex = '#'.ltrim($data['color'], '#');
        $allWalls = $request->boolean('all') || $request->input('edge', null) === null;
        if ($allWalls) {
            $this->service->setWallColor((int) $space->id, $hex);
        } else {
            $this->service->setWallColorForEdge((int) $space->id, (int) $request->input('edge'), $hex);
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'color' => $hex, 'all' => $allWalls, 'edge' => $allWalls ? null : (int) $request->input('edge')]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Wall colour saved.');
    }

    /** Clear a wall paint colour - the all-walls default, or one wall (edge). */
    public function clearWallColor(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $allWalls = $request->boolean('all') || $request->input('edge', null) === null;
        if ($allWalls) {
            $this->service->setWallColor((int) $space->id, null);
        } else {
            $this->service->setWallColorForEdge((int) $space->id, (int) $request->input('edge'), null);
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'all' => $allWalls, 'edge' => $allWalls ? null : (int) $request->input('edge')]);
        }

        return redirect()->route('exhibition-space.builder', ['slug' => $slug])->with('success', 'Wall colour cleared.');
    }

    /** AJAX: add a furniture/fitting item to this room at floor-fraction (fx,fy). */
    public function furnitureAddAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['kind' => 'nullable|string|max:32', 'fx' => 'nullable|numeric', 'fy' => 'nullable|numeric', 'asset_id' => 'nullable|integer|min:1']);
        if (! empty($data['asset_id'])) {
            $asset = $this->service->getFurnitureAsset((int) $data['asset_id']);
            if (! $asset) {
                return response()->json(['ok' => false, 'error' => 'asset not found'], 404);
            }
            $row = $this->service->addFurniture((int) $space->id, 'asset', (float) ($data['fx'] ?? 0.5), (float) ($data['fy'] ?? 0.5), $asset->file_path, $asset->ext);
            $row['label'] = $asset->label;
        } else {
            $row = $this->service->addFurniture((int) $space->id, (string) ($data['kind'] ?? 'pedestal'), (float) ($data['fx'] ?? 0.5), (float) ($data['fy'] ?? 0.5));
        }

        return response()->json(['ok' => true, 'item' => $row]);
    }

    /** Upload a custom furniture asset (3D model or image) into the reusable library. */
    public function uploadFurnitureAsset(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        // Validate by EXTENSION, not MIME: binary 3D formats (glb/gltf/obj/stl/ply) have no reliable
        // MIME type, so a mimes: rule silently rejects them - only images would pass.
        $request->validate([
            'asset' => 'required|file|extensions:glb,gltf,obj,stl,ply,jpeg,jpg,png,webp|max:51200',
            'label' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
        ]);
        $file = $request->file('asset');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'glb');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $kind = in_array($ext, ['jpg', 'png', 'webp'], true) ? 'image' : 'model';
        $dir = config('heratio.storage_path').'/uploads/exhibition-furniture';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $base = \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'furniture';
        $filename = $base.'-'.substr(md5((string) microtime(true)), 0, 8).'.'.$ext;
        $file->move($dir, $filename);
        $path = '/uploads/exhibition-furniture/'.$filename;
        $label = $request->input('label') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $asset = $this->service->addFurnitureAsset((string) $label, $path, $ext, $kind, $request->input('description'));

        return response()->json(['ok' => true, 'asset' => $asset]);
    }

    /** Remove a furniture asset from the library (placed copies keep their denormalised path). */
    public function deleteFurnitureAssetAjax(Request $request, string $slug)
    {
        if (! $this->service->getBySlug($slug)) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['id' => 'required|integer|min:1']);

        return response()->json(['ok' => $this->service->deleteFurnitureAsset((int) $data['id'])]);
    }

    /** AJAX: move/rotate/scale a furniture item. */
    public function furnitureMoveAjax(Request $request, string $slug)
    {
        if (! $this->service->getBySlug($slug)) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['id' => 'required|integer|min:1', 'fx' => 'required|numeric', 'fy' => 'required|numeric', 'rot' => 'nullable|numeric', 'scale' => 'nullable|numeric', 'segments' => 'nullable|integer|min:2|max:20']);
        $ok = $this->service->moveFurniture((int) $data['id'], (float) $data['fx'], (float) $data['fy'], isset($data['rot']) ? (float) $data['rot'] : null, isset($data['scale']) ? (float) $data['scale'] : null, isset($data['segments']) ? (int) $data['segments'] : null);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: save explicit pole offsets for a rope railing. */
    public function furniturePolesAjax(Request $request, string $slug)
    {
        if (! $this->service->getBySlug($slug)) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'id' => 'required|integer|min:1',
            'poles' => 'present|array',
            'poles.*.x' => 'required|numeric',
            'poles.*.z' => 'required|numeric',
        ]);
        $ok = $this->service->saveFurniturePoles((int) $data['id'], $data['poles']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: remove a furniture item. */
    public function furnitureRemoveAjax(Request $request, string $slug)
    {
        if (! $this->service->getBySlug($slug)) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['id' => 'required|integer|min:1']);
        $ok = $this->service->removeFurniture((int) $data['id']);

        return response()->json(['ok' => $ok]);
    }

    /** AJAX: set room dimensions (width/depth/wall height) from the Builder. */
    public function roomDimsAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate([
            'room_w' => 'nullable|numeric', 'room_d' => 'nullable|numeric', 'room_h' => 'nullable|numeric',
        ]);
        $this->service->updateRoomDims(
            (int) $space->id,
            isset($data['room_w']) ? (float) $data['room_w'] : null,
            isset($data['room_d']) ? (float) $data['room_d'] : null,
            isset($data['room_h']) ? (float) $data['room_h'] : null
        );

        return response()->json(['ok' => true]);
    }

    // ===================================================================
    // Digital twin - 2.5D pannable walkthrough (heratio#1138, Phase 2)
    // ===================================================================

    /** heratio#1194 - accessible (text + narration, keyboard) alternative to the 3D walkthrough. */
    public function accessibleTour(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return view('ahg-exhibition::exhibition-space.accessible-tour', [
            'space' => $space,
            'stops' => $this->service->accessibleTour($space),
        ]);
    }

    /**
     * heratio#1191 - on-site AR companion (first slice). A mobile-first, one-handed
     * "companion" page a visitor opens on their phone in the physical gallery (QR /
     * short URL). It shows the room's twin-sourced object info as large tap-friendly
     * cards (reusing accessibleTour()) and embeds the grounded room AI docent (reusing
     * the existing ask-room endpoint). Public, read-only.
     *
     * NOT in this slice: geo / marker AR anchoring (camera passthrough + placing the
     * cards in 3D space relative to the twin's object placements). That is the next
     * slice - see docs/reference/onsite-companion.md.
     */
    public function companion(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return view('ahg-exhibition::exhibition-space.companion', [
            'space' => $space,
            'stops' => $this->service->accessibleTour($space),
            'questions' => $this->service->roomSuggestedQuestions($space),
        ]);
    }

    public function walkthrough(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        $building = $this->service->getWalkthroughBuilding($space);
        // The walkthrough renders if ANY room in the building (or a corridor) has
        // content - not just the room whose slug was opened.
        $hasContent = count($building['corridor'] ?? []) > 0;
        foreach ($building['rooms'] as $r) {
            $hasContent = $hasContent || count($r['stops'] ?? []) > 0;
        }

        // #1192 event mode: arriving via a ticketed opening (?event=<token>) surfaces the event
        // banner + auto-identifies the verified attendee (pinned in session by the join action).
        $eventCtx = null;
        $eventToken = (string) $request->query('event', '');
        if ($eventToken !== '') {
            $ev = app(\AhgExhibition\Services\ExhibitionEventService::class)->getByToken($eventToken);
            if ($ev) {
                $att = session('exhibition_event_attendee');
                $eventCtx = [
                    'title' => $ev->title ?? 'Live opening',
                    'host' => $ev->host_name ?? null,
                    'attendee_name' => (is_array($att) && ($att['event_token'] ?? null) === $eventToken) ? ($att['name'] ?? null) : null,
                ];
            }
        }

        // #1153/#1193 promoted: the live walkthrough is now the ESM/three-r169 view with in-room
        // Gaussian splats, the conversational docent and live-opening mode (the old r137 view is retired).
        return view('ahg-exhibition::exhibition-space.walkthrough', [
            'space' => $space,
            'stops' => $this->service->getWalkthroughStops((int) $space->id),
            'walls' => $this->service->getWalls((int) $space->id),
            'building' => $building,
            'hasContent' => $hasContent,
            'canDocent' => auth()->check(),   // #1150 - logged-in staff can run a guided tour
            'annotations' => $this->service->listAnnotations($space),   // #1165 - wall graffiti
            'guidedTour' => $this->service->getGuidedTour($space),      // authored audio tour
            'eventCtx' => $eventCtx,
        ]);
    }

    /**
     * heratio#1153 - WebGPU renderer SPIKE (proof page, not the live walkthrough).
     * A minimal first-person room renderer built on modern three.js ES modules +
     * WebGPURenderer (which auto-falls-back to WebGL2 where WebGPU is unavailable),
     * to validate the renderer stack + importmap-under-CSP + jsm loaders/controls +
     * scan-shell loading before any migration of the live r137 walkthrough. The live
     * walkthrough() above is deliberately untouched.
     */
    public function walkthroughWebgpu(string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        return view('ahg-exhibition::exhibition-space.walkthrough-webgpu', [
            'space' => $space,
            'building' => $this->service->getWalkthroughBuilding($space),
        ]);
    }

    /**
     * heratio#1153/#1193 - BETA walkthrough: identical data to walkthrough(), rendered by the
     * ESM/three-r169 view (walkthrough-next) that keeps WebGLRenderer but adds in-room Gaussian
     * splats via GaussianSplats3D DropInViewer. The live walkthrough() stays on r137 until this
     * is signed off. Same payload so the A/B comparison is apples-to-apples.
     */
    public function walkthroughNext(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        $building = $this->service->getWalkthroughBuilding($space);
        $hasContent = count($building['corridor'] ?? []) > 0;
        foreach ($building['rooms'] as $r) {
            $hasContent = $hasContent || count($r['stops'] ?? []) > 0;
        }

        // #1192 event mode: arriving via a ticketed opening (?event=<token>) surfaces the event
        // banner and auto-identifies the verified attendee (pinned in session by the join action)
        // in the presence beat, so co-present ticket holders see each other by name.
        $eventCtx = null;
        $eventToken = (string) $request->query('event', '');
        if ($eventToken !== '') {
            $ev = app(\AhgExhibition\Services\ExhibitionEventService::class)->getByToken($eventToken);
            if ($ev) {
                $att = session('exhibition_event_attendee');
                $eventCtx = [
                    'title' => $ev->title ?? 'Live opening',
                    'host' => $ev->host_name ?? null,
                    'attendee_name' => (is_array($att) && ($att['event_token'] ?? null) === $eventToken) ? ($att['name'] ?? null) : null,
                ];
            }
        }

        // walkthrough-next is now an alias of the promoted walkthrough view (same ESM/r169 content).
        return view('ahg-exhibition::exhibition-space.walkthrough', [
            'space' => $space,
            'stops' => $this->service->getWalkthroughStops((int) $space->id),
            'walls' => $this->service->getWalls((int) $space->id),
            'building' => $building,
            'hasContent' => $hasContent,
            'canDocent' => auth()->check(),
            'annotations' => $this->service->listAnnotations($space),
            'guidedTour' => $this->service->getGuidedTour($space),
            'eventCtx' => $eventCtx,
        ]);
    }

    /** AJAX (builder): save the ordered guided-route for the walkthrough. */
    public function saveWalkthroughPath(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false, 'error' => 'Space not found.'], 404);
        }
        $data = $request->validate([
            'order' => 'present|array',
            'order.*' => 'integer|min:1',
        ]);
        $this->service->saveWalkthroughPath((int) $space->id, $data['order']);

        return response()->json(['ok' => true]);
    }
}
