<?php

/**
 * IiifController - Controller for Heratio
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

namespace AhgCore\Controllers;

use App\Http\Controllers\Controller;
use App\Support\IiifSettings;
use Illuminate\Http\Request;

/**
 * IIIF settings JSON endpoint for the front-end viewer (closes audit #81).
 *
 * Mirrors the pattern used by VoiceController::getSettings (closes #94)
 * and TtsController::settings: the master.blade.php injector exposes
 * window.AHG_IIIF synchronously so the viewer init can read it without
 * an extra round-trip; this endpoint exists for any future fetch-based
 * consumer (e.g. a settings sync from a SPA shell).
 *
 * Response shape matches IiifSettings::payload() exactly so the JSON +
 * window-global stay in lockstep. No auth gate — the values are entirely
 * presentation policy and already publicly leaked through the viewer's
 * rendered HTML, so no information disclosure risk.
 */
class IiifController extends Controller
{
    public function getSettings(Request $request)
    {
        return response()->json([
            'success'  => true,
            'settings' => IiifSettings::payload(),
        ]);
    }
}
