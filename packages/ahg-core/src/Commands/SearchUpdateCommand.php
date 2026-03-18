<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class SearchUpdateCommand extends Command
{
    protected $signature = 'ahg:search-update
        {--since= : Only update records modified since this datetime}
        {--type= : Only update a specific document type}';

    protected $description = 'Incremental search index update';

    public function handle(): int
    {
        $this->info('Starting incremental search index update...');
        // TODO: Implement incremental search index update
        $this->info('Incremental search index update complete.');
        return 0;
    }
}
