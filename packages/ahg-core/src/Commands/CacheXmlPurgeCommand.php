<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class CacheXmlPurgeCommand extends Command
{
    protected $signature = 'ahg:cache-xml-purge
        {--format= : Only purge a specific XML format (ead, dc, mods)}
        {--older-than= : Only purge files older than N days}';

    protected $description = 'Purge cached XML exports';

    public function handle(): int
    {
        $this->info('Purging cached XML exports...');
        // TODO: Implement cached XML export purge
        $this->info('Cached XML export purge complete.');
        return 0;
    }
}
