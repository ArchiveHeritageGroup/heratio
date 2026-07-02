<?php

namespace AhgApi\Controllers\V1;

use AhgApi\Services\EmbeddedMetadataService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DigitalObjectApiController extends Controller
{
    public function __construct(protected ?EmbeddedMetadataService $embedded = null)
    {
        $this->embedded = $embedded ?? new EmbeddedMetadataService();
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $mediaType = $request->get('media_type');
        $includeEmbedded = $this->wantsEmbeddedMetadata($request);

        $query = DB::table('digital_object as do')
            ->join('object', 'do.id', '=', 'object.id')
            ->leftJoin('slug', 'do.object_id', '=', 'slug.object_id')
            // Publication-status gate — only expose digital objects whose parent
            // record is Published (status.type_id=158, status_id=160). Drafts are
            // never leaked to anonymous callers.
            ->join('status', function ($j) {
                $j->on('do.object_id', '=', 'status.object_id')
                    ->where('status.type_id', '=', 158)
                    ->where('status.status_id', '=', 160);
            })
            // #1384/#1389 — withhold digital objects of ICIP/ODRL-restricted or
            // PII-redacted records (raw derivatives must never leak).
            ->whereNotIn('do.object_id', app(\AhgCore\Services\DisclosureGate::class)->restrictedIds())
            ->whereNotIn('do.object_id', app(\AhgCore\Services\DisclosureGate::class)->redactedIds())
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
                // 'do.path' deliberately omitted — never leak the raw server
                // filesystem path to anonymous callers.
                'slug.slug as parent_slug',
                'object.created_at', 'object.updated_at'
            )
            ->orderByDesc('object.updated_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        if ($includeEmbedded) {
            $researcherId = $request->attributes->get('api_user_id');
            $results = $results->map(function ($row) use ($researcherId) {
                $block = $this->embedded->forDigitalObject((int) $row->id, $researcherId);
                if ($block !== null) {
                    $row->embedded_metadata = $block;
                }

                return $row;
            });
        }

        return response()->json(['total' => $total, 'page' => $page, 'limit' => $limit, 'results' => $results]);
    }

    /**
     * GET /api/v1/digital-object/{id} - show a single digital_object plus optional
     * embedded EXIF/IPTC/XMP block (issue #747). Honours `?include=embedded_metadata`.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $do = DB::table('digital_object as do')
            ->join('object', 'do.id', '=', 'object.id')
            ->leftJoin('slug', 'do.object_id', '=', 'slug.object_id')
            // Publication-status gate — only expose a digital object whose parent
            // record is Published. A draft record's digital object 404s for anon.
            ->join('status', function ($j) {
                $j->on('do.object_id', '=', 'status.object_id')
                    ->where('status.type_id', '=', 158)
                    ->where('status.status_id', '=', 160);
            })
            // #1384/#1389 — withhold digital objects of ICIP/ODRL-restricted or
            // PII-redacted records (raw derivatives must never leak).
            ->whereNotIn('do.object_id', app(\AhgCore\Services\DisclosureGate::class)->restrictedIds())
            ->whereNotIn('do.object_id', app(\AhgCore\Services\DisclosureGate::class)->redactedIds())
            ->where('do.id', $id)
            ->select(
                'do.id', 'do.object_id', 'do.usage_id',
                'do.mime_type', 'do.media_type_id',
                'do.byte_size', 'do.name as filename',
                // 'do.path' + 'do.checksum' deliberately omitted — never leak the
                // raw server filesystem path or checksum to anonymous callers.
                'slug.slug as parent_slug',
                'object.created_at', 'object.updated_at'
            )
            ->first();

        if (! $do) {
            return response()->json(['error' => 'Digital object not found.'], 404);
        }

        $payload = (array) $do;

        if ($this->wantsEmbeddedMetadata($request)) {
            $researcherId = $request->attributes->get('api_user_id');
            $block = $this->embedded->forDigitalObject((int) $do->id, $researcherId);
            // null = ODRL denied; quietly drop the key rather than 403 (the
            // base record is still legitimately visible).
            if ($block !== null) {
                $payload['embedded_metadata'] = $block;
            }
        }

        return response()->json($payload);
    }

    /**
     * The `?include=` flag follows JSON:API conventions - comma-delimited
     * list of opt-in relationship names.
     */
    private function wantsEmbeddedMetadata(Request $request): bool
    {
        $include = (string) $request->query('include', '');
        if ($include === '') {
            return false;
        }
        $parts = array_map('trim', explode(',', $include));

        return in_array('embedded_metadata', $parts, true)
            || in_array('embedded-metadata', $parts, true);
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
        if (! $parentId) {
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
