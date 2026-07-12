<?php

/**
 * ClaimLedgerController - Controller for Heratio
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
use AhgResearch\Concerns\LogsResearchActivity;
use AhgResearch\Services\ClaimLedgerService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ClaimLedgerController - Research OS Stage 8 (heratio#1223).
 *
 * Per-project Claim Ledger over research_assertion + research_assertion_evidence.
 * Founding principle: no unsupported claim passes silently - the ledger surfaces
 * claims with no citation and claims over-dependent on a single source.
 *
 * Auth-gated like the rest of the portal; routes carry the 'auth' middleware and
 * each action resolves the researcher + project context defensively.
 */
class ClaimLedgerController extends Controller
{
    use LogsResearchActivity;
    use AuthorizesProjectAccess;

    protected ClaimLedgerService $ledger;
    protected ResearchService $research;

    public function __construct()
    {
        $this->ledger = new ClaimLedgerService();
        $this->research = new ResearchService();
    }

    /** Resolve [project, researcher] for a project id, mirroring loadProjectContext. */
    protected function context(int $projectId): array
    {
        $researcher = $this->research->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            abort(403);
        }
        $project = DB::table('research_project')->where('id', $projectId)->first();
        if (! $project) {
            abort(404, 'Project not found');
        }
        // SECURITY (#1308-parity): authorize the caller against the resolved project.
        $this->assertProjectMember($projectId, (int) $researcher->id);
        return [$project, $researcher];
    }

    /** Build the shared sidebar payload (matches the rest of the research portal). */
    protected function sidebar(string $active = 'projects'): array
    {
        $unread = 0;
        try {
            $researcher = $this->research->getResearcherByUserId(Auth::id());
            if ($researcher) {
                $unread = (int) DB::table('research_notification')
                    ->where('researcher_id', $researcher->id)
                    ->where('is_read', 0)->count();
            }
        } catch (\Throwable $e) {
            // table may not exist yet
        }
        return ['sidebarActive' => $active, 'unreadNotifications' => $unread];
    }

    /** List + filter claims for a project. */
    public function index(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $filters = [
            'status' => $request->input('status'),
            'search' => $request->input('q'),
        ];

        $claims          = $this->ledger->listClaims($projectId, $filters);
        $statusCounts    = $this->ledger->statusCounts($projectId);
        $noCitation      = $this->ledger->claimsWithoutCitation($projectId);
        $overDependent   = $this->ledger->claimsOverDependent($projectId);

        return view('research::research.claim-ledger.index', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'claims', 'statusCounts', 'noCitation', 'overDependent', 'filters'),
            [
                'statuses'        => ClaimLedgerService::STATUSES,
                'statusBadges'    => ClaimLedgerService::STATUS_BADGES,
                'provenanceKinds' => ClaimLedgerService::PROVENANCE_KINDS,
                'confidenceLevels'=> ClaimLedgerService::CONFIDENCE_LEVELS,
                'evidenceTypes'   => ClaimLedgerService::EVIDENCE_TYPES,
            ]
        ));
    }

    /** Show a single claim with its evidence + attach candidates. */
    public function show(Request $request, int $projectId, int $claimId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $claim = $this->ledger->getClaim($projectId, $claimId);
        if (! $claim) {
            return redirect()->route('research.claims.index', $projectId)
                ->with('error', 'Claim not found.');
        }

        $evidence  = $this->ledger->getEvidence($claimId);
        $available = $this->ledger->availableEvidence($projectId);

        return view('research::research.claim-ledger.show', array_merge(
            $this->sidebar('projects'),
            compact('project', 'researcher', 'claim', 'evidence', 'available'),
            [
                'statuses'        => ClaimLedgerService::STATUSES,
                'statusBadges'    => ClaimLedgerService::STATUS_BADGES,
                'provenanceKinds' => ClaimLedgerService::PROVENANCE_KINDS,
                'confidenceLevels'=> ClaimLedgerService::CONFIDENCE_LEVELS,
                'evidenceTypes'   => ClaimLedgerService::EVIDENCE_TYPES,
                'sourceTypes'     => ClaimLedgerService::SOURCE_TYPES,
            ]
        ));
    }

    /** Create a new claim (POST). */
    public function store(Request $request, int $projectId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $validated = $request->validate([
            'text'                  => 'required|string|max:5000',
            'status'                => 'nullable|string|max:46',
            'assertion_type'        => 'nullable|string|max:60',
            'evidence_type'         => 'nullable|string|max:80',
            'confidence_level'      => 'nullable|string|max:40',
            'provenance_kind'       => 'nullable|string|max:40',
            'supporting_sources'    => 'nullable|string',
            'opposing_sources'      => 'nullable|string',
            'quotations'            => 'nullable|string',
            'method_theory_link'    => 'nullable|string',
            'researcher_notes'      => 'nullable|string',
            'unresolved_weaknesses' => 'nullable|string',
            'ethical_concerns'      => 'nullable|string',
        ]);

        $id = $this->ledger->createClaim($projectId, (int) $researcher->id, $validated);
        if (! $id) {
            return redirect()->route('research.claims.index', $projectId)
                ->with('error', 'Could not create the claim.');
        }
        $this->logResearchActivity('create', 'claim_ledger', (int) $id, $validated['text'] ?? null, ['method' => 'ClaimLedgerController@store'], $projectId);
        return redirect()->route('research.claims.show', [$projectId, $id])
            ->with('success', 'Claim added to the ledger.');
    }

    /** Update an existing claim (POST). */
    public function update(Request $request, int $projectId, int $claimId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        [$project, $researcher] = $this->context($projectId);

        $validated = $request->validate([
            'text'                  => 'required|string|max:5000',
            'status'                => 'nullable|string|max:46',
            'assertion_type'        => 'nullable|string|max:60',
            'evidence_type'         => 'nullable|string|max:80',
            'confidence_level'      => 'nullable|string|max:40',
            'provenance_kind'       => 'nullable|string|max:40',
            'supporting_sources'    => 'nullable|string',
            'opposing_sources'      => 'nullable|string',
            'quotations'            => 'nullable|string',
            'method_theory_link'    => 'nullable|string',
            'researcher_notes'      => 'nullable|string',
            'unresolved_weaknesses' => 'nullable|string',
            'ethical_concerns'      => 'nullable|string',
        ]);

        $ok = $this->ledger->updateClaim($projectId, $claimId, $validated);
        if ($ok) {
            $this->logResearchActivity('update', 'claim_ledger', $claimId, $validated['text'] ?? null, ['method' => 'ClaimLedgerController@update'], $projectId);
        }
        return redirect()->route('research.claims.show', [$projectId, $claimId])
            ->with($ok ? 'success' : 'error', $ok ? 'Claim updated.' : 'Could not update the claim.');
    }

    /** Set claim status (lifecycle transition) (POST). */
    public function setStatus(Request $request, int $projectId, int $claimId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $request->validate(['status' => 'required|string|max:46']);
        $ok = $this->ledger->setStatus($projectId, $claimId, (string) $request->input('status'));

        if ($ok) {
            $this->logResearchActivity('update', 'claim_ledger', $claimId, null, ['method' => 'ClaimLedgerController@setStatus', 'status' => (string) $request->input('status')], $projectId);
        }
        return redirect()->back()
            ->with($ok ? 'success' : 'error', $ok ? 'Status updated.' : 'Could not update status.');
    }

    /** Attach an evidence source to a claim (POST). */
    public function attachEvidence(Request $request, int $projectId, int $claimId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $validated = $request->validate([
            'source_type'  => 'required|string|max:50',
            'source_id'    => 'required|integer',
            'relationship' => 'nullable|string|max:26',
            'note'         => 'nullable|string|max:2000',
        ]);

        $ok = $this->ledger->attachEvidence(
            $projectId,
            $claimId,
            $validated['source_type'],
            (int) $validated['source_id'],
            (int) Auth::id(),
            $validated['relationship'] ?? 'supports',
            $validated['note'] ?? null
        );

        if ($ok) {
            $this->logResearchActivity('update', 'claim_ledger', $claimId, null, ['method' => 'ClaimLedgerController@attachEvidence'], $projectId);
        }
        return redirect()->route('research.claims.show', [$projectId, $claimId])
            ->with($ok ? 'success' : 'error', $ok ? 'Evidence attached.' : 'Could not attach evidence.');
    }

    /** Detach an evidence row from a claim (POST). */
    public function detachEvidence(Request $request, int $projectId, int $claimId, int $evidenceId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->ledger->detachEvidence($projectId, $claimId, $evidenceId);
        if ($ok) {
            $this->logResearchActivity('delete', 'claim_ledger', $claimId, null, ['method' => 'ClaimLedgerController@detachEvidence'], $projectId);
        }
        return redirect()->route('research.claims.show', [$projectId, $claimId])
            ->with($ok ? 'success' : 'error', $ok ? 'Evidence detached.' : 'Could not detach evidence.');
    }

    /** Delete a claim (POST). */
    public function destroy(Request $request, int $projectId, int $claimId)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->context($projectId);

        $ok = $this->ledger->deleteClaim($projectId, $claimId);
        if ($ok) {
            $this->logResearchActivity('delete', 'claim_ledger', $claimId, null, ['method' => 'ClaimLedgerController@destroy'], $projectId);
        }
        return redirect()->route('research.claims.index', $projectId)
            ->with($ok ? 'success' : 'error', $ok ? 'Claim deleted.' : 'Could not delete the claim.');
    }
}
