<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditPurgeCommand extends Command
{
    protected $signature = 'ahg:audit-purge
        {--table=audit_log : Specific audit table to purge (default audit_log)}
        {--older-than=365 : Purge entries older than N days}
        {--dry-run : Report without deleting}';

    protected $description = 'Purge old entries from a specific audit trail table (manual / one-shot; for policy-driven purge use ahg:audit-retention)';

    public function handle(): int
    {
        $table = (string) $this->option('table');
        $days  = max(1, (int) $this->option('older-than'));
        $dry   = (bool) $this->option('dry-run');

        if (! Schema::hasTable($table)) {
            $this->error("Table {$table} does not exist.");
            return self::FAILURE;
        }
        // Only allow tables whose name looks like an audit table — defence against typos.
        if (! preg_match('/audit/i', $table)) {
            $this->error("Refusing to purge {$table} — name does not contain 'audit'.");
            return self::FAILURE;
        }

        $tsCol = Schema::hasColumn($table, 'created_at') ? 'created_at'
              : (Schema::hasColumn($table, 'logged_at') ? 'logged_at'
              : (Schema::hasColumn($table, 'occurred_at') ? 'occurred_at' : null));
        if (! $tsCol) {
            $this->error("Table {$table} has no created_at/logged_at/occurred_at column — cannot determine age.");
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $eligible = (int) DB::table($table)->where($tsCol, '<', $cutoff)->count();
        $this->info("[{$table}] cutoff={$cutoff->toIso8601String()} eligible={$eligible}" . ($dry ? ' (dry-run)' : ''));
        if ($dry || $eligible === 0) return self::SUCCESS;

        $deleted = (int) DB::table($table)->where($tsCol, '<', $cutoff)->delete();
        $this->info("deleted={$deleted}");
        return self::SUCCESS;
    }
}
