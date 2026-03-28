<?php

namespace AhgExtendedRights\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EmbargoProcessCommand extends Command
{
    protected $signature = 'embargo:process
        {--dry-run : Preview changes without executing}
        {--notify-only : Only send expiry notifications}
        {--lift-only : Only lift expired embargoes}
        {--warn-days=30,7,1 : Days before expiry to warn (comma-separated)}';

    protected $description = 'Process embargo expiry: auto-lift and send notifications';

    public function handle(): int
    {
        if (! Schema::hasTable('rights_embargo')) {
            $this->error('Table rights_embargo does not exist. Aborting.');

            return 1;
        }

        $dryRun = $this->option('dry-run');
        $runAll = ! $this->option('notify-only') && ! $this->option('lift-only');
        $warnDays = array_map('intval', explode(',', $this->option('warn-days')));

        if ($dryRun) {
            $this->comment('*** DRY RUN MODE - No changes will be made ***');
        }

        $this->info('Starting embargo processing...');

        $results = [
            'lifted' => 0,
            'notifications_sent' => 0,
            'notifications_failed' => 0,
            'errors' => [],
        ];

        // 1. Lift expired embargoes
        if ($runAll || $this->option('lift-only')) {
            $liftResults = $this->liftExpiredEmbargoes($dryRun);
            $results['lifted'] = $liftResults['lifted'];
            $results['errors'] = array_merge($results['errors'], $liftResults['errors']);
        }

        // 2. Send expiry notifications
        if ($runAll || $this->option('notify-only')) {
            $notifyResults = $this->sendExpiryNotifications($warnDays, $dryRun);
            $results['notifications_sent'] = $notifyResults['sent'];
            $results['notifications_failed'] = $notifyResults['failed'];
        }

        // Summary
        $this->info('=== Processing Complete ===');
        $this->line("Embargoes lifted: {$results['lifted']}");
        $this->line("Notifications sent: {$results['notifications_sent']}");
        $this->line("Notifications failed: {$results['notifications_failed']}");

        Log::channel('single')->info('Embargo processing complete', $results);

        if (! empty($results['errors'])) {
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $err) {
                $this->error("  - {$err}");
            }
            Log::channel('single')->error('Embargo processing errors', $results['errors']);
        }

