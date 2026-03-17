<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PhysicalObjectApiController extends Controller
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

        $query = DB::table('physical_object as po')
            ->join('object', 'po.id', '=', 'object.id')
            ->leftJoin('physical_object_i18n as po_i18n', function ($join) {
                $join->on('po.id', '=', 'po_i18n.id')
                    ->where('po_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('term_i18n as type_term', function ($join) {
                $join->on('po.type_id', '=', 'type_term.id')
                    ->where('type_term.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'po.id', '=', 'slug.object_id');

        $total = $query->count();
        $results = $query
            ->select(
                'po.id', 'po.type_id',
                'po_i18n.name', 'po_i18n.location', 'po_i18n.description',
                'type_term.name as type_name',
                'slug.slug',
                'object.created_at', 'object.updated_at'
            )
            ->orderBy('po_i18n.name', $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json(['total' => $total, 'page' => $page, 'limit' => $limit, 'results' => $results]);
    }

    public function show(string $slug): JsonResponse
    {
        $po = DB::table('physical_object as po')
            ->join('object', 'po.id', '=', 'object.id')
            ->leftJoin('physical_object_i18n as po_i18n', function ($join) {
                $join->on('po.id', '=', 'po_i18n.id')
                    ->where('po_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('term_i18n as type_term', function ($join) {
                $join->on('po.type_id', '=', 'type_term.id')
                    ->where('type_term.culture', '=', $this->culture);
            })
            ->join('slug', 'po.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('po.*', 'po_i18n.*', 'type_term.name as type_name', 'slug.slug', 'object.created_at', 'object.updated_at')
            ->first();

        if (!$po) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Get linked information objects
        $po->linked_descriptions = DB::table('relation')
            ->join('information_object_i18n', function ($join) {
                $join->on('relation.subject_id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'relation.subject_id', '=', 'slug.object_id')
            ->where('relation.object_id', $po->id)
            ->select('relation.subject_id as id', 'information_object_i18n.title', 'slug.slug')
            ->get();

        return response()->json($po);
    }
}
