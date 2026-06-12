<?php

/**
 * RepatriationKnowledgeController - community KNOWLEDGE contributions about a
 * displaced item / repatriation claim (north-star heratio#1207: the repatriation
 * engine).
 *
 * The next slice on top of the detection register and the claim / virtual-return
 * surface. A community member, descendant, researcher or any knowledgeable
 * person, viewing a claim's public virtual-return page, can contribute KNOWLEDGE
 * about the displaced object - oral history, provenance knowledge, a correction,
 * a pointer to the source community, or another note. The contribution lands
 * MODERATED ('pending') and is shown publicly only once an admin approves it -
 * exactly the language-revival glossary / transcription flow:
 *
 *   PUBLIC (web):
 *     GET  /repatriation-knowledge/{claim}   form       - context + submit form
 *     POST /repatriation-knowledge/{claim}   contribute - lodge a contribution (-> pending)
 *
 *   ADMIN (auth + admin):
 *     GET  /repatriation/knowledge            moderate    - moderation queue
 *     POST /repatriation/knowledge/{id}       moderateSet - approve / reject one entry
 *
 * Every write goes ONLY to the new repatriation_knowledge_contribution table via
 * RepatriationKnowledgeService. The claim / item context is read READ-ONLY from
 * the existing claim register (RepatriationClaimService) and the catalogue. No
 * existing table is written or ALTERed. Forms have full validation; every screen
 * has an empty-state and never 500s. Respectful, non-partisan framing;
 * contributors are credited only on explicit consent.
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

use AhgSemanticSearch\Services\RepatriationKnowledgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RepatriationKnowledgeController extends Controller
{
    protected RepatriationKnowledgeService $service;

    public function __construct()
    {
        $this->service = new RepatriationKnowledgeService;
    }

    /**
     * Public contribution form for one repatriation claim: shows the claim's
     * read-only context (origin place, claimant community, the underlying item),
     * the approved community knowledge already on it, and the submit form. Never
     * 500s: an unknown claim renders a clear "not available" state.
     */
    public function form(Request $request, $claim)
    {
        $claimId = (int) $claim;

        $context = null;
        $approved = [];
        try {
            $context = $this->service->resolveClaim($claimId);
            if ($context !== null) {
                $approved = $this->service->approvedForClaim($claimId);
            }
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] form failed for '.$claimId.': '.$e->getMessage());
        }

        return view('ahg-semantic-search::repatriation-knowledge.form', [
            'claimId' => $claimId,
            'context' => $context,
            'approved' => $approved,
            'types' => RepatriationKnowledgeService::CONTRIBUTION_TYPES,
            'disclaimer' => RepatriationKnowledgeService::DISCLAIMER,
            'available' => $this->service->available(),
        ]);
    }

    /**
     * Lodge a new contribution against one claim. Full validation. The
     * contribution lands 'pending' and is NOT shown publicly until an admin
     * approves it. Redirects back to the claim form with a clear confirmation.
     */
    public function contribute(Request $request, $claim)
    {
        $claimId = (int) $claim;

        $validated = $request->validate([
            'contribution_type' => 'required|string|max:32',
            'body' => 'required|string|max:60000',
            'source' => 'nullable|string|max:512',
            'contributor_name' => 'nullable|string|max:255',
            'credit_consent' => 'nullable',
        ]);
        $validated['claim_id'] = $claimId;
        $validated['credit_consent'] = $request->boolean('credit_consent');

        $id = $this->service->contribute($validated, $this->userId($request));

        if ($id === null) {
            return back()
                ->withInput()
                ->with('error', __('Your contribution could not be saved. The claim may not be available, or some details may be missing. Please check and try again.'));
        }

        return redirect()
            ->route('repatriation-knowledge.form', ['claim' => $claimId])
            ->with('success', __('Thank you. Your contribution has been submitted and will appear once a reviewer has approved it.'));
    }

    /**
     * Admin moderation queue for community knowledge contributions. Lists entries
     * in one moderation state (default 'pending'), with approve / reject actions.
     * Never 500s: a missing table renders the empty-state.
     */
    public function moderate(Request $request)
    {
        $status = trim((string) $request->query('status', 'pending'));

        $entries = [];
        $counts = [];
        try {
            $entries = $this->service->moderationQueue($status);
            $counts = $this->service->moderationCounts();
        } catch (\Throwable $e) {
            Log::info('[repatriation-knowledge] moderate queue failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::repatriation-knowledge.moderate', [
            'entries' => $entries,
            'counts' => $counts,
            'statuses' => RepatriationKnowledgeService::MODERATION_STATUSES,
            'types' => RepatriationKnowledgeService::CONTRIBUTION_TYPES,
            'statusFilter' => array_key_exists($status, RepatriationKnowledgeService::MODERATION_STATUSES) ? $status : 'pending',
            'available' => $this->service->available(),
        ]);
    }

    /**
     * Approve or reject one contribution (admin action from the moderation queue).
     * Full validation; redirects back with a confirmation.
     */
    public function moderateSet(Request $request, $id)
    {
        $validated = $request->validate([
            'moderation_status' => 'required|string|max:32',
        ]);

        $ok = $this->service->moderate((int) $id, $validated['moderation_status'], $this->userId($request));

        return back()->with($ok ? 'success' : 'error', $ok
            ? __('Contribution updated.')
            : __('The contribution could not be updated.'));
    }

    /**
     * Current user id, when authenticated.
     */
    protected function userId(Request $request): ?int
    {
        try {
            $user = $request->user();

            return $user ? (int) $user->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
