# Touring-exhibition / loan scheduling with conflict detection (ahg-loan, #1190)

First slice of the digital-twin cross-institution loan / touring-exhibition
planning feature (#1190). Lets a curator schedule an object's movement to a
venue for a date range and detects scheduling conflicts before committing.

## What it does

An object cannot be committed to two overlapping engagements. When a curator
tries to book an object into a venue for a date window, the scheduler checks
that window against three sources and only persists the booking when it is
clear:

1. **Other tour bookings** for the same object (`ahg_loan_tour_booking`,
   statuses `tentative` / `committed`).
2. **Committed outgoing loans** carrying the same object (`ahg_loan` joined to
   `ahg_loan_object`), for loan statuses that represent a real commitment
   (`submitted` through `return_requested`; draft / rejected / cancelled /
   closed / returned do not block).
3. **On-display windows** in the digital-twin exhibition
   (`ahg_exhibition_placement.starts_at` / `ends_at`), guarded by
   `Schema::hasTable` so it is optional.

Date windows are inclusive on both ends; two windows conflict when
`start_a <= end_b AND start_b <= end_a`.

## Components (package `packages/ahg-loan`)

- `database/install_tour.sql` - `ahg_loan_tour_booking` table
  (object ref + cached title/identifier, optional `loan_id`, free-text
  `venue_name` / city / country, `start_date` / `end_date`, `status`,
  `notes`). `external_object_id` reserved for federated/remote objects.
- `src/Services/TourSchedulingService.php`:
  - `findConflicts($objectId, $start, $end, $excludeBookingId = null)` -
    returns a flat conflict list across the three sources.
  - `checkAndBook($objectId, $start, $end, $data, $userId)` - checks, and on
    a clear window inserts the booking; returns
    `['booked' => bool, 'booking_id' => ?int, 'conflicts' => array]`.
  - `cancelBooking($bookingId)` - soft-cancel (keeps the row, frees the window).
  - `getObjectSchedule($objectId)` - merged chronological timeline of tour
    stops + loans + on-display windows.
- `src/Controllers/TourController.php` - `objectSchedule`, `checkAndBook`,
  `checkJson` (AJAX no-write preview), `cancelBooking`.
- `resources/views/tour/object-schedule.blade.php` - per-object schedule table
  plus a "check + book" form that re-surfaces conflicts on a blocked attempt.
- Routes (auth group, multi-segment to dodge the `/{slug}` IO catch-all):
  - `GET  /loan/tour/object/{objectId}` - `loan.tour.object`
  - `POST /loan/tour/object/{objectId}/book` - `loan.tour.book` (acl:create)
  - `POST /loan/tour/object/{objectId}/check` - `loan.tour.check` (JSON)
  - `POST /loan/tour/object/{objectId}/booking/{bookingId}/cancel` -
    `loan.tour.cancel` (acl:update)

## Install

The `ahg_loan_tour_booking` table is auto-created on first boot by
`AhgLoanServiceProvider::ensureTourTable()` - a `Schema::hasTable` guard that
runs `install_tour.sql` via `DB::unprepared` in a single try/catch (same
convention as `ahg-io-manage`).

## Notes

- Jurisdiction-neutral: venue is free text; no country-specific assumptions.
- Builds toward the federation track (#1155): `external_object_id` is already
  present so a federated/remote object can later be booked as a tour
  placeholder.
