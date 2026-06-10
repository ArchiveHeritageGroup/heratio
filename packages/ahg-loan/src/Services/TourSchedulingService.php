<?php

/**
 * TourSchedulingService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLoan\Services;

use Illuminate\Support\Facades\DB;

/**
 * Touring-exhibition / loan scheduling with date-conflict detection (#1190).
 *
 * A single object cannot be committed to two overlapping engagements. This
 * service detects overlaps across three sources before persisting a venue
 * booking:
 *   1. Other tour bookings for the same object (ahg_loan_tour_booking).
 *   2. Committed outgoing loans carrying the same object
 *      (ahg_loan + ahg_loan_object).
 *   3. On-display windows in the digital-twin exhibition
 *      (ahg_exhibition_placement), when the table exists.
 *
 * Date windows are treated as inclusive on both ends; two windows conflict
 * when start_a <= end_b AND start_b <= end_a.
 */
class TourSchedulingService
{
    /**
     * Loan statuses that represent a real commitment of the object (i.e. it is
     * spoken for and unavailable for an overlapping tour stop). Draft /
     * rejected / cancelled / closed / returned loans do not block.
     */
    public const BLOCKING_LOAN_STATUSES = [
        'submitted', 'under_review', 'approved', 'preparing',
        'dispatched', 'in_transit', 'received', 'on_loan', 'return_requested',
    ];

    /**
     * Tour-booking statuses that block an overlapping window. Cancelled
     * bookings are ignored.
     */
    public const BLOCKING_TOUR_STATUSES = ['tentative', 'committed'];

