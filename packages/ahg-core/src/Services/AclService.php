<?php

declare(strict_types=1);

namespace AhgCore\Services;

use AhgCore\Models\AclGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ACL Service - Access Control List enforcement.
 *
 * Migrated from AtomExtensions\Services\AclService.
 * Replaces sfContext user detection with Laravel Auth.
 */
class AclService
{
    public const GRANT = 1;
    public const DENY = 0;
    public const INHERIT = -1;

    public static array $ACTIONS = [
        'read' => 'Read',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'translate' => 'Translate',
        'publish' => 'Publish',
        'viewDraft' => 'View Draft',
        'readMaster' => 'Read Master',
        'readReference' => 'Read Reference',
        'readThumbnail' => 'Read Thumbnail',
        'createTerm' => 'Create Term',
        'list' => 'List',
    ];

    private static ?object $user = null;
    private static ?array $userGroups = null;

    public static function setUser(?object $user): void
    {
        self::$user = $user;
        self::$userGroups = null;
    }

    public static function getUser(): ?object
    {
        if (! self::$user) {
            self::$user = Auth::user();
        }

        return self::$user;
    }

    public static function check(?object $resource, $action, ?object $user = null): bool
    {
        $user = $user ?? self::getUser();

        if (! $user) {
            return false;
        }

        $groups = self::getUserGroups($user->id ?? null);

        // Administrator has all permissions
        if (in_array(AclGroup::ADMINISTRATOR_ID, $groups)) {
            return true;
        }

        // Handle array of actions
        if (is_array($action)) {
            foreach ($action as $a) {
                if (self::checkSingleAction($resource, $a, $user, $groups)) {
                    return true;
                }
            }

            return false;
        }

        return self::checkSingleAction($resource, $action, $user, $groups);
    }

    private static function checkSingleAction(?object $resource, string $action, object $user, array $groups): bool
    {
        // Editors can do most things
        if (in_array(AclGroup::EDITOR_ID, $groups)) {
            $editorActions = ['create', 'read', 'update', 'delete', 'translate', 'publish', 'createTerm', 'list', 'readMaster', 'readReference', 'readThumbnail'];
            if (in_array($action, $editorActions)) {
                return true;
            }
        }

        // Contributors can create and update
        if (in_array(AclGroup::CONTRIBUTOR_ID, $groups)) {
            $contributorActions = ['create', 'read', 'update'];
            if (in_array($action, $contributorActions)) {
                return true;
            }
        }

        // Translators can translate
        if (in_array(AclGroup::TRANSLATOR_ID, $groups)) {
            if ($action === 'translate') {
                return true;
            }
        }

        // Check object-specific permissions
        if ($resource) {
            $perm = DB::table('acl_permission')
                ->where(function ($q) use ($user, $groups) {
                    $q->where('user_id', $user->id)
                        ->orWhereIn('group_id', $groups);
                })
                ->where('action', $action)
                ->where(function ($q) use ($resource) {
                    $q->whereNull('object_id')
                        ->orWhere('object_id', $resource->id ?? null);
                })
                ->orderByRaw('object_id IS NULL')
                ->first();

            if ($perm) {
                return $perm->grant_deny == self::GRANT;
            }
        }

        return false;
    }

    public static function getUserGroups(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

        if (self::$userGroups !== null && self::$user && (self::$user->id ?? null) === $userId) {
            return self::$userGroups;
        }

        self::$userGroups = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->pluck('group_id')
            ->toArray();

        return self::$userGroups;
    }

    public static function hasGroup(int $groupId, ?object $user = null): bool
    {
        $user = $user ?? self::getUser();
        if (! $user) {
            return false;
        }

        return in_array($groupId, self::getUserGroups($user->id ?? null));
    }

    public static function isAdministrator(?object $user = null): bool
    {
        return self::hasGroup(AclGroup::ADMINISTRATOR_ID, $user);
    }

    public static function isEditor(?object $user = null): bool
    {
        return self::hasGroup(AclGroup::EDITOR_ID, $user);
    }

    public static function isContributor(?object $user = null): bool
    {
        return self::hasGroup(AclGroup::CONTRIBUTOR_ID, $user);
    }

    public static function isTranslator(?object $user = null): bool
    {
        return self::hasGroup(AclGroup::TRANSLATOR_ID, $user);
    }

