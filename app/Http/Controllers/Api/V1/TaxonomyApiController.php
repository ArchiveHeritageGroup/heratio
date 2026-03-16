<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TaxonomyApiController extends Controller
{
    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    /**
     * GET /api/v1/taxonomies
     *
     * List all taxonomies.
     */
    public function index(): JsonResponse
    {
        $taxonomies = DB::table('taxonomy')
            ->join('taxonomy_i18n', 'taxonomy.id', '=', 'taxonomy_i18n.id')
            ->where('taxonomy_i18n.culture', $this->culture)
            ->where('taxonomy.id', '!=', 1) // Exclude root
            ->orderBy('taxonomy_i18n.name')
            ->select([
                'taxonomy.id',
                'taxonomy_i18n.name',
                'taxonomy_i18n.note',
                'taxonomy.usage',
                'taxonomy.source_culture',
            ])
            ->get();

        // Count terms per taxonomy
        $taxonomyIds = $taxonomies->pluck('id')->toArray();
        $termCounts = [];
        if (!empty($taxonomyIds)) {
            $termCounts = DB::table('term')
                ->whereIn('taxonomy_id', $taxonomyIds)
                ->select('taxonomy_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('taxonomy_id')
                ->pluck('cnt', 'taxonomy_id')
                ->toArray();
        }

        $data = $taxonomies->map(function ($tax) use ($termCounts) {
            return [
                'id' => $tax->id,
                'name' => $tax->name,
                'note' => $tax->note,
                'usage' => $tax->usage,
                'term_count' => $termCounts[$tax->id] ?? 0,
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'meta' => [
                'total' => $data->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/taxonomies/{id}/terms
     *
     * List all terms in a taxonomy.
     */
    public function terms(int $taxonomyId): JsonResponse
    {
        // Verify taxonomy exists
        $taxonomy = DB::table('taxonomy')
            ->join('taxonomy_i18n', 'taxonomy.id', '=', 'taxonomy_i18n.id')
            ->where('taxonomy.id', $taxonomyId)
            ->where('taxonomy_i18n.culture', $this->culture)
            ->select('taxonomy.id', 'taxonomy_i18n.name')
            ->first();

        if (!$taxonomy) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Taxonomy ID {$taxonomyId} not found.",
            ], 404);
        }

        $terms = DB::table('term')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('term.taxonomy_id', $taxonomyId)
            ->orderBy('term_i18n.name')
            ->select([
                'term.id',
                'term.parent_id',
                'term_i18n.name',
                'term.code',
                'term.source_culture',
            ])
            ->get();

        $data = $terms->map(function ($term) {
            return [
                'id' => $term->id,
                'name' => $term->name,
                'parent_id' => $term->parent_id,
                'code' => $term->code,
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'meta' => [
                'taxonomy_id' => $taxonomy->id,
                'taxonomy_name' => $taxonomy->name,
                'total' => $data->count(),
            ],
        ]);
    }
}
