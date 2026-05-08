<?php

/**
 * DropdownController - Controller for Heratio
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



namespace AhgDropdownManage\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * Edit: list all terms for a given source + taxonomy.
     *
     * Issue #59 Phase 3 - source dispatcher pattern. {source} is one of
     * 'ahg_dropdown', 'term', 'setting'. The view receives a normalised
     * shape regardless of source so the side-by-side editor blade can
     * iterate rows with $row->id, $row->code, $row->label, $row->sort_order
     * and post per-row saves to /admin/dropdowns/{source}/{id}/i18n.
     */
    public function edit(string $source, string $taxonomy)
    {
        $payload = match ($source) {
            'ahg_dropdown' => $this->loadAhgDropdownTaxonomy($taxonomy),
            'term'         => $this->loadTermTaxonomy((int) $taxonomy),
            'setting'      => $this->loadSettingScope($taxonomy),
            default        => abort(404, 'Unknown dropdown source.'),
        };

        if (empty($payload['terms']) || $payload['terms']->isEmpty()) {
            abort(404, 'Taxonomy not found in source ' . $source);
        }

        return view('ahg-dropdown-manage::edit', array_merge([
            'source'          => $source,
            'taxonomy'        => $taxonomy,
            'sectionLabels'   => $this->sectionLabels,
            'sectionIcons'    => $this->sectionIcons,
            'enabledLocales'  => $this->getEnabledLocales(),
            'currentLocale'   => (string) app()->getLocale(),
        ], $payload));
    }

    /**
     * Issue #59 Phase 3 - source loader for ahg_dropdown.
     *
     * The existing edit page shows ALL terms (active + inactive, behind a
     * "Show inactive" toggle) and lets admins flip is_active per row. The
     * Tier 1 helper getDropdownChoicesWithAttributes filters to active-only,
     * which suits show-page rendering but not this admin editor. So this
     * loader runs its own query that includes inactive rows + the same i18n
     * COALESCE chain as the helper.
     */
    protected function loadAhgDropdownTaxonomy(string $taxonomy): array
    {
        $culture = (string) app()->getLocale();
        $hasI18n = Schema::hasTable('ahg_dropdown_i18n');

        $q = DB::table('ahg_dropdown as d')->where('d.taxonomy', $taxonomy);
        if ($hasI18n) {
            $q->leftJoin('ahg_dropdown_i18n as di_cur', function ($j) use ($culture) {
                $j->on('di_cur.id', '=', 'd.id')->where('di_cur.culture', '=', $culture);
            });
            $q->leftJoin('ahg_dropdown_i18n as di_fb', function ($j) {
                $j->on('di_fb.id', '=', 'd.id')->where('di_fb.culture', '=', 'en');
            });
            $q->select(
                'd.id', 'd.code', 'd.taxonomy', 'd.taxonomy_label', 'd.taxonomy_section',
                'd.color', 'd.icon', 'd.sort_order', 'd.is_default', 'd.is_active', 'd.metadata',
                DB::raw("COALESCE(NULLIF(di_cur.label, ''), NULLIF(di_fb.label, ''), d.label) AS label"),
                DB::raw("d.label AS source_label")
            );
        } else {
            $q->select('d.*', DB::raw('d.label AS source_label'));
        }
        $terms = $q->orderBy('d.sort_order')->orderBy('d.label')->get();

        if ($terms->isEmpty()) {
            return ['terms' => collect()];
        }

        $first = $terms->first();
        $columnMappings = DB::table('ahg_dropdown_column_map')
            ->where('taxonomy', $taxonomy)
            ->orderBy('table_name')
            ->orderBy('column_name')
            ->get();

        return [
            'terms'           => $terms,
            'taxonomyLabel'   => $first->taxonomy_label ?? $taxonomy,
            'taxonomySection' => $first->taxonomy_section ?? null,
            'columnMappings'  => $columnMappings,
        ];
    }

    /**
     * Issue #59 Phase 3 - source loader for term + term_i18n (AtoM base).
     * Read-only in the Dropdown Manager. {taxonomy} is the numeric taxonomy_id.
     */
    protected function loadTermTaxonomy(int $taxonomyId): array
    {
        if (!Schema::hasTable('term') || !Schema::hasTable('term_i18n')) {
            return ['terms' => collect()];
        }
        $culture = (string) app()->getLocale();
        $terms = DB::table('term as t')
            ->where('t.taxonomy_id', $taxonomyId)
            ->leftJoin('term_i18n as ti_cur', function ($j) use ($culture) {
                $j->on('ti_cur.id', '=', 't.id')->where('ti_cur.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ti_fb', function ($j) {
                $j->on('ti_fb.id', '=', 't.id')->where('ti_fb.culture', '=', 'en');
            })
            ->select(
                't.id', 't.code', 't.source_culture', 't.taxonomy_id',
                DB::raw("COALESCE(NULLIF(ti_cur.name, ''), NULLIF(ti_fb.name, ''), '') AS label"),
                DB::raw("COALESCE(ti_fb.name, '') AS source_label")
            )
            ->orderBy('t.id')
            ->get();

        $taxonomyName = DB::table('taxonomy_i18n')
            ->where('id', $taxonomyId)->where('culture', 'en')
            ->value('name');

        return [
            'terms'           => $terms,
            'taxonomyLabel'   => $taxonomyName ?? ('Taxonomy #' . $taxonomyId),
            'taxonomySection' => 'atom',
            'columnMappings'  => collect(),
        ];
    }

    /**
     * Issue #59 Phase 3 - source loader for setting + setting_i18n (AtoM base,
     * scope='ui_label' or any other named scope).
     */
    protected function loadSettingScope(string $scope): array
    {
        if (!Schema::hasTable('setting') || !Schema::hasTable('setting_i18n')) {
            return ['terms' => collect()];
        }
        $culture = (string) app()->getLocale();
        $terms = DB::table('setting as s')
            ->where('s.scope', $scope)
            ->leftJoin('setting_i18n as si_cur', function ($j) use ($culture) {
                $j->on('si_cur.id', '=', 's.id')->where('si_cur.culture', '=', $culture);
            })
            ->leftJoin('setting_i18n as si_fb', function ($j) {
                $j->on('si_fb.id', '=', 's.id')->where('si_fb.culture', '=', 'en');
            })
            ->select(
                's.id', 's.name as code', 's.scope', 's.editable',
                DB::raw("COALESCE(NULLIF(si_cur.value, ''), NULLIF(si_fb.value, ''), '') AS label"),
                DB::raw("COALESCE(si_fb.value, '') AS source_label")
            )
            ->orderBy('s.name')
            ->get();

        return [
            'terms'           => $terms,
            'taxonomyLabel'   => $scope,
            'taxonomySection' => 'atom',
            'columnMappings'  => collect(),
        ];
    }

    /**
     * Issue #59 Phase 3 - list of enabled locales for the Culture filter.
     * Reads from setting scope='i18n_languages' (set by the Languages admin
     * page). Falls back to the lang/*.json filenames if the setting table
     * is empty. Always includes 'en' as the source culture.
     */
    protected function getEnabledLocales(): array
    {
        $locales = [];
        try {
            if (Schema::hasTable('setting')) {
                $locales = DB::table('setting')
                    ->where('scope', 'i18n_languages')
                    ->where('editable', 1)
                    ->pluck('name')
                    ->toArray();
            }
        } catch (\Throwable $e) {}
        if (empty($locales)) {
            $files = glob(base_path('lang/*.json')) ?: [];
            $locales = array_map(fn ($f) => pathinfo($f, PATHINFO_FILENAME), $files);
        }
        if (!in_array('en', $locales, true)) array_unshift($locales, 'en');
        return array_values(array_unique($locales));
    }

    /**
     * Issue #59 Phase 3 - per-row save to the source's _i18n table.
     *
     * POST /admin/dropdowns/{source}/{id}/i18n with {culture, label}.
     * Admin auto-applies; editor (acl:translate) queues a draft into
     * ahg_translation_draft so a second admin reviews on /admin/translation/drafts.
     * Returns JSON for the inline-save flow on the editor blade.
     */
    public function saveI18n(string $source, int $id, Request $request): JsonResponse
    {
        $culture = (string) $request->input('culture', '');
        $label   = (string) $request->input('label', '');
        if ($culture === '' || $label === '') {
            return response()->json(['ok' => false, 'error' => 'culture and label are required'], 422);
        }
        if (!in_array($source, ['ahg_dropdown', 'term', 'setting'], true)) {
            return response()->json(['ok' => false, 'error' => 'unknown source'], 400);
        }

        $isAdmin = \AhgCore\Services\AclService::isAdministrator();

        // Editor path - queue a draft. The 4 source values flow through
        // ahg_translation_draft.entity_type so /admin/translation/drafts can
        // filter and admin's draftApprove can dispatch back to this saver.
        // source_hash is NOT NULL with no default (sha256 of source_text);
        // the source_text for a dropdown label is the en parent value.
        if (!$isAdmin) {
            try {
                $sourceText = $this->lookupSourceLabel($source, $id);
                $draftId = DB::table('ahg_translation_draft')->insertGetId([
                    'object_id'           => $id,
                    'entity_type'         => $source,
                    'field_name'          => 'label',
                    'source_culture'      => 'en',
                    'target_culture'      => $culture,
                    'source_hash'         => hash('sha256', $sourceText),
                    'source_text'         => $sourceText,
                    'translated_text'     => $label,
                    'status'              => 'draft',
                    'created_by_user_id'  => auth()->id(),
                    'created_at'          => now(),
                ]);
                return response()->json([
                    'ok'       => true,
                    'state'    => 'pending',
                    'draft_id' => $draftId,
                    'source'   => $source,
                    'id'       => $id,
                    'culture'  => $culture,
                ]);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
        }

        // Admin path - apply directly to the source's _i18n table.
        try {
            $this->applyI18nSave($source, $id, $culture, $label);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
        return response()->json([
            'ok'      => true,
            'state'   => 'applied',
            'source'  => $source,
            'id'      => $id,
            'culture' => $culture,
        ]);
    }

    /**
     * Issue #59 Phase 3 - read the en source-of-truth label for a row in any
     * of the 3 sources. Used to populate ahg_translation_draft.source_text +
     * source_hash when an editor queues a draft. Returns '' if the row is
     * gone (orphan); the draft will reject on approval via draftApplyDropdown.
     */
    protected function lookupSourceLabel(string $source, int $id): string
    {
        try {
            switch ($source) {
                case 'ahg_dropdown':
                    return (string) (DB::table('ahg_dropdown')->where('id', $id)->value('label') ?? '');
                case 'term':
                    return (string) (DB::table('term_i18n')->where('id', $id)->where('culture', 'en')->value('name') ?? '');
                case 'setting':
                    return (string) (DB::table('setting_i18n')->where('id', $id)->where('culture', 'en')->value('value') ?? '');
            }
        } catch (\Throwable $e) {}
        return '';
    }

    /**
     * Issue #59 Phase 3 - upsert helper. Used by saveI18n (admin path) and by
     * TranslationController::draftApprove when applying a queued draft.
     * Public so the translation controller can re-use it without re-deriving
     * the source -> table dispatch.
     */
    public static function applyI18nSave(string $source, int $id, string $culture, string $label): void
    {
        switch ($source) {
            case 'ahg_dropdown':
                if (!Schema::hasTable('ahg_dropdown_i18n')) {
                    throw new \RuntimeException('ahg_dropdown_i18n table not installed yet');
                }
                DB::table('ahg_dropdown_i18n')->updateOrInsert(
                    ['id' => $id, 'culture' => $culture],
                    ['label' => $label]
                );
                break;
            case 'term':
                // term_i18n (id, culture, name, ...) - use `name` not `label`.
                DB::table('term_i18n')->updateOrInsert(
                    ['id' => $id, 'culture' => $culture],
                    ['name' => $label]
                );
                break;
            case 'setting':
                // setting_i18n (id, culture, value) - use `value` not `label`.
                DB::table('setting_i18n')->updateOrInsert(
                    ['id' => $id, 'culture' => $culture],
                    ['value' => $label]
                );
                break;
            default:
                throw new \RuntimeException('Unknown dropdown source: ' . $source);
        }
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
