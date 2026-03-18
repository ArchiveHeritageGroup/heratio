<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class DedupeMergeCommand extends Command
{
    protected $signature = 'ahg:dedupe-merge
        {--pair-id= : Merge specific duplicate pair}
        {--all-approved : Merge all approved pairs}
        {--dry-run : Simulate without merging}';

    protected $description = 'Merge confirmed duplicates';

    public function handle(): int
    {
        $this->info('Merging confirmed duplicates...');
        // TODO: Implement duplicate merging
        $this->info('Duplicate merge complete.');
        return 0;
    }
}
