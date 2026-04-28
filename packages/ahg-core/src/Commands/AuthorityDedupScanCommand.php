<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuthorityDedupScanCommand extends Command
{
    protected $signature = 'ahg:authority-dedup-scan
        {--limit=20 : Max duplicate groups to report}
        {--connection=atom : Source DB}';

    protected $description = 'Scan for likely-duplicate authority records (grouped by normalised authorized_form_of_name)';

    public function handle(): int
    {
        $conn = (string) $this->option('connection');
        $limit = max(1, (int) $this->option('limit'));

        $rows = DB::connection($conn)->table('actor_i18n')
            ->where('culture', 'en')
            ->whereNotNull('authorized_form_of_name')
            ->selectRaw("
                LOWER(TRIM(authorized_form_of_name)) AS norm_name,
                COUNT(*) AS dup_count,
                GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS actor_ids
            ")
            ->groupBy('norm_name')
            ->having('dup_count', '>', 1)
            ->orderByDesc('dup_count')
            ->limit($limit)
            ->get();

        $this->info("=== authority dedup candidates (top {$limit} groups by name collision count) ===");
        $totalDups = 0;
        foreach ($rows as $r) {
            $this->line(sprintf("  n=%-3d  ids=[%s]  name=%s", $r->dup_count, $r->actor_ids, $r->norm_name));
            $totalDups += $r->dup_count - 1;
        }
        $this->info("\ngroups={$rows->count()} total_excess_records={$totalDups}");
        return self::SUCCESS;
    }
}
