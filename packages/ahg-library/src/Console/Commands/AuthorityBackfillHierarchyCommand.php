<?php

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\AuthorityHierarchyService;
use Illuminate\Console\Command;

class AuthorityBackfillHierarchyCommand extends Command
{
    protected $signature = 'ahg:library-authority-backfill-hierarchy {--dry-run : Report without writing}';
    protected $description = 'Convert flat broader/narrower term lists into authority relations';

    public function handle(AuthorityHierarchyService $hierarchy): int
    {
        $totals = $hierarchy->backfillFlatTerms((bool) $this->option('dry-run'));
        $this->info("Linked {$totals['linked']}, unresolved {$totals['unresolved']}, rejected {$totals['rejected']}.");

        return self::SUCCESS;
    }
}
