<?php

/**
 * ResearchCitationsController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchCitationsController - Public citation rendering / export.
 *
 * Extracted from ResearchController as stage 3 (Part B) of the monolith
 * decomposition (issue #1253). Both endpoints are PUBLIC (no auth) - the
 * citation styles render for any visitor, and citation logging is associated
 * with the current researcher only when one happens to be logged in.
 */
class ResearchCitationsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function cite(Request $request, string $slug)
    {
        $object = DB::table('slug')->where('slug', $slug)->first();
        if (!$object) abort(404);

        $styles = ['chicago', 'mla', 'turabian', 'apa', 'harvard', 'unisa'];
        $citations = [];
        foreach ($styles as $style) {
            $citations[$style] = $this->service->generateCitation($object->object_id, $style);
        }

        $researcherId = null;
        if (Auth::check()) {
            $r = $this->service->getResearcherByUserId(Auth::id());
            if ($r) $researcherId = $r->id;
        }
        foreach ($citations as $style => $data) {
            if (!isset($data['error'])) {
                $this->service->logCitation($researcherId, $object->object_id, $style, $data['citation']);
            }
        }

        $exportFormats = \AhgResearch\Services\CitationService::FORMATS;

        return view('research::research.cite', array_merge(
            $this->getSidebarData('bibliographies'),
            compact('citations', 'styles', 'exportFormats'),
            ['objectId' => (int) $object->object_id, 'objectSlug' => $slug]
        ));
    }

    public function citeExport(Request $request, string $slug, string $format)
    {
        $object = DB::table('slug')->where('slug', $slug)->first();
        if (!$object) abort(404);

        $citation = app(\AhgResearch\Services\CitationService::class)->export((int) $object->object_id, strtolower($format));

        if (isset($citation['error'])) {
            abort(404, $citation['error']);
        }

        return response($citation['body'], 200, [
            'Content-Type'        => $citation['mime'],
            'Content-Disposition' => 'attachment; filename="' . $citation['filename'] . '"',
        ]);
    }
}
