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
            'types' => $this->service->options(StorageLocationService::TYPE_TAXONOMY),
            'units' => $this->service->options(StorageLocationService::UNIT_TAXONOMY),
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function show(string $slug)
    {
        $location = $this->find($slug);
        $id = (int) $location->id;

        return view('ahg-storage-manage::storage-location.show', [
            'location' => $location,
            'path' => $this->service->getPath($id),
            'children' => $this->service->getChildren($id),
            'descendants' => $this->service->getDescendants($id),
            'subtree' => $this->service->getTree($id),
            'types' => $this->service->options(StorageLocationService::TYPE_TAXONOMY),
            'units' => $this->service->options(StorageLocationService::UNIT_TAXONOMY),
        ]);
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
            'types' => $this->service->options(StorageLocationService::TYPE_TAXONOMY),
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

        return view('ahg-storage-manage::storage-location.edit', [
            'location' => $location,
            'parent' => null,
            'path' => $this->service->getPath($id),
            'parents' => array_values(array_filter(
                $this->service->getLocations(),
                fn ($c) => ! in_array((int) $c->id, $exclude, true)
            )),
            'types' => $this->service->options(StorageLocationService::TYPE_TAXONOMY),
            'units' => $this->service->options(StorageLocationService::UNIT_TAXONOMY),
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $location = $this->find($slug);
        $data = $this->validated($request);

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
            'types' => $this->service->options(StorageLocationService::TYPE_TAXONOMY),
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

    private function validated(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location_type' => ['required', 'string', Rule::in(array_keys($this->service->options(StorageLocationService::TYPE_TAXONOMY)))],
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
