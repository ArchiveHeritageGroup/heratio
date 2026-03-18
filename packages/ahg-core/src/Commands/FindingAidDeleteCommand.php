<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class FindingAidDeleteCommand extends Command
{
    protected $signature = 'ahg:finding-aid-delete
        {--slug= : Delete finding aid for specific slug}
        {--older-than= : Delete finding aids older than N days}';

    protected $description = 'Delete finding aids';

    public function handle(): int
    {
        $this->info('Deleting finding aids...');
        // TODO: Implement finding aid deletion
        $this->info('Finding aid deletion complete.');
        return 0;
    }
}
