<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class NazTransferDueCommand extends Command
{
    protected $signature = 'ahg:naz-transfer-due
        {--days=90 : Days until transfer deadline}';

    protected $description = 'Zimbabwe NAZ transfer due';

    public function handle(): int
    {
        $this->info('Checking Zimbabwe NAZ transfer deadlines...');
        // TODO: Implement NAZ transfer due check
        $this->info('NAZ transfer due check complete.');
        return 0;
    }
}
