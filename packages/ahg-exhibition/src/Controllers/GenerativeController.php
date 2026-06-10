<?php

/**
 * GenerativeController - Heratio ahg-exhibition
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgExhibition\Controllers;

use AhgExhibition\Services\GenerativeExhibitionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * heratio#1186 - generative exhibitions. Enter a theme, get an AI-curated draft exhibition
 * (rooms + objects + labels) drawn from the catalogue, for the curator to review. Placement
 * into a real Exhibition Space is a later slice.
 */
class GenerativeController extends Controller
{
    public function __construct(private GenerativeExhibitionService $service) {}

    public function index()
    {
        return view('ahg-exhibition::exhibition-space.generate');
    }

    public function suggestAjax(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|string|max:200',
            'count' => 'nullable|integer|min:4|max:24',
        ]);

        return response()->json($this->service->suggest($data['theme'], (int) ($data['count'] ?? 12)));
    }
}
