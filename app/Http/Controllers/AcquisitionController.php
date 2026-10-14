<?php

/**
 * AcquisitionController - receives the demo site's visit and click beacons and
 * shows the weekly source-to-enquiry dashboard (H09). See AcquisitionService.
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

namespace App\Http\Controllers;

use App\Services\AcquisitionService;
use App\Support\MarketingSite;
use Illuminate\Http\Request;

class AcquisitionController extends Controller
{
    /** Crawler and headless signatures. A beacon needs JavaScript anyway; this catches the rest. */
    private const BOT_UA = '/bot|crawl|spider|slurp|headless|lighthouse|preview|facebookexternalhit|curl|wget|python|java\/|go-http/i';

    /** A source label as the beacon builds it; anything else is recorded as direct. */
    public const SOURCE = '#^(direct|github|search|social|referral:[a-z0-9.-]{1,50}|campaign:[a-z0-9._-]{1,40})$#';

    public function event(Request $request, AcquisitionService $svc)
    {
        if (! MarketingSite::is() || preg_match(self::BOT_UA, (string) $request->userAgent())) {
            return response()->noContent();
        }
        $data = $request->validate([
            'event' => 'required|in:visit,demo,tour,docs,github',
            'source' => 'nullable|string|max:60',
            'path' => 'nullable|string|max:255',
            'detail' => 'nullable|string|max:120',
            'visit' => 'nullable|string|size:16',
        ]);
        $source = preg_match(self::SOURCE, (string) ($data['source'] ?? ''))
            ? $data['source'] : 'direct';
        $svc->record($data['event'], $source, $data['detail'] ?? null, $data['path'] ?? null, $data['visit'] ?? null);

        return response()->noContent();
    }

    public function dashboard(Request $request, AcquisitionService $svc)
    {
        $weeks = max(1, min(52, (int) $request->query('weeks', 12)));
        $to = now();
        $from = now()->startOfWeek()->subWeeks($weeks - 1);

        return view('acquisition.dashboard', [
            'weeks' => $weeks,
            'weekly' => $svc->weekly($weeks),
            'sources' => $svc->bySource($from, $to),
            'enquiryTypes' => $svc->enquiriesByType($from, $to),
            'typeLabels' => \AhgCore\Support\DropdownOptions::resolve('contact_enquiry_type', []),
            'from' => $from,
        ]);
    }
}
