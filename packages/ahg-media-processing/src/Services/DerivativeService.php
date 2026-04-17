<?php

/**
 * DerivativeService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DerivativeService - Generates image derivatives (thumbnails and reference images)
 * from master digital objects. Ported from the AtoM DigitalObjectService pattern.
 *
 * Uses ImageMagick (convert) for all derivative generation.
 */
class DerivativeService
{
    /** Usage IDs matching AtoM term taxonomy */
    const USAGE_MASTER = 140;
    const USAGE_REFERENCE = 141;
    const USAGE_THUMBNAIL = 142;
    const USAGE_EXTERNAL_URI = 166;

    /** Derivative type labels */
    const THUMBNAIL = 'thumbnail';
    const REFERENCE = 'reference';

    /** Default derivative dimensions */
    const DEFAULT_THUMBNAIL_SIZE = 150;
    const DEFAULT_REFERENCE_MAX_DIM = 480;

    /** Base upload path from server config */
    protected string $uploadsBasePath;

    public function __construct()
    {
        $this->uploadsBasePath = rtrim(config('heratio.uploads_path'), '/');
    }

    /**
     * Generate a thumbnail from a master image.
     *
     * Uses ImageMagick to create a square center-cropped thumbnail.
     * Command: convert input -thumbnail {size}x{size} -gravity center -extent {size}x{size} output
     */
    public function generateThumbnail(string $masterPath, string $outputPath, int $size = self::DEFAULT_THUMBNAIL_SIZE): bool
    {
        if (!file_exists($masterPath)) {
            Log::error('DerivativeService: Master file not found', ['path' => $masterPath]);
            return false;
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
            Log::error('DerivativeService: Cannot create output directory', ['dir' => $outputDir]);
            return false;
        }

        $command = sprintf(
            'convert %s -thumbnail %dx%d -gravity center -extent %dx%d %s 2>&1',
            escapeshellarg($masterPath),
            $size,
            $size,
            $size,
            $size,
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('DerivativeService: Thumbnail generation failed', [
                'command' => $command,
                'output' => implode("\n", $output),
                'return_code' => $returnCode,
            ]);
            return false;
        }

        return file_exists($outputPath);
    }

    /**
     * Generate a reference image from a master image.
     *
     * Uses ImageMagick to resize while maintaining aspect ratio.
     * The > flag means only shrink larger images, never enlarge.
     * Command: convert input -resize {maxDim}x{maxDim}> output
     */
    public function generateReference(string $masterPath, string $outputPath, int $maxDim = self::DEFAULT_REFERENCE_MAX_DIM): bool
    {
        if (!file_exists($masterPath)) {
            Log::error('DerivativeService: Master file not found', ['path' => $masterPath]);
            return false;
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
            Log::error('DerivativeService: Cannot create output directory', ['dir' => $outputDir]);
            return false;
        }

        // Read reference_image_maxwidth from settings if available
        $settingMaxWidth = DB::table('setting')
            ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
            ->where('setting.name', 'reference_image_maxwidth')
            ->where('setting_i18n.culture', 'en')
            ->value('setting_i18n.value');

        if ($settingMaxWidth && is_numeric($settingMaxWidth) && (int)$settingMaxWidth > 0) {
            $maxDim = (int)$settingMaxWidth;
        }

        $command = sprintf(
            'convert %s -resize %dx%d\> %s 2>&1',
            escapeshellarg($masterPath),
            $maxDim,
            $maxDim,
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('DerivativeService: Reference generation failed', [
                'command' => $command,
                'output' => implode("\n", $output),
                'return_code' => $returnCode,
            ]);
            return false;
        }

        return file_exists($outputPath);
    }

