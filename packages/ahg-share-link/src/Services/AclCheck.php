<?php

/**
 * AclCheck — share-link permission helper. Mirror of the AtoM-side service.
 *
 * @phase C
 */

namespace AhgShareLink\Services;

use Illuminate\Support\Facades\DB;

class AclCheck
{
    public const ACTION_CREATE                  = 'share_link.create';
    public const ACTION_CREATE_CLASSIFIED       = 'share_link.create_classified';
    public const ACTION_CREATE_UNLIMITED_EXPIRY = 'share_link.create_unlimited_expiry';
    public const ACTION_LIST_ALL                = 'share_link.list_all';
    public const ACTION_REVOKE_OTHERS           = 'share_link.revoke_others';

    private const ACL_GROUP_ADMINISTRATOR = 100;

    private static array $groupCache = [];

    public function canUserDo(?int $userId, string $action): bool
    {
        if ($userId === null) {
            return false;
        }
        try {
            $groups = $this->getUserGroups($userId);
            if (in_array(self::ACL_GROUP_ADMINISTRATOR, $groups, true)) {
                return true;
            }
            $userGrant = DB::table('acl_permission')
                ->where('user_id', $userId)
                ->where('action', $action)
                ->where('grant_deny', 1)
                ->exists();
            if ($userGrant) {
                return true;
            }
            if (empty($groups)) {
                return false;
            }
            $groupGrant = DB::table('acl_permission')
                ->whereIn('group_id', $groups)
                ->where('action', $action)
                ->where('grant_deny', 1)
                ->exists();
            if ($groupGrant) {
                return true;
            }
            return DB::table('acl_permission')
                ->whereIn('group_id', $groups)
                ->whereNull('action')
                ->where('grant_deny', 1)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<int,int>
     */
    private function getUserGroups(int $userId): array
    {
        if (isset(self::$groupCache[$userId])) {
            return self::$groupCache[$userId];
        }
        try {
            $rows = DB::table('acl_user_group')->where('user_id', $userId)->pluck('group_id')->all();
            self::$groupCache[$userId] = array_map('intval', $rows);
        } catch (\Throwable $e) {
            self::$groupCache[$userId] = [];
        }
        return self::$groupCache[$userId];
    }
}
