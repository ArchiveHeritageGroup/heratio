<?php

namespace AhgCore\Commands;

use AhgDoiManage\Services\DoiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DoiProcessQueueCommand extends Command
{
    protected $signature = 'ahg:doi-process-queue
        {--limit=50 : Maximum queue items to process per run}
        {--retry-failed : Re-queue failed items (resets status to pending; respects max_attempts)}
        {--dry-run : Report without writing}';

    protected $description = 'Process pending ahg_doi_queue rows (claim → call DataCite → write back).';

    public function handle(DoiService $svc): int
    {
        if ($this->option('retry-failed')) {
            $reset = (int) DB::table('ahg_doi_queue')
                ->where('status', 'failed')
                ->whereColumn('attempts', '<', 'max_attempts')
                ->update([
                    'status'       => 'pending',
                    'last_error'   => null,
                    'scheduled_at' => now(),
                ]);
            $this->info("requeued {$reset} previously-failed rows");
        }

        $limit = max(1, (int) $this->option('limit'));
        if ($this->option('dry-run')) {
            $next = $svc->nextBatch($limit);
            $this->info("would process {$next->count()} (dry-run)");
            foreach ($next as $r) {
                $this->line("  oid={$r->information_object_id} action={$r->action} priority={$r->priority} attempts={$r->attempts}");
            }
            return self::SUCCESS;
        }

        $r = $svc->processQueue($limit);
        $this->info("processed={$r['processed']} ok={$r['ok']} fail={$r['fail']}");
        return $r['fail'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
