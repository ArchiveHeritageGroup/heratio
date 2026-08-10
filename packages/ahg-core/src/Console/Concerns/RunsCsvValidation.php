<?php

/**
 * RunsCsvValidation - shared "validate only" reporting for sector CSV import commands.
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

namespace AhgCore\Console\Concerns;

use AhgCore\Services\Import\SectorCsvImporter;

/**
 * Run a sector CSV importer's validation pass and print the report
 * (totals, first 20 errors, first 20 warnings), returning 0 if valid
 * else 1. The body was byte-identical in all five sector import commands
 * (Dam, Gallery, Archives, Library, Museum) - they differed only in the
 * concrete importer type-hint, all of which extend SectorCsvImporter.
 *
 * Used by Illuminate\Console\Command subclasses (info/line/error/warn/
 * newLine come from the host command).
 */
trait RunsCsvValidation
{
    protected function runValidation(SectorCsvImporter $importer, string $filename): int
    {
        $this->info('Running validation only (no import)...');

        $report = $importer->validate($filename);

        $this->newLine();
        $this->info('=== Validation Results ===');
        $this->line(sprintf('Total rows: %d', $report['total']));
        $this->line(sprintf('Errors: %d', count($report['errors'])));
        $this->line(sprintf('Warnings: %d', count($report['warnings'])));

        if (!$report['valid']) {
            $this->newLine();
            $this->error('Errors found:');
            foreach (array_slice($report['errors'], 0, 20) as $err) {
                $this->line('  ' . $err);
            }
        }

        if (!empty($report['warnings'])) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach (array_slice($report['warnings'], 0, 20) as $warn) {
                $this->line('  ' . $warn);
            }
        }

        return $report['valid'] ? 0 : 1;
    }
}
