<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class BackupCleanupCommand extends Command
{
    protected $signature = 'ahg:backup-cleanup
        {--dry-run : Show what would be removed without deleting}';

    protected $description = 'Remove old backups past retention';

    public function handle(): int
    {
        $this->info('Starting backup cleanup...');
        // TODO: Implement old backup removal
        $this->info('Backup cleanup complete.');
        return 0;
    }
}
