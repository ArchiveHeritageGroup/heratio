<?php

/**
 * MediaStreamController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgMediaStreaming\Controllers;

use AhgCore\Models\DigitalObject;
use AhgCore\Services\AclService;
use AhgCore\Services\MediaProtection;
use AhgMediaStreaming\Services\StreamingService;
use AhgMediaStreaming\Services\TranscodingService;
use AhgResearch\Services\OdrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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
     *
     * This route is public (players use it), so it is the raw-binary path that
     * must enforce ODRL itself - a restricted/embargoed dataset's files have to
     * be inaccessible here, not merely unlinked from the landing page (#1347).
     * Open objects carry no prohibition, so this is a no-op for public media.
     */
    public function stream(int $id): Response
    {
        // Guarded: if the research/ODRL stack isn't installed there are no
        // policies to enforce, so streaming proceeds (fail-open only when the
        // whole policy engine is absent).
        if (class_exists(OdrlService::class)
            && ! app(OdrlService::class)->isDigitalObjectPermitted($id, 'use')) {
            abort(403, 'Access denied by rights policy.');
        }

        // heratio#1771: the same gates as the /uploads file check, not ODRL
        // alone - publication, embargo, ICIP/TK, classification, PII
        // screening, release clearance, and for the master file itself
        // "Protect originals" and the video watermark.
        $do = DB::table('digital_object')->where('id', $id)->first();
        $master = MediaProtection::masterOf($do);
        if ($master && ! MediaProtection::mayPlay($master, ! $do->parent_id)) {
            abort(403, 'Access denied.');
        }

        return $this->streamingService->streamWithTranscode($id);
    }

    /**
     * GET /media-streaming/hls-key/{masterId}/{rendition} - the AES-128 key
     * of one HLS rendition (heratio#1771). Handed out only to a viewer who may
     * play the record (MediaProtection::mayPlay: publication, embargo, ICIP/TK,
     * classification, ODRL, PII screening, release clearance; staff pass), and
     * never cached. Without the key the segments are unreadable.
     * This is encryption in transit with access-controlled keys, not DRM: a
     * viewer who is given the key can keep the decrypted stream.
     */
    public function hlsKey(int $masterId, int $rendition): Response
    {
        $master = DB::table('digital_object')->where('id', $masterId)->whereNull('parent_id')->first();
        if (! $master) {
            abort(404);
        }
        if (! MediaProtection::mayPlay($master)) {
            abort(auth()->check() ? 403 : 401);
        }
        $key = MediaProtection::keyPath($masterId, $rendition);
        if (! is_file($key) || filesize($key) !== 16) {
            abort(404);
        }

        return response((string) file_get_contents($key), 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * POST /media-streaming/clearance/{masterId} - clear audio/video for
     * release, or hold it back (heratio#1771). Administrators and editors.
     */
    public function clearance(Request $request, int $masterId): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! (AclService::isAdministrator($user) || AclService::isEditor($user))) {
            abort(403);
        }
        $data = $request->validate([
            'decision' => 'required|in:clear,withhold',
            'note' => 'nullable|string|max:2000',
        ]);
        $master = DB::table('digital_object')->where('id', $masterId)->whereNull('parent_id')->first();
        if (! $master || ! MediaProtection::isAudioVideo($master)) {
            abort(404);
        }
        MediaProtection::setClearance($masterId, $data['decision'] === 'clear', $data['note'] ?? null);

        return back()->with('success', $data['decision'] === 'clear'
            ? __('Cleared for release.')
            : __('Held back from release.'));
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
        $digitalObject = DigitalObject::find($id);

        if (! $digitalObject) {
            abort(404, 'Digital object not found.');
        }
        // heratio#1771: a frame of the video is the video's content - same gates as playback.
        $master = MediaProtection::masterOf(DB::table('digital_object')->where('id', $id)->first());
        if ($master && ! MediaProtection::mayPlay($master)) {
            abort(403, 'Access denied.');
        }

        $uploadsDir = config('heratio.uploads_path');
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
        $thumbnailPath = $thumbnailDir.'/'.$id.'.jpg';

        if (! file_exists($thumbnailPath) || filesize($thumbnailPath) === 0) {
            $success = $this->transcodingService->generateVideoThumbnail($sourcePath, $thumbnailPath);

            if (! $success) {
                abort(500, 'Failed to generate video thumbnail.');
            }
        }

        return response()->file($thumbnailPath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800', // access-checked: no shared caches
        ]);
    }

    /**
     * Return JSON media info (duration, codec, resolution, etc.).
     */
    public function info(int $id): JsonResponse
    {
        $digitalObject = DigitalObject::find($id);

        if (! $digitalObject) {
            return response()->json(['error' => 'Digital object not found.'], 404);
        }
        $master = MediaProtection::masterOf(DB::table('digital_object')->where('id', $id)->first());
        if ($master && ! MediaProtection::mayPlay($master)) {
            return response()->json(['error' => 'Access denied.'], 403);
        }

        $uploadsDir = config('heratio.uploads_path');
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
