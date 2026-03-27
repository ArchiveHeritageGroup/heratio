<?php

namespace AhgAccessRequest\Controllers;

use AhgAccessRequest\Services\AccessRequestService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AccessRequestController extends Controller
{
    public function __construct(
        protected AccessRequestService $service
    ) {}

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
}
