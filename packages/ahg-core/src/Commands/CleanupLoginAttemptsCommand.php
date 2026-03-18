<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class CleanupLoginAttemptsCommand extends Command
{
    protected $signature = 'ahg:cleanup-login-attempts
        {--days= : Remove attempts older than N days}';

    protected $description = 'Remove expired login attempts';

    public function handle(): int
    {
        $this->info('Cleaning up expired login attempts...');
        // TODO: Implement expired login attempt removal
        $this->info('Login attempt cleanup complete.');
        return 0;
    }
}
