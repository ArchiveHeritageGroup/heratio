<?php

/**
 * StrongroomController - heratio#144 rebuild 2026-05-23.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3.0 or later. This
 * file is part of Heratio. See <https://www.gnu.org/licenses/> for details.
 */

namespace AhgStorageManage\Controllers;

use AhgStorageManage\Services\StrongroomService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Strongroom space-allocation CRUD + occupant view.
 *
 * Routes (see routes/web.php):
 *   GET  /strongroom/browse             browse           (public read)
 *   GET  /strongroom/add                create           (auth)
 *   POST /strongroom/add                store            (auth + acl:create)
 *   GET  /strongroom/{slug}             show             (public read)
 *   GET  /strongroom/{slug}/edit        edit             (auth)
 *   POST /strongroom/{slug}/edit        update           (auth + acl:update)
 *   GET  /strongroom/{slug}/delete      confirmDelete    (admin)
 *   DELETE /strongroom/{slug}/delete    destroy          (admin + acl:delete)
 */
class StrongroomController extends Controller
{
    private StrongroomService $service;

    public function __construct()
    {
        $this->service = new StrongroomService;
    }

    public function browse(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $rooms = $this->service->browse($search, 25);

        return view('ahg-storage-manage::strongroom.browse', [
            'rooms' => $rooms,
            'search' => $search,
            'capacityUnits' => StrongroomService::CAPACITY_UNITS,
        ]);
    }

    public function show(string $slug)
    {
        $room = $this->service->getBySlug($slug);
        if ($room === null) {
            abort(404, 'Strongroom not found');
        }

        $used = $this->service->getUsedCapacity((int) $room->id);

        return view('ahg-storage-manage::strongroom.show', [
            'room' => $room,
            'occupants' => $this->service->getOccupants((int) $room->id),
            'usedUnits' => $used,
            'remainingUnits' => $room->capacity_value === null
                ? null
                : (float) $room->capacity_value - $used,
            'capacityUnits' => StrongroomService::CAPACITY_UNITS,
        ]);
    }

    public function create()
    {
        return view('ahg-storage-manage::strongroom.edit', [
            'room' => null,
            'capacityUnits' => StrongroomService::CAPACITY_UNITS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $id = $this->service->create($data);
        $room = $this->service->getById($id);

        return redirect()
            ->route('strongroom.show', ['slug' => $room->slug])
            ->with('success', 'Strongroom created.');
    }

    public function edit(string $slug)
    {
        $room = $this->service->getBySlug($slug);
        if ($room === null) {
            abort(404, 'Strongroom not found');
        }

        return view('ahg-storage-manage::strongroom.edit', [
            'room' => $room,
            'capacityUnits' => StrongroomService::CAPACITY_UNITS,
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $room = $this->service->getBySlug($slug);
        if ($room === null) {
            abort(404, 'Strongroom not found');
        }

        $data = $this->validated($request);
        $this->service->update((int) $room->id, $data);

        return redirect()
            ->route('strongroom.show', ['slug' => $room->slug])
            ->with('success', 'Strongroom updated.');
    }

    public function confirmDelete(string $slug)
    {
        $room = $this->service->getBySlug($slug);
        if ($room === null) {
            abort(404, 'Strongroom not found');
        }

        return view('ahg-storage-manage::strongroom.delete', [
            'room' => $room,
            'occupantCount' => $this->service->getOccupants((int) $room->id)->count(),
        ]);
    }

    public function destroy(string $slug)
    {
        $room = $this->service->getBySlug($slug);
        if ($room === null) {
            abort(404, 'Strongroom not found');
        }

        try {
            $this->service->delete((int) $room->id);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('strongroom.show', ['slug' => $room->slug])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('strongroom.browse')
            ->with('success', 'Strongroom deleted.');
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location_description' => 'nullable|string|max:65535',
            'capacity_value' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|in:'.implode(',', array_keys(StrongroomService::CAPACITY_UNITS)),
            'notes' => 'nullable|string|max:65535',
        ]);

        return $request->only(['name', 'location_description', 'capacity_value', 'capacity_unit', 'notes']);
    }
}
