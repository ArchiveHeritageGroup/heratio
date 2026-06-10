<?php

/**
 * CollectionsHealthController - Controller for Heratio
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



namespace AhgReports\Controllers;

use AhgReports\Services\CollectionsHealthService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Collections Health dashboard (issue #1215) - a single read-only,
 * cross-collection KPI overview computed live from existing tables.
 */
class CollectionsHealthController extends Controller
{
    private CollectionsHealthService $service;

    public function __construct(CollectionsHealthService $service)
    {
        $this->service = $service;
    }

    public function index(): View
    {
        $stats = $this->service->getHealthStats();

        return view('ahg-reports::collections-health', [
            'stats' => $stats,
        ]);
    }
}
