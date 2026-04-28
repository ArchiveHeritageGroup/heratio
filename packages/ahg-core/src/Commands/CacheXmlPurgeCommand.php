<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CacheXmlPurgeCommand extends Command
{
    protected $signature = 'ahg:cache-xml-purge
        {--format= : Only purge a specific XML format (ead, dc, mods, lido, marc, ric)}
        {--older-than= : Only purge entries older than N days}
        {--dry-run : Report without deleting}';

    protected $description = 'Purge cached XML exports';

    public function handle(): int
    {
        $format = $this->option('format');
        $days = $this->option('older-than');
        $dry = (bool) $this->option('dry-run');

        $totalDeleted = 0;

        // 1. metadata_export_log: completed export rows past retention.
        if (Schema::hasTable('metadata_export_log')) {
            $q = DB::table('metadata_export_log');
            if ($format) $q = $q->where('format', strtolower($format));
            if ($days)   $q = $q->where('completed_at', '<', now()->subDays((int) $days));
            $count = (int) (clone $q)->count();
            $this->info("[metadata_export_log] eligible={$count}" . ($dry ? ' (dry-run)' : ''));
            if (! $dry && $count > 0) {
                $totalDeleted += (int) $q->delete();
            }
        }

        // 2. Generic Laravel cache rows whose key indicates an XML export.
        if (Schema::hasTable('cache')) {
            $like = $format ? "xml-export-{$format}-%" : 'xml-export-%';
            $rows = DB::table('cache')->where('key', 'like', $like);
            $count = (int) (clone $rows)->count();
            $this->info("[cache xml-export-*] eligible={$count}");
            if (! $dry && $count > 0) {
                $totalDeleted += (int) $rows->delete();
            }
        }

        $this->info("done; total_deleted={$totalDeleted}");
        return self::SUCCESS;
    }
}
