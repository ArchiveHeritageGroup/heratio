<?php

/**
 * LoanController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgLoan\Controllers;

use App\Http\Controllers\Controller;
use AhgLoan\Services\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Loan Management Controller.
 * Handles loan agreements, object lending, returns, documents, extensions, and status transitions.
 */
class LoanController extends Controller
{
    protected LoanService $service;

    public function __construct(LoanService $service)
    {
        $this->service = $service;
    }

    /**
     * Browse loans with filters.
     */
    public function index(Request $request)
    {
        $params = [
            'type'     => $request->input('type'),
            'status'   => $request->input('status'),
            'search'   => $request->input('search'),
            'overdue'  => $request->input('overdue'),
            'sector'   => $request->input('sector'),
            'sort'     => $request->input('sort', 'updated_at'),
            'dir'      => $request->input('dir', 'desc'),
            'page'     => $request->input('page', 1),
            'per_page' => $request->input('per_page', 25),
        ];

        $loans = $this->service->browse($params);

        // Statistics for sidebar
        $activeStatuses = ['on_loan', 'dispatched', 'in_transit', 'received'];
        $stats = [
            'total'                => DB::table('ahg_loan')->count(),
            'active_out'           => DB::table('ahg_loan')->where('loan_type', 'out')->whereIn('status', $activeStatuses)->count(),
            'active_in'            => DB::table('ahg_loan')->where('loan_type', 'in')->whereIn('status', $activeStatuses)->count(),
            'overdue'              => DB::table('ahg_loan')->where('end_date', '<', now())->whereIn('status', $activeStatuses)->count(),
            'due_this_month'       => DB::table('ahg_loan')->whereBetween('end_date', [now(), now()->addDays(30)])->whereIn('status', $activeStatuses)->count(),
            'total_insurance_value' => DB::table('ahg_loan')->sum('insurance_value'),
        ];

        // Top 5 overdue loans for sidebar
        $overdue = DB::table('ahg_loan')
            ->where('end_date', '<', now())
            ->whereIn('status', $activeStatuses)
            ->select('id', 'loan_number', 'partner_institution', 'end_date')
            ->orderBy('end_date')
            ->limit(5)
            ->get();

        // Top 5 due within 30 days for sidebar
        $dueSoon = DB::table('ahg_loan')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->whereIn('status', $activeStatuses)
            ->select('id', 'loan_number', 'partner_institution', 'end_date')
            ->orderBy('end_date')
            ->limit(5)
            ->get();

        // Purpose mapping
        $purposes = [
            'exhibition'   => 'Exhibition',
            'research'     => 'Research',
            'conservation' => 'Conservation',
            'photography'  => 'Photography',
            'education'    => 'Education',
            'other'        => 'Other',
        ];

        return view('ahg-loan::loan.index', compact('loans', 'stats', 'overdue', 'dueSoon', 'params', 'purposes'));
    }

    /**
     * Show a single loan with all details.
     */
    public function show(int $id)
    {
        $loan = $this->service->get($id);

        if (!$loan) {
            abort(404);
        }

        $validTransitions = $this->service->getValidTransitions($loan->status);

        return view('ahg-loan::loan.show', compact('loan', 'validTransitions'));
    }

    /**
     * Create loan form (GET).
     */
    public function create(Request $request)
    {
        $objectId = $request->input('object_id');
        $prefill = [];

        if ($objectId) {
            $culture = app()->getLocale();
            $io = DB::table('information_object')
                ->join('information_object_i18n', function ($j) use ($culture) {
                    $j->on('information_object.id', '=', 'information_object_i18n.id')
                       ->where('information_object_i18n.culture', $culture);
                })
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->where('information_object.id', $objectId)
                ->select('information_object.id', 'information_object.identifier',
                         'information_object_i18n.title', 'information_object_i18n.scope_and_content',
                         'slug.slug')
                ->first();

            if ($io) {
                $prefill['title'] = $io->title;
                $prefill['description'] = $io->scope_and_content;
                $prefill['object_title'] = $io->title;
                $prefill['object_slug'] = $io->slug;
                $prefill['object_identifier'] = $io->identifier;
            }
        }

        return view('ahg-loan::loan.create', compact('prefill'));
    }

    /**
     * Store new loan (POST).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'loan_type'               => 'required|in:out,in',
            'sector'                  => 'required|string|max:44',
            'title'                   => 'nullable|string|max:500',
            'description'             => 'nullable|string',
            'purpose'                 => 'nullable|string|max:100',
            'partner_institution'     => 'required|string|max:500',
            'partner_contact_name'    => 'nullable|string|max:255',
            'partner_contact_email'   => 'nullable|email|max:255',
            'partner_contact_phone'   => 'nullable|string|max:100',
            'partner_address'         => 'nullable|string',
            'request_date'            => 'nullable|date',
            'start_date'              => 'nullable|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'insurance_type'          => 'nullable|string|max:53',
            'insurance_value'         => 'nullable|numeric|min:0',
            'insurance_currency'      => 'nullable|string|max:3',
            'insurance_policy_number' => 'nullable|string|max:100',
            'insurance_provider'      => 'nullable|string|max:255',
            'loan_fee'                => 'nullable|numeric|min:0',
            'loan_fee_currency'       => 'nullable|string|max:3',
            'repository_id'           => 'nullable|integer',
            'notes'                   => 'nullable|string',
        ]);

        $id = $this->service->create($validated, auth()->id());

        // Redirect back to originating record if object_id was provided
        $objectId = $request->input('object_id');
        if ($objectId) {
            $slug = \Illuminate\Support\Facades\DB::table('slug')->where('object_id', $objectId)->value('slug');
            if ($slug) {
                return redirect('/' . $slug)->with('success', 'Loan created successfully.');
            }
        }

        return redirect()->route('loan.show', $id)
            ->with('success', 'Loan created successfully.');
    }

    /**
     * Edit loan form (GET).
     */
    public function edit(Request $request, int $id)
    {
        $loan = $this->service->get($id);

        if (!$loan) {
            abort(404);
        }

        return view('ahg-loan::loan.edit', compact('loan'));
    }

