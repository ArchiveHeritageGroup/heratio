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
            'floorplan' => 'required|image|mimes:jpeg,png,webp,svg|max:8192',
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
        $request->validate(['ceiling' => 'required|image|mimes:jpeg,png,webp|max:8192']);

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
            'wall_image' => 'required|image|mimes:jpeg,png,webp|max:8192',
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

    /** AJAX: add a furniture/fitting item to this room at floor-fraction (fx,fy). */
    public function furnitureAddAjax(Request $request, string $slug)
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['kind' => 'required|string|max:32', 'fx' => 'nullable|numeric', 'fy' => 'nullable|numeric']);
        $row = $this->service->addFurniture((int) $space->id, (string) $data['kind'], (float) ($data['fx'] ?? 0.5), (float) ($data['fy'] ?? 0.5));

        return response()->json(['ok' => true, 'item' => $row]);
    }

    /** AJAX: move/rotate/scale a furniture item. */
    public function furnitureMoveAjax(Request $request, string $slug)
    {
        if (! $this->service->getBySlug($slug)) {
            return response()->json(['ok' => false], 404);
        }
        $data = $request->validate(['id' => 'required|integer|min:1', 'fx' => 'required|numeric', 'fy' => 'required|numeric', 'rot' => 'nullable|numeric', 'scale' => 'nullable|numeric']);
        $ok = $this->service->moveFurniture((int) $data['id'], (float) $data['fx'], (float) $data['fy'], isset($data['rot']) ? (float) $data['rot'] : null, isset($data['scale']) ? (float) $data['scale'] : null);

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

    public function walkthrough(string $slug)
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

        return view('ahg-exhibition::exhibition-space.walkthrough', [
            'space' => $space,
            'stops' => $this->service->getWalkthroughStops((int) $space->id),
            'walls' => $this->service->getWalls((int) $space->id),
            'building' => $building,
            'hasContent' => $hasContent,
            'canDocent' => auth()->check(),   // #1150 - logged-in staff can run a guided tour
            'annotations' => $this->service->listAnnotations($space),   // #1165 - wall graffiti
            'guidedTour' => $this->service->getGuidedTour($space),      // authored audio tour
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
