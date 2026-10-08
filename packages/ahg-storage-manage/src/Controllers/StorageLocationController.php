<?php

/**
 * StorageLocationController - hierarchical storage locations (heratio#1514).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3.0 or later. This
 * file is part of Heratio. See <https://www.gnu.org/licenses/> for details.
 */

namespace AhgStorageManage\Controllers;

use AhgStorageManage\Services\StorageLocationService;
use AhgStorageManage\Services\StorageMovementService;
use AhgStorageManage\Services\StoragePlacementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Port of the AtoM storageLocation module: browse (tree + search + paged
 * table), show (path, details, children, descendants), create / edit (with a
 * parent picker that excludes the location's own subtree), delete (refused
 * while children exist), and the JSON locations / tree / search endpoints.
 *
 * Routes (see routes/web.php). Storage layout is staff-only (#1364), so reads
 * need auth; writes need acl; delete needs admin.
 */
class StorageLocationController extends Controller
{
    public const PER_PAGE = 30;

    /** How many unplaced objects a location's page offers at once; the search narrows it (heratio#1528). */
    public const PLACE_LIMIT = 100;

    private StorageLocationService $service;

    public function __construct()
    {
        $this->service = new StorageLocationService;
    }

    public function browse(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $type = trim((string) $request->input('type', ''));

        $params = array_filter(['search' => $search, 'type' => $type], fn ($v) => $v !== '');
        $all = $this->service->getLocations($params);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $locations = new LengthAwarePaginator(
            array_slice($all, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($all),
            self::PER_PAGE,
            $page,
            ['path' => $request->url()]
        );

        return view('ahg-storage-manage::storage-location.browse', [
            'locations' => $locations,
            'tree' => $this->service->getTree(),
            'types' => $this->service->types(),
            'units' => $this->service->options(StorageLocationService::UNIT_TAXONOMY),
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $location = $this->find($slug);
        $id = (int) $location->id;

        $movements = new StorageMovementService;

        // heratio#1528 - what sits in the locations beneath this one: the boxes
        // in the cartons on this pallet. Objects held here directly are listed
        // in their own card, with the move form.
        $beneath = array_values(array_filter(
            $movements->objectsUnder($id),
            static fn ($object) => (int) $object['depth'] > 0
        ));

        // Objects with no place yet, so they can be given this one. Only worked
        // out for somebody who can act on it.
        $unplacedSearch = trim((string) $request->input('q', ''));
        $canPlace = StoragePlacementService::mayCreate();
        $unplaced = $canPlace
            ? $movements->unplacedObjects($unplacedSearch, self::PLACE_LIMIT)
            : ['rows' => [], 'total' => 0];

        return view('ahg-storage-manage::storage-location.show', [
            'location' => $location,
            'path' => $this->service->getPath($id),
            'children' => $this->service->getChildren($id),
            'descendants' => $this->service->getDescendants($id),
            'subtree' => $this->service->getTree($id),
            'types' => $this->service->types(),
            'units' => $this->service->options(StorageLocationService::UNIT_TAXONOMY),
            'objects' => $movements->objectsIn($id),
            'movements' => $movements->historyForLocation($id),
            'locations' => $this->service->getLocations(),
            'rollup' => $this->service->capacityRollup($id),
            'beneath' => $beneath,
            'canPlace' => $canPlace,
            'unplaced' => $unplaced,
            'unplacedSearch' => $unplacedSearch,
        ]);
    }

    /**
     * heratio#1528 - put objects that have no place yet into this location, in
     * one batch of first placements. This is how anything enters the tree from
     * the location side; objects that already have a place are moved from the
     * page of the location they are in.
     */
    public function placeObjects(Request $request, string $slug)
    {
        $location = $this->find($slug);

        $data = $request->validate([
            'object_ids' => ['required', 'array', 'min:1'],
            'object_ids.*' => ['integer', 'exists:physical_object,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'object_ids.required' => __('Select at least one object to place.'),
        ]);

        $movements = new StorageMovementService;

        // Only objects that are still unplaced: this is a first placement, not a
        // way round the move form for objects already somewhere else.
        $ids = array_values(array_filter(
            array_map('intval', $data['object_ids']),
            fn ($id) => $movements->currentLocationOf($id) === null
        ));

        try {
            $written = $movements->moveObjects($ids, (int) $location->id, ['note' => $data['note'] ?? null]);
        } catch (RuntimeException $e) {
            return back()->with('error', __('Error placing objects: :message', ['message' => $e->getMessage()]));
        }

        return redirect()->route('storagelocation.show', $location->slug)
            ->with('success', trans_choice('{0}Nothing placed: those objects already have a place.|{1}One object placed here.|[2,*]:count objects placed here.', count($written), ['count' => count($written)]));
    }

    /**
     * heratio#1514 - move the selected objects out of this location in one
     * batch. Started from the location that holds them, because the real task
     * is emptying a shelf rather than moving one box at a time.
     */
    public function moveObjects(Request $request, string $slug)
    {
        $location = $this->find($slug);

        $data = $request->validate([
            'object_ids' => ['required', 'array', 'min:1'],
            'object_ids.*' => ['integer'],
            'to_location_id' => ['nullable', 'integer', 'exists:ahg_storage_location,id'],
            'remove_from_storage' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $to = $request->boolean('remove_from_storage') ? null : ($data['to_location_id'] ?? null);

        if ($to === null && ! $request->boolean('remove_from_storage')) {
            return back()->withErrors(['to_location_id' => __('Choose a destination, or tick "remove from storage".')]);
        }

        try {
            $written = (new StorageMovementService)->moveObjects($data['object_ids'], $to, ['note' => $data['note'] ?? null]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['to_location_id' => $e->getMessage()]);
        }

        return redirect()->route('storagelocation.show', $location->slug)
            ->with('success', trans_choice('{0}Nothing moved: those objects are already there.|{1}One object moved.|[2,*]:count objects moved.', count($written), ['count' => count($written)]));
    }

    public function create(Request $request)
    {
        $parent = $request->filled('parent_id')
            ? $this->service->getById((int) $request->input('parent_id'))
            : null;

        return view('ahg-storage-manage::storage-location.edit', [
            'location' => null,
            'parent' => $parent,
            'path' => $parent ? $this->service->getPath((int) $parent->id) : [],
            'parents' => $this->service->getLocations(),
            'types' => $this->service->types(),
            'units' => $this->service->options(StorageLocationService::UNIT_TAXONOMY),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            $id = $this->service->create($data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('storagelocation.show', ['slug' => $this->service->getById($id)->slug])
            ->with('success', __('Storage location created.'));
    }

    public function edit(string $slug)
    {
        $location = $this->find($slug);
        $id = (int) $location->id;

        // Neither the location nor anything under it can become its parent.
        $exclude = array_merge([$id], array_map(fn ($d) => (int) $d->id, $this->service->getDescendants($id)));

        // A type retired in the Dropdown Manager stays on the location that has
        // it, so it stays in this location's own select (heratio#1528).
        $types = $this->service->types();
        if ($location->location_type !== null && ! array_key_exists($location->location_type, $types)) {
            $types[$location->location_type] = $this->service->typeLabel($location->location_type);
        }

        return view('ahg-storage-manage::storage-location.edit', [
            'location' => $location,
            'parent' => null,
            'path' => $this->service->getPath($id),
            'parents' => array_values(array_filter(
                $this->service->getLocations(),
                fn ($c) => ! in_array((int) $c->id, $exclude, true)
            )),
            'types' => $types,
            'units' => $this->service->options(StorageLocationService::UNIT_TAXONOMY),
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $location = $this->find($slug);
        $data = $this->validated($request, $location->location_type);

        try {
            $this->service->update((int) $location->id, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('storagelocation.show', ['slug' => $this->service->getById((int) $location->id)->slug])
            ->with('success', __('Storage location updated.'));
    }

    public function confirmDelete(string $slug)
    {
        $location = $this->find($slug);
        $id = (int) $location->id;

        return view('ahg-storage-manage::storage-location.delete', [
            'location' => $location,
            'path' => $this->service->getPath($id),
            'children' => $this->service->getChildren($id),
            'descendants' => $this->service->getDescendants($id),
            'types' => $this->service->types(),
        ]);
    }

    public function destroy(string $slug)
    {
        $location = $this->find($slug);

        try {
            $this->service->delete((int) $location->id);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('storagelocation.show', ['slug' => $location->slug])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('storagelocation.browse')->with('success', __('Storage location deleted.'));
    }

    public function apiLocations(Request $request): JsonResponse
    {
        $params = array_filter([
            'search' => trim((string) $request->input('search', '')),
            'type' => trim((string) $request->input('type', '')),
        ], fn ($v) => $v !== '');

        if ($request->filled('parent_id')) {
            $params['parent_id'] = (int) $request->input('parent_id');
        }

        return $this->json($this->service->getLocations($params));
    }

    public function apiTree(Request $request): JsonResponse
    {
        return $this->json($this->service->getTree(
            $request->filled('parent_id') ? (int) $request->input('parent_id') : null
        ));
    }

    public function apiSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return $this->json([], false, 'Search query is required');
        }

        return $this->json($this->service->getLocations(['search' => $q]));
    }

    private function find(string $slug): object
    {
        return $this->service->getBySlug($slug) ?? abort(404, 'Storage location not found');
    }

    /**
     * @param  string|null  $keepType  the type the location already has: it may
     *                                 keep one since retired in the Dropdown
     *                                 Manager, but not be given one (heratio#1528)
     */
    private function validated(Request $request, ?string $keepType = null): array
    {
        $allowed = array_keys($this->service->types());
        if ($keepType !== null && $keepType !== '') {
            $allowed[] = $keepType;
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'location_type' => ['required', 'string', Rule::in($allowed)],
            'parent_id' => 'nullable|integer|exists:ahg_storage_location,id',
            'description' => 'nullable|string|max:65535',
            'capacity_value' => 'nullable|numeric|min:0',
            'capacity_unit' => ['nullable', 'string', Rule::in(array_keys($this->service->options(StorageLocationService::UNIT_TAXONOMY)))],
            'notes' => 'nullable|string|max:65535',
        ]);

        return $request->only(['name', 'location_type', 'parent_id', 'description', 'capacity_value', 'capacity_unit', 'notes']);
    }

    private function json(array $data, bool $success = true, ?string $message = null): JsonResponse
    {
        $payload = ['success' => $success, 'data' => $data, 'count' => count($data)];
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $success ? 200 : 422);
    }
}
