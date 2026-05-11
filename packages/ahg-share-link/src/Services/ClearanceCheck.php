<?php

/**
 * ClearanceCheck — record-level security clearance lookup for share-link
 * issuance. Mirror of the AtoM-side service.
 *
 * @phase C
 */

namespace AhgShareLink\Services;

use Illuminate\Support\Facades\DB;

class ClearanceCheck
{
    private const ACL_GROUP_ADMINISTRATOR = 100;

    public function canUserIssueLink(?int $userId, int $entityId): bool
    {
        if ($userId === null) {
            return false;
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
            return true;
        }
    }

    public function resolveEntityClassificationLevel(int $entityId): ?int
    {
        $level = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
            ->where('osc.object_id', $entityId)
            ->where('osc.active', 1)
            ->value('sc.level');
        return $level !== null ? (int) $level : null;
    }

    public function explainDenial(?int $userId, int $entityId): string
    {
        try {
            $level = $this->resolveEntityClassificationLevel($entityId);
            $row = DB::table('security_classification')->where('level', $level)->first();
            $entityClass = $row ? "{$row->name} (level {$level})" : "level {$level}";
            $userLevel = $userId !== null ? $this->resolveUserClearanceLevel($userId) : 0;
            return "This record is classified {$entityClass}; your clearance level is {$userLevel}. You cannot issue a share link for it.";
        } catch (\Throwable $e) {
            return 'Insufficient security clearance to issue a share link for this record.';
        }
    }

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
