<?php

/**
 * EuropeanaController - admin UI for the Europeana EDM publish pipeline.
 *
 *   GET  /federation/europeana          dashboard (last run, history)
 *   POST /federation/europeana/run      manual "Generate now"
 *   GET  /federation/europeana/download download the most recent bundle
 *
 * Carved out from the locked FederationController on purpose - this
 * controller is fresh code under #670 Phase 4 and does not share state
 * with the F3 SharePoint federation controllers / connectors that are
 * pinned for stability.
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

namespace AhgFederation\Controllers;

use AhgFederation\Edm\EuropeanaExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EuropeanaController extends Controller
{
    public function index(EuropeanaExportService $service)
    {
        return view('ahg-federation::europeana.index', [
            'last' => $service->lastRun(),
            'history' => $service->history(10),
        ]);
    }

    public function run(Request $request, EuropeanaExportService $service): RedirectResponse
    {
        $since = $request->input('since') ?: null;
        $service->run('storage/europeana/', $since, 'en');

        return redirect()
            ->route('federation.europeana.index')
            ->with('status', __('Europeana export completed.'));
    }

    public function download(EuropeanaExportService $service)
    {
        $last = $service->lastRun();
        if (! $last || empty($last->bundle_path) || ! is_file($last->bundle_path)) {
            return redirect()
                ->route('federation.europeana.index')
                ->with('error', __('No Europeana bundle is available yet. Run an export first.'));
        }

        return new BinaryFileResponse($last->bundle_path, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.basename((string) $last->bundle_path).'"',
        ]);
    }
}
