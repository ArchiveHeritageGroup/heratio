<?php

/**
 * TriposrImportService — moves a TripoSR-generated GLB from staging into an
 * IO's permanent uploads dir and registers the digital_object + object_3d_model
 * rows. Shared by the artisan command (auto-import) and the web preview flow
 * (after the user clicks Save & attach).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Ahg3dModel\Services;

use Illuminate\Support\Facades\DB;

class TriposrImportService
{
    /**
     * Move the GLB into uploads/r/<io>/, create digital_object + object_3d_model rows.
     * Returns ['success', 'model_id' | 'error', 'digital_object_id'].
     */
    public function importGlb(string $glbPath, int $ioId, ?string $sourceImagePath = null): array
    {
        if (!is_file($glbPath)) {
            return ['success' => false, 'error' => 'GLB file not found at ' . $glbPath];
        }

        $base = rtrim((string) config('heratio.uploads_path', ''), '/');
        if ($base === '') {
            return ['success' => false, 'error' => 'uploads_path not configured'];
        }
        $destDir = $base . '/' . $ioId;
        if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            return ['success' => false, 'error' => 'Could not create ' . $destDir];
        }

        $stem = 'triposr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $destName = $stem . '.glb';
        $destPath = $destDir . '/' . $destName;

        if (!@copy($glbPath, $destPath)) {
            return ['success' => false, 'error' => 'Could not copy GLB into ' . $destDir];
        }
        @chmod($destPath, 0644);

        $now = now();
        $byteSize = @filesize($destPath) ?: null;
        $checksum = @md5_file($destPath) ?: null;

        return DB::transaction(function () use ($ioId, $destName, $destPath, $byteSize, $checksum, $sourceImagePath, $now) {
            // 1. object table row
            $doObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitDigitalObject',
                'created_at' => $now,
                'updated_at' => $now,
                'serial_number' => 0,
            ]);

            // 2. digital_object row
            DB::table('digital_object')->insert([
                'id' => $doObjectId,
                'object_id' => $ioId,
                'usage_id' => 140, // master
                'mime_type' => 'model/gltf-binary',
                'name' => $destName,
                'path' => '/uploads/r/' . $ioId . '/',
                'byte_size' => $byteSize,
                'checksum' => $checksum,
                'checksum_type' => 'md5',
                'parent_id' => null,
            ]);

            // 3. object_3d_model row (lets the 3D viewer pick it up)
            $modelId = DB::table('object_3d_model')->insertGetId([
                'object_id' => $ioId,
                'filename' => $destName,
                'original_filename' => $destName,
                'file_path' => '/uploads/r/' . $ioId . '/' . $destName,
                'file_size' => $byteSize,
                'mime_type' => 'model/gltf-binary',
                'format' => 'glb',
                'auto_rotate' => 1,
                'rotation_speed' => 1.00,
                'camera_orbit' => '0deg 75deg 105%',
                'field_of_view' => '30deg',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'success' => true,
                'model_id' => $modelId,
                'digital_object_id' => $doObjectId,
                'destination' => $destPath,
            ];
        });
    }

    /**
     * Discard a staged GLB — used when the user clicks "Discard" in the modal.
     */
    public function discardStaged(string $glbPath): bool
    {
        // Refuse to delete anything outside the staging dir for safety
        $stagingDir = realpath(sys_get_temp_dir() . '/heratio-triposr') ?: (sys_get_temp_dir() . '/heratio-triposr');
        $real = realpath($glbPath) ?: $glbPath;
        if (strpos($real, $stagingDir) !== 0) {
            return false;
        }
        return is_file($real) ? @unlink($real) : true;
    }
}
