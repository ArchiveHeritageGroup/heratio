<?php

/**
 * ResearchAiDecisionController - Controller for Heratio
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

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Services\AiDisclosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ResearchAiDecisionController - Accept/Reject of AI suggestions (heratio#1252).
 *
 * A single thin JSON endpoint. The <x-research::ai-decision> component (or its
 * @include twin) fetch-POSTs {slice, id, decision} here; on {ok:true} the
 * component swaps its Accept/Reject buttons for the decided badge in place.
 *
 * The slice key is validated against the same hardcoded allowlist the service
 * owns (AiDisclosureService::DECISION_TABLES) so no table name ever derives from
 * request input. Auth-gated via the route group; Auth::id() is the acting user.
 */
class ResearchAiDecisionController extends Controller
{
    protected AiDisclosureService $disclosure;

    public function __construct()
    {
        $this->disclosure = new AiDisclosureService();
    }

    /**
     * Record a researcher's Accept/Reject on an AI-produced row.
     *
     * Returns JSON {ok:true, decision} on success, or a 4xx JSON error on
     * invalid input / no eligible row.
     */
    public function decision(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slice'    => 'required|in:writing,question,analysis,grant,publication,copilot',
            'id'       => 'required|integer',
            'decision' => 'required|in:accepted,rejected',
        ]);

        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json([
                'ok'    => false,
                'error' => __('Not authenticated.'),
            ], 401);
        }

        $ok = $this->disclosure->recordAiDecision(
            $validated['slice'],
            (int) $validated['id'],
            $validated['decision'],
            $userId
        );

        if (! $ok) {
            return response()->json([
                'ok'    => false,
                'error' => __('The AI suggestion could not be updated.'),
            ], 422);
        }

        return response()->json([
            'ok'       => true,
            'decision' => $validated['decision'],
        ]);
    }
}
