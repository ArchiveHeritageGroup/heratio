<?php

/**
 * PropertyRow - read and write AtoM `property` rows without repeating the
 * three-table dance.
 *
 * A property is class-table-inherited like `status`: an `object` row carrying
 * the id, a `property` row keyed to it, and the value itself in
 * `property_i18n`. Writers across this codebase each open-code that, which is
 * how the id-allocation bug behind [[StatusRow]] happened - one place that
 * allocates the object row means no caller has to remember.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgCore\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropertyRow
{
    /**
     * The value of one named property on an object, or null if absent.
     *
     * Absent and empty are different answers and the caller usually cares:
     * a setting nobody has chosen is not the same as one set to "no".
     */
    public static function get(int $objectId, string $name, ?string $scope = null, ?string $culture = null): ?string
    {
        if (! Schema::hasTable('property')) {
            return null;
        }

        try {
            $q = DB::table('property as p')
                ->join('property_i18n as pi', 'pi.id', '=', 'p.id')
                ->where('p.object_id', $objectId)
                ->where('p.name', $name)
                ->where('pi.culture', $culture ?? app()->getLocale());

            if ($scope !== null) {
                $q->where('p.scope', $scope);
            }

            $v = $q->value('pi.value');

            return $v === null ? null : (string) $v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Set a named property, creating the object/property/i18n trio on first
     * write and updating only the value afterwards.
     */
    public static function set(int $objectId, string $name, string $value, ?string $scope = null, ?string $culture = null): void
    {
        if (! Schema::hasTable('property')) {
            return;
        }

        $culture = $culture ?? app()->getLocale();

        $q = DB::table('property')->where('object_id', $objectId)->where('name', $name);
        if ($scope !== null) {
            $q->where('scope', $scope);
        }
        $existing = $q->value('id');

        if ($existing) {
            DB::table('property_i18n')->updateOrInsert(
                ['id' => $existing, 'culture' => $culture],
                ['value' => $value]
            );

            return;
        }

        // `property` has no timestamps of its own - they live on `object`,
        // which is also what allocates the shared id.
        $id = DB::table('object')->insertGetId([
            'class_name' => 'QubitProperty',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('property')->insert([
            'id' => $id,
            'object_id' => $objectId,
            'name' => $name,
            'scope' => $scope,
            'source_culture' => $culture,
        ]);

        DB::table('property_i18n')->insert([
            'id' => $id,
            'culture' => $culture,
            'value' => $value,
        ]);
    }

    /** Convenience for the flag case, preserving "never set" as a distinct answer. */
    public static function bool(int $objectId, string $name, ?bool $default = null, ?string $scope = null): ?bool
    {
        $v = self::get($objectId, $name, $scope);

        return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }
}
