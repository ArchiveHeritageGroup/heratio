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

    /**
     * POST /api/v1/digitalobjects — Upload a digital object for an information object.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:512000',
            'object_slug' => 'required|string',
        ]);

        $parentId = DB::table('slug')->where('slug', $request->get('object_slug'))->value('object_id');
        if (!$parentId) {
            return response()->json(['error' => 'Parent information object not found.'], 404);
        }

        $file = $request->file('file');
        $mime = $file->getMimeType();

        $type = match (true) {
            str_starts_with($mime, 'image/') => 'images',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'model/') => 'models',
            default => 'other',
        };

        $path = sprintf('uploads/%s/%s/%s', $type, now()->format('Y'), now()->format('m'));
        $filename = $file->hashName();
        $file->storeAs($path, $filename, 'public');

        // Create object row
        $doObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Resolve media_type_id
        $mediaTypeId = match (true) {
            str_starts_with($mime, 'image/') => 137,
            str_starts_with($mime, 'audio/') => 138,
            str_starts_with($mime, 'video/') => 139,
            $mime === 'application/pdf' => 140,
            default => null,
        };

        DB::table('digital_object')->insert([
            'id' => $doObjectId,
            'object_id' => $parentId,
            'usage_id' => 166,
            'mime_type' => $mime,
            'media_type_id' => $mediaTypeId,
            'name' => $file->getClientOriginalName(),
            'path' => "{$path}/{$filename}",
            'byte_size' => $file->getSize(),
            'sequence' => 0,
        ]);

        DB::table('object')->where('id', $parentId)->update(['updated_at' => now()]);

        return response()->json([
            'id' => $doObjectId,
            'object_id' => $parentId,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'path' => "{$path}/{$filename}",
        ], 201);
    }
}
