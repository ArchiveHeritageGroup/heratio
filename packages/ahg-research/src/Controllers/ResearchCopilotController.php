<?php

/**
 * ResearchCopilotController - Heratio ahg-research
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgResearch\Controllers;

use AhgResearch\Services\ResearchCopilotService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * heratio#1198 - researcher copilot. Ask a research question and get an annotated source set
 * plus a grounded, cited synthesis drawn from the catalogue, for the researcher to review.
 */
class ResearchCopilotController extends Controller
{
    public function __construct(private ResearchCopilotService $service) {}

    public function index()
    {
        return view('research::copilot');
    }

    public function askAjax(Request $request)
    {
        $data = $request->validate(['question' => 'required|string|max:300']);

        return response()->json($this->service->ask($data['question']));
    }
}
