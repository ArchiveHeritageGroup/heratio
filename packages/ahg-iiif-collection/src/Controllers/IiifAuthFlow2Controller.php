<?php

/**
 * IiifAuthFlow2Controller - Controller for Heratio
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

use AhgIiifCollection\Services\IiifAuthFlow2Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * IIIF Authorization Flow 2.0 endpoints (issue #696).
 *
 * Spec: https://iiif.io/api/auth/2.0/
 *
 * Routes:
 *   GET /iiif/auth/2/probe?resource=<iri>   ProbeService
 *   GET /iiif/auth/2/access                 AccessService entry point
 *   GET /iiif/auth/2/token                  AccessTokenService
 *
 * The legacy 1.0 endpoints under /iiif-auth/* continue to ship for
 * Mirador 3 / older OSD viewers; the spec lets a single resource
 * advertise both auth versions in its service list.
 */
class IiifAuthFlow2Controller extends Controller
{
    private IiifAuthFlow2Service $service;

    public function __construct(IiifAuthFlow2Service $service)
    {
        $this->service = $service;
    }

    /**
     * ProbeService. Returns 200 + AuthProbeResult2 (status=ok) when
     * access is allowed, 401 + AuthProbeResult2 + AccessService block
     * when not. The resource URI is read from the `resource` query
     * parameter per the spec.
     */
    public function probe(Request $request): JsonResponse
    {
        $resource = (string) $request->query('resource', '');
        if ($resource === '') {
            return response()->json(['error' => 'resource query parameter is required'], 422);
        }
        $result = $this->service->probe($resource);
        return response()->json($result['body'], $result['status'], [
            'Content-Type' => 'application/ld+json',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * AccessService entry point. Active profile: if the user isn't
     * authenticated yet, route them through the standard Heratio login
     * flow (which preserves the intended URL via ?redirect=), then
     * close the window once they're back here authenticated. The viewer
     * picks up the new session via the AccessTokenService probe.
     */
    public function access(Request $request)
    {
        if (!Auth::check()) {
            // Redirect to login with the access endpoint as the next
            // hop. Heratio's auth controller honours `?redirect=`.
            return redirect('/login?redirect=' . urlencode($request->fullUrl()));
        }
        // User authenticated - render the close-window page from the
        // existing iiifAuth views.
        return view('ahg-iiif-collection::iiifAuth.access-service-close');
    }

    /**
     * AccessTokenService. CORS-friendly endpoint that mints an opaque
     * token tied to the current session. The viewer reads the token
     * out of the JSON response (or, when in iframe-postMessage mode,
     * out of the iframe message) and rides it on subsequent probe
     * requests as a Bearer header.
     */
    public function token(Request $request): JsonResponse
    {
        $origin = $request->header('Origin');
        $messageId = (string) $request->query('messageId', '');
        $result = $this->service->issueAccessToken($origin, $messageId !== '' ? $messageId : null);

        $response = response()->json($result['body'], $result['status'], [
            'Content-Type' => 'application/ld+json',
        ], JSON_UNESCAPED_SLASHES);

        // CORS - the AccessTokenService is called cross-origin by the
        // viewer, so we need an explicit ACAO. The spec also asks for
        // ACAC: true because the cookie has to ride along.
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Vary', 'Origin');
        }
        return $response;
    }
}
