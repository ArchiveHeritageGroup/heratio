<?php

/**
 * FunctionController - Controller for Heratio
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



namespace AhgFunctionManage\Controllers;

use AhgFunctionManage\Services\FunctionBrowseService;
use AhgFunctionManage\Services\FunctionService;
use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FunctionController extends Controller
{
    protected FunctionService $service;

    public function __construct()
    {
        $this->service = new FunctionService(app()->getLocale());
    }

    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $browseService = new FunctionBrowseService($culture);

        $result = $browseService->browse([
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', SettingHelper::hitsPerPage()),
            'sort' => $request->get('sort', 'alphabetic'),
            'sortDir' => $request->get('sortDir', 'asc'),
            'subquery' => $request->get('subquery', ''),
        ]);

        $pager = new SimplePager($result);

        // Enrich results with function type name
        $enrichedResults = collect($pager->getResults())->map(function ($doc) use ($culture) {
            $doc['type_name'] = isset($doc['type_id']) && $doc['type_id']
                ? DB::table('term_i18n')->where('id', $doc['type_id'])->where('culture', $culture)->value('name')
                : null;
            return $doc;
        })->toArray();

        return view('ahg-function-manage::browse', [
            'pager' => $pager,
            'enrichedResults' => $enrichedResults,
            'sortOptions' => [
                'alphabetic' => 'Name',
                'lastUpdated' => 'Date modified',
                'identifier' => 'Identifier',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $function = $this->service->getBySlug($slug);
        if (!$function) {
            abort(404);
        }

        $typeName = $this->service->getTermName($function->type_id);
        $descriptionStatus = $this->service->getTermName($function->description_status_id);
        $descriptionDetail = $this->service->getTermName($function->description_detail_id);
        $relatedFunctions = $this->service->getRelatedFunctions($function->id);
        $relatedResources = $this->service->getRelatedResources($function->id);

        $canUpdate = auth()->check();
        $canDelete = auth()->check() && \AhgCore\Services\AclService::canAdmin(auth()->id());
        $canCreate = auth()->check();

        return view('ahg-function-manage::show', [
            'function' => $function,
            'typeName' => $typeName,
            'descriptionStatus' => $descriptionStatus,
            'descriptionDetail' => $descriptionDetail,
            'relatedFunctions' => $relatedFunctions,
            'relatedResources' => $relatedResources,
            'canUpdate' => $canUpdate,
            'canDelete' => $canDelete,
            'canCreate' => $canCreate,
        ]);
    }

    public function create()
    {
        $formChoices = $this->service->getFormChoices();

        return view('ahg-function-manage::edit', [
            'function' => null,
            'formChoices' => $formChoices,
        ]);
    }

    public function edit(string $slug)
    {
        $function = $this->service->getBySlug($slug);
        if (!$function) {
            abort(404);
        }

        $formChoices = $this->service->getFormChoices();

        return view('ahg-function-manage::edit', [
            'function' => $function,
            'formChoices' => $formChoices,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'authorized_form_of_name' => 'required|string|max:1024',
            'type_id' => 'required|integer',
            'classification' => 'nullable|string|max:1024',
            'dates' => 'nullable|string|max:1024',
            'description' => 'nullable|string',
            'history' => 'nullable|string',
            'legislation' => 'nullable|string',
            'description_identifier' => 'nullable|string|max:1024',
            'institution_identifier' => 'nullable|string',
            'rules' => 'nullable|string',
            'description_status_id' => 'nullable|integer',
            'description_detail_id' => 'nullable|integer',
            'revision_history' => 'nullable|string',
            'sources' => 'nullable|string',
            'source_standard' => 'nullable|string|max:1024',
            'parallel_name' => 'nullable|string|max:1024',
            'other_name' => 'nullable|string|max:1024',
            'maintenance_notes' => 'nullable|string',
        ]);

        $data = $request->only([
            'authorized_form_of_name', 'type_id', 'classification', 'dates',
            'description', 'history', 'legislation', 'description_identifier',
            'institution_identifier', 'rules', 'description_status_id',
            'description_detail_id', 'revision_history', 'sources', 'source_standard',
            'parallel_name', 'other_name', 'maintenance_notes',
        ]);

        $id = $this->service->create($data);
        $slug = $this->service->getSlug($id);

        return redirect()
            ->route('function.show', $slug)
            ->with('success', 'Function created successfully.');
    }

    public function update(Request $request, string $slug)
    {
        $function = $this->service->getBySlug($slug);
        if (!$function) {
            abort(404);
        }

        $request->validate([
            'authorized_form_of_name' => 'required|string|max:1024',
            'type_id' => 'required|integer',
            'classification' => 'nullable|string|max:1024',
            'dates' => 'nullable|string|max:1024',
            'description' => 'nullable|string',
            'history' => 'nullable|string',
            'legislation' => 'nullable|string',
            'description_identifier' => 'nullable|string|max:1024',
            'institution_identifier' => 'nullable|string',
            'rules' => 'nullable|string',
            'description_status_id' => 'nullable|integer',
            'description_detail_id' => 'nullable|integer',
            'revision_history' => 'nullable|string',
            'sources' => 'nullable|string',
            'source_standard' => 'nullable|string|max:1024',
            'parallel_name' => 'nullable|string|max:1024',
            'other_name' => 'nullable|string|max:1024',
            'maintenance_notes' => 'nullable|string',
        ]);

        $data = $request->only([
            'authorized_form_of_name', 'type_id', 'classification', 'dates',
            'description', 'history', 'legislation', 'description_identifier',
            'institution_identifier', 'rules', 'description_status_id',
            'description_detail_id', 'revision_history', 'sources', 'source_standard',
            'parallel_name', 'other_name', 'maintenance_notes',
        ]);

        $this->service->update($function->id, $data);

        return redirect()
            ->route('function.show', $slug)
            ->with('success', 'Function updated successfully.');
    }

    public function confirmDelete(string $slug)
    {
        $function = $this->service->getBySlug($slug);
        if (!$function) {
            abort(404);
        }

        return view('ahg-function-manage::delete', [
            'function' => $function,
        ]);
    }

    public function destroy(Request $request, string $slug)
    {
        $function = $this->service->getBySlug($slug);
        if (!$function) {
            abort(404);
        }

        $this->service->delete($function->id);

        return redirect()
            ->route('function.browse')
            ->with('success', 'Function deleted successfully.');
    }
}
