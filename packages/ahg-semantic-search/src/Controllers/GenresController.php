<?php

/**
 * GenresController - the PUBLIC "Browse by genre / form" discovery surface (the
 * genre/form slice; sibling of the "Explore by theme" subject slice and the
 * "Browse by place" geography slice).
 *
 * Genres/forms are the genre and document-form access points the published
 * holdings carry: the genre terms (genre taxonomy 78) under which the most
 * PUBLISHED records sit. The surface frames them as "ways into the collection by
 * genre/form" so a visitor can start from a genre rather than a search box.
 *
 *   GET /genres            index - a browsable list/cloud of the genres used
 *                                  across published records, each with its count,
 *                                  ordered by frequency, linking to a per-genre
 *                                  detail.
 *   GET /genres/{termId}   show  - one genre: its label, scope note, and a
 *                                  paginated, bounded list of the published
 *                                  records of it, each linking to the record
 *                                  (and a "browse all of this genre" link into
 *                                  the canonical GLAM browse).
 *   GET /genres.json       json  - the machine-readable genre list (CORS-open).
 *
 * READ-ONLY and published-only: every record surfaced is published (status
 * type_id = 158 / status_id = 160), the catalogue root is excluded, and no table
 * is ever written. Every path degrades to an empty-state rather than a 500.
 * International, jurisdiction-neutral: the genre names come from the data, with no
 * hardcoded vocabulary and no country default.
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

use AhgSemanticSearch\Services\GenreService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GenresController extends Controller
{
    protected GenreService $service;

    public function __construct()
    {
        $this->service = new GenreService;
    }

    /**
     * Public landing: the genres used across the published collection, as a
     * browsable cloud ordered by frequency. Never 500s - any failure renders the
     * grounded empty-state.
     */
    public function index()
    {
        $genres = [];
        try {
            $genres = $this->service->topGenres(GenreService::DEFAULT_GENRES);
        } catch (\Throwable $e) {
            Log::info('[genres] index failed: '.$e->getMessage());
        }

        // Pre-compute the max count so the cloud can size each genre relative to
        // the busiest one. Empty list -> 1 to avoid a divide-by-zero in the view.
        $maxCount = 1;
        foreach ($genres as $g) {
            $c = (int) ($g['record_count'] ?? 0);
            if ($c > $maxCount) {
                $maxCount = $c;
            }
        }

        return view('ahg-semantic-search::genres.index', [
            'genres' => $genres,
            'count' => count($genres),
            'maxCount' => $maxCount,
        ]);
    }

    /**
     * Public detail for one genre (a genre term). Paginated, bounded record list.
     * Falls back to the genres index when the term is missing, is not a genre
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

        $genre = null;
        try {
            $genre = $this->service->genre((int) $termId, $page, GenreService::PER_PAGE);
        } catch (\Throwable $e) {
            Log::info('[genres] show('.$termId.') failed: '.$e->getMessage());
        }

        if ($genre === null) {
            return redirect()->route('genres.index');
        }

        return view('ahg-semantic-search::genres.show', [
            'genre' => $genre,
        ]);
    }

    /**
     * Machine-readable genre list (CORS-open, cacheable). Never 500s - degrades to
     * an empty genres array.
     */
    public function json(): JsonResponse
    {
        $genres = [];
        try {
            $genres = $this->service->genreList(GenreService::MAX_GENRES);
        } catch (\Throwable $e) {
            Log::info('[genres] json failed: '.$e->getMessage());
        }

        $payload = [
            'surface' => 'genres',
            'description' => 'The published holdings grouped by the genres and forms they carry, by published-record count.',
            'taxonomy' => 'genre',
            'count' => count($genres),
            'genres' => array_map(static function (array $g) {
                return [
                    'id' => $g['term_id'],
                    'label' => $g['label'],
                    'record_count' => $g['record_count'],
                    'url' => url('/genres/'.$g['term_id']),
                    'browse_url' => url('/glam/browse?genre='.$g['term_id']),
                ];
            }, $genres),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
