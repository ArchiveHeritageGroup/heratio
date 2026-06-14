<?php

/**
 * ResearchDocumentTemplatesController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchDocumentTemplatesController - Researcher document templates.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). The single endpoint is auth-gated and manages
 * research_document_template rows (create/update + listing). No cross-calls to
 * other ResearchController methods existed - the method used only the shared
 * trait helper (getSidebarData) and the injected ResearchService
 * (getResearcherByUserId), so the move is a verbatim lift.
 */
class ResearchDocumentTemplatesController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function documentTemplates(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post')) {
            $formAction = $request->input('form_action');

            if ($formAction === 'create') {
                DB::table('research_document_template')->insert([
                    'name' => $request->input('name'),
                    'document_type' => $request->input('document_type'),
                    'description' => $request->input('description'),
                    'fields_json' => $request->input('fields_json') ?: '[]',
                    'created_by' => $researcher->id,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                return redirect('/research/documentTemplates')->with('success', 'Template created.');
            }

            if ($formAction === 'update') {
                $templateId = (int) $request->input('template_id');
                DB::table('research_document_template')
                    ->where('id', $templateId)
                    ->update([
                        'name' => $request->input('name'),
                        'document_type' => $request->input('document_type'),
                        'description' => $request->input('description'),
                        'fields_json' => $request->input('fields_json') ?: '[]',
                    ]);
                return redirect('/research/documentTemplates')->with('success', 'Template updated.');
            }
        }

        $templates = DB::table('research_document_template')
            ->orderBy('name')
            ->get()
            ->toArray();

        return view('research::research.document-templates', array_merge(
            $this->getSidebarData('documentTemplates'),
            compact('templates')
        ));
    }
}
