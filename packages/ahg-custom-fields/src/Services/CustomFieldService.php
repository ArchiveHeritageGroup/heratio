<?php

/**
 * CustomFieldService - Service for Heratio
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



namespace AhgCustomFields\Services;

use Illuminate\Support\Facades\DB;

class CustomFieldService
{
    /**
     * Get all custom field definitions.
     */
    public function getDefinitions(): \Illuminate\Support\Collection
    {
        return DB::table('custom_field_definition')
            ->orderBy('sort_order')
            ->orderBy('field_label')
            ->get();
    }

    /**
     * Get a single definition by ID.
     */
    public function getDefinition(int $id): ?object
    {
        return DB::table('custom_field_definition')->where('id', $id)->first();
    }

    public function createDefinition(array $data): int
    {
        $row = array_intersect_key($data, array_flip([
            'field_key', 'field_label', 'field_type', 'entity_type', 'field_group',
            'dropdown_taxonomy', 'is_required', 'is_searchable', 'is_visible_public',
            'is_visible_edit', 'is_repeatable', 'default_value', 'help_text',
            'validation_rule', 'sort_order', 'is_active',
        ]));
        foreach (['is_required', 'is_searchable', 'is_visible_public', 'is_visible_edit', 'is_repeatable', 'is_active'] as $bool) {
            if (array_key_exists($bool, $row)) {
                $row[$bool] = (int) (bool) $row[$bool];
            }
        }
        $row['created_at'] = now();
        $row['updated_at'] = now();
        return (int) DB::table('custom_field_definition')->insertGetId($row);
    }

    public function updateDefinition(int $id, array $data): bool
    {
        $row = array_intersect_key($data, array_flip([
            'field_key', 'field_label', 'field_type', 'entity_type', 'field_group',
            'dropdown_taxonomy', 'is_required', 'is_searchable', 'is_visible_public',
            'is_visible_edit', 'is_repeatable', 'default_value', 'help_text',
            'validation_rule', 'sort_order', 'is_active',
        ]));
        foreach (['is_required', 'is_searchable', 'is_visible_public', 'is_visible_edit', 'is_repeatable', 'is_active'] as $bool) {
            if (array_key_exists($bool, $row)) {
                $row[$bool] = (int) (bool) $row[$bool];
            }
        }
        $row['updated_at'] = now();
        return DB::table('custom_field_definition')->where('id', $id)->update($row) >= 0;
    }

    public function deleteDefinition(int $id): bool
    {
        DB::table('custom_field_value')->where('definition_id', $id)->delete();
        return DB::table('custom_field_definition')->where('id', $id)->delete() > 0;
    }

    /**
     * Get entity types for the dropdown.
     */
    public function getEntityTypes(): array
    {
        return [
            'information_object' => 'Information Object',
            'actor' => 'Actor / Authority',
            'repository' => 'Repository',
            'accession' => 'Accession',
            'function' => 'Function',
        ];
    }

    /**
     * Get field types for the dropdown.
     */
    public function getFieldTypes(): array
    {
        return [
            'text' => 'Text (single line)',
            'textarea' => 'Text (multi-line)',
            'number' => 'Number',
            'date' => 'Date',
            'boolean' => 'Yes/No',
            'select' => 'Dropdown',
            'multiselect' => 'Multi-select',
            'url' => 'URL',
        ];
    }

    /**
     * Get custom field values for a specific entity.
     */
    public function getValues(string $entityType, int $entityId): \Illuminate\Support\Collection
    {
        return DB::table('custom_field_value')
            ->join('custom_field_definition', 'custom_field_value.definition_id', '=', 'custom_field_definition.id')
            ->where('custom_field_value.entity_type', $entityType)
            ->where('custom_field_value.entity_id', $entityId)
            ->select('custom_field_definition.*', 'custom_field_value.value', 'custom_field_value.id as value_id')
            ->orderBy('custom_field_definition.sort_order')
            ->get();
    }

    /**
     * Get field definitions for a specific entity type.
     */
    public function getFieldsForEntityType(string $entityType): \Illuminate\Support\Collection
    {
        return DB::table('custom_field_definition')
            ->where('entity_type', $entityType)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();
    }
}
