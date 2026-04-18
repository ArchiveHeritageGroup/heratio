<?php

/**
 * WatermarkService - Service for Heratio
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WatermarkService - Handles watermark application and configuration.
 *
 * Ported from AtoM's WatermarkService + WatermarkSettingsService.
 * Uses ImageMagick composite for watermark application.
 *
 * Tables used:
 * - watermark_setting (global key/value settings)
 * - watermark_type (system watermark types: DRAFT, COPYRIGHT, etc.)
 * - custom_watermark (user-uploaded custom watermarks)
 * - object_watermark_setting (per-object watermark config)
 * - object_security_classification + security_classification (security-based watermarks)
 */
class WatermarkService
{
    /** Position constants matching AtoM gravity values */
    const POS_TOP_LEFT = 'NorthWest';
    const POS_TOP_CENTER = 'North';
    const POS_TOP_RIGHT = 'NorthEast';
    const POS_CENTER_LEFT = 'West';
    const POS_CENTER = 'Center';
    const POS_CENTER_RIGHT = 'East';
    const POS_BOTTOM_LEFT = 'SouthWest';
    const POS_BOTTOM_CENTER = 'South';
    const POS_BOTTOM_RIGHT = 'SouthEast';
    const POS_REPEAT = 'Repeat';

    /** Map of human-readable position labels to ImageMagick gravity values */
    const POSITION_MAP = [
        'top-left' => 'NorthWest',
        'top-center' => 'North',
        'top-right' => 'NorthEast',
        'center-left' => 'West',
        'center' => 'Center',
        'center-right' => 'East',
        'bottom-left' => 'SouthWest',
        'bottom-center' => 'South',
        'bottom-right' => 'SouthEast',
        'repeat' => 'Repeat',
    ];

    /** Reverse map: gravity value to human label */
    const POSITION_LABELS = [
        'NorthWest' => 'Top Left',
        'North' => 'Top Center',
        'NorthEast' => 'Top Right',
        'West' => 'Center Left',
        'Center' => 'Center',
        'East' => 'Center Right',
        'SouthWest' => 'Bottom Left',
        'South' => 'Bottom Center',
        'SouthEast' => 'Bottom Right',
        'Repeat' => 'Repeat (Tile)',
    ];

    /** Watermark images path relative to public */
    protected string $watermarkImagePath = '/images/watermarks/';

    /** Upload path for custom watermarks */
    protected string $uploadsPath;

    public function __construct()
    {
        $this->uploadsPath = rtrim(config('heratio.uploads_path'), '/') . '/watermarks';
    }

    /**
     * Apply a watermark image to a target image using ImageMagick composite.
     *
     * For positions other than Repeat:
     *   composite -dissolve {opacity} -gravity {position} watermark image output
     *
     * For Repeat (tile):
     *   composite -dissolve {opacity} -tile watermark image output
     */
    public function apply(string $imagePath, string $watermarkPath, string $position = 'SouthEast', int $opacity = 30): bool
    {
        if (!file_exists($imagePath)) {
            Log::error('WatermarkService: Target image not found', ['path' => $imagePath]);
            return false;
        }

        if (!file_exists($watermarkPath)) {
            Log::error('WatermarkService: Watermark image not found', ['path' => $watermarkPath]);
            return false;
        }

        // Check minimum image size before applying
        $minSize = (int)$this->getSetting('watermark_min_size', '200');
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo && ($imageInfo[0] < $minSize || $imageInfo[1] < $minSize)) {
            Log::info('WatermarkService: Image too small for watermark', [
                'path' => $imagePath,
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'min_size' => $minSize,
            ]);
            return false;
        }

        $gravity = self::POSITION_MAP[$position] ?? $position;

