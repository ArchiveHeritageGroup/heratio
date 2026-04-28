<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WorkflowStatusCommand extends Command
{
    protected $signature = 'ahg:workflow-status
        {--queues : Show queue statistics}
        {--format=table : Output (table or json)}';

    protected $description = 'Workflow status — counts by status, breaches, queue depth';

    public function handle(): int
    {
        $byStatus = DB::table('ahg_workflow_task')->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n','status')->toArray();
        $byQueue = DB::table('ahg_workflow_task')->selectRaw('queue, COUNT(*) AS n')->groupBy('queue')->pluck('n','queue')->toArray();
        $breaches = (int) DB::table('ahg_workflow_task')->where('sla_breach', 1)->count();

        if ($this->option('format') === 'json') {
            $this->line(json_encode(['by_status' => $byStatus, 'by_queue' => $byQueue, 'sla_breaches' => $breaches], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('=== workflow tasks by status ===');
        foreach ($byStatus as $s => $n) $this->line(sprintf("  %-15s %d", $s, $n));

        $this->info("\nSLA breaches:    {$breaches}");

        if ($this->option('queues')) {
            $this->info("\n=== by queue ===");
            foreach ($byQueue as $q => $n) $this->line(sprintf("  %-25s %d", $q, $n));
        }
        return self::SUCCESS;
    }
}
