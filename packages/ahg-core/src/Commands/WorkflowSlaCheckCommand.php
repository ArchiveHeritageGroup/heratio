<?php

/**
 * Detect workflow SLA breaches: open tasks past their per-policy deadline.
 *
 * This was written against a schema that does not exist. It joined `p.queue` to
 * `t.queue` (both tables key on `queue_id`), compared against `p.sla_hours` (the
 * policy stores `due_days`), and flagged tasks by writing `sla_breach` /
 * `sla_breach_at`, neither of which is a column on `ahg_workflow_task`. Every
 * scheduled run therefore died on "Unknown column 't.queue'" and it had never
 * once produced a result.
 *
 * It now runs against the real tables, converts the policy's days to hours for
 * the age comparison, and resolves `--queue` through `ahg_workflow_queue` so the
 * option can still take a human queue name or slug rather than an id.
 *
 * Breaches are recorded on the columns added alongside this fix; a task already
 * flagged is not re-stamped, so `sla_breach_at` keeps the moment the SLA was
 * first missed rather than the time of the most recent run.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio, licensed under the GNU AGPL v3 or later.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkflowSlaCheckCommand extends Command
{
    protected $signature = 'ahg:workflow-sla-check
        {--queue= : Restrict to one workflow queue (name or slug)}
        {--dry-run : Show breaches without writing}';

    protected $description = 'Detect workflow SLA breaches: open tasks past their per-policy deadline';

    public function handle(): int
    {
        foreach (['ahg_workflow_task', 'ahg_workflow_sla_policy'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->info("No {$t} table here - nothing to check.");

                return self::SUCCESS;
            }
        }

        $q = DB::table('ahg_workflow_task as t')
            // Both sides key on queue_id.
            ->join('ahg_workflow_sla_policy as p', 'p.queue_id', '=', 't.queue_id')
            ->leftJoin('ahg_workflow_queue as wq', 'wq.id', '=', 't.queue_id')
            ->whereIn('t.status', ['pending', 'in_progress'])
            // The policy is expressed in DAYS; the age comparison is in hours.
            ->whereRaw('TIMESTAMPDIFF(HOUR, t.created_at, NOW()) > (p.due_days * 24)')
            ->when(Schema::hasColumn('ahg_workflow_sla_policy', 'is_active'),
                fn ($w) => $w->where('p.is_active', 1))
            ->select('t.id', 't.queue_id', 't.status', 't.created_at', 'p.due_days',
                DB::raw('COALESCE(wq.name, wq.slug, CONCAT("queue #", t.queue_id)) as queue_label'));

        if ($queue = $this->option('queue')) {
            // Accept a human name or slug, not the numeric id the column holds.
            $queueId = DB::table('ahg_workflow_queue')
                ->where('name', $queue)->orWhere('slug', $queue)
                ->value('id');
            if (! $queueId) {
                $this->error("No workflow queue matching '{$queue}'.");

                return self::FAILURE;
            }
            $q->where('t.queue_id', $queueId);
        }

        $breaches = $q->limit(500)->get();
        $this->info("SLA breaches: {$breaches->count()}");

        foreach ($breaches->take(20) as $b) {
            $hours = round((time() - strtotime((string) $b->created_at)) / 3600, 1);
            $this->line(sprintf('  task=#%-5d queue=%-20s status=%-12s age=%.1fh limit=%dh',
                $b->id, $b->queue_label, $b->status, $hours, (int) $b->due_days * 24));
        }

        $canFlag = Schema::hasColumn('ahg_workflow_task', 'sla_breach');
        if (! $this->option('dry-run') && $breaches->isNotEmpty() && $canFlag) {
            $flagged = DB::table('ahg_workflow_task')
                ->whereIn('id', $breaches->pluck('id'))
                // Keep the first breach time rather than overwriting it each run.
                ->where(function ($w) {
                    $w->whereNull('sla_breach')->orWhere('sla_breach', 0);
                })
                ->update(['sla_breach' => 1, 'sla_breach_at' => now()]);
            $this->info("newly flagged {$flagged} task(s) with sla_breach=1");
        }

        return self::SUCCESS;
    }
}
