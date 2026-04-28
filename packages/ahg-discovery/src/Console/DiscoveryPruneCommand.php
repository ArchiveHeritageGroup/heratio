<?php

/**
 * DiscoveryPruneCommand — bound the growth of ahg_discovery_log so query
 * telemetry capture can be left on for real users without the table running
 * away. Implements GitHub issue #19.
 *
 * Retention default = 90 days, overridable per run (--keep-days=N) or
 * persistently via ahg_settings.ahg_discovery_log_retention_days.
 *
 * Designed to run hourly via /etc/cron.d/ahg-discovery-prune; the outer
 * flock guard is in the cron entry, not in PHP.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * AGPL-3.0-or-later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgDiscovery\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiscoveryPruneCommand extends Command
{
    protected $signature = 'ahg:discovery-prune
        {--keep-days= : Override retention window (default reads ahg_settings.ahg_discovery_log_retention_days)}
        {--batch=10000 : Max rows to delete per DELETE statement (LIMIT clause)}
        {--max-batches=100 : Safety cap on total batches per run}
        {--dry-run : Report what would be deleted, without deleting}';

    protected $description = 'Prune ahg_discovery_log rows older than the retention window';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_discovery_log')) {
            $this->warn('ahg_discovery_log table does not exist; nothing to prune.');
            return self::SUCCESS;
        }

        $keepDays = $this->option('keep-days');
        if ($keepDays === null) {
            $keepDays = (int) (DB::table('ahg_settings')
                ->where('setting_key', 'ahg_discovery_log_retention_days')
                ->value('setting_value') ?? 90);
        }
        $keepDays = max(1, (int) $keepDays);

        $batch = max(100, (int) $this->option('batch'));
        $maxBatches = max(1, (int) $this->option('max-batches'));
        $dry = (bool) $this->option('dry-run');

        $this->checkCreatedAtIndex();

        $cutoff = now()->subDays($keepDays);
        $eligible = (int) DB::table('ahg_discovery_log')
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->info(sprintf(
            '[%s] keep_days=%d cutoff=%s eligible_rows=%d batch=%d max_batches=%d%s',
            now()->toIso8601String(),
            $keepDays,
            $cutoff->toIso8601String(),
            $eligible,
            $batch,
            $maxBatches,
            $dry ? ' DRY-RUN' : ''
        ));

        if ($dry || $eligible === 0) {
            return self::SUCCESS;
        }

        $deletedTotal = 0;
        for ($i = 1; $i <= $maxBatches; $i++) {
            $deleted = (int) DB::table('ahg_discovery_log')
                ->where('created_at', '<', $cutoff)
                ->limit($batch)
                ->delete();
            $deletedTotal += $deleted;
            if ($deleted < $batch) {
                break;
            }
        }

        $this->info("deleted_rows={$deletedTotal}");
        return self::SUCCESS;
    }

    /**
     * The schema ships with idx_created on (created_at); if it's been dropped,
     * a delete by created_at < cutoff turns into a full-table scan and the
     * cron starts piling up. Warn loudly rather than fail silently.
     */
    protected function checkCreatedAtIndex(): void
    {
        try {
            $indexes = DB::select(
                "SHOW INDEXES FROM ahg_discovery_log WHERE Column_name = 'created_at'"
            );
            if (empty($indexes)) {
                $this->warn('No index on ahg_discovery_log.created_at — pruning will full-scan. Recommend: ALTER TABLE ahg_discovery_log ADD INDEX idx_created (created_at).');
            }
        } catch (\Throwable $e) {
            // SHOW INDEXES against a missing table is already handled upstream.
        }
    }
}
