<?php

/**
 * AutoExpirePatronsCommand - daily sweep of patrons past membership_expiry + grace
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

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\LibraryPatronService;
use AhgLibrary\Support\LibrarySettings;
use Illuminate\Console\Command;

class AutoExpirePatronsCommand extends Command
{
    protected $signature = 'ahg:library-auto-expire-patrons {--dry-run}';
    protected $description = 'Mark active library patrons past their membership_expiry (with grace) as expired.';

    public function handle(LibraryPatronService $patrons): int
    {
        if (!LibrarySettings::autoExpirePatrons()) {
            $this->info('library_auto_expire_patrons is disabled; nothing to do.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $grace = LibrarySettings::patronExpiryGraceDays();
            $cutoff = date('Y-m-d', strtotime("-{$grace} days"));
            $count = \Illuminate\Support\Facades\DB::table('library_patron')
                ->where('borrowing_status', 'active')
                ->whereNotNull('membership_expiry')
                ->where('membership_expiry', '<', $cutoff)
                ->count();
            $this->info("Dry run: $count patron(s) would be expired (grace={$grace} days).");
            return self::SUCCESS;
        }

        $expired = $patrons->expireLapsed();
        $this->info("Expired $expired patron(s).");
        return self::SUCCESS;
    }
}
