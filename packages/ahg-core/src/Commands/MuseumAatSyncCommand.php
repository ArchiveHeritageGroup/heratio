<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MuseumAatSyncCommand extends Command
{
    protected $signature = 'ahg:museum-aat-sync
        {--category=all : AAT category code (or all)}
        {--limit=200 : Max terms to refresh in this run}
        {--clear : Truncate getty_aat_cache before sync}
        {--stats : Show getty_aat_cache stats and exit}';

    protected $description = 'Refresh Getty AAT vocabulary cache (queues stale rows for re-fetch via getty SPARQL)';

    public function handle(): int
    {
        if ($this->option('stats')) {
            $rows = DB::table('getty_aat_cache')
                ->selectRaw('category, COUNT(*) AS n, MIN(synced_at) AS oldest, MAX(synced_at) AS newest')
                ->groupBy('category')
                ->get();
            foreach ($rows as $r) $this->line(sprintf("  %-15s n=%-6d oldest=%s newest=%s", $r->category, $r->n, $r->oldest, $r->newest));
            return self::SUCCESS;
        }

        if ($this->option('clear')) {
            DB::table('getty_aat_cache')->truncate();
            $this->info('truncated getty_aat_cache');
        }

        $cat = (string) $this->option('category');
        $limit = max(1, (int) $this->option('limit'));
        // Refresh stalest rows; full SPARQL fetch is delegated to a job — this command
        // marks rows for refresh so a worker can batch-fetch from vocab.getty.edu.
        $q = DB::table('getty_aat_cache')->orderBy('synced_at');
        if ($cat !== 'all') $q->where('category', $cat);
        $stale = $q->limit($limit)->pluck('aat_id');

        if ($stale->isEmpty()) {
            $this->info('no stale AAT entries to refresh');
            return self::SUCCESS;
        }
        DB::table('getty_aat_cache')->whereIn('aat_id', $stale)->update(['synced_at' => null]);
        $this->info("queued {$stale->count()} AAT entries for refresh (synced_at=NULL); a worker will fetch from getty SPARQL.");
        return self::SUCCESS;
    }
}
