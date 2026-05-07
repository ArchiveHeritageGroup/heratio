<?php

/**
 * AutoExpireHoldsCommand - daily sweep of pending holds past their pickup date
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

use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Support\LibrarySettings;
use Illuminate\Console\Command;

class AutoExpireHoldsCommand extends Command
{
    protected $signature = 'ahg:library-auto-expire-holds {--dry-run}';
    protected $description = 'Mark library holds whose pickup window has passed as expired.';

    public function handle(LibraryCirculationService $circ): int
    {
        if (!LibrarySettings::autoExpireHolds()) {
            $this->info('library_auto_expire_holds is disabled; nothing to do.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $count = \Illuminate\Support\Facades\DB::table('library_hold')
                ->where('status', 'pending')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', date('Y-m-d'))
                ->count();
            $this->info("Dry run: $count hold(s) would be expired.");
            return self::SUCCESS;
        }

        $expired = $circ->autoExpireHolds();
        $this->info("Expired $expired hold(s).");
        return self::SUCCESS;
    }
}
