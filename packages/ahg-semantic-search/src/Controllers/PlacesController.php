<?php

/**
 * PlacesController - the PUBLIC "Browse by place" discovery surface (the
 * geography slice; sibling of the "Explore by theme" subject slice).
 *
 * Places are the geographic access points the published holdings are ABOUT: the
 * place terms (place taxonomy 42) under which the most PUBLISHED records sit. The
 * surface frames them as "ways into the collection by geography" so a visitor can
 * start from a place rather than a search box.
 *
 *   GET /places            index - a browsable list/cloud of the places used
 *                                  across published records, each with its count,
 *                                  ordered by frequency, linking to a per-place
 *                                  detail.
 *   GET /places/{termId}   show  - one place: its label, scope note, and a
 *                                  paginated, bounded list of the published
 *                                  records about it, each linking to the record
 *                                  (and a "browse all about this place" link into
 *                                  the canonical GLAM browse).
 *   GET /places.json       json  - the machine-readable place list (CORS-open).
 *
 * READ-ONLY and published-only: every record surfaced is published (status
 * type_id = 158 / status_id = 160), the catalogue root is excluded, and no table
 * is ever written. Every path degrades to an empty-state rather than a 500.
 * International, jurisdiction-neutral: the place names come from the data, with no
 * hardcoded geography and no country default.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\PlaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlacesController extends Controller
{
    protected PlaceService $service;

    public function __construct()
    {
        $this->service = new PlaceService;
    }

    /**
     * Public landing: the places used across the published collection, as a
     * browsable cloud ordered by frequency. Never 500s - any failure renders the
     * grounded empty-state.
     */
    public function index()
    {
        $places = [];
        try {
            $places = $this->service->topPlaces(PlaceService::DEFAULT_PLACES);
        } catch (\Throwable $e) {
            Log::info('[places] index failed: '.$e->getMessage());
        }

        // Pre-compute the max count so the cloud can size each place relative to
        // the busiest one. Empty list -> 1 to avoid a divide-by-zero in the view.
        $maxCount = 1;
        foreach ($places as $p) {
            $c = (int) ($p['record_count'] ?? 0);
            if ($c > $maxCount) {
                $maxCount = $c;
            }
        }

        return view('ahg-semantic-search::places.index', [
            'places' => $places,
            'count' => count($places),
            'maxCount' => $maxCount,
        ]);
    }

    /**
     * Public detail for one place (a place term). Paginated, bounded record list.
     * Falls back to the places index when the term is missing, is not a place
     * term, or has no published records - never 500s.
     *
     * @param  int|string  $termId
     */
    public function show(Request $request, $termId)
    {
        $page = (int) $request->query('page', '1');
        if ($page < 1) {
            $page = 1;
        }

        $place = null;
        try {
            $place = $this->service->place((int) $termId, $page, PlaceService::PER_PAGE);
        } catch (\Throwable $e) {
            Log::info('[places] show('.$termId.') failed: '.$e->getMessage());
        }

        if ($place === null) {
            return redirect()->route('places.index');
        }

        return view('ahg-semantic-search::places.show', [
            'place' => $place,
        ]);
    }

    /**
     * Machine-readable place list (CORS-open, cacheable). Never 500s - degrades to
     * an empty places array.
     */
    public function json(): JsonResponse
    {
        $places = [];
        try {
            $places = $this->service->placeList(PlaceService::MAX_PLACES);
        } catch (\Throwable $e) {
            Log::info('[places] json failed: '.$e->getMessage());
        }

        $payload = [
            'surface' => 'places',
            'description' => 'The published holdings grouped by the places they are about, by published-record count.',
            'taxonomy' => 'place',
            'count' => count($places),
            'places' => array_map(static function (array $p) {
                return [
                    'id' => $p['term_id'],
                    'label' => $p['label'],
                    'record_count' => $p['record_count'],
                    'url' => url('/places/'.$p['term_id']),
                    'browse_url' => url('/glam/browse?place='.$p['term_id']),
                ];
            }, $places),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
