<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupLoginAttemptsCommand extends Command
{
    protected $signature = 'ahg:cleanup-login-attempts
        {--days= : Remove attempts older than N days (default 30)}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Remove expired login attempts';

    public function handle(): int
    {
        if (! Schema::hasTable('login_attempt')) {
            $this->warn('login_attempt table missing — nothing to do.');
            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?? 30);
        $cutoff = now()->subDays(max(1, $days));
        $dry = (bool) $this->option('dry-run');

        $eligible = (int) DB::table('login_attempt')->where('attempted_at', '<', $cutoff)->count();
        $this->info("[login_attempt] cutoff={$cutoff->toIso8601String()} eligible={$eligible}" . ($dry ? ' (dry-run)' : ''));

        if ($dry || $eligible === 0) return self::SUCCESS;

        $deleted = DB::table('login_attempt')->where('attempted_at', '<', $cutoff)->delete();
        $this->info("deleted={$deleted}");
        return self::SUCCESS;
    }
}
