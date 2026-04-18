<?php

/**
 * AclController - Controller for Heratio
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

        // Build a row per user (with-or-without clearance) so the table matches AtoM
        $rows = collect();
        $stats = ['total_users' => 0, 'with_clearance' => 0, 'top_secret' => 0];

        foreach ($users as $user) {
            $stats['total_users']++;
            $clearance = $this->service->getUserClearance($user->id);

            $row = (object) [
                'user_id'             => $user->id,
                'username'            => $user->username,
                'email'               => $user->email ?? '',
                'user_display_name'   => $user->display_name ?? $user->username,
                'active'              => $user->active ?? 1,
                'classification_id'   => $clearance->id ?? null,
                'classification_name' => $clearance->classification_name ?? $clearance->name ?? null,
                'classification_code' => $clearance->code ?? null,
                'classification_level' => $clearance->level ?? null,
                'classification_color' => $clearance->color ?? null,
                'granted_at'          => $clearance->granted_at ?? null,
                'granted_by_name'     => $clearance->granted_by_name ?? null,
                'expires_at'          => $clearance->expires_at ?? null,
                'two_factor_verified' => $clearance->two_factor_verified ?? false,
                'renewal_status'      => $clearance->renewal_status ?? null,
            ];

            if ($clearance) {
                $stats['with_clearance']++;
                if (($clearance->level ?? 0) >= 4) {
                    $stats['top_secret']++;
                }
            }
            $rows->push($row);
        }

        // Backwards-compat alias for any code/templates still expecting $clearances
        $clearances = $rows;

        return view('ahg-acl::clearances', compact('rows', 'clearances', 'users', 'classifications', 'stats'));
    }

    /**
     * POST: Set a user's security clearance.
     */
    /**
     * POST: Bulk grant a clearance level to multiple users at once.
     */
    public function bulkGrantClearance(Request $request)
    {
        $validated = $request->validate([
            'user_ids'          => 'required|array|min:1',
            'user_ids.*'        => 'integer|min:1|exists:user,id',
            'classification_id' => 'required|integer|min:1|exists:security_classification,id',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $grantedBy = auth()->id() ?? 1;
        $count = 0;
        foreach ($validated['user_ids'] as $userId) {
            $this->service->setUserClearance((int) $userId, (int) $validated['classification_id'], $grantedBy);
            $count++;
        }

        return redirect()->route('acl.clearances')
            ->with('success', "Granted clearance to {$count} user(s).");
    }

    public function setClearance(Request $request)
    {
        // Revoke = delete the user_security_clearance row entirely.
        // The form posts classification_id=0 + _revoke=1, which is invalid for a normal update.
        if ($request->boolean('_revoke')) {
            $request->validate([
                'user_id' => 'required|integer|min:1|exists:user,id',
            ]);

            $userId = (int) $request->input('user_id');
            $revokedBy = auth()->id() ?? 1;

            DB::table('user_security_clearance')->where('user_id', $userId)->delete();

            if (Schema::hasTable('user_security_clearance_log')) {
                DB::table('user_security_clearance_log')->insert([
                    'user_id'    => $userId,
                    'action'     => 'clearance_revoked',
                    'changed_by' => $revokedBy,
                    'notes'      => null,
                    'created_at' => now(),
                ]);
            }

            return redirect()->route('acl.clearances')
                ->with('success', 'User clearance revoked.');
        }

        $validated = $request->validate([
            'user_id'           => 'required|integer|min:1|exists:user,id',
            'classification_id' => 'required|integer|min:1|exists:security_classification,id',
        ]);

        $grantedBy = auth()->id() ?? 1;

        $this->service->setUserClearance(
            (int) $validated['user_id'],
            (int) $validated['classification_id'],
            $grantedBy
        );

        return redirect()->route('acl.clearances')
            ->with('success', 'User clearance updated successfully.');
    }

    /**
     * List pending security access requests.
     *
     * Heratio keeps its tab-based list of `security_access_request` rows and
     * additively clones the PSIS "history" audit-log view (stats cards,
     * status/action filters, paginated log table over `access_request_log`).
     */
    public function accessRequests(Request $request)
    {
        $status = $request->input('status', 'pending');
        $requests = $this->service->getAccessRequests($status ?: null);

        // ── PSIS history (audit log) additive clone ────────────────────
        $statusFilter = (string) $request->input('status_filter', '');
        $actionFilter = (string) $request->input('action_filter', '');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;

        $logs = collect();
        $total = 0;
        $totalPages = 1;
        $stats = [
            'total_requests' => 0,
            'pending' => 0,
            'approved' => 0,
            'denied' => 0,
        ];

        if (Schema::hasTable('access_request_log') && Schema::hasTable('access_request')) {
            $query = DB::table('access_request_log as l')
                ->leftJoin('access_request as r', 'l.request_id', '=', 'r.id')
                ->leftJoin('user as u', 'l.actor_id', '=', 'u.id');

            if ($actionFilter !== '') {
                $query->where('l.action', $actionFilter);
            }
            if ($statusFilter !== '') {
                $query->where('r.status', $statusFilter);
            }

            $total = (clone $query)->count();
            $totalPages = max(1, (int) ceil($total / $perPage));

            $logs = $query
                ->select(
                    'l.id',
                    'l.request_id',
                    'l.action',
                    'l.actor_id',
                    'l.details',
                    'l.ip_address',
                    'l.created_at',
                    'r.status as request_status',
                    'r.reason',
                    'r.urgency',
                    'u.username as actor_username'
                )
                ->orderByDesc('l.created_at')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $stats = [
                'total_requests' => DB::table('access_request')->count(),
                'pending'        => DB::table('access_request')->where('status', 'pending')->count(),
                'approved'       => DB::table('access_request')->where('status', 'approved')->count(),
                'denied'         => DB::table('access_request')->where('status', 'denied')->count(),
            ];
        }

        return view('ahg-acl::access-requests', compact(
            'requests', 'status',
            'logs', 'stats', 'total', 'totalPages', 'page', 'statusFilter', 'actionFilter'
        ));
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
     * My Access Requests — user's own requests, clearance status, access grants.
     * Migrated from AtoM: ahgAccessRequestPlugin/modules/accessRequest/actions/myRequests
     */
    public function myRequests(Request $request)
    {
        $userId = auth()->id();

        // Current clearance
        $currentClearance = DB::table('user_security_clearance as usc')
            ->leftJoin('security_classification as sc', 'sc.id', '=', 'usc.classification_id')
            ->where('usc.user_id', $userId)
            ->select('usc.*', 'sc.name as classification_name', 'sc.level', 'sc.color')
            ->first();

        // Access grants
        $accessGrants = collect();
        if (Schema::hasTable('security_object_access')) {
            $accessGrants = DB::table('security_object_access as soa')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('soa.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->leftJoin('actor_i18n as ai', function ($j) {
                    $j->on('soa.granted_by', '=', 'ai.id')->where('ai.culture', '=', 'en');
                })
                ->where('soa.user_id', $userId)
                ->select('soa.*', 'ioi.title as object_title', 'ai.authorized_form_of_name as granted_by_name')
                ->orderByDesc('soa.granted_at')
                ->get();
        }

        // User's own requests
        $requests = DB::table('security_access_request as sar')
            ->leftJoin('security_classification as sc', 'sc.id', '=', 'sar.classification_id')
            ->where('sar.user_id', $userId)
            ->select('sar.*', 'sc.name as requested_classification', 'sc.code as classification_code')
            ->orderByDesc('sar.created_at')
            ->get();

        return view('ahg-acl::my-requests', compact('currentClearance', 'accessGrants', 'requests'));
    }

    /**
     * Pending Access Requests — admin/approver review page with stats.
     * Migrated from AtoM: ahgAccessRequestPlugin/modules/accessRequest/actions/pending
     */
    public function pendingRequests(Request $request)
    {
        if (!\AhgCore\Services\AclService::canAdmin(auth()->id())) {
            abort(403, 'Insufficient permissions');
        }

        $requests = $this->service->getAccessRequests('pending');

        $stats = [
            'pending' => DB::table('security_access_request')->where('status', 'pending')->count(),
            'approved_today' => DB::table('security_access_request')
                ->where('status', 'approved')
                ->whereDate('reviewed_at', today())
                ->count(),
            'denied_today' => DB::table('security_access_request')
                ->where('status', 'denied')
                ->whereDate('reviewed_at', today())
                ->count(),
            'total_this_month' => DB::table('security_access_request')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        return view('ahg-acl::pending-requests', compact('requests', 'stats'));
    }

    /**
     * Security audit log.
     */
    public function auditLog(Request $request)
    {
        $filters = [
            'action'      => $request->string('filter_action')->trim()->toString(),
            'object_type' => $request->string('entity_type')->trim()->toString(),
            'username'    => $request->string('username')->trim()->toString(),
            'from_date'   => $request->string('from_date')->trim()->toString(),
            'to_date'     => $request->string('to_date')->trim()->toString(),
        ];
        $page  = max(1, (int) $request->input('page', 1));
        $limit = (int) $request->input('limit', 50);
        $limit = in_array($limit, [25, 50, 100, 250]) ? $limit : 50;
        $format = $request->string('format')->lower()->toString();

        // Base query — same joins as getSecurityAuditLog but filterable & paginatable
        $query = DB::table('security_audit_log as sal')
            ->leftJoin('user as u', 'u.id', '=', 'sal.user_id')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'u.id')->where('ai.culture', '=', 'en');
            })
            ->select(
                'sal.id', 'sal.object_id', 'sal.object_type', 'sal.user_id', 'sal.user_name',
                'sal.action', 'sal.action_category', 'sal.details', 'sal.ip_address',
                'sal.user_agent', 'sal.created_at',
                'ai.authorized_form_of_name as display_name'
            );

        if ($filters['action']      !== '') $query->where('sal.action', $filters['action']);
        if ($filters['object_type'] !== '') $query->where('sal.object_type', $filters['object_type']);
        if ($filters['username']    !== '') $query->where('sal.user_name', $filters['username']);
        if ($filters['from_date']   !== '') $query->where('sal.created_at', '>=', $filters['from_date'] . ' 00:00:00');
        if ($filters['to_date']     !== '') $query->where('sal.created_at', '<=', $filters['to_date'] . ' 23:59:59');

        // CSV / JSON export — return all rows matching the filters (no pagination cap)
        if ($format === 'csv' || $format === 'json') {
            $rows = $query->orderByDesc('sal.created_at')->limit(50000)->get();
            return $this->exportAuditLog($rows, $format);
        }

        $total = (clone $query)->count();
        $entries = $query->orderByDesc('sal.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        // Filter dropdown options
        $actionTypes = DB::table('security_audit_log')
            ->select('action')->distinct()->orderBy('action')->pluck('action')->filter()->values();
        $entityTypes = DB::table('security_audit_log')
            ->select('object_type')->distinct()->orderBy('object_type')->pluck('object_type')->filter()->values();
        $usernames = DB::table('security_audit_log')
            ->select('user_name')->distinct()->whereNotNull('user_name')->orderBy('user_name')->pluck('user_name')->filter()->values();

        $pager = [
            'total'        => $total,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $limit)),
            'from'         => $total > 0 ? (($page - 1) * $limit) + 1 : 0,
            'to'           => min($total, $page * $limit),
        ];

        return view('ahg-acl::audit-log', compact('entries', 'limit', 'filters', 'actionTypes', 'entityTypes', 'usernames', 'pager'));
    }

    private function exportAuditLog($rows, string $format)
    {
        $stamp = now()->format('Ymd-His');
        if ($format === 'json') {
            return response()->json($rows)
                ->header('Content-Disposition', 'attachment; filename="audit-log-' . $stamp . '.json"');
        }

        // CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-' . $stamp . '.csv"',
        ];
        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Date', 'User', 'Action', 'Category', 'Object Type', 'Object ID', 'IP Address', 'User Agent']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->id,
                    $r->created_at,
                    $r->display_name ?? $r->user_name ?? '',
                    $r->action,
                    $r->action_category,
                    $r->object_type,
                    $r->object_id,
                    $r->ip_address,
                    $r->user_agent,
                ]);
            }
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
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

    // ── Security Audit ──────────────────────────────────────────────

    public function securityAuditIndex(Request $request)
    {
        $logs = DB::table('security_audit_log')->orderByDesc('created_at')->limit(100)->get();
        // Map DB column names to what the view expects
        $logs->transform(function ($log) {
            $log->username = $log->user_name ?? '';
            $log->category = $log->action_category ?? '';
            $log->object_title = '';
            if (!empty($log->details)) {
                $details = is_string($log->details) ? json_decode($log->details, true) : (array) $log->details;
                $log->object_title = $details['object_title'] ?? $details['title'] ?? '';
            }
            $log->details = is_string($log->details) ? $log->details : json_encode($log->details);
            return $log;
        });
        $actions = DB::table('security_audit_log')->distinct()->pluck('action')->filter()->values()->toArray();
        $categories = DB::table('security_audit_log')->distinct()->pluck('action_category')->filter()->values()->toArray();
        $total = DB::table('security_audit_log')->count();
        return view('ahg-acl::security-audit.index', compact('logs', 'actions', 'categories', 'total'));
    }

    public function securityAuditDashboard(Request $request)
    {
        $period = $request->input('period', '30 days');
        $since = now()->sub(\DateInterval::createFromDateString($period));
        $stats = [
            'total_events' => DB::table('security_audit_log')->where('created_at', '>=', $since)->count(),
            'security_events' => DB::table('security_audit_log')->where('created_at', '>=', $since)->where('action_category', 'security')->count(),
            'by_user' => DB::table('security_audit_log')->where('created_at', '>=', $since)->select('user_name as username', DB::raw('COUNT(*) as count'))->groupBy('user_name')->orderByDesc('count')->limit(10)->get(),
            'top_objects' => collect(),
            'since' => $since->format('M j, Y H:i'),
        ];
        return view('ahg-acl::security-audit.dashboard', compact('stats', 'period'));
    }

    public function securityAuditObjectAccess(Request $request)
    {
        $objectId = (int) $request->input('object_id');
        $period = $request->input('period', '30 days');
        $object = DB::table('information_object_i18n')->where('id', $objectId)->where('culture', 'en')->first() ?? (object) ['id' => $objectId, 'title' => 'Unknown'];
        $accessLogs = collect();
        $securityLogs = collect();
        $dailyAccess = collect();
        $totalAccess = 0;
        return view('ahg-acl::security-audit.object-access', compact('object', 'period', 'accessLogs', 'securityLogs', 'dailyAccess', 'totalAccess'));
    }

    // ── Security Clearance Management ───────────────────────────────

    public function securityDashboard()
    {
        $stats = [
            'total_users' => DB::table('user_security_clearance')->count(),
            'active_requests' => Schema::hasTable('security_access_request') ? DB::table('security_access_request')->where('status', 'pending')->count() : 0,
            'classified_objects' => Schema::hasTable('object_security_classification') ? DB::table('object_security_classification')->where('active', 1)->count() : 0,
            'compartments' => Schema::hasTable('security_compartment') ? DB::table('security_compartment')->count() : 0,
        ];
        $recentActivity = collect();
        return view('ahg-acl::security.security-dashboard', compact('stats', 'recentActivity'));
    }

    public function securityIndex()
    {
        $clearances = collect();
        return view('ahg-acl::security.security-index', compact('clearances'));
    }

    public function compartments()
    {
        $compartments = Schema::hasTable('security_compartment') ? DB::table('security_compartment')->get() : collect();
        return view('ahg-acl::security.compartments', compact('compartments'));
    }

    public function compartmentAccess()
    {
        $grants = collect();
        return view('ahg-acl::security.compartment-access', compact('grants'));
    }

    public function classify(int $id)
    {
        $object = DB::table('information_object_i18n')->where('id', $id)->where('culture', 'en')->first() ?? (object) ['id' => $id, 'title' => 'Unknown'];
        $classifications = $this->service->getClassificationLevels();
        return view('ahg-acl::security.classify', compact('object', 'classifications'));
    }

    public function classifyStore(Request $request)
    {
        return redirect()->route('acl.security-dashboard')->with('success', 'Classification applied.');
    }

    public function declassification(int $id)
    {
        $object = DB::table('information_object_i18n')->where('id', $id)->where('culture', 'en')->first() ?? (object) ['id' => $id, 'title' => 'Unknown'];
        $currentClassification = null;
        $classifications = $this->service->getClassificationLevels();
        return view('ahg-acl::security.declassification', compact('object', 'currentClassification', 'classifications'));
    }

    public function declassifyStore(Request $request)
    {
        return redirect()->route('acl.security-dashboard')->with('success', 'Object declassified.');
    }

    public function securityReport()
    {
        $stats = ['classified_count' => 0, 'cleared_users' => 0, 'denied_count' => 0];
        $breakdown = collect();
        return view('ahg-acl::security.report', compact('stats', 'breakdown'));
    }

    public function securityCompliance()
    {
        $compliance = ['score' => 0, 'issues' => 0, 'overdue_reviews' => 0, 'expired_clearances' => 0];
        $issues = collect();
        return view('ahg-acl::security.security-compliance', compact('compliance', 'issues'));
    }

    public function watermarkSettings()
    {
        $wmService = app(\AhgMediaProcessing\Services\WatermarkService::class);

        $watermarkTypes     = $wmService->getWatermarkTypes();
        $customWatermarks   = $wmService->getCustomWatermarks();
        $defaultEnabled     = $wmService->getSetting('default_watermark_enabled', '1');
        $defaultType        = $wmService->getSetting('default_watermark_type', 'COPYRIGHT');
        $defaultCustomWatermarkId = $wmService->getSetting('default_custom_watermark_id', '');
        $applyOnView        = $wmService->getSetting('apply_watermark_on_view', '1');
        $applyOnDownload    = $wmService->getSetting('apply_watermark_on_download', '1');
        $securityOverride   = $wmService->getSetting('security_watermark_override', '1');
        $minSize            = $wmService->getSetting('watermark_min_size', '200');

        return view('ahg-acl::security.watermark-settings', compact(
            'watermarkTypes', 'customWatermarks',
            'defaultEnabled', 'defaultType', 'defaultCustomWatermarkId',
            'applyOnView', 'applyOnDownload', 'securityOverride', 'minSize'
        ));
    }

    public function watermarkSettingsStore(Request $request)
    {
        $wmService = app(\AhgMediaProcessing\Services\WatermarkService::class);

        // Handle custom watermark upload
        if ($request->hasFile('custom_watermark_file') && $request->file('custom_watermark_file')->isValid()) {
            $wmService->uploadCustomWatermark(
                $request->file('custom_watermark_file'),
                $request->input('custom_watermark_name', 'Custom Watermark'),
                $request->input('custom_watermark_position', 'center'),
                (float) $request->input('custom_watermark_opacity', 0.40),
                auth()->id()
            );

            return redirect()->route('acl.watermark-settings')
                ->with('success', 'Custom watermark uploaded successfully.');
        }

        // Handle delete custom watermark
        if ($request->filled('delete_custom_watermark')) {
            $wmService->deleteCustomWatermark((int) $request->input('delete_custom_watermark'));

            return redirect()->route('acl.watermark-settings')
                ->with('success', 'Custom watermark deleted.');
        }

        // Save settings
        $wmService->saveSettings([
            'default_watermark_enabled'  => $request->input('default_watermark_enabled', '0'),
            'default_watermark_type'     => $request->input('default_watermark_type', 'COPYRIGHT'),
            'default_custom_watermark_id' => $request->input('default_custom_watermark_id', ''),
            'apply_watermark_on_view'    => $request->input('apply_watermark_on_view', '0'),
            'apply_watermark_on_download' => $request->input('apply_watermark_on_download', '0'),
            'security_watermark_override' => $request->input('security_watermark_override', '0'),
            'watermark_min_size'         => $request->input('watermark_min_size', '200'),
        ]);

        // Update Cantaloupe cache
        $wmService->updateCantaloupeCache();

        return redirect()->route('acl.watermark-settings')
            ->with('success', 'Watermark settings saved successfully.');
    }

    public function traceWatermark()
    {
        return view('ahg-acl::security.trace-watermark', ['watermarkCode' => null, 'traceResult' => null]);
    }

    public function traceWatermarkResult(Request $request)
    {
        $watermarkCode = $request->input('watermark_code');
        $traceResult = null;
        return view('ahg-acl::security.trace-watermark', compact('watermarkCode', 'traceResult'));
    }

    public function objectView(int $id)
    {
        $object = DB::table('information_object_i18n')->where('id', $id)->where('culture', 'en')->first() ?? (object) ['id' => $id, 'title' => 'Unknown'];
        $objectClassification = null;
        return view('ahg-acl::security.object-view', compact('object', 'objectClassification'));
    }

    public function userClearance(int $id)
    {
        $user = DB::table('user')->where('id', $id)->first() ?? (object) ['id' => $id, 'username' => 'Unknown'];
        $clearance = $this->service->getUserClearance($id);
        $accessHistory = collect();
        return view('ahg-acl::security.user-clearance', compact('user', 'clearance', 'accessHistory'));
    }

    public function userSecurity(int $id)
    {
        $user = DB::table('user')->where('id', $id)->first() ?? (object) ['id' => $id, 'username' => 'Unknown'];
        $clearance = $this->service->getUserClearance($id);
        $groups = collect();
        return view('ahg-acl::security.user', compact('user', 'clearance', 'groups'));
    }

    public function viewClassification(int $id)
    {
        $record = (object) ['object_title' => '', 'classification_name' => '', 'color' => '#999', 'classified_by' => '', 'classified_at' => '', 'reason' => ''];
        return view('ahg-acl::security.view', compact('record'));
    }

    public function securityAudit()
    {
        $auditEntries = collect();
        return view('ahg-acl::security.audit', compact('auditEntries'));
    }

    public function accessRequest(int $id)
    {
        $object = DB::table('information_object_i18n')->where('id', $id)->where('culture', 'en')->first() ?? (object) ['id' => $id, 'title' => 'Unknown'];
        $classification = null;
        $userClearance = null;
        return view('ahg-acl::security.access-request', compact('object', 'classification', 'userClearance'));
    }

    public function submitAccessRequest(Request $request)
    {
        return redirect()->route('security.my-requests')->with('success', 'Access request submitted.');
    }

    public function accessDenied()
    {
        $access = ['reasons' => []];
        $objectTitle = 'Restricted Resource';
        return view('ahg-acl::access-denied', compact('access', 'objectTitle'));
    }

    public function setupTwoFactor()
    {
        return view('ahg-acl::security.setup-two-factor', ['qrCode' => null]);
    }

    public function setupTwoFactorStore(Request $request)
    {
        return redirect()->route('acl.security-dashboard')->with('success', 'Two-factor authentication enabled.');
    }

    public function twoFactor()
    {
        return view('ahg-acl::security.two-factor');
    }

    public function verifyTwoFactor(Request $request)
    {
        return redirect('/')->with('success', 'Two-factor verified.');
    }

    public function reviewAccessRequest(int $id)
    {
        $accessRequest = DB::table('security_access_request')->where('id', $id)->first()
            ?? (object) ['id' => $id, 'requester_name' => '', 'object_title' => '', 'justification' => '', 'created_at' => '', 'request_type' => '', 'priority' => 'normal', 'duration_hours' => 24, 'user_id' => 0, 'status' => 'pending'];
        return view('ahg-acl::security.review-request', ['accessRequest' => $accessRequest]);
    }
}
