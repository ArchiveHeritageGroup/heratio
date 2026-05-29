<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * ahg:library-serial-expiry-alerts (heratio#1092)
 *
 * Daily sweep: warn the subscription contact when a serial subscription is
 * within N days of its subscription_end. N defaults to
 * config('ahg-library.serials.expiry_warning_days', 30) and can be overridden
 * with --days.
 */

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\LibrarySerialNotificationService;
use AhgLibrary\Services\LibrarySerialService;
use Illuminate\Console\Command;

class SerialExpiryAlertsCommand extends Command
{
    protected $signature = 'ahg:library-serial-expiry-alerts
        {--days= : Days-before-expiry threshold (defaults to config / 30)}
        {--dry-run : List expiring subscriptions but do not email}';

    protected $description = 'Warn subscription contacts N days before a serial subscription_end date.';

    public function handle(LibrarySerialService $serials, LibrarySerialNotificationService $notifier): int
    {
        $days = (int) ($this->option('days')
            ?: config('ahg-library.serials.expiry_warning_days', 30));
        $days = max(1, $days);

        $expiring = $serials->listExpiringSubscriptions($days);

        if (!$expiring) {
            $this->info("No subscriptions expiring within {$days} day(s).");
            return self::SUCCESS;
        }

        $emailed = 0;
        $noRecipient = 0;

        foreach ($expiring as $row) {
            $title = $row['serial']->title ?? ('Serial #' . ($row['serial']->id ?? '?'));

            if ($this->option('dry-run')) {
                $this->line(sprintf('  [dry-run] "%s" expires %s (%d days)', $title, $row['subscription_end'], $row['days_until']));
                continue;
            }

            if ($notifier->sendExpiryAlert($row)) {
                $emailed++;
            } else {
                $noRecipient++;
                $this->warn(sprintf('  no valid recipient for "%s" (expires %s)', $title, $row['subscription_end']));
            }
        }

        $this->info(sprintf('Expiry alerts: %d candidate(s), %d emailed, %d without recipient.', count($expiring), $emailed, $noRecipient));

        return self::SUCCESS;
    }
}
