<?php

/**
 * ResolvesUserClearanceLevel - shared max-security-clearance lookup.
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

namespace AhgCore\Support\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Resolve a user's highest current (non-expired) security-clearance level.
 * Was byte-identical in the ClearanceCheck services of ahg-share-link and
 * ahg-version-control (whose surrounding classes otherwise differ).
 */
trait ResolvesUserClearanceLevel
{
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
