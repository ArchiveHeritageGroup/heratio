<?php

/**
 * StrongroomController - Controller for Heratio (heratio#144)
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

namespace AhgStorageManage\Controllers;

use AhgStorageManage\Services\StrongroomService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Strongroom space-allocation CRUD + occupant view.
 *
 * Routes (see routes/web.php):
 *   GET  /strongroom/browse             browse           (public-read; gated by route group)
 *   GET  /strongroom/add                create           (admin)
 *   POST /strongroom/add                store            (admin)
 *   GET  /strongroom/{slug}             show             (public-read)
 *   GET  /strongroom/{slug}/edit        edit             (admin)
 *   POST /strongroom/{slug}/edit        update           (admin)
 *   GET  /strongroom/{slug}/delete      confirmDelete    (admin)
 *   POST /strongroom/{slug}/delete      destroy          (admin)
 */
class StrongroomController extends Controller
{
    private StrongroomService $service;

    public function __construct()
    {
        $this->service = new StrongroomService();
    }

    public function browse(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $rooms = $this->service->browse($search, 25);

        return view('ahg-storage-manage::strongroom.browse', [
            'rooms'           => $rooms,
            'search'          => $search,
            'capacityUnits'   => StrongroomService::CAPACITY_UNITS,
        ]);
    }

    public function show(string $slug)
    {
        $room = $this->service->getBySlug($slug);
        if (null === $room) {
            abort(404, 'Strongroom not found');
        }

        $used = $this->service->getUsedCapacity((int) $room->id);

        return view('ahg-storage-manage::strongroom.show', [
            'room'          => $room,
            'occupants'     => $this->service->getOccupants((int) $room->id),
            'usedUnits'     => $used,
            'remainingUnits' => null === $room->capacity_value
                ? null
                : (float) $room->capacity_value - $used,
            'capacityUnits' => StrongroomService::CAPACITY_UNITS,
        ]);
    }

    public function create()
    {
        return view('ahg-storage-manage::strongroom.edit', [
            'room'          => null,
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
        if (null === $room) {
            abort(404, 'Strongroom not found');
        }

        return view('ahg-storage-manage::strongroom.edit', [
            'room'          => $room,
            'capacityUnits' => StrongroomService::CAPACITY_UNITS,
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $room = $this->service->getBySlug($slug);
        if (null === $room) {
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
        if (null === $room) {
            abort(404, 'Strongroom not found');
        }

        return view('ahg-storage-manage::strongroom.delete', [
            'room'           => $room,
            'occupantCount'  => $this->service->getOccupants((int) $room->id)->count(),
        ]);
    }

    public function destroy(string $slug)
    {
        $room = $this->service->getBySlug($slug);
        if (null === $room) {
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

    /**
     * Validate + normalise the form payload. Returns only the keys the
     * service consumes — anything else from the request is ignored.
     */
    private function validated(Request $request): array
    {
        $request->validate([
            'name'                 => 'required|string|max:255',
            'location_description' => 'nullable|string|max:65535',
            'capacity_value'       => 'nullable|numeric|min:0',
            'capacity_unit'        => 'nullable|string|in:' . implode(',', array_keys(StrongroomService::CAPACITY_UNITS)),
            'notes'                => 'nullable|string|max:65535',
        ]);

        return $request->only(['name', 'location_description', 'capacity_value', 'capacity_unit', 'notes']);
    }
}
