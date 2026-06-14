<?php

/**
 * ResearchValidationQueueController - Controller for Heratio
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
use AhgResearch\Services\ValidationQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ResearchValidationQueueController - AI extraction result validation queue.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). The three endpoints (validationQueue, validateResult,
 * bulkValidate) are auth-gated and operate on the AI extraction validation
 * queue via ValidationQueueService. No cross-calls to other ResearchController
 * methods existed - the methods used only the shared trait helper
 * (getSidebarData) and the injected ResearchService (getResearcherByUserId),
 * plus a locally-instantiated ValidationQueueService, so the move is a verbatim
 * lift.
 */
class ResearchValidationQueueController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function validationQueue(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $vqService = new ValidationQueueService();

        $filters = [
            'status' => $request->input('status', 'pending'),
            'result_type' => $request->input('result_type'),
            'extraction_type' => $request->input('extraction_type'),
            'min_confidence' => $request->input('min_confidence'),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $queue = $vqService->getQueue(null, $filters, $page);
        $stats = $vqService->getQueueStats();
        $pendingCount = $vqService->getPendingCount();

        return view('research::research.validation-queue', array_merge(
            $this->getSidebarData('validationQueue'),
            compact('queue', 'stats', 'pendingCount')
        ));
    }

    public function validateResult(Request $request, $resultId)
    {
        if (!Auth::check()) return response()->json(['error' => 'Unauthorized'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $vqService = new ValidationQueueService();
        $action = $request->input('form_action');

        if ($action === 'accept') {
            $success = $vqService->acceptResult((int) $resultId, $researcher->id);
        } elseif ($action === 'reject') {
            $success = $vqService->rejectResult((int) $resultId, $researcher->id, $request->input('reason', ''));
        } elseif ($action === 'modify') {
            $success = $vqService->modifyResult((int) $resultId, $researcher->id, $request->input('modified_data', []));
        } else {
            return response()->json(['error' => 'Invalid action'], 400);
        }

        return response()->json(['success' => $success]);
    }

    public function bulkValidate(Request $request)
    {
        if (!Auth::check()) return response()->json(['error' => 'Unauthorized'], 401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'Not a researcher'], 403);

        $vqService = new ValidationQueueService();
        $resultIds = $request->input('result_ids', []);
        $action = $request->input('form_action');

        if ($action === 'accept') {
            $count = $vqService->bulkAccept($resultIds, $researcher->id);
        } elseif ($action === 'reject') {
            $count = $vqService->bulkReject($resultIds, $researcher->id, $request->input('reason', ''));
        } else {
            return response()->json(['error' => 'Invalid action'], 400);
        }

        return response()->json(['success' => true, 'count' => $count]);
    }
}
