<?php

/**
 * AclCheck — version-control permission helper. Mirror of the AtoM-side service.
 *
 * @phase K
 */

namespace AhgVersionControl\Services;

use Illuminate\Support\Facades\DB;

class AclCheck
{
    public const ACTION_LIST                = 'version.list';
    public const ACTION_DIFF                = 'version.diff';
    public const ACTION_RESTORE             = 'version.restore';
    public const ACTION_RESTORE_CLASSIFIED  = 'version.restore_classified';

    private const ACL_GROUP_ADMINISTRATOR = 100;

    private static array $groupCache = [];

    public function canUserDo(?int $userId, string $action): bool
    {
        if ($userId === null) {
            return true;
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
