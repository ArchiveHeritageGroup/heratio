<?php

/**
 * CalculateFinesCommand - daily sweep that creates/updates overdue fines
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

class CalculateFinesCommand extends Command
{
    protected $signature = 'ahg:library-calculate-fines';
    protected $description = 'Sweep all overdue active checkouts and ensure each has a current overdue fine row.';

    public function handle(LibraryCirculationService $circ): int
    {
        if (!LibrarySettings::autoFine()) {
            $this->info('library_auto_fine is disabled; nothing to do.');
            return self::SUCCESS;
        }
        $processed = $circ->calculateAllOverdueFines();
        $this->info("Processed $processed overdue checkout(s).");
        return self::SUCCESS;
    }
}
