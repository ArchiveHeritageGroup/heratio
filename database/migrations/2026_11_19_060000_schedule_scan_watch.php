<?php

use AhgCore\Services\CronSchedulerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1749: the watched-folder poll (ahg:scan-watch --once) is now a default
 * schedule. New installs get it from the cron seed; this adds the one row to
 * existing installs. Other schedules are left alone, and an existing scan-watch
 * row (for example one an operator disabled) is not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cron_schedule') || DB::table('cron_schedule')->where('slug', 'scan-watch')->exists()) {
            return;
        }
        $svc = new CronSchedulerService;
        $row = collect($svc->getDefaultSchedules())->firstWhere('slug', 'scan-watch');
        if (! $row) {
            return;
        }
        DB::table('cron_schedule')->insert(array_merge($row, [
            'next_run_at' => $svc->computeNextRun($row['cron_expression']),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function down(): void
    {
        DB::table('cron_schedule')->where('slug', 'scan-watch')->delete();
    }
};
