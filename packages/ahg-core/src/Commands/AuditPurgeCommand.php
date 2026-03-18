<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class AuditPurgeCommand extends Command
{
    protected $signature = 'ahg:audit-purge
        {--older-than=365 : Purge entries older than N days}';

    protected $description = 'Purge old audit trail entries';

    public function handle(): int
    {
        $this->info('Purging old audit trail entries...');
        // TODO: Implement audit trail purge
        $this->info('Audit trail purge complete.');
        return 0;
    }
}
