<?php

/**
 * ExhibitionEventService - heratio#1192 - live virtual openings (ticketed events).
 *
 * FIRST SLICE: scheduling + capacity-checked RSVP/ticketing + a public event
 * page that links into the existing 3D walkthrough at event time. Real-time
 * multi-user spatial presence / docent voice is a later slice (heratio#1150).
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

    // -------- Read --------

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

    /** Sum of confirmed party sizes for an event (cancelled RSVPs do not count). */
    public function reservedSeats(int $eventId): int
    {
        return (int) DB::table('ahg_exhibition_event_rsvp')
            ->where('event_id', $eventId)
            ->where('status', 'confirmed')
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
     * @param  array  $data  title, starts_at, duration_minutes, capacity, host_name, description
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
            'status' => 'scheduled',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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

            $code = $this->uniqueToken('ahg_exhibition_event_rsvp', 'ticket_code');
            $id = DB::table('ahg_exhibition_event_rsvp')->insertGetId([
                'event_id' => $event->id,
                'ticket_code' => $code,
                'name' => $name,
                'email' => $email,
                'party_size' => $party,
                'status' => 'confirmed',
                'created_at' => now(),
            ]);

            return DB::table('ahg_exhibition_event_rsvp')->where('id', $id)->first();
        });
    }

    public function listRsvps(int $eventId): array
    {
        return DB::table('ahg_exhibition_event_rsvp')
            ->where('event_id', $eventId)
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    // -------- Helpers --------

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
