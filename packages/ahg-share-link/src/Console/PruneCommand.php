<?php

/**
 * php artisan share-link:prune [--dry-run]
 *
 * Runs PruneService — applies retention rules to share-link tables.
 *
 * Retention settings (read from ahg_settings):
 *   share_link.token_retain_days       (default 365)
 *   share_link.access_log_retain_days  (default 180)
 *
 * Mirror of AtoM's `php symfony share-link:prune` CLI task.
 *
 * @phase H
 */

namespace AhgShareLink\Console;

use AhgShareLink\Services\PruneService;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'share-link:prune
        {--dry-run : Report what would be pruned without deleting}';

    protected $description = 'Apply retention rules to share-link tokens + access log.';

    public function handle(PruneService $svc): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = $svc->prune($dryRun);

        $this->info(sprintf(
            'share-link:prune  token_retain_days=%d  access_log_retain_days=%d  dry_run=%s',
            $summary['token_retain_days'],
            $summary['access_log_retain_days'],
            $dryRun ? 'yes' : 'no',
        ));
        $this->line(sprintf(
            '  %s %d token row(s)',
            $dryRun ? 'would delete' : 'deleted',
            $summary['tokens_deleted'],
        ));
        $this->line(sprintf(
            '  %s %d access-log row(s)',
            $dryRun ? 'would delete' : 'deleted',
            $summary['access_rows_deleted'],
        ));

        return self::SUCCESS;
    }
}
