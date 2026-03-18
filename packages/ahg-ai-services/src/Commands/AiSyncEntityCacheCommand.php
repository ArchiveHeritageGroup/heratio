<?php

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiSyncEntityCacheCommand extends Command
{
    protected $signature = 'ahg:ai-sync-entity-cache';
    protected $description = 'Rebuild NER entity search cache';

    public function handle(): int
    {
        $this->info('Starting NER entity cache rebuild...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
