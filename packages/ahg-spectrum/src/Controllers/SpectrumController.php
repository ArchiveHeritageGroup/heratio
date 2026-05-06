<?php

/**
 * SpectrumController - Controller for Heratio
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



namespace AhgSpectrum\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use AhgSpectrum\Services\SpectrumNotificationService;
use AhgSpectrum\Services\SpectrumWorkflowService;
use AhgSpectrum\Services\SpectrumSettings;

class SpectrumController extends Controller
{
    // ... existing content left unchanged ...

    // ----------------------------------------------------------------
    // Per-object Spectrum index (object entry point)
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $slug = $request->query('slug');
        $resource = null;
        $museumData = [];
        $grapData = null;

        $settings = new SpectrumSettings();

        if (!$settings->isEnabled()) {
            abort(404);
        }

        if ($slug) {
            $resource = $this->getResourceBySlug($slug);
            if (!$resource) {
                abort(404);
            }

            // Museum metadata (culture-aware via museum_metadata_i18n with en fallback)
            if (Schema::hasTable('museum_metadata')) {
                $museumData = \AhgMuseum\Services\MuseumService::fetchTranslated((int) $resource->id, app()->getLocale());
            }

            // GRAP data
            if (Schema::hasTable('grap_heritage_asset')) {
                $grapData = DB::table('grap_heritage_asset')->where('object_id', $resource->id)->first();
            }
        }

        return view('spectrum::index', [
            'resource'   => $resource,
            'museumData' => $museumData,
            'grapData'   => $grapData,
        ]);
    }

    // ... rest of file unchanged ...
}