<?php

/**
 * Drop the `--notifications` option from the stored workflow-process schedule.
 *
 * `ahg:workflow-process` defines --limit, --escalate and --dry-run. It has never
 * defined --notifications and has never contained any notification logic, but
 * the seeded schedule invoked it with that flag, so every run died on
 * `The "--notifications" option does not exist.` and the scheduler logged a
 * failed command.
 *
 * v1.154.602 corrected the seed in CronSchedulerService, which fixes new
 * installs. It does NOT touch a `cron_schedule` row that already exists, and
 * that row is what actually runs - so dev, heratio.org and sasa all carried on
 * failing every fifteen minutes afterwards. This repairs the stored rows.
 *
 * Matched on the exact string the seed used, so an operator who has since
 * customised this schedule keeps their version.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cron_schedule')) {
            return;
        }

        $fixed = DB::table('cron_schedule')
            ->where('artisan_command', 'ahg:workflow-process --escalate --notifications')
            ->update(['artisan_command' => 'ahg:workflow-process --escalate']);

        // The description promised email that the command does not send.
        if (Schema::hasColumn('cron_schedule', 'description')) {
            DB::table('cron_schedule')
                ->where('artisan_command', 'ahg:workflow-process --escalate')
                ->where('description', 'Process pending workflow tasks, escalate overdue items, and send email notifications.')
                ->update(['description' => 'Process pending workflow tasks and escalate overdue items.']);
        }

        if ($fixed) {
            Log::info("Removed the unsupported --notifications option from {$fixed} workflow-process schedule row(s); the command had failed on every run.");
        }
    }

    public function down(): void
    {
        // Not restored: putting the flag back only re-breaks the command.
    }
};
