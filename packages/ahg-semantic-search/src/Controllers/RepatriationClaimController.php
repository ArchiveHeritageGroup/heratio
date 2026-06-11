<?php

/**
 * RepatriationClaimController - admin workflow for repatriation claims, the next
 * slice of the repatriation engine (north-star heratio#1207).
 *
 * Admin-gated (auth + admin) CRUD over the new displaced_heritage_claim table:
 *
 *   GET  /repatriation/claims                 index  - register of claims (filter by status)
 *   GET  /repatriation/claims/create          create - new-claim form (prefilled from ?item= when given)
 *   POST /repatriation/claims                  store  - register a claim
 *   GET  /repatriation/claims/{id}/edit        edit   - edit form
 *   POST /repatriation/claims/{id}             update - amend a claim
 *   POST /repatriation/claims/{id}/status      status - advance just the status
 *
 * Every write goes ONLY to the new table via RepatriationClaimService. The form
 * can be prefilled from a displaced-heritage item (?item=<information_object_id>)
 * by reading the existing detection register, read-only, so a curator registering
 * a claim from a traced item starts with the origin/holding context already
 * filled in. No existing table is written or ALTERed.
 *
 * Sensitive subject matter: claim status describes where a dialogue stands, never
 * a legal outcome. The framing disclaimer is surfaced on every screen. Forms have
 * full validation; the index never 500s (a missing table renders the empty-state).
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

use AhgSemanticSearch\Services\DisplacedHeritageService;
use AhgSemanticSearch\Services\RepatriationClaimService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RepatriationClaimController extends Controller
{
    protected RepatriationClaimService $service;

    public function __construct()
    {
        $this->service = new RepatriationClaimService;
    }

    /**
     * Register of claims, optionally narrowed to one status (?status=). Never
     * 500s: a missing table or any failure renders the dignified empty-state.
     */
    public function index(Request $request)
    {
        $statusFilter = trim((string) $request->query('status', ''));

        $claims = [];
        $counts = [];
        try {
            $claims = $this->service->list($statusFilter !== '' ? $statusFilter : null, 0);
            $counts = $this->service->statusCounts();
        } catch (\Throwable $e) {
            Log::info('[repatriation] index failed: '.$e->getMessage());
        }

        return view('ahg-semantic-search::repatriation.index', [
            'claims' => $claims,
            'counts' => $counts,
            'statuses' => RepatriationClaimService::STATUSES,
            'statusFilter' => $statusFilter,
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
            'total' => array_sum($counts),
        ]);
    }

    /**
     * New-claim form. When ?item=<information_object_id> is supplied and that
     * item is currently traced by the detection register, the form is prefilled
     * with the traced origin / holding context (read-only).
     */
    public function create(Request $request)
    {
        $itemRef = (int) $request->query('item', 0);

        $prefill = [
            'item_ref' => $itemRef > 0 ? $itemRef : '',
            'claimant_community' => '',
            'origin_place' => '',
            'current_holder' => '',
            'claim_status' => 'registered',
            'evidence_summary' => '',
            'contact' => '',
            'notes' => '',
        ];

        $tracedItem = null;
        if ($itemRef > 0) {
            $tracedItem = $this->tracedItem($itemRef);
            if ($tracedItem !== null) {
                $prefill['origin_place'] = (string) ($tracedItem['origin']['value'] ?? $tracedItem['origin_region'] ?? '');
                $prefill['current_holder'] = (string) ($tracedItem['holding']['value'] ?? $tracedItem['holding_region'] ?? '');
            }
        }

        return view('ahg-semantic-search::repatriation.form', [
            'mode' => 'create',
            'claim' => null,
            'prefill' => $prefill,
            'tracedItem' => $tracedItem,
            'statuses' => RepatriationClaimService::STATUSES,
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
        ]);
    }

    /**
     * Persist a new claim. Full validation; redirects to the new claim's edit
     * screen on success.
     */
    public function store(Request $request)
    {
        $data = $this->validateClaim($request, true);

        $id = $this->service->register($data, $this->userId($request));

        if ($id === null) {
            return back()
                ->withInput()
                ->with('error', __('The claim could not be registered. Please check the details and try again.'));
        }

        return redirect()
            ->route('repatriation.claims.edit', ['id' => $id])
            ->with('success', __('Repatriation claim registered.'));
    }

    /**
     * Edit form for an existing claim.
     */
    public function edit($id)
    {
        $claim = $this->service->find((int) $id);
        if ($claim === null) {
            abort(404);
        }

        return view('ahg-semantic-search::repatriation.form', [
            'mode' => 'edit',
            'claim' => $claim,
            'prefill' => $claim,
            'tracedItem' => $this->tracedItem((int) $claim['item_ref']),
            'statuses' => RepatriationClaimService::STATUSES,
            'disclaimer' => RepatriationClaimService::DISCLAIMER,
        ]);
    }

    /**
     * Amend an existing claim. Full validation; item_ref is immutable on edit.
     */
    public function update(Request $request, $id)
    {
        $claim = $this->service->find((int) $id);
        if ($claim === null) {
            abort(404);
        }

        $data = $this->validateClaim($request, false);

        $ok = $this->service->update((int) $id, $data);

        return redirect()
            ->route('repatriation.claims.edit', ['id' => (int) $id])
            ->with($ok ? 'success' : 'error', $ok
                ? __('Claim updated.')
                : __('The claim could not be updated.'));
    }

    /**
     * Advance just the status of a claim (quick action from the register).
     */
    public function status(Request $request, $id)
    {
        $claim = $this->service->find((int) $id);
        if ($claim === null) {
            abort(404);
        }

        $validated = $request->validate([
            'claim_status' => 'required|string|max:64',
        ]);

        $ok = $this->service->updateStatus((int) $id, $validated['claim_status']);

        return back()->with($ok ? 'success' : 'error', $ok
            ? __('Claim status updated.')
            : __('The status could not be updated.'));
    }

    /**
     * Shared validation for create/update. item_ref is required + must be a
     * positive integer only on create.
     *
     * @return array<string,mixed>
     */
    protected function validateClaim(Request $request, bool $requireItemRef): array
    {
        $rules = [
            'claimant_community' => 'nullable|string|max:512',
            'origin_place' => 'nullable|string|max:512',
            'current_holder' => 'nullable|string|max:512',
            'claim_status' => 'required|string|max:64',
            'evidence_summary' => 'nullable|string|max:20000',
            'contact' => 'nullable|string|max:512',
            'notes' => 'nullable|string|max:20000',
        ];
        if ($requireItemRef) {
            $rules['item_ref'] = 'required|integer|min:1';
        }

        $validated = $request->validate($rules);

        // Defensive: ensure status is one of the known workflow values (or a
        // dropdown value); the service re-normalises regardless.
        $validated['claim_status'] = $this->service->normaliseStatus($validated['claim_status'] ?? 'registered');

        return $validated;
    }

    /**
     * Read-only lookup of a single traced item from the existing detection
     * register, used to prefill the claim form and show origin/holding context.
     * Returns null when the item is not currently traced. Never throws.
     *
     * @return array<string,mixed>|null
     */
    protected function tracedItem(int $itemRef): ?array
    {
        if ($itemRef <= 0) {
            return null;
        }

        try {
            $report = (new DisplacedHeritageService)->scan(['limit' => 0]);
            $records = is_array($report['records'] ?? null) ? $report['records'] : [];
            foreach ($records as $r) {
                if ((int) ($r['id'] ?? 0) === $itemRef) {
                    return $r;
                }
            }
        } catch (\Throwable $e) {
            Log::info('[repatriation] traced-item lookup failed for '.$itemRef.': '.$e->getMessage());
        }

        return null;
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
