<?php

/**
 * MediaDerivativeService - Heratio core (P7)
 *
 * Generates format-appropriate derivatives for audio / video / 3D masters.
 * Complements DigitalObjectService::generateDerivativesForMaster(), which
 * handles still-image formats.
 *
 *   Audio → MP3 128 kbps preview + waveform PNG (ffmpeg)
 *   Video → MP4 H.264 480p preview + poster frame (ffmpeg)
 *   3D    → delegates to AhgThreeDModel\Services\ThreeDThumbnailService
 *
 * Derivatives are stored as digital_object rows with parent_id = master and
 * usage_id set to match the existing taxonomy (reference = 141, thumbnail
 * = 142). Files land next to the master in {uploads}/<io_id>/.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MediaDerivativeService
{
    /**
     * Dispatch derivative generation based on the master's mime type /
     * extension. Returns the number of derivatives successfully created.
     */
    public static function generateForMaster(int $masterId): int
    {
        $master = DB::table('digital_object')->where('id', $masterId)->first();
        if (!$master || !$master->object_id) { return 0; }

        $uploadDir = config('heratio.uploads_path', DigitalObjectService::UPLOAD_DIR) . '/' . $master->object_id;
        $masterPath = $uploadDir . '/' . $master->name;
        if (!is_file($masterPath)) { return 0; }

        $mime = (string) ($master->mime_type ?? '');
        $ext = strtolower(pathinfo($master->name, PATHINFO_EXTENSION));

        // 3D is delegated to the existing package.
        if (self::is3D($mime, $ext)) {
            try {
                if (class_exists(\AhgThreeDModel\Services\ThreeDThumbnailService::class)) {
                    $svc = app(\AhgThreeDModel\Services\ThreeDThumbnailService::class);
                    return $svc->createDerivatives((int) $masterId) ? 1 : 0;
                }
            } catch (\Throwable $e) {
                Log::info('[MediaDerivativeService] 3D thumbnail failed: ' . $e->getMessage());
            }
            return 0;
        }

        $ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            Log::info('[MediaDerivativeService] ffmpeg not installed; skipping audio/video derivatives for DO ' . $masterId);
            return 0;
        }

        if (self::isAudio($mime, $ext)) {
            return self::generateAudioDerivatives($ffmpeg, $masterId, $master, $masterPath, $uploadDir);
        }
        if (self::isVideo($mime, $ext)) {
            return self::generateVideoDerivatives($ffmpeg, $masterId, $master, $masterPath, $uploadDir);
        }
        if (self::isTiff($mime, $ext)) {
            return self::generatePyramidTiff($masterId, $master, $masterPath, $uploadDir);
        }
        return 0;
    }

    /**
     * Build a pyramidal TIFF for Cantaloupe IIIF delivery. ImageMagick's
     * `convert` ships a `ptif:` target that generates the standard
     * multi-resolution Pyramid TIFF the image server's JAI/TurboJPEG
     * backends can stream tiles from at any zoom level.
     */
    protected static function generatePyramidTiff(int $masterId, object $master, string $masterPath, string $uploadDir): int
    {
        $convert = trim((string) @shell_exec('command -v convert 2>/dev/null'));
        if ($convert === '') {
            Log::info('[MediaDerivativeService] ImageMagick convert not installed; skipping pyramid TIFF for DO ' . $masterId);
            return 0;
        }

        $baseName = pathinfo($master->name, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $baseName);
        if (str_starts_with($safe, 'master_')) { $safe = substr($safe, 7); }

        $out = $uploadDir . '/pyramid_' . $safe . '.ptif';
        $cmd = sprintf(
            '%s %s -define tiff:tile-geometry=256x256 -compress jpeg -quality 85 ptif:%s 2>/dev/null',
            escapeshellcmd($convert), escapeshellarg($masterPath), escapeshellarg($out)
        );
        @shell_exec($cmd);
        if (!is_file($out) || filesize($out) === 0) {
            return 0;
        }

        self::insertDerivative(
            $masterId, $master->object_id,
            'pyramid_' . $safe . '.ptif',
            $master->path,
            'image/tiff',
            DigitalObjectService::MEDIA_IMAGE,
            DigitalObjectService::USAGE_REFERENCE,  // IIIF serves from reference slot
            $out
        );
        return 1;
    }

    // ---------------------------------------------------------------
    // Audio
    // ---------------------------------------------------------------

    protected static function generateAudioDerivatives(string $ffmpeg, int $masterId, object $master, string $masterPath, string $uploadDir): int
    {
        $baseName = pathinfo($master->name, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $baseName);
        if (str_starts_with($safe, 'master_')) { $safe = substr($safe, 7); }

        $created = 0;

        // 1. MP3 128 kbps reference
        $mp3 = $uploadDir . '/reference_' . $safe . '.mp3';
        $cmd = sprintf('%s -y -i %s -codec:a libmp3lame -b:a 128k %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), escapeshellarg($mp3));
        @shell_exec($cmd);
        if (is_file($mp3) && filesize($mp3) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'reference_' . $safe . '.mp3',
                $master->path, 'audio/mpeg', DigitalObjectService::MEDIA_AUDIO, DigitalObjectService::USAGE_REFERENCE, $mp3);
            $created++;
        }

        // 2. Waveform PNG thumbnail
        $png = $uploadDir . '/thumbnail_' . $safe . '.png';
        $cmd = sprintf('%s -y -i %s -filter_complex "showwavespic=s=640x120:colors=#555555" -frames:v 1 %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), escapeshellarg($png));
        @shell_exec($cmd);
        if (is_file($png) && filesize($png) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'thumbnail_' . $safe . '.png',
                $master->path, 'image/png', DigitalObjectService::MEDIA_IMAGE, DigitalObjectService::USAGE_THUMBNAIL, $png);
            $created++;
        }

        return $created;
    }

    // ---------------------------------------------------------------
    // Video
    // ---------------------------------------------------------------

    protected static function generateVideoDerivatives(string $ffmpeg, int $masterId, object $master, string $masterPath, string $uploadDir): int
    {
        $baseName = pathinfo($master->name, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $baseName);
        if (str_starts_with($safe, 'master_')) { $safe = substr($safe, 7); }

        $created = 0;

        // 1. MP4 H.264 480p reference (scale, keep aspect ratio)
        $mp4 = $uploadDir . '/reference_' . $safe . '.mp4';
        $cmd = sprintf('%s -y -i %s -vf "scale=-2:480" -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k -movflags +faststart %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), escapeshellarg($mp4));
        @shell_exec($cmd);
        if (is_file($mp4) && filesize($mp4) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'reference_' . $safe . '.mp4',
                $master->path, 'video/mp4', DigitalObjectService::MEDIA_VIDEO, DigitalObjectService::USAGE_REFERENCE, $mp4);
            $created++;
        }

        // 2. Poster frame (JPG thumbnail taken 1s in)
        $jpg = $uploadDir . '/thumbnail_' . $safe . '.jpg';
        $cmd = sprintf('%s -y -ss 00:00:01 -i %s -frames:v 1 -q:v 3 %s 2>/dev/null',
            escapeshellcmd($ffmpeg), escapeshellarg($masterPath), escapeshellarg($jpg));
        @shell_exec($cmd);
        if (is_file($jpg) && filesize($jpg) > 0) {
            self::insertDerivative($masterId, $master->object_id, 'thumbnail_' . $safe . '.jpg',
                $master->path, 'image/jpeg', DigitalObjectService::MEDIA_IMAGE, DigitalObjectService::USAGE_THUMBNAIL, $jpg);
            $created++;
        }

        return $created;
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    protected static function isAudio(string $mime, string $ext): bool
    {
        if (str_starts_with($mime, 'audio/')) { return true; }
        return in_array($ext, ['wav', 'flac', 'aac', 'ogg', 'mp3', 'm4a', 'aif', 'aiff', 'bwf'], true);
    }

    protected static function isVideo(string $mime, string $ext): bool
    {
        if (str_starts_with($mime, 'video/')) { return true; }
        return in_array($ext, ['mov', 'mp4', 'mkv', 'webm', 'avi', 'mxf', 'dv', 'mts', 'm2ts'], true);
    }

    protected static function is3D(string $mime, string $ext): bool
    {
        if (str_starts_with($mime, 'model/')) { return true; }
        return in_array($ext, ['glb', 'gltf', 'obj', 'ply', 'stl', 'usdz', 'fbx'], true);
    }

    protected static function isTiff(string $mime, string $ext): bool
    {
        if ($mime === 'image/tiff' || $mime === 'image/tif') { return true; }
        return in_array($ext, ['tif', 'tiff'], true);
    }

    protected static function insertDerivative(int $parentId, int $ioId, string $filename, ?string $webPath, string $mime, int $mediaTypeId, int $usageId, string $diskPath): void
    {
        $byteSize = filesize($diskPath) ?: null;
        $checksum = hash_file('sha256', $diskPath);
        $now = now()->format('Y-m-d H:i:s');

        $doId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        DB::table('digital_object')->insert([
            'id' => $doId,
            'object_id' => $ioId,
            'usage_id' => $usageId,
            'mime_type' => $mime,
            'media_type_id' => $mediaTypeId,
            'name' => $filename,
            'path' => $webPath ?: ('/uploads/r/' . $ioId . '/'),
            'byte_size' => $byteSize,
            'checksum' => $checksum,
            'checksum_type' => 'sha256',
            'parent_id' => $parentId,
        ]);
    }
}
