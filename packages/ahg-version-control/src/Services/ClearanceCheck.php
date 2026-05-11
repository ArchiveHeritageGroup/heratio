<?php

/**
 * ClearanceCheck — focused single-record clearance lookup for restore guards.
 *
 * Mirror of the AtoM-side service. Same schema, same rules:
 *   1) Administrators always pass.
 *   2) No active classification on the record → pass.
 *   3) User clearance level must be >= entity classification level.
 *   4) Null user (CLI) → pass.
 *
 * The classification used is the CURRENT one on the entity — never the
 * historical one in the version being restored.
 *
 * @phase J
 */

namespace AhgVersionControl\Services;

use Illuminate\Support\Facades\DB;

class ClearanceCheck
{
    public function canUserRestore(?int $userId, int $entityId): bool
    {
        if ($userId === null) {
            return true;
        }

        try {
            if ($this->userIsAdministrator($userId)) {
                return true;
            }
            $entityLevel = $this->resolveEntityClassificationLevel($entityId);
            if ($entityLevel === null) {
                return true;
            }
            return $this->resolveUserClearanceLevel($userId) >= $entityLevel;
        } catch (\Throwable $e) {
            // ahgSecurityClearancePlugin not installed → fail OPEN.
            return true;
        }
    }

    public function explainDenial(?int $userId, int $entityId): ?string
    {
        if ($this->canUserRestore($userId, $entityId)) {
            return null;
        }
        try {
            $level = $this->resolveEntityClassificationLevel($entityId);
            $row = DB::table('security_classification')->where('level', $level)->first();
            $entityClass = $row ? "{$row->name} (level {$level})" : "level {$level}";
            $userLevel = $userId !== null ? $this->resolveUserClearanceLevel($userId) : 0;
            return "This record is classified {$entityClass}; your clearance level is {$userLevel}. Restore is not permitted.";
        } catch (\Throwable $e) {
            return 'Insufficient security clearance to restore this record.';
        }
    }

    /** AtoM administrator ACL group id. */
    private const ACL_GROUP_ADMINISTRATOR = 100;

    private function userIsAdministrator(int $userId): bool
    {
        try {
            return DB::table('acl_user_group')
                ->where('user_id', $userId)
                ->where('group_id', self::ACL_GROUP_ADMINISTRATOR)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveEntityClassificationLevel(int $entityId): ?int
    {
        $level = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
            ->where('osc.object_id', $entityId)
            ->where('osc.active', 1)
            ->value('sc.level');
        return $level !== null ? (int) $level : null;
    }

    private function resolveUserClearanceLevel(int $userId): int
    {
        $today = date('Y-m-d');
        $level = DB::table('user_security_clearance as usc')
            ->join('security_classification as sc', 'sc.id', '=', 'usc.classification_id')
            ->where('usc.user_id', $userId)
            ->where(function ($q) use ($today) {
                $q->whereNull('usc.expires_at')->orWhere('usc.expires_at', '>=', $today);
            })
            ->max('sc.level');
        return (int) ($level ?? 0);
    }
}
