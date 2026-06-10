<?php

/**
 * StorytellingController - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\StorytellingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * heratio#1202 - storytelling engine. Generate an engaging public narrative ("story of the
 * collection") from catalogue objects on a theme, for review and publication.
 */
class StorytellingController extends Controller
{
    public function __construct(private StorytellingService $service) {}

    public function index()
    {
        return view('ahg-core::stories');
    }

    public function generateAjax(Request $request)
    {
        $data = $request->validate(['theme' => 'required|string|max:200']);

        return response()->json($this->service->generate($data['theme']));
    }
}
