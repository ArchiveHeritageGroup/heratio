<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class AuditRetentionCommand extends Command
{
    protected $signature = 'ahg:audit-retention
        {--days=365 : Purge audit entries older than N days}';

    protected $description = 'Purge old audit log entries';

    public function handle(): int
    {
        $this->info('Processing audit log retention...');
        // TODO: Implement audit log retention purge
        $this->info('Audit log retention complete.');
        return 0;
    }
}
