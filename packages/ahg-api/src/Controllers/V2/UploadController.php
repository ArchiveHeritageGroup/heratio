<?php

namespace AhgApi\Controllers\V2;

use AhgCore\Services\DigitalObjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UploadController extends BaseApiController
{
    // heratio#1750: the same rule as the web upload - every format is accepted
    // except the deny-list of browser- or server-executable types (svg, html,
    // xml, php, js, ...). The old 18-type allow-list refused design formats
    // (psd, ai, indd), office documents and most audio and video.

    /**
     * POST /api/v2/upload - Generic file upload.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required',
            'files.*' => 'file|max:'.intdiv(DigitalObjectService::getMaxUploadSize(), 1024),
        ]);

        $uploaded = [];
        $errors = [];

        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                $errors[] = ['error' => 'Invalid file upload.'];

                continue;
            }

            $mime = $file->getMimeType();
            $denied = DigitalObjectService::deniedUploadExtension($file);
            if ($denied !== null) {
                $errors[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => DigitalObjectService::deniedUploadMessage($denied),
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
     * POST /api/v2/descriptions/{slug}/upload - Upload digital object for a description.
     */
    public function uploadForDescription(string $slug, Request $request): JsonResponse
    {
        $objectId = $this->slugToId($slug);
        if (! $objectId) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        $request->validate([
            'file' => 'required|file|max:'.intdiv(DigitalObjectService::getMaxUploadSize(), 1024),
        ]);

        // Idempotency (#1435): if the description already has a master digital
        // object, do not attach a second one. An Archivematica re-upload of the
        // same DIP hits this endpoint again; without the guard it would create a
        // duplicate Master. Return the existing one with 200.
        $existing = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->where('usage_id', DigitalObjectService::USAGE_MASTER)
            ->first();
        if ($existing) {
            return $this->success([
                'digital_object_id' => (int) $existing->id,
                'description_slug' => $slug,
                'filename' => $existing->name,
                'mime_type' => $existing->mime_type,
                'size' => $existing->byte_size,
                'path' => $existing->path,
                'skipped' => true,
                'reason' => 'Description already has a master digital object.',
            ], 200);
        }

        $file = $request->file('file');

        // Canonical upload path: stores under the {uploads}/r/{object_id}/ shard,
        // writes the master with usage_id = 140 (Master), and generates the
        // reference (141) + thumbnail (142) derivatives. This replaces the old
        // inline insert that wrote a date-path master with usage_id 166 and no
        // derivatives (which is why images did not render and an out-of-band
        // regenerate step was needed).
        $doId = DigitalObjectService::upload($objectId, $file);
        $do = DB::table('digital_object')->where('id', $doId)->first();

        DB::table('object')->where('id', $objectId)->update(['updated_at' => now()]);

        return $this->success([
            'digital_object_id' => $doId,
            'description_slug' => $slug,
            'filename' => $do->name ?? $file->getClientOriginalName(),
            'mime_type' => $do->mime_type ?? $file->getMimeType(),
            'size' => $do->byte_size ?? $file->getSize(),
            'path' => $do->path ?? null,
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
        // The core mapping: this used 137/138/139/140, which are Text/Video/Other
        // and nothing, so images were stored as Text and audio as Video.
        return \AhgCore\Services\DigitalObjectService::resolveMediaTypeId($mime);
    }
}