    /**
     * Regenerate both thumbnail and reference derivatives from the master.
     *
     * @return array{thumbnail: bool, reference: bool, errors: string[]}
     */
    public function regenerateDerivatives(int $digitalObjectId): array
    {
        $result = [
            'thumbnail' => false,
            'reference' => false,
            'errors' => [],
        ];

        // Get the master digital object
        $master = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->where('usage_id', self::USAGE_MASTER)
            ->first();

        if (!$master) {
            // Maybe the ID is for a derivative — find the master via parent_id
            $derivative = DB::table('digital_object')
                ->where('id', $digitalObjectId)
                ->first();

            if ($derivative && $derivative->parent_id) {
                $master = DB::table('digital_object')
                    ->where('id', $derivative->parent_id)
                    ->where('usage_id', self::USAGE_MASTER)
                    ->first();
            }

            if (!$master) {
                $result['errors'][] = 'Master digital object not found for ID ' . $digitalObjectId;
                return $result;
            }
        }

        $masterPath = $this->resolvePath($master->path, $master->name);
        if (!file_exists($masterPath)) {
            $result['errors'][] = 'Master file not found on disk: ' . $masterPath;
            return $result;
        }

        $baseDir = $this->uploadsBasePath . '/' . ltrim($master->path, '/');

        // Generate thumbnail
        $thumbFilename = pathinfo($master->name, PATHINFO_FILENAME) . '_142.' . $this->getOutputExtension($master->name);
        $thumbPath = $baseDir . $thumbFilename;
        if ($this->generateThumbnail($masterPath, $thumbPath)) {
            $this->upsertDerivativeRecord($master, self::USAGE_THUMBNAIL, $thumbFilename, $thumbPath);
            $result['thumbnail'] = true;
        } else {
            $result['errors'][] = 'Failed to generate thumbnail';
        }

        // Generate reference
        $refFilename = pathinfo($master->name, PATHINFO_FILENAME) . '_141.' . $this->getOutputExtension($master->name);
        $refPath = $baseDir . $refFilename;
        if ($this->generateReference($masterPath, $refPath)) {
            $this->upsertDerivativeRecord($master, self::USAGE_REFERENCE, $refFilename, $refPath);
            $result['reference'] = true;
        } else {
            $result['errors'][] = 'Failed to generate reference image';
        }

        return $result;
    }

    /**
     * Get the full filesystem path to an existing derivative.
     */
    public function getDerivativePath(int $objectId, string $type): ?string
    {
        $usageId = $type === self::THUMBNAIL ? self::USAGE_THUMBNAIL : self::USAGE_REFERENCE;

        // Find the master for this object
        $master = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->where('usage_id', self::USAGE_MASTER)
            ->first();

        if (!$master) {
            return null;
        }

        $derivative = DB::table('digital_object')
            ->where('parent_id', $master->id)
            ->where('usage_id', $usageId)
            ->first();

        if (!$derivative) {
            return null;
        }

        $path = $this->resolvePath($derivative->path, $derivative->name);
        return file_exists($path) ? $path : null;
    }

    /**
     * Get the full filesystem path to the master file for an information object.
     */
    public function getMasterPath(int $objectId): ?string
    {
        $master = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->where('usage_id', self::USAGE_MASTER)
            ->first();

        if (!$master) {
            return null;
        }

        $path = $this->resolvePath($master->path, $master->name);
        return file_exists($path) ? $path : null;
    }

    /**
     * Get derivative statistics for the admin dashboard.
     *
     * @return array{total_masters: int, with_thumbnails: int, with_references: int, missing_thumbnails: int, missing_references: int}
     */
    public function getStats(): array
    {
        $totalMasters = DB::table('digital_object')
            ->where('usage_id', self::USAGE_MASTER)
            ->count();

        $withThumbnails = DB::table('digital_object as m')
            ->join('digital_object as d', function ($join) {
                $join->on('d.parent_id', '=', 'm.id')
                    ->where('d.usage_id', '=', self::USAGE_THUMBNAIL);
            })
            ->where('m.usage_id', self::USAGE_MASTER)
            ->count();

        $withReferences = DB::table('digital_object as m')
            ->join('digital_object as d', function ($join) {
                $join->on('d.parent_id', '=', 'm.id')
                    ->where('d.usage_id', '=', self::USAGE_REFERENCE);
            })
            ->where('m.usage_id', self::USAGE_MASTER)
            ->count();

        return [
            'total_masters' => $totalMasters,
            'with_thumbnails' => $withThumbnails,
            'with_references' => $withReferences,
            'missing_thumbnails' => $totalMasters - $withThumbnails,
            'missing_references' => $totalMasters - $withReferences,
        ];
    }

