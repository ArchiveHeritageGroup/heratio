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
            'published_only' => 'nullable|boolean',
        ]);

        $publishedOnly = ! $request->has('published_only') || $request->boolean('published_only');

        return response()->json($this->service->suggest($data['theme'], (int) ($data['count'] ?? 12), $publishedOnly));
    }

    /**
     * heratio#1186 - build a reviewed draft into a real Exhibition Space (rooms + placed
     * objects) and hand back the builder URL so the curator can fine-tune it.
     */
    public function buildAjax(Request $request)
    {
        $data = $request->validate([
            'theme' => 'nullable|string|max:200',
            'rooms' => 'required|array|min:1',
            'rooms.*.room' => 'nullable|string|max:200',
            'rooms.*.objects' => 'required|array|min:1',
            'rooms.*.objects.*.id' => 'required|integer|min:1',
        ]);

        $result = $this->service->buildExhibition($data);
        if (! empty($result['ok']) && ! empty($result['slug'])) {
            $result['builder_url'] = route('exhibition-space.builder', ['slug' => $result['slug']]);
        }

        return response()->json($result);
    }
}
