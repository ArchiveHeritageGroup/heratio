<?php

namespace AhgCore\Commands;

use AhgDoiManage\Services\DoiService;
use Illuminate\Console\Command;

class DoiReportCommand extends Command
{
    protected $signature = 'ahg:doi-report
        {--type=summary : Report type (summary, errors, recent)}
        {--format=table : Output format (table or json)}';

    protected $description = 'DOI status report — counts by status, queue depth, recent log';

    public function handle(DoiService $svc): int
    {
        $r = $svc->reportSummary();

        if ($this->option('format') === 'json') {
            $this->line(json_encode($r, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $type = $this->option('type');

        $this->info("=== DOI summary ===");
        $this->line("  total DOIs:    {$r['total']}");
        foreach (($r['by_status'] ?? []) as $st => $n) $this->line(sprintf("    %-12s %d", $st, $n));

        $this->info("=== queue ===");
        foreach (($r['queue'] ?? []) as $st => $n) $this->line(sprintf("  %-12s %d", $st, $n));

        if ($type === 'errors') {
            $this->info("=== recent log entries with errors ===");
            foreach ($r['last_log'] as $row) {
                if (! empty($row->details) && str_contains((string) $row->details, 'error')) {
                    $this->line(sprintf("  %s  oid=%s  %s  %s", $row->performed_at, $row->information_object_id, $row->action, $row->details));
                }
            }
        } elseif ($type === 'recent') {
            $this->info("=== recent log (last 20) ===");
            foreach ($r['last_log'] as $row) {
                $this->line(sprintf("  %s  oid=%-7s  %-10s  %s → %s", $row->performed_at, $row->information_object_id ?: '-', $row->action, $row->status_before ?: '-', $row->status_after ?: '-'));
            }
        }

        return self::SUCCESS;
    }
}
