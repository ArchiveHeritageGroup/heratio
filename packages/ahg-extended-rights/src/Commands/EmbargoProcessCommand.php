<?php

namespace AhgExtendedRights\Commands;

use AhgExtendedRights\Services\EmbargoService;
use AhgExtendedRights\Services\EmbargoNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmbargoProcessCommand extends Command
{
    protected $signature = 'embargo:process
        {--dry-run : Preview changes without applying}
        {--notify-only : Only send notifications, do not lift}
        {--lift-only : Only lift expired, do not send warnings}
        {--warn-days=30,7,1 : Comma-separated warning intervals in days}';

    protected $description = 'Process expired embargoes and send expiry notifications';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $notifyOnly = $this->option('notify-only');
        $liftOnly = $this->option('lift-only');
        $warnDays = array_map('intval', explode(',', $this->option('warn-days')));

        if ($dryRun) {
            $this->info('[DRY RUN] No changes will be applied.');
        }

        if (!Schema::hasTable('rights_embargo')) {
            $this->error('Table rights_embargo does not exist.');
            return 1;
        }

        $lifted = 0;
        $warned = 0;

        // 1. Lift expired embargoes
        if (!$notifyOnly) {
            $expired = DB::table('rights_embargo')
                ->where('status', 'active')
                ->where('end_date', '<', now()->toDateString())
                ->whereNotNull('end_date')
                ->get();

            foreach ($expired as $embargo) {
                if ($dryRun) {
                    $this->line("  Would lift embargo #{$embargo->id} (object {$embargo->object_id}, expired {$embargo->end_date})");
                } else {
                    DB::table('rights_embargo')
                        ->where('id', $embargo->id)
                        ->update([
                            'status' => 'lifted',
                            'lifted_at' => now(),
                            'lift_reason' => 'Auto-lifted: embargo period expired',
                        ]);

                    if (Schema::hasTable('embargo_audit')) {
                        DB::table('embargo_audit')->insert([
                            'embargo_id' => $embargo->id,
                            'action'     => 'lifted',
                            'new_values' => json_encode(['reason' => 'Auto-lifted by embargo:process']),
                            'created_at' => now(),
                        ]);
                    }
                }
                $lifted++;
            }

            $this->info("Lifted: {$lifted} expired embargoes" . ($dryRun ? ' (dry run)' : ''));
        }

        // 2. Send expiry warnings
        if (!$liftOnly) {
            foreach ($warnDays as $days) {
                $targetDate = now()->addDays($days)->toDateString();

                $expiring = DB::table('rights_embargo')
                    ->where('status', 'active')
                    ->where('end_date', $targetDate)
                    ->whereNotNull('end_date')
                    ->get();

                foreach ($expiring as $embargo) {
                    if ($dryRun) {
                        $this->line("  Would warn: embargo #{$embargo->id} expires in {$days} days ({$embargo->end_date})");
                    } else {
                        try {
                            $notificationService = app(EmbargoNotificationService::class);
                            $notificationService->sendExpiryNotification($embargo, $days);
                        } catch (\Throwable $e) {
                            $this->warn("  Failed to notify for embargo #{$embargo->id}: {$e->getMessage()}");
                        }
                    }
                    $warned++;
                }
            }

            $this->info("Warnings sent: {$warned}" . ($dryRun ? ' (dry run)' : ''));
        }

        $this->info('Done.');

        return 0;
    }
}