        return empty($results['errors']) ? 0 : 1;
    }

    private function liftExpiredEmbargoes(bool $dryRun): array
    {
        $this->info('Checking for expired embargoes to lift...');

        $today = date('Y-m-d');
        $culture = config('app.locale', 'en');

        $expiredEmbargoes = DB::table('rights_embargo as e')
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('e.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->where('e.status', 'active')
            ->where('e.auto_release', true)
            ->whereNotNull('e.end_date')
            ->where('e.end_date', '<', $today)
            ->select(['e.*', 'ioi.title as object_title'])
            ->get();

        $results = [
            'lifted' => 0,
            'errors' => [],
        ];

        if ($expiredEmbargoes->isEmpty()) {
            $this->line('No expired embargoes found');

            return $results;
        }

        $this->info("Found {$expiredEmbargoes->count()} expired embargoes");

        $notificationService = app(\AhgExtendedRights\Services\EmbargoNotificationService::class);

        foreach ($expiredEmbargoes as $embargo) {
            $title = $embargo->object_title ?? "Object #{$embargo->object_id}";

            if ($dryRun) {
                $this->comment("[DRY RUN] Would lift: {$title} (embargo #{$embargo->id}, ended {$embargo->end_date})");
                $results['lifted']++;

                continue;
            }

            try {
                DB::table('rights_embargo')
                    ->where('id', $embargo->id)
                    ->update([
                        'status' => 'lifted',
                        'lifted_at' => now()->toDateTimeString(),
                        'lift_reason' => 'Auto-released after expiry date',
                        'updated_at' => now()->toDateTimeString(),
                    ]);

                $results['lifted']++;
                $this->info("Lifted: {$title} (embargo #{$embargo->id})");

                Log::channel('single')->info("Embargo lifted: #{$embargo->id} - {$title}");

                // Log to audit table if it exists
                if (Schema::hasTable('embargo_audit')) {
                    DB::table('embargo_audit')->insert([
                        'embargo_id' => $embargo->id,
                        'action' => 'lifted',
                        'new_values' => json_encode(['reason' => 'Auto-released after expiry date']),
                        'performed_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ]);
                }

                try {
                    $notificationService->sendLiftedNotification($embargo, 'Auto-released after expiry date');
                } catch (\Exception $e) {
                    $this->warn("Failed to send lifted notification for embargo #{$embargo->id}: " . $e->getMessage());
                    Log::channel('single')->warning("Failed to send lifted notification for embargo #{$embargo->id}", ['error' => $e->getMessage()]);
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Failed to lift embargo #{$embargo->id}: " . $e->getMessage();
                $this->error("Error lifting embargo #{$embargo->id}: " . $e->getMessage());
                Log::channel('single')->error("Error lifting embargo #{$embargo->id}", ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    private function sendExpiryNotifications(array $warnDays, bool $dryRun): array
    {
        $this->info('Checking for embargoes expiring soon...');

        $culture = config('app.locale', 'en');

        $results = [
            'sent' => 0,
            'failed' => 0,
        ];

        $notificationService = app(\AhgExtendedRights\Services\EmbargoNotificationService::class);

        foreach ($warnDays as $days) {
            $targetDate = date('Y-m-d', strtotime("+{$days} days"));

            $expiringEmbargoes = DB::table('rights_embargo as e')
                ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                    $join->on('e.object_id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', $culture);
                })
                ->where('e.status', 'active')
                ->where('e.end_date', $targetDate)
                ->where(function ($q) use ($days) {
                    $q->whereNull('e.notify_before_days')
                        ->orWhere('e.notify_before_days', '>=', $days);
                })
                ->select(['e.*', 'ioi.title as object_title'])
                ->get();

            if ($expiringEmbargoes->isEmpty()) {
                continue;
            }

            $this->info("Found {$expiringEmbargoes->count()} embargoes expiring in {$days} days");

            foreach ($expiringEmbargoes as $embargo) {
                $title = $embargo->object_title ?? "Object #{$embargo->object_id}";

                $alreadyNotified = $this->hasRecentNotification($embargo->id, $days);
                if ($alreadyNotified) {
                    continue;
                }

                if ($dryRun) {
                    $this->comment("[DRY RUN] Would notify: {$title} ({$days} days warning)");
                    $results['sent']++;

                    continue;
                }

                try {
                    $sent = $notificationService->sendExpiryNotification($embargo, $days);
                    if ($sent) {
                        $results['sent']++;
                        $this->info("Sent {$days}-day warning: {$title}");
                        Log::channel('single')->info("Embargo expiry notification sent: #{$embargo->id} - {$days} days warning");
                    } else {
                        $results['failed']++;
                        $this->warn("Failed to send warning for: {$title} (no recipients)");
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $this->error("Error sending notification for embargo #{$embargo->id}: " . $e->getMessage());
                    Log::channel('single')->error("Error sending embargo notification #{$embargo->id}", ['error' => $e->getMessage()]);
                }
            }
        }

        if ($results['sent'] === 0 && $results['failed'] === 0) {
            $this->line('No embargoes require notification');
        }

        return $results;
    }

    private function hasRecentNotification(int $embargoId, int $days): bool
    {
        try {
            if (Schema::hasTable('embargo_notification_log')) {
                return DB::table('embargo_notification_log')
                    ->where('embargo_id', $embargoId)
                    ->where('notification_type', 'expiry_warning')
                    ->where('days_before', $days)
                    ->where('sent_at', '>=', date('Y-m-d 00:00:00'))
                    ->exists();
            }

            if (Schema::hasTable('embargo_audit')) {
                return DB::table('embargo_audit')
                    ->where('embargo_id', $embargoId)
                    ->where('action', 'notification_expiry_warning')
                    ->where('performed_at', '>=', date('Y-m-d 00:00:00'))
                    ->whereRaw("JSON_EXTRACT(details, '$.days_before') = ?", [$days])
                    ->exists();
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
