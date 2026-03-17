<?php

namespace AhgAcl\Controllers;

use AhgAcl\Services\AclService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * List active access request approvers.
     */
    public function approvers()
    {
        $approvers = collect();
        $classifications = $this->service->getClassificationLevels();

        if (Schema::hasTable('access_request_approver')) {
            $approvers = DB::table('access_request_approver as ara')
                ->join('user as u', 'u.id', '=', 'ara.user_id')
                ->leftJoin('actor_i18n as ai', function ($join) {
                    $join->on('ai.id', '=', 'u.id')
                         ->where('ai.culture', '=', 'en');
                })
                ->leftJoin('user_security_clearance as uc', 'uc.user_id', '=', 'u.id')
                ->leftJoin('security_classification as sc', 'sc.id', '=', 'uc.classification_id')
                ->select(
                    'ara.id',
                    'ara.user_id',
                    'ara.min_classification_level',
                    'ara.max_classification_level',
                    'ara.email_notifications',
                    'ara.active',
                    'ara.created_at',
                    'u.username',
                    'u.email',
                    'ai.authorized_form_of_name as display_name',
                    'sc.name as clearance_name',
                    'sc.code as clearance_code',
                    'sc.color as clearance_color',
                    'sc.level as clearance_level'
                )
                ->where('ara.active', 1)
                ->orderBy('ai.authorized_form_of_name')
                ->get();
        }

        // Get active approver user IDs to exclude from the add dropdown
        $approverUserIds = $approvers->pluck('user_id')->toArray();

        $availableUsers = $this->service->getAllUsers()
            ->filter(function ($user) use ($approverUserIds) {
                return !in_array($user->id, $approverUserIds);
            });

        return view('ahg-acl::approvers', compact('approvers', 'availableUsers', 'classifications'));
    }

    /**
     * POST: Add a new access request approver.
     */
    public function addApprover(Request $request)
    {
        $request->validate([
            'user_id'                  => 'required|integer',
            'min_classification_level' => 'required|integer',
            'max_classification_level' => 'required|integer',
            'email_notifications'      => 'nullable|boolean',
        ]);

        $now = now()->toDateTimeString();

        // Check if user is already an active approver
        $existing = DB::table('access_request_approver')
            ->where('user_id', (int) $request->input('user_id'))
            ->where('active', 1)
            ->first();

        if ($existing) {
            return redirect()->route('acl.approvers')
                ->with('error', 'This user is already an active approver.');
        }

        DB::table('access_request_approver')->insert([
            'user_id'                  => (int) $request->input('user_id'),
            'min_classification_level' => (int) $request->input('min_classification_level'),
            'max_classification_level' => (int) $request->input('max_classification_level'),
            'email_notifications'      => $request->boolean('email_notifications') ? 1 : 0,
            'active'                   => 1,
            'created_at'               => $now,
        ]);

        return redirect()->route('acl.approvers')
            ->with('success', 'Approver added successfully.');
    }

    /**
     * POST: Deactivate an access request approver.
     */
    public function removeApprover(int $id)
    {
        DB::table('access_request_approver')
            ->where('id', $id)
            ->update(['active' => 0]);

        return redirect()->route('acl.approvers')
            ->with('success', 'Approver removed successfully.');
    }
}
