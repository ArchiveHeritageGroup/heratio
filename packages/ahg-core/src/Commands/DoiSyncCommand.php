<?php

namespace AhgCore\Commands;

use AhgDoiManage\Services\DoiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DoiSyncCommand extends Command
{
    protected $signature = 'ahg:doi-sync
        {--all : Sync all DOIs}
        {--id= : Sync specific DOI ID (DOI string, e.g. 10.12345/PNTE/2026/123)}
        {--status= : Filter by status (e.g. findable, draft, registered)}
        {--limit=100 : Maximum DOIs to sync}
        {--queue : Queue for background processing instead of syncing inline}
        {--dry-run : Report without writing}';

    protected $description = 'Push local metadata changes to DataCite for existing DOIs';

    public function handle(DoiService $svc): int
    {
        $q = DB::table('ahg_doi');
        if ($this->option('id')) $q->where('doi', (string) $this->option('id'));
        if ($this->option('status')) $q->where('status', (string) $this->option('status'));
        if (! $this->option('all') && ! $this->option('id')) {
            $q->where('last_sync_at', '<', now()->subHours(24));
        }
        $limit = max(1, (int) $this->option('limit'));
        $rows = $q->orderBy('last_sync_at')->limit($limit)->get(['doi', 'information_object_id']);

        $this->info("syncing " . $rows->count() . " DOIs" . ($this->option('dry-run') ? ' (dry-run)' : ''));
        if ($this->option('queue')) {
            foreach ($rows as $r) $svc->enqueue((int) $r->information_object_id, 'update');
            $this->info("enqueued " . $rows->count() . " update jobs");
            return self::SUCCESS;
        }

        $ok = 0; $fail = 0;
        foreach ($rows as $r) {
            if ($this->option('dry-run')) { $this->line("  would sync {$r->doi}"); $ok++; continue; }
            $res = $svc->update($r->doi);
            if ($res['success']) $ok++;
            else { $fail++; $this->line("  fail {$r->doi}: {$res['error']}"); }
        }
        $this->info("done; ok={$ok} fail={$fail}");
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
