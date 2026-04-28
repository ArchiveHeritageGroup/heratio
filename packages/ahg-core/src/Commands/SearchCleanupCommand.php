<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchCleanupCommand extends Command
{
    protected $signature = 'ahg:search-cleanup
        {--cache-ttl-hours=24 : ahg_discovery_cache rows whose expires_at is older than NOW are always removed; this option also drops rows whose expires_at is in the future but was set more than N hours ago (for stale-cache invalidation)}
        {--dry-run : Show what would be removed without deleting}';

    protected $description = 'Remove stale search entries (expired discovery cache, federation cache, getty AAT staleness)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $ttl = max(1, (int) $this->option('cache-ttl-hours'));
        $totalDeleted = 0;

        // ahg_discovery_cache — expired cache rows.
        if (Schema::hasTable('ahg_discovery_cache')) {
            $expired = DB::table('ahg_discovery_cache')->where('expires_at', '<', now());
            $count = (int) (clone $expired)->count();
            $this->info("[ahg_discovery_cache] expired_rows={$count}" . ($dry ? ' (dry-run)' : ''));
            if (! $dry && $count > 0) $totalDeleted += (int) $expired->delete();
        }

        // federation_search_cache (multi-system search) — drop rows older than the soft TTL.
        if (Schema::hasTable('federation_search_cache')) {
            $stale = DB::table('federation_search_cache')->where('created_at', '<', now()->subHours($ttl));
            $count = (int) (clone $stale)->count();
            $this->info("[federation_search_cache] stale_rows={$count}");
            if (! $dry && $count > 0) $totalDeleted += (int) $stale->delete();
        }

        // getty_aat_cache — keep entries fresh; drop ones not synced in 30 days.
        if (Schema::hasTable('getty_aat_cache') && Schema::hasColumn('getty_aat_cache', 'synced_at')) {
            $stale = DB::table('getty_aat_cache')->where('synced_at', '<', now()->subDays(30));
            $count = (int) (clone $stale)->count();
            $this->info("[getty_aat_cache] stale_rows={$count}");
            if (! $dry && $count > 0) $totalDeleted += (int) $stale->delete();
        }

        $this->info("done; total_deleted={$totalDeleted}");
        return self::SUCCESS;
    }
}
