<?php

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;

class DigitalObjectService
{
    /**
     * Get all digital objects for an entity, organized by usage type.
     * Returns: ['master' => ..., 'reference' => ..., 'thumbnail' => ..., 'all' => Collection]
     */
    public static function getForObject(int $objectId): array
    {
        $all = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->get();

        // If no direct objects, return empty
        if ($all->isEmpty()) {
            return ['master' => null, 'reference' => null, 'thumbnail' => null, 'all' => collect()];
        }

        // Find master (usage_id = 140)
        $master = $all->firstWhere('usage_id', 140);

        // Find derivatives (children of master)
        $thumbnail = null;
        $reference = null;
        if ($master) {
            $derivatives = DB::table('digital_object')
                ->where('parent_id', $master->id)
                ->get();
            $thumbnail = $derivatives->firstWhere('usage_id', 142);
            $reference = $derivatives->firstWhere('usage_id', 141);
            $all = $all->merge($derivatives);
        }

        return [
            'master' => $master,
            'reference' => $reference,
            'thumbnail' => $thumbnail,
            'all' => $all,
        ];
    }

    /**
     * Build the full URL path for a digital object.
     */
    public static function getUrl($digitalObject): string
    {
        if (!$digitalObject) {
            return '';
        }

        return rtrim($digitalObject->path, '/') . '/' . $digitalObject->name;
    }

    /**
     * Get the best display image URL (reference > master for images, thumbnail for listing).
     */
    public static function getDisplayUrl(array $objects): string
    {
        if (!empty($objects['reference'])) {
            return self::getUrl($objects['reference']);
        }
        if (!empty($objects['master'])) {
            return self::getUrl($objects['master']);
        }

        return '';
    }

    /**
     * Get thumbnail URL.
     */
    public static function getThumbnailUrl(array $objects): string
    {
        if (!empty($objects['thumbnail'])) {
            return self::getUrl($objects['thumbnail']);
        }

        return self::getDisplayUrl($objects);
    }

    /**
     * Determine media type string from media_type_id.
     * 136=Image, 137=Audio, 138=Video, 139=Text, 140=Other
     */
    public static function getMediaType($digitalObject): string
    {
        if (!$digitalObject) {
            return 'unknown';
        }

        return match ((int) $digitalObject->media_type_id) {
            136 => 'image',
            137 => 'audio',
            138 => 'video',
            139 => 'text',
            default => 'other',
        };
    }
}
