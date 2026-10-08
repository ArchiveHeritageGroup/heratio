<?php

/**
 * CustomFieldAdminController - Controller for Heratio
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

namespace AhgCustomFields\Controllers;

use AhgCustomFields\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomFieldAdminController extends Controller
{
    public function __construct(
        protected CustomFieldService $service
    ) {}

    /**
     * List all custom field definitions.
     */
    public function index()
    {
        $definitions = $this->service->getDefinitions();

        return view('ahg-custom-fields::admin.index', compact('definitions'));
    }

    /**
     * Edit/create a custom field definition.
     */
    public function edit(?int $id = null)
    {
        $definition = $id ? $this->service->getDefinition($id) : null;
        $entityTypes = $this->service->getEntityTypes();
        $fieldTypes = $this->service->getFieldTypes();
        $dropdownTaxonomies = $this->service->getDropdownTaxonomies();

        return view('ahg-custom-fields::admin.edit', compact('definition', 'entityTypes', 'fieldTypes', 'dropdownTaxonomies'));
    }

    /**
     * Save a custom field definition.
     */
    public function save(Request $request)
    {
        $id = $request->filled('id') ? (int) $request->input('id') : null;
        $validated = $request->validate([
            'field_label' => 'required|string|max:255',
            'field_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'field_type' => ['required', \Illuminate\Validation\Rule::in(array_keys($this->service->getFieldTypes()))],
            'entity_type' => ['required', \Illuminate\Validation\Rule::in(array_keys($this->service->getEntityTypes()))],
            'dropdown_taxonomy' => ['nullable', 'string', 'max:100', 'required_if:field_type,dropdown,multiselect'],
            'field_group' => 'nullable|string|max:100',
            'help_text' => 'nullable|string|max:500',
            'default_value' => 'nullable|string|max:500',
            'validation_rule' => ['nullable', 'string', 'max:255', 'regex:/^(max:\d+|regex:.+)$/'],
            'sort_order' => 'nullable|integer|min:0',
            'is_required' => 'boolean', 'is_active' => 'boolean', 'is_repeatable' => 'boolean',
            'is_visible_edit' => 'boolean', 'is_visible_public' => 'boolean',
            'include_in_export' => 'boolean', 'is_searchable' => 'boolean',
        ]);
        $validated['field_key'] = $this->service->generateFieldKey(($validated['field_key'] ?? '') ?: $validated['field_label']);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        if ($validated['field_key'] === '') {
            return back()->withInput()->withErrors(['field_key' => __('The field key cannot be made from this label; enter one.')]);
        }
        if (! $this->service->isKeyUnique($validated['field_key'], $validated['entity_type'], $id)) {
            return back()->withInput()->withErrors(['field_key' => __('A field with this key already exists for this entity type.')]);
        }

        if ($id) {
            $this->service->updateDefinition($id, $validated);
        } else {
            $this->service->createDefinition($validated);
        }

        return redirect()->route('customFields.index')->with('notice', __('Custom field saved.'));
    }

    /**
     * Delete a custom field definition.
     */
    public function delete(int $id)
    {
        $this->service->deleteDefinition($id);

        return redirect()->route('customFields.index')->with('notice', 'Custom field deleted.');
    }

    /**
     * Export custom field definitions.
     */
    public function export()
    {
        $columns = ['field_key', 'field_label', 'field_type', 'entity_type', 'field_group', 'dropdown_taxonomy',
            'is_required', 'is_repeatable', 'is_visible_edit', 'is_visible_public', 'include_in_export', 'is_searchable',
            'default_value', 'help_text', 'validation_rule', 'sort_order', 'is_active'];

        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, $columns);
        foreach ($this->service->getDefinitions() as $def) {
            fputcsv($output, array_map(fn ($c) => $def->{$c} ?? '', $columns));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="custom_fields_export.csv"',
        ]);
    }

    /**
     * Import custom field definitions from a CSV in the export's format.
     * Existing keys (per entity type) are skipped, not overwritten.
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $rows = array_map('str_getcsv', file($request->file('file')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), array_shift($rows) ?? []);
        $fieldTypes = array_keys($this->service->getFieldTypes());
        $entityTypes = array_keys($this->service->getEntityTypes());

        $imported = 0;
        foreach ($rows as $row) {
            if (count($row) !== count($header)) {
                continue;
            }
            $data = array_combine($header, $row);
            $data['entity_type'] = \AhgCustomFields\Services\CustomFieldService::normaliseEntity((string) ($data['entity_type'] ?? ''));
            $data['field_key'] = $this->service->generateFieldKey((string) (($data['field_key'] ?? '') ?: ($data['field_label'] ?? '')));
            if ($data['field_key'] === '' || trim((string) ($data['field_label'] ?? '')) === ''
                || ! in_array($data['field_type'] ?? '', $fieldTypes, true) || ! in_array($data['entity_type'], $entityTypes, true)
                || ! $this->service->isKeyUnique($data['field_key'], $data['entity_type'])) {
                continue;
            }
            $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
            $this->service->createDefinition(array_filter($data, fn ($v) => $v !== null));
            $imported++;
        }

        return redirect()->route('customFields.index')->with('notice', __(':count custom field(s) imported.', ['count' => $imported]));
    }

    /**
     * Reorder custom field definitions.
     */
    public function reorder(Request $request)
    {
        $order = $request->input('order', []);

        foreach ($order as $item) {
            $this->service->updateDefinition((int) $item['id'], ['sort_order' => $item['sort']]);
        }

        return response()->json(['success' => true]);
    }
}
