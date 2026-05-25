<?php
/**
 * Heratio - REST + admin controller for Mirador workspace persistence (issue #699).
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgIiifCollection\Controllers;

use App\Http\Controllers\Controller;
use AhgIiifCollection\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * WorkspaceController - 6 REST endpoints + 1 admin page.
 *
 * REST endpoints live under /api/iiif/workspace and require an authenticated
 * session. Tenant scoping is purely user_id-based for the first cut, so each
 * user only ever sees their own workspaces. The admin page at
 * /iiif/workspaces is a thin Bootstrap 5 table that calls the REST endpoints
 * for rename / delete / set-default.
 */
class WorkspaceController extends Controller
{
    protected WorkspaceService $service;

    public function __construct(WorkspaceService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/iiif/workspace - list user's saved workspaces.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        return response()->json([
            'data' => $this->service->listForUser($userId),
        ]);
    }

    /**
     * POST /api/iiif/workspace - save current workspace.
     * Body: { name: string, config_json: object|string, is_default?: bool }
     */
    public function store(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'config_json' => 'required',
            'is_default'  => 'nullable|boolean',
        ]);

        $id = $this->service->create(
            $userId,
            $validated['name'],
            $validated['config_json'],
            (bool) ($validated['is_default'] ?? false),
        );

        return response()->json(['data' => $this->service->find($userId, $id)], 201);
    }

    /**
     * GET /api/iiif/workspace/{id} - fetch a specific workspace incl. config.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $row = $this->service->find($userId, $id);
        if (!$row) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // Decode config_json so the client can pipe it straight into
        // Mirador.importMiradorState() / Redux dispatch.
        if (isset($row['config_json']) && is_string($row['config_json'])) {
            $decoded = json_decode($row['config_json'], true);
            if ($decoded !== null) {
                $row['config_json'] = $decoded;
            }
        }

        return response()->json(['data' => $row]);
    }

    /**
     * PUT /api/iiif/workspace/{id} - rename or overwrite the config.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $validated = $request->validate([
            'name'        => 'nullable|string|max:255',
            'config_json' => 'nullable',
        ]);

        $ok = $this->service->update($userId, $id, $validated);
        if (!$ok) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->service->find($userId, $id)]);
    }

    /**
     * DELETE /api/iiif/workspace/{id} - delete one.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $ok = $this->service->delete($userId, $id);
        if (!$ok) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * POST /api/iiif/workspace/{id}/load - mark as default-on-load.
     */
    public function load(Request $request, int $id): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $ok = $this->service->setDefault($userId, $id);
        if (!$ok) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->service->find($userId, $id)]);
    }

    /**
     * GET /iiif/workspaces - admin page (Bootstrap 5 table).
     */
    public function adminIndex(Request $request)
    {
        $userId = (int) Auth::id();
        $workspaces = $userId ? $this->service->listForUser($userId) : [];

        return view('ahg-iiif-collection::workspaces.index', [
            'workspaces' => $workspaces,
        ]);
    }
}
