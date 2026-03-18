<?php

namespace Ahg3dModel\Commands;

use Illuminate\Console\Command;

class TriposrPreloadCommand extends Command
{
    protected $signature = 'ahg:triposr-preload';
    protected $description = 'Preload TripoSR model into memory';

    public function handle(): int
    {
        $this->info('Starting TripoSR model preload...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
