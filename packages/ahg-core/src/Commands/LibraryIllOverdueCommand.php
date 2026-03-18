<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class LibraryIllOverdueCommand extends Command
{
    protected $signature = 'ahg:library-ill-overdue
        {--days=1 : Items overdue by at least N days}';

    protected $description = 'Report overdue ILL items';

    public function handle(): int
    {
        $this->info('Reporting overdue ILL items...');
        // TODO: Implement ILL overdue reporting
        $this->info('ILL overdue report complete.');
        return 0;
    }
}
