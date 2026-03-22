<?php

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
