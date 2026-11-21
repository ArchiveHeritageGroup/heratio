<?php

/**
 * DisplayService - Service for Heratio
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



namespace AhgDisplay\Services;

use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

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
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as i18n', 'io.id', $culture))
            ->tap(fn ($q) => I18nFallback::join($q, 'term_i18n as level', 'io.level_of_description_id', $culture))
            ->where('io.id', $objectId)
            ->select('io.*', I18nFallback::col('i18n', 'title', 'title'), I18nFallback::col('i18n', 'scope_and_content', 'scope_and_content'), I18nFallback::col('i18n', 'extent_and_medium', 'extent_and_medium'),
                I18nFallback::col('i18n', 'archival_history', 'archival_history'), I18nFallback::col('i18n', 'acquisition', 'acquisition'), I18nFallback::col('i18n', 'arrangement', 'arrangement'),
                I18nFallback::col('i18n', 'access_conditions', 'access_conditions'), I18nFallback::col('i18n', 'reproduction_conditions', 'reproduction_conditions'),
                I18nFallback::col('level', 'name', 'level_name'))
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
            ->tap(fn ($q) => I18nFallback::join($q, 'display_field_i18n as dfi', 'df.id', $culture))
            ->whereIn('df.code', $fieldCodes)
            ->select('df.*', I18nFallback::col('dfi', 'name', 'name'), I18nFallback::col('dfi', 'help_text', 'help_text'))
            ->orderByRaw('FIELD(df.code, "' . implode('","', $fieldCodes) . '")')
            ->get()
            ->toArray();
    }

    public function getLevels(?string $domain = null): array
    {
        $culture = app()->getLocale();
        $query = DB::table('display_level as dl')
            ->tap(fn ($q) => I18nFallback::join($q, 'display_level_i18n as dli', 'dl.id', $culture))
            ->select('dl.*', I18nFallback::col('dli', 'name', 'name'), I18nFallback::col('dli', 'description', 'description'))
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
            ->tap(fn ($q) => I18nFallback::join($q, 'display_collection_type_i18n as dcti', 'dct.id', $culture))
            ->select('dct.*', I18nFallback::col('dcti', 'name', 'name'), I18nFallback::col('dcti', 'description', 'description'))
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
