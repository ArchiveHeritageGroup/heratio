<?php

/**
 * ExhibitionEventService - heratio#1192 - live virtual openings (ticketed events).
 *
 * Slice 1: scheduling + capacity-checked RSVP/ticketing + a public event page that
 * links into the existing 3D walkthrough at event time.
 * Slice 2a: ticket-gated join + live co-presence.
 * Slice 2b (this file): PAID ticketing. A curator can price an opening; a paid event
 * creates the RSVP as status='pending' (still holding the seat under the capacity
 * lock) and only issues a usable ticket once payment is confirmed. Payment is kept
 * self-contained (no ahg-cart): markRsvpPaid() is the admin "mark as paid" action and
 * confirmPayment() is the named hook a real gateway callback would call later.
 * Real-time spatial audio / docent voice is a later slice (heratio#1150).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgExhibition\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExhibitionEventService
{
    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'live' => 'Live',
        'ended' => 'Ended',
        'cancelled' => 'Cancelled',
    ];

    /** Minutes before start the "Join the walkthrough" link goes live. */
    public const JOIN_WINDOW_BEFORE_MIN = 15;

    /**
     * RSVP / ticket states. 'confirmed' = usable ticket (free RSVP, or a paid one once
     * settled). 'pending' = seat reserved but payment outstanding on a paid event - the
     * ticket is NOT usable and cannot join. 'cancelled' = released, frees the seat.
     */
    public const RSVP_STATUSES = ['pending', 'confirmed', 'cancelled'];

    /** RSVP states that hold a seat against capacity (pending paid + confirmed). */
    public const SEAT_HOLDING_STATUSES = ['pending', 'confirmed'];

    /** Default currency applied when a curator prices an event without picking one. */
    public const DEFAULT_CURRENCY = 'USD';

    // -------- Read --------

    /**
     * True when this event charges for entry (a positive price is set). Free events
     * leave price NULL/0 and keep the slice-1 behaviour untouched.
     */
    public function isPaid(object $event): bool
    {
        return isset($event->price) && (float) $event->price > 0;
    }

    /** Events for one space, newest scheduled first, each with a confirmed-seat count. */
    public function listForSpace(int $spaceId): array
    {
        $rows = DB::table('ahg_exhibition_event')
            ->where('exhibition_space_id', $spaceId)
            ->orderByDesc('starts_at')
            ->get()
            ->all();

        foreach ($rows as $r) {
            $r->reserved = $this->reservedSeats((int) $r->id);
            $r->remaining = max(0, (int) $r->capacity - $r->reserved);
        }

        return $rows;
    }

    public function getById(int $id): ?object
    {
        return DB::table('ahg_exhibition_event')->where('id', $id)->first();
    }

    public function getByToken(string $token): ?object
    {
        return DB::table('ahg_exhibition_event')->where('public_token', $token)->first();
    }

    /**
     * Sum of seat-holding party sizes for an event. A seat is held by a 'confirmed'
     * ticket OR a 'pending' (paid-but-unsettled) ticket - both must count so a paid
     * event cannot be oversold while attendees pay. Only 'cancelled' RSVPs release the
     * seat. Free events never produce 'pending' rows, so this equals the slice-1
     * confirmed-only count for them.
     */
    public function reservedSeats(int $eventId): int
    {
        return (int) DB::table('ahg_exhibition_event_rsvp')
            ->where('event_id', $eventId)
            ->whereIn('status', self::SEAT_HOLDING_STATUSES)
            ->sum('party_size');
    }

    public function remainingSeats(object $event): int
    {
        return max(0, (int) $event->capacity - $this->reservedSeats((int) $event->id));
    }

    /**
     * The join link is live from JOIN_WINDOW_BEFORE_MIN before start until the
     * event's scheduled end. Cancelled events never go live.
     */
    public function isJoinable(object $event, ?int $nowTs = null): bool
    {
        if (($event->status ?? '') === 'cancelled') {
            return false;
        }
        $nowTs = $nowTs ?? time();
        $start = strtotime($event->starts_at);
        $end = $start + ((int) $event->duration_minutes * 60);
        $open = $start - (self::JOIN_WINDOW_BEFORE_MIN * 60);

        return $nowTs >= $open && $nowTs <= $end;
    }

    // -------- Write --------

    /**
     * Schedule a new opening on a space.
     *
     * @param  array  $data  title, starts_at, duration_minutes, capacity, host_name,
     *                       description, price, currency
     *
     * Pricing (slice 2b): a positive `price` makes this a PAID opening; a blank/zero
     * price keeps it FREE and unchanged from slice 1. `currency` is only persisted when
     * a price is set, defaulting to DEFAULT_CURRENCY.
     *
     * @throws \InvalidArgumentException on validation failure
     */
    public function schedule(int $spaceId, array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('A title is required.');
        }
        $startsAt = $this->normaliseDateTime($data['starts_at'] ?? null);
        if ($startsAt === null) {
            throw new \InvalidArgumentException('A valid start date and time is required.');
        }
        $duration = (int) ($data['duration_minutes'] ?? 60);
        if ($duration < 5 || $duration > 1440) {
            throw new \InvalidArgumentException('Duration must be between 5 and 1440 minutes.');
        }
        $capacity = (int) ($data['capacity'] ?? 50);
        if ($capacity < 1 || $capacity > 100000) {
            throw new \InvalidArgumentException('Capacity must be between 1 and 100000.');
        }

        [$price, $currency] = $this->normalisePrice($data['price'] ?? null, $data['currency'] ?? null);

        $now = now();

        return (int) DB::table('ahg_exhibition_event')->insertGetId([
            'exhibition_space_id' => $spaceId,
            'public_token' => $this->uniqueToken('ahg_exhibition_event', 'public_token'),
            'title' => $title,
            'host_name' => trim((string) ($data['host_name'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'starts_at' => $startsAt,
            'duration_minutes' => $duration,
            'capacity' => $capacity,
            'price' => $price,
            'currency' => $currency,
            'status' => 'scheduled',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Update the price + currency on an existing opening (slice 2b admin action).
     * Clearing the price (blank / 0) reverts the opening to FREE. Returns true on a
     * change. Does NOT touch already-issued RSVPs - it only governs new reservations.
     */
    public function updatePricing(int $eventId, $price, $currency): bool
    {
        [$normPrice, $normCurrency] = $this->normalisePrice($price, $currency);

        return DB::table('ahg_exhibition_event')
            ->where('id', $eventId)
            ->update([
                'price' => $normPrice,
                'currency' => $normCurrency,
                'updated_at' => now(),
            ]) > 0;
    }

    public function updateStatus(int $eventId, string $status): bool
    {
        if (! array_key_exists($status, self::STATUSES)) {
            throw new \InvalidArgumentException('Unknown status.');
        }

        return DB::table('ahg_exhibition_event')
            ->where('id', $eventId)
            ->update(['status' => $status, 'updated_at' => now()]) > 0;
    }

    public function delete(int $eventId): void
    {
        DB::table('ahg_exhibition_event_rsvp')->where('event_id', $eventId)->delete();
        DB::table('ahg_exhibition_event')->where('id', $eventId)->delete();
    }

    /**
     * RSVP to an event with a hard capacity check. Re-RSVP from the same email
     * is rejected (one ticket per email). Returns the created RSVP row.
     *
     * Paid events (price > 0): the RSVP is created as status='pending' and still holds
     * the seat under the capacity lock (reservedSeats() counts pending), but the ticket
     * is NOT usable for joining until markRsvpPaid()/confirmPayment() settles it.
     * Free events: created as status='confirmed' exactly as in slice 1.
     *
     * @throws \InvalidArgumentException validation / capacity / closed-event failures
     */
    public function rsvp(object $event, array $data): object
    {
        if (($event->status ?? '') === 'cancelled') {
            throw new \InvalidArgumentException('This event has been cancelled.');
        }
        if (($event->status ?? '') === 'ended') {
            throw new \InvalidArgumentException('This event has already ended.');
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $party = (int) ($data['party_size'] ?? 1);

        if ($name === '') {
            throw new \InvalidArgumentException('Please give your name.');
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Please give a valid email address.');
        }
        if ($party < 1 || $party > 20) {
            throw new \InvalidArgumentException('Party size must be between 1 and 20.');
        }

        // Atomic capacity guard: re-read reserved seats inside a transaction with a
        // row lock on the event so two concurrent RSVPs cannot oversell.
        return DB::transaction(function () use ($event, $name, $email, $party) {
            DB::table('ahg_exhibition_event')->where('id', $event->id)->lockForUpdate()->first();

            $existing = DB::table('ahg_exhibition_event_rsvp')
                ->where('event_id', $event->id)
                ->where('email', $email)
                ->first();
            if ($existing) {
                throw new \InvalidArgumentException('That email already has a ticket for this event.');
            }

            $reserved = $this->reservedSeats((int) $event->id);
            $remaining = (int) $event->capacity - $reserved;
            if ($party > $remaining) {
                if ($remaining <= 0) {
                    throw new \InvalidArgumentException('Sorry, this event is fully booked.');
                }
                throw new \InvalidArgumentException("Only {$remaining} seat(s) remain.");
            }

            $paid = $this->isPaid($event);
            $code = $this->uniqueToken('ahg_exhibition_event_rsvp', 'ticket_code');
            $id = DB::table('ahg_exhibition_event_rsvp')->insertGetId([
                'event_id' => $event->id,
                'ticket_code' => $code,
                'name' => $name,
                'email' => $email,
                'party_size' => $party,
                // Paid -> pending (seat held, ticket unusable until settled). Free -> confirmed.
                'status' => $paid ? 'pending' : 'confirmed',
                'amount_paid' => null,
                'paid_at' => null,
                'created_at' => now(),
            ]);

            return DB::table('ahg_exhibition_event_rsvp')->where('id', $id)->first();
        });
    }

    /**
     * Settle payment on a pending RSVP: flip it to 'confirmed', stamp the amount paid
     * and paid_at, making the ticket usable for joining. Idempotent - re-confirming an
     * already-confirmed ticket is a no-op that still records/keeps the paid amount.
     *
     * This is the low-level state transition. Both the admin "mark as paid" action and
     * the gateway-callback hook (confirmPayment) funnel through here so the rules live
     * in one place. Done under a row lock so two callbacks cannot double-process.
     *
     * @param  int|null  $rsvpId    RSVP row to settle
     * @param  float|null  $amount   amount taken; defaults to the event's full price
     * @return object|null          the updated RSVP row, or null if not found
     */
    public function markRsvpPaid(int $rsvpId, ?float $amount = null): ?object
    {
        return DB::transaction(function () use ($rsvpId, $amount) {
            $rsvp = DB::table('ahg_exhibition_event_rsvp')->where('id', $rsvpId)->lockForUpdate()->first();
            if (! $rsvp) {
                return null;
            }
            if ($rsvp->status === 'cancelled') {
                throw new \InvalidArgumentException('That ticket has been cancelled and cannot be marked paid.');
            }

            $event = DB::table('ahg_exhibition_event')->where('id', $rsvp->event_id)->first();
            if ($amount === null) {
                $amount = ($event && isset($event->price)) ? (float) $event->price : 0.0;
            }

            DB::table('ahg_exhibition_event_rsvp')
                ->where('id', $rsvpId)
                ->update([
                    'status' => 'confirmed',
                    'amount_paid' => $amount,
                    'paid_at' => $rsvp->paid_at ?? now(),
                ]);

            return DB::table('ahg_exhibition_event_rsvp')->where('id', $rsvpId)->first();
        });
    }

    /**
     * Named hook for a real payment-gateway callback to confirm a paid RSVP later.
     * A webhook handler resolves the RSVP from its own reference (e.g. ticket_code held
     * as the gateway's order metadata), verifies the charge out-of-band, then calls this
     * with the settled amount. Today it simply funnels into markRsvpPaid(); the place to
     * add signature verification / amount reconciliation is right here.
     *
     * @return object|null  the confirmed RSVP row, or null if the ticket is unknown
     */
    public function confirmPayment(int $eventId, string $ticketCode, ?float $amount = null): ?object
    {
        $ticketCode = trim($ticketCode);
        if ($ticketCode === '') {
            return null;
        }
        $rsvp = DB::table('ahg_exhibition_event_rsvp')
            ->where('event_id', $eventId)
            ->where('ticket_code', $ticketCode)
            ->first();
        if (! $rsvp) {
            return null;
        }

        return $this->markRsvpPaid((int) $rsvp->id, $amount);
    }

    /** Look up a single RSVP row by id (admin "mark as paid" resolves the row to settle). */
    public function getRsvpById(int $rsvpId): ?object
    {
        return DB::table('ahg_exhibition_event_rsvp')->where('id', $rsvpId)->first();
    }

    public function listRsvps(int $eventId): array
    {
        return DB::table('ahg_exhibition_event_rsvp')
            ->where('event_id', $eventId)
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    /**
     * Look up a confirmed RSVP / ticket row by its (event_id, ticket_code).
     * Returns null when no confirmed ticket matches (unknown / cancelled code).
     */
    public function getRsvpByTicketCode(int $eventId, string $ticketCode): ?object
    {
        $ticketCode = trim($ticketCode);
        if ($ticketCode === '') {
            return null;
        }

        return DB::table('ahg_exhibition_event_rsvp')
            ->where('event_id', $eventId)
            ->where('ticket_code', $ticketCode)
            ->where('status', 'confirmed')
            ->first();
    }

    /**
     * Gate a ticket holder into the live walkthrough. Validates two things:
     *   1. the event is inside its join window (reuses isJoinable()/JOIN_WINDOW), and
     *   2. the supplied ticket code resolves to a *confirmed* RSVP for THIS event.
     *
     * Every opening issues a ticket on RSVP (see rsvp()), and the public page only
     * offers the door once a ticket is held, so we always require a valid ticket -
     * there is no anonymous-join path. This keeps presence to verified attendees.
     *
     * PAID gating (slice 2b): getRsvpByTicketCode() resolves only status='confirmed'
     * rows, so a paid event's 'pending' (unpaid) ticket never validates here - the join
     * is rejected until markRsvpPaid()/confirmPayment() flips it to confirmed. Free
     * tickets are 'confirmed' on creation and join as before.
     *
     * @return array{ok: bool, reason: ?string, rsvp: ?object}
     */
    public function joinEvent(object $event, ?string $ticketCode): array
    {
        if (! $this->isJoinable($event)) {
            $reason = (($event->status ?? '') === 'cancelled')
                ? 'This opening has been cancelled.'
                : 'The opening is not open to join right now.';

            return ['ok' => false, 'reason' => $reason, 'rsvp' => null];
        }

        $ticketCode = trim((string) $ticketCode);
        if ($ticketCode === '') {
            return ['ok' => false, 'reason' => 'A valid ticket is required to join this opening.', 'rsvp' => null];
        }

        $rsvp = $this->getRsvpByTicketCode((int) $event->id, $ticketCode);
        if (! $rsvp) {
            return ['ok' => false, 'reason' => 'That ticket is not valid for this opening.', 'rsvp' => null];
        }

        return ['ok' => true, 'reason' => null, 'rsvp' => $rsvp];
    }

    /**
     * The event currently "live" for a space: an explicit status='live' row, or a
     * scheduled row inside its join window (JOIN_WINDOW before start -> end). When
     * several qualify, the soonest-starting one wins. Returns null when none apply.
     */
    public function eventForSpaceNow(int $exhibitionSpaceId): ?object
    {
        $candidates = DB::table('ahg_exhibition_event')
            ->where('exhibition_space_id', $exhibitionSpaceId)
            ->whereIn('status', ['scheduled', 'live'])
            ->orderBy('starts_at')
            ->get();

        foreach ($candidates as $event) {
            if (($event->status ?? '') === 'live' || $this->isJoinable($event)) {
                return $event;
            }
        }

        return null;
    }

    // -------- Helpers --------

    /**
     * Normalise a price + currency pair into [price, currency] for storage.
     *  - blank / non-numeric / <= 0  -> [null, null]  (FREE event, unchanged behaviour)
     *  - positive                    -> [round(price,2), 3-letter UPPER currency or default]
     *
     * @return array{0: float|null, 1: string|null}
     */
    private function normalisePrice($price, $currency): array
    {
        if ($price === null || $price === '' || ! is_numeric($price)) {
            return [null, null];
        }
        $price = round((float) $price, 2);
        if ($price <= 0) {
            return [null, null];
        }

        $currency = strtoupper(trim((string) $currency));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = self::DEFAULT_CURRENCY;
        }

        return [$price, $currency];
    }

    private function normaliseDateTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function uniqueToken(string $table, string $column): string
    {
        do {
            $token = Str::lower(Str::random(20));
        } while (DB::table($table)->where($column, $token)->exists());

        return $token;
    }
}
