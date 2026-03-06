<?php

namespace AhgDisplay\Services;

use Illuminate\Support\Facades\DB;

class DisplayService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getObjectDisplay(int $objectId): array
    {
        $type = DisplayTypeDetector::detect($objectId);
        $profile = DisplayTypeDetector::getProfile($objectId);
        $object = $this->getObjectData($objectId);

        return [
            'object' => $object,
            'type' => $type,
            'profile' => $profile,
            'fields' => $this->getFieldsForProfile($profile),
        ];
    }

    public function getObjectData(int $objectId): ?object
    {
        $culture = app()->getLocale();
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'level.id')->where('level.culture', '=', $culture);
            })
            ->where('io.id', $objectId)
            ->select('io.*', 'i18n.title', 'i18n.scope_and_content', 'i18n.extent_and_medium',
                'i18n.archival_history', 'i18n.acquisition', 'i18n.arrangement',
                'i18n.access_conditions', 'i18n.reproduction_conditions',
                'level.name as level_name')
            ->first();
    }

    public function getFieldsForProfile(?object $profile): array
    {
        if (!$profile) {
            return [];
        }

        $fieldCodes = array_merge(
            json_decode($profile->identity_fields ?? '[]', true) ?: [],
            json_decode($profile->description_fields ?? '[]', true) ?: [],
            json_decode($profile->context_fields ?? '[]', true) ?: [],
            json_decode($profile->access_fields ?? '[]', true) ?: []
        );

        if (empty($fieldCodes)) {
            return [];
        }

        $culture = app()->getLocale();
        return DB::table('display_field as df')
            ->leftJoin('display_field_i18n as dfi', function ($j) use ($culture) {
                $j->on('df.id', '=', 'dfi.id')->where('dfi.culture', '=', $culture);
            })
            ->whereIn('df.code', $fieldCodes)
            ->select('df.*', 'dfi.name', 'dfi.help_text')
            ->orderByRaw('FIELD(df.code, "' . implode('","', $fieldCodes) . '")')
            ->get()
            ->toArray();
    }

    public function getLevels(?string $domain = null): array
    {
        $culture = app()->getLocale();
        $query = DB::table('display_level as dl')
            ->leftJoin('display_level_i18n as dli', function ($j) use ($culture) {
                $j->on('dl.id', '=', 'dli.id')->where('dli.culture', '=', $culture);
            })
            ->select('dl.*', 'dli.name', 'dli.description')
            ->orderBy('dl.sort_order');

        if ($domain) {
            $query->where('dl.domain', $domain);
        }

        return $query->get()->toArray();
    }

    public function getCollectionTypes(): array
    {
        $culture = app()->getLocale();
        return DB::table('display_collection_type as dct')
            ->leftJoin('display_collection_type_i18n as dcti', function ($j) use ($culture) {
                $j->on('dct.id', '=', 'dcti.id')->where('dcti.culture', '=', $culture);
            })
            ->select('dct.*', 'dcti.name', 'dcti.description')
            ->orderBy('dct.sort_order')
            ->get()
            ->toArray();
    }

    public function setObjectType(int $objectId, string $type): void
    {
        DB::table('display_object_config')->updateOrInsert(
            ['object_id' => $objectId],
            ['object_type' => $type, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }

    public function setObjectTypeRecursive(int $parentId, string $type): int
    {
        $children = DB::table('information_object')
            ->where('parent_id', $parentId)
            ->pluck('id')
            ->toArray();

        $count = 0;
        foreach ($children as $childId) {
            $this->setObjectType($childId, $type);
            $count++;
            $count += $this->setObjectTypeRecursive($childId, $type);
        }

        return $count;
    }

    public function assignProfile(int $objectId, int $profileId, string $context = 'default', bool $primary = false): void
    {
        DB::table('display_object_profile')->updateOrInsert(
            ['object_id' => $objectId, 'profile_id' => $profileId, 'context' => $context],
            ['is_primary' => $primary ? 1 : 0]
        );
    }
}
