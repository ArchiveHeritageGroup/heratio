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
    public function view(int $id)
    {
        $accessRequest = $this->service->getRequest($id);
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
}
