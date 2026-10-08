<?php

/**
 * EmitsCustomFields - admin-defined custom field values for the metadata
 * exporters (heratio#1548, following #1530).
 *
 * One source for every serializer: CustomFieldService::exportValuesFor(),
 * which returns only fields marked for export, as object id => list of
 * ['key', 'label', 'value']. Empty when ahg-custom-fields is not installed.
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

namespace AhgMetadataExport\Services\Exporters\Concerns;

trait EmitsCustomFields
{
    /** @return array<int, list<array{key: string, label: string, value: string}>> */
    protected function customFields(array $objectIds): array
    {
        if (! class_exists(\AhgCustomFields\Services\CustomFieldService::class)) {
            return [];
        }
        try {
            return app(\AhgCustomFields\Services\CustomFieldService::class)->exportValuesFor($objectIds);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** EAD <odd> blocks, one per field; EAD3 and EAD4 use localtype for the key. */
    protected function customFieldsOdd(array $fields, string $indent, bool $localtype = false): string
    {
        $e = fn (string $v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '';
        foreach ($fields as $f) {
            $xml .= $indent.'<odd '.($localtype ? 'localtype' : 'type').'="'.$e($f['key']).'"><head>'.$e($f['label'])
                .'</head><p>'.$e($f['value'])."</p></odd>\n";
        }

        return $xml;
    }
}
