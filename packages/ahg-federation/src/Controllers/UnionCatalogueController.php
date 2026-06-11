<?php

/**
 * UnionCatalogueController - public union-catalogue search surface.
 *
 *   GET /union-catalogue          HTML search across enabled members
 *   GET /union-catalogue.json     machine surface (CORS-open) for the same
 *
 * Both query federation_union_record across ALL enabled members for a ?q=
 * term, paginated, grouped by member institution, each result linking to its
 * source record url. Anonymous-readable on purpose - this is a discovery
 * surface. Never 500s: the service is Schema::hasTable-guarded and the views
 * carry an empty-state.
 *
 * Fresh code under #1203 - separate from the locked F3 FederationController.
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

namespace AhgFederation\Controllers;

use AhgFederation\Services\UnionCatalogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UnionCatalogueController extends Controller
{
    public function __construct(private UnionCatalogueService $service)
    {
    }

    /** HTML search page. */
    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $page = (int) $request->query('page', 1);

        $result = $this->service->search($q, $page, 20);

        return view('ahg-federation::union.index', [
            'result' => $result,
            'q' => $q,
        ]);
    }

    /** JSON machine surface (CORS-open). */
    public function json(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $page = (int) $request->query('page', 1);

        $result = $this->service->search($q, $page, 20);

        // Flatten the grouped structure into a portable record list while
        // keeping the per-member grouping available too.
        $records = [];
        foreach ($result['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                $records[] = [
                    'member_id' => (int) $row->member_id,
                    'member' => $row->member_name,
                    'record_ref' => $row->record_ref,
                    'title' => $row->title,
                    'level' => $row->level,
                    'dates' => $row->dates,
                    'repository' => $row->repository,
                    'url' => $row->url,
                ];
            }
        }

        $payload = [
            'q' => $result['q'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['perPage'],
            'last_page' => $result['lastPage'],
            'member_count' => $result['memberCount'],
            'records' => $records,
        ];

        return response()
            ->json($payload)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }
}
