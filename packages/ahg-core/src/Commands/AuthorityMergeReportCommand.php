<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthorityMergeReportCommand extends Command
{
    protected $signature = 'ahg:authority-merge-report
        {--days=30 : Report window in days}';

    protected $description = 'Report authority merge / split operations from the audit log';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        if (! Schema::hasTable('audit_log')) {
            $this->warn('audit_log missing — nothing to report.');
            return self::SUCCESS;
        }

        $rows = DB::table('audit_log')
            ->where('table_name', 'actor')
            ->whereIn('action', ['merge', 'split', 'redirect'])
            ->where('created_at', '>=', $cutoff)
            ->orderBy('created_at', 'desc')
            ->get(['id','record_id','action','user_id','username','action_description','created_at']);

        $this->info("=== authority merge/split since {$cutoff->toDateString()} (n={$rows->count()}) ===");
        foreach ($rows as $r) {
            $this->line(sprintf("  %s  actor=%-7s  %-8s  by=%-20s  %s",
                $r->created_at, $r->record_id, $r->action, $r->username ?: $r->user_id, $r->action_description ?? ''));
        }

        $this->info("\nsummary by action:");
        foreach ($rows->groupBy('action') as $action => $group) {
            $this->line(sprintf("  %-8s  %d", $action, $group->count()));
        }
        return self::SUCCESS;
    }
}
