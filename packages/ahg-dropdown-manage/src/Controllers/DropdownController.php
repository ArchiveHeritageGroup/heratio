<?php

/**
 * DropdownController - Controller for Heratio
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


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DropdownController extends Controller
{
    /**
     * Section labels for taxonomy grouping — matches AtoM ahgDropdownPlugin.
     */
    protected array $sectionLabels = [
        'access_research'          => 'Access & Research',
        'ai'                       => 'AI & Automation',
        'condition'                => 'Condition & Conservation',
        'core'                     => 'Core & System',
        'digital_media'            => 'Digital Assets & Media',
        'display_ui'               => 'Display & UI',
        'donor_agreement'          => 'Donor Agreements',
        'exhibition_loan'          => 'Exhibitions & Loans',
        'export_import'            => 'Export & Import',
        'federation'               => 'Federation',
        'finance'                  => 'Finance',
        'forms_metadata'           => 'Forms & Metadata',
        'heritage_monuments'       => 'Heritage & Monuments',
        'integration'              => 'Integration',
        'people'                   => 'People & Organisations',
        'preservation'             => 'Preservation',
        'privacy_compliance'       => 'Privacy & Compliance',
        'provenance_rights'        => 'Provenance & Rights',
        'reporting_workflow'       => 'Reporting & Workflow',
        'reproduction'             => 'Reproduction',
        'vendor'                   => 'Vendor',
        'other'                    => 'Other',
    ];

    /**
     * Section icons — matches AtoM ahgDropdownPlugin.
     */
    protected array $sectionIcons = [
        'access_research'    => 'fa-book-reader',
        'ai'                 => 'fa-robot',
        'condition'          => 'fa-clipboard-check',
        'core'               => 'fa-cogs',
        'digital_media'      => 'fa-photo-video',
        'display_ui'         => 'fa-desktop',
        'donor_agreement'    => 'fa-handshake',
        'exhibition_loan'    => 'fa-university',
        'export_import'      => 'fa-file-export',
        'federation'         => 'fa-project-diagram',
        'finance'            => 'fa-coins',
        'forms_metadata'     => 'fa-file-alt',
        'heritage_monuments' => 'fa-landmark',
        'integration'        => 'fa-plug',
        'people'             => 'fa-users',
        'preservation'       => 'fa-shield-alt',
        'privacy_compliance' => 'fa-user-shield',
        'provenance_rights'  => 'fa-balance-scale',
        'reporting_workflow' => 'fa-tasks',
        'reproduction'       => 'fa-copy',
        'vendor'             => 'fa-store',
        'other'              => 'fa-folder',
    ];

    /**
     * Index: list all taxonomies grouped by section.
     */
    public function index()
    {
        $rows = DB::table('ahg_dropdown')
            ->select('taxonomy', 'taxonomy_label', 'taxonomy_section')
            ->selectRaw('COUNT(*) as term_count')
            ->where('is_active', 1)
            ->groupBy('taxonomy', 'taxonomy_label', 'taxonomy_section')
            ->orderBy('taxonomy_label')
            ->get();

        // Group by section
        $bySection = [];
        foreach ($rows as $row) {
            $section = $row->taxonomy_section ?: 'other';
            $bySection[$section][] = $row;
        }

        // Sort sections by the defined order
        $orderedSections = [];
        foreach (array_keys($this->sectionLabels) as $key) {
            if (isset($bySection[$key])) {
                $orderedSections[$key] = $bySection[$key];
            }
        }
        // Any sections not in the predefined list go at the end
        foreach ($bySection as $key => $items) {
            if (!isset($orderedSections[$key])) {
                $orderedSections[$key] = $items;
            }
        }

        return view('ahg-dropdown-manage::index', [
            'sectionLabels'   => $this->sectionLabels,
            'sectionIcons'    => $this->sectionIcons,
            'taxonomyGroups'  => $orderedSections,
        ]);
    }

    /**
     * Edit: list all terms for a given taxonomy.
     */
    public function edit(string $taxonomy)
    {
        $terms = DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        if ($terms->isEmpty()) {
            abort(404, 'Taxonomy not found.');
        }

        $taxonomyLabel   = $terms->first()->taxonomy_label;
        $taxonomySection = $terms->first()->taxonomy_section;

        // Get column mappings for this taxonomy from ahg_dropdown_column_map
        $columnMappings = DB::table('ahg_dropdown_column_map')
            ->where('taxonomy', $taxonomy)
            ->orderBy('table_name')
            ->orderBy('column_name')
            ->get();

        return view('ahg-dropdown-manage::edit', [
            'taxonomy'        => $taxonomy,
            'taxonomyLabel'   => $taxonomyLabel,
            'taxonomySection' => $taxonomySection,
            'terms'           => $terms,
            'columnMappings'  => $columnMappings,
            'sectionLabels'   => $this->sectionLabels,
            'sectionIcons'    => $this->sectionIcons,
        ]);
    }

    /**
     * AJAX: Create a new taxonomy with an initial placeholder term.
     */
    public function createTaxonomy(Request $request): JsonResponse
    {
        $request->validate([
            'taxonomy_label' => 'required|string|max:255',
            'taxonomy_code'  => 'required|string|max:100',
            'taxonomy_section' => 'required|string|max:50',
        ]);

        $code = Str::snake(Str::ascii($request->taxonomy_code));

        // Check if taxonomy code already exists
        $exists = DB::table('ahg_dropdown')->where('taxonomy', $code)->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A taxonomy with this code already exists.'], 422);
        }

        DB::table('ahg_dropdown')->insert([
            'taxonomy'         => $code,
            'taxonomy_label'   => $request->taxonomy_label,
            'taxonomy_section' => $request->taxonomy_section,
            'code'             => 'default',
            'label'            => 'Default',
            'color'            => null,
            'icon'             => null,
            'sort_order'       => 0,
            'is_default'       => 1,
            'is_active'        => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Taxonomy created successfully.',
            'taxonomy_code' => $code,
        ]);
    }

    /**
     * AJAX: Rename a taxonomy's display label.
     */
    public function renameTaxonomy(Request $request): JsonResponse
    {
        $request->validate([
            'taxonomy'  => 'required|string|max:100',
            'new_label' => 'required|string|max:255',
        ]);

        $updated = DB::table('ahg_dropdown')
            ->where('taxonomy', $request->taxonomy)
            ->update([
                'taxonomy_label' => $request->new_label,
                'updated_at'     => now(),
            ]);

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Taxonomy not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Taxonomy renamed successfully.']);
    }

    /**
     * AJAX: Delete an entire taxonomy (all its terms).
     */
    public function deleteTaxonomy(Request $request): JsonResponse
    {
        $request->validate([
            'taxonomy' => 'required|string|max:100',
        ]);

        $deleted = DB::table('ahg_dropdown')
            ->where('taxonomy', $request->taxonomy)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['success' => false, 'message' => 'Taxonomy not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Taxonomy and all its terms deleted.']);
    }

    /**
     * AJAX: Move a taxonomy to a different section.
     */
    public function moveSection(Request $request): JsonResponse
    {
        $request->validate([
            'taxonomy' => 'required|string|max:100',
            'section'  => 'required|string|max:50',
        ]);

        $updated = DB::table('ahg_dropdown')
            ->where('taxonomy', $request->taxonomy)
            ->update([
                'taxonomy_section' => $request->section,
                'updated_at'       => now(),
            ]);

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Taxonomy not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Taxonomy moved to new section.']);
    }

    /**
     * AJAX: Add a term to a taxonomy.
     */
    public function addTerm(Request $request): JsonResponse
    {
        $request->validate([
            'taxonomy' => 'required|string|max:100',
            'label'    => 'required|string|max:255',
            'code'     => 'required|string|max:100',
            'color'    => 'nullable|string|max:7',
            'icon'     => 'nullable|string|max:50',
        ]);

        // Check code uniqueness within taxonomy
        $exists = DB::table('ahg_dropdown')
            ->where('taxonomy', $request->taxonomy)
            ->where('code', $request->code)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A term with this code already exists in this taxonomy.'], 422);
        }

        // Get taxonomy metadata from existing terms
        $existing = DB::table('ahg_dropdown')
            ->where('taxonomy', $request->taxonomy)
            ->first();

        if (!$existing) {
            return response()->json(['success' => false, 'message' => 'Taxonomy not found.'], 404);
        }

        // Determine next sort_order
        $maxSort = DB::table('ahg_dropdown')
            ->where('taxonomy', $request->taxonomy)
            ->max('sort_order') ?? -1;

        $id = DB::table('ahg_dropdown')->insertGetId([
            'taxonomy'         => $request->taxonomy,
            'taxonomy_label'   => $existing->taxonomy_label,
            'taxonomy_section' => $existing->taxonomy_section,
            'code'             => $request->code,
            'label'            => $request->label,
            'color'            => $request->color ?: null,
            'icon'             => $request->icon ?: null,
            'sort_order'       => $maxSort + 1,
            'is_default'       => 0,
            'is_active'        => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $term = DB::table('ahg_dropdown')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Term added successfully.',
            'term'    => $term,
        ]);
    }

    /**
     * AJAX: Update a single field on a term.
     */
    public function updateTerm(Request $request): JsonResponse
    {
        $request->validate([
            'id'    => 'required|integer',
            'field' => 'required|string|in:label,color,icon,is_active',
            'value' => 'nullable|string|max:255',
        ]);

        $field = $request->field;
        $value = $request->value;

        // Convert boolean-like values for is_active
        if ($field === 'is_active') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        $updated = DB::table('ahg_dropdown')
            ->where('id', $request->id)
            ->update([
                $field       => $value,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Term not found or no change.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Term updated.']);
    }

    /**
     * AJAX: Delete a term.
     */
    public function deleteTerm(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $term = DB::table('ahg_dropdown')->where('id', $request->id)->first();

        if (!$term) {
            return response()->json(['success' => false, 'message' => 'Term not found.'], 404);
        }

        DB::table('ahg_dropdown')->where('id', $request->id)->delete();

        // If deleted term was the default, set the first remaining term as default
        if ($term->is_default) {
            $first = DB::table('ahg_dropdown')
                ->where('taxonomy', $term->taxonomy)
                ->orderBy('sort_order')
                ->first();
            if ($first) {
                DB::table('ahg_dropdown')
                    ->where('id', $first->id)
                    ->update(['is_default' => 1, 'updated_at' => now()]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Term deleted.']);
    }

    /**
     * AJAX: Reorder terms within a taxonomy.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        foreach ($request->ids as $index => $id) {
            DB::table('ahg_dropdown')
                ->where('id', $id)
                ->update(['sort_order' => $index, 'updated_at' => now()]);
        }

        return response()->json(['success' => true, 'message' => 'Terms reordered.']);
    }

    /**
     * AJAX: Set a term as the default for its taxonomy.
     */
    public function setDefault(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $term = DB::table('ahg_dropdown')->where('id', $request->id)->first();

        if (!$term) {
            return response()->json(['success' => false, 'message' => 'Term not found.'], 404);
        }

        // Clear existing default for this taxonomy
        DB::table('ahg_dropdown')
            ->where('taxonomy', $term->taxonomy)
            ->update(['is_default' => 0, 'updated_at' => now()]);

        // Set the new default
        DB::table('ahg_dropdown')
            ->where('id', $request->id)
            ->update(['is_default' => 1, 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Default term updated.']);
    }
}
