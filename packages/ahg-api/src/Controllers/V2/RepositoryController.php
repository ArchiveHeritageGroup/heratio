<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepositoryController extends BaseApiController
{
    /**
     * GET /api/v2/repositories
     */
    public function index(Request $request): JsonResponse
    {
        ['page' => $page, 'limit' => $limit, 'sort' => $sort, 'sortDir' => $sortDir] = $this->paginationParams($request);
        $offset = ($page - 1) * $limit;

        $query = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('object.class_name', 'QubitRepository');

        $total = $query->count();

        match ($sort) {
            'alphabetic', 'name' => $query->orderBy('actor_i18n.authorized_form_of_name', $sortDir),
            default => $query->orderBy('object.updated_at', $sortDir),
        };

        $rows = $query->select(
            'repository.id', 'actor_i18n.authorized_form_of_name',
            'object.created_at', 'object.updated_at', 'slug.slug'
        )->offset($offset)->limit($limit)->get();

        // Holdings counts
        $repoIds = $rows->pluck('id')->toArray();
        $holdingsCounts = [];
        if (!empty($repoIds)) {
            $holdingsCounts = DB::table('information_object')
                ->whereIn('repository_id', $repoIds)
                ->selectRaw('repository_id, COUNT(*) as cnt')
                ->groupBy('repository_id')
                ->pluck('cnt', 'repository_id')
                ->toArray();
        }

        $data = $rows->map(fn ($r) => [
            'id' => $r->id,
            'slug' => $r->slug,
            'name' => $r->authorized_form_of_name,
            'holdings_count' => $holdingsCounts[$r->id] ?? 0,
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
        ]);

        return $this->paginated($data, $total, $page, $limit, '/api/v2/repositories');
    }
}