    /**
     * Detect scheduling conflicts for an object over a requested date window.
     *
     * Returns a flat list of conflict descriptors. An empty list means the
     * window is clear and the booking may be committed.
     *
     * @param  int|null  $excludeBookingId  ignore this tour booking (for re-checks/edits)
     * @return array<int, array{source: string, ref: string, label: string, start: string, end: string, status: ?string}>
     */
    public function findConflicts(int $objectId, string $startDate, string $endDate, ?int $excludeBookingId = null): array
    {
        $conflicts = [];

        // 1) Overlapping tour bookings for the same object.
        $bookingQuery = DB::table('ahg_loan_tour_booking')
            ->where('information_object_id', $objectId)
            ->whereIn('status', self::BLOCKING_TOUR_STATUSES)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($excludeBookingId !== null) {
            $bookingQuery->where('id', '!=', $excludeBookingId);
        }

        foreach ($bookingQuery->get() as $b) {
            $conflicts[] = [
                'source' => 'tour',
                'ref' => 'booking #'.$b->id,
                'label' => $b->venue_name,
                'start' => (string) $b->start_date,
                'end' => (string) $b->end_date,
                'status' => $b->status,
            ];
        }

        // 2) Overlapping committed outgoing loans carrying the same object.
        $loanRows = DB::table('ahg_loan_object as lo')
            ->join('ahg_loan as l', 'lo.loan_id', '=', 'l.id')
            ->where('lo.information_object_id', $objectId)
            ->whereIn('l.status', self::BLOCKING_LOAN_STATUSES)
            ->whereNotNull('l.start_date')
            ->whereNotNull('l.end_date')
            ->where('l.start_date', '<=', $endDate)
            ->where('l.end_date', '>=', $startDate)
            ->select('l.id', 'l.loan_number', 'l.partner_institution', 'l.start_date', 'l.end_date', 'l.status')
            ->get();

        foreach ($loanRows as $l) {
            $conflicts[] = [
                'source' => 'loan',
                'ref' => $l->loan_number ?? ('loan #'.$l->id),
                'label' => $l->partner_institution ?? '',
                'start' => (string) $l->start_date,
                'end' => (string) $l->end_date,
                'status' => $l->status,
            ];
        }

        // 3) Overlapping on-display windows in the digital-twin exhibition.
        if (\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_placement')) {
            $placementRows = DB::table('ahg_exhibition_placement as p')
                ->leftJoin('ahg_exhibition_space as s', 'p.exhibition_space_id', '=', 's.id')
                ->where('p.information_object_id', $objectId)
                ->whereNotNull('p.starts_at')
                ->whereNotNull('p.ends_at')
                ->where('p.starts_at', '<=', $endDate)
                ->where('p.ends_at', '>=', $startDate)
                ->select('p.id', 's.name as space_name', 'p.starts_at', 'p.ends_at')
                ->get();

            foreach ($placementRows as $p) {
                $conflicts[] = [
                    'source' => 'exhibition',
                    'ref' => 'placement #'.$p->id,
                    'label' => $p->space_name ?? 'On display',
                    'start' => (string) $p->starts_at,
                    'end' => (string) $p->ends_at,
                    'status' => 'on_display',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Check the window and, when clear, persist the tour booking.
     *
     * Returns ['booked' => bool, 'booking_id' => ?int, 'conflicts' => array].
     * When conflicts are found nothing is written and booked === false.
     *
     * @param  array{venue_name: string, venue_city?: ?string, venue_country?: ?string, status?: ?string, loan_id?: ?int, notes?: ?string}  $data
     */
    public function checkAndBook(int $objectId, string $startDate, string $endDate, array $data, ?int $userId = null): array
    {
        $conflicts = $this->findConflicts($objectId, $startDate, $endDate);

        if (! empty($conflicts)) {
            return ['booked' => false, 'booking_id' => null, 'conflicts' => $conflicts];
        }

        $object = $this->getObjectDescriptor($objectId);

        $status = $data['status'] ?? 'committed';
        if (! in_array($status, self::BLOCKING_TOUR_STATUSES, true)) {
            $status = 'committed';
        }

        $bookingId = DB::table('ahg_loan_tour_booking')->insertGetId([
            'information_object_id' => $objectId,
            'object_title' => $object['title'],
            'object_identifier' => $object['identifier'],
            'loan_id' => $data['loan_id'] ?? null,
            'venue_name' => $data['venue_name'],
            'venue_city' => $data['venue_city'] ?? null,
            'venue_country' => $data['venue_country'] ?? null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['booked' => true, 'booking_id' => $bookingId, 'conflicts' => []];
    }

    /**
     * Cancel a tour booking (soft - keeps the row for audit, frees the window).
     */
    public function cancelBooking(int $bookingId): bool
    {
        return DB::table('ahg_loan_tour_booking')
            ->where('id', $bookingId)
            ->update(['status' => 'cancelled', 'updated_at' => now()]) > 0;
    }

    /**
     * Full per-object schedule: tour stops, committed loans, and on-display
     * windows, merged into one chronologically ordered timeline.
     *
     * @return array<int, array{source: string, ref: string, label: string, start: string, end: string, status: ?string, booking_id: ?int}>
     */
    public function getObjectSchedule(int $objectId): array
    {
        $timeline = [];

        foreach (DB::table('ahg_loan_tour_booking')
            ->where('information_object_id', $objectId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_date')
            ->get() as $b) {
            $timeline[] = [
                'source' => 'tour',
                'ref' => 'Tour stop',
                'label' => $b->venue_name,
                'start' => (string) $b->start_date,
                'end' => (string) $b->end_date,
                'status' => $b->status,
                'booking_id' => (int) $b->id,
            ];
        }

        foreach (DB::table('ahg_loan_object as lo')
            ->join('ahg_loan as l', 'lo.loan_id', '=', 'l.id')
            ->where('lo.information_object_id', $objectId)
            ->whereIn('l.status', self::BLOCKING_LOAN_STATUSES)
            ->whereNotNull('l.start_date')
            ->whereNotNull('l.end_date')
            ->select('l.id', 'l.loan_number', 'l.partner_institution', 'l.start_date', 'l.end_date', 'l.status')
            ->get() as $l) {
            $timeline[] = [
                'source' => 'loan',
                'ref' => $l->loan_number ?? ('Loan #'.$l->id),
                'label' => $l->partner_institution ?? '',
                'start' => (string) $l->start_date,
                'end' => (string) $l->end_date,
                'status' => $l->status,
                'booking_id' => null,
            ];
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_placement')) {
            foreach (DB::table('ahg_exhibition_placement as p')
                ->leftJoin('ahg_exhibition_space as s', 'p.exhibition_space_id', '=', 's.id')
                ->where('p.information_object_id', $objectId)
                ->whereNotNull('p.starts_at')
                ->whereNotNull('p.ends_at')
                ->select('p.id', 's.name as space_name', 'p.starts_at', 'p.ends_at')
                ->get() as $p) {
                $timeline[] = [
                    'source' => 'exhibition',
                    'ref' => 'On display',
                    'label' => $p->space_name ?? '',
                    'start' => (string) $p->starts_at,
                    'end' => (string) $p->ends_at,
                    'status' => 'on_display',
                    'booking_id' => null,
                ];
            }
        }

        usort($timeline, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return $timeline;
    }

    /**
     * Resolve a cached title/identifier for an object (local or remote).
     *
     * @return array{title: ?string, identifier: ?string}
     */
    public function getObjectDescriptor(int $objectId): array
    {
        $title = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', 'en')
            ->value('title');

        $identifier = DB::table('information_object')
            ->where('id', $objectId)
            ->value('identifier');

        return ['title' => $title, 'identifier' => $identifier];
    }
}
