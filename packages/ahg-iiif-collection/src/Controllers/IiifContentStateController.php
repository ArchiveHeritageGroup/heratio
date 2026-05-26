<?php

/**
 * IiifContentStateController - Controller for Heratio
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

use AhgIiifCollection\Services\IiifContentStateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IIIF Content State API 1.0 endpoint (issue #696).
 *
 * Spec: https://iiif.io/api/content-state/1.0/
 *
 * Routes:
 *   POST /iiif/content-state/encode   { manifest, canvas?, selector? }
 *                                     -> { token, annotation }
 *   GET  /iiif/content-state/decode?token=<base64url>
 *                                     -> { annotation } | 400
 *
 * Anonymous - no auth gate. Content State tokens carry only references
 * to manifests + viewports; resolving them still goes through whatever
 * Authorization Flow 2.0 protections (#696) the manifest carries.
 */
class IiifContentStateController extends Controller
{
    private IiifContentStateService $service;

    public function __construct(IiifContentStateService $service)
    {
        $this->service = $service;
    }

    public function encode(Request $request): JsonResponse
    {
        $body = $request->json()->all();
        $manifest = (string) ($body['manifest'] ?? $request->input('manifest', ''));
        if ($manifest === '') {
            return response()->json(['error' => 'manifest IRI is required'], 422);
        }
        $canvas = $body['canvas'] ?? $request->input('canvas');
        $selector = $body['selector'] ?? null;
        if (!is_array($selector)) {
            $selector = null;
        }

        $token = $this->service->encode($manifest, $canvas, $selector);
        $annotation = $this->service->buildAnnotation($manifest, $canvas, $selector);

        return response()->json([
            'token' => $token,
            'annotation' => $annotation,
        ]);
    }

    public function decode(Request $request): JsonResponse
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            return response()->json(['error' => 'token query parameter is required'], 422);
        }
        $annotation = $this->service->decode($token);
        if ($annotation === null) {
            return response()->json(['error' => 'Invalid token'], 400);
        }
        return response()->json(['annotation' => $annotation]);
    }
}
