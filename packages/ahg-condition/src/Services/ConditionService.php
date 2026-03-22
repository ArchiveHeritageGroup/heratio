<?php

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
            'total_photos' => DB::table('condition_photo')->count(),
            'total_annotations' => DB::table('condition_annotation')->count(),
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
        return DB::table('spectrum_condition_check')->insertGetId([
            'object_id' => $objectId,
            'condition_check_reference' => 'CC-' . date('Ymd') . '-' . $objectId,
            'check_date' => now()->toDateString(),
            'overall_condition' => 'pending',
            'checked_by' => 'System',
            'created_at' => now(),
        ]);
    }

    public function getPhotosForCheck(int $checkId): \Illuminate\Support\Collection
    {
        return DB::table('condition_photo')
            ->where('condition_check_id', $checkId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getPhoto(int $id): ?object
    {
        return DB::table('condition_photo')->where('id', $id)->first();
    }

    public function getAnnotations(int $photoId): array
    {
        $photo = $this->getPhoto($photoId);

        if (!$photo || empty($photo->annotation_data)) {
            return [];
        }

        return json_decode($photo->annotation_data, true) ?: [];
    }

    public function saveAnnotations(int $photoId, array $annotations, int $userId): bool
    {
        return DB::table('condition_photo')
            ->where('id', $photoId)
            ->update([
                'annotation_data' => json_encode($annotations),
                'updated_at' => now(),
            ]) >= 0;
    }

    public function getAnnotationStats(int $checkId): array
    {
        $photos = $this->getPhotosForCheck($checkId);
        $totalAnnotations = 0;

        foreach ($photos as $photo) {
            if (!empty($photo->annotation_data)) {
                $annotations = json_decode($photo->annotation_data, true);
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
        $uploadDir = storage_path('app/public/condition_photos');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('cond_') . '.' . $ext;
        $path = $uploadDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            return DB::table('condition_photo')->insertGetId([
                'condition_check_id' => $checkId,
                'filename' => $filename,
                'original_name' => $file['name'],
                'photo_type' => $photoType,
                'caption' => $caption,
                'uploaded_by' => $userId,
                'created_at' => now(),
            ]);
        }

        return null;
    }

    public function deletePhoto(int $photoId, int $userId): bool
    {
        $photo = $this->getPhoto($photoId);

        if (!$photo) {
            return false;
        }

        $path = storage_path('app/public/condition_photos/' . $photo->filename);
        if (file_exists($path)) {
            unlink($path);
        }

        return DB::table('condition_photo')->where('id', $photoId)->delete() > 0;
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
        return DB::table('condition_template')->orderBy('name')->get();
    }

    public function getTemplateView(int $id): ?object
    {
        return DB::table('condition_template')->where('id', $id)->first();
    }
}
