<?php

/**
 * ResearchProjectsController - transitional delegate for project routes.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\ResearchController as LegacyResearchController;
use Illuminate\Http\Request;

/**
 * ResearchProjectsController
 *
 * This controller is a thin delegator to the legacy ResearchController.
 * It allows routes to be re-pointed while we incrementally move logic out
 * of the large ResearchController into focused controllers.
 */
class ResearchProjectsController extends Controller
{
    protected LegacyResearchController $legacy;

    public function __construct()
    {
        $this->legacy = new LegacyResearchController();
    }

    public function projects(Request $request)
    {
        return $this->legacy->projects($request);
    }

    public function createProject(Request $request)
    {
        return $this->legacy->createProject($request);
    }

    public function storeProject(Request $request)
    {
        return $this->legacy->storeProject($request);
    }

    public function viewProject(Request $request, int $id)
    {
        return $this->legacy->viewProject($request, $id);
    }
}
