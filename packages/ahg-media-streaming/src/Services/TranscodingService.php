<?php

/**
 * TranscodingService - Service for Heratio
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


use AhgCore\Models\QubitDigitalObject;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TranscodingService
{
    /**
     * Video formats that require transcoding to MP4 for browser playback.
     */
    private array $videoTranscodeFormats = [
        'asf', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'ts', 'wtv', 'hevc', '3gp', 'vob', 'mxf',
    ];

    /**
     * Audio formats that require transcoding to MP3 for browser playback.
     */
    private array $audioTranscodeFormats = [
        'aiff', 'au', 'ac3', '8svx', 'wma', 'ra', 'flac',
    ];

    /**
     * MIME types considered video.
     */
    private array $videoMimeTypes = [
        'video/x-ms-asf', 'video/x-msvideo', 'video/quicktime', 'video/x-ms-wmv',
        'video/x-flv', 'video/x-matroska', 'video/mp2t', 'video/hevc',
        'video/3gpp', 'video/mpeg', 'video/x-mxf',
    ];

    /**
     * MIME types considered audio.
     */
    private array $audioMimeTypes = [
        'audio/x-aiff', 'audio/basic', 'audio/ac3', 'audio/x-8svx',
        'audio/x-ms-wma', 'audio/x-realaudio', 'audio/flac',
    ];

    /**
     * Check if a file format needs transcoding to a browser-compatible format.
     */
    public function needsTranscoding(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->videoTranscodeFormats, true)
            || in_array($extension, $this->audioTranscodeFormats, true);
    }

    /**
     * Determine if the file is a video format that needs transcoding.
     */
    public function isVideoTranscodeFormat(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->videoTranscodeFormats, true);
    }

    /**
     * Determine if the file is an audio format that needs transcoding.
     */
    public function isAudioTranscodeFormat(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->audioTranscodeFormats, true);
    }

    /**
     * Transcode a video file to MP4 (H.264 + AAC) for browser playback.
     *
     * Uses -movflags +faststart so the moov atom is at the beginning of the file,
     * enabling progressive download / streaming without waiting for the full file.
     */
    public function transcodeToMp4(string $inputPath, string $outputPath): bool
    {
        if (! $this->isFfmpegAvailable()) {
            Log::error('TranscodingService: ffmpeg is not available on this system.');

            return false;
        }

        if (! file_exists($inputPath)) {
            Log::error('TranscodingService: Input file does not exist.', ['path' => $inputPath]);

            return false;
        }

        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $command = sprintf(
            'ffmpeg -y -i %s -c:v libx264 -c:a aac -movflags +faststart %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('TranscodingService: ffmpeg video transcode failed.', [
                'input' => $inputPath,
                'output' => $outputPath,
                'return_code' => $returnCode,
                'ffmpeg_output' => implode("\n", array_slice($output, -20)),
            ]);

            // Clean up partial output
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }

            return false;
        }

        return file_exists($outputPath) && filesize($outputPath) > 0;
    }

    /**
     * Transcode an audio file to MP3 for browser playback.
     *
     * Uses variable bitrate quality scale 2 (~190 kbps) for good quality.
     */
    public function transcodeToMp3(string $inputPath, string $outputPath): bool
    {
        if (! $this->isFfmpegAvailable()) {
            Log::error('TranscodingService: ffmpeg is not available on this system.');

            return false;
        }

        if (! file_exists($inputPath)) {
            Log::error('TranscodingService: Input file does not exist.', ['path' => $inputPath]);

            return false;
        }

        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $command = sprintf(
            'ffmpeg -y -i %s -codec:a libmp3lame -qscale:a 2 %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('TranscodingService: ffmpeg audio transcode failed.', [
                'input' => $inputPath,
                'output' => $outputPath,
                'return_code' => $returnCode,
                'ffmpeg_output' => implode("\n", array_slice($output, -20)),
            ]);

            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }

            return false;
        }

        return file_exists($outputPath) && filesize($outputPath) > 0;
    }

    /**
     * Get media information using ffprobe.
     *
     * Returns an associative array with keys: duration, codec_name, width, height, bit_rate, sample_rate.
     */
    public function getMediaInfo(string $filePath): array
    {
        $info = [
            'duration' => null,
            'codec_name' => null,
            'width' => null,
            'height' => null,
            'bit_rate' => null,
            'sample_rate' => null,
        ];

        if (! $this->isFfprobeAvailable()) {
            Log::warning('TranscodingService: ffprobe is not available on this system.');

            return $info;
        }

        if (! file_exists($filePath)) {
            return $info;
        }

        // Get format-level info (duration, bit_rate)
        $formatCommand = sprintf(
            'ffprobe -v quiet -print_format json -show_format %s 2>/dev/null',
            escapeshellarg($filePath)
        );

        exec($formatCommand, $formatOutput, $formatReturnCode);

        if ($formatReturnCode === 0) {
            $formatData = json_decode(implode('', $formatOutput), true);
            if (isset($formatData['format'])) {
                $info['duration'] = isset($formatData['format']['duration'])
                    ? (float) $formatData['format']['duration']
                    : null;
                $info['bit_rate'] = isset($formatData['format']['bit_rate'])
                    ? (int) $formatData['format']['bit_rate']
                    : null;
            }
        }

        // Get stream-level info (codec, dimensions, sample_rate)
        $streamCommand = sprintf(
            'ffprobe -v quiet -print_format json -show_streams %s 2>/dev/null',
            escapeshellarg($filePath)
        );

        exec($streamCommand, $streamOutput, $streamReturnCode);

        if ($streamReturnCode === 0) {
            $streamData = json_decode(implode('', $streamOutput), true);
            if (isset($streamData['streams']) && is_array($streamData['streams'])) {
                foreach ($streamData['streams'] as $stream) {
                    if (($stream['codec_type'] ?? '') === 'video') {
                        $info['codec_name'] = $stream['codec_name'] ?? null;
                        $info['width'] = isset($stream['width']) ? (int) $stream['width'] : null;
                        $info['height'] = isset($stream['height']) ? (int) $stream['height'] : null;

                        break;
                    }
                }

                // If no video stream found, use the first audio stream
                if ($info['codec_name'] === null) {
                    foreach ($streamData['streams'] as $stream) {
                        if (($stream['codec_type'] ?? '') === 'audio') {
                            $info['codec_name'] = $stream['codec_name'] ?? null;
                            $info['sample_rate'] = isset($stream['sample_rate'])
                                ? (int) $stream['sample_rate']
                                : null;

                            break;
                        }
                    }
                } else {
                    // Also grab sample_rate from audio stream if present
                    foreach ($streamData['streams'] as $stream) {
                        if (($stream['codec_type'] ?? '') === 'audio') {
                            $info['sample_rate'] = isset($stream['sample_rate'])
                                ? (int) $stream['sample_rate']
                                : null;

                            break;
                        }
                    }
                }
            }
        }

        return $info;
    }

    /**
     * Get the duration of a media file in seconds.
     */
    public function getDuration(string $filePath): ?float
    {
        if (! $this->isFfprobeAvailable() || ! file_exists($filePath)) {
            return null;
        }

        $command = sprintf(
            'ffprobe -v quiet -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($filePath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            return null;
        }

        $duration = (float) trim($output[0]);

        return $duration > 0 ? $duration : null;
    }

    /**
     * Generate a thumbnail image from a video file at the given timestamp.
     *
     * Produces a JPEG scaled to 480px width (maintaining aspect ratio).
     */
    public function generateVideoThumbnail(string $videoPath, string $outputPath, float $timestamp = 1.0): bool
    {
        if (! $this->isFfmpegAvailable()) {
            Log::error('TranscodingService: ffmpeg is not available on this system.');

            return false;
        }

        if (! file_exists($videoPath)) {
            Log::error('TranscodingService: Video file does not exist.', ['path' => $videoPath]);

            return false;
        }

        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $command = sprintf(
            'ffmpeg -y -i %s -ss %s -vframes 1 -vf scale=480:-1 %s 2>&1',
            escapeshellarg($videoPath),
            escapeshellarg((string) $timestamp),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('TranscodingService: Thumbnail generation failed.', [
                'video' => $videoPath,
                'output' => $outputPath,
                'return_code' => $returnCode,
                'ffmpeg_output' => implode("\n", array_slice($output, -10)),
            ]);

            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }

            return false;
        }

        return file_exists($outputPath) && filesize($outputPath) > 0;
    }

    /**
     * Check if ffmpeg is available on the system.
     */
    public function isFfmpegAvailable(): bool
    {
        exec('which ffmpeg 2>/dev/null', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Check if ffprobe is available on the system.
     */
    public function isFfprobeAvailable(): bool
    {
        exec('which ffprobe 2>/dev/null', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Get or create a transcoded version of a digital object, cached in storage.
     *
     * Returns the path to the transcoded file, or null if transcoding is not needed
     * or the source file does not exist.
     */
    public function getTranscodedPath(int $digitalObjectId): ?string
    {
        $digitalObject = QubitDigitalObject::find($digitalObjectId);

        if (! $digitalObject) {
            Log::warning('TranscodingService: Digital object not found.', ['id' => $digitalObjectId]);

            return null;
        }

        $uploadsDir = config('app.uploads_dir', '/mnt/nas/heratio/archive');
        $sourcePath = $digitalObject->getFullPath($uploadsDir);

        if (! file_exists($sourcePath)) {
            Log::warning('TranscodingService: Source file does not exist.', [
                'id' => $digitalObjectId,
                'path' => $sourcePath,
            ]);

            return null;
        }

        if (! $this->needsTranscoding($sourcePath)) {
            return null;
        }

        // Determine output format and cached path
        $cacheDir = storage_path('app/transcoded');
        if ($this->isVideoTranscodeFormat($sourcePath)) {
            $cachedPath = $cacheDir . '/' . $digitalObjectId . '.mp4';
        } else {
            $cachedPath = $cacheDir . '/' . $digitalObjectId . '.mp3';
        }

        // Return cached version if it already exists and is non-empty
        if (file_exists($cachedPath) && filesize($cachedPath) > 0) {
            return $cachedPath;
        }

        // Transcode
        if ($this->isVideoTranscodeFormat($sourcePath)) {
            $success = $this->transcodeToMp4($sourcePath, $cachedPath);
        } else {
            $success = $this->transcodeToMp3($sourcePath, $cachedPath);
        }

        return $success ? $cachedPath : null;
    }
}