        if (strtolower($gravity) === 'repeat' || strtolower($position) === 'repeat') {
            $command = sprintf(
                'composite -dissolve %d -tile %s %s %s 2>&1',
                $opacity,
                escapeshellarg($watermarkPath),
                escapeshellarg($imagePath),
                escapeshellarg($imagePath)
            );
        } else {
            $command = sprintf(
                'composite -dissolve %d -gravity %s %s %s %s 2>&1',
                $opacity,
                escapeshellarg($gravity),
                escapeshellarg($watermarkPath),
                escapeshellarg($imagePath),
                escapeshellarg($imagePath)
            );
        }

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('WatermarkService: Watermark application failed', [
                'command' => $command,
                'output' => implode("\n", $output),
                'return_code' => $returnCode,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Apply watermark to an image based on its object's watermark configuration.
     * Follows the AtoM priority chain: security > object-specific > default.
     */
    public function applyForObject(string $imagePath, int $objectId): bool
    {
        $config = $this->getWatermarkConfig($objectId);

        if (!$config || !isset($config['image'])) {
            return false;
        }

        $watermarkPath = public_path($config['image']);
        if (!file_exists($watermarkPath)) {
            // Try absolute path
            $watermarkPath = $config['image'];
            if (!file_exists($watermarkPath)) {
                Log::warning('WatermarkService: Watermark image not found for object', [
                    'object_id' => $objectId,
                    'image' => $config['image'],
                ]);
                return false;
            }
        }

        $position = $config['position'] ?? 'center';
        $opacity = (int)(($config['opacity'] ?? 0.40) * 100);

        return $this->apply($imagePath, $watermarkPath, $position, $opacity);
    }

    /**
     * Get the complete watermark configuration for an object.
     *
     * Priority:
     * 1. Security classification watermark (highest)
     * 2. Object-specific watermark (object_watermark_setting)
     * 3. Default watermark (global setting)
     */
    public function getWatermarkConfig(int $objectId): ?array
    {
        // 1. Check security classification (highest priority)
        $security = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->where('osc.object_id', $objectId)
            ->where('sc.watermark_required', 1)
            ->whereNotNull('sc.watermark_image')
            ->where('sc.watermark_image', '!=', '')
            ->select('sc.*')
            ->first();

        if ($security) {
            return [
                'type' => 'security',
                'code' => 'SECURITY',
                'image' => $security->watermark_image,
                'position' => 'repeat',
                'opacity' => 0.5,
                'source' => 'object_security_classification',
            ];
        }

        // 2. Check object_watermark_setting table
        $watermarkSetting = DB::table('object_watermark_setting as ows')
            ->leftJoin('watermark_type as wt', 'ows.watermark_type_id', '=', 'wt.id')
            ->leftJoin('custom_watermark as cw', 'ows.custom_watermark_id', '=', 'cw.id')
            ->where('ows.object_id', $objectId)
            ->select([
                'ows.watermark_enabled',
                'ows.watermark_type_id',
                'ows.custom_watermark_id',
                'ows.position',
                'ows.opacity',
                'wt.code as type_code',
                'wt.image_file as type_image',
                'wt.position as type_position',
                'wt.opacity as type_opacity',
                'cw.file_path as custom_path',
                'cw.name as custom_name',
                'cw.position as custom_position',
                'cw.opacity as custom_opacity',
            ])
            ->first();

        if ($watermarkSetting) {
            if (!$watermarkSetting->watermark_enabled) {
                return null;
            }

            // Custom watermark takes priority
            if ($watermarkSetting->custom_watermark_id && $watermarkSetting->custom_path) {
                return [
                    'type' => 'custom',
                    'code' => 'CUSTOM',
                    'image' => $watermarkSetting->custom_path,
                    'position' => $watermarkSetting->position ?? $watermarkSetting->custom_position ?? 'center',
                    'opacity' => (float)($watermarkSetting->opacity ?? $watermarkSetting->custom_opacity ?? 0.40),
                    'source' => 'custom_watermark',
                    'name' => $watermarkSetting->custom_name,
                ];
            }

            // System watermark type
            if ($watermarkSetting->watermark_type_id && $watermarkSetting->type_code !== 'NONE' && $watermarkSetting->type_image) {
                return [
                    'type' => 'selected',
                    'code' => $watermarkSetting->type_code,
                    'image' => $this->watermarkImagePath . $watermarkSetting->type_image,
                    'position' => $watermarkSetting->position ?? $watermarkSetting->type_position ?? 'center',
                    'opacity' => (float)($watermarkSetting->opacity ?? $watermarkSetting->type_opacity ?? 0.40),
                    'source' => 'object_watermark_setting',
                ];
            }
        }

        // 3. Check default watermark
        if ($this->getSetting('default_watermark_enabled', '1') === '1') {
            $defaultCode = $this->getSetting('default_watermark_type', 'COPYRIGHT');

            if ($defaultCode && $defaultCode !== 'NONE') {
                $wtype = DB::table('watermark_type')
                    ->where('code', $defaultCode)
                    ->where('active', 1)
                    ->first();

                if ($wtype && $wtype->image_file) {
                    return [
                        'type' => 'default',
                        'code' => $wtype->code,
                        'image' => $this->watermarkImagePath . $wtype->image_file,
                        'position' => $wtype->position ?? 'center',
                        'opacity' => (float)($wtype->opacity ?? 0.40),
                        'source' => 'default_setting',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check whether an object has a watermark configured.
     */
    public function hasWatermark(int $objectId): bool
    {
        return $this->getWatermarkConfig($objectId) !== null;
    }

    /**
     * Get all watermark settings from the watermark_setting table.
     */
    public function getSettings(): array
    {
        return DB::table('watermark_setting')
            ->pluck('setting_value', 'setting_key')
            ->toArray();
    }

    /**
     * Get a single watermark setting.
     */
    public function getSetting(string $key, ?string $default = null): ?string
    {
        $value = DB::table('watermark_setting')
            ->where('setting_key', $key)
            ->value('setting_value');

        return $value ?? $default;
    }

    /**
     * Save watermark settings (batch update/insert).
     */
    public function saveSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $exists = DB::table('watermark_setting')
                ->where('setting_key', $key)
                ->exists();

            if ($exists) {
                DB::table('watermark_setting')
                    ->where('setting_key', $key)
                    ->update([
                        'setting_value' => $value,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('watermark_setting')->insert([
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Get all active system watermark types.
     */
    public function getWatermarkTypes(): \Illuminate\Support\Collection
    {
        return DB::table('watermark_type')
            ->where('active', 1)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get all active custom watermarks (global ones: object_id IS NULL).
     */
    public function getCustomWatermarks(): \Illuminate\Support\Collection
    {
        return DB::table('custom_watermark')
            ->whereNull('object_id')
            ->where('active', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Upload a new custom watermark image.
     *
     * @return int|false The ID of the new custom_watermark record, or false on failure.
     */
    public function uploadCustomWatermark(
        \Illuminate\Http\UploadedFile $file,
        string $name,
        string $position = 'center',
        float $opacity = 0.40,
        ?int $createdBy = null
    ): int|false {
        // Validate file type
        $allowedMimes = ['image/png', 'image/jpeg', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return false;
        }

        // Ensure upload directory exists
        if (!is_dir($this->uploadsPath) && !mkdir($this->uploadsPath, 0755, true)) {
            Log::error('WatermarkService: Cannot create uploads directory', ['path' => $this->uploadsPath]);
            return false;
        }

        $ext = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'watermark_' . uniqid() . '.' . $ext;
        $filepath = $this->uploadsPath . '/' . $filename;

        if (!$file->move($this->uploadsPath, $filename)) {
            return false;
        }

        $id = DB::table('custom_watermark')->insertGetId([
            'object_id' => null,
            'name' => $name,
            'filename' => $filename,
            'file_path' => $filepath,
            'position' => $position,
            'opacity' => $opacity,
            'created_by' => $createdBy,
            'active' => 1,
            'created_at' => now(),
        ]);

        return $id ?: false;
    }

    /**
     * Delete a custom watermark.
     */
    public function deleteCustomWatermark(int $id): bool
    {
        $watermark = DB::table('custom_watermark')
            ->where('id', $id)
            ->first();

        if (!$watermark) {
            return false;
        }

        if ($watermark->file_path && file_exists($watermark->file_path)) {
            @unlink($watermark->file_path);
        }

        DB::table('custom_watermark')->where('id', $id)->delete();

        return true;
    }

    /**
     * Save per-object watermark settings.
     */
    public function saveObjectWatermark(int $objectId, ?int $watermarkTypeId, bool $enabled = true, ?string $position = null, ?float $opacity = null): bool
    {
        $existing = DB::table('object_watermark_setting')
            ->where('object_id', $objectId)
            ->first();

        $data = [
            'watermark_type_id' => $watermarkTypeId,
            'watermark_enabled' => $enabled ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($position !== null) {
            $data['position'] = $position;
        }
        if ($opacity !== null) {
            $data['opacity'] = $opacity;
        }

        if ($existing) {
            return DB::table('object_watermark_setting')
                ->where('object_id', $objectId)
                ->update($data) >= 0;
        }

        $data['object_id'] = $objectId;
        $data['position'] = $position ?? 'center';
        $data['opacity'] = $opacity ?? 0.40;
        $data['created_at'] = now();

        return DB::table('object_watermark_setting')->insert($data);
    }

    /**
     * Update the Cantaloupe IIIF cache file with all watermark configurations.
     */
    public function updateCantaloupeCache(): int
    {
        $cacheFile = '/tmp/cantaloupe_classifications.json';
        $cache = [];

        // Get security classifications
        $securityObjects = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->where('sc.watermark_required', 1)
            ->whereNotNull('sc.watermark_image')
            ->where('sc.watermark_image', '!=', '')
            ->select('osc.object_id', 'sc.watermark_image')
            ->get();

        foreach ($securityObjects as $sec) {
            $cache[$sec->object_id] = [
                'type' => 'security',
                'image' => $sec->watermark_image,
                'position' => 'repeat',
                'opacity' => 0.5,
            ];
        }

        // Get object-specific watermarks
        $objectWatermarks = DB::table('object_watermark_setting as ows')
            ->leftJoin('watermark_type as wt', 'ows.watermark_type_id', '=', 'wt.id')
            ->leftJoin('custom_watermark as cw', 'ows.custom_watermark_id', '=', 'cw.id')
            ->where('ows.watermark_enabled', 1)
            ->select([
                'ows.object_id',
                'ows.position',
                'ows.opacity',
                'wt.code as type_code',
                'wt.image_file as type_image',
                'cw.file_path as custom_path',
            ])
            ->get();

        foreach ($objectWatermarks as $ow) {
            if (isset($cache[$ow->object_id])) {
                continue; // Security classification takes priority
            }

            if ($ow->custom_path) {
                $cache[$ow->object_id] = [
                    'type' => 'custom',
                    'image' => $ow->custom_path,
                    'position' => $ow->position ?? 'center',
                    'opacity' => (float)($ow->opacity ?? 0.40),
                ];
            } elseif ($ow->type_code && $ow->type_code !== 'NONE' && $ow->type_image) {
                $cache[$ow->object_id] = [
                    'type' => 'selected',
                    'code' => $ow->type_code,
                    'image' => $this->watermarkImagePath . $ow->type_image,
                    'position' => $ow->position ?? 'center',
                    'opacity' => (float)($ow->opacity ?? 0.40),
                ];
            }
        }

        // Add default settings
        $defaultEnabled = $this->getSetting('default_watermark_enabled', '1') === '1';
        $defaultCode = $this->getSetting('default_watermark_type', 'COPYRIGHT');

        $cache['_default'] = [
            'enabled' => $defaultEnabled,
            'code' => $defaultCode,
        ];

        if ($defaultEnabled && $defaultCode && $defaultCode !== 'NONE') {
            $wtype = DB::table('watermark_type')
                ->where('code', $defaultCode)
                ->where('active', 1)
                ->first();

            if ($wtype && $wtype->image_file) {
                $cache['_default']['image'] = $this->watermarkImagePath . $wtype->image_file;
                $cache['_default']['position'] = $wtype->position ?? 'center';
                $cache['_default']['opacity'] = (float)($wtype->opacity ?? 0.40);
            }
        }

        file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));

        return count($cache) - 1; // Exclude _default from count
    }
}
