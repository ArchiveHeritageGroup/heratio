<?php

namespace AhgMediaStreaming\Controllers;

use AhgCore\Models\QubitDigitalObject;
use AhgMediaStreaming\Services\StreamingService;
use AhgMediaStreaming\Services\TranscodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class MediaStreamController extends Controller
{
    public function __construct(
        private StreamingService $streamingService,
        private TranscodingService $transcodingService,
    ) {}

    /**
     * Stream a digital object (auto-transcode if needed).
     *
     * Supports HTTP Range requests for video/audio seeking.
     */
    public function stream(int $id): Response
    {
        return $this->streamingService->streamWithTranscode($id);
    }

    /**
     * Generate and serve a video thumbnail for a digital object.
     *
     * The thumbnail is cached in storage/app/thumbnails/{id}.jpg.
     * Returns 404 if the digital object or source file does not exist.
     * Returns 500 if ffmpeg is not available or thumbnail generation fails.
     */
    public function thumbnail(int $id): Response
    {
        $digitalObject = QubitDigitalObject::find($id);

        if (! $digitalObject) {
            abort(404, 'Digital object not found.');
        }

        $uploadsDir = config('app.uploads_dir', '/mnt/nas/heratio/archive');
        $sourcePath = $digitalObject->getFullPath($uploadsDir);

        if (! file_exists($sourcePath)) {
            abort(404, 'Media file not found.');
        }

        // Check if this is a video file
        $mimeType = $digitalObject->mime_type ?? '';
        if (! str_starts_with($mimeType, 'video/')) {
            abort(400, 'Thumbnails can only be generated for video files.');
        }

        if (! $this->transcodingService->isFfmpegAvailable()) {
            abort(500, 'ffmpeg is not available on this server.');
        }

        // Cache the thumbnail
        $thumbnailDir = storage_path('app/thumbnails');
        $thumbnailPath = $thumbnailDir . '/' . $id . '.jpg';

        if (! file_exists($thumbnailPath) || filesize($thumbnailPath) === 0) {
            $success = $this->transcodingService->generateVideoThumbnail($sourcePath, $thumbnailPath);

            if (! $success) {
                abort(500, 'Failed to generate video thumbnail.');
            }
        }

        return response()->file($thumbnailPath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * Return JSON media info (duration, codec, resolution, etc.).
     */
    public function info(int $id): JsonResponse
    {
        $digitalObject = QubitDigitalObject::find($id);

        if (! $digitalObject) {
            return response()->json(['error' => 'Digital object not found.'], 404);
        }

        $uploadsDir = config('app.uploads_dir', '/mnt/nas/heratio/archive');
        $sourcePath = $digitalObject->getFullPath($uploadsDir);

        if (! file_exists($sourcePath)) {
            return response()->json(['error' => 'Media file not found.'], 404);
        }

        $mediaInfo = $this->transcodingService->getMediaInfo($sourcePath);

        return response()->json([
            'id' => $digitalObject->id,
            'name' => $digitalObject->name,
            'mime_type' => $digitalObject->mime_type,
            'byte_size' => $digitalObject->byte_size,
            'needs_transcoding' => $this->transcodingService->needsTranscoding($sourcePath),
            'ffmpeg_available' => $this->transcodingService->isFfmpegAvailable(),
            'ffprobe_available' => $this->transcodingService->isFfprobeAvailable(),
            'duration' => $mediaInfo['duration'],
            'codec_name' => $mediaInfo['codec_name'],
            'width' => $mediaInfo['width'],
            'height' => $mediaInfo['height'],
            'bit_rate' => $mediaInfo['bit_rate'],
            'sample_rate' => $mediaInfo['sample_rate'],
        ]);
    }
}
