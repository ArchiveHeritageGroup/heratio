<?php

/**
 * ResearchRoomsController - Controller for Heratio
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
 * ResearchRoomsController - Admin reading-room CRUD.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Both endpoints sit in the admin route group (RequireAdmin
 * middleware, which aborts 403 for non-admins / anonymous requests) and operate
 * on the research_reading_room table. No cross-calls to other ResearchController
 * methods existed - the methods used only the shared trait helper
 * (getSidebarData) and the injected ResearchService (getReadingRooms +
 * getReadingRoom), so the move is a verbatim lift.
 */
class ResearchRoomsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function rooms()
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms(false);
        return view('research::research.rooms', array_merge(
            $this->getSidebarData('rooms'),
            compact('rooms')
        ));
    }

    public function editRoom(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $id = (int) $request->input('id');
        $room = $id ? $this->service->getReadingRoom($id) : null;
        $isNew = !$room;

        if ($request->isMethod('post')) {
            $data = [
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'location' => $request->input('location'),
                'capacity' => (int) $request->input('capacity', 10),
                'description' => $request->input('description'),
                'amenities' => $request->input('amenities'),
                'rules' => $request->input('rules'),
                'opening_time' => $request->input('opening_time', '09:00:00'),
                'closing_time' => $request->input('closing_time', '17:00:00'),
                'days_open' => $request->input('days_open', 'Mon,Tue,Wed,Thu,Fri'),
                'is_active' => $request->input('is_active') ? 1 : 0,
                'advance_booking_days' => (int) $request->input('advance_booking_days', 14),
                'max_booking_hours' => (int) $request->input('max_booking_hours', 4),
                'cancellation_hours' => (int) $request->input('cancellation_hours', 24),
            ];
            if ($id && $room) {
                DB::table('research_reading_room')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                DB::table('research_reading_room')->insert($data);
            }
            return redirect()->route('research.rooms')->with('success', $isNew ? 'Reading room created' : 'Reading room updated');
        }

        return view('research::research.edit-room', array_merge(
            $this->getSidebarData('rooms'),
            compact('room', 'isNew')
        ));
    }
}
