<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class PreservationSchedulerCommand extends Command
{
    protected $signature = 'ahg:preservation-scheduler
        {--dry-run : Simulate without executing}
        {--force : Force run even if not scheduled}';

    protected $description = 'Run scheduled preservation workflows';

    public function handle(): int
    {
        $this->info('Running scheduled preservation workflows...');
        // TODO: Implement preservation scheduler
        $this->info('Preservation scheduler complete.');
        return 0;
    }
}
