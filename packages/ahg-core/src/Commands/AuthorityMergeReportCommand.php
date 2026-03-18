<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class AuthorityMergeReportCommand extends Command
{
    protected $signature = 'ahg:authority-merge-report';

    protected $description = 'Merge/split operations report';

    public function handle(): int
    {
        $this->info('Generating authority merge/split report...');
        // TODO: Implement authority merge/split reporting
        $this->info('Authority merge/split report complete.');
        return 0;
    }
}
