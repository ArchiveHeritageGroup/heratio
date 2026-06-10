<?php

/**
 * GenerativeExhibitionController - Heratio ahg-exhibition
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgExhibition\Controllers;

use AhgExhibition\Services\ThemeExhibitionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * heratio#1186 - generative exhibitions (single-shot).
 *
 * A curator enters a theme/prompt; the system selects on-theme collection objects, asks the
 * AI gateway to curate them into a narrative, builds a real Exhibition Space with the objects
 * placed, and drops the curator straight into the builder to walk through / fine-tune it.
 *
 * Routes are registered by the package service provider (admin-gated, mirroring the other
 * generative routes which use middleware('acl:create')).
 */
class GenerativeExhibitionController extends Controller
{
    public function __construct(private ThemeExhibitionService $service) {}

    /** GET - show the theme prompt form. */
    public function create()
    {
        return view('ahg-exhibition::exhibition-space.theme-exhibition');
    }

    /**
     * POST - run the generative pipeline and redirect into the new space's builder with a
     * success flash. On a recoverable failure (empty theme, no matches) redirect back with the
     * message so the curator can refine the prompt.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|string|max:200',
            'max_objects' => 'nullable|integer|min:1|max:48',
            'building' => 'nullable|string|max:120',
        ]);

        try {
            $result = $this->service->curate(
                $data['theme'],
                (int) ($data['max_objects'] ?? 12),
                isset($data['building']) ? trim((string) $data['building']) : null
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()
                ->route('exhibition-space.generate.theme')
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()
                ->route('exhibition-space.generate.theme')
                ->withInput()
                ->with('error', __('Could not generate an exhibition. Please try again.'));
        }

        if (empty($result['slug'])) {
            return redirect()
                ->route('exhibition-space.generate.theme')
                ->withInput()
                ->with('error', __('The exhibition could not be created. Please try again.'));
        }

        $msg = trans_choice(
            '{0}Generated ":theme" - no objects could be placed.|[1,*]Generated ":theme" with :count object(s) placed. Walk through or fine-tune it below.',
            (int) $result['placed'],
            ['theme' => $data['theme'], 'count' => (int) $result['placed']]
        );

        return redirect()
            ->route('exhibition-space.builder', ['slug' => $result['slug']])
            ->with('success', $msg);
    }
}
