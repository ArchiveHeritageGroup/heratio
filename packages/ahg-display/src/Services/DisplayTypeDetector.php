<?php

/**
 * DisplayTypeDetector - Service for Heratio
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

class DisplayTypeDetector
{
    protected static array $levelToDomain = [
        // Archive (ISAD)
        'fonds' => 'archive',
        'subfonds' => 'archive',
        'series' => 'archive',
        'subseries' => 'archive',
        'file' => 'archive',
        'item' => 'archive',
        'piece' => 'archive',
        'record group' => 'archive',
        'document' => 'archive',
        'part' => 'archive',
        'travel and exploration' => 'archive',

        // Museum (Spectrum)
        'object' => 'museum',
        'specimen' => 'museum',
        'artefact' => 'museum',
        'artifact' => 'museum',
        '3d model' => 'museum',

        // Gallery
        'artwork' => 'gallery',
        'painting' => 'gallery',
        'sculpture' => 'gallery',
        'drawing' => 'gallery',
        'print' => 'gallery',
        'installation' => 'gallery',

        // Library
        'book' => 'library',
        'periodical' => 'library',
        'volume' => 'library',
        'pamphlet' => 'library',
        'monograph' => 'library',
        'article' => 'library',
        'manuscript' => 'library',
        'journal' => 'library',

        // DAM
        'photograph' => 'dam',
        'photo' => 'dam',
        'image' => 'dam',
        'negative' => 'dam',
        'album' => 'dam',
        'slide' => 'dam',
        'video' => 'dam',
        'audio' => 'dam',
        'film' => 'dam',
        'map' => 'dam',
        'poster' => 'dam',

        // Universal
        'collection' => 'universal',
    ];

    public static function detect(int $objectId): string
    {
        if ($objectId <= 1) {
            return 'archive';
        }

        $existing = DB::table('display_object_config')
            ->where('object_id', $objectId)
            ->value('object_type');

        if ($existing) {
            return $existing;
        }

        return self::detectAndSave($objectId);
    }

    public static function detectAndSave(int $objectId, bool $force = false): string
    {
        if ($objectId <= 1) {
            return 'archive';
        }

        if ($force) {
            DB::table('display_object_config')->where('object_id', $objectId)->delete();
        }

        $culture = app()->getLocale();
        $object = DB::table('information_object as io')
            ->leftJoin('term_i18n as level', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'level.id')->where('level.culture', '=', $culture);
            })
            ->where('io.id', $objectId)
            ->select('io.*', 'level.name as level_name')
            ->first();

        if (!$object) {
            return 'archive';
        }

        $type = self::detectByDisplayStandard($object->display_standard_id)
            ?? self::detectByLevel($object->level_name)
            ?? self::detectByParent($object->parent_id)
            ?? self::detectByEvents($objectId, $culture)
            ?? self::detectByMediaType($objectId, $culture)
            ?? 'archive';

        self::saveType($objectId, $type);

        return $type;
    }

    /**
     * Detect sector from display_standard_id — most authoritative indicator.
     */
    protected static function detectByDisplayStandard(?int $displayStandardId): ?string
    {
        if (!$displayStandardId) {
            return null;
        }

        // Map display standard term IDs to sectors
        $map = [
            353 => 'archive',  // ISAD(G)
            354 => 'archive',  // Dublin Core
            355 => 'library',  // MODS
            356 => 'archive',  // RAD
            357 => 'archive',  // DACS
            449 => 'museum',   // Museum (CCO)
            1691 => 'dam',     // Photo/DAM (IPTC/XMP)
            1696 => 'gallery', // Gallery (Spectrum 5.0)
            1705 => 'library', // Library (MARC-inspired)
        ];

        return $map[$displayStandardId] ?? null;
    }

    protected static function detectByLevel(?string $levelName): ?string
    {
        if (!$levelName) {
            return null;
        }
        $level = strtolower(trim($levelName));
        return self::$levelToDomain[$level] ?? null;
    }

    protected static function detectByParent(?int $parentId): ?string
    {
        if (!$parentId || $parentId <= 1) {
            return null;
        }

        $parentType = DB::table('display_object_config')
            ->where('object_id', $parentId)
            ->value('object_type');

        if ($parentType && $parentType !== 'universal') {
            return $parentType;
        }

        $grandparentId = DB::table('information_object')
            ->where('id', $parentId)
            ->value('parent_id');

        if ($grandparentId && $grandparentId > 1) {
            return DB::table('display_object_config')
                ->where('object_id', $grandparentId)
                ->value('object_type');
        }

        return null;
    }

    protected static function detectByEvents(int $objectId, string $culture): ?string
    {
        $events = DB::table('event as e')
            ->join('term_i18n as t', function ($j) use ($culture) {
                $j->on('e.type_id', '=', 't.id')->where('t.culture', '=', $culture);
            })
            ->where('e.object_id', $objectId)
            ->pluck('t.name')
            ->map(fn($n) => strtolower($n))
            ->toArray();

        if (in_array('photographer', $events) || in_array('photography', $events)) return 'dam';
        if (in_array('artist', $events) || in_array('painter', $events)) return 'gallery';
        if (in_array('author', $events) || in_array('writer', $events)) return 'library';
        if (in_array('production', $events) || in_array('manufacturer', $events)) return 'museum';

        return null;
    }

    protected static function detectByMediaType(int $objectId, string $culture): ?string
    {
        $mediaType = DB::table('digital_object as do')
            ->join('term_i18n as t', function ($j) use ($culture) {
                $j->on('do.media_type_id', '=', 't.id')->where('t.culture', '=', $culture);
            })
            ->where('do.object_id', $objectId)
            ->value('t.name');

        if (!$mediaType) {
            return null;
        }

        $mediaToDomain = [
            'image' => 'dam',
            'video' => 'dam',
            'audio' => 'dam',
        ];

        return $mediaToDomain[strtolower($mediaType)] ?? null;
    }

    protected static function saveType(int $objectId, string $type): void
    {
        DB::table('display_object_config')->updateOrInsert(
            ['object_id' => $objectId],
            [
                'object_type' => $type,
                'updated_at' => date('Y-m-d H:i:s'),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    public static function getProfile(int $objectId): ?object
    {
        $type = self::detect($objectId);
        $culture = app()->getLocale();

        $profile = DB::table('display_object_profile as dop')
            ->join('display_profile as dp', 'dop.profile_id', '=', 'dp.id')
            ->join('display_profile_i18n as dpi', function ($j) use ($culture) {
                $j->on('dp.id', '=', 'dpi.id')->where('dpi.culture', '=', $culture);
            })
            ->where('dop.object_id', $objectId)
            ->select('dp.*', 'dpi.name', 'dpi.description')
            ->first();

        if (!$profile) {
            $profile = DB::table('display_profile as dp')
                ->join('display_profile_i18n as dpi', function ($j) use ($culture) {
                    $j->on('dp.id', '=', 'dpi.id')->where('dpi.culture', '=', $culture);
                })
                ->where('dp.domain', $type)
                ->where('dp.is_default', 1)
                ->select('dp.*', 'dpi.name', 'dpi.description')
                ->first();
        }

        return $profile;
    }

    public static function getType(int $objectId): string
    {
        return self::detect($objectId);
    }
}
