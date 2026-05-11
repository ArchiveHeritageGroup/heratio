<?php

/**
 * TenantRoleService - supplements AhgCore\Services\AclService with per-tenant
 * role gating. AclService remains the system-wide ACL; this service answers
 * "what can this user do *within this tenant*?".
 *
 * Both must allow the action for it to succeed. Tenant role is a gate in
 * addition to, never a replacement for, ahg-acl.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Services;

use AhgCore\Services\AclService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantRoleService
{
    /** Role action capability matrix. Add finer-grained actions as needed. */
    private const ROLE_ACTIONS = [
        'owner'       => ['read', 'create', 'update', 'delete', 'publish', 'translate', 'manage_users', 'manage_branding', 'manage_settings'],
        'super_user'  => ['read', 'create', 'update', 'delete', 'publish', 'translate', 'manage_users', 'manage_branding'],
        'editor'      => ['read', 'create', 'update', 'publish', 'translate'],
        'contributor' => ['read', 'create', 'update'],
        'viewer'      => ['read'],
    ];

    private AclService $acl;

    public function __construct(?AclService $acl = null)
    {
        $this->acl = $acl ?? new AclService();
    }

    /**
     * Combined check: ahg-acl AND tenant role must both allow.
     *
     * Heratio admins (user.is_admin = 1) bypass the tenant-role gate but
     * still go through ahg-acl, matching the existing admin-bypass pattern.
     */
    public function check(int $userId, int $tenantId, string $action, ?int $objectId = null): bool
    {
        $aclAction = $this->mapActionToAclVerb($action);
        if (!$this->acl->check($userId, $aclAction, $objectId)) {
            return false;
        }

        if ($this->userIsHeratioAdmin($userId)) {
            return true;
        }

        $role = $this->getRole($userId, $tenantId);
        if ($role === null) {
            return false;
        }

        return $this->roleAllows($role, $action);
    }

    public function getRole(int $userId, int $tenantId): ?string
    {
        $row = DB::table('ahg_tenant_user')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->value('role');

        return $row === null ? null : (string) $row;
    }

    public function userBelongs(int $userId, int $tenantId): bool
    {
        return DB::table('ahg_tenant_user')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    public function isSuperUser(int $userId, int $tenantId): bool
    {
        return DB::table('ahg_tenant_user')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('is_super_user', 1)
            ->exists();
    }

    public function roleAllows(string $role, string $action): bool
    {
        $allowed = self::ROLE_ACTIONS[$role] ?? [];

        return in_array($action, $allowed, true);
    }

    /**
     * Tenants this user can switch to (powers the navbar switcher).
     */
    public function getUserTenants(int $userId): Collection
    {
        return DB::table('ahg_tenant_user as tu')
            ->join('ahg_tenant as t', 't.id', '=', 'tu.tenant_id')
            ->where('tu.user_id', $userId)
            ->where('t.is_active', 1)
            ->select('t.*', 'tu.role', 'tu.is_super_user', 'tu.is_primary')
            ->orderByDesc('tu.is_primary')
            ->orderBy('t.name')
            ->get();
    }

    /**
     * Assign / update a user's role within a tenant. Idempotent.
     */
    public function assignUser(int $tenantId, int $userId, string $role, bool $isPrimary = false): void
    {
        $assignedBy = (int) (auth()->id() ?? 0) ?: null;

        $isSuper = in_array($role, ['owner', 'super_user'], true) ? 1 : 0;

        DB::table('ahg_tenant_user')->updateOrInsert(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            [
                'role' => $role,
                'is_super_user' => $isSuper,
                'is_primary' => $isPrimary ? 1 : 0,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]
        );

        if ($isPrimary) {
            DB::table('ahg_tenant_user')
                ->where('user_id', $userId)
                ->where('tenant_id', '!=', $tenantId)
                ->update(['is_primary' => 0]);
        }
    }

    public function unassignUser(int $tenantId, int $userId): void
    {
        DB::table('ahg_tenant_user')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Map our richer action vocabulary to AclService's verb set so we can
     * keep tenant actions named meaningfully (e.g. manage_users) without
     * loosening the system ACL check.
     */
    private function mapActionToAclVerb(string $action): string
    {
        return match ($action) {
            'manage_users', 'manage_branding', 'manage_settings' => 'update',
            default => $action,
        };
    }

    private function userIsHeratioAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $row = DB::table('user')->where('id', $userId)->first();

        return $row && (int) ($row->is_admin ?? 0) === 1;
    }
}
