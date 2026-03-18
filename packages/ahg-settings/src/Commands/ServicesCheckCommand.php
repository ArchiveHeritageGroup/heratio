<?php

namespace AhgSettings\Commands;

use Illuminate\Console\Command;

class ServicesCheckCommand extends Command
{
    protected $signature = 'ahg:services-check';
    protected $description = 'Check all system services and send alerts';

    public function handle(): int
    {
        $this->info('Starting system services check...');
        // TODO: Implement
        $this->info('Done.');
        return 0;
    }
}
