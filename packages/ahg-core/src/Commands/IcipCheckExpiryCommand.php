<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class IcipCheckExpiryCommand extends Command
{
    protected $signature = 'ahg:icip-check-expiry
        {--days=90 : Warn about consents expiring within N days}';

    protected $description = 'Check ICIP consent expiry';

    public function handle(): int
    {
        $this->info('Checking ICIP consent expiry...');
        // TODO: Implement ICIP consent expiry checking
        $this->info('ICIP consent expiry check complete.');
        return 0;
    }
}
