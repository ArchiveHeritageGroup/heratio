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
use AhgResearch\Concerns\AuthorizesProjectAccess;
use AhgResearch\Services\AiDisclosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    use AuthorizesProjectAccess;

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

        // heratio#1777: the row must belong to a project (or copilot workspace)
        // the caller can reach - the id alone used to be enough.
        $this->assertRowAccess($validated['slice'], (int) $validated['id']);

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

    /** 404 when the row is missing, 403 when the caller is not on its project or workspace. */
    private function assertRowAccess(string $slice, int $id): void
    {
        $table = AiDisclosureService::DECISION_TABLES[$slice] ?? null;
        $row = $table ? DB::table($table)->where('id', $id)->first() : null;
        abort_unless($row, 404);

        if ($slice === 'copilot') {
            $researcher = DB::table('research_researcher')->where('user_id', Auth::id())->first();
            $ok = \AhgCore\Services\AclService::isAdministrator(Auth::user())
                || ($researcher && app(\AhgResearch\Services\CollaborationService::class)
                    ->canAccess((int) $row->workspace_id, (int) $researcher->id, 'editor'));
            abort_unless($ok, 403);

            return;
        }

        $projectId = $slice === 'writing'
            ? (int) DB::table('research_writing_doc')->where('id', $row->doc_id)->value('project_id')
            : (int) $row->project_id;
        $this->assertProjectAccess($projectId);
    }
}
