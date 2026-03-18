<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Laravel Task Scheduler — DB-Driven Cron
|--------------------------------------------------------------------------
|
| All scheduled tasks are managed via the `cron_schedule` database table.
| The single scheduler entry below runs every minute and dispatches
| any due jobs from the DB.
|
| System crontab (one entry):
|   * * * * * cd /usr/share/nginx/heratio && php artisan schedule:run >> /dev/null 2>&1
|
| Manage schedules:
|   php artisan ahg:cron-seed          # Populate default entries
|   php artisan ahg:cron-status        # CLI dashboard
|   php artisan ahg:cron-run --dry-run # Preview due jobs
|   /admin/settings/cron-jobs          # Web UI
|
*/

Schedule::command('ahg:cron-run')->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-runner.log'));
