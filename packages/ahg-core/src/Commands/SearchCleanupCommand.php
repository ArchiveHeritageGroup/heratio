<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class SearchCleanupCommand extends Command
{
    protected $signature = 'ahg:search-cleanup
        {--dry-run : Show what would be removed without deleting}';

    protected $description = 'Remove stale search entries';

    public function handle(): int
    {
        $this->info('Starting search index cleanup...');
        // TODO: Implement stale search entry removal
        $this->info('Search index cleanup complete.');
        return 0;
    }
}
