<?php

/**
 * ReservationNotifier — sends emails for reservation lifecycle events to
 * the buyer (always) and the seller (when they have notifications enabled).
 *
 * Trigger points:
 *   - on reserve         (immediate; "you've reserved X for 12 hours")
 *   - 6 hours before     (cron-driven via marketplace:reservation-notify)
 *   - 1 hour before      (cron-driven)
 *   - on expiry          (cron-driven)
 *
 * Idempotency: each row in marketplace_reservation has notified_* flags.
 * Each notifier method only sends if the flag is unset, then sets it.
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

namespace AhgMarketplace\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReservationNotifier
{
    public function notifyOnReserve(int $reservationId): void
    {
        $this->dispatchIfFlag(
            $reservationId,
            'notified_on_reserve',
            'Reserved for 12 hours: %s',
            "%s\n\nYou've placed a 12-hour hold on '%s' on the Heratio Marketplace.\n\n"
            . "Hold expires: %s (in %s).\n"
            . "Listing URL: %s\n\n"
            . "Complete your purchase before the hold expires to keep this item.\n\n"
            . "If you no longer want it, you can release the reservation from the listing page.\n"
        );
    }

    public function notify6HoursBefore(int $reservationId): void
    {
        $this->dispatchIfFlag(
            $reservationId,
            'notified_6h_before',
            'Reservation reminder (6 hours left): %s',
            "%s\n\nYour 12-hour hold on '%s' expires in 6 hours.\n\n"
            . "Hold expires: %s\n"
            . "Listing URL: %s\n\n"
            . "Complete your purchase to keep this item.\n"
        );
    }

    public function notify1HourBefore(int $reservationId): void
    {
        $this->dispatchIfFlag(
            $reservationId,
            'notified_1h_before',
            'Reservation reminder (1 hour left): %s',
            "%s\n\nYour 12-hour hold on '%s' expires in 1 hour.\n\n"
            . "Hold expires: %s\n"
            . "Listing URL: %s\n\n"
            . "Complete your purchase now to keep this item, or it will be released to other buyers.\n"
        );
    }

    public function notifyOnExpiry(int $reservationId): void
    {
        $this->dispatchIfFlag(
            $reservationId,
            'notified_on_expiry',
            'Reservation released: %s',
            "%s\n\nYour 12-hour hold on '%s' has expired.\n\n"
            . "The listing is now available to other buyers.\n"
            . "Listing URL: %s\n",
            true /* expired = no expires-at line, no countdown */
        );
    }

    /**
     * Internal dispatcher. Loads context, formats message, sends to buyer
     * and (optionally) seller, then sets the flag.
     */
    private function dispatchIfFlag(int $reservationId, string $flagCol, string $subjectFmt, string $bodyFmt, bool $expired = false): void
    {
        $row = DB::table('marketplace_reservation as r')
            ->join('marketplace_listing as l', 'l.id', '=', 'r.listing_id')
            ->leftJoin('marketplace_seller as s', 's.id', '=', 'l.seller_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.id', $reservationId)
            ->select(
                'r.id', 'r.expires_at', 'r.user_id', 'r.' . $flagCol . ' as already_sent',
                'l.title', 'l.slug', 'l.seller_id',
                's.email as seller_email', 's.notify_on_reservation', 's.notify_reservation_reminders', 's.notify_on_reservation_expiry',
                'u.email as buyer_email', 'u.name as buyer_name'
            )
            ->first();

        if (!$row || $row->already_sent) {
            return;
        }

        $expires = Carbon::parse($row->expires_at);
        $listingUrl = url('/marketplace/listing?slug=' . urlencode((string) $row->slug));
        $title = (string) ($row->title ?? '#' . $row->id);

        // Build subject + body using sprintf placeholders
        $subject = sprintf($subjectFmt, $title);
        $body = $expired
            ? sprintf($bodyFmt, 'Hi ' . ($row->buyer_name ?: 'there'), $title, $listingUrl)
            : sprintf(
                $bodyFmt,
                'Hi ' . ($row->buyer_name ?: 'there'),
                $title,
                $expires->format('Y-m-d H:i') . ' UTC',
                $expires->diffForHumans(),
                $listingUrl
            );

        // Always email the buyer
        $this->safeSend($row->buyer_email, $subject, $body);

        // Email the seller if their preferences allow it
        $sellerWantsThis = match ($flagCol) {
            'notified_on_reserve' => (int) ($row->notify_on_reservation ?? 1) === 1,
            'notified_6h_before',
            'notified_1h_before' => (int) ($row->notify_reservation_reminders ?? 1) === 1,
            'notified_on_expiry' => (int) ($row->notify_on_reservation_expiry ?? 1) === 1,
            default => false,
        };
        if ($sellerWantsThis && !empty($row->seller_email)) {
            $sellerSubject = '[Seller] ' . $subject;
            $sellerBody = "A buyer reservation update on your listing:\n\n" . $body;
            $this->safeSend($row->seller_email, $sellerSubject, $sellerBody);
        }

        DB::table('marketplace_reservation')->where('id', $reservationId)->update([
            $flagCol => 1, 'updated_at' => now(),
        ]);
    }

    private function safeSend(?string $to, string $subject, string $body): void
    {
        if (empty($to)) {
            return;
        }
        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[ReservationNotifier] mail failed', ['to' => $to, 'err' => $e->getMessage()]);
        }
    }
}
