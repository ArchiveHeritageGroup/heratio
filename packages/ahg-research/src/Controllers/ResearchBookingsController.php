<?php

/**
 * ResearchBookingsController - Controller for Heratio
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



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ResearchBookingsController - Reading-room booking lifecycle.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Covers the full booking lifecycle: the admin bookings queue,
 * researcher self-service booking, the booking detail page (with inline
 * confirm/cancel/no-show), and the dedicated lifecycle endpoints
 * (confirm / check-in / check-out / no-show / cancel) plus the legacy
 * check-in/check-out aliases.
 *
 * Verbatim lift - the methods used only the shared trait helper
 * (getSidebarData) and the injected ResearchService (getReadingRooms,
 * getResearcherByUserId, createBooking, addMaterialRequest, getBooking,
 * confirmBooking, cancelBooking). No cross-calls to other ResearchController
 * methods and no exclusive private helpers existed, so the move is exact.
 */
class ResearchBookingsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    /**
     * Reading-room staff / administrator? Staff may manage every booking
     * (confirm, check-in, check-out, no-show, cancel any). Researchers may
     * only view and cancel their own.
     */
    private function isBookingStaff(): bool
    {
        return Auth::check() && \AhgCore\Services\AclService::isAdministrator(Auth::user());
    }

    /**
     * Does the current logged-in researcher own this booking?
     */
    private function ownsBooking(?object $booking): bool
    {
        if (!$booking) {
            return false;
        }
        $researcher = $this->service->getResearcherByUserId(Auth::id());

        return $researcher && (int) $booking->researcher_id === (int) $researcher->id;
    }

    public function bookings()
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms();
        $pendingBookings = DB::table('research_booking as b')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.status', 'pending')
            ->select('b.*', 'b.booking_date as date', DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"), 'r.email', 'rm.name as room_name')
            ->orderBy('b.booking_date')->get()->toArray();
        $upcomingBookings = DB::table('research_booking as b')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.status', 'confirmed')->where('b.booking_date', '>=', date('Y-m-d'))
            ->select('b.*', 'b.booking_date as date', DB::raw("CONCAT(r.first_name, ' ', r.last_name) as researcher_name"), 'rm.name as room_name')
            ->orderBy('b.booking_date')->limit(20)->get()->toArray();

        return view('research::research.bookings', array_merge(
            $this->getSidebarData('bookings'),
            compact('rooms', 'pendingBookings', 'upcomingBookings')
        ));
    }

    public function book(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher || $researcher->status !== 'approved') {
            return redirect()->route('research.dashboard')->with('error', 'Must be approved researcher');
        }

        $rooms = $this->service->getReadingRooms();
        $objectSlug = $request->input('object');
        $object = null;
        if ($objectSlug) {
            $object = DB::table('slug')
                ->join('information_object_i18n as i18n', function ($join) {
                    $join->on('slug.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                })->where('slug.slug', $objectSlug)
                ->select('slug.object_id', 'i18n.title')->first();
        }

        if ($request->isMethod('post')) {
            $bookingId = $this->service->createBooking([
                'researcher_id' => $researcher->id,
                'reading_room_id' => $request->input('room_id'),
                'booking_date' => $request->input('date'),
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'purpose' => $request->input('purpose'),
                'notes' => $request->input('notes'),
            ]);
            foreach ($request->input('materials', []) as $objectId) {
                $this->service->addMaterialRequest($bookingId, (int) $objectId);
            }
            return redirect()->route('research.viewBooking', $bookingId)
                ->with('success', 'Booking submitted');
        }

        return view('research::research.book', array_merge(
            $this->getSidebarData('book'),
            compact('researcher', 'rooms', 'objectSlug', 'object')
        ));
    }

    public function viewBooking(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');

        // IDOR guard: a researcher may only view/act on their own booking; staff may view any.
        $isStaff = $this->isBookingStaff();
        if (!$isStaff && !$this->ownsBooking($booking)) {
            abort(403, 'You do not have access to this booking.');
        }

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');
            if ($action === 'confirm') {
                if (!$isStaff) abort(403, 'Staff only.');
                $this->service->confirmBooking($id, Auth::id());
                return redirect()->route('research.viewBooking', $id)->with('success', 'Booking confirmed');
            } elseif ($action === 'cancel') {
                // Owner or staff (already enforced by the guard above).
                $this->service->cancelBooking($id, $isStaff ? 'Cancelled by staff' : 'Cancelled by researcher');
                return redirect()->route('research.viewBooking', $id)->with('success', 'Booking cancelled');
            } elseif ($action === 'noshow') {
                if (!$isStaff) abort(403, 'Staff only.');
                DB::table('research_booking')->where('id', $id)->update(['status' => 'no_show']);
                return redirect()->route('research.viewBooking', $id)->with('success', 'Marked as no-show');
            }
        }

        $materials = DB::table('research_material_request as m')
            ->leftJoin('information_object_i18n as ioi18n', function ($j) {
                $j->on('m.object_id', '=', 'ioi18n.id')->where('ioi18n.culture', '=', 'en');
            })
            ->leftJoin('slug', 'm.object_id', '=', 'slug.object_id')
            ->where('m.booking_id', $id)
            ->select('m.*', 'ioi18n.title as description', 'slug.slug')
            ->get();

        $isAdmin = Auth::check() && \AhgCore\Services\AclService::isAdministrator(Auth::user());

        return view('research::research.view-booking', array_merge(
            $this->getSidebarData('bookings'),
            compact('booking', 'materials', 'isAdmin')
        ));
    }

    public function checkIn(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        if (!$this->isBookingStaff()) abort(403, 'Staff only.');
        DB::table('research_booking')->where('id', $id)->update([
            'checked_in_at' => date('Y-m-d H:i:s'),
            'status' => 'confirmed',
        ]);
        return redirect()->route('research.viewBooking', $id)->with('success', 'Researcher checked in');
    }

    public function checkOut(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        if (!$this->isBookingStaff()) abort(403, 'Staff only.');
        DB::table('research_booking')->where('id', $id)->update([
            'checked_out_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);
        DB::table('research_material_request')
            ->where('booking_id', $id)
            ->where('status', '!=', 'returned')
            ->update(['status' => 'returned', 'returned_at' => date('Y-m-d H:i:s')]);
        return redirect()->route('research.bookings')->with('success', 'Researcher checked out');
    }

    // =========================================================================
    // DEDICATED ROUTE METHODS (Bookings)
    // =========================================================================

    public function confirmBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');
        if (!$this->isBookingStaff()) abort(403, 'Staff only.');

        $this->service->confirmBooking($id, Auth::id());

        return redirect()->route('research.viewBooking', $id)->with('success', 'Booking confirmed');
    }

    public function checkInBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');
        if (!$this->isBookingStaff()) abort(403, 'Staff only.');

        DB::table('research_booking')->where('id', $id)->update([
            'checked_in_at' => date('Y-m-d H:i:s'),
            'status' => 'confirmed',
        ]);

        return redirect()->route('research.viewBooking', $id)->with('success', 'Researcher checked in');
    }

    public function checkOutBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');
        if (!$this->isBookingStaff()) abort(403, 'Staff only.');

        DB::table('research_booking')->where('id', $id)->update([
            'checked_out_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);
        DB::table('research_material_request')
            ->where('booking_id', $id)
            ->where('status', '!=', 'returned')
            ->update(['status' => 'returned', 'returned_at' => date('Y-m-d H:i:s')]);

        return redirect()->route('research.bookings')->with('success', 'Researcher checked out');
    }

    public function noShowBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');
        if (!$this->isBookingStaff()) abort(403, 'Staff only.');

        DB::table('research_booking')->where('id', $id)->update(['status' => 'no_show']);

        return redirect()->route('research.viewBooking', $id)->with('success', 'Marked as no-show');
    }

    public function cancelBooking(int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $booking = $this->service->getBooking($id);
        if (!$booking) abort(404, 'Booking not found');
        // Owner or staff may cancel; nobody else.
        $isStaff = $this->isBookingStaff();
        if (!$isStaff && !$this->ownsBooking($booking)) {
            abort(403, 'You do not have access to this booking.');
        }

        $this->service->cancelBooking($id, $isStaff ? 'Cancelled by staff' : 'Cancelled by researcher');

        return redirect()->route('research.viewBooking', $id)->with('success', 'Booking cancelled');
    }
}
