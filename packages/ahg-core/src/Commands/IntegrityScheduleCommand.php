<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrityScheduleCommand extends Command
{
    protected $signature = 'ahg:integrity-schedule
        {--run-due : Run all due schedules}
        {--list : List all schedules}
        {--status : Show schedule status}
        {--run-id= : Run specific schedule by ID}
        {--enable= : Enable schedule by ID}
        {--disable= : Disable schedule by ID}';

    protected $description = 'Manage and run integrity verification schedules';

    public function handle(): int
    {
        if (!Schema::hasTable('integrity_schedule')) {
            $this->error('Table integrity_schedule does not exist.');
            return 1;
        }

        if ($this->option('list')) {
            return $this->listSchedules();
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('enable')) {
            return $this->toggleSchedule((int) $this->option('enable'), true);
        }

        if ($this->option('disable')) {
            return $this->toggleSchedule((int) $this->option('disable'), false);
        }

        if ($this->option('run-id')) {
            return $this->runSchedule((int) $this->option('run-id'));
        }

        if ($this->option('run-due')) {
            return $this->runDueSchedules();
        }

        $this->info('Use --list, --status, --run-due, --run-id=N, --enable=N, or --disable=N.');
        return 0;
    }

    private function listSchedules(): int
    {
        $schedules = DB::table('integrity_schedule')->orderBy('name')->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules configured.');
            return 0;
        }

        $rows = [];
        foreach ($schedules as $s) {
            $rows[] = [
                $s->id,
                $s->name,
                $s->algorithm,
                $s->frequency,
                $s->cron_expression ?? '-',
                $s->batch_size,
                $s->is_enabled ? 'Yes' : 'No',
                $s->last_run_at ?? 'Never',
                $s->next_run_at ?? '-',
                $s->total_runs,
            ];
        }

        $this->table(
            ['ID', 'Name', 'Algorithm', 'Frequency', 'Cron', 'Batch', 'Enabled', 'Last Run', 'Next Run', 'Runs'],
            $rows
        );

        return 0;
    }

    private function showStatus(): int
    {
        $schedules = DB::table('integrity_schedule')->orderBy('name')->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules configured.');
            return 0;
        }

        foreach ($schedules as $s) {
            $this->info('=== ' . $s->name . ' (ID: ' . $s->id . ') ===');

            $lastRun = null;
            if (Schema::hasTable('integrity_run')) {
                $lastRun = DB::table('integrity_run')
                    ->where('schedule_id', $s->id)
                    ->orderBy('started_at', 'desc')
                    ->first();
            }

            $this->table(['Metric', 'Value'], [
                ['Enabled', $s->is_enabled ? 'Yes' : 'No'],
                ['Algorithm', $s->algorithm],
                ['Frequency', $s->frequency],
                ['Batch size', $s->batch_size],
                ['Total runs', $s->total_runs],
                ['Last run', $s->last_run_at ?? 'Never'],
                ['Next run', $s->next_run_at ?? '-'],
                ['Last status', $lastRun ? $lastRun->status : '-'],
                ['Last passed', $lastRun ? $lastRun->objects_passed : '-'],
                ['Last failed', $lastRun ? $lastRun->objects_failed : '-'],
            ]);
        }

        return 0;
    }

    private function toggleSchedule(int $id, bool $enable): int
    {
        $schedule = DB::table('integrity_schedule')->where('id', $id)->first();
        if (!$schedule) {
            $this->error('Schedule #' . $id . ' not found.');
            return 1;
        }

        DB::table('integrity_schedule')->where('id', $id)->update([
            'is_enabled' => $enable ? 1 : 0,
            'updated_at' => now(),
        ]);

        $this->info('Schedule #' . $id . ' (' . $schedule->name . ') ' . ($enable ? 'enabled' : 'disabled') . '.');
        return 0;
    }

    private function runSchedule(int $id): int
    {
        $schedule = DB::table('integrity_schedule')->where('id', $id)->first();
        if (!$schedule) {
            $this->error('Schedule #' . $id . ' not found.');
            return 1;
        }

        $this->info('Running schedule: ' . $schedule->name . ' (ID: ' . $id . ')...');

        $exitCode = Artisan::call('ahg:integrity-verify', [
            '--schedule-id' => $id,
            '--limit'       => $schedule->batch_size,
        ], $this->output);

        // Update schedule tracking
        DB::table('integrity_schedule')->where('id', $id)->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun($schedule),
            'total_runs'  => $schedule->total_runs + 1,
            'updated_at'  => now(),
        ]);

        return $exitCode;
    }

    private function runDueSchedules(): int
    {
        $dueSchedules = DB::table('integrity_schedule')
            ->where('is_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->info('No schedules are due to run.');
            return 0;
        }

        $this->info('Found ' . $dueSchedules->count() . ' due schedule(s).');

        $totalExit = 0;
        foreach ($dueSchedules as $schedule) {
            $exitCode = $this->runSchedule($schedule->id);
            if ($exitCode !== 0) {
                $totalExit = 1;
            }
        }

        return $totalExit;
    }

    /**
     * Calculate next run time from schedule frequency or cron expression.
     */
    private function calculateNextRun(object $schedule): string
    {
        $now = now();

        // Try cron expression parsing if available
        if ($schedule->cron_expression) {
            try {
                if (class_exists(\Cron\CronExpression::class)) {
                    $cron = new \Cron\CronExpression($schedule->cron_expression);
                    return $cron->getNextRunDate($now)->format('Y-m-d H:i:s');
                }
            } catch (\Throwable $e) {
                // Fall through to frequency-based calculation
            }
        }

        // Simple frequency mapping
        return match ($schedule->frequency) {
            'hourly'  => $now->addHour()->format('Y-m-d H:i:s'),
            'daily'   => $now->addDay()->format('Y-m-d H:i:s'),
            'weekly'  => $now->addWeek()->format('Y-m-d H:i:s'),
            'monthly' => $now->addMonth()->format('Y-m-d H:i:s'),
            default   => $now->addWeek()->format('Y-m-d H:i:s'),
        };
    }
}
