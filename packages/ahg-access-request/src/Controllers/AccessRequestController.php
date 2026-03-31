<?php

/**
 * AccessRequestController - Controller for Heratio
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


use AhgAccessRequest\Services\AccessRequestService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AccessRequestController extends Controller
{
    public function __construct(
        protected AccessRequestService $service
    ) {}

    /**
     * Browse all access requests.
     */
    public function browse()
    {
        $requests = $this->service->getAllRequests();

        return view('ahg-access-request::browse', compact('requests'));
    }

    /**
     * New access request form.
     */
    public function create()
    {
        return view('ahg-access-request::new');
    }

    /**
     * Request access to a specific object.
     */
    public function requestObject(Request $request, string $slug)
    {
        return view('ahg-access-request::request-object', compact('slug'));
    }

    /**
     * My requests listing.
     */
    public function myRequests()
    {
        $userId = auth()->id();
        $requests = $this->service->getMyRequests($userId);

        return view('ahg-access-request::my-requests', compact('requests'));
    }

    /**
     * Pending requests (admin/approver view).
     */
    public function pending()
    {
        $requests = $this->service->getPendingRequests();

        return view('ahg-access-request::pending', compact('requests'));
    }

    /**
     * View a single request.
     */
    public function view(string $id)
    {
        $accessRequest = $this->service->getRequest((int) $id);
        abort_unless($accessRequest, 404);

        return view('ahg-access-request::view', compact('accessRequest'));
    }

    /**
     * Manage approvers.
     */
    public function approvers()
    {
        $approvers = $this->service->getApprovers();

        return view('ahg-access-request::approvers', compact('approvers'));
    }

    /**
     * Store a new access request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'object_id' => 'required|integer',
            'reason' => 'required|string|max:2000',
        ]);

        $this->service->createRequest(auth()->id(), $validated);

        return redirect()->route('accessRequest.myRequests')->with('notice', 'Access request submitted.');
    }

    /**
     * Approve an access request.
     */
    public function approve(Request $request, string $id)
    {
        $this->service->approveRequest((int) $id, auth()->id(), $request->get('notes'));

        return redirect()->route('accessRequest.pending')->with('notice', 'Request approved.');
    }

    /**
     * Deny an access request.
     */
    public function deny(Request $request, string $id)
    {
        $this->service->denyRequest((int) $id, auth()->id(), $request->get('reason'));

        return redirect()->route('accessRequest.pending')->with('notice', 'Request denied.');
    }

    /**
     * Add an approver.
     */
    public function addApprover(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
        ]);

        $this->service->addApprover($validated['user_id']);

        return redirect()->route('accessRequest.approvers')->with('notice', 'Approver added.');
    }

    /**
     * Remove an approver.
     */
    public function removeApprover(string $id)
    {
        $this->service->removeApprover((int) $id);

        return redirect()->route('accessRequest.approvers')->with('notice', 'Approver removed.');
    }

    /**
     * Cancel an access request (by the requesting user).
     */
    public function cancel(string $id)
    {
        $this->service->cancelRequest((int) $id, auth()->id());

        return redirect()->route('accessRequest.myRequests')->with('notice', 'Access request cancelled.');
    }

    /**
     * Store a new object-specific access request.
     */
    public function storeObjectRequest(Request $request)
    {
        $validated = $request->validate([
            'object_id' => 'required|integer',
            'reason' => 'required|string|max:2000',
        ]);

        $this->service->createRequest(auth()->id(), $validated);

        return redirect()->route('accessRequest.myRequests')->with('notice', 'Object access request submitted.');
    }
}
