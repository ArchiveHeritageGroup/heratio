<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreservationMigrateCommand extends Command
{
    protected $signature = 'ahg:preservation-migrate
        {--plan-id= : Specific preservation_migration_plan id (default: all active plans)}
        {--limit=50 : Max objects to migrate per plan per run}
        {--dry-run : Simulate without converting}
        {--preserve-original : Keep original files after migration (default true)}';

    protected $description = 'Execute scheduled format migrations from preservation_migration_plan';

    public function handle(): int
    {
        $plans = DB::table('preservation_migration_plan')->where('status', 'active');
        if ($id = $this->option('plan-id')) $plans->where('id', (int) $id);
        $plans = $plans->get();

        if ($plans->isEmpty()) {
            $this->info('no active migration plans');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        $totalMigrated = 0; $totalFail = 0;
        foreach ($plans as $plan) {
            $this->info("--- plan id={$plan->id} from={$plan->source_pronom_id} to={$plan->target_pronom_id} ---");

            $objects = DB::table('preservation_migration_plan_object')
                ->where('migration_plan_id', $plan->id)
                ->where('status', 'pending')
                ->limit($limit)
                ->get(['id', 'digital_object_id']);
            $this->info("  pending objects: {$objects->count()}" . ($dry ? ' (dry-run)' : ''));

            foreach ($objects as $obj) {
                if ($dry) { $totalMigrated++; continue; }
                // Mark as in_progress; the actual conversion is delegated to the per-format
                // tool registry (PreservationService::getConversionTools). Recording the queue
                // intent here makes the workflow auditable; the worker that executes the tool
                // will flip the status to completed/failed.
                DB::table('preservation_migration_plan_object')
                    ->where('id', $obj->id)
                    ->update(['status' => 'in_progress', 'started_at' => now()]);
                DB::table('preservation_event')->insert([
                    'digital_object_id' => $obj->digital_object_id,
                    'event_type'        => 'migration_queued',
                    'detail'            => json_encode(['plan_id' => $plan->id, 'plan_object_id' => $obj->id]),
                    'outcome'           => 'pending',
                    'occurred_at'       => now(),
                ]);
                $totalMigrated++;
            }
        }
        $this->info("done; queued={$totalMigrated} fail={$totalFail}");
        return self::SUCCESS;
    }
}
