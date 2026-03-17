<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->leftJoin('function_object_i18n as fo_i18n', function ($join) {
                $join->on('fo.id', '=', 'fo_i18n.id')
                    ->where('fo_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'fo.id', '=', 'slug.object_id');

        $total = $query->count();
        $results = $query
            ->select(
                'fo.id', 'fo.type_id', 'fo.description_identifier',
                'fo_i18n.authorized_form_of_name', 'fo_i18n.dates',
                'fo_i18n.description', 'fo_i18n.history',
                'slug.slug',
                'object.created_at', 'object.updated_at'
            )
            ->orderBy('fo_i18n.authorized_form_of_name', $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json(['total' => $total, 'page' => $page, 'limit' => $limit, 'results' => $results]);
    }

    public function show(string $slug): JsonResponse
    {
        $fn = DB::table('function_object as fo')
            ->join('object', 'fo.id', '=', 'object.id')
            ->leftJoin('function_object_i18n as fo_i18n', function ($join) {
                $join->on('fo.id', '=', 'fo_i18n.id')
                    ->where('fo_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'fo.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('fo.*', 'fo_i18n.*', 'slug.slug', 'object.created_at', 'object.updated_at')
            ->first();

        if (!$fn) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($fn);
    }
}
