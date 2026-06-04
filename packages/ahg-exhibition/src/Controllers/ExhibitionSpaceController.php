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
        ]);

        return $request->only([
            'name', 'space_type', 'building', 'floor',
            'capacity_value', 'capacity_unit',
            'lighting_lux_target', 'notes',
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

        return view('ahg-exhibition::exhibition-space.builder', [
            'space' => $space,
            'placements' => $this->service->getPlacementsForBuilder((int) $space->id),
            'capacityUnits' => ExhibitionSpaceService::CAPACITY_UNITS,
        ]);
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
        ]);
        try {
            $placement = $this->service->createPlacementAt(
                (int) $space->id,
                (int) $data['information_object_id'],
                (float) $data['pos_x'],
                (float) $data['pos_y']
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
        $dir = public_path('uploads/exhibition-floorplans');
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
}
