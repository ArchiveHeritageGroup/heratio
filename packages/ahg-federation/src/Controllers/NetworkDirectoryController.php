<?php

/**
 * NetworkDirectoryController - the PUBLIC GLAM-network directory (#1203 slice).
 *
 *   GET /federation/network        HTML roll of participating institutions
 *   GET /federation/network.json   machine surface (CORS-open) of the same
 *
 * Both list the ENABLED participating member institutions (federation_member
 * where is_enabled=1): name, short description / what each shares (the
 * share-scope), how many discovery records it contributes to the union index,
 * a link to the union catalogue filtered to that member, and a link to the
 * member's own site. The local institution (the self-member) is highlighted.
 *
 * Anonymous-readable on purpose - this is the public face of the federation,
 * the network-effects story: the more institutions participate, the richer
 * the shared memory. Read-only over federation_member; never 500s (the service
 * is Schema::hasTable-guarded and the view carries an empty-state).
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

use AhgFederation\Services\NetworkDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class NetworkDirectoryController extends Controller
{
    public function __construct(private NetworkDirectoryService $service)
    {
    }

    /** HTML directory page. */
    public function index()
    {
        $directory = $this->service->directory();

        return view('ahg-federation::union.network', [
            'directory' => $directory,
        ]);
    }

    /** JSON machine surface (CORS-open). */
    public function json(): JsonResponse
    {
        $directory = $this->service->directory();

        $institutions = [];
        foreach ($directory['members'] as $m) {
            $institutions[] = [
                'id' => (int) $m->id,
                'name' => $m->name,
                'is_self' => (bool) $m->is_self,
                'description' => $m->share_scope,
                'shares' => $m->share_scope,
                'contact' => $m->contact,
                'site' => $m->base_url,
                'record_count' => (int) $m->record_count,
                'catalogue_url' => $m->catalogue_url,
            ];
        }

        $payload = [
            'network' => 'glam-federation',
            'member_count' => $directory['memberCount'],
            'record_count' => $directory['recordCount'],
            'self_member_id' => $directory['selfMemberId'],
            'institutions' => $institutions,
        ];

        return response()
            ->json($payload)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }
}
