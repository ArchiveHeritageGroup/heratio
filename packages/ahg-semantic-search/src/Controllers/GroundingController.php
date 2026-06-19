<?php

/**
 * GroundingController - Heratio
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

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\GraphGroundingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GraphRAG grounding endpoint (#1320 increment 2).
 *
 * KM / an agent GETs /api/ric/ground?q=<query> and receives authoritative
 * disambiguation facts from the RiC graph, ready to prepend to its grounding
 * prompt. Read-only; GET so server-to-server callers need no CSRF token.
 */
class GroundingController extends Controller
{
    public function ground(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['error' => 'missing required query parameter: q'], 422);
        }

        $max = (int) $request->query('max', 5);
        $max = max(1, min(20, $max));

        $result = (new GraphGroundingService())->groundQuery($q, $max);

        return response()->json($result);
    }
}
