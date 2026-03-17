<?php

namespace AhgApi\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessionApiController extends Controller
{
    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = $request->get('sort', 'updated');
        $sortDir = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('accession')
            ->join('object', 'accession.id', '=', 'object.id')
            ->leftJoin('accession_i18n', function ($join) {
                $join->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', function ($join) {
                $join->on('accession.id', '=', 'slug.object_id');
            });

        $orderCol = match ($sort) {
            'alphabetic', 'title' => 'accession_i18n.title',
            'identifier' => 'accession.identifier',
            default => 'object.updated_at',
        };

        $total = $query->count();
        $results = $query
            ->select(
                'accession.id', 'accession.identifier', 'accession.date',
                'accession_i18n.title', 'accession_i18n.scope_and_content',
                'slug.slug',
                'object.created_at', 'object.updated_at'
            )
            ->orderBy($orderCol, $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json([
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'results' => $results,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $accession = DB::table('accession')
            ->join('object', 'accession.id', '=', 'object.id')
            ->leftJoin('accession_i18n', function ($join) {
                $join->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $this->culture);
            })
            ->join('slug', function ($join) {
                $join->on('accession.id', '=', 'slug.object_id');
            })
            ->where('slug.slug', $slug)
            ->select(
                'accession.id', 'accession.identifier', 'accession.date',
                'accession.source_culture',
                'accession_i18n.*',
                'slug.slug',
                'object.created_at', 'object.updated_at'
            )
            ->first();

        if (!$accession) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Get linked donors via relation table
        $donors = DB::table('relation')
            ->join('actor_i18n', function ($join) {
                $join->on('relation.subject_id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->where('relation.object_id', $accession->id)
            ->where('relation.type_id', 173) // accession-donor relation
            ->select('relation.subject_id as id', 'actor_i18n.authorized_form_of_name')
            ->get();

        $accession->donors = $donors;

        return response()->json($accession);
    }
}
