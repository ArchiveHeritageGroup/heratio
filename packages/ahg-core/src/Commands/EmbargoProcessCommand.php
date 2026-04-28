<?php

namespace AhgCore\Commands;

use AhgExtendedRights\Services\EmbargoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EmbargoProcessCommand extends Command
{
    protected $signature = 'ahg:embargo-process
        {--dry-run : Show what would be processed without making changes}
        {--notify-only : Only send notifications, do not lift embargoes}
        {--lift-only : Only lift expired embargoes, do not send notifications}
        {--system-user-id=1 : User id to credit lifts to in audit log}';

    protected $description = 'Process and lift expired embargoes; send pre-expiry notifications';

    public function handle(EmbargoService $svc): int
    {
        $dry = (bool) $this->option('dry-run');
        $notifyOnly = (bool) $this->option('notify-only');
        $liftOnly = (bool) $this->option('lift-only');

        $expired = DB::table('embargo')
            ->where('status', 'active')
            ->where('is_perpetual', 0)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString())
            ->get(['id','object_id','end_date']);
        $this->info("expired active embargoes: {$expired->count()}");

        $lifted = 0;
        if (! $notifyOnly) {
            foreach ($expired as $e) {
                if ($dry) { $this->line("  would lift embargo id={$e->id} object={$e->object_id} (end_date={$e->end_date})"); $lifted++; continue; }
                if ($svc->liftEmbargo((int) $e->id, (int) $this->option('system-user-id'), 'auto-lifted at expiry')) $lifted++;
            }
        }

        $notified = 0;
        if (! $liftOnly) {
            $expiring = $svc->getExpiringEmbargoes(30);
            $this->info("embargoes expiring in 30 days: {$expiring->count()}");
            foreach ($expiring as $e) {
                if (! ($e->notify_on_expiry ?? 1)) continue;
                if ($dry) { $this->line("  would notify embargo id={$e->id}"); $notified++; continue; }
                // Notification is delegated to EmbargoNotificationService via the service layer's
                // event hooks; we just record the trigger here.
                DB::table('embargo_notification_log')->insert([
                    'embargo_id'  => $e->id,
                    'notified_at' => now(),
                    'days_before' => $e->notify_days_before ?? 30,
                ]);
                $notified++;
            }
        }

        $this->info("done; lifted={$lifted} notified={$notified}" . ($dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
