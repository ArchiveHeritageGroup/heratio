<?php

namespace AhgApi\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function terms(int $taxonomyId = null): JsonResponse
    {
        // If no taxonomyId, list all terms
        if ($taxonomyId === null) {
            $terms = DB::table('term')
                ->leftJoin('term_i18n', function ($j) {
                    $j->on('term.id', '=', 'term_i18n.id')
                        ->where('term_i18n.culture', '=', $this->culture);
                })
                ->where('term.id', '!=', 1) // Exclude root
                ->orderBy('term_i18n.name')
                ->select([
                    'term.id',
                    'term.taxonomy_id',
                    'term.parent_id',
                    'term_i18n.name',
                    'term.code',
                    'term.source_culture',
                ])
                ->limit(100)
                ->get();

            $data = $terms->map(function ($term) {
                return [
                    'id' => $term->id,
                    'taxonomy_id' => $term->taxonomy_id,
                    'name' => $term->name,
                    'parent_id' => $term->parent_id,
                    'code' => $term->code,
                ];
            });

            return response()->json([
                'data' => $data->values(),
                'meta' => [
                    'total' => $data->count(),
                ],
            ]);
        }

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

    /**
     * GET /api/term/search?q=query
     *
     * Search terms by name.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json(['data' => []]);
        }
        
        $results = DB::table('term')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('term.id', '!=', 1)
            ->where('term_i18n.name', 'like', "%{$query}%")
            ->select([
                'term.id',
                'term.taxonomy_id',
                'term_i18n.name',
                'term.code',
            ])
            ->limit(20)
            ->get();
        
        $data = $results->map(fn($row) => [
            'id' => $row->id,
            'taxonomy_id' => $row->taxonomy_id,
            'name' => $row->name,
            'code' => $row->code,
        ]);
        
        return response()->json(['data' => $data->values()]);
    }

    /**
     * GET /api/term/{id}
     *
     * Show a single term.
     */
    public function show(string $id): JsonResponse
    {
        $termId = (int) $id;
        
        $term = DB::table('term')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('term.id', $termId)
            ->select([
                'term.id',
                'term.taxonomy_id',
                'term.parent_id',
                'term_i18n.name',
                'term.code',
                'term.source_culture',
            ])
            ->first();

        if (!$term) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Term ID {$id} not found.",
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $term->id,
                'taxonomy_id' => $term->taxonomy_id,
                'name' => $term->name,
                'parent_id' => $term->parent_id,
                'code' => $term->code,
            ],
        ]);
    }

    /**
     * POST /api/term
     *
     * Create a new term.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:500',
            'taxonomy_id' => 'required|integer',
            'parent_id' => 'nullable|integer',
            'code' => 'nullable|string|max:100',
        ]);
        
        // Verify taxonomy exists
        if (!DB::table('taxonomy')->where('id', $validated['taxonomy_id'])->exists()) {
            return response()->json([
                'error' => 'Bad Request',
                'message' => "Taxonomy ID {$validated['taxonomy_id']} not found.",
            ], 400);
        }
        
        try {
            return DB::transaction(function () use ($validated) {
                // Create base object
                $objectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitTerm',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Create term record
                DB::table('term')->insert([
                    'id' => $objectId,
                    'taxonomy_id' => $validated['taxonomy_id'],
                    'parent_id' => $validated['parent_id'] ?? null,
                    'code' => $validated['code'] ?? null,
                    'source_culture' => $this->culture,
                ]);
                
                // Create i18n record
                DB::table('term_i18n')->insert([
                    'id' => $objectId,
                    'culture' => $this->culture,
                    'name' => $validated['name'],
                ]);
                
                return response()->json([
                    'data' => [
                        'id' => $objectId,
                        'name' => $validated['name'],
                        'taxonomy_id' => $validated['taxonomy_id'],
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create term',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/term/{id}
     *
     * Update an existing term.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:500',
            'parent_id' => 'nullable|integer',
            'code' => 'nullable|string|max:100',
        ]);
        
        $termId = (int) $id;
        
        if (!DB::table('term')->where('id', $termId)->exists()) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Term ID {$id} not found.",
            ], 404);
        }
        
        try {
            // Update term record
            if (isset($validated['parent_id']) || isset($validated['code'])) {
                DB::table('term')
                    ->where('id', $termId)
                    ->update(array_filter([
                        'parent_id' => $validated['parent_id'] ?? null,
                        'code' => $validated['code'] ?? null,
                    ], fn($v) => $v !== null));
            }
            
            // Update i18n record
            if (isset($validated['name'])) {
                DB::table('term_i18n')
                    ->where('id', $termId)
                    ->where('culture', $this->culture)
                    ->update(['name' => $validated['name']]);
            }
            
            return response()->json([
                'data' => [
                    'id' => $termId,
                    'message' => 'Term updated successfully.',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update term',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/term/{id}
     *
     * Delete a term.
     */
    public function destroy(string $id): JsonResponse
    {
        $termId = (int) $id;
        
        if (!DB::table('term')->where('id', $termId)->exists()) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Term ID {$id} not found.",
            ], 404);
        }
        
        try {
            // Delete related records
            DB::table('term_i18n')->where('id', $termId)->delete();
            DB::table('object_term_relation')->where('term_id', $termId)->delete();
            DB::table('term')->where('id', $termId)->delete();
            DB::table('object')->where('id', $termId)->delete();
            
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete term',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
