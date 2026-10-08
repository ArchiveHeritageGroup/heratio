<?php

/**
 * CustomFieldService - admin-defined metadata fields (EAV) for Heratio
 *
 * Definitions live in custom_field_definition, values in custom_field_value
 * (one typed column per field type, `sequence` for repeatable fields), the
 * schema of the package's install.sql. heratio#1530: the service now matches
 * that schema, values are saved from the description edit forms, shown on the
 * show page and carried into the finding aid and exports.
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

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomFieldService
{
    /** Canonical entity key for archival descriptions (AtoM's spelling). */
    public const IO = 'informationobject';

    /** Older spellings written by earlier Heratio admin forms. */
    private const ALIASES = ['information_object' => self::IO];

    /** Hidden input the edit partial emits; no marker, no save (API and other forms). */
    public const FORM_MARKER = 'cf_present';

    private const DEFINITION_COLUMNS = [
        'field_key', 'field_label', 'field_type', 'entity_type', 'field_group',
        'dropdown_taxonomy', 'is_required', 'is_searchable', 'is_visible_public',
        'is_visible_edit', 'is_repeatable', 'include_in_export', 'default_value', 'help_text',
        'validation_rule', 'sort_order', 'is_active',
    ];

    private const BOOLEAN_COLUMNS = [
        'is_required', 'is_searchable', 'is_visible_public', 'is_visible_edit',
        'is_repeatable', 'include_in_export', 'is_active',
    ];

    public static function normaliseEntity(string $entityType): string
    {
        return self::ALIASES[$entityType] ?? $entityType;
    }

    /** Every spelling stored for an entity type. */
    private static function entitySpellings(string $entityType): array
    {
        $canonical = self::normaliseEntity($entityType);

        return array_values(array_unique(array_merge([$canonical], array_keys(self::ALIASES, $canonical, true))));
    }

    // ----------------------------------------------------------------
    // Definitions
    // ----------------------------------------------------------------

    public function getDefinitions(): Collection
    {
        return DB::table('custom_field_definition')
            ->orderBy('entity_type')
            ->orderBy('sort_order')
            ->orderBy('field_label')
            ->get();
    }

    public function getDefinition(int $id): ?object
    {
        return DB::table('custom_field_definition')->where('id', $id)->first();
    }

    /** Active definitions for an entity type, in display order. */
    public function getFieldsForEntityType(string $entityType): Collection
    {
        return DB::table('custom_field_definition')
            ->whereIn('entity_type', self::entitySpellings($entityType))
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function createDefinition(array $data): int
    {
        $row = $this->definitionRow($data);
        $row['created_at'] = now();
        $row['updated_at'] = now();

        return (int) DB::table('custom_field_definition')->insertGetId($row);
    }

    public function updateDefinition(int $id, array $data): bool
    {
        $row = $this->definitionRow($data);
        $row['updated_at'] = now();

        return DB::table('custom_field_definition')->where('id', $id)->update($row) >= 0;
    }

    private function definitionRow(array $data): array
    {
        $row = array_intersect_key($data, array_flip(self::DEFINITION_COLUMNS));
        foreach (self::BOOLEAN_COLUMNS as $bool) {
            if (array_key_exists($bool, $row)) {
                $row[$bool] = (int) (bool) $row[$bool];
            }
        }
        if (isset($row['entity_type'])) {
            $row['entity_type'] = self::normaliseEntity((string) $row['entity_type']);
        }
        if (array_key_exists('field_key', $row)) {
            $row['field_key'] = $this->generateFieldKey((string) ($row['field_key'] ?: ($data['field_label'] ?? '')));
        }

        return $row;
    }

    public function deleteDefinition(int $id): bool
    {
        DB::table('custom_field_value')->where('field_definition_id', $id)->delete();

        return DB::table('custom_field_definition')->where('id', $id)->delete() > 0;
    }

    public function isKeyUnique(string $fieldKey, string $entityType, ?int $exceptId = null): bool
    {
        return ! DB::table('custom_field_definition')
            ->where('field_key', $fieldKey)
            ->whereIn('entity_type', self::entitySpellings($entityType))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    public function generateFieldKey(string $label): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($label))), '_');

        return substr($key, 0, 100);
    }

    public function getEntityTypes(): array
    {
        return [
            self::IO => 'Information Object',
            'actor' => 'Actor / Authority Record',
            'accession' => 'Accession',
            'repository' => 'Repository',
            'donor' => 'Donor',
            'function' => 'Function',
        ];
    }

    public function getFieldTypes(): array
    {
        return [
            'text' => 'Text (single line)',
            'textarea' => 'Text (multi-line)',
            'number' => 'Number',
            'date' => 'Date',
            'boolean' => 'Yes/No',
            'dropdown' => 'Dropdown (from the Dropdown Manager)',
            'multiselect' => 'Multi-select (from the Dropdown Manager)',
            'url' => 'URL',
        ];
    }

    /** Dropdown Manager taxonomies a dropdown field can draw from. */
    public function getDropdownTaxonomies(): Collection
    {
        return DB::table('ahg_dropdown')
            ->where('is_active', 1)
            ->select('taxonomy', 'taxonomy_label')
            ->distinct()
            ->orderBy('taxonomy_label')
            ->get();
    }

    /** code => label for one Dropdown Manager taxonomy. */
    public function getDropdownOptions(?string $taxonomy): array
    {
        if (! $taxonomy) {
            return [];
        }

        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'code')
            ->all();
    }

    // ----------------------------------------------------------------
    // Values
    // ----------------------------------------------------------------

    /**
     * Definitions for an entity type with this object's values attached as
     * `value` (scalar, or a list for repeatable and multi-select fields).
     */
    public function fieldsWithValues(string $entityType, ?int $objectId, bool $publicOnly = false): Collection
    {
        $defs = $this->getFieldsForEntityType($entityType)
            ->when($publicOnly, fn ($c) => $c->where('is_visible_public', 1));
        $values = $objectId ? $this->valuesByDefinition($objectId, $defs->pluck('id')->all()) : [];

        return $defs->map(function ($def) use ($values) {
            $raw = array_map(fn ($row) => $this->extractValue($def, $row), $values[$def->id] ?? []);
            $def->value = ($def->is_repeatable || $def->field_type === 'multiselect')
                ? array_values(array_merge(...array_map(fn ($v) => (array) $v, $raw ?: [[]])))
                : ($raw[0] ?? null);

            return $def;
        })->values();
    }

    /** @return array<int, array<int, object>> definition id => value rows */
    private function valuesByDefinition(int $objectId, array $definitionIds): array
    {
        if (! $definitionIds) {
            return [];
        }

        return DB::table('custom_field_value')
            ->where('object_id', $objectId)
            ->whereIn('field_definition_id', $definitionIds)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->groupBy('field_definition_id')
            ->map(fn ($rows) => $rows->all())
            ->all();
    }

    private function extractValue(object $def, object $row): mixed
    {
        return match ($def->field_type) {
            'number' => $row->value_number === null ? null : rtrim(rtrim((string) $row->value_number, '0'), '.'),
            'date' => $row->value_date,
            'boolean' => $row->value_boolean === null ? null : (bool) $row->value_boolean,
            'dropdown' => $row->value_dropdown,
            'multiselect' => is_array($d = json_decode((string) $row->value_text, true)) ? $d : [],
            default => $row->value_text,
        };
    }

    /**
     * Human-readable values for output (show page, finding aid, exports):
     * object id => list of ['key', 'label', 'value'], value a display string.
     * Only fields flagged include_in_export when $forExport is set.
     *
     * @param  array<int, int>  $objectIds
     * @return array<int, array<int, array{key: string, label: string, value: string}>>
     */
    public function exportValuesFor(array $objectIds, string $entityType = self::IO, bool $forExport = true): array
    {
        $objectIds = array_values(array_filter(array_map('intval', $objectIds)));
        if (! $objectIds) {
            return [];
        }
        $defs = $this->getFieldsForEntityType($entityType)
            ->when($forExport, fn ($c) => $c->where('include_in_export', 1))
            ->keyBy('id');
        if ($defs->isEmpty()) {
            return [];
        }

        $rows = DB::table('custom_field_value')
            ->whereIn('object_id', $objectIds)
            ->whereIn('field_definition_id', $defs->keys()->all())
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        $labels = [];
        $out = [];
        foreach ($defs as $def) {
            foreach ($rows->where('field_definition_id', $def->id)->groupBy('object_id') as $objectId => $defRows) {
                $parts = [];
                foreach ($defRows as $row) {
                    foreach ((array) $this->extractValue($def, $row) as $v) {
                        if ($v === null || $v === '') {
                            continue;
                        }
                        if (in_array($def->field_type, ['dropdown', 'multiselect'], true)) {
                            $labels[$def->dropdown_taxonomy] ??= $this->getDropdownOptions($def->dropdown_taxonomy);
                            $v = $labels[$def->dropdown_taxonomy][$v] ?? $v;
                        } elseif ($def->field_type === 'boolean') {
                            $v = $v ? __('Yes') : __('No');
                        }
                        $parts[] = (string) $v;
                    }
                }
                if ($parts) {
                    $out[(int) $objectId][] = ['key' => $def->field_key, 'label' => $def->field_label, 'value' => implode('; ', $parts)];
                }
            }
        }

        return $out;
    }

    /** One object's output values (see exportValuesFor). */
    public function exportValues(int $objectId, string $entityType = self::IO, bool $forExport = true): array
    {
        return $this->exportValuesFor([$objectId], $entityType, $forExport)[$objectId] ?? [];
    }

    /**
     * Validate the cf[...] inputs a form posted; throws a ValidationException
     * keyed cf.<field_key> so the form shows the message under the field.
     */
    public static function validateRequest(Request $request, string $entityType = self::IO): void
    {
        if (! $request->has(self::FORM_MARKER)) {
            return;
        }
        $svc = app(self::class);
        $input = (array) $request->input('cf', []);
        $errors = [];
        foreach ($svc->getFieldsForEntityType($entityType)->where('is_visible_edit', 1) as $def) {
            foreach ((array) ($input[$def->field_key] ?? null) ?: [null] as $value) {
                $result = $svc->validateValue($def, is_string($value) ? trim($value) : $value);
                if ($result !== true) {
                    $errors['cf.'.$def->field_key] = $result;
                    break;
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Save the cf[...] inputs a form posted for one object. */
    public static function saveFromRequest(int $objectId, Request $request, string $entityType = self::IO): void
    {
        if (! $request->has(self::FORM_MARKER)) {
            return;
        }
        app(self::class)->saveValues($objectId, $entityType, (array) $request->input('cf', []));
    }

    /**
     * Save values keyed by field_key. A field the form did not post is left
     * alone, except a boolean (an unticked checkbox posts nothing). A blank
     * value removes the stored one rather than keeping an empty row.
     */
    public function saveValues(int $objectId, string $entityType, array $fieldValues): void
    {
        foreach ($this->getFieldsForEntityType($entityType)->where('is_visible_edit', 1) as $def) {
            if (! array_key_exists($def->field_key, $fieldValues) && $def->field_type !== 'boolean') {
                continue;
            }
            $raw = $fieldValues[$def->field_key] ?? null;
            if ($def->field_type === 'multiselect') {
                $raw = [$raw];   // one row holding the selected codes
            } elseif (! $def->is_repeatable || ! is_array($raw)) {
                $raw = [is_array($raw) ? reset($raw) : $raw];
            }

            DB::table('custom_field_value')->where('field_definition_id', $def->id)->where('object_id', $objectId)->delete();
            $sequence = 0;
            foreach ($raw as $value) {
                $row = $this->buildValueData($def, $value);
                if ($row === null) {
                    continue;
                }
                DB::table('custom_field_value')->insert($row + [
                    'field_definition_id' => $def->id, 'object_id' => $objectId,
                    'sequence' => $sequence++, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /** Typed value columns for one value, or null when there is nothing to store. */
    private function buildValueData(object $def, mixed $raw): ?array
    {
        $data = ['value_text' => null, 'value_number' => null, 'value_date' => null, 'value_boolean' => null, 'value_dropdown' => null];
        if ($def->field_type === 'boolean') {
            $data['value_boolean'] = $raw ? 1 : 0;

            return $data;
        }
        if ($def->field_type === 'multiselect') {
            $codes = array_values(array_filter(array_map(fn ($v) => trim((string) $v), (array) $raw), fn ($v) => $v !== ''));
            $data['value_text'] = $codes ? json_encode($codes) : null;

            return $codes ? $data : null;
        }
        $raw = is_scalar($raw) ? trim((string) $raw) : '';
        if ($raw === '') {
            return null;
        }
        match ($def->field_type) {
            'number' => $data['value_number'] = is_numeric($raw) ? (float) $raw : null,
            'date' => $data['value_date'] = $raw,
            'dropdown' => $data['value_dropdown'] = $raw,
            default => $data['value_text'] = $raw,
        };

        return array_filter($data, fn ($v) => $v !== null) ? $data : null;
    }

    /** True, or the error message for one value (AtoM's rules). */
    public function validateValue(object $def, mixed $value): bool|string
    {
        if (is_array($value)) {
            $value = array_filter($value, fn ($v) => $v !== '' && $v !== null) ? $value : '';
        }
        if ($def->is_required && $def->field_type !== 'boolean' && ($value === null || $value === '')) {
            return __(':field is required.', ['field' => $def->field_label]);
        }
        if ($value === null || $value === '' || is_array($value)) {
            return true;
        }
        $value = (string) $value;
        switch ($def->field_type) {
            case 'number':
                if (! is_numeric($value)) {
                    return __(':field must be a number.', ['field' => $def->field_label]);
                }
                break;
            case 'date':
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return __(':field must be a valid date (YYYY-MM-DD).', ['field' => $def->field_label]);
                }
                break;
            case 'url':
                if (! filter_var($value, FILTER_VALIDATE_URL)) {
                    return __(':field must be a valid URL.', ['field' => $def->field_label]);
                }
                break;
        }
        $rule = (string) ($def->validation_rule ?? '');
        if (preg_match('/^max:(\d+)$/', $rule, $m) && mb_strlen($value) > (int) $m[1]) {
            return __(':field must be at most :max characters.', ['field' => $def->field_label, 'max' => $m[1]]);
        }
        if (preg_match('/^regex:(.+)$/', $rule, $m) && @preg_match($m[1], $value) === 0) {
            return __(':field does not match the required format.', ['field' => $def->field_label]);
        }

        return true;
    }
}
