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
        // Session auth. #1395(A) - a session user only receives write/delete/
        // batch/publish scopes if they are an admin; everyone else is read-only.
        // Previously ANY authenticated user (incl. a self-registered researcher)
        // got the full scope set, enabling unauthorised CRUD via the api routes
        // (e.g. RiC create/update/delete). Admin retains full scopes.
        if ($request->user()) {
            $userId = $request->user()->id ?? null;
            $request->attributes->set('api_key_id', null);
            $request->attributes->set('api_user_id', $userId);

            // #1395(A) - grant API scopes by the user's ACL capability, not by the
            // mere fact of being logged in. A self-registered researcher (no
            // create grant) is read-only; contributors/editors who may catalogue
            // get write (and delete/publish where their role allows); admins get
            // everything. Prevents unauthorised CRUD via the api routes.
            $scopes = self::sessionScopes($userId);
            $request->attributes->set('api_scopes', $scopes);

            return $this->checkScopes($request, $next, $requiredScopes);
        }

        // Try API key from headers
        $rawKey = $request->header('X-API-Key')
            ?? $request->header('X-REST-API-Key')
            ?? $this->bearerToken($request);

        if (! $rawKey) {
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

        if (! $apiKey) {
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

        // #1558 - a key whose owner has no read capability (a user with no
        // groups, e.g. the MCP's public service key) reads the public catalogue
        // only: the visibility-gated v2 catalogue routes, never v1 donor,
        // accession or other read-gated data. Same rule as PSIS (framework
        // AhgApiController::hasScope).
        $owner = self::sessionScopes((int) $apiKey->user_id);
        if (! in_array('read', $owner, true)) {
            if (! $this->isPublicCatalogueRoute($request)) {
                $write = ! in_array($request->method(), ['GET', 'HEAD'], true);

                return response()->json([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => $write
                        ? 'This key reads the public catalogue only. Proposing or changing records needs the key of a catalogue staff account.'
                        : 'This key may read the public catalogue only.',
                ], 403);
            }
            $scopes = array_values(array_intersect($scopes, ['read']));
        }

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
            if (! in_array($scope, $scopes)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => "Scope '{$scope}' required.",
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * The scopes a signed-in user holds, from their ACL capabilities (#1395 A/B):
     * read, write, delete and publish by capability, batch for admins. Also the
     * ceiling for any key that user creates.
     */
    public static function sessionScopes(?int $userId): array
    {
        $u = (object) ['id' => $userId];
        $scopes = [];
        if (\AhgCore\Services\AclService::check(null, 'read', $u)) {
            $scopes[] = 'read';
        }
        if (\AhgCore\Services\AclService::check(null, ['create', 'update'], $u)) {
            $scopes[] = 'write';
        }
        if (\AhgCore\Services\AclService::check(null, 'delete', $u)) {
            $scopes[] = 'delete';
        }
        if (\AhgCore\Services\AclService::check(null, 'publish', $u)) {
            $scopes[] = 'publish:write';
        }
        if ($userId && \AhgCore\Services\AclService::canAdmin($userId)) {
            $scopes[] = 'batch';
        }

        return $scopes;
    }

    /**
     * The routes a public-only key may use: GET/HEAD on the v2 catalogue reads,
     * which apply publication, ICIP/TK, ODRL, embargo and classification
     * themselves, plus a caller's own proposals list.
     */
    protected function isPublicCatalogueRoute(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD', 'POST'], true)) {
            return false;
        }
        $path = trim($request->path(), '/');
        if ($request->method() === 'POST') {
            return $path === 'api/v2/search';
        }

        return $path === 'api/v2'
            || (bool) preg_match('#^api/v2/(descriptions(/[^/]+(/text)?)?|authorities(/[^/]+)?|repositories|taxonomies(/\d+/terms)?|exhibitions(/[^/]+)?|search|proposals)$#', $path);
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
