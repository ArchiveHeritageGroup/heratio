<?php

namespace AhgApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiAuthenticate
{
    /**
     * Authenticate via API key (header) or Laravel session.
     * Sets request attributes: api_key_id, api_user_id, api_scopes.
     */
    public function handle(Request $request, Closure $next, string ...$requiredScopes)
    {
        // Try session auth first (logged-in admin = full scopes)
        if ($request->user()) {
            $request->attributes->set('api_key_id', null);
            $request->attributes->set('api_user_id', $request->user()->id ?? null);
            $request->attributes->set('api_scopes', ['read', 'write', 'delete', 'batch', 'publish:write']);
            return $this->checkScopes($request, $next, $requiredScopes);
        }

        // Try API key from headers
        $rawKey = $request->header('X-API-Key')
            ?? $request->header('X-REST-API-Key')
            ?? $this->bearerToken($request);

        if (!$rawKey) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'API key required. Use X-API-Key header or Authorization: Bearer.',
            ], 401);
        }

        $hashedKey = hash('sha256', $rawKey);

        $apiKey = DB::table('ahg_api_key')
            ->where('api_key', $hashedKey)
            ->where('is_active', 1)
            ->first();

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Invalid or inactive API key.',
            ], 401);
        }

        // Check expiry
        if ($apiKey->expires_at && $apiKey->expires_at < now()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'API key has expired.',
            ], 401);
        }

        // Update last_used_at
        DB::table('ahg_api_key')
            ->where('id', $apiKey->id)
            ->update(['last_used_at' => now()]);

        $scopes = json_decode($apiKey->scopes, true) ?: [];

        $request->attributes->set('api_key_id', $apiKey->id);
        $request->attributes->set('api_user_id', $apiKey->user_id);
        $request->attributes->set('api_scopes', $scopes);

        return $this->checkScopes($request, $next, $requiredScopes);
    }

    protected function checkScopes(Request $request, Closure $next, array $requiredScopes)
    {
        if (empty($requiredScopes)) {
            return $next($request);
        }

        $scopes = $request->attributes->get('api_scopes', []);
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $scopes)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => "Scope '{$scope}' required.",
                ], 403);
            }
        }

        return $next($request);
    }

    protected function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
