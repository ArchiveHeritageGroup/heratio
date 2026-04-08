<?php

namespace AhgResearch\Middleware;

use AhgResearch\Services\OdrlService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Middleware to enforce ODRL policies on archival descriptions.
 *
 * Usage in routes:
 *   ->middleware('odrl:use')           — checks 'use' action
 *   ->middleware('odrl:reproduce')     — checks 'reproduce' action
 *   ->middleware('odrl:distribute')    — checks 'distribute' action
 *
 * The middleware resolves the archival description ID from:
 *   1. Route parameter 'slug' (looks up slug → object_id)
 *   2. Route parameter 'id' (direct ID)
 *
 * If no policies exist for the target, access is allowed.
 * If the user is an administrator, access is always allowed.
 */
class OdrlPolicyMiddleware
{
    public function handle(Request $request, Closure $next, string $action = 'use')
    {
        $user = Auth::user();

        // Admins bypass all policies
        if ($user && $this->isAdmin($user->id)) {
            return $next($request);
        }

        // Resolve the archival description ID
        $objectId = $this->resolveObjectId($request);
        if (!$objectId) {
            return $next($request); // Can't determine target — allow
        }

        // Check if any policies exist for this object
        $odrlService = app(OdrlService::class);
        $researcherId = $this->getResearcherId($user);

        if (!$odrlService->isPermitted('archival_description', $objectId, $researcherId, $action)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Access denied by rights policy',
                    'action' => $action,
                    'object_id' => $objectId,
                ], 403);
            }

            // Log the denied access
            $odrlService->evaluateAccess('archival_description', $objectId, $researcherId ?? 0, $action);

            abort(403, "Access denied: your account does not have permission to {$action} this resource. Contact the repository administrator to request access.");
        }

        return $next($request);
    }

    private function resolveObjectId(Request $request): ?int
    {
        // Try slug first
        $slug = $request->route('slug');
        if ($slug) {
            $obj = DB::table('slug')->where('slug', $slug)->first();
            return $obj ? (int) $obj->object_id : null;
        }

        // Try direct ID
        $id = $request->route('id');
        if ($id && is_numeric($id)) {
            return (int) $id;
        }

        return null;
    }

    private function isAdmin(int $userId): bool
    {
        return DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->where('group_id', 100) // Administrator group
            ->exists();
    }

    private function getResearcherId($user): ?int
    {
        if (!$user) {
            return null;
        }

        return DB::table('research_researcher')
            ->where('user_id', $user->id)
            ->value('id');
    }
}
