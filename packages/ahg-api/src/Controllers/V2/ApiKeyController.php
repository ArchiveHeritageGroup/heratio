<?php

namespace AhgApi\Controllers\V2;

use AhgApi\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends BaseApiController
{
    public function __construct(protected ApiKeyService $keyService)
    {
        parent::__construct();
    }

    /**
     * GET /api/v2/keys
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $this->apiUserId($request);
        if (!$userId) {
            return $this->error('Unauthorized', 'Authentication required.', 401);
        }

        $keys = $this->keyService->listKeys($userId);
        return $this->success(['keys' => $keys]);
    }

    /**
     * POST /api/v2/keys
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $this->apiUserId($request);
        if (!$userId) {
            return $this->error('Unauthorized', 'Authentication required.', 401);
        }

        $input = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'scopes.*' => 'in:read,write,delete,batch',
            'rate_limit' => 'nullable|integer|min:100|max:100000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $key = $this->keyService->createKey(
            $userId,
            $input['name'],
            $input['scopes'] ?? ['read'],
            $input['rate_limit'] ?? 1000,
            $input['expires_at'] ?? null,
        );

        return $this->success($key, 201);
    }

    /**
     * DELETE /api/v2/keys/{id}
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $userId = $this->apiUserId($request);
        if (!$userId) {
            return $this->error('Unauthorized', 'Authentication required.', 401);
        }

        $deleted = $this->keyService->deleteKey($id, $userId);
        if (!$deleted) {
            return $this->error('Not Found', 'API key not found or not owned by you.', 404);
        }

        return $this->success(['message' => 'API key deleted.']);
    }
}
