<?php

/**
 * ExhibitionApiController - #1280
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed under AGPL-3.0.
 *
 * REST API v1 for exhibition spaces (ahg_exhibition_space) and their object
 * placements (ahg_exhibition_placement), so an external system can create and
 * manage exhibitions with a scoped API key. Reads require the `read` scope,
 * writes `write`, deletes `delete` (mirrors ResearchProjectApiController). The
 * public, read-only interop surfaces (IIIF manifest.json, the ahg-exhibition-scene
 * scene.json, exhibition.jsonld) and the #1277 federated borrow endpoints are
 * unchanged - this is the authenticated management resource on top of them.
 *
 * No business logic is duplicated: this is a thin controller over
 * AhgExhibition\Services\ExhibitionSpaceService (the same service the web UI uses),
 * so validation, slug generation, capacity-overflow and date-order rules stay in
 * one place. Response envelope / pagination / 404 shape mirror the other v1
 * resource controllers exactly.
 */

namespace AhgApi\Controllers\V1;

use AhgExhibition\Services\ExhibitionSpaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExhibitionApiController extends Controller
{
    protected ExhibitionSpaceService $service;

    public function __construct(ExhibitionSpaceService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/v1/exhibitions
     * Paginated list with optional `q` (name) and `space_type` filters.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = $request->get('sort', 'updated');
        $sortDir = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('ahg_exhibition_space');

        if (($q = trim((string) $request->get('q', ''))) !== '') {
            $query->where('name', 'like', '%' . $q . '%');
        }
        if (($type = trim((string) $request->get('space_type', ''))) !== '') {
            $query->where('space_type', $type);
        }

        $orderCol = match ($sort) {
            'alphabetic', 'name' => 'name',
            'type', 'space_type' => 'space_type',
            'created' => 'created_at',
            default => 'updated_at',
        };

        $total = $query->count();
        $results = $query
            ->select(
                'id', 'slug', 'name', 'space_type', 'building', 'floor',
                'capacity_value', 'capacity_unit', 'lighting_lux_target',
                'room_w', 'room_d', 'room_h', 'created_at', 'updated_at'
            )
            ->orderBy($orderCol, $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json([
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'results' => $results,
        ]);
    }

    /**
     * GET /api/v1/exhibitions/{slug}
     * The space plus its placements (local + remote/borrowed).
     */
    public function show(string $slug): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $space->placements = $this->service->getPlacements((int) $space->id);

        return response()->json($space);
    }

    /**
     * POST /api/v1/exhibitions
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $this->validateSpace($request);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        try {
            $id = $this->service->create($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation failed', 'messages' => ['name' => [$e->getMessage()]]], 422);
        }

        return response()->json($this->service->getById($id), 201);
    }

    /**
     * PUT /api/v1/exhibitions/{slug}
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $data = $this->validateSpace($request, true);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $this->service->update((int) $space->id, $data);

        return response()->json($this->service->getById((int) $space->id));
    }

    /**
     * DELETE /api/v1/exhibitions/{slug}
     * The service refuses while placements still reference the space (409).
     */
    public function destroy(string $slug): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $this->service->delete((int) $space->id);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['deleted' => true, 'slug' => $slug]);
    }

    /**
     * GET /api/v1/exhibitions/{slug}/placements
     */
    public function placements(string $slug): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json(['results' => $this->service->getPlacements((int) $space->id)]);
    }

    /**
     * POST /api/v1/exhibitions/{slug}/placements
     * Place (or update, when placement_id is given) a LOCAL information object.
     */
    public function storePlacement(Request $request, string $slug): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $data = $request->validate([
                'information_object_id' => 'required|integer|min:1',
                'placement_id' => 'nullable|integer|min:1',
                'size_units_used' => 'nullable|numeric|min:0',
                'starts_at' => 'nullable|date',
                'ends_at' => 'nullable|date',
                'exhibition_id' => 'nullable|integer',
                'notes' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        try {
            $id = $this->service->placePlacement([
                'id' => $data['placement_id'] ?? null,
                'exhibition_space_id' => (int) $space->id,
                'information_object_id' => (int) $data['information_object_id'],
                'size_units_used' => (float) ($data['size_units_used'] ?? 0),
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'exhibition_id' => $data['exhibition_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation failed', 'messages' => ['placement' => [$e->getMessage()]]], 422);
        } catch (\RuntimeException $e) {
            // Capacity overflow for the requested date range.
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $id], empty($data['placement_id']) ? 201 : 200);
    }

    /**
     * POST /api/v1/exhibitions/{slug}/placements/remote
     * Borrow a peer object as a read-only remote placement (#1277). The normalised
     * remote object is the same shape RemoteSceneFetchService / peerScene returns.
     */
    public function storeRemotePlacement(Request $request, string $slug): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            $data = $request->validate([
                'remote_payload' => 'required',   // array or JSON string of the normalised peer object
                'remote_peer_id' => 'nullable|integer|min:1',
                'remote_ref' => 'nullable|string|max:255',
                'placement_id' => 'nullable|integer|min:1',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $payload = $data['remote_payload'];
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        try {
            $placement = $this->service->placeRemotePlacement([
                'id' => $data['placement_id'] ?? null,
                'exhibition_space_id' => (int) $space->id,
                'remote_peer_id' => $data['remote_peer_id'] ?? null,
                'remote_ref' => $data['remote_ref'] ?? null,
                'remote_payload' => $payload,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation failed', 'messages' => ['remote_payload' => [$e->getMessage()]]], 422);
        }

        return response()->json($placement, empty($data['placement_id']) ? 201 : 200);
    }

    /**
     * PUT /api/v1/exhibitions/{slug}/placements/{id}
     *
     * Update a local placement's position/size/dates. Thin wrapper over the same
     * placePlacement() path storePlacement uses for an in-place update, scoped so
     * the placement must belong to this exhibition space. Behaves as a merge: any
     * field omitted from the body keeps its current value, so a caller can PUT
     * just {starts_at, ends_at} without resetting size or notes. Remote (borrowed)
     * placements are managed via the /placements/remote endpoint.
     */
    public function updatePlacement(Request $request, string $slug, int $id): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $placement = DB::table('ahg_exhibition_placement')
            ->where('id', $id)
            ->where('exhibition_space_id', (int) $space->id)
            ->first();
        if (! $placement) {
            return response()->json(['error' => 'Placement not found in this exhibition'], 404);
        }
        if ((int) ($placement->information_object_id ?? 0) <= 0) {
            return response()->json([
                'error' => 'Remote placements are managed via POST /exhibitions/{slug}/placements/remote',
            ], 422);
        }

        try {
            $data = $request->validate([
                'information_object_id' => 'nullable|integer|min:1',
                'size_units_used' => 'nullable|numeric|min:0',
                'starts_at' => 'nullable|date',
                'ends_at' => 'nullable|date',
                'exhibition_id' => 'nullable|integer',
                'notes' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        try {
            $this->service->placePlacement([
                'id' => $id,
                'exhibition_space_id' => (int) $space->id,
                'information_object_id' => (int) ($data['information_object_id'] ?? $placement->information_object_id),
                'size_units_used' => array_key_exists('size_units_used', $data)
                    ? (float) $data['size_units_used']
                    : (float) ($placement->size_units_used ?? 0),
                'starts_at' => $data['starts_at'] ?? $placement->starts_at,
                'ends_at' => $data['ends_at'] ?? $placement->ends_at,
                'exhibition_id' => $data['exhibition_id'] ?? $placement->exhibition_id,
                'notes' => $data['notes'] ?? $placement->notes,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation failed', 'messages' => ['placement' => [$e->getMessage()]]], 422);
        } catch (\RuntimeException $e) {
            // Capacity overflow / date-order violation for the requested range.
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $id, 'updated' => true]);
    }

    /**
     * DELETE /api/v1/exhibitions/{slug}/placements/{id}
     */
    public function destroyPlacement(string $slug, int $id): JsonResponse
    {
        $space = $this->service->getBySlug($slug);
        if (! $space) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $row = DB::table('ahg_exhibition_placement')
            ->where('id', $id)->where('exhibition_space_id', $space->id)->first();
        if (! $row) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $this->service->removePlacement($id);

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Shared validation for create/update. On update, `name` is `sometimes`.
     *
     * @return array<string,mixed>
     */
    private function validateSpace(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'space_type' => 'nullable|string|max:20',
            'building' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:64',
            'capacity_value' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:20',
            'lighting_lux_target' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'room_w' => 'nullable|numeric|min:1|max:200',
            'room_d' => 'nullable|numeric|min:1|max:200',
            'room_h' => 'nullable|numeric|min:1|max:30',
            'building_id' => 'nullable|string|max:64',
            'building_seq' => 'nullable|integer|min:0',
        ]);
    }

    private function validationError(ValidationException $e): JsonResponse
    {
        return response()->json(['error' => 'Validation failed', 'messages' => $e->errors()], 422);
    }
}
