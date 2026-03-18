<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class LibraryPatronExpiryCommand extends Command
{
    protected $signature = 'ahg:library-patron-expiry
        {--grace-days=0 : Grace period in days}
        {--dry-run : Simulate without flagging}';

    protected $description = 'Flag expired memberships';

    public function handle(): int
    {
        $this->info('Flagging expired patron memberships...');
        // TODO: Implement patron expiry flagging
        $this->info('Patron expiry flagging complete.');
        return 0;
    }
}
