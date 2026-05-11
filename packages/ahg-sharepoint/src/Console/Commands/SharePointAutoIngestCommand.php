<?php

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Services\SharePointAutoIngestService;
use Illuminate\Console\Command;

/**
 * php artisan sharepoint:auto-ingest [--rule= --dry-run --force]
 *
 * @phase 2 (v2 ingest plan, step 3)
 */
class SharePointAutoIngestCommand extends Command
{
    protected $signature = 'sharepoint:auto-ingest {--rule= : Run one rule by id} {--dry-run : Log what would happen} {--force : Ignore schedule_cron}';
    protected $description = 'Cron-driven SharePoint→Heratio ingest';

    public function handle(SharePointAutoIngestService $svc): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $results = $this->option('rule')
            ? [$svc->runRule((int) $this->option('rule'), $dryRun)]
            : $svc->runDueRules($force, $dryRun);

        $this->info(sprintf('Processed %d rule(s)', count($results)));
        foreach ($results as $r) {
            $this->line(sprintf(
                '  rule=%d  status=%s  new=%d  skipped=%d  %s%s',
                $r['rule_id'],
                $r['status'],
                $r['items_new'] ?? 0,
                $r['items_skipped'] ?? 0,
                isset($r['session_id']) ? "session={$r['session_id']} job={$r['job_id']}" : '',
                isset($r['error']) ? "  ERROR: {$r['error']}" : '',
            ));
        }
        return self::SUCCESS;
    }
}