    /**
     * Update loan (POST).
     */
    public function update(Request $request, int $id)
    {
        $loan = $this->service->get($id);
        if (!$loan) {
            abort(404);
        }

        $validated = $request->validate([
            'loan_type'               => 'required|in:out,in',
            'sector'                  => 'required|string|max:44',
            'title'                   => 'nullable|string|max:500',
            'description'             => 'nullable|string',
            'purpose'                 => 'nullable|string|max:100',
            'partner_institution'     => 'required|string|max:500',
            'partner_contact_name'    => 'nullable|string|max:255',
            'partner_contact_email'   => 'nullable|email|max:255',
            'partner_contact_phone'   => 'nullable|string|max:100',
            'partner_address'         => 'nullable|string',
            'request_date'            => 'nullable|date',
            'start_date'              => 'nullable|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'insurance_type'          => 'nullable|string|max:53',
            'insurance_value'         => 'nullable|numeric|min:0',
            'insurance_currency'      => 'nullable|string|max:3',
            'insurance_policy_number' => 'nullable|string|max:100',
            'insurance_provider'      => 'nullable|string|max:255',
            'loan_fee'                => 'nullable|numeric|min:0',
            'loan_fee_currency'       => 'nullable|string|max:3',
            'repository_id'           => 'nullable|integer',
            'notes'                   => 'nullable|string',
        ]);

        $validated['updated_by'] = auth()->id();
        $this->service->update($id, $validated);

        return redirect()->route('loan.show', $id)
            ->with('success', 'Loan updated successfully.');
    }

    /**
     * Delete a loan.
     */
    public function delete(int $id)
    {
        $this->service->delete($id);

        return redirect()->route('loan.index')
            ->with('success', 'Loan deleted successfully.');
    }

    /**
     * Add an object to a loan.
     */
    public function addObject(Request $request, int $id)
    {
        $request->validate([
            'object_id'            => 'required|integer',
            'insurance_value'      => 'nullable|numeric|min:0',
            'special_requirements' => 'nullable|string',
            'display_requirements' => 'nullable|string',
        ]);

        $this->service->addObject($id, $request->input('object_id'), $request->only([
            'insurance_value', 'special_requirements', 'display_requirements',
        ]));

        return redirect()->route('loan.show', $id)
            ->with('success', 'Object added to loan.');
    }

    /**
     * Remove an object from a loan.
     */
    public function removeObject(Request $request, int $id, int $objectId)
    {
        $this->service->removeObject($id, $objectId);

        return redirect()->route('loan.show', $id)
            ->with('success', 'Object removed from loan.');
    }

    /**
     * Transition loan status.
     */
    public function transition(Request $request, int $id)
    {
        $request->validate([
            'new_status' => 'required|string',
            'comment'    => 'nullable|string|max:1000',
        ]);

        $result = $this->service->transition(
            $id,
            $request->input('new_status'),
            auth()->id(),
            $request->input('comment')
        );

        if (!$result) {
            return redirect()->route('loan.show', $id)
                ->with('error', 'Invalid status transition.');
        }

        return redirect()->route('loan.show', $id)
            ->with('success', 'Loan status updated to: ' . str_replace('_', ' ', $request->input('new_status')));
    }

    /**
     * Extend loan end date.
     */
    public function extend(Request $request, int $id)
    {
        $request->validate([
            'new_end_date' => 'required|date|after:today',
            'reason'       => 'required|string|max:1000',
        ]);

        $this->service->extend(
            $id,
            $request->input('new_end_date'),
            $request->input('reason'),
            auth()->id()
        );

        return redirect()->route('loan.show', $id)
            ->with('success', 'Loan extended successfully.');
    }

    /**
     * Record loan return.
     */
    public function returnLoan(Request $request, int $id)
    {
        $request->validate([
            'return_date' => 'required|date',
            'notes'       => 'nullable|string|max:1000',
        ]);

        $this->service->recordReturn(
            $id,
            $request->input('return_date'),
            $request->input('notes'),
            auth()->id()
        );

        return redirect()->route('loan.show', $id)
            ->with('success', 'Loan return recorded.');
    }

    /**
     * Upload a document to a loan.
     */
    public function uploadDocument(Request $request, int $id)
    {
        $request->validate([
            'document'      => 'required|file|max:20480',
            'document_type' => 'required|string|max:50',
        ]);

        $this->service->uploadDocument(
            $id,
            $request->file('document'),
            $request->input('document_type'),
            auth()->id()
        );

        return redirect()->route('loan.show', $id)
            ->with('success', 'Document uploaded successfully.');
    }

    /**
     * AJAX: Search information objects for autocomplete.
     */
    public function searchObjects(Request $request)
    {
        $query = $request->input('q', '');
        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = $this->service->searchObjects($query);

        return response()->json(['results' => $results]);
    }

    public function dashboard() { return view('ahg-loan::loan-dashboard', ['activeCount'=>0,'overdueCount'=>0,'returningCount'=>0,'completedCount'=>0]); }
}
