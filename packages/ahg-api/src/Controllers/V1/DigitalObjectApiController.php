<?php

namespace AhgApi\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DigitalObjectApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $mediaType = $request->get('media_type');

        $query = DB::table('digital_object as do')
            ->join('object', 'do.id', '=', 'object.id')
            ->leftJoin('slug', 'do.object_id', '=', 'slug.object_id')
            ->where('do.usage_id', 166); // Master

        if ($mediaType) {
            $query->where('do.media_type_id', $mediaType);
        }

        $total = $query->count();
        $results = $query
            ->select(
                'do.id', 'do.object_id', 'do.usage_id',
                'do.mime_type', 'do.media_type_id',
                'do.byte_size', 'do.name as filename',
                'do.path',
                'slug.slug as parent_slug',
                'object.created_at', 'object.updated_at'
            )
            ->orderByDesc('object.updated_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json(['total' => $total, 'page' => $page, 'limit' => $limit, 'results' => $results]);
    }
}
