<?php

namespace AhgApi\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyApiController extends Controller
{
    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    /**
     * GET/POST /api/search/io — Legacy IO search.
     */
    public function searchIo(Request $request): JsonResponse
    {
        $queryStr = $request->get('query', $request->get('q', ''));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $skip = max(0, (int) $request->get('skip', 0));

        if (empty($queryStr)) {
            return response()->json(['error' => 'Query parameter required.'], 400);
        }

        $searchTerm = '%' . $queryStr . '%';

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', 158);
            })
            ->where('ioi.culture', $this->culture)
            ->where('io.id', '!=', 1)
            ->where('status.status_id', 160)
            ->where(function ($q) use ($searchTerm) {
                $q->where('ioi.title', 'LIKE', $searchTerm)
                    ->orWhere('io.identifier', 'LIKE', $searchTerm)
                    ->orWhere('ioi.scope_and_content', 'LIKE', $searchTerm);
            });

        $total = $query->count();

        $results = $query
            ->select('io.id', 'ioi.title', 'io.identifier', 'slug.slug',
                'io.level_of_description_id', 'io.repository_id')
            ->orderByRaw("CASE WHEN ioi.title LIKE ? THEN 0 ELSE 1 END", [$searchTerm])
            ->offset($skip)
            ->limit($limit)
            ->get();

        return response()->json([
            'total' => $total,
            'limit' => $limit,
            'skip' => $skip,
            'results' => $results,
        ]);
    }

    /**
     * GET/POST /api/autocomplete/glam — GLAM autocomplete for search boxes.
     */
    public function autocompleteGlam(Request $request): JsonResponse
    {
        $queryStr = $request->get('query', $request->get('q', ''));
        $limit = min(20, max(1, (int) $request->get('limit', 10)));

        if (strlen($queryStr) < 2) {
            return response()->json(['results' => []]);
        }

        $searchTerm = $queryStr . '%';

        // Search titles
        $titles = DB::table('information_object_i18n as ioi')
            ->join('information_object as io', 'ioi.id', '=', 'io.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', 158);
            })
            ->where('ioi.culture', $this->culture)
            ->where('io.id', '!=', 1)
            ->where('status.status_id', 160)
            ->where('ioi.title', 'LIKE', $searchTerm)
            ->select('io.id', 'ioi.title as label', 'slug.slug')
            ->selectRaw("'description' as type")
            ->limit($limit)
            ->get();

        // Search actor names
        $actors = DB::table('actor_i18n as ai')
            ->join('actor', 'ai.id', '=', 'actor.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('ai.culture', $this->culture)
            ->where('object.class_name', 'QubitActor')
            ->where('actor.parent_id', '!=', 0)
            ->where('ai.authorized_form_of_name', 'LIKE', $searchTerm)
            ->select('actor.id', 'ai.authorized_form_of_name as label', 'slug.slug')
            ->selectRaw("'authority' as type")
            ->limit($limit)
            ->get();

        // Search repository names
        $repos = DB::table('actor_i18n as ai')
            ->join('repository', 'ai.id', '=', 'repository.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->where('ai.culture', $this->culture)
            ->where('object.class_name', 'QubitRepository')
            ->where('ai.authorized_form_of_name', 'LIKE', $searchTerm)
            ->select('repository.id', 'ai.authorized_form_of_name as label', 'slug.slug')
            ->selectRaw("'repository' as type")
            ->limit($limit)
            ->get();

        $results = $titles->merge($actors)->merge($repos)->take($limit);

        return response()->json(['results' => $results->values()]);
    }

    /**
     * GET /api/export-preview — Export statistics.
     */
    public function exportPreview(): JsonResponse
    {
        $ioCount = DB::table('information_object')->where('id', '!=', 1)->count();
        $actorCount = DB::table('actor')->join('object', 'actor.id', '=', 'object.id')
            ->where('object.class_name', 'QubitActor')->where('actor.parent_id', '!=', 0)->count();
        $repoCount = DB::table('repository')->join('object', 'repository.id', '=', 'object.id')
            ->where('object.class_name', 'QubitRepository')->count();

        $publishedCount = DB::table('information_object as io')
            ->join('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', 158);
            })
            ->where('io.id', '!=', 1)
            ->where('status.status_id', 160)
            ->count();

        return response()->json([
            'descriptions' => $ioCount,
            'published_descriptions' => $publishedCount,
            'authority_records' => $actorCount,
            'repositories' => $repoCount,
        ]);
    }

    /**
     * GET /api/reports/pending-counts — Counts for UI badges.
     */
    public function pendingCounts(): JsonResponse
    {
        // Draft descriptions
        $draftCount = DB::table('information_object as io')
            ->join('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', 158);
            })
            ->where('io.id', '!=', 1)
            ->where('status.status_id', 159)
            ->count();

        // Failed jobs (if table exists)
        $failedJobs = 0;
        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            // Table may not exist
        }

        return response()->json([
            'draft_descriptions' => $draftCount,
            'failed_jobs' => $failedJobs,
        ]);
    }
}