    /**
     * Get a list of masters that are missing one or both derivatives.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getMastersWithMissingDerivatives(int $limit = 100): \Illuminate\Support\Collection
    {
        return DB::table('digital_object as m')
            ->leftJoin('digital_object as thumb', function ($join) {
                $join->on('thumb.parent_id', '=', 'm.id')
                    ->where('thumb.usage_id', '=', self::USAGE_THUMBNAIL);
            })
            ->leftJoin('digital_object as ref', function ($join) {
                $join->on('ref.parent_id', '=', 'm.id')
                    ->where('ref.usage_id', '=', self::USAGE_REFERENCE);
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'm.object_id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->where('m.usage_id', self::USAGE_MASTER)
            ->where(function ($query) {
                $query->whereNull('thumb.id')
                    ->orWhereNull('ref.id');
            })
            ->select([
                'm.id',
                'm.object_id',
                'm.name',
                'm.path',
                'm.mime_type',
                'm.byte_size',
                'ioi.title as object_title',
                DB::raw('CASE WHEN thumb.id IS NOT NULL THEN 1 ELSE 0 END as has_thumbnail'),
                DB::raw('CASE WHEN ref.id IS NOT NULL THEN 1 ELSE 0 END as has_reference'),
            ])
            ->orderBy('m.id', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recently generated derivatives.
     */
    public function getRecentDerivatives(int $limit = 25): \Illuminate\Support\Collection
    {
        return DB::table('digital_object as d')
            ->join('digital_object as m', 'd.parent_id', '=', 'm.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('ioi.id', '=', 'm.object_id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->whereIn('d.usage_id', [self::USAGE_THUMBNAIL, self::USAGE_REFERENCE])
            ->select([
                'd.id',
                'd.name',
                'd.usage_id',
                'd.mime_type',
                'd.byte_size',
                'm.id as master_id',
                'm.name as master_name',
                'm.object_id',
                'ioi.title as object_title',
            ])
            ->orderBy('d.id', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve a digital object's relative path + name to an absolute filesystem path.
     */
    protected function resolvePath(string $relativePath, string $filename): string
    {
        return $this->uploadsBasePath . '/' . ltrim($relativePath, '/') . $filename;
    }

    /**
     * Determine the output file extension for a derivative.
     * TIFFs are converted to JPEG; everything else stays the same.
     */
    protected function getOutputExtension(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        // Convert non-web formats to JPEG for derivatives
        if (in_array($ext, ['tiff', 'tif', 'bmp', 'psd', 'eps', 'ai', 'raw', 'cr2', 'nef', 'arw'])) {
            return 'jpg';
        }
        if ($ext === 'webp' || $ext === 'png' || $ext === 'gif' || $ext === 'jpg' || $ext === 'jpeg') {
            return $ext;
        }
        return 'jpg';
    }

    /**
     * Insert or update a derivative record in the digital_object table.
     */
    protected function upsertDerivativeRecord(object $master, int $usageId, string $filename, string $fullPath): void
    {
        $existing = DB::table('digital_object')
            ->where('parent_id', $master->id)
            ->where('usage_id', $usageId)
            ->first();

        $byteSize = file_exists($fullPath) ? filesize($fullPath) : null;
        $mimeType = file_exists($fullPath) ? (mime_content_type($fullPath) ?: 'image/jpeg') : 'image/jpeg';

        $data = [
            'usage_id' => $usageId,
            'name' => $filename,
            'path' => $master->path,
            'mime_type' => $mimeType,
            'media_type_id' => $master->media_type_id,
            'byte_size' => $byteSize,
            'object_id' => $master->object_id,
            'parent_id' => $master->id,
            'language' => $master->language,
            'sequence' => $master->sequence,
        ];

        if ($existing) {
            DB::table('digital_object')
                ->where('id', $existing->id)
                ->update($data);
        } else {
            DB::table('digital_object')->insert($data);
        }
    }
}
