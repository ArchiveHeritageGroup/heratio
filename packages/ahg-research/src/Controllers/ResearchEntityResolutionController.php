<?php

/**
 * ResearchEntityResolutionController - Controller for Heratio
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
use AhgResearch\Services\EntityResolutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ResearchEntityResolutionController - Entity resolution proposals/conflicts.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). All three endpoints are auth-gated and operate on
 * entity-resolution match proposals via the EntityResolutionService (which is
 * instantiated locally with `new`). No cross-calls to other ResearchController
 * methods existed - the methods used only the shared trait helper
 * (getSidebarData) and the injected ResearchService (getResearcherByUserId),
 * so the move is a verbatim lift.
 */
class ResearchEntityResolutionController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function entityResolution(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $erService = new EntityResolutionService();

        if ($request->isMethod('post') && $request->input('form_action') === 'propose') {
            $erService->proposeMatch([
                'entity_a_type' => $request->input('entity_a_type'),
                'entity_a_id' => (int) $request->input('entity_a_id'),
                'entity_b_type' => $request->input('entity_b_type'),
                'entity_b_id' => (int) $request->input('entity_b_id'),
                'relationship_type' => $request->input('relationship_type', 'sameAs'),
                'match_method' => $request->input('match_method', 'manual'),
                'confidence' => $request->input('confidence') !== null ? (float) $request->input('confidence') : null,
                'notes' => $request->input('notes'),
                'proposer_id' => $researcher->id,
            ]);
            return redirect('/research/entityResolution')->with('success', 'Match proposed.');
        }

        $filters = [
            'status' => $request->input('status'),
            'entity_type' => $request->input('entity_type'),
            'relationship_type' => $request->input('relationship_type'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $proposals = $erService->getProposals($filters, $page);

        return view('research::research.entity-resolution', array_merge(
            $this->getSidebarData('entityResolution'),
            compact('proposals')
        ));
    }

    public function resolveEntityResolution(Request $request, $id)
    {
        if (!Auth::check()) return response()->json(['error' => 'Unauthorized'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $erService = new EntityResolutionService();
        $status = $request->input('status');
        $success = $erService->resolveMatch((int) $id, $status, $researcher->id);

        return response()->json(['success' => $success]);
    }

    public function entityResolutionConflicts($id)
    {
        $erService = new EntityResolutionService();
        $conflicts = $erService->getConflictingAssertions((int) $id);
        return response()->json(['conflicts' => $conflicts]);
    }
}
