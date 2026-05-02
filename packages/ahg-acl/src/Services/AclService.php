<?php

/**
 * AclService - Service for Heratio
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



namespace AhgAcl\Services;

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

    // ─── Per-entity ACL editor support (issue #50) ──────────────────────────

    public const IO_ACTIONS = [
        'read'           => 'Read',
        'create'         => 'Create',
        'update'         => 'Update',
        'delete'         => 'Delete',
        'viewDraft'      => 'View draft',
        'publish'        => 'Publish',
        'readMaster'     => 'Access master',
        'readReference'  => 'Access reference',
        'readThumbnail'  => 'Access thumbnail',
    ];

    public const ACTOR_ACTIONS = self::IO_ACTIONS;

    public const REPOSITORY_ACTIONS = [
        'read'   => 'Read',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
    ];

    public const TERM_ACTIONS = [
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
    ];

    /**
     * Save Profile tab: name, description, translate flag.
     * Translate is stored as a single grant row on action='translate' with
     * object_id NULL — matches AtoM's QubitAclGroup model.
     */
    public function saveGroupProfile(int $groupId, array $data): void
    {
        $now = now()->toDateTimeString();

        $existing = DB::table('acl_group_i18n')->where('id', $groupId)->where('culture', 'en')->first();
        if ($existing) {
            DB::table('acl_group_i18n')->where('id', $groupId)->where('culture', 'en')->update([
                'name'        => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
            ]);
        } else {
            DB::table('acl_group_i18n')->insert([
                'id'          => $groupId,
                'culture'     => 'en',
                'name'        => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'serial_number' => 0,
            ]);
        }
        DB::table('acl_group')->where('id', $groupId)->update(['updated_at' => $now]);

        $translate = !empty($data['translate']);
        $translateRow = DB::table('acl_permission')
            ->where('group_id', $groupId)
            ->where('action', 'translate')
            ->whereNull('object_id')
            ->first();

        if ($translate && !$translateRow) {
            DB::table('acl_permission')->insert([
                'group_id'      => $groupId,
                'action'        => 'translate',
                'grant_deny'    => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
                'serial_number' => 0,
            ]);
        } elseif (!$translate && $translateRow) {
            DB::table('acl_permission')->where('id', $translateRow->id)->delete();
        }
    }

    /**
     * Whether the group's Profile-tab translate flag is on.
     */
    public function getGroupTranslateFlag(int $groupId): bool
    {
        return DB::table('acl_permission')
            ->where('group_id', $groupId)
            ->where('action', 'translate')
            ->whereNull('object_id')
            ->where('grant_deny', 1)
            ->exists();
    }

    /**
     * Tabs menu shape consumed by `_tabs.blade.php`.
     */
    public function getGroupTabsMenu(int $groupId): array
    {
        return [
            ['label' => __('Profile'),               'url' => route('acl.edit-group',                 ['id' => $groupId])],
            ['label' => __('Archival Description'),  'url' => route('acl.editInformationObjectAcl',   ['id' => $groupId])],
            ['label' => __('Authority Record'),      'url' => route('acl.editActorAcl',               ['id' => $groupId])],
            ['label' => __('Archival Institution'),  'url' => route('acl.editRepositoryAcl',          ['id' => $groupId])],
            ['label' => __('Taxonomy'),              'url' => route('acl.editTermAcl',                ['id' => $groupId])],
        ];
    }

    /**
     * Permissions for a group filtered by qubit class_name.
     * Joined to `object` so root rows (object_id NULL) and class-scoped rows
     * come through together. The partial expects `->grantDeny` (camelCase).
     */
    public function getGroupPermissionsByClass(int $groupId, string $className): \Illuminate\Support\Collection
    {
        return DB::table('acl_permission as p')
            ->leftJoin('object as o', 'o.id', '=', 'p.object_id')
            ->where('p.group_id', $groupId)
            ->where(function ($q) use ($className) {
                $q->whereNull('p.object_id')
                  ->orWhere('o.class_name', $className);
            })
            ->select('p.id', 'p.object_id', 'p.action', 'p.grant_deny as grantDeny', 'p.constants', 'o.class_name')
            ->orderBy('p.object_id')
            ->orderBy('p.action')
            ->get();
    }

    /**
     * Group an ACL permission collection by scope: 'root', per-repo, per-object.
     * Returns ['root'=>[action=>perm], 'repositories'=>[slug=>[action=>perm]], 'objects'=>[id=>[action=>perm]]]
     */
    public function bucketIoPermissions(\Illuminate\Support\Collection $perms): array
    {
        $root = []; $repos = []; $objs = [];
        foreach ($perms as $p) {
            $repoSlug = null;
            if (!empty($p->constants)) {
                $c = json_decode($p->constants, true) ?: [];
                $repoSlug = $c['repository'] ?? null;
            }
            if ($p->object_id === null && $repoSlug === null) {
                $root[$p->action] = $p;
            } elseif ($repoSlug !== null) {
                $repos[$repoSlug][$p->action] = $p;
            } else {
                $objs[$p->object_id][$p->action] = $p;
            }
        }
        return ['root' => $root, 'repositories' => $repos, 'objects' => $objs];
    }

    /**
     * Apply form data shaped as `acl[<perm_id|key>] = grant|deny|inherit`
     * to the group's permissions for one $action set + class scope.
     *
     * - existing perm + grant/deny  → update grant_deny
     * - existing perm + inherit     → delete
     * - new key (no perm yet) + grant/deny → insert with action+object_id+constants
     * - new key + inherit           → noop
     */
    public function applyAclForm(int $groupId, array $form, array $allowedActions, string $className): void
    {
        $now = now()->toDateTimeString();

        $existing = $this->getGroupPermissionsByClass($groupId, $className)
            ->keyBy('id');

        foreach ($form as $key => $value) {
            $value = (int) $value;
            // Existing-perm rows: numeric integer key → DB id
            if (ctype_digit((string) $key)) {
                $permId = (int) $key;
                if (!$existing->has($permId)) {
                    continue;
                }
                if ($value === self::INHERIT) {
                    DB::table('acl_permission')->where('id', $permId)->delete();
                } elseif (in_array($value, [self::GRANT, self::DENY], true)) {
                    DB::table('acl_permission')->where('id', $permId)->update([
                        'grant_deny' => $value,
                        'updated_at' => $now,
                    ]);
                }
                continue;
            }
            // New-perm rows: <action>_<scopeKey>
            if (!preg_match('/^([a-zA-Z]+)_(.+)$/', $key, $m)) {
                continue;
            }
            [$_full, $action, $scopeKey] = $m;
            if (!isset($allowedActions[$action])) {
                continue;
            }
            if ($value !== self::GRANT && $value !== self::DENY) {
                continue;
            }
            // Resolve scopeKey: 'root' or '<class-prefix>:<slug-or-id>'
            $objectId = null;
            $constants = null;
            if ($scopeKey === 'root') {
                // Whole-class scope (root) — object_id NULL, no constants
            } elseif (str_starts_with($scopeKey, 'repo:')) {
                // Per-repository scope inside IO ACL → constants={"repository":"<slug>"}
                $constants = json_encode(['repository' => substr($scopeKey, 5)]);
            } else {
                // Per-object scope: scopeKey is a slug → resolve to object.id
                $obj = DB::table('slug')->where('slug', $scopeKey)->first();
                if (!$obj) {
                    continue;
                }
                $objectId = $obj->object_id;
            }
            DB::table('acl_permission')->insert([
                'group_id'      => $groupId,
                'object_id'     => $objectId,
                'action'        => $action,
                'grant_deny'    => $value,
                'constants'     => $constants,
                'created_at'    => $now,
                'updated_at'    => $now,
                'serial_number' => 0,
            ]);
        }
    }

    /**
     * Hydrate per-repository / per-IO entity rows referenced by permission scopes
     * so `_acl-table.blade.php` can render captions with names.
     */
    public function hydrateRepositoryEntities(array $repoSlugs): array
    {
        if (empty($repoSlugs)) return [];
        return DB::table('repository as r')
            ->join('slug as s', 's.object_id', '=', 'r.id')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'r.id')->where('ai.culture', '=', 'en');
            })
            ->whereIn('s.slug', $repoSlugs)
            ->select('s.slug', 'r.id', 'ai.authorized_form_of_name')
            ->get()
            ->keyBy('slug')
            ->all();
    }

    public function hydrateInformationObjectEntities(array $ids): array
    {
        if (empty($ids)) return [];
        return DB::table('information_object as io')
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ii', function ($j) {
                $j->on('ii.id', '=', 'io.id')->where('ii.culture', '=', 'en');
            })
            ->whereIn('io.id', $ids)
            ->select('io.id', 's.slug', 'ii.title')
            ->get()
            ->keyBy('id')
            ->all();
    }

    public function hydrateActorEntities(array $ids): array
    {
        if (empty($ids)) return [];
        return DB::table('actor as a')
            ->leftJoin('slug as s', 's.object_id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'a.id')->where('ai.culture', '=', 'en');
            })
            ->whereIn('a.id', $ids)
            ->select('a.id', 's.slug', 'ai.authorized_form_of_name')
            ->get()
            ->keyBy('id')
            ->all();
    }

    public function hydrateTaxonomyEntities(array $ids): array
    {
        if (empty($ids)) return [];
        return DB::table('taxonomy as t')
            ->leftJoin('slug as s', 's.object_id', '=', 't.id')
            ->leftJoin('taxonomy_i18n as ti', function ($j) {
                $j->on('ti.id', '=', 't.id')->where('ti.culture', '=', 'en');
            })
            ->whereIn('t.id', $ids)
            ->select('t.id', 's.slug', 'ti.name')
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * AclService::GRANT|DENY|INHERIT mirror so the per-entity package can
     * reference local constants without depending on ahg-core.
     */
    public const GRANT   = 1;
    public const DENY    = 0;
    public const INHERIT = -1;

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
