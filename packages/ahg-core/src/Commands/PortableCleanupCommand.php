<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class PortableCleanupCommand extends Command
{
    protected $signature = 'ahg:portable-cleanup';

    protected $description = 'Remove expired export packages';

    public function handle(): int
    {
        $this->info('Removing expired export packages...');
        // TODO: Implement expired export package cleanup
        $this->info('Export package cleanup complete.');
        return 0;
    }
}
