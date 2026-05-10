<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;

class SharePointInstallCommand extends Command
{
    protected $signature = 'sharepoint:install {--dry-run : Print SQL without executing}';
    protected $description = 'Install ahg-sharepoint schema (idempotent). Run package migrations.';

    public function handle(): int
    {
        $this->info('Running migrations from ahg-sharepoint package...');
        $this->call('migrate', [
            '--path' => 'packages/ahg-sharepoint/database/migrations',
            '--force' => true,
        ]);
        $this->info('Done.');
        return self::SUCCESS;
    }
}
