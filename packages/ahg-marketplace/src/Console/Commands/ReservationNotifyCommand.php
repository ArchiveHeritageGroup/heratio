<?php

/**
 * ReservationNotifyCommand — drives the 6-hour, 1-hour and expiry email
 * reminders for marketplace reservations. Schedule via cron every ~10–15
 * minutes; idempotent because each message is gated by a flag on the row.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgMarketplace\Console\Commands;

use AhgMarketplace\Services\ReservationNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReservationNotifyCommand extends Command
{
    protected $signature = 'marketplace:reservation-notify
        {--dry-run : Print would-send rows without dispatching}';

    protected $description = 'Dispatch 6h / 1h / expiry reservation reminder emails';

    public function handle(ReservationNotifier $notifier): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        // 6-hour reminders: active rows expiring within (5h, 6h] AND not yet sent
        $six = DB::table('marketplace_reservation')
            ->where('status', 'active')
            ->where('notified_6h_before', 0)
            ->whereBetween('expires_at', [$now->copy()->addHours(5), $now->copy()->addHours(6)])
            ->pluck('id');

        // 1-hour reminders: expiring within (45min, 1h]
        $one = DB::table('marketplace_reservation')
            ->where('status', 'active')
            ->where('notified_1h_before', 0)
            ->whereBetween('expires_at', [$now->copy()->addMinutes(45), $now->copy()->addHours(1)])
            ->pluck('id');

        // Expiry: rows that already expired and weren't notified.
        // (expireOldReservations() flips active → expired; we run AFTER that.)
        DB::table('marketplace_reservation')
            ->where('status', 'active')
            ->where('expires_at', '<', $now)
            ->update(['status' => 'expired', 'updated_at' => $now]);
        DB::table('marketplace_listing')
            ->whereIn('id', function ($q) use ($now) {
                $q->select('listing_id')->from('marketplace_reservation')
                    ->where('status', 'expired')->where('expires_at', '<', $now);
            })
            ->where('status', 'reserved')
            ->update(['status' => 'active', 'reserved_by_user_id' => null, 'reserved_until' => null, 'updated_at' => $now]);

        $expired = DB::table('marketplace_reservation')
            ->where('status', 'expired')
            ->where('notified_on_expiry', 0)
            ->pluck('id');

        $this->line(sprintf(
            '6h reminders: %d, 1h reminders: %d, expiry notices: %d',
            count($six), count($one), count($expired)
        ));

        if ($dryRun) {
            return 0;
        }

        foreach ($six as $id) {
            $notifier->notify6HoursBefore((int) $id);
        }
        foreach ($one as $id) {
            $notifier->notify1HourBefore((int) $id);
        }
        foreach ($expired as $id) {
            $notifier->notifyOnExpiry((int) $id);
        }

        return 0;
    }
}
