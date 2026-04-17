<?php

/**
 * ThreeDThumbnailService - 3D model thumbnail generation
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

declare(strict_types=1);

namespace Ahg3dModel\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for generating thumbnails from 3D model files (GLB, GLTF, OBJ, etc.)
 *
 * Ported from ahg3DModelPlugin ThreeDThumbnailService.
 */
class ThreeDThumbnailService
{
    private string $toolPath;
    private string $logPath;

    private array $supported3DExtensions = [
        'glb', 'gltf', 'obj', 'stl', 'fbx', 'ply', 'dae',
    ];

    private array $supported3DMimeTypes = [
        'model/obj',
        'model/gltf-binary',
        'model/gltf+json',
        'model/stl',
        'application/x-tgif',
        'model/vnd.usdz+zip',
        'application/x-ply',
    ];

    public function __construct()
    {
        $this->toolPath = __DIR__ . '/../../tools/3d-thumbnail';
        $this->logPath = storage_path('logs/3d-thumbnail.log');
    }

    // ------------------------------------------------------------------
    // Public helpers
    // ------------------------------------------------------------------

    /**
     * Check whether a filename has a recognised 3D extension.
     */
    public function is3DModel(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, $this->supported3DExtensions, true);
    }

    /**
     * Check whether a MIME type is a recognised 3D type.
     */
    public function is3DMimeType(string $mime): bool
    {
        return in_array($mime, $this->supported3DMimeTypes, true);
    }

    /**
     * Return all supported 3D file extensions.
     */
    public function getSupportedExtensions(): array
    {
        return $this->supported3DExtensions;
    }

    /**
     * Return all supported 3D MIME types.
     */
    public function getSupportedMimeTypes(): array
    {
        return $this->supported3DMimeTypes;
    }

    // ------------------------------------------------------------------
    // Thumbnail generation
    // ------------------------------------------------------------------

    /**
     * Generate a single thumbnail image from a 3D model file.
     *
     * Checks for Blender (/snap/bin/blender then PATH), runs
     * generate-thumbnail.sh, and falls back to an ImageMagick
     * placeholder if Blender is unavailable.
     */
    public function generateThumbnail(
        string $inputPath,
        string $outputPath,
        int $width = 512,
        int $height = 512,
    ): bool {
        if (!file_exists($inputPath)) {
            $this->log("Input file not found: {$inputPath}", 'ERROR');

            return false;
        }

        $script = $this->toolPath . '/generate-thumbnail.sh';

        if (!file_exists($script)) {
            $this->log("Thumbnail script not found: {$script}", 'ERROR');

            return false;
        }

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $cmd = sprintf(
            '%s %s %s %d %d 2>&1',
            escapeshellcmd($script),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath),
            $width,
            $height,
        );

        $this->log("Executing: {$cmd}");
        $output = shell_exec($cmd);
        $this->log("Output: {$output}", 'DEBUG');

        if (file_exists($outputPath) && filesize($outputPath) > 1000) {
            $this->log("Thumbnail generated: {$outputPath}");

            return true;
        }

        $this->log("Thumbnail generation failed for: {$inputPath}", 'WARNING');

        return false;
    }

    /**
     * Generate 6 multi-angle renders of a 3D model via Blender.
     *
     * Views: front, back, left, right, top, detail.
     * Falls back to ImageMagick placeholders if Blender is unavailable.
     *
     * @return array<string, string> Map of view name => file path
     */
    public function generateMultiAngle(
        string $inputPath,
        string $outputDir,
        int $size = 1024,
    ): array {
        $views = ['front', 'back', 'left', 'right', 'top', 'detail'];

        if (!file_exists($inputPath)) {
            $this->log("Multi-angle input not found: {$inputPath}", 'ERROR');

            return [];
        }

        // Check cache: if all 6 PNGs exist and input file is older, return cached
        $allExist = true;
        $cached = [];
        foreach ($views as $view) {
            $png = rtrim($outputDir, '/') . '/' . $view . '.png';
            if (!file_exists($png)) {
                $allExist = false;
                break;
            }
            $cached[$view] = $png;
        }

        if ($allExist && isset($cached['front']) && filemtime($inputPath) < filemtime($cached['front'])) {
            $this->log("Multi-angle cache hit for: {$inputPath}");

            return $cached;
        }

        // Create output directory
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $script = $this->toolPath . '/generate-multiangle.sh';

        if (!file_exists($script)) {
            $this->log("Multi-angle script not found: {$script}", 'ERROR');

            return [];
        }

        $cmd = sprintf(
            '%s %s %s %d 2>&1',
            escapeshellcmd($script),
            escapeshellarg($inputPath),
            escapeshellarg($outputDir),
            $size,
        );

        $this->log("Multi-angle: {$cmd}");
        $output = shell_exec($cmd);
        $this->log("Multi-angle output: {$output}", 'DEBUG');

        $results = [];
        foreach ($views as $view) {
            $png = rtrim($outputDir, '/') . '/' . $view . '.png';
            if (file_exists($png) && filesize($png) > 500) {
                $results[$view] = $png;
                @chown($png, 'www-data');
                @chgrp($png, 'www-data');
            }
        }

        if (count($results) > 0) {
            $this->log('Multi-angle generated ' . count($results) . " views for: {$inputPath}");
        } else {
            $this->log("Multi-angle generation failed for: {$inputPath}", 'WARNING');
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Derivative records (AtoM digital_object table)
    // ------------------------------------------------------------------

    /**
     * Create reference + thumbnail derivative images for a digital object.
     */
    public function createDerivatives(int $digitalObjectId): bool
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->first();

        if (!$digitalObject) {
            $this->log("Digital object not found: {$digitalObjectId}", 'ERROR');

            return false;
        }

        if (!$this->is3DModel($digitalObject->name)) {
            $this->log("Not a 3D model: {$digitalObject->name}", 'DEBUG');

            return false;
        }

        $uploadsBase = config('heratio.uploads_path');
        $masterPath = $uploadsBase . $digitalObject->path . $digitalObject->name;

        if (!file_exists($masterPath)) {
            $this->log("Master file not found: {$masterPath}", 'ERROR');

            return false;
        }

        $baseName = pathinfo($digitalObject->name, PATHINFO_FILENAME);
        $derivativePath = dirname($masterPath) . '/';

        $referenceName = $baseName . '_reference.png';
        $thumbnailName = $baseName . '_thumbnail.png';

        $referencePath = $derivativePath . $referenceName;
        $thumbnailPath = $derivativePath . $thumbnailName;

        // Generate reference image (larger)
        $refSuccess = $this->generateThumbnail($masterPath, $referencePath, 480, 480);

        // Generate thumbnail (smaller)
        $thumbSuccess = $this->generateThumbnail($masterPath, $thumbnailPath, 150, 150);

        if (!$refSuccess && !$thumbSuccess) {
            return false;
        }

        // Get usage term IDs
        $referenceUsageId = $this->getTermId('Reference');
        $thumbnailUsageId = $this->getTermId('Thumbnail');

        if (!$referenceUsageId) {
            $referenceUsageId = $this->getTermId('reference image');
        }
        if (!$thumbnailUsageId) {
            $thumbnailUsageId = $this->getTermId('thumbnail image');
        }

        $this->log("Reference usage ID: {$referenceUsageId}, Thumbnail usage ID: {$thumbnailUsageId}");

        // Store reference derivative
        if ($refSuccess && file_exists($referencePath) && $referenceUsageId) {
            $this->createDerivativeRecord(
                $digitalObjectId,
                $referenceName,
                $digitalObject->path,
                $referenceUsageId,
                'image/png',
                filesize($referencePath),
            );
        }

        // Store thumbnail derivative
        if ($thumbSuccess && file_exists($thumbnailPath) && $thumbnailUsageId) {
            $this->createDerivativeRecord(
                $digitalObjectId,
                $thumbnailName,
                $digitalObject->path,
                $thumbnailUsageId,
                'image/png',
                filesize($thumbnailPath),
            );
        }

        // Set file ownership
        @chown($referencePath, 'www-data');
        @chown($thumbnailPath, 'www-data');
        @chgrp($referencePath, 'www-data');
        @chgrp($thumbnailPath, 'www-data');

        $this->log("Derivatives created for digital object: {$digitalObjectId}");

        return true;
    }

    /**
     * Get the multi-angle output directory for a digital object.
     */
    public function getMultiAngleDir(int $digitalObjectId): string
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $digitalObjectId)
            ->first();

        if (!$digitalObject) {
            return '';
        }

        $uploadsBase = config('heratio.uploads_path');
        $masterDir = dirname($uploadsBase . $digitalObject->path . $digitalObject->name);

        return $masterDir . '/multiangle';
    }

    /**
     * Batch-process all 3D digital objects that have no derivative thumbnails.
     *
     * @return array{processed: int, success: int, failed: int}
     */
    public function batchProcessExisting(): array
    {
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0];

        // Find all 3D digital objects without derivatives
        $objects = DB::table('digital_object as do')
            ->leftJoin('digital_object as deriv', 'deriv.parent_id', '=', 'do.id')
            ->whereNull('do.parent_id')
            ->whereNull('deriv.id')
            ->where(function ($query) {
                foreach ($this->supported3DExtensions as $ext) {
                    $query->orWhere('do.name', 'LIKE', "%.{$ext}");
                }
            })
            ->select('do.id', 'do.name', 'do.path')
            ->get();

        $this->log('Found ' . count($objects) . ' 3D objects without thumbnails');

        foreach ($objects as $obj) {
            $results['processed']++;

            try {
                if ($this->createDerivatives($obj->id)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $this->log('Exception: ' . $e->getMessage(), 'ERROR');
            }
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Create or update a derivative record in the digital_object table.
     */
    private function createDerivativeRecord(
        int $parentId,
        string $name,
        string $path,
        int $usageId,
        string $mimeType,
        int $byteSize,
    ): int {
        // Check if derivative already exists
        $existing = DB::table('digital_object')
            ->where('parent_id', $parentId)
            ->where('usage_id', $usageId)
            ->first();

        if ($existing) {
            DB::table('digital_object')
                ->where('id', $existing->id)
                ->update([
                    'name' => $name,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'byte_size' => $byteSize,
                ]);
            $this->log("Updated existing derivative: {$existing->id}");

            return $existing->id;
        }

        // AtoM requires object table entry first (class table inheritance)
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        DB::table('digital_object')->insert([
            'id' => $objectId,
            'parent_id' => $parentId,
            'usage_id' => $usageId,
            'name' => $name,
            'path' => $path,
            'mime_type' => $mimeType,
            'byte_size' => $byteSize,
            'sequence' => 0,
        ]);

        $this->log("Created derivative record: {$objectId}");

        return $objectId;
    }

    /**
     * Lookup a term ID by name from term_i18n.
     */
    private function getTermId(string $name): int
    {
        $term = DB::table('term_i18n')
            ->where('name', $name)
            ->first();

        return $term ? (int) $term->id : 0;
    }

    /**
     * Append a timestamped log line to the 3D thumbnail log file.
     */
    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}\n";

        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($this->logPath, $line, FILE_APPEND);

        // Also log to Laravel's logger for important messages
        if ($level === 'ERROR') {
            Log::error("[3D Thumbnail] {$message}");
        } elseif ($level === 'WARNING') {
            Log::warning("[3D Thumbnail] {$message}");
        }
    }
}
