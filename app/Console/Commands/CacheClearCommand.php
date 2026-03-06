<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CacheClearCommand extends Command
{
    protected $signature = 'heratio:cache:clear';

    protected $description = 'Clear all caches (Laravel + Symfony)';

    public function handle(): int
    {
        $this->info('Clearing Laravel caches...');
        $this->call('cache:clear');
        $this->call('view:clear');
        $this->call('route:clear');
        $this->call('config:clear');

        $this->info('Clearing Symfony cache...');
        passthru('rm -rf /usr/share/nginx/archive/cache/*', $exitCode);

        if ($exitCode !== 0) {
            $this->warn('Failed to clear Symfony cache directory.');
        }

        $this->info('All caches cleared.');

        return self::SUCCESS;
    }
}
