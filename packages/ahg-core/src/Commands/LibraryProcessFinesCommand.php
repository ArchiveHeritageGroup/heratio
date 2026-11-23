<?php

/**
 * LibraryProcessFinesCommand - accrue / record overdue fines per loan_rule.
 *
 * Runs the shared fine sweep in LibraryCirculationService: every open loan
 * past due, by branch and patron-type rule, excluding closed days and grace.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use AhgLibrary\Services\LibraryCirculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class LibraryProcessFinesCommand extends Command
{
    protected $signature = 'ahg:library-process-fines
        {--dry-run : Calculate fines without applying}';

    protected $description = 'Calculate overdue fines per loan_rule';

    public function handle(): int
    {
        if (! Schema::hasTable('library_checkout') || ! Schema::hasTable('library_fine')) {
            $this->warn('library_checkout or library_fine missing');

            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');

        // The same calculation as ahg:library-calculate-fines, so the two
        // commands agree and running both cannot charge a day twice.
        $sweep = app(LibraryCirculationService::class)->sweepOverdueFines($dry);
        $this->info(sprintf('accrued=%d total=%s%s', $sweep['loans'], number_format($sweep['total'], 2), $dry ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
