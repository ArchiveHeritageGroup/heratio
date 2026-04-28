<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WorkflowSlaCheckCommand extends Command
{
    protected $signature = 'ahg:workflow-sla-check
        {--queue= : Restrict to a workflow queue name}
        {--dry-run : Show breaches without writing}';

    protected $description = 'Detect workflow SLA breaches: open tasks past their per-policy deadline';

    public function handle(): int
    {
        $now = now();
        $q = DB::table('ahg_workflow_task as t')
            ->join('ahg_workflow_sla_policy as p', 'p.queue', '=', 't.queue')
            ->whereIn('t.status', ['pending', 'in_progress'])
            ->whereRaw('TIMESTAMPDIFF(HOUR, t.created_at, NOW()) > p.sla_hours')
            ->select('t.id', 't.queue', 't.status', 't.created_at', 'p.sla_hours');
        if ($queue = $this->option('queue')) $q->where('t.queue', $queue);
        $breaches = $q->limit(500)->get();
        $this->info("SLA breaches: {$breaches->count()}");

        foreach ($breaches->take(20) as $b) {
            $hours = round((time() - strtotime((string) $b->created_at)) / 3600, 1);
            $this->line(sprintf("  task=#%-5d queue=%-20s status=%-12s age=%.1fh limit=%dh", $b->id, $b->queue, $b->status, $hours, $b->sla_hours));
        }

        if (! $this->option('dry-run') && $breaches->isNotEmpty()) {
            DB::table('ahg_workflow_task')
                ->whereIn('id', $breaches->pluck('id'))
                ->update(['sla_breach' => 1, 'sla_breach_at' => $now]);
            $this->info("flagged {$breaches->count()} tasks with sla_breach=1");
        }
        return self::SUCCESS;
    }
}
