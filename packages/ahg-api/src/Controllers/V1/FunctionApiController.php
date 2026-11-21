<?php

namespace AhgApi\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

class FunctionApiController extends Controller
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
        $sortDir = strtolower($request->get('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = DB::table('function_object as fo')
            ->join('object', 'fo.id', '=', 'object.id')
            ->tap(fn ($q) => I18nFallback::join($q, 'function_object_i18n as fo_i18n', 'fo.id', $this->culture))
            ->leftJoin('slug', 'fo.id', '=', 'slug.object_id');

        $total = $query->count();
        $results = $query
            ->select(
                'fo.id', 'fo.type_id', 'fo.description_identifier',
                I18nFallback::col('fo_i18n', 'authorized_form_of_name', 'authorized_form_of_name'), I18nFallback::col('fo_i18n', 'dates', 'dates'),
                I18nFallback::col('fo_i18n', 'description', 'description'), I18nFallback::col('fo_i18n', 'history', 'history'),
                'slug.slug',
                'object.created_at', 'object.updated_at'
            )
            ->orderBy(I18nFallback::col('fo_i18n', 'authorized_form_of_name'), $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json(['total' => $total, 'page' => $page, 'limit' => $limit, 'results' => $results]);
    }

    public function show(string $slug): JsonResponse
    {
        $fn = DB::table('function_object as fo')
            ->join('object', 'fo.id', '=', 'object.id')
            ->tap(fn ($q) => I18nFallback::join($q, 'function_object_i18n as fo_i18n', 'fo.id', $this->culture))
            ->join('slug', 'fo.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('fo.*', I18nFallback::col('fo_i18n', 'authorized_form_of_name', 'authorized_form_of_name'), I18nFallback::col('fo_i18n', 'classification', 'classification'), I18nFallback::col('fo_i18n', 'dates', 'dates'), I18nFallback::col('fo_i18n', 'description', 'description'), I18nFallback::col('fo_i18n', 'history', 'history'), I18nFallback::col('fo_i18n', 'legislation', 'legislation'), I18nFallback::col('fo_i18n', 'institution_identifier', 'institution_identifier'), I18nFallback::col('fo_i18n', 'revision_history', 'revision_history'), I18nFallback::col('fo_i18n', 'rules', 'rules'), I18nFallback::col('fo_i18n', 'sources', 'sources'), 'slug.slug', 'object.created_at', 'object.updated_at')
            ->first();

        if (! $fn) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($fn);
    }
}
