<?php

/**
 * ConditionService - Service for Heratio
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



namespace AhgCondition\Services;

use Illuminate\Support\Facades\DB;

class ConditionService
{
    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? app()->getLocale();
    }

    public function getAdminStats(): array
    {
        return [
            'total_checks' => DB::table('spectrum_condition_check')->count(),
            'total_photos' => DB::table('spectrum_condition_photo')->count(),
            'total_annotations' => DB::table('spectrum_condition_photo')->whereNotNull('annotations')->count(),
        ];
    }

    public function getRecentChecks(int $limit = 20): \Illuminate\Support\Collection
    {
        return DB::table('spectrum_condition_check as cc')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'cc.object_id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->select('cc.*', 'ioi.title as object_title')
            ->orderByDesc('cc.check_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Get condition checks for a specific information object.
     */
    public function getConditionChecksForObject(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('spectrum_condition_check')
            ->where('object_id', $objectId)
            ->orderByDesc('check_date')
            ->get();
    }

    public function getByConditionBreakdown(): \Illuminate\Support\Collection
    {
        return DB::table('spectrum_condition_check')
            ->selectRaw('overall_condition, COUNT(*) as count')
            ->groupBy('overall_condition')
            ->orderByDesc('count')
            ->get();
    }

    public function getConditionCheck(int $id): ?object
    {
        return DB::table('spectrum_condition_check as cc')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'cc.object_id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'cc.object_id', '=', 'slug.object_id')
            ->select('cc.*', 'ioi.title as object_title', 'slug.slug as object_slug')
            ->where('cc.id', $id)
            ->first();
    }

    public function getConditionChecksByObject(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('spectrum_condition_check')
            ->where('object_id', $objectId)
            ->orderByDesc('check_date')
            ->get();
    }

    public function createConditionCheck(int $objectId): int
    {
        $newId = DB::table('spectrum_condition_check')->insertGetId([
            'object_id' => $objectId,
            'condition_check_reference' => 'CC-' . date('Ymd') . '-' . $objectId,
            'check_date' => now()->toDateString(),
            'overall_condition' => 'pending',
            'checked_by' => 'System',
            'created_at' => now(),
        ]);
        \AhgCore\Support\AuditLog::captureMutation((int) $newId, 'condition_check', 'create', [
            'data' => [
                'condition_check_id' => $newId,
                'object_id' => $objectId,
                'reference' => 'CC-' . date('Ymd') . '-' . $objectId,
            ],
        ]);
        return (int) $newId;
    }

    public function getPhotosForCheck(int $checkId): \Illuminate\Support\Collection
    {
        return DB::table('spectrum_condition_photo')
            ->where('condition_check_id', $checkId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getPhoto(int $id): ?object
    {
        return DB::table('spectrum_condition_photo')->where('id', $id)->first();
    }

    public function getAnnotations(int $photoId): array
    {
        $photo = $this->getPhoto($photoId);

        if (!$photo || empty($photo->annotations)) {
            return [];
        }

        return json_decode($photo->annotations, true) ?: [];
    }

    public function saveAnnotations(int $photoId, array $annotations, int $userId): bool
    {
        $result = DB::table('spectrum_condition_photo')
            ->where('id', $photoId)
            ->update([
                'annotations' => json_encode($annotations),
                'updated_at' => now(),
            ]) >= 0;
        \AhgCore\Support\AuditLog::captureMutation($photoId, 'condition_photo', 'annotations_update', [
            'data' => ['annotation_count' => count($annotations), 'photo_id' => $photoId, 'user_id' => $userId],
        ]);
        return $result;
    }

    public function getAnnotationStats(int $checkId): array
    {
        $photos = $this->getPhotosForCheck($checkId);
        $totalAnnotations = 0;

        foreach ($photos as $photo) {
            if (!empty($photo->annotations)) {
                $annotations = json_decode($photo->annotations, true);
                if (is_array($annotations)) {
                    $totalAnnotations += count($annotations);
                }
            }
        }

        return [
            'total_photos' => $photos->count(),
            'total_annotations' => $totalAnnotations,
        ];
    }

    public function uploadPhoto(int $checkId, array $file, string $photoType, string $caption, int $userId): ?int
    {
        try {
            $result = (new \AhgMediaProcessing\Services\PhotoProcessor())
                ->process($file['tmp_name'], $file['name']);
        } catch (\AhgMediaProcessing\Services\PhotoProcessorException $e) {
            \Illuminate\Support\Facades\Log::warning('[condition] uploadPhoto rejected', [
                'condition_check_id' => $checkId,
                'original_name' => $file['name'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $exif = $result['exif'] ?? [];

        $newId = DB::table('spectrum_condition_photo')->insertGetId([
            'condition_check_id' => $checkId,
            'filename' => $result['filename'],
            'original_name' => $file['name'],
            'original_filename' => $file['name'],
            'file_path' => $result['path'],
            'file_size' => $result['size'],
            'mime_type' => $result['mime_type'],
            'width' => $result['width'],
            'height' => $result['height'],
            'photographer' => $exif['photographer'] ?? null,
            'photo_date' => $exif['photo_date'] ?? null,
            'camera_info' => $exif['camera_info'] ?? null,
            'photo_type' => $photoType,
            'caption' => $caption,
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        \AhgCore\Support\AuditLog::captureMutation((int) $newId, 'condition_photo', 'create', [
            'data' => [
                'condition_check_id' => $checkId,
                'filename' => $result['filename'],
                'original_name' => $file['name'],
                'photo_type' => $photoType,
                'caption' => $caption,
                'width' => $result['width'],
                'height' => $result['height'],
                'thumbnails' => array_keys($result['thumbnails'] ?? []),
            ],
        ]);

        return (int) $newId;
    }

    public function deletePhoto(int $photoId, int $userId): bool
    {
        $photo = $this->getPhoto($photoId);

        if (!$photo) {
            return false;
        }

        \AhgCore\Support\AuditLog::captureMutation($photoId, 'condition_photo', 'delete', [
            'data' => [
                'filename' => $photo->filename ?? null,
                'original_name' => $photo->original_name ?? null,
                'photo_type' => $photo->photo_type ?? null,
                'caption' => $photo->caption ?? null,
            ],
        ]);

        // Master file: prefer the stored file_path (populated by uploadPhoto via PhotoProcessor);
        // fall back to the legacy hardcoded location for rows that predate the wiring.
        $masterPath = !empty($photo->file_path)
            ? $photo->file_path
            : storage_path('app/public/condition_photos/' . $photo->filename);
        if (!empty($photo->filename) && is_file($masterPath)) {
            @unlink($masterPath);
        }

        // Thumbnail siblings sit at {masterDir}/thumbs/{small,medium,large}/{filename}.
        // Sweep them whether or not the master existed; orphan thumbs are a real
        // failure mode we'd rather not leave behind.
        if (!empty($photo->filename)) {
            $masterDir = dirname($masterPath);
            foreach (['small', 'medium', 'large'] as $size) {
                $thumbPath = rtrim($masterDir, '/') . "/thumbs/$size/" . $photo->filename;
                if (is_file($thumbPath)) {
                    @unlink($thumbPath);
                }
            }
        }

        return DB::table('spectrum_condition_photo')->where('id', $photoId)->delete() > 0;
    }

    public function getConditionCheckForObject(string $slug): ?array
    {
        $slugRecord = DB::table('slug')->where('slug', $slug)->first();

        if (!$slugRecord) {
            return null;
        }

        $resource = DB::table('information_object')->where('id', $slugRecord->object_id)->first();

        if (!$resource) {
            return null;
        }

        $i18n = DB::table('information_object_i18n')
            ->where('id', $resource->id)
            ->where('culture', $this->culture)
            ->first();

        $conditions = DB::table('spectrum_condition_check')
            ->where('object_id', $resource->id)
            ->orderByDesc('check_date')
            ->get();

        return [
            'resource' => $resource,
            'title' => $i18n->title ?? $slug,
            'slug' => $slug,
            'conditions' => $conditions,
            'latest' => $conditions->first(),
        ];
    }

    public function getConditionChecks(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('spectrum_condition_check as cc')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'cc.object_id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->select('cc.*', 'ioi.title as object_title');

        if (!empty($filters['condition'])) {
            $query->where('cc.overall_condition', $filters['condition']);
        }

        return $query->orderByDesc('cc.check_date')->get();
    }

    public function getTemplates(): \Illuminate\Support\Collection
    {
        return DB::table('spectrum_condition_template')->orderBy('name')->get();
    }

    public function getTemplateView(int $id): ?object
    {
        return DB::table('spectrum_condition_template')->where('id', $id)->first();
    }
}
