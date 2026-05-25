<?php

/**
 * CameraBookmarkController - Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * Per-user camera bookmarks for the 3D model viewer (#666 Phase 2).
 *
 * Reads are anonymous if the underlying model is public. Writes require
 * an authenticated user; user_id is captured from auth()->id() (never the
 * client). user_id NULL writes are allowed only for the admin role and
 * represent site-wide presets.
 */

namespace Ahg3dModel\Controllers;

use Ahg3dModel\Models\Object3dCameraBookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CameraBookmarkController extends Controller
{
    /**
     * GET /3d/{model_id}/bookmarks
     *
     * Lists shared bookmarks plus (if signed in) the caller's own
     * bookmarks. Anonymous callers see shared bookmarks only, and only
     * if the underlying model is public.
     */
    public function index(int $modelId): JsonResponse
    {
        $model = $this->loadAccessibleModel($modelId);
        if (! $model) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $userId = Auth::id();

        $query = Object3dCameraBookmark::query()
            ->where('object_3d_id', $modelId);

        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            });
        } else {
            $query->whereNull('user_id');
        }

        $rows = $query
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'object_3d_id', 'user_id', 'name', 'camera_orbit', 'camera_target', 'field_of_view', 'is_default']);

        return response()->json([
            'bookmarks' => $rows,
            'current_user_id' => $userId,
        ]);
    }

    /**
     * POST /3d/{model_id}/bookmarks
     */
    public function store(Request $request, int $modelId): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $model = $this->loadAccessibleModel($modelId);
        if (! $model) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $this->validateInput($request);

        $bookmark = new Object3dCameraBookmark;
        $bookmark->object_3d_id = $modelId;
        // user_id always comes from the session; client cannot impersonate.
        // Shared bookmarks (user_id NULL) are admin-only.
        $bookmark->user_id = $this->resolveOwnerId($request);
        $bookmark->name = $data['name'];
        $bookmark->camera_orbit = $data['camera_orbit'];
        $bookmark->camera_target = $data['camera_target'] ?? null;
        $bookmark->field_of_view = $data['field_of_view'] ?? null;
        $bookmark->is_default = (bool) ($data['is_default'] ?? false);
        $bookmark->save();

        if ($bookmark->is_default) {
            $this->clearOtherDefaults($bookmark);
        }

        return response()->json(['bookmark' => $bookmark], 201);
    }

    /**
     * PUT /3d/{model_id}/bookmarks/{id}
     */
    public function update(Request $request, int $modelId, int $id): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $bookmark = Object3dCameraBookmark::where('object_3d_id', $modelId)->find($id);
        if (! $bookmark) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if (! $this->canMutate($bookmark)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $this->validateInput($request);

        $bookmark->name = $data['name'];
        $bookmark->camera_orbit = $data['camera_orbit'];
        $bookmark->camera_target = $data['camera_target'] ?? null;
        $bookmark->field_of_view = $data['field_of_view'] ?? null;
        $bookmark->is_default = (bool) ($data['is_default'] ?? false);
        $bookmark->save();

        if ($bookmark->is_default) {
            $this->clearOtherDefaults($bookmark);
        }

        return response()->json(['bookmark' => $bookmark]);
    }

    /**
     * DELETE /3d/{model_id}/bookmarks/{id}
     */
    public function destroy(Request $request, int $modelId, int $id): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $bookmark = Object3dCameraBookmark::where('object_3d_id', $modelId)->find($id);
        if (! $bookmark) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if (! $this->canMutate($bookmark)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $bookmark->delete();

        return response()->json(['success' => true]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Load the model only if the caller is allowed to see it. Anonymous
     * callers can only see is_public=1 models; authenticated users may see
     * everything they have row-level access to (the show pages already
     * gate visibility before linking here).
     */
    private function loadAccessibleModel(int $modelId): ?object
    {
        $model = DB::table('object_3d_model')->where('id', $modelId)->first();
        if (! $model) {
            return null;
        }
        if (! Auth::check() && empty($model->is_public)) {
            return null;
        }

        return $model;
    }

    private function validateInput(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'camera_orbit' => ['required', 'string', 'max:120'],
            'camera_target' => ['nullable', 'string', 'max:120'],
            'field_of_view' => ['nullable', 'string', 'max:40'],
            'is_default' => ['nullable', 'boolean'],
            'shared' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Returns the user_id to write on the row. Shared (NULL) requires
     * the caller to be an admin; everyone else writes a personal preset.
     */
    private function resolveOwnerId(Request $request): ?int
    {
        $userId = (int) Auth::id();
        $wantsShared = $request->boolean('shared');

        if ($wantsShared && $this->isAdmin()) {
            return null;
        }

        return $userId;
    }

    private function canMutate(Object3dCameraBookmark $bookmark): bool
    {
        $userId = (int) Auth::id();

        // Personal bookmarks: must be owner.
        if (! is_null($bookmark->user_id)) {
            return $bookmark->user_id === $userId;
        }

        // Shared bookmarks: admin only.
        return $this->isAdmin();
    }

    /**
     * Best-effort admin check via the standard AtoM ACL group membership
     * (group 100 = administrator in base AtoM seed data). Falls back to
     * the QubitAcl-style group check used elsewhere in Heratio if the
     * helper isn't available.
     */
    private function isAdmin(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // Heratio user model exposes isAdmin() in some namespaces.
        if (method_exists($user, 'isAdmin')) {
            try {
                if ($user->isAdmin()) {
                    return true;
                }
            } catch (\Throwable $e) {
                // fall through to manual lookup
            }
        }

        try {
            return DB::table('user_group')
                ->where('user_id', $user->id)
                ->where('group_id', 100)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * After saving a row as default, clear is_default on the caller's
     * other rows in the same scope (personal vs shared) for this model.
     */
    private function clearOtherDefaults(Object3dCameraBookmark $bookmark): void
    {
        $query = Object3dCameraBookmark::query()
            ->where('object_3d_id', $bookmark->object_3d_id)
            ->where('id', '!=', $bookmark->id);

        if (is_null($bookmark->user_id)) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $bookmark->user_id);
        }

        $query->update(['is_default' => false]);
    }
}
