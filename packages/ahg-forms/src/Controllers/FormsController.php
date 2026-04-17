<?php

/**
 * FormsController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgForms\Controllers;

use AhgForms\Services\FormService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FormsController extends Controller
{
    protected FormService $service;

    public function __construct()
    {
        $this->service = new FormService();
    }

    public function index()
    {
        $templates = $this->service->getTemplates();
        $stats = $this->service->getStatistics();

        return view('ahg-forms::index', compact('templates', 'stats'));
    }

    public function browse(Request $request)
    {
        $type = $request->get('type');
        $search = $request->get('search');

        $query = \Illuminate\Support\Facades\DB::table('ahg_form_template')->orderBy('name');

        if ($type) {
            $query->where('form_type', $type);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $templates = $query->get();

        foreach ($templates as $template) {
            $template->field_count = \Illuminate\Support\Facades\DB::table('ahg_form_field')
                ->where('template_id', $template->id)->count();
        }

        $formTypes = [
            'information_object' => 'Information Object',
            'actor' => 'Authority Record',
            'repository' => 'Repository',
            'accession' => 'Accession',
            'deaccession' => 'Deaccession',
            'rights' => 'Rights',
        ];

        return view('ahg-forms::browse', compact('templates', 'formTypes', 'type', 'search'));
    }

    public function templates(Request $request)
    {
        $type = $request->get('type');
        $templates = $this->service->getTemplates($type);

        return view('ahg-forms::templates', compact('templates', 'type'));
    }

    public function templateCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $id = $this->service->createTemplate($request->only([
                'name', 'description', 'form_type',
            ]) + ['config' => ['layout' => $request->get('layout', 'single')]]);

            return redirect()->route('forms.builder', $id);
        }

        return view('ahg-forms::template-create');
    }

    /**
     * GET /forms/template/{id}/export — download the template as JSON.
     */
    public function templateExport(int $id)
    {
        $template = $this->service->getTemplate($id);
        abort_unless($template, 404, 'Template not found');

        $fields = $this->service->getFields($id);
        $payload = [
            'template' => (array) $template,
            'fields'   => collect($fields)->map(fn ($f) => (array) $f)->values()->all(),
            'exported_at' => now()->toIso8601String(),
        ];

        $filename = 'form_template_' . $id . '_' . now()->format('Y-m-d_His') . '.json';
        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function builder(int $id)
    {
        $template = $this->service->getTemplate($id);
        abort_unless($template, 404, 'Template not found');

        $fields = $this->service->getFields($id);

        $fieldTypes = [
            'text' => 'Text Input', 'textarea' => 'Text Area',
            'richtext' => 'Rich Text Editor', 'date' => 'Date Picker',
            'daterange' => 'Date Range', 'select' => 'Dropdown Select',
            'multiselect' => 'Multi-Select', 'autocomplete' => 'Autocomplete',
            'checkbox' => 'Checkbox', 'radio' => 'Radio Buttons',
            'file' => 'File Upload', 'hidden' => 'Hidden Field',
            'heading' => 'Section Heading', 'divider' => 'Divider',
        ];

        return view('ahg-forms::builder', compact('template', 'fields', 'fieldTypes'));
    }

    public function preview(int $id)
    {
        $template = $this->service->getTemplate($id);
        abort_unless($template, 404, 'Template not found');

        $fields = $this->service->getFields($id);

        return view('ahg-forms::preview', compact('template', 'fields'));
    }

    public function assignments()
    {
        $assignments = $this->service->getAssignments();
        $templates = $this->service->getTemplates();

        return view('ahg-forms::assignments', compact('assignments', 'templates'));
    }

    public function assignmentCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->service->createAssignment($request->only([
                'template_id', 'repository_id', 'level_of_description_id',
                'collection_id', 'priority', 'inherit_to_children',
            ]));

            return redirect()->route('forms.assignments')->with('notice', 'Assignment created');
        }

        $templates = $this->service->getTemplates();

        return view('ahg-forms::assignment-create', compact('templates'));
    }

    public function library()
    {
        $library = [
            ['id' => 'isadg-minimal', 'name' => 'ISAD-G Minimal', 'description' => 'Minimal ISAD(G) compliant form with essential fields only', 'fields' => 8],
            ['id' => 'isadg-full', 'name' => 'ISAD-G Full', 'description' => 'Complete ISAD(G) form with all 26 elements across 7 areas', 'fields' => 26],
            ['id' => 'dublin-core', 'name' => 'Dublin Core Simple', 'description' => 'Dublin Core 15 core elements', 'fields' => 15],
            ['id' => 'accession', 'name' => 'Accession Standard', 'description' => 'Standard accession registration form', 'fields' => 15],
            ['id' => 'photo-collection', 'name' => 'Photo Collection Item', 'description' => 'Specialized form for photograph collections', 'fields' => 19],
        ];

        return view('ahg-forms::library', compact('library'));
    }

    // AJAX
    public function fieldAdd(Request $request)
    {
        $templateId = (int) $request->get('template_id');
        $maxSort = \Illuminate\Support\Facades\DB::table('ahg_form_field')
            ->where('template_id', $templateId)->max('sort_order') ?? 0;

        $fieldId = \Illuminate\Support\Facades\DB::table('ahg_form_field')->insertGetId([
            'template_id' => $templateId,
            'field_name' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $request->get('label'))),
            'field_type' => $request->get('field_type'),
            'label' => $request->get('label'),
            'atom_field' => $request->get('atom_field') ?: null,
            'sort_order' => $maxSort + 1,
            'is_required' => 0,
            'is_readonly' => 0,
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'field_id' => $fieldId]);
    }

    public function fieldUpdate(Request $request)
    {
        $fieldId = (int) $request->get('field_id');

        \Illuminate\Support\Facades\DB::table('ahg_form_field')
            ->where('id', $fieldId)
            ->update([
                'label' => $request->get('label'),
                'field_name' => $request->get('field_name'),
                'help_text' => $request->get('help_text'),
                'placeholder' => $request->get('placeholder'),
                'default_value' => $request->get('default_value'),
                'is_required' => $request->get('is_required') ? 1 : 0,
                'is_readonly' => $request->get('is_readonly') ? 1 : 0,
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    public function fieldDelete(Request $request)
    {
        \Illuminate\Support\Facades\DB::table('ahg_form_field')
            ->where('id', (int) $request->get('field_id'))
            ->delete();

        return response()->json(['success' => true]);
    }

    public function fieldReorder(Request $request)
    {
        $order = $request->json('order', []);

        foreach ($order as $item) {
            \Illuminate\Support\Facades\DB::table('ahg_form_field')
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Admin dashboard for forms management.
     */
    public function admin()
    {
        $templates = $this->service->getTemplates();
        $stats = $this->service->getStatistics();

        return view('ahg-forms::admin', compact('templates', 'stats'));
    }

    /**
     * Handle POST actions for forms.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');

        if ($action === 'delete') {
            $id = (int) $request->get('id');
            \Illuminate\Support\Facades\DB::table('ahg_form_field')->where('template_id', $id)->delete();
            \Illuminate\Support\Facades\DB::table('ahg_form_template')->where('id', $id)->delete();

            return redirect()->route('forms.index')->with('notice', 'Template deleted.');
        }

        if ($action === 'duplicate') {
            $id = (int) $request->get('id');
            $template = $this->service->getTemplate($id);

            if ($template) {
                $newId = $this->service->createTemplate([
                    'name' => $template->name . ' (Copy)',
                    'description' => $template->description,
                    'form_type' => $template->form_type,
                    'config' => json_decode($template->config ?? '{}', true),
                ]);

                return redirect()->route('forms.builder', $newId)->with('notice', 'Template duplicated.');
            }
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }

    /**
     * API: Autosave form draft (AJAX).
     * POST /api/forms/autosave
     */
    public function apiAutosave(Request $request)
    {
        $data = $request->json()->all();

        if (empty($data['template_id']) || empty($data['object_type']) || empty($data['form_data'])) {
            return response()->json(['error' => 'Missing required fields: template_id, object_type, form_data']);
        }

        $draftId = \Illuminate\Support\Facades\DB::table('ahg_form_draft')->updateOrInsert(
            [
                'template_id' => (int) $data['template_id'],
                'object_type' => $data['object_type'],
                'object_id' => $data['object_id'] ?? null,
                'user_id' => auth()->id(),
            ],
            [
                'form_data' => json_encode($data['form_data']),
                'updated_at' => now(),
            ]
        );

        // Get the actual draft ID
        $draft = \Illuminate\Support\Facades\DB::table('ahg_form_draft')
            ->where('template_id', (int) $data['template_id'])
            ->where('object_type', $data['object_type'])
            ->where('user_id', auth()->id())
            ->first();

        return response()->json([
            'success' => true,
            'draft_id' => $draft->id ?? null,
            'saved_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * API: Get resolved form template (AJAX).
     * GET /api/forms/template?type=informationobject&id=123
     */
    public function apiGetForm(Request $request)
    {
        $type = $request->query('type', 'informationobject');
        $objectId = (int) $request->query('id', 0);

        $repositoryId = null;
        $levelId = null;

        if ($type === 'informationobject' && $objectId) {
            $obj = \Illuminate\Support\Facades\DB::table('information_object')
                ->where('id', $objectId)
                ->first();
            if ($obj) {
                $repositoryId = $obj->repository_id;
                $levelId = $obj->level_of_description_id;
            }
        }

        $formType = ($type === 'informationobject') ? 'information_object' : $type;

        // Find a template matching the context
        $query = \Illuminate\Support\Facades\DB::table('ahg_form_template')
            ->where('form_type', $formType);

        if ($repositoryId) {
            $query->where(function ($q) use ($repositoryId) {
                $q->whereNull('repository_id')
                  ->orWhere('repository_id', $repositoryId);
            });
        }

        $template = $query->orderByDesc('is_default')->first();

        if (!$template) {
            return response()->json(['error' => 'No template found']);
        }

        $fields = \Illuminate\Support\Facades\DB::table('ahg_form_field')
            ->where('template_id', $template->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'template_id' => $template->id,
            'template_name' => $template->name,
            'config' => json_decode($template->config ?? '{}', true),
            'fields' => $fields,
        ]);
    }
}
