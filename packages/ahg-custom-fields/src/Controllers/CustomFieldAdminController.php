<?php

/**
 * CustomFieldAdminController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

        return view('ahg-custom-fields::admin.edit', compact('definition', 'entityTypes', 'fieldTypes'));
    }

    /**
     * Admin dashboard for custom fields.
     */
    public function admin()
    {
        $definitions = $this->service->getDefinitions();
        $stats = [
            'total' => count($definitions),
            'active' => collect($definitions)->where('is_active', 1)->count(),
        ];

        return view('ahg-custom-fields::admin.dashboard', compact('definitions', 'stats'));
    }

    /**
     * Save a custom field definition.
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'field_type' => 'required|string|max:50',
            'entity_type' => 'required|string|max:50',
        ]);

        $id = $request->get('id');

        if ($id) {
            $this->service->updateDefinition((int) $id, $request->except('_token'));
        } else {
            $id = $this->service->createDefinition($request->except('_token'));
        }

        return redirect()->route('customFields.index')->with('notice', 'Custom field saved.');
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
        $definitions = $this->service->getDefinitions();

        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', 'Name', 'Field Type', 'Entity Type', 'Is Active']);

        foreach ($definitions as $def) {
            fputcsv($output, [
                $def->id ?? '',
                $def->name ?? '',
                $def->field_type ?? '',
                $def->entity_type ?? '',
                $def->is_active ?? 0,
            ]);
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
     * Import custom field definitions from CSV.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($rows);

        $imported = 0;
        foreach ($rows as $row) {
            if (count($row) >= 3) {
                $this->service->createDefinition([
                    'name' => $row[1] ?? '',
                    'field_type' => $row[2] ?? 'text',
                    'entity_type' => $row[3] ?? 'information_object',
                    'is_active' => $row[4] ?? 1,
                ]);
                $imported++;
            }
        }

        return redirect()->route('customFields.index')->with('notice', "{$imported} custom field(s) imported.");
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

    /**
     * Web-facing custom fields view.
     */
    public function web()
    {
        $definitions = $this->service->getDefinitions();

        return view('ahg-custom-fields::web', compact('definitions'));
    }
}
