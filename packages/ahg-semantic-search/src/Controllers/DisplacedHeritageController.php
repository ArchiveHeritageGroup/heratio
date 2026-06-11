<?php

/**
 * DisplacedHeritageController - "Potentially displaced heritage" review register
 * (first slice of the repatriation engine, north-star heratio#1207).
 *
 * index() renders an admin report of museum-catalogued objects whose recorded
 * origin appears to differ from where they are now held, grouped by origin region
 * with counts, from DisplacedHeritageService::scan(). Admin-gated by the route
 * group (auth + admin), mirroring the rest of the semantic-search admin surface.
 *
 * The report is framed throughout as a CURATORIAL REVIEW AID: a heuristic
 * origin-vs-holding mismatch flag, never a repatriation claim or a legal
 * determination. The service's standing disclaimer is passed straight through to
 * the view and shown prominently.
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

use AhgSemanticSearch\Services\DisplacedHeritageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DisplacedHeritageController extends Controller
{
    protected DisplacedHeritageService $service;

    public function __construct()
    {
        $this->service = new DisplacedHeritageService;
    }

    /**
     * Render the potentially-displaced-heritage review register. Optional
     * ?limit= query param caps the listed records (0 = no cap); the report never
     * 500s on the scan path - an empty/grounded result is a valid render.
     */
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 0);
        if ($limit < 0) {
            $limit = 0;
        }

        $report = $this->service->scan(['limit' => $limit]);

        return view('ahg-semantic-search::displaced-heritage', compact('report'));
    }
}
