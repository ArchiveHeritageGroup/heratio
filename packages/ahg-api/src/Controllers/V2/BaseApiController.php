<?php

namespace AhgApi\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class BaseApiController extends Controller
{
    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    /**
     * Standard v2 success response.
     */
    protected function success($data, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => true,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ], $extra), $status);
    }

    /**
     * Standard v2 error response.
     */
    protected function error(string $error, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $error,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }

    /**
     * Paginated v2 response.
     */
    protected function paginated($data, int $total, int $page, int $limit, string $path): JsonResponse
    {
        $lastPage = max(1, (int) ceil($total / $limit));
        $baseUrl = url($path);

        $links = ['self' => "{$baseUrl}?page={$page}&limit={$limit}"];
        if ($page < $lastPage) {
            $links['next'] = "{$baseUrl}?page=".($page + 1)."&limit={$limit}";
        }
        if ($page > 1) {
            $links['prev'] = "{$baseUrl}?page=".($page - 1)."&limit={$limit}";
        }

        return response()->json([
            'success' => true,
            'data' => is_array($data) ? $data : $data->values(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'last_page' => $lastPage,
            ],
            'links' => $links,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * List response in the PSIS shape: data = {total, limit, skip, results}
     * (#1558, so one client reads PSIS and Heratio alike), with meta and links
     * kept at the top level for page-based clients.
     */
    protected function listing($rows, int $total, int $limit, int $skip, string $path, string $key = 'results'): JsonResponse
    {
        $page = intdiv($skip, max(1, $limit)) + 1;
        $lastPage = max(1, (int) ceil($total / max(1, $limit)));
        $baseUrl = url($path);
        $links = ['self' => "{$baseUrl}?page={$page}&limit={$limit}"];
        if ($page < $lastPage) {
            $links['next'] = "{$baseUrl}?page=".($page + 1)."&limit={$limit}";
        }
        if ($page > 1) {
            $links['prev'] = "{$baseUrl}?page=".($page - 1)."&limit={$limit}";
        }

        return response()->json([
            'success' => true,
            'data' => ['total' => $total, 'limit' => $limit, 'skip' => $skip, $key => is_array($rows) ? array_values($rows) : $rows->values()],
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'last_page' => $lastPage],
            'links' => $links,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Offset for a list: an explicit skip wins, else it follows from page.
     */
    protected function listOffset(Request $request, int $page, int $limit): int
    {
        return $request->filled('skip') ? max(0, (int) $request->get('skip')) : ($page - 1) * $limit;
    }

    /**
     * The key owner as an ACL user object, or null for no user.
     */
    protected function apiUser(Request $request): ?object
    {
        $id = $this->apiUserId($request);

        return $id ? (object) ['id' => (int) $id] : null;
    }

    /**
     * Administrator or editor: may see provenance details kept from the public
     * and everyone's proposals.
     */
    protected function isStaff(Request $request): bool
    {
        $user = $this->apiUser($request);

        return $user && (\AhgCore\Services\AclService::isAdministrator($user) || \AhgCore\Services\AclService::isEditor($user));
    }

    /**
     * Parse pagination params from request.
     */
    protected function paginationParams(Request $request): array
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $skip = (int) $request->get('skip', 0);
        $sort = $request->get('sort', 'updated');
        $sortDir = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        return compact('page', 'limit', 'skip', 'sort', 'sortDir');
    }

    /**
     * Every word of $search (up to six) must appear in $column, in any order,
     * so "Hendrik Engelbrecht" also finds "Engelbrecht, Hendrik".
     */
    protected function whereWords($query, string $column, string $search): void
    {
        $words = array_slice(preg_split('/[\s,]+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY), 0, 6);
        foreach ($words as $w) {
            $query->where($column, 'like', '%'.addcslashes(mb_substr($w, 0, 100), '%_\\').'%');
        }
    }

    /**
     * Resolve term name by ID.
     */
    protected function termName(?int $termId): ?string
    {
        if (! $termId) {
            return null;
        }

        return DB::table('term_i18n')
            ->where('id', $termId)
            ->where('culture', $this->culture)
            ->value('name');
    }

    /**
     * Get authenticated user ID from request attributes.
     */
    protected function apiUserId(Request $request): ?int
    {
        return $request->attributes->get('api_user_id');
    }

    /**
     * Get API key ID from request attributes.
     */
    protected function apiKeyId(Request $request): ?int
    {
        return $request->attributes->get('api_key_id');
    }

    /**
     * Check if request has a specific scope.
     */
    protected function hasScope(Request $request, string $scope): bool
    {
        $scopes = $request->attributes->get('api_scopes', []);

        return in_array($scope, $scopes);
    }

    /**
     * Look up an object by slug, return its ID or null.
     */
    protected function slugToId(string $slug): ?int
    {
        return DB::table('slug')->where('slug', $slug)->value('object_id');
    }

    /**
     * Generate a unique slug for a title.
     */
    protected function generateSlug(string $title): string
    {
        $base = \Illuminate\Support\Str::slug($title);
        if (empty($base)) {
            $base = 'untitled';
        }

        $slug = $base;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
