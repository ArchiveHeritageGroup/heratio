<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditRetentionCommand extends Command
{
    protected $signature = 'ahg:audit-retention
        {--days= : Override per-table retention from ahg_audit_retention_policy (applies to all known audit tables)}
        {--archive-dir= : Override archive_path for archived rows (NDJSON gzip per-table, named YYYY-MM-DD.<table>.ndjson.gz)}
        {--dry-run : Report what would be deleted/archived without writing}';

    protected $description = 'Purge old audit log entries per ahg_audit_retention_policy (with optional archive)';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_audit_retention_policy')) {
            $this->warn('ahg_audit_retention_policy missing — falling back to global default 365d on audit_log only.');
            return $this->fallbackPurge(365);
        }

        $policies = DB::table('ahg_audit_retention_policy')->get();
        $dry = (bool) $this->option('dry-run');
        $forceDays = $this->option('days');
        $archiveOverride = $this->option('archive-dir');

        $totalDeleted = 0; $totalArchived = 0;
        foreach ($policies as $p) {
            $days = $forceDays !== null ? max(1, (int) $forceDays) : max(1, (int) $p->retention_days);
            $cutoff = now()->subDays($days);
            $table = $p->log_type;

            if (! Schema::hasTable($table)) {
                $this->warn("  [{$table}] table does not exist — skipping.");
                continue;
            }

            // Determine the column representing creation time. Most audit tables use created_at.
            $tsCol = Schema::hasColumn($table, 'created_at') ? 'created_at'
                  : (Schema::hasColumn($table, 'logged_at') ? 'logged_at'
                  : (Schema::hasColumn($table, 'occurred_at') ? 'occurred_at' : null));
            if (! $tsCol) {
                $this->warn("  [{$table}] no recognisable timestamp column — skipping.");
                continue;
            }

            $eligible = (int) DB::table($table)->where($tsCol, '<', $cutoff)->count();
            if ($eligible === 0) {
                $this->info("  [{$table}] keep_days={$days} eligible=0");
                continue;
            }

            if ($p->archive_before_delete && ! $dry) {
                $dir = rtrim((string) ($archiveOverride ?: $p->archive_path ?: config('heratio.backups_path', '/tmp')), '/') . '/audit-archive';
                if (! is_dir($dir)) @mkdir($dir, 0775, true);
                $file = $dir . '/' . now()->toDateString() . ".{$table}.ndjson.gz";
                $gz = gzopen($file, 'ab');
                $rows = DB::table($table)->where($tsCol, '<', $cutoff)->orderBy('id')->get();
                foreach ($rows as $row) gzwrite($gz, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
                gzclose($gz);
                $totalArchived += count($rows);
            }

            $this->info("  [{$table}] keep_days={$days} eligible={$eligible}" . ($dry ? ' (dry-run)' : ''));
            if (! $dry) {
                $totalDeleted += (int) DB::table($table)->where($tsCol, '<', $cutoff)->delete();
                DB::table('ahg_audit_retention_policy')->where('id', $p->id)->update(['last_cleanup_at' => now()]);
            }
        }

        $this->info("done; archived={$totalArchived} deleted={$totalDeleted}");
        return self::SUCCESS;
    }

    private function fallbackPurge(int $days): int
    {
        if (! Schema::hasTable('audit_log')) return self::SUCCESS;
        $cutoff = now()->subDays($days);
        $deleted = (int) DB::table('audit_log')->where('created_at', '<', $cutoff)->delete();
        $this->info("[audit_log] keep_days={$days} deleted={$deleted}");
        return self::SUCCESS;
    }
}
