<?php

/**
 * RepatriationSharedRecordController - the SHARED RECORD surface (north-star
 * heratio#1207, pillar 3): a permissioned, token-gated view of one repatriation
 * claim that BOTH the holding institution and the origin community can see.
 *
 * This is the claimant / origin-community door. It is NOT admin-gated: access is a
 * capability token minted by staff (repatriation_claim_access). A representative
 * with a valid, active, non-expired token can:
 *
 *   GET  /repatriation/shared/{token}            show    - the shared record: the
 *                                                           object in its origin
 *                                                           context, the provenance
 *                                                           trace link, the claim's
 *                                                           current status + status
 *                                                           history, and the SHARED
 *                                                           dialogue thread (shared
 *                                                           messages only - internal
 *                                                           staff notes are never
 *                                                           exposed here).
 *   POST /repatriation/shared/{token}/message    message - post a dialogue message
 *                                                           AS the claimant, when the
 *                                                           grant permits messaging.
 *
 * The surface is keyed off the token, never off the claim id, so it cannot be
 * enumerated. An unknown / revoked / expired token resolves to a dignified
 * "link no longer active" state (HTTP 200, not a 500, not a leak). Internal-
 * visibility messages and staff-only fields (curatorial notes, contact, the other
 * grants) are NEVER sent to this surface. This is a scoped read+dialogue surface
 * for ONE claim; full cross-peer federation of the record is a deliberate
 * follow-up (see docs/help/repatriation-claims.md).
 *
 * Sensitive subject matter: factual, non-partisan. The status is presented as
 * where a dialogue stands, never a legal outcome. The standing disclaimer is shown.
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
use AhgSemanticSearch\Services\RepatriationDialogueService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class RepatriationSharedRecordController extends Controller
{
    protected RepatriationClaimService $claims;

    protected RepatriationDialogueService $dialogue;

    public function __construct()
    {
        $this->claims = new RepatriationClaimService;
        $this->dialogue = new RepatriationDialogueService;
    }

    /**
     * The shared record for one claim, opened with a capability token. An invalid /
     * revoked / expired token renders the dignified "link not active" state (still
     * HTTP 200) rather than a 404 or 500, so the surface never leaks whether a claim
     * exists behind a bad token.
     */
    public function show($token)
    {
        $grant = $this->dialogue->resolveToken((string) $token);
        if ($grant === null) {
            return view('ahg-semantic-search::repatriation.shared-inactive');
        }

        $claim = $this->claims->find((int) $grant['claim_id']);
        if ($claim === null) {
            return view('ahg-semantic-search::repatriation.shared-inactive');
        }

        // Record the visit (best effort).
        $this->dialogue->touchGrant((int) $grant['id']);

        $messages = [];
        $history = [];
        try {
            $messages = $this->dialogue->messages((int) $grant['claim_id'], true); // SHARED only
            $history = $this->dialogue->statusHistory((int) $grant['claim_id']);
        } catch (\Throwable $e) {
            Log::info('[repatriation-shared] load failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::repatriation.shared', [
            'grant' => $grant,
            'claim' => $this->publicClaimView($claim),
            'messages' => $messages,
            'history' => $history,
            'provenance' => $this->provenanceTrace($claim),
            'statuses' => RepatriationClaimService::STATUSES,
            'canMessage' => (bool) $grant['can_message'],
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
        ]);
    }

    /**
     * Post a dialogue message AS the claimant, through the token. Only when the
     * grant permits messaging. The message is recorded with the claimant role and
     * is always 'shared' visibility (a claimant can never post an internal note).
     */
    public function message(Request $request, $token)
    {
        $grant = $this->dialogue->resolveToken((string) $token);
        if ($grant === null) {
            return view('ahg-semantic-search::repatriation.shared-inactive');
        }

        if (! $grant['can_message']) {
            return redirect()
                ->route('repatriation.shared.show', ['token' => $token])
                ->with('error', __('This link is read-only.'));
        }

        $validated = $request->validate([
            'body' => 'required|string|max:60000',
        ]);

        $newId = $this->dialogue->postMessage((int) $grant['claim_id'], [
            'author_role' => $grant['grantee_role'] === 'mediator' ? 'mediator' : 'claimant',
            'author_name' => $grant['grantee_name'] ?: __('Claimant representative'),
            'access_id' => (int) $grant['id'],
            'visibility' => 'shared',
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('repatriation.shared.show', ['token' => $token])
            ->with($newId !== null ? 'success' : 'error', $newId !== null
                ? __('Your message has been added to the dialogue.')
                : __('Your message could not be added. Please try again.'));
    }

    /**
     * The claimant-safe projection of a claim: only the fields appropriate for the
     * shared record. Internal-only fields (curatorial notes, point of contact) are
     * deliberately NOT included.
     *
     * @param  array<string,mixed>  $claim
     * @return array<string,mixed>
     */
    protected function publicClaimView(array $claim): array
    {
        return [
            'id' => $claim['id'] ?? null,
            'item_ref' => $claim['item_ref'] ?? null,
            'item_title' => $claim['item_title'] ?? null,
            'origin_place' => $claim['origin_place'] ?? null,
            'claimant_community' => $claim['claimant_community'] ?? null,
            'current_holder' => $claim['current_holder'] ?? null,
            'evidence_summary' => $claim['evidence_summary'] ?? null,
            'claim_status' => $claim['claim_status'] ?? 'registered',
            'status_meta' => $claim['status_meta'] ?? null,
        ];
    }

    /**
     * Provenance-trace affordances for the shared record: a link to the published
     * record + its provenance page, existence-checked. Read-only. Only a PUBLISHED
     * record is linked (the claimant surface is not a back door to a draft record).
     *
     * @param  array<string,mixed>  $claim
     * @return array<string,mixed>
     */
    protected function provenanceTrace(array $claim): array
    {
        $context = null;
        try {
            // Reuse the claim service's published-only resolution by rendering the
            // virtual-return context, which already gates on publication status.
            $vr = $this->claims->virtualReturn((int) ($claim['id'] ?? 0));
            $context = $vr['item'] ?? null;
        } catch (\Throwable $e) {
            Log::info('[repatriation-shared] provenance build failed: '.$e->getMessage());
        }

        $recordUrl = $context['url'] ?? null; // present only for published items
        $slug = $context['slug'] ?? null;

        $provenanceUrl = null;
        if (is_string($slug) && $slug !== '' && $recordUrl !== null) {
            try {
                if (Route::has('provenance.view')) {
                    $provenanceUrl = route('provenance.view', ['slug' => $slug]);
                }
            } catch (\Throwable $e) {
                Log::info('[repatriation-shared] provenance link failed: '.$e->getMessage());
            }
        }

        return [
            'record_url' => $recordUrl,
            'provenance_url' => $provenanceUrl,
        ];
    }
}