    /**
     * Filter a Laravel Query Builder to only return published records
     * or records the current user can view as drafts.
     *
     * Status type_id 158 = publicationStatusId
     * Status status_id 160 = PUBLICATION_STATUS_PUBLISHED_ID
     */
    public static function addFilterDraftsCriteria($query): mixed
    {
        $user = self::getUser();

        // No user → only show published
        if (! $user) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', 'i.id')
                    ->where('status.type_id', 158)
                    ->where('status.status_id', 160);
            });

            return $query;
        }

        $groups = self::getUserGroups($user->id ?? null);

        // Administrators and editors can see all drafts
        if (in_array(AclGroup::ADMINISTRATOR_ID, $groups) || in_array(AclGroup::EDITOR_ID, $groups)) {
            return $query;
        }

        // Contributors can see their own drafts + all published
        $query->where(function ($q) use ($user) {
            $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', 'i.id')
                    ->where('status.type_id', 158)
                    ->where('status.status_id', 160);
            });
            if ($user->id ?? null) {
                $q->orWhereExists(function ($sub) use ($user) {
                    $sub->select(DB::raw(1))
                        ->from('object')
                        ->whereColumn('object.id', 'i.id')
                        ->where('object.created_by', $user->id);
                });
            }
        });

        return $query;
    }

    /**
     * Get all group IDs for a user (with caching).
     * If no user (anonymous), return [98].
     * If authenticated, always include [99] + their actual groups.
     */
    public static function getUserGroupIds(?int $userId): array
    {
        if (! $userId) {
            return [98]; // anonymous
        }

        return Cache::remember("acl_groups_{$userId}", 300, function () use ($userId) {
            $groups = DB::table('acl_user_group')
                ->where('user_id', $userId)
                ->pluck('group_id')
                ->toArray();

            // Always include authenticated group
            if (! in_array(99, $groups)) {
                $groups[] = 99;
            }

            return $groups;
        });
    }

    /**
     * Check if user has permission for an action.
     * Checks user-specific permissions first, then group permissions.
     * Administrator group (100) has all permissions.
     */
    public static function hasPermission(?int $userId, string $action, ?int $objectId = null): bool
    {
        $groupIds = self::getUserGroupIds($userId);

        // Administrators have all permissions
        if (in_array(AclGroup::ADMINISTRATOR_ID, $groupIds)) {
            return true;
        }

        // Check user-specific permissions
        if ($userId) {
            $userPerm = DB::table('acl_permission')
                ->where('user_id', $userId)
                ->where(function ($q) use ($action) {
                    $q->where('action', $action)->orWhereNull('action');
                })
                ->where(function ($q) use ($objectId) {
                    $q->where('object_id', $objectId)->orWhereNull('object_id');
                })
                ->orderByDesc('grant_deny')
                ->first();

            if ($userPerm) {
                return (bool) $userPerm->grant_deny;
            }
        }

        // Check group permissions
        $groupPerm = DB::table('acl_permission')
            ->whereIn('group_id', $groupIds)
            ->whereNull('user_id')
            ->where(function ($q) use ($action) {
                $q->where('action', $action)->orWhereNull('action');
            })
            ->where(function ($q) use ($objectId) {
                $q->where('object_id', $objectId)->orWhereNull('object_id');
            })
            ->orderByDesc('grant_deny')
            ->first();

        return $groupPerm ? (bool) $groupPerm->grant_deny : false;
    }

    /**
     * Check if user can access admin area (administrator or editor).
     */
    public static function canAdmin(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        $groupIds = self::getUserGroupIds($userId);

        return ! empty(array_intersect([AclGroup::ADMINISTRATOR_ID, AclGroup::EDITOR_ID], $groupIds));
    }

    public static function getRepositoryAccess(string $action): array
    {
        $user = self::getUser();
        if (! $user) {
            return [['access' => self::DENY]];
        }
        if (self::isAdministrator()) {
            return [['access' => self::GRANT]];
        }

        $groups = self::getUserGroups($user->id);

        $permissions = DB::table('acl_permission')
            ->whereIn('group_id', $groups)
            ->where('action', $action)
            ->whereNotNull('object_id')
            ->select('object_id', 'grant_deny')
            ->get();

        $result = [];
        foreach ($permissions as $perm) {
            $result[] = ['repository_id' => $perm->object_id, 'access' => $perm->grant_deny];
        }

        $defaultAccess = in_array(AclGroup::EDITOR_ID, $groups) ? self::GRANT : self::DENY;
        $result[] = ['access' => $defaultAccess];

        return $result;
    }
}
