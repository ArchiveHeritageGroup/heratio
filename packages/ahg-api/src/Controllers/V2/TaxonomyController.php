<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

class TaxonomyController extends BaseApiController
{
    /**
     * GET /api/v2/taxonomies
     */
    public function index(): JsonResponse
    {
        $taxonomies = DB::table('taxonomy')
            ->tap(fn ($q) => I18nFallback::join($q, 'taxonomy_i18n', 'taxonomy.id', $this->culture, required: true))
            ->where('taxonomy.id', '!=', 1)
            ->select('taxonomy.id', I18nFallback::col('taxonomy_i18n', 'name', 'name'), 'taxonomy.usage')
            ->orderBy(I18nFallback::col('taxonomy_i18n', 'name'))
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
        if (! $taxonomy) {
            return $this->error('Not Found', "Taxonomy {$id} not found.", 404);
        }

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);
        $offset = $this->listOffset($request, $page, $limit);

        $query = DB::table('term')
            ->tap(fn ($q) => I18nFallback::join($q, 'term_i18n', 'term.id', $this->culture, required: true))
            ->where('term.taxonomy_id', $id);
        // ?search= - every word must appear in the name, in any order (PSIS).
        $this->whereWords($query, 'term_i18n.name', (string) $request->get('search', ''));

        $total = $query->count();

        $rows = $query
            ->select('term.id', 'term.parent_id', I18nFallback::col('term_i18n', 'name', 'name'))
            ->orderBy(I18nFallback::col('term_i18n', 'name'))
            ->offset($offset)
            ->limit($limit)
            ->get();

        $rows = $rows->map(fn ($r) => [
            'id' => $r->id, 'name' => $r->name, 'parent_id' => $r->parent_id != 1 ? $r->parent_id : null,
        ]);

        return $this->listing($rows, $total, $limit, $offset, "/api/v2/taxonomies/{$id}/terms", 'terms');
    }
}
