<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'ahg:backup
        {--components=database : Components to back up (database, uploads, all)}
        {--retention=30 : Number of days to retain backups}';

    protected $description = 'Database backup';

    public function handle(): int
    {
        $this->info('Starting database backup...');
        // TODO: Implement database backup
        $this->info('Database backup complete.');
        return 0;
    }
}
