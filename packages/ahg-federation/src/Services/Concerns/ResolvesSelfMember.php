<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgFederation\Services\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Return this node's own federation-member row (is_self = 1), or null. Was
 * byte-identical in LoanAnalyticsService and LoanRequestService. The using
 * class must expose a `tableReady()` guard and a `MEMBER_TABLE` constant
 * (both do).
 */
trait ResolvesSelfMember
{
    public function selfMember(): ?object
    {
        if (! $this->tableReady(self::MEMBER_TABLE)) {
            return null;
        }
        try {
            return DB::table(self::MEMBER_TABLE)
                ->where('is_self', 1)
                ->orderBy('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
