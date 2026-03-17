<?php

namespace AhgLoan\Controllers;

use App\Http\Controllers\Controller;
use AhgLoan\Services\LoanService;
use Illuminate\Http\Request;

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
        $stats = $this->service->getStatistics();

        return view('ahg-loan::loan.index', compact('loans', 'stats', 'params'));
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
        return view('ahg-loan::loan.create');
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
}
