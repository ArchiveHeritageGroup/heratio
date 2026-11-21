<?php

namespace AhgApi\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

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
            ->tap(fn ($q) => I18nFallback::join($q, 'accession_i18n', 'accession.id', $this->culture))
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
                I18nFallback::col('accession_i18n', 'title', 'title'), I18nFallback::col('accession_i18n', 'scope_and_content', 'scope_and_content'),
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
            ->tap(fn ($q) => I18nFallback::join($q, 'accession_i18n', 'accession.id', $this->culture))
            ->join('slug', function ($join) {
                $join->on('accession.id', '=', 'slug.object_id');
            })
            ->where('slug.slug', $slug)
            ->select(
                'accession.id', 'accession.identifier', 'accession.date',
                'accession.source_culture',
                I18nFallback::col('accession_i18n', 'appraisal', 'appraisal'), I18nFallback::col('accession_i18n', 'archival_history', 'archival_history'), I18nFallback::col('accession_i18n', 'location_information', 'location_information'), I18nFallback::col('accession_i18n', 'physical_characteristics', 'physical_characteristics'), I18nFallback::col('accession_i18n', 'processing_notes', 'processing_notes'), I18nFallback::col('accession_i18n', 'received_extent_units', 'received_extent_units'), I18nFallback::col('accession_i18n', 'scope_and_content', 'scope_and_content'), I18nFallback::col('accession_i18n', 'source_of_acquisition', 'source_of_acquisition'), I18nFallback::col('accession_i18n', 'title', 'title'),
                'slug.slug',
                'object.created_at', 'object.updated_at'
            )
            ->first();

        if (! $accession) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Get linked donors via relation table
        $donors = DB::table('relation')
            ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'relation.subject_id', $this->culture, required: true))
            ->where('relation.object_id', $accession->id)
            ->where('relation.type_id', 173) // accession-donor relation
            ->select('relation.subject_id as id', I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'authorized_form_of_name'))
            ->get();

        $accession->donors = $donors;

        return response()->json($accession);
    }
}
