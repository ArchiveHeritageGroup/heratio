<?php

/**
 * TourController - Controller for Heratio
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

namespace AhgLoan\Controllers;

use AhgLoan\Services\TourSchedulingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Touring-exhibition / loan scheduling controller (#1190).
 *
 * Renders a per-object tour schedule and provides a "check + book" action
 * that surfaces date conflicts before committing an object to a venue.
 */
class TourController extends Controller
{
    protected TourSchedulingService $service;

    public function __construct(TourSchedulingService $service)
    {
        $this->service = $service;
    }

    /**
     * Per-object tour/loan schedule with the check-and-book form.
     */
    public function objectSchedule(int $objectId)
    {
        $object = $this->service->getObjectDescriptor($objectId);

        if ($object['title'] === null && $object['identifier'] === null) {
            abort(404);
        }

        $timeline = $this->service->getObjectSchedule($objectId);

        return view('ahg-loan::tour.object-schedule', [
            'objectId' => $objectId,
            'object' => $object,
            'timeline' => $timeline,
            'conflicts' => session('tour_conflicts', []),
            'attempt' => session('tour_attempt', []),
        ]);
    }

    /**
     * Check a window for conflicts and book it when clear.
     */
    public function checkAndBook(Request $request, int $objectId)
    {
        $validated = $request->validate([
            'venue_name' => 'required|string|max:500',
            'venue_city' => 'nullable|string|max:255',
            'venue_country' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:tentative,committed',
            'notes' => 'nullable|string|max:2000',
        ]);

        $result = $this->service->checkAndBook(
            $objectId,
            $validated['start_date'],
            $validated['end_date'],
            $validated,
            auth()->id()
        );

        if (! $result['booked']) {
            return redirect()
                ->route('loan.tour.object', $objectId)
                ->with('tour_conflicts', $result['conflicts'])
                ->with('tour_attempt', $validated)
                ->with('error', 'Cannot book: '.count($result['conflicts']).' scheduling conflict(s) found for this object and window.');
        }

        return redirect()
            ->route('loan.tour.object', $objectId)
            ->with('success', 'Venue booked - the window is clear. Booking #'.$result['booking_id'].'.');
    }

    /**
     * AJAX conflict check (no write). Returns JSON for inline preview.
     */
    public function checkJson(Request $request, int $objectId)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $conflicts = $this->service->findConflicts(
            $objectId,
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json([
            'clear' => empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Cancel a tour booking, freeing the window.
     */
    public function cancelBooking(Request $request, int $objectId, int $bookingId)
    {
        $this->service->cancelBooking($bookingId);

        return redirect()
            ->route('loan.tour.object', $objectId)
            ->with('success', 'Tour booking cancelled - the window is now free.');
    }
}
