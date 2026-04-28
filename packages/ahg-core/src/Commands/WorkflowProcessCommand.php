<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WorkflowProcessCommand extends Command
{
    protected $signature = 'ahg:workflow-process
        {--limit=100 : Max tasks to process per run}
        {--escalate : Escalate overdue tasks (status=in_progress past deadline → escalated)}
        {--dry-run : Show without writing}';

    protected $description = 'Process pending workflow tasks (claim → mark in_progress; escalate overdue)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        // Claim pending tasks whose due_at is past or null.
        $pending = DB::table('ahg_workflow_task')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('claim_at')->orWhere('claim_at', '<=', now());
            })
            ->limit($limit)
            ->get();
        $this->info("pending tasks to process: {$pending->count()}" . ($dry ? ' (dry-run)' : ''));

        $claimed = 0;
        foreach ($pending as $t) {
            if ($dry) continue;
            $updated = (int) DB::table('ahg_workflow_task')
                ->where('id', $t->id)
                ->where('status', 'pending')
                ->update(['status' => 'in_progress', 'started_at' => now()]);
            if ($updated) $claimed++;
        }
        $this->info("claimed={$claimed}");

        if ($this->option('escalate')) {
            $escalated = (int) DB::table('ahg_workflow_task')
                ->where('status', 'in_progress')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->update(['status' => 'escalated', 'escalated_at' => now()]);
            $this->info("escalated={$escalated}");
        }
        return self::SUCCESS;
    }
}
