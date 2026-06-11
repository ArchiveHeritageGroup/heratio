<?php

/**
 * LoanRequestController - admin-gated inter-institution loan-request workflow
 * for the federated GLAM network (#1203 loan slice).
 *
 *   GET  /federation/loans                worklist: incoming + outgoing,
 *                                          filterable by status + direction
 *   GET  /federation/loans/new            new loan-request form
 *   POST /federation/loans/save           create a loan request
 *   GET  /federation/loans/{id}           one loan request + its workflow
 *   POST /federation/loans/{id}/transition  move the request to a new status
 *
 * Admin-gated (auth + admin middleware in the route group). Two-segment+
 * paths (/federation/loans/...) so the locked /{slug} catch-all never
 * intercepts them. Reuses the union-catalogue member registry read-only;
 * the only writes are to the new federation_loan_request table.
 *
 * Fresh code under #1203 - never touches the locked F3 FederationController /
 * edit-peer view / Connectors / FederatedSearchService.
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

namespace AhgFederation\Controllers;

use AhgFederation\Services\LoanRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LoanRequestController extends Controller
{
    public function __construct(private LoanRequestService $service)
    {
    }

    /**
     * The loans worklist. Filterable by ?status= and ?direction=. The view
     * groups the rows into Incoming and Outgoing buckets relative to the
     * self-member.
     */
    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');
        $direction = (string) $request->query('direction', '');

        $rows = $this->service->list($status, $direction);

        // Split the (already direction-filtered) rows into the two buckets so
        // the worklist always reads incoming-then-outgoing regardless of the
        // active filter.
        $incoming = [];
        $outgoing = [];
        $other = [];
        foreach ($rows as $row) {
            if (($row->direction ?? '') === 'incoming') {
                $incoming[] = $row;
            } elseif (($row->direction ?? '') === 'outgoing') {
                $outgoing[] = $row;
            } else {
                $other[] = $row;
            }
        }

        return view('ahg-federation::loans.index', [
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'other' => $other,
            'counts' => $this->service->statusCounts($direction),
            'status' => $status,
            'direction' => $direction,
            'statuses' => LoanRequestService::STATUSES,
            'self' => $this->service->selfMember(),
        ]);
    }

    public function create()
    {
        return view('ahg-federation::loans.create', [
            'members' => $this->service->members(),
            'self' => $this->service->selfMember(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requesting_member_id' => ['required', 'integer', 'min:1'],
            'holding_member_id' => ['required', 'integer', 'min:1', 'different:requesting_member_id'],
            'item_ref' => ['nullable', 'string', 'max:255'],
            'item_title' => ['nullable', 'string', 'max:1024'],
            'purpose' => ['nullable', 'string', 'max:2048'],
            'needed_from' => ['nullable', 'date'],
            'needed_to' => ['nullable', 'date', 'after_or_equal:needed_from'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ]);

        $id = $this->service->create($data);

        if ($id === null) {
            return redirect()
                ->route('federation.loans.create')
                ->withInput()
                ->with('error', __('Could not create the loan request. The federation tables may not be installed yet.'));
        }

        return redirect()
            ->route('federation.loans.show', $id)
            ->with('status', __('Loan request created.'));
    }

    public function show(int $id)
    {
        $loan = $this->service->find($id);
        if (! $loan) {
            return redirect()
                ->route('federation.loans.index')
                ->with('error', __('That loan request could not be found.'));
        }

        return view('ahg-federation::loans.show', [
            'loan' => $loan,
            'requesting' => $this->service->findMember((int) $loan->requesting_member_id),
            'holding' => $this->service->findMember((int) $loan->holding_member_id),
            'allowed' => $this->service->allowedTransitions((string) $loan->status),
        ]);
    }

    public function transition(int $id, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:2048'],
        ]);

        $ok = $this->service->transition($id, $data['to'], $data['note'] ?? null);

        return redirect()
            ->route('federation.loans.show', $id)
            ->with($ok ? 'status' : 'error',
                $ok
                    ? __('Loan request updated to: ').$data['to']
                    : __('That status change is not allowed from the current state.'));
    }
}
