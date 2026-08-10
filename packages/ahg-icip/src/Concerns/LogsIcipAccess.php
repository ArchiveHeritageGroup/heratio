<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgIcip\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append an ICIP access-decision audit row (fail-safe). Byte-identical body
 * in IcipAccessService::logAccess and IcipController::logIcipAccess; both
 * names are provided (logIcipAccess delegates) so call sites are unchanged.
 */
trait LogsIcipAccess
{
    protected function logAccess(int $objectId, ?int $userId, array $result): void
    {
        if (! Schema::hasTable('icip_access_log')) {
            return;
        }
        try {
            $decision = $result['allowed']
                ? (! empty($result['requires_consultation']) ? 'allow_consultation' : 'allow')
                : 'deny';
            $types = collect($result['restrictions'] ?? [])
                ->pluck('restriction_type')->filter()->unique()->implode(',');
            $reason = $result['blocked_reason']
                ?? $result['access_reason']
                ?? (! empty($result['requires_consultation'])
                    ? 'Consultation required: '.implode(', ', $result['consultation_restrictions'] ?? [])
                    : 'Access to ICIP-restricted object');
            DB::table('icip_access_log')->insert([
                'information_object_id' => $objectId,
                'user_id' => $userId,
                'decision' => $decision,
                'restriction_types' => mb_substr((string) $types, 0, 255),
                'reason' => mb_substr((string) $reason, 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // audit must never break access evaluation
        }
    }

    /** Alias of logAccess() - kept for IcipController's call sites. */
    protected function logIcipAccess(int $objectId, ?int $userId, array $result): void
    {
        $this->logAccess($objectId, $userId, $result);
    }
}
