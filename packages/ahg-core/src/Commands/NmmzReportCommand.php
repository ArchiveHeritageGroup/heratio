<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class NmmzReportCommand extends Command
{
    protected $signature = 'ahg:nmmz-report
        {--format=table : Output format (table, json, csv)}';

    protected $description = 'Zimbabwe NMMZ monuments report';

    public function handle(): int
    {
        $this->info('Generating Zimbabwe NMMZ monuments report...');
        // TODO: Implement NMMZ monuments reporting
        $this->info('NMMZ monuments report complete.');
        return 0;
    }
}
