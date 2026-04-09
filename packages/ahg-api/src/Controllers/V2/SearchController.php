<?php

namespace AhgApi\Controllers\V2;

use AhgCore\Constants\TermId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends BaseApiController
{
    /**
     * POST /api/v2/search
     */
    public function search(Request $request): JsonResponse
    {
        $input = $request->validate([
            'query' => 'required|string|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'skip' => 'nullable|integer|min:0',
            'filters' => 'nullable|array',
            'filters.repository' => 'nullable|string',
            'filters.level' => 'nullable|integer',
        ]);

        $queryStr = $input['query'];
        $limit = min(100, $input['limit'] ?? 10);
        $skip = $input['skip'] ?? 0;
        $searchTerm = '%' . $queryStr . '%';

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->join('object', 'io.id', '=', 'object.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', TermId::STATUS_TYPE_PUBLICATION);
            })
            ->where('ioi.culture', $this->culture)
            ->where('io.id', '!=', 1)
            ->where('status.status_id', TermId::PUBLICATION_STATUS_PUBLISHED)
            ->where(function ($q) use ($searchTerm) {
                $q->where('ioi.title', 'LIKE', $searchTerm)
                    ->orWhere('io.identifier', 'LIKE', $searchTerm)
                    ->orWhere('ioi.scope_and_content', 'LIKE', $searchTerm)
                    ->orWhere('ioi.archival_history', 'LIKE', $searchTerm);
            });

        // Filters
        $filters = $input['filters'] ?? [];
        if (!empty($filters['repository'])) {
            $repoId = is_numeric($filters['repository']) ? $filters['repository'] : $this->slugToId($filters['repository']);
            if ($repoId) {
                $query->where('io.repository_id', $repoId);
            }
        }
        if (!empty($filters['level'])) {
            $query->where('io.level_of_description_id', $filters['level']);
        }

        $total = $query->count();

        $rows = $query
            ->select('io.id', 'io.identifier', 'io.level_of_description_id', 'ioi.title', 'slug.slug')
            ->orderByRaw("CASE WHEN ioi.title LIKE ? THEN 0 ELSE 1 END", [$searchTerm])
            ->orderByDesc('object.updated_at')
            ->offset($skip)
            ->limit($limit)
            ->get();

        $levelNames = [];
        $levelIds = $rows->pluck('level_of_description_id')->filter()->unique()->values()->toArray();
        if (!empty($levelIds)) {
            $levelNames = DB::table('term_i18n')->whereIn('id', $levelIds)->where('culture', $this->culture)->pluck('name', 'id')->toArray();
        }

        $results = $rows->map(fn ($r) => [
            'id' => $r->id,
            'slug' => $r->slug,
            'title' => $r->title,
            'identifier' => $r->identifier,
            'level' => $levelNames[$r->level_of_description_id] ?? null,
        ]);

        return $this->success([
            'total' => $total,
            'limit' => $limit,
            'skip' => $skip,
            'results' => $results->values(),
        ]);
    }
}
