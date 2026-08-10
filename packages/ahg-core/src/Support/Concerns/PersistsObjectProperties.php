<?php

/**
 * PersistsObjectProperties - shared property / property_i18n writers for standard editors.
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

namespace AhgCore\Support\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Upsert an object's `property` / `property_i18n` rows. The two writers
 * were copy-pasted verbatim into the description-standard editors:
 * saveSerializedProperty() in all four (Dacs/Dc/Mods/Rad) and
 * saveProperty() in Dacs + Rad. They now live here once.
 */
trait PersistsObjectProperties
{
    /**
     * Upsert a serialized (multi-value) property. Stores
     * serialize(array_values(array_filter($values))) in property_i18n.
     */
    private function saveSerializedProperty(int $objectId, string $name, array $values, string $culture): void
    {
        $serialized = serialize(array_values(array_filter($values)));

        $existing = DB::table('property')
            ->where('object_id', $objectId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            DB::table('property_i18n')
                ->where('id', $existing->id)
                ->where('culture', $culture)
                ->update(['value' => $serialized]);
        } elseif (! empty($values)) {
            $propId = DB::table('property')->insertGetId([
                'object_id' => $objectId,
                'name' => $name,
                'source_culture' => $culture,
            ]);
            DB::table('property_i18n')->insert([
                'id' => $propId,
                'culture' => $culture,
                'value' => $serialized,
            ]);
        }
    }

    /**
     * Upsert a single scalar property value.
     */
    private function saveProperty(int $objectId, string $name, ?string $value, string $culture): void
    {
        $existing = DB::table('property')
            ->where('object_id', $objectId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            DB::table('property_i18n')
                ->where('id', $existing->id)
                ->where('culture', $culture)
                ->update(['value' => $value]);
        } elseif ($value !== null && $value !== '') {
            $propId = DB::table('property')->insertGetId([
                'object_id' => $objectId,
                'name' => $name,
                'source_culture' => $culture,
            ]);
            DB::table('property_i18n')->insert([
                'id' => $propId,
                'culture' => $culture,
                'value' => $value,
            ]);
        }
    }
}
