<?php

namespace AhgCore\Commands;

use AhgPreservation\Services\PreservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PreservationSchedulerCommand extends Command
{
    protected $signature = 'ahg:preservation-scheduler
        {--dry-run : Simulate without executing}
        {--force : Run all schedules now regardless of last_run_at}';

    protected $description = 'Run scheduled preservation workflows from the policy table';

    public function handle(PreservationService $svc): int
    {
        $schedules = $svc->getSchedules();
        if ($schedules->isEmpty()) {
            $this->info('no preservation schedules configured');
            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $ran = 0; $skipped = 0;
        foreach ($schedules as $s) {
            $due = $force || empty($s->next_run_at) || strtotime((string) $s->next_run_at) <= time();
            if (! $due) { $skipped++; continue; }

            $action = $s->action ?? '';
            $opts = json_decode((string) ($s->options_json ?? '{}'), true) ?: [];
            $cmd = match ($action) {
                'fixity'        => 'ahg:preservation-fixity',
                'identify'      => 'ahg:preservation-identify',
                'virus_scan'    => 'ahg:preservation-virus-scan',
                'replicate'     => 'ahg:preservation-replicate',
                'migrate'       => 'ahg:preservation-migrate',
                default         => null,
            };
            if (! $cmd) { $skipped++; $this->warn("  schedule id={$s->id} unknown action='{$action}' — skipping"); continue; }

            $this->info(($dry ? 'WOULD run: ' : 'running: ') . "schedule={$s->id} → {$cmd}");
            if (! $dry) {
                try {
                    Artisan::call($cmd, $opts);
                    $ran++;
                } catch (\Throwable $e) {
                    $this->error("  failed schedule={$s->id}: {$e->getMessage()}");
                }
            } else {
                $ran++;
            }
        }
        $this->info("done; ran={$ran} skipped={$skipped}");
        return self::SUCCESS;
    }
}
