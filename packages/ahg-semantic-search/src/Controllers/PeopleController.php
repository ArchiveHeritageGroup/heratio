<?php

/**
 * PeopleController - the PUBLIC "People and organisations" discovery surface (the
 * creator slice; sibling of the "Explore by theme" subject slice and the "Browse
 * by place" geography slice).
 *
 * Creators are the people and organisations the published holdings are credited
 * to: the actors that the `event` table links to a record as its creator. The
 * surface frames them as "ways into the collection by who made it" so a visitor
 * can start from a person or an organisation rather than a search box.
 *
 *   GET /people            index - a browsable list/cloud of the creators credited
 *                                  across published records, each with its count,
 *                                  ordered by frequency, linking to a per-creator
 *                                  detail.
 *   GET /people/{actorId}  show  - one creator: the authorized form of name, dates
 *                                  and history if present, and a paginated, bounded
 *                                  list of the published records they created, each
 *                                  linking to the record (and a "browse all by this
 *                                  creator" link into the canonical GLAM browse).
 *   GET /people.json       json  - the machine-readable creator list (CORS-open).
 *
 * READ-ONLY and published-only: every record surfaced is published (status
 * type_id = 158 / status_id = 160), the catalogue root is excluded, and no table
 * is ever written. Every path degrades to an empty-state rather than a 500.
 * International, jurisdiction-neutral: the names come from the data, with no
 * hardcoded person or organisation and no country default.
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

use AhgSemanticSearch\Services\PersonService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PeopleController extends Controller
{
    protected PersonService $service;

    public function __construct()
    {
        $this->service = new PersonService;
    }

    /**
     * Public landing: the creators credited across the published collection, as a
     * browsable cloud ordered by frequency. Never 500s - any failure renders the
     * grounded empty-state.
     */
    public function index()
    {
        $creators = [];
        try {
            $creators = $this->service->topCreators(PersonService::DEFAULT_CREATORS);
        } catch (\Throwable $e) {
            Log::info('[people] index failed: '.$e->getMessage());
        }

        // Pre-compute the max count so the cloud can size each creator relative to
        // the busiest one. Empty list -> 1 to avoid a divide-by-zero in the view.
        $maxCount = 1;
        foreach ($creators as $c) {
            $n = (int) ($c['record_count'] ?? 0);
            if ($n > $maxCount) {
                $maxCount = $n;
            }
        }

        return view('ahg-semantic-search::people.index', [
            'creators' => $creators,
            'count' => count($creators),
            'maxCount' => $maxCount,
        ]);
    }

    /**
     * Public detail for one creator (an actor). Paginated, bounded record list.
     * Falls back to the people index when the actor is missing or has no published
     * records - never 500s.
     *
     * @param  int|string  $actorId
     */
    public function show(Request $request, $actorId)
    {
        $page = (int) $request->query('page', '1');
        if ($page < 1) {
            $page = 1;
        }

        $creator = null;
        try {
            $creator = $this->service->creator((int) $actorId, $page, PersonService::PER_PAGE);
        } catch (\Throwable $e) {
            Log::info('[people] show('.$actorId.') failed: '.$e->getMessage());
        }

        if ($creator === null) {
            return redirect()->route('people.index');
        }

        return view('ahg-semantic-search::people.show', [
            'creator' => $creator,
        ]);
    }

    /**
     * Machine-readable creator list (CORS-open, cacheable). Never 500s - degrades
     * to an empty creators array.
     */
    public function json(): JsonResponse
    {
        $creators = [];
        try {
            $creators = $this->service->creatorList(PersonService::MAX_CREATORS);
        } catch (\Throwable $e) {
            Log::info('[people] json failed: '.$e->getMessage());
        }

        $payload = [
            'surface' => 'people',
            'description' => 'The published holdings grouped by the people and organisations credited as their creators, by published-record count.',
            'entity' => 'creator',
            'count' => count($creators),
            'people' => array_map(static function (array $c) {
                return [
                    'actor_id' => $c['actor_id'],
                    'name' => $c['name'],
                    'record_count' => $c['record_count'],
                    'url' => url('/people/'.$c['actor_id']),
                    'browse_url' => url('/glam/browse?creator='.$c['actor_id']),
                ];
            }, $creators),
            'generated_at' => now()->toIso8601String(),
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
