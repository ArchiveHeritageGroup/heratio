<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxonomyController extends BaseApiController
{
    /**
     * GET /api/v2/taxonomies
     */
    public function index(): JsonResponse
    {
        $taxonomies = DB::table('taxonomy')
            ->join('taxonomy_i18n', 'taxonomy.id', '=', 'taxonomy_i18n.id')
            ->where('taxonomy_i18n.culture', $this->culture)
            ->where('taxonomy.id', '!=', 1)
            ->select('taxonomy.id', 'taxonomy_i18n.name', 'taxonomy.usage')
            ->orderBy('taxonomy_i18n.name')
            ->get();

        // Term counts
        $counts = DB::table('term')
            ->selectRaw('taxonomy_id, COUNT(*) as cnt')
            ->groupBy('taxonomy_id')
            ->pluck('cnt', 'taxonomy_id');

        $data = $taxonomies->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'usage' => $t->usage,
            'term_count' => $counts[$t->id] ?? 0,
        ]);

        return $this->success($data);
    }

    /**
     * GET /api/v2/taxonomies/{id}/terms
     */
    public function terms(int $id, Request $request): JsonResponse
    {
        $taxonomy = DB::table('taxonomy')->where('id', $id)->first();
        if (!$taxonomy) {
            return $this->error('Not Found', "Taxonomy {$id} not found.", 404);
        }

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);
        $offset = ($page - 1) * $limit;

        $query = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', $id)
            ->where('term_i18n.culture', $this->culture);

        $total = $query->count();

        $rows = $query
            ->select('term.id', 'term.parent_id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $this->paginated($rows, $total, $page, $limit, "/api/v2/taxonomies/{$id}/terms");
    }
}
