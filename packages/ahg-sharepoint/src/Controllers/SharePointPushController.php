<?php

namespace AhgSharePoint\Controllers;

use AhgSharePoint\Services\GraphTokenValidatorService;
use AhgSharePoint\Services\SharePointPushService;
use AhgSharePoint\Services\SharePointUserMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2.B push endpoints — POST /api/v2/sharepoint/push/* .
 *
 * AAD bearer token required. Routes are CSRF-exempt (see routes/api.php
 * and the package service provider middleware config).
 *
 * @phase 2.B
 */
class SharePointPushController extends Controller
{
    public function __construct(
        private GraphTokenValidatorService $validator,
        private SharePointPushService $push,
        private SharePointUserMappingService $userMapping,
    ) {
    }

    /** POST /api/v2/sharepoint/push/projection */
    public function projection(Request $request): JsonResponse
    {
        return $this->withClaims($request, function (array $body, array $claims) {
            return response()->json($this->push->project($body, $claims));
        });
    }

    /** POST /api/v2/sharepoint/push */
    public function commit(Request $request): JsonResponse
    {
        return $this->withClaims($request, function (array $body, array $claims) {
            $userId = $this->userMapping->resolve($claims);
            if ($userId === null) {
                return response()->json(['error' => 'aad_user_not_mapped'], 403);
            }
            $jobId = $this->push->commit($body, $userId, $claims);
            return response()->json(['ingest_job_id' => $jobId], 201);
        });
    }

    /** GET /api/v2/sharepoint/push/jobs/{id} */
    public function job(Request $request, int $id): JsonResponse
    {
        $claims = $this->bearerClaims($request);
        if ($claims === null) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        $row = DB::table('ingest_job')->where('id', $id)->first();
        if ($row === null) {
            return response()->json(['error' => 'not_found'], 404);
        }
        return response()->json([
            'id' => (int) $row->id,
            'status' => $row->status ?? null,
            'progress' => $row->progress ?? null,
            'error' => $row->error_message ?? null,
            'primary_object_id' => isset($row->primary_object_id) ? (int) $row->primary_object_id : null,
        ]);
    }

    private function withClaims(Request $request, callable $closure): JsonResponse
    {
        $claims = $this->bearerClaims($request);
        if ($claims === null) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        $body = $request->json()->all();
        if (!is_array($body)) {
            return response()->json(['error' => 'invalid_json'], 400);
        }
        try {
            return $closure($body, $claims);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function bearerClaims(Request $request): ?array
    {
        $auth = $request->header('Authorization');
        if (!$auth || stripos($auth, 'Bearer ') !== 0) {
            return null;
        }
        $token = trim(substr($auth, 7));
        if ($token === '') {
            return null;
        }
        $tenantId = (int) ($request->input('tenant_id') ?? 0);
        if ($tenantId <= 0) {
            return null;
        }
        try {
            return $this->validator->validate($token, $tenantId);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
