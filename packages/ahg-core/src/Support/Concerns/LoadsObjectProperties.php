<?php

/**
 * LoadsObjectProperties - shared property / property_i18n readers for standard editors.
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

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read an object's property / property_i18n values (the read counterpart to
 * PersistsObjectProperties). loadSerializedProperty() was byte-identical in
 * all five standard editors (Dacs/Dc/Mods/Rad/Ric) and loadProperty() in
 * Dacs/Rad/Ric. Kept separate from the writer trait because ahg-ric-manage
 * has its own (different) save methods but the same readers.
 */
trait LoadsObjectProperties
{
    /**
     * Read a serialized (multi-value) property as a Collection.
     */
    private function loadSerializedProperty(int $objectId, string $name, string $culture): Collection
    {
        $raw = DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $objectId)
            ->where('property.name', $name)
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');

        if ($raw) {
            $decoded = @unserialize($raw, ['allowed_classes' => false]);
            if (is_array($decoded)) {
                return collect($decoded);
            }
        }

        return collect();
    }

    /**
     * Read a single scalar property value.
     */
    private function loadProperty(int $objectId, string $name, string $culture): ?string
    {
        return DB::table('property')
            ->join('property_i18n', 'property.id', '=', 'property_i18n.id')
            ->where('property.object_id', $objectId)
            ->where('property.name', $name)
            ->where('property_i18n.culture', $culture)
            ->value('property_i18n.value');
    }
}
