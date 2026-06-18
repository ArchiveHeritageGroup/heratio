<?php

/**
 * RepatriationDialogueController - the STAFF (holding-institution) side of the
 * repatriation claim dialogue, status audit trail and shared-record access
 * (north-star heratio#1207).
 *
 * Admin-gated (auth + admin). It hangs off one claim and drives the conversation
 * around it:
 *
 *   GET  /repatriation/claims/{id}/dialogue            show    - the claim's full
 *                                                                 staff workspace:
 *                                                                 provenance-trace
 *                                                                 links, status
 *                                                                 history, the
 *                                                                 dialogue thread
 *                                                                 (incl. internal
 *                                                                 notes), and the
 *                                                                 shared-access
 *                                                                 grants.
 *   POST /repatriation/claims/{id}/dialogue/message    message - post a dialogue
 *                                                                 message (shared
 *                                                                 or internal).
 *   POST /repatriation/claims/{id}/dialogue/status     status  - change status WITH
 *                                                                 a note, recorded
 *                                                                 in the audit trail.
 *   POST /repatriation/claims/{id}/dialogue/grant      grant   - mint a shared-
 *                                                                 record access token
 *                                                                 for a claimant.
 *   POST /repatriation/claims/{id}/dialogue/revoke/{g} revoke  - revoke a grant.
 *
 * Reads the claim from RepatriationClaimService (read-only) and the dialogue /
 * audit / access from RepatriationDialogueService. Writes go ONLY to the three new
 * dialogue tables (and to the claim's status via the claim service, which records
 * the transition). No existing table is ALTERed. Provenance-trace links are
 * existence-checked (Route::has) so the page never emits a dead link.
 *
 * Sensitive subject matter: every status / message describes where a dialogue
 * stands, never a legal outcome. The standing disclaimer is always shown.
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

class RepatriationDialogueController extends Controller
{
    protected RepatriationClaimService $claims;

    protected RepatriationDialogueService $dialogue;

    public function __construct()
    {
        $this->claims = new RepatriationClaimService;
        $this->dialogue = new RepatriationDialogueService;
    }

    /**
     * Staff dialogue workspace for one claim. 404 when the claim is unknown; any
     * other failure degrades to a dignified state - never a 500.
     */
    public function show($id)
    {
        $claimId = (int) $id;
        $claim = $this->claims->find($claimId);
        if ($claim === null) {
            abort(404);
        }

        $messages = [];
        $history = [];
        $grants = [];
        try {
            $messages = $this->dialogue->messages($claimId, false); // staff see all
            $history = $this->dialogue->statusHistory($claimId);
            $grants = $this->dialogue->grantsForClaim($claimId);
        } catch (\Throwable $e) {
            Log::info('[repatriation-dialogue] workspace load failed for '.$claimId.': '.$e->getMessage());
        }

        return view('ahg-semantic-search::repatriation.dialogue', [
            'claim' => $claim,
            'messages' => $messages,
            'history' => $history,
            'grants' => $grants,
            'statuses' => RepatriationClaimService::STATUSES,
            'roles' => RepatriationDialogueService::AUTHOR_ROLES,
            'visibilities' => RepatriationDialogueService::VISIBILITIES,
            'granteeRoles' => RepatriationDialogueService::GRANTEE_ROLES,
            'provenance' => $this->provenanceTrace($claim),
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
        ]);
    }

    /**
     * Post a dialogue message on the claim (staff author). Full validation.
     */
    public function message(Request $request, $id)
    {
        $claimId = (int) $id;
        if ($this->claims->find($claimId) === null) {
            abort(404);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:60000',
            'visibility' => 'required|string|max:16',
            'author_role' => 'nullable|string|max:32',
        ]);

        $user = $this->user($request);

        $newId = $this->dialogue->postMessage($claimId, [
            'author_role' => $validated['author_role'] ?? 'institution',
            'author_name' => $user['name'],
            'author_user' => $user['id'],
            'visibility' => $validated['visibility'],
            'body' => $validated['body'],
        ]);

        return $this->backToDialogue($claimId, $newId !== null,
            __('Message posted.'), __('The message could not be posted.'));
    }

    /**
     * Change the claim status WITH an audit note. Full validation.
     */
    public function status(Request $request, $id)
    {
        $claimId = (int) $id;
        if ($this->claims->find($claimId) === null) {
            abort(404);
        }

        $validated = $request->validate([
            'claim_status' => 'required|string|max:64',
            'note' => 'nullable|string|max:1024',
        ]);

        $user = $this->user($request);

        $ok = $this->claims->updateStatus(
            $claimId,
            $validated['claim_status'],
            $validated['note'] ?? null,
            $user['id'],
            $user['name']
        );

        return $this->backToDialogue($claimId, $ok,
            __('Status updated and recorded in the history.'), __('The status could not be updated.'));
    }

    /**
     * Mint a shared-record access grant (capability token) for a claimant /
     * origin-community representative. Full validation.
     */
    public function grant(Request $request, $id)
    {
        $claimId = (int) $id;
        if ($this->claims->find($claimId) === null) {
            abort(404);
        }

        $validated = $request->validate([
            'grantee_name' => 'nullable|string|max:255',
            'grantee_role' => 'required|string|max:32',
            'can_message' => 'nullable',
            'expires_at' => 'nullable|date',
        ]);

        $user = $this->user($request);

        $created = $this->dialogue->grantAccess($claimId, [
            'grantee_name' => $validated['grantee_name'] ?? null,
            'grantee_role' => $validated['grantee_role'],
            'can_message' => $request->boolean('can_message'),
            'expires_at' => $validated['expires_at'] ?? null,
        ], $user['id']);

        if ($created === null) {
            return $this->backToDialogue($claimId, false, '', __('The shared-record link could not be created.'));
        }

        // Surface the freshly-minted shared-record URL once so staff can pass it to
        // the claimant. The token is secret; it is shown here and not again.
        $url = route('repatriation.shared.show', ['token' => $created['token']]);

        return redirect()
            ->route('repatriation.claims.dialogue', ['id' => $claimId])
            ->with('success', __('Shared-record link created. Send it to the claimant: ').$url)
            ->with('new_share_url', $url);
    }

    /**
     * Revoke a shared-record access grant.
     */
    public function revoke(Request $request, $id, $grant)
    {
        $claimId = (int) $id;
        if ($this->claims->find($claimId) === null) {
            abort(404);
        }

        $ok = $this->dialogue->revokeGrant((int) $grant);

        return $this->backToDialogue($claimId, $ok,
            __('Shared-record link revoked.'), __('The link could not be revoked.'));
    }

    /**
     * Build the provenance-trace affordances for a claim's underlying item: a link
     * to the existing record provenance page (ahg-provenance) and the record's own
     * show page, plus the register-trace summary the claim already carries. Every
     * link is Route::has / slug-existence checked so the page never emits a dead
     * link. Read-only.
     *
     * @param  array<string,mixed>  $claim
     * @return array<string,mixed>
     */
    protected function provenanceTrace(array $claim): array
    {
        $slug = $claim['item_slug'] ?? null;

        $provenanceUrl = null;
        $timelineUrl = null;
        $recordUrl = null;

        if (is_string($slug) && $slug !== '') {
            $recordUrl = url('/'.$slug);
            try {
                if (Route::has('provenance.view')) {
                    $provenanceUrl = route('provenance.view', ['slug' => $slug]);
                }
                if (Route::has('provenance.timeline')) {
                    $timelineUrl = route('provenance.timeline', ['slug' => $slug]);
                }
            } catch (\Throwable $e) {
                Log::info('[repatriation-dialogue] provenance link build failed: '.$e->getMessage());
            }
        }

        return [
            'record_url' => $recordUrl,
            'provenance_url' => $provenanceUrl,
            'timeline_url' => $timelineUrl,
            'origin_place' => $claim['origin_place'] ?? null,
            'current_holder' => $claim['current_holder'] ?? null,
        ];
    }

    /**
     * Redirect back to the dialogue workspace with a flash result.
     */
    protected function backToDialogue(int $claimId, bool $ok, string $okMsg, string $errMsg)
    {
        return redirect()
            ->route('repatriation.claims.dialogue', ['id' => $claimId])
            ->with($ok ? 'success' : 'error', $ok ? $okMsg : $errMsg);
    }

    /**
     * Current staff user id + display name.
     *
     * @return array{id:?int,name:?string}
     */
    protected function user(Request $request): array
    {
        try {
            $u = $request->user();
            if ($u === null) {
                return ['id' => null, 'name' => null];
            }

            $name = $u->name ?? ($u->username ?? ($u->email ?? null));

            return ['id' => (int) $u->id, 'name' => $name !== null ? (string) $name : null];
        } catch (\Throwable $e) {
            return ['id' => null, 'name' => null];
        }
    }
}
