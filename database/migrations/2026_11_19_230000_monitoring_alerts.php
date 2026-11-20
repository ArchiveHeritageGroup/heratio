<?php

use AhgCore\Services\CronSchedulerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1742: the built-in monitor (ahg:monitor, every 5 minutes) and its
 * settings under Settings > Jobs. Existing rows are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cron_schedule') && ! DB::table('cron_schedule')->where('slug', 'monitor')->exists()) {
            $svc = new CronSchedulerService;
            $row = collect($svc->getDefaultSchedules())->firstWhere('slug', 'monitor');
            if ($row) {
                DB::table('cron_schedule')->insert(array_merge($row, [
                    'next_run_at' => $svc->computeNextRun($row['cron_expression']), 'created_at' => now(), 'updated_at' => now(),
                ]));
            }
        }
        if (! Schema::hasTable('ahg_settings')) {
            return;
        }
        foreach ([
            'monitoring_alert_email' => ['', 'Addresses (comma-separated) e-mailed when the website, disk space, database, search engine or job queue has a problem, when it recovers, and when a scheduled job is missed. Administrators are always told in Heratio.'],
            'monitoring_disk_min_free_percent' => ['10', 'Alert when a storage volume has less free space than this percentage.'],
            'monitoring_queue_max_wait_minutes' => ['30', 'Alert when a background job has waited longer than this many minutes.'],
            'monitoring_url' => ['', 'Address the website check calls (its /up page). Empty uses the site address.'],
        ] as $key => [$value, $desc]) {
            if (! DB::table('ahg_settings')->where('setting_key', $key)->exists()) {
                $row = ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'jobs'];
                if (Schema::hasColumn('ahg_settings', 'description')) {
                    $row['description'] = $desc;
                }
                DB::table('ahg_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        // Additive only.
    }
};
