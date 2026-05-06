<?php

namespace App\Console\Commands;

use App\Auth\SecuritySettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * auth:gc-attempts — drop login_attempt rows older than the configured
 * retention. Reads
 * ahg_settings.security_login_attempt_cleanup_hours (default 24). Closes
 * the cleanup-hours half of audit issue #90. Scheduled hourly from
 * AppServiceProvider::boot — the command does its own no-op short-circuit
 * if retention is set to 0 or the table is missing.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */
class AuthGcAttemptsCommand extends Command
{
    protected $signature = 'auth:gc-attempts {--dry-run}';
    protected $description = 'Delete login_attempt rows older than ahg_settings.security_login_attempt_cleanup_hours';

    public function handle(): int
    {
        $hours = SecuritySettings::loginAttemptCleanupHours();
        if ($hours <= 0) {
            $this->info('Cleanup disabled (security_login_attempt_cleanup_hours <= 0). No-op.');
            return self::SUCCESS;
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('login_attempt')) {
            $this->warn('login_attempt table missing — nothing to clean up.');
            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours);
        $count = DB::table('login_attempt')->where('attempted_at', '<', $cutoff)->count();
        if ($count === 0) {
            $this->info("No login_attempt rows older than {$cutoff} ({$hours}h retention).");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("[DRY RUN] would delete {$count} row(s) older than {$cutoff}");
            return self::SUCCESS;
        }

        $deleted = DB::table('login_attempt')->where('attempted_at', '<', $cutoff)->delete();
        $this->info("Deleted {$deleted} login_attempt row(s) older than {$cutoff} ({$hours}h retention).");
        return self::SUCCESS;
    }
}
