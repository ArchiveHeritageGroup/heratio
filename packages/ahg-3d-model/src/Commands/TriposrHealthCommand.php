<?php

namespace Ahg3dModel\Commands;

use Illuminate\Console\Command;

class TriposrHealthCommand extends Command
{
    protected $signature = 'ahg:triposr-health';
    protected $description = 'Check TripoSR API health';

    public function handle(): int
    {
        $this->info('Starting TripoSR health check...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
