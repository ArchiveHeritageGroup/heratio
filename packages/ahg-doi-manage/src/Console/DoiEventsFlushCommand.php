<?php

/**
 * doi:events-flush - retry pending / failed DataCite event submissions.
 *
 * Issue #654 Phase 3. Walks ahg_datacite_event for rows in state 'pending'
 * or 'failed' and pushes each via DataciteEventsService::submit(). The
 * --dry-run mode prints what would be sent without hitting the network.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Console;

use AhgDoiManage\Services\DataciteEventsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DoiEventsFlushCommand extends Command
{
    protected $signature = 'doi:events-flush
        {--limit=200 : maximum number of rows to flush this run}
        {--max-attempts=5 : skip rows whose attempts >= this value}
        {--dry-run : log the targets without submitting}';

    protected $description = 'Issue #654 - retry pending / failed DataCite Events API submissions';

    public function handle(DataciteEventsService $svc): int
    {
        if (! Schema::hasTable('ahg_datacite_event')) {
            $this->warn('ahg_datacite_event table is missing - has the package booted?');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $dry = (bool) $this->option('dry-run');

        $rows = DB::table('ahg_datacite_event')
            ->whereIn('state', ['pending', 'failed'])
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info(sprintf('Flushing %d ahg_datacite_event rows%s', $rows->count(), $dry ? ' [dry-run]' : ''));

        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            if ($dry) {
                $this->line(sprintf(
                    '  would submit id=%d subj=%s rel=%s obj=%s',
                    $row->id, $row->subj_id, $row->relation_type_id, $row->obj_id,
                ));
                continue;
            }
            if ($svc->submit((int) $row->id)) {
                $ok++;
            } else {
                $fail++;
            }
        }

        $this->info(sprintf('Done. submitted=%d failed=%d', $ok, $fail));
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
