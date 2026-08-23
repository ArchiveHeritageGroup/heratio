<?php

/**
 * CcoFields - the Cataloguing Cultural Objects fields on museum_metadata.
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

namespace AhgCore\Support;

/**
 * One list, used by both sector services - #1478.
 *
 * The gallery and museum edit forms both write `museum_metadata`, and each
 * carried its OWN inline whitelist of the columns to persist. Thirty CCO
 * fields were in neither, so the forms collected them and threw them away
 * without an error. Two hand-maintained copies of one list is precisely how
 * that happens, and is the same shape as the three divergent loan-rule
 * lookups fixed in v1.154.634.
 *
 * Adding a CCO field is now: add the column in a migration, add its name here,
 * and add the input to the form under the same name. The names must match the
 * columns exactly - divergence between a form field and its column is the root
 * cause of the whole #1478 defect class, and matching them is what lets
 * tools/scan-blade-bindings.php prove these stay wired.
 */
final class CcoFields
{
    /**
     * Fields added in 2026_08_23_000300 - previously discarded on save.
     * All TEXT: museum_metadata is at 98% of InnoDB's row-size limit and a
     * VARCHAR counts against it in full. See the migration for the detail.
     */
    public const ADDED = [
        'work_type_qualifier',        // CCO 2.1.1
        'components_count',           // CCO 2.2
        'title_language',             // CCO 3.1.2
        'creator_display',            // CCO 4.1
        'attribution_qualifier',      // CCO 4.1.2
        'school_group',               // CCO 5.3
        'dimensions_display',         // CCO 6.1  - marked required in the form
        'height_value',               // CCO 6.2
        'width_value',                // CCO 6.2
        'depth_value',                // CCO 6.2
        'weight_value',               // CCO 6.3
        'dimension_notes',            // CCO 6.4
        'materials_display',          // CCO 7.1  - marked required in the form
        'subjects_depicted',          // CCO 8.2
        'iconography',                // CCO 8.3
        'named_subjects',             // CCO 8.4
        'impression_quality',         // CCO 10.4
        'condition_summary',          // CCO 12.1
        'location_within_repository', // CCO 13.2
    ];

    /**
     * The added fields as a `column => value` array ready to merge into a
     * service's existing museum_metadata write. Absent keys become null, which
     * matches how the surrounding whitelists already behave.
     */
    public static function values(array $data): array
    {
        $out = [];
        foreach (self::ADDED as $field) {
            $out[$field] = $data[$field] ?? null;
        }

        return $out;
    }

    /**
     * Validation rules. Every one of these is a text or textarea input and the
     * measurement fields carry their units ("45.5 cm"), so none is numeric or
     * date-typed - a stricter rule here would reject what the form legitimately
     * sends and reintroduce silent data loss by another route.
     */
    public static function rules(): array
    {
        $rules = [];
        foreach (self::ADDED as $field) {
            $rules[$field] = 'nullable|string|max:65535';
        }

        return $rules;
    }

    /** Qualified for a SELECT, e.g. `museum_metadata.iconography`. */
    public static function select(string $table = 'museum_metadata'): array
    {
        return array_map(static fn ($f) => $table . '.' . $f, self::ADDED);
    }
}
