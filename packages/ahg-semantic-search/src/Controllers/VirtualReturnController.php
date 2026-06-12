<?php

/**
 * VirtualReturnController - the public "virtual return" surface for the
 * repatriation engine (north-star heratio#1207).
 *
 *   GET /virtual-return/{id}   (name virtual-return.show)
 *
 * Renders one repatriation claim as a respectful "virtual return": the object
 * shown in its ORIGIN context - its place of origin, the claimant community, the
 * documented evidence, and (only when the underlying record is PUBLISHED) a link
 * to the object's own record where any digital surrogate / 3D viewer lives. This
 * lets the public re-encounter the object in its own context even when no
 * physical return has happened.
 *
 * Public + read-only. It reads the claim from RepatriationClaimService and the
 * underlying item's context read-only; it surfaces a record link ONLY for
 * published items (an unpublished item degrades to origin-context-only - never a
 * back door to a draft record). {id} is the claim id, numerically constrained on
 * the route. An unknown id 404s; any service / table failure degrades to a
 * dignified "not available" state - this page never 500s.
 *
 * Sensitive subject matter: the page is factual and non-partisan. The status is
 * presented as where a dialogue stands, never as a legal outcome. The standing
 * disclaimer is shown prominently.
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

use AhgSemanticSearch\Services\RepatriationClaimService;
use AhgSemanticSearch\Services\RepatriationKnowledgeService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class VirtualReturnController extends Controller
{
    protected RepatriationClaimService $service;

    protected RepatriationKnowledgeService $knowledge;

    public function __construct()
    {
        $this->service = new RepatriationClaimService;
        $this->knowledge = new RepatriationKnowledgeService;
    }

    /**
     * Public virtual-return page for one claim (by claim id). Unknown id 404s;
     * any failure degrades to the dignified empty-state. Never 500s.
     */
    public function show($id)
    {
        $claimId = (int) $id;
        if ($claimId <= 0) {
            abort(404);
        }

        $context = null;
        try {
            $context = $this->service->virtualReturn($claimId);
        } catch (\Throwable $e) {
            Log::info('[virtual-return] assembly failed for '.$claimId.': '.$e->getMessage());
        }

        if ($context === null) {
            abort(404);
        }

        // heratio#1207 - community KNOWLEDGE slice. Surface the APPROVED community
        // knowledge on this claim (read-only) plus a link to the public submit
        // form. Wrapped + defaulted so the existing virtual-return page never
        // breaks if the knowledge table is absent.
        $knowledge = [];
        try {
            $knowledge = $this->knowledge->approvedForClaim($claimId);
        } catch (\Throwable $e) {
            Log::info('[virtual-return] knowledge read failed for '.$claimId.': '.$e->getMessage());
        }

        return view('ahg-semantic-search::virtual-return.show', [
            'claim' => $context['claim'] ?? [],
            'item' => $context['item'] ?? null,
            'register' => $context['register'] ?? null,
            'disclaimer' => (string) ($context['disclaimer'] ?? RepatriationClaimService::DISCLAIMER),
            'claimId' => $claimId,
            'knowledge' => $knowledge,
        ]);
    }
}
