<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PurgeCommand extends Command
{
    protected $signature = 'heratio:tools:purge
                            {--force : Skip confirmation prompt}
                            {--demo : Load demo data after purge}';

    protected $description = 'Remove all data from the database (dangerous!)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('WARNING: This will remove ALL data from the database!');
            $this->warn('This action cannot be undone.');

            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->info('Purge cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Purging database...');

        $cmd = 'php /usr/share/nginx/archive/symfony tools:purge --no-confirmation';

        if ($this->option('demo')) {
            $cmd .= ' --demo';
        }

        passthru($cmd, $exitCode);

        if ($exitCode === 0) {
            $this->info('Database purged successfully.');

            // Also clear Laravel caches
            $this->call('cache:clear');
        } else {
            $this->error('Purge failed with exit code: ' . $exitCode);
        }

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
