<?php

namespace AhgCore\Commands;

use AhgDoiManage\Services\DoiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DoiDeactivateCommand extends Command
{
    protected $signature = 'ahg:doi-deactivate
        {--doi= : DOI string to tombstone}
        {--object-id= : Information object ID (resolves to its DOI)}
        {--reason=admin tombstone : Reason for deactivation}
        {--list-deleted : List all tombstoned DOIs and exit}
        {--dry-run : Report without writing}';

    protected $description = 'Tombstone (deactivate) a DOI on DataCite — keeps the identifier resolvable but flips event=hide';

    public function handle(DoiService $svc): int
    {
        if ($this->option('list-deleted')) {
            $rows = DB::table('ahg_doi')->where('status', 'tombstone')->orderByDesc('last_sync_at')->get(['doi','information_object_id','last_sync_at']);
            $this->info("tombstoned: " . $rows->count());
            foreach ($rows as $r) $this->line(sprintf("  %s  oid=%s  at=%s", $r->doi, $r->information_object_id, $r->last_sync_at));
            return self::SUCCESS;
        }

        $reason = (string) $this->option('reason');
        if ($d = $this->option('doi')) {
            if ($this->option('dry-run')) { $this->info("would tombstone {$d} ({$reason})"); return self::SUCCESS; }
            $r = $svc->deactivate((string) $d, $reason);
            $this->info($r['success'] ? "ok  {$d}" : "fail {$d} {$r['error']}");
            return $r['success'] ? self::SUCCESS : self::FAILURE;
        }
        if ($oid = $this->option('object-id')) {
            if ($this->option('dry-run')) { $this->info("would tombstone DOI for oid={$oid}"); return self::SUCCESS; }
            $r = $svc->deactivateByObject((int) $oid);
            $this->info($r['success'] ? "ok oid={$oid}" : "fail oid={$oid} {$r['error']}");
            return $r['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->error("Provide --doi or --object-id (or --list-deleted).");
        return self::FAILURE;
    }
}
