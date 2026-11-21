<?php

/**
 * UiTranslationCoverageController - admin page: how much of the interface each
 * locale shows, from lang/*.json with the ui_string overrides (heratio#1416).
 * Read-only; the same figures as ahg:translation-coverage.
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

use AhgCore\Services\UiCoverageService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class UiTranslationCoverageController extends Controller
{
    private const CACHE_KEY = 'ahg:ui-translation-coverage';

    public function index(Request $request)
    {
        if ($request->boolean('refresh')) {
            Cache::forget(self::CACHE_KEY);
        }
        // ponytail: one cached report (10 min); the codebase scan and ~70 locale
        // files take a few seconds, too slow for every page view.
        $report = Cache::remember(self::CACHE_KEY, 600, fn () => [
            'rows' => (new UiCoverageService)->report(UiCoverageService::codebaseKeys()),
            'generated_at' => now()->toDateTimeString(),
        ]);
        $rows = $report['rows'];
        if ($request->boolean('sa')) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['sa']));
        }

        return view('ahg-core::ui-translation-coverage.index', [
            'rows' => $rows,
            'generatedAt' => $report['generated_at'],
            'saOnly' => $request->boolean('sa'),
        ]);
    }
}
