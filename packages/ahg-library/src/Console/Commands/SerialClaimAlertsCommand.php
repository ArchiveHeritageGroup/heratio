<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * ahg:library-serial-claim-alerts (heratio#1092)
 *
 * Daily sweep: for every active serial whose latest expected issue is overdue
 * past its claim window (LibrarySerialService::listOverdueClaims), raise a claim
 * and email the subscription contact via LibrarySerialNotificationService.
 * De-duplicates against open claims so a long-running gap is not re-alerted
 * every single day.
 */

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\LibrarySerialNotificationService;
use AhgLibrary\Services\LibrarySerialService;
use Illuminate\Console\Command;

class SerialClaimAlertsCommand extends Command
{
    protected $signature = 'ahg:library-serial-claim-alerts
        {--dry-run : Identify overdue serials but do not email or record claims}';

    protected $description = 'Raise + email claims for overdue serial issues (active subscriptions only).';

    public function handle(LibrarySerialService $serials, LibrarySerialNotificationService $notifier): int
    {
        $overdue = $serials->listOverdueClaims();

        if (!$overdue) {
            $this->info('No overdue serial issues to claim.');
            return self::SUCCESS;
        }

        $raised = 0;
        $skipped = 0;
        $emailed = 0;

        foreach ($overdue as $claim) {
            $serialId = (int) $claim['serial']->id;

            // De-dup: if an open/sent claim already exists since the predicted
            // date, skip - we already alerted for this gap.
            if ($serials->hasOpenClaimSince($serialId, $claim['predicted_date'] ?? now()->toDateString())) {
                $skipped++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf('  [dry-run] would claim "%s" (%d days late)', $claim['serial']->title ?? $serialId, $claim['days_late'] ?? 0));
                $raised++;
                continue;
            }

            $recipient = $claim['serial']->notification_email ?? null;
            if (!$recipient) {
                $sub = $serials->getSubscriptionData($serialId);
                $recipient = $sub->notification_email ?? null;
            }

            $sent = $notifier->sendClaimAlert($claim, $recipient, true);
            $raised++;
            if ($sent) {
                $emailed++;
            }
        }

        $this->info(sprintf('Claims raised: %d, emailed: %d, skipped (already claimed): %d.', $raised, $emailed, $skipped));

        return self::SUCCESS;
    }
}
