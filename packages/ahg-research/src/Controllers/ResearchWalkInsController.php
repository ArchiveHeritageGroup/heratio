<?php

/**
 * ResearchWalkInsController - Controller for Heratio
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
 * ResearchWalkInsController - Reading-room walk-in visitor register.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). The single endpoint is admin-gated (the route lives in the
 * research-prefixed admin middleware group) and registers / checks out
 * walk-in visitors against the research_walk_in_visitor table. No cross-calls
 * to other ResearchController methods existed - the method used only the shared
 * trait helper (getSidebarData) and the injected ResearchService
 * (getReadingRooms + getReadingRoom), so the move is a verbatim lift.
 */
class ResearchWalkInsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function walkIn(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms();
        $roomId = (int) $request->input('room_id');
        $currentRoom = $roomId ? $this->service->getReadingRoom($roomId) : null;
        $currentWalkIns = $roomId
            ? DB::table('research_walk_in_visitor')
                ->where('reading_room_id', $roomId)
                ->where('visit_date', date('Y-m-d'))
                ->whereNull('check_out_time')
                ->orderBy('check_in_time')
                ->get()->toArray()
            : [];

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            if ($action === 'register') {
                DB::table('research_walk_in_visitor')->insert([
                    'reading_room_id' => $roomId,
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'email' => $request->input('email'),
                    'phone' => $request->input('phone'),
                    'id_type' => $request->input('id_type'),
                    'id_number' => $request->input('id_number'),
                    'organization' => $request->input('organization'),
                    'purpose' => $request->input('purpose'),
                    'research_topic' => $request->input('research_topic'),
                    'rules_acknowledged' => $request->input('rules_acknowledged') ? 1 : 0,
                    'visit_date' => date('Y-m-d'),
                    'check_in_time' => date('H:i:s'),
                    'checked_in_by' => Auth::id(),
                ]);
                return redirect()->route('research.walkIn', ['room_id' => $roomId])
                    ->with('success', 'Walk-in visitor registered');
            }
            if ($action === 'checkout') {
                DB::table('research_walk_in_visitor')
                    ->where('id', (int) $request->input('visitor_id'))
                    ->update([
                        'check_out_time' => date('H:i:s'),
                        'checked_out_by' => Auth::id(),
                    ]);
                return redirect()->route('research.walkIn', ['room_id' => $roomId])
                    ->with('success', 'Visitor checked out');
            }
        }

        return view('research::research.walk-in', array_merge(
            $this->getSidebarData('walkIn'),
            compact('rooms', 'roomId', 'currentRoom', 'currentWalkIns')
        ));
    }
}
