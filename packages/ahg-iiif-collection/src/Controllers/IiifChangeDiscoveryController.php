<?php

/**
 * IiifChangeDiscoveryController - Controller for Heratio
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

namespace AhgIiifCollection\Controllers;

use AhgIiifCollection\Services\IiifChangeDiscoveryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * IIIF Change Discovery 1.0 endpoint (issue #695).
 *
 * Spec: https://iiif.io/api/discovery/1.0/
 *
 * Routes:
 *   GET /iiif/discovery/changes              OrderedCollection (root)
 *   GET /iiif/discovery/changes?page=N       OrderedCollectionPage
 *
 * Anonymous reads - the activity stream is intended for cross-repository
 * harvest. We don't expose anything beyond manifest URIs + change type +
 * timestamp; the manifest URI itself respects whatever access controls
 * are wired through the Auth 2.0 surface.
 */
class IiifChangeDiscoveryController extends Controller
{
    private IiifChangeDiscoveryService $service;

    public function __construct(IiifChangeDiscoveryService $service)
    {
        $this->service = $service;
    }

    /**
     * Return the OrderedCollection root, or - when ?page=N is present -
     * the requested OrderedCollectionPage. The spec allows both shapes
     * on the same endpoint URL with query-string disambiguation.
     */
    public function changes(Request $request)
    {
        $page = $request->query('page');
        if ($page === null || $page === '') {
            $doc = $this->service->buildOrderedCollection();
        } else {
            $doc = $this->service->buildOrderedCollectionPage((int) $page);
            if ($doc === null) {
                return response()->json(['error' => 'Page out of range'], 404);
            }
        }

        return response()->json($doc, 200, [
            'Content-Type' => 'application/ld+json',
            'Access-Control-Allow-Origin' => '*',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
