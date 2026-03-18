<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadController extends BaseApiController
{
    protected array $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/tiff', 'image/webp',
        'application/pdf',
        'audio/mpeg', 'audio/wav', 'audio/ogg',
        'video/mp4', 'video/webm', 'video/quicktime',
        'model/gltf-binary', 'model/gltf+json',
        'application/xml', 'text/xml', 'text/csv',
        'application/zip', 'application/x-tar',
    ];

    /**
     * POST /api/v2/upload — Generic file upload.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required',
            'files.*' => 'file|max:512000', // 500MB max
        ]);

        $uploaded = [];
        $errors = [];

        $files = $request->file('files', []);
        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                $errors[] = ['error' => 'Invalid file upload.'];
                continue;
            }

            $mime = $file->getMimeType();
            if (!in_array($mime, $this->allowedMimeTypes)) {
                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => "MIME type '{$mime}' not allowed.",
                ];
                continue;
            }

            $type = $this->classifyMime($mime);
            $path = sprintf('uploads/%s/%s/%s', $type, now()->format('Y'), now()->format('m'));
            $filename = $file->hashName();

            $file->storeAs($path, $filename, 'public');

            $uploaded[] = [
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'path' => "{$path}/{$filename}",
            ];
        }

        return $this->success([
            'uploaded' => count($uploaded),
            'files' => $uploaded,
            'errors' => $errors,
        ], count($uploaded) > 0 ? 201 : 400);
    }

    /**
     * POST /api/v2/descriptions/{slug}/upload — Upload digital object for a description.
     */
    public function uploadForDescription(string $slug, Request $request): JsonResponse
    {
        $objectId = $this->slugToId($slug);
        if (!$objectId) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();

        $type = $this->classifyMime($mime);
        $path = sprintf('uploads/%s/%s/%s', $type, now()->format('Y'), now()->format('m'));
        $filename = $file->hashName();

        $file->storeAs($path, $filename, 'public');

        // Create digital_object row linked to the description
        $doObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Determine media_type_id based on mime
        $mediaTypeId = $this->resolveMediaTypeId($mime);

        DB::table('digital_object')->insert([
            'id' => $doObjectId,
            'object_id' => $objectId,
            'usage_id' => 166, // Master
            'mime_type' => $mime,
            'media_type_id' => $mediaTypeId,
            'name' => $file->getClientOriginalName(),
            'path' => "{$path}/{$filename}",
            'byte_size' => $file->getSize(),
            'sequence' => 0,
        ]);

        DB::table('object')->where('id', $objectId)->update(['updated_at' => now()]);

        return $this->success([
            'digital_object_id' => $doObjectId,
            'description_slug' => $slug,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'path' => "{$path}/{$filename}",
        ], 201);
    }

    protected function classifyMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'images',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'model/') => 'models',
            $mime === 'application/pdf' => 'documents',
            default => 'other',
        };
    }

    protected function resolveMediaTypeId(string $mime): ?int
    {
        // AtoM media type taxonomy IDs
        return match (true) {
            str_starts_with($mime, 'image/') => 137,  // Image
            str_starts_with($mime, 'audio/') => 138,  // Audio
            str_starts_with($mime, 'video/') => 139,  // Video
            $mime === 'application/pdf' => 140,        // Text
            default => null,
        };
    }
}
