<?php

/**
 * PhotoProcessor - photo upload pipeline honouring the photo_* settings
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

namespace AhgMediaProcessing\Services;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\Log;

class PhotoProcessor
{
    public const DEFAULT_MAX_UPLOAD_SIZE = 5242880;          // 5MB
    public const DEFAULT_ALLOWED_TYPES = 'jpg,jpeg,png,gif,tiff';
    public const DEFAULT_JPEG_QUALITY = 72;
    public const DEFAULT_PNG_COMPRESSION = 8;
    public const DEFAULT_THUMBNAIL_SMALL = 150;
    public const DEFAULT_THUMBNAIL_MEDIUM = 400;
    public const DEFAULT_THUMBNAIL_LARGE = 800;

    /**
     * Process an uploaded photo end-to-end:
     *   - Validate against photo_max_upload_size + photo_allowed_types.
     *   - Move into photo_upload_path with a fresh unique filename.
     *   - Apply ImageMagick auto-orient + EXIF strip per photo_auto_rotate / photo_auto_orient / photo_exif_strip.
     *   - Re-encode JPEG at photo_jpeg_quality, PNG at photo_png_compression.
     *   - Generate small / medium / large thumbnails when photo_create_thumbnails is on.
     *   - Apply watermark when photo_watermark_enabled is on (uses WatermarkService).
     *   - Extract EXIF (photo_date / camera_info / photographer / dims) when photo_extract_exif is on.
     *
     * @param  string  $tmpPath        Source path on disk (typically $_FILES['file']['tmp_name'])
     * @param  string  $originalName   Original filename, used for extension + provenance
     * @return array{
     *     filename: string,
     *     path: string,
     *     mime_type: string,
     *     size: int,
     *     width: ?int,
     *     height: ?int,
     *     exif: array,
     *     thumbnails: array,
     * }
     *
     * @throws PhotoProcessorException on validation failure or ImageMagick error.
     */
    public function process(string $tmpPath, string $originalName): array
    {
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new PhotoProcessorException("Source file not readable: $tmpPath");
        }

        $size = filesize($tmpPath);
        $maxSize = (int) AhgSettingsService::getInt('photo_max_upload_size', self::DEFAULT_MAX_UPLOAD_SIZE);
        if ($maxSize > 0 && $size > $maxSize) {
            throw new PhotoProcessorException(sprintf(
                'File too large: %d bytes (max %d)',
                $size,
                $maxSize,
            ));
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = $this->allowedExtensions();
        if (!in_array($ext, $allowed, true)) {
            throw new PhotoProcessorException(sprintf(
                'Disallowed file type: .%s (allowed: %s)',
                $ext,
                implode(', ', $allowed),
            ));
        }

        $uploadDir = $this->resolveUploadPath();
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
            throw new PhotoProcessorException("Cannot create upload directory: $uploadDir");
        }

        $filename = uniqid('photo_', true) . '.' . $ext;
        $destPath = rtrim($uploadDir, '/') . '/' . $filename;

        // Read EXIF before any pixel-level mutation - the auto-orient pass below
        // strips the orientation tag, and a future photo_exif_strip pass strips
        // the rest, so this is the only chance to capture them.
        $exif = AhgSettingsService::getBool('photo_extract_exif', true)
            ? $this->extractExif($tmpPath)
            : [];

        // Copy then transform; never write transformed bytes back over $tmpPath
        // since that lives under tempdir managed by PHP and the move semantics
        // differ across SAPIs.
        if (!@copy($tmpPath, $destPath)) {
            throw new PhotoProcessorException("Failed to copy upload to $destPath");
        }

        $this->applyImageMagick($destPath, $ext);

        if (AhgSettingsService::getBool('photo_watermark_enabled', false)) {
            $this->applyTextWatermark($destPath);
        }

        $thumbnails = AhgSettingsService::getBool('photo_create_thumbnails', true)
            ? $this->generateThumbnails($destPath, $uploadDir, $filename, $ext)
            : [];

        [$width, $height, $mime] = $this->readDimensions($destPath);

        return [
            'filename' => $filename,
            'path' => $destPath,
            'mime_type' => $mime,
            'size' => filesize($destPath) ?: $size,
            'width' => $width,
            'height' => $height,
            'exif' => $exif,
            'thumbnails' => $thumbnails,
        ];
    }

    protected function allowedExtensions(): array
    {
        $raw = (string) AhgSettingsService::get('photo_allowed_types', self::DEFAULT_ALLOWED_TYPES);
        return array_values(array_filter(array_map(
            fn ($e) => strtolower(trim($e, '. ')),
            explode(',', $raw),
        )));
    }

    /**
     * Resolve the upload directory.
     *
     * Priority: explicit photo_upload_path > heratio.uploads_path/condition_photos
     * > storage_path('app/public/condition_photos').
     *
     * Absolute paths from photo_upload_path are honoured as-is so operators can
     * point at NAS / shared storage; relative values resolve against
     * heratio.storage_path.
     */
    protected function resolveUploadPath(): string
    {
        $configured = (string) AhgSettingsService::get('photo_upload_path', '');
        if ($configured !== '') {
            return $configured[0] === '/'
                ? $configured
                : rtrim(config('heratio.storage_path', storage_path('app')), '/') . '/' . ltrim($configured, '/');
        }

        $uploads = config('heratio.uploads_path');
        if ($uploads) {
            return rtrim($uploads, '/') . '/condition_photos';
        }

        return storage_path('app/public/condition_photos');
    }

    /**
     * Run ImageMagick `convert` on the destination path applying:
     *   - auto-orient (rotate per EXIF orientation tag) when photo_auto_rotate or photo_auto_orient
     *   - EXIF strip when photo_exif_strip OR photo_auto_orient (auto-orient consumes the tag)
     *   - JPEG quality re-encode when source is jpeg
     *   - PNG compression level when source is png
     *
     * Idempotent in the sense that if no flags are on for the given format,
     * no convert command runs - the original file stays untouched.
     */
    protected function applyImageMagick(string $path, string $ext): void
    {
        $args = [];

        $autoRotate = AhgSettingsService::getBool('photo_auto_rotate', true)
            || AhgSettingsService::getBool('photo_auto_orient', false);
        if ($autoRotate) {
            $args[] = '-auto-orient';
        }

        if (AhgSettingsService::getBool('photo_exif_strip', false)
            || AhgSettingsService::getBool('photo_auto_orient', false)
        ) {
            $args[] = '-strip';
        }

        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            $quality = AhgSettingsService::getInt('photo_jpeg_quality', self::DEFAULT_JPEG_QUALITY);
            if ($quality > 0 && $quality <= 100) {
                $args[] = '-quality';
                $args[] = (string) $quality;
            }
        } elseif ($ext === 'png') {
            $level = AhgSettingsService::getInt('photo_png_compression', self::DEFAULT_PNG_COMPRESSION);
            if ($level >= 0 && $level <= 9) {
                $args[] = '-define';
                $args[] = "png:compression-level=$level";
            }
        }

        if (empty($args)) {
            return;
        }

        $cmd = sprintf(
            'convert %s %s %s 2>&1',
            escapeshellarg($path),
            implode(' ', array_map('escapeshellarg', $args)),
            escapeshellarg($path),
        );

        $output = [];
        $exitCode = -1;
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new PhotoProcessorException("ImageMagick failed (exit $exitCode): " . implode("\n", $output));
        }
    }

    /**
     * Generate small / medium / large square thumbnails next to the master.
     *
     * Layout: {uploadDir}/thumbs/{small|medium|large}/{filename}
     * Each thumbnail is square center-cropped, matching DerivativeService
     * conventions. Width sourced from the corresponding setting key.
     */
    protected function generateThumbnails(string $masterPath, string $uploadDir, string $filename, string $ext): array
    {
        $sizes = [
            'small' => AhgSettingsService::getInt('photo_thumbnail_small', self::DEFAULT_THUMBNAIL_SMALL),
            'medium' => AhgSettingsService::getInt('photo_thumbnail_medium', self::DEFAULT_THUMBNAIL_MEDIUM),
            'large' => AhgSettingsService::getInt('photo_thumbnail_large', self::DEFAULT_THUMBNAIL_LARGE),
        ];

        $thumbs = [];
        foreach ($sizes as $key => $size) {
            if ($size <= 0) {
                continue;
            }
            $thumbDir = rtrim($uploadDir, '/') . '/thumbs/' . $key;
            if (!is_dir($thumbDir) && !@mkdir($thumbDir, 0755, true)) {
                Log::warning('[photo] thumbnail dir create failed', ['dir' => $thumbDir]);
                continue;
            }
            $thumbPath = $thumbDir . '/' . $filename;
            $cmd = sprintf(
                'convert %s -thumbnail %dx%d -gravity center -extent %dx%d %s 2>&1',
                escapeshellarg($masterPath),
                $size, $size, $size, $size,
                escapeshellarg($thumbPath),
            );
            $output = [];
            $exitCode = -1;
            exec($cmd, $output, $exitCode);
            if ($exitCode === 0) {
                $thumbs[$key] = ['path' => $thumbPath, 'size' => $size];
            } else {
                Log::warning('[photo] thumbnail generation failed', [
                    'thumb' => $thumbPath,
                    'size' => $size,
                    'output' => implode("\n", $output),
                ]);
            }
        }

        return $thumbs;
    }

    /**
     * Apply a text watermark using ImageMagick.
     *
     * Reads photo_watermark_text and overlays it bottom-right with a default
     * font + 30% opacity. WatermarkService is image-only (composite); for the
     * text path we stay in convert here so operators don't have to upload a
     * watermark PNG just to get a basic text overlay.
     */
    protected function applyTextWatermark(string $path): void
    {
        $text = trim((string) AhgSettingsService::get('photo_watermark_text', ''));
        if ($text === '') {
            return;
        }

        $cmd = sprintf(
            'convert %s -gravity SouthEast -fill "rgba(255,255,255,0.7)" -stroke "rgba(0,0,0,0.5)" -strokewidth 1 -pointsize 18 -annotate +12+12 %s %s 2>&1',
            escapeshellarg($path),
            escapeshellarg($text),
            escapeshellarg($path),
        );
        $output = [];
        $exitCode = -1;
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            Log::warning('[photo] watermark apply failed', [
                'path' => $path,
                'output' => implode("\n", $output),
            ]);
        }
    }

    /**
     * Extract a curated EXIF subset suitable for the spectrum_condition_photo
     * columns (camera_info / photo_date / photographer) plus dimensions.
     *
     * Uses native exif_read_data (PHP ext-exif) - no shell hop. Returns an
     * empty array if EXIF can't be read for any reason; callers should handle
     * the absence gracefully.
     */
    protected function extractExif(string $path): array
    {
        if (!function_exists('exif_read_data')) {
            return [];
        }

        $raw = @exif_read_data($path);
        if ($raw === false || !is_array($raw)) {
            return [];
        }

        $cameraParts = array_filter([
            $raw['Make'] ?? null,
            $raw['Model'] ?? null,
        ]);
        $photoDate = null;
        if (!empty($raw['DateTimeOriginal'])) {
            $ts = strtotime($raw['DateTimeOriginal']);
            if ($ts !== false) {
                $photoDate = date('Y-m-d', $ts);
            }
        }

        return [
            'camera_info' => $cameraParts ? implode(' ', $cameraParts) : null,
            'photo_date' => $photoDate,
            'photographer' => $raw['Artist'] ?? $raw['Author'] ?? null,
            'orientation' => $raw['Orientation'] ?? null,
            'iso' => $raw['ISOSpeedRatings'] ?? null,
            'exposure_time' => $raw['ExposureTime'] ?? null,
            'focal_length' => $raw['FocalLength'] ?? null,
        ];
    }

    /**
     * Read final dimensions + mime from the processed file. Falls back to
     * sensible defaults if getimagesize fails (e.g. for TIFF on stripped
     * builds).
     */
    protected function readDimensions(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return [null, null, mime_content_type($path) ?: 'application/octet-stream'];
        }
        return [(int) $info[0], (int) $info[1], $info['mime'] ?? 'application/octet-stream'];
    }
}

class PhotoProcessorException extends \Exception
{
}
