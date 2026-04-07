<?php

/**
 * StreamingService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgMediaStreaming\Services;

use AhgCore\Models\DigitalObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamingService
{
    public function __construct(
        private TranscodingService $transcodingService,
    ) {}

    /**
     * Stream a file with HTTP Range request support for seeking.
     *
     * Handles the Range, Content-Range, Accept-Ranges, and Content-Length headers
     * to allow clients (video/audio players) to seek within the media file.
     */
    public function stream(string $filePath, ?string $mimeType = null): Response
    {
        if (! file_exists($filePath)) {
            abort(404, 'File not found.');
        }

        if ($mimeType === null) {
            $mimeType = $this->detectMimeType($filePath);
        }

        $fileSize = filesize($filePath);
        $request = request();

        // Handle Range request for seeking
        if ($request->header('Range')) {
            return $this->streamRange($filePath, $fileSize, $mimeType, $request);
        }

        // Full file response (no Range header)
        return new StreamedResponse(function () use ($filePath) {
            $fp = fopen($filePath, 'rb');
            if ($fp === false) {
                return;
            }

            while (! feof($fp)) {
                echo fread($fp, 8192);
                flush();
            }

            fclose($fp);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Stream a file with a Range request (HTTP 206 Partial Content).
     */
    private function streamRange(string $filePath, int $fileSize, string $mimeType, Request $request): StreamedResponse
    {
        $start = 0;
        $end = $fileSize - 1;

        $rangeHeader = $request->header('Range');
        if (preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            $start = (int) $matches[1];
            if (! empty($matches[2])) {
                $end = (int) $matches[2];
            }
        }

        // Validate range
        if ($start > $end || $start >= $fileSize) {
            return new StreamedResponse(function () {
                // Empty body for 416 response
            }, 416, [
                'Content-Range' => "bytes */{$fileSize}",
            ]);
        }

        $length = $end - $start + 1;

        return new StreamedResponse(function () use ($filePath, $start, $length) {
            $fp = fopen($filePath, 'rb');
            if ($fp === false) {
                return;
            }

            fseek($fp, $start);
            $remaining = $length;

            while ($remaining > 0 && ! feof($fp)) {
                $chunk = min(8192, $remaining);
                $data = fread($fp, $chunk);
                if ($data === false) {
                    break;
                }

                echo $data;
                flush();
                $remaining -= strlen($data);
            }

            fclose($fp);
        }, 206, [
            'Content-Type' => $mimeType,
            'Content-Range' => "bytes {$start}-{$end}/{$fileSize}",
            'Content-Length' => $length,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Stream a digital object, transcoding first if necessary.
     *
     * If the file needs transcoding (e.g. AVI, WMV, FLAC), the transcoded version
     * is cached and then streamed. If already transcoded (cached), the cached version
     * is streamed directly. If no transcoding is needed, the original file is streamed.
     */
    public function streamWithTranscode(int $digitalObjectId): Response
    {
        $digitalObject = DigitalObject::find($digitalObjectId);

        if (! $digitalObject) {
            abort(404, 'Digital object not found.');
        }

        $uploadsDir = config('heratio.uploads_path');
        $sourcePath = $digitalObject->getFullPath($uploadsDir);

        if (! file_exists($sourcePath)) {
            abort(404, 'Media file not found.');
        }

        // Check if transcoding is needed
        if ($this->transcodingService->needsTranscoding($sourcePath)) {
            $transcodedPath = $this->transcodingService->getTranscodedPath($digitalObjectId);

            if ($transcodedPath === null) {
                Log::error('StreamingService: Transcoding failed for digital object.', [
                    'id' => $digitalObjectId,
                    'source' => $sourcePath,
                ]);

                abort(500, 'Media transcoding failed. Ensure ffmpeg is installed.');
            }

            // Determine transcoded mime type
            $mimeType = str_ends_with($transcodedPath, '.mp3') ? 'audio/mpeg' : 'video/mp4';

            return $this->stream($transcodedPath, $mimeType);
        }

        // No transcoding needed — stream the original file
        return $this->stream($sourcePath, $digitalObject->mime_type);
    }

    /**
     * Detect the MIME type of a file using PHP's built-in finfo.
     */
    private function detectMimeType(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }
}
