<?php

namespace AhgAcl\Controllers;

use AhgAcl\Services\AclService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AclController extends Controller
{
    private AclService $service;

    public function __construct(AclService $service)
    {
        $this->service = $service;
    }

    /**
     * List all ACL groups with member counts.
     */
    public function groups()
    {
        $groups = $this->service->getGroups();

        // Count permissions per group
        foreach ($groups as $group) {
            $group->permissions_count = $this->service->getGroupPermissions($group->id)->count();
        }

        return view('ahg-acl::groups', compact('groups'));
    }

    /**
     * GET: Show group with members and permissions.
     * POST: Update permissions for the group.
     */
    public function editGroup(Request $request, int $id)
    {
        if ($request->isMethod('post')) {
            $action = $request->input('_action');

            if ($action === 'add_permission') {
                $request->validate([
                    'action'     => 'required|string|max:255',
                    'grant_deny' => 'required|in:0,1',
                ]);

                $this->service->savePermission([
                    'group_id'   => $id,
                    'action'     => $request->input('action'),
                    'object_id'  => $request->input('object_id') ?: null,
                    'grant_deny' => (int) $request->input('grant_deny'),
                ]);

                return redirect()->route('acl.edit-group', ['id' => $id])
                    ->with('success', 'Permission added successfully.');
            }

            if ($action === 'delete_permission') {
                $request->validate([
                    'permission_id' => 'required|integer',
                ]);

                $this->service->deletePermission((int) $request->input('permission_id'));

                return redirect()->route('acl.edit-group', ['id' => $id])
                    ->with('success', 'Permission removed successfully.');
            }

            return redirect()->route('acl.edit-group', ['id' => $id]);
        }

        $group = $this->service->getGroup($id);

        if (!$group) {
            abort(404, 'Group not found.');
        }

        $allUsers = $this->service->getAllUsers();

        return view('ahg-acl::edit-group', compact('group', 'allUsers'));
    }

    /**
     * POST: Add a user to a group.
     */
    public function addMember(Request $request, int $groupId)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $this->service->addUserToGroup((int) $request->input('user_id'), $groupId);

        return redirect()->route('acl.edit-group', ['id' => $groupId])
            ->with('success', 'Member added to group.');
    }

    /**
     * POST: Remove a user from a group.
     */
    public function removeMember(Request $request, int $groupId, int $userId)
    {
        $this->service->removeUserFromGroup($userId, $groupId);

        return redirect()->route('acl.edit-group', ['id' => $groupId])
            ->with('success', 'Member removed from group.');
    }

    /**
     * List security classification levels.
     */
    public function classifications()
    {
        $classifications = $this->service->getClassificationLevels();

        return view('ahg-acl::classifications', compact('classifications'));
    }

    /**
     * List user security clearances.
     */
    public function clearances()
    {
        $users = $this->service->getAllUsers();
        $classifications = $this->service->getClassificationLevels();

        // Get clearance for each user
        $clearances = collect();
        foreach ($users as $user) {
            $clearance = $this->service->getUserClearance($user->id);
            if ($clearance) {
                $clearance->username = $user->username;
                $clearance->user_display_name = $user->display_name ?? $user->username;
                $clearances->push($clearance);
            }
        }

        return view('ahg-acl::clearances', compact('clearances', 'users', 'classifications'));
    }

    /**
     * POST: Set a user's security clearance.
     */
    public function setClearance(Request $request)
    {
        $request->validate([
            'user_id'           => 'required|integer',
            'classification_id' => 'required|integer',
        ]);

        $grantedBy = auth()->id() ?? 1;

        $this->service->setUserClearance(
            (int) $request->input('user_id'),
            (int) $request->input('classification_id'),
            $grantedBy
        );

        return redirect()->route('acl.clearances')
            ->with('success', 'User clearance updated successfully.');
    }

    /**
     * List pending security access requests.
     */
    public function accessRequests(Request $request)
    {
        $status = $request->input('status', 'pending');
        $requests = $this->service->getAccessRequests($status ?: null);

        return view('ahg-acl::access-requests', compact('requests', 'status'));
    }

    /**
     * POST: Approve or deny an access request.
     */
    public function reviewRequest(Request $request, int $id)
    {
        $request->validate([
            'decision' => 'required|in:approved,denied',
        ]);

        $reviewerId = auth()->id() ?? 1;
        $notes = $request->input('notes');
        $decision = $request->input('decision');

        if ($decision === 'approved') {
            $this->service->approveAccessRequest($id, $reviewerId, $notes);
        } else {
            $this->service->denyAccessRequest($id, $reviewerId, $notes);
        }

        return redirect()->route('acl.access-requests')
            ->with('success', 'Access request ' . $decision . '.');
    }

    /**
     * Security audit log.
     */
    public function auditLog(Request $request)
    {
        $limit = (int) ($request->input('limit', 50));
        $entries = $this->service->getSecurityAuditLog($limit);

        return view('ahg-acl::audit-log', compact('entries', 'limit'));
    }
}
