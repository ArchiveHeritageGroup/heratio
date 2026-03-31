<?php

/**
 * AclService - Service for Heratio
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


use Illuminate\Support\Facades\DB;

class AclService
{
    /**
     * Get all ACL groups with i18n names and member counts.
     */
    public function getGroups(): \Illuminate\Support\Collection
    {
        return DB::table('acl_group as g')
            ->leftJoin('acl_group_i18n as gi', function ($join) {
                $join->on('gi.id', '=', 'g.id')
                     ->where('gi.culture', '=', 'en');
            })
            ->leftJoin(DB::raw('(SELECT group_id, COUNT(*) as member_count FROM acl_user_group GROUP BY group_id) as mc'), 'mc.group_id', '=', 'g.id')
            ->select(
                'g.id',
                'g.parent_id',
                'g.created_at',
                'g.updated_at',
                'g.source_culture',
                'g.serial_number',
                'gi.name',
                'gi.description',
                DB::raw('COALESCE(mc.member_count, 0) as member_count')
            )
            ->orderBy('gi.name')
            ->get();
    }

    /**
     * Get a single ACL group with its members and permissions.
     */
    public function getGroup(int $id): ?object
    {
        $group = DB::table('acl_group as g')
            ->leftJoin('acl_group_i18n as gi', function ($join) {
                $join->on('gi.id', '=', 'g.id')
                     ->where('gi.culture', '=', 'en');
            })
            ->select(
                'g.id',
                'g.parent_id',
                'g.created_at',
                'g.updated_at',
                'g.source_culture',
                'g.serial_number',
                'gi.name',
                'gi.description'
            )
            ->where('g.id', $id)
            ->first();

        if (!$group) {
            return null;
        }

        // Get members with user names via actor_i18n
        $group->members = DB::table('acl_user_group as ug')
            ->join('user as u', 'u.id', '=', 'ug.user_id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'u.id')
                     ->where('ai.culture', '=', 'en');
            })
            ->select(
                'ug.id as membership_id',
                'ug.user_id',
                'u.username',
                'u.email',
                'ai.authorized_form_of_name as display_name',
                'ug.serial_number'
            )
            ->where('ug.group_id', $id)
            ->orderBy('ai.authorized_form_of_name')
            ->get();

        // Get permissions
        $group->permissions = $this->getGroupPermissions($id);

        return $group;
    }

    /**
     * Get all permissions for a specific group.
     */
    public function getGroupPermissions(int $groupId): \Illuminate\Support\Collection
    {
        return DB::table('acl_permission')
            ->select('id', 'user_id', 'group_id', 'object_id', 'action', 'grant_deny', 'conditional', 'constants', 'created_at', 'updated_at', 'serial_number')
            ->where('group_id', $groupId)
            ->orderBy('action')
            ->get();
    }

    /**
     * Insert or update a permission record.
     */
    public function savePermission(array $data): int
    {
        $now = now()->toDateTimeString();

        // Check if permission already exists for this group+action+object combo
        $existing = DB::table('acl_permission')
            ->where('group_id', $data['group_id'] ?? null)
            ->where('action', $data['action'] ?? null)
            ->where('object_id', $data['object_id'] ?? null)
            ->first();

        if ($existing) {
            DB::table('acl_permission')
                ->where('id', $existing->id)
                ->update([
                    'grant_deny'  => $data['grant_deny'] ?? 0,
                    'conditional' => $data['conditional'] ?? null,
                    'constants'   => $data['constants'] ?? null,
                    'updated_at'  => $now,
                ]);

            return $existing->id;
        }

        return DB::table('acl_permission')->insertGetId([
            'user_id'      => $data['user_id'] ?? null,
            'group_id'     => $data['group_id'] ?? null,
            'object_id'    => $data['object_id'] ?? null,
            'action'       => $data['action'] ?? null,
            'grant_deny'   => $data['grant_deny'] ?? 0,
            'conditional'  => $data['conditional'] ?? null,
            'constants'    => $data['constants'] ?? null,
            'created_at'   => $now,
            'updated_at'   => $now,
            'serial_number' => 0,
        ]);
    }

    /**
     * Delete a permission by ID.
     */
    public function deletePermission(int $id): bool
    {
        return DB::table('acl_permission')->where('id', $id)->delete() > 0;
    }

    /**
     * Get groups for a specific user.
     */
    public function getUserGroups(int $userId): \Illuminate\Support\Collection
    {
        return DB::table('acl_user_group as ug')
            ->join('acl_group as g', 'g.id', '=', 'ug.group_id')
            ->leftJoin('acl_group_i18n as gi', function ($join) {
                $join->on('gi.id', '=', 'g.id')
                     ->where('gi.culture', '=', 'en');
            })
            ->select(
                'ug.id as membership_id',
                'g.id as group_id',
                'gi.name',
                'gi.description',
                'ug.serial_number'
            )
            ->where('ug.user_id', $userId)
            ->orderBy('gi.name')
            ->get();
    }

    /**
     * Add a user to a group.
     */
    public function addUserToGroup(int $userId, int $groupId): int
    {
        // Prevent duplicate entries
        $existing = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::table('acl_user_group')->insertGetId([
            'user_id'       => $userId,
            'group_id'      => $groupId,
            'serial_number' => 0,
        ]);
    }

    /**
     * Remove a user from a group.
     */
    public function removeUserFromGroup(int $userId, int $groupId): bool
    {
        return DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->delete() > 0;
    }

    /**
     * Get all active security classification levels ordered by level.
     */
    public function getClassificationLevels(): \Illuminate\Support\Collection
    {
        return DB::table('security_classification')
            ->select(
                'id', 'code', 'level', 'name', 'description', 'color', 'icon',
                'requires_justification', 'requires_approval', 'requires_2fa',
                'max_session_hours', 'watermark_required', 'watermark_image',
                'download_allowed', 'print_allowed', 'copy_allowed',
                'active', 'created_at', 'updated_at'
            )
            ->where('active', 1)
            ->orderBy('level')
            ->get();
    }

    /**
     * Get the security classification for a specific object.
     */
    public function getObjectClassification(int $objectId): ?object
    {
        return DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
            ->select(
                'osc.*',
                'sc.code as classification_code',
                'sc.name as classification_name',
                'sc.level as classification_level',
                'sc.color as classification_color'
            )
            ->where('osc.object_id', $objectId)
            ->where('osc.active', 1)
            ->first();
    }

    /**
     * Set or update the security classification for an object.
     */
    public function setObjectClassification(int $objectId, int $classificationId, int $userId): int
    {
        $now = now()->toDateTimeString();

        // Deactivate any existing classification
        DB::table('object_security_classification')
            ->where('object_id', $objectId)
            ->where('active', 1)
            ->update(['active' => 0, 'updated_at' => $now]);

        return DB::table('object_security_classification')->insertGetId([
            'object_id'         => $objectId,
            'classification_id' => $classificationId,
            'classified_by'     => $userId,
            'classified_at'     => $now,
            'assigned_by'       => $userId,
            'assigned_at'       => $now,
            'inherit_to_children' => 1,
            'active'            => 1,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    /**
     * Get a user's current security clearance with classification name.
     */
    public function getUserClearance(int $userId): ?object
    {
        return DB::table('user_security_clearance as uc')
            ->join('security_classification as sc', 'sc.id', '=', 'uc.classification_id')
            ->leftJoin('user as u', 'u.id', '=', 'uc.granted_by')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'u.id')
                     ->where('ai.culture', '=', 'en');
            })
            ->select(
                'uc.*',
                'sc.code as classification_code',
                'sc.name as classification_name',
                'sc.level as classification_level',
                'sc.color as classification_color',
                'ai.authorized_form_of_name as granted_by_name'
            )
            ->where('uc.user_id', $userId)
            ->first();
    }

    /**
     * Set or update a user's security clearance and log the change.
     */
    public function setUserClearance(int $userId, int $classificationId, int $grantedBy): void
    {
        $now = now()->toDateTimeString();

        // Check if user already has a clearance
        $existing = DB::table('user_security_clearance')
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->update([
                    'classification_id' => $classificationId,
                    'granted_by'        => $grantedBy,
                    'granted_at'        => $now,
                ]);
            $action = 'clearance_updated';
        } else {
            DB::table('user_security_clearance')->insert([
                'user_id'           => $userId,
                'classification_id' => $classificationId,
                'granted_by'        => $grantedBy,
                'granted_at'        => $now,
            ]);
            $action = 'clearance_granted';
        }

        // Log the change
        DB::table('user_security_clearance_log')->insert([
            'user_id'           => $userId,
            'classification_id' => $classificationId,
            'action'            => $action,
            'changed_by'        => $grantedBy,
            'notes'             => null,
            'created_at'        => $now,
        ]);
    }

    /**
     * Get security access requests filtered by status.
     */
    public function getAccessRequests(?string $status = 'pending'): \Illuminate\Support\Collection
    {
        $query = DB::table('security_access_request as sar')
            ->leftJoin('user as u', 'u.id', '=', 'sar.user_id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'u.id')
                     ->where('ai.culture', '=', 'en');
            })
            ->leftJoin('security_classification as sc', 'sc.id', '=', 'sar.classification_id')
            ->select(
                'sar.*',
                'ai.authorized_form_of_name as user_name',
                'u.username',
                'sc.name as classification_name',
                'sc.code as classification_code',
                'sc.color as classification_color'
            );

        if ($status !== null) {
            $query->where('sar.status', $status);
        }

        return $query->orderByDesc('sar.created_at')->get();
    }

    /**
     * Approve a security access request.
     */
    public function approveAccessRequest(int $id, int $reviewerId, ?string $notes = null): bool
    {
        $now = now()->toDateTimeString();

        return DB::table('security_access_request')
            ->where('id', $id)
            ->update([
                'status'       => 'approved',
                'reviewed_by'  => $reviewerId,
                'reviewed_at'  => $now,
                'review_notes' => $notes,
                'updated_at'   => $now,
            ]) > 0;
    }

    /**
     * Deny a security access request.
     */
    public function denyAccessRequest(int $id, int $reviewerId, ?string $notes = null): bool
    {
        $now = now()->toDateTimeString();

        return DB::table('security_access_request')
            ->where('id', $id)
            ->update([
                'status'       => 'denied',
                'reviewed_by'  => $reviewerId,
                'reviewed_at'  => $now,
                'review_notes' => $notes,
                'updated_at'   => $now,
            ]) > 0;
    }

    /**
     * Get recent security audit log entries.
     */
    public function getSecurityAuditLog(int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table('security_audit_log as sal')
            ->leftJoin('user as u', 'u.id', '=', 'sal.user_id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'u.id')
                     ->where('ai.culture', '=', 'en');
            })
            ->select(
                'sal.id',
                'sal.object_id',
                'sal.object_type',
                'sal.user_id',
                'sal.user_name',
                'sal.action',
                'sal.action_category',
                'sal.details',
                'sal.ip_address',
                'sal.user_agent',
                'sal.created_at',
                'ai.authorized_form_of_name as display_name'
            )
            ->orderByDesc('sal.created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if a user has permission to perform an action on an object.
     *
     * Looks up all groups the user belongs to, then checks acl_permission
     * for a matching group+action+object. Grant (1) wins over deny (0)
     * if found; returns false if no matching permission exists.
     */
    public function check(int $userId, string $action, ?int $objectId = null): bool
    {
        // Get all group IDs for this user
        $groupIds = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->pluck('group_id')
            ->toArray();

        if (empty($groupIds)) {
            return false;
        }

        // Also check user-level permissions
        $query = DB::table('acl_permission')
            ->where('action', $action)
            ->where(function ($q) use ($userId, $groupIds) {
                $q->whereIn('group_id', $groupIds)
                  ->orWhere('user_id', $userId);
            });

        if ($objectId !== null) {
            $query->where(function ($q) use ($objectId) {
                $q->where('object_id', $objectId)
                  ->orWhereNull('object_id');
            });
        }

        $permissions = $query->get();

        if ($permissions->isEmpty()) {
            return false;
        }

        // If any permission explicitly grants access (grant_deny = 1), allow.
        // If any permission explicitly denies (grant_deny = 0), deny.
        // Object-specific permissions take precedence over general ones.
        $objectSpecific = $permissions->whereNotNull('object_id');
        if ($objectSpecific->isNotEmpty()) {
            return $objectSpecific->contains('grant_deny', 1);
        }

        return $permissions->contains('grant_deny', 1);
    }

    /**
     * Get all users for dropdown selection (from user + actor_i18n).
     */
    public function getAllUsers(): \Illuminate\Support\Collection
    {
        return DB::table('user as u')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'u.id')
                     ->where('ai.culture', '=', 'en');
            })
            ->select(
                'u.id',
                'u.username',
                'u.email',
                'ai.authorized_form_of_name as display_name'
            )
            ->orderBy('ai.authorized_form_of_name')
            ->get();
    }
}
