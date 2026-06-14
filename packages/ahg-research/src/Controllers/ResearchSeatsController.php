<?php

/**
 * ResearchSeatsController - Controller for Heratio
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
 * ResearchSeatsController - Reading-room seat admin CRUD.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). The single endpoint is admin-gated (the 'admin' middleware
 * group, which aborts 403 for anonymous/non-admin users via RequireAdmin) and
 * operates on reading-room seats via the research_reading_room_seat table. It
 * supports create, update, delete (soft - is_active=0), release, assign and
 * bulk_create form actions. No cross-calls to other ResearchController methods
 * existed - the method used only the shared trait helper (getSidebarData) and
 * the injected ResearchService (getReadingRooms + getReadingRoom), so the move
 * is a verbatim lift.
 */
class ResearchSeatsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function seats(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms(false);
        $roomId = (int) $request->input('room_id');
        $currentRoom = $roomId ? $this->service->getReadingRoom($roomId) : null;

        if ($request->isMethod('post') && $roomId) {
            $action = $request->input('form_action');
            $redir = redirect()->route('research.seats', ['room_id' => $roomId]);

            if ($action === 'create') {
                $maxSort = DB::table('research_reading_room_seat')->where('reading_room_id', $roomId)->max('sort_order') ?? 0;
                DB::table('research_reading_room_seat')->insert([
                    'reading_room_id' => $roomId,
                    'seat_number' => $request->input('seat_number'),
                    'seat_label' => $request->input('seat_label') ?: null,
                    'seat_type' => $request->input('seat_type', 'standard'),
                    'zone' => $request->input('zone') ?: null,
                    'has_power' => $request->has('has_power') ? 1 : 0,
                    'has_lamp' => $request->has('has_lamp') ? 1 : 0,
                    'has_computer' => $request->has('has_computer') ? 1 : 0,
                    'has_magnifier' => $request->has('has_magnifier') ? 1 : 0,
                    'notes' => $request->input('notes') ?: null,
                    'is_active' => 1,
                    'sort_order' => $maxSort + 1,
                    'created_at' => now(),
                ]);
                return $redir->with('success', 'Seat added.');
            }

            if ($action === 'update') {
                DB::table('research_reading_room_seat')->where('id', (int) $request->input('seat_id'))->update([
                    'seat_number' => $request->input('seat_number'),
                    'seat_label' => $request->input('seat_label') ?: null,
                    'seat_type' => $request->input('seat_type', 'standard'),
                    'zone' => $request->input('zone') ?: null,
                    'has_power' => $request->has('has_power') ? 1 : 0,
                    'has_lamp' => $request->has('has_lamp') ? 1 : 0,
                    'has_computer' => $request->has('has_computer') ? 1 : 0,
                    'has_magnifier' => $request->has('has_magnifier') ? 1 : 0,
                    'notes' => $request->input('notes') ?: null,
                    'updated_at' => now(),
                ]);
                return $redir->with('success', 'Seat updated.');
            }

            if ($action === 'delete') {
                DB::table('research_reading_room_seat')->where('id', (int) $request->input('seat_id'))
                    ->update(['is_active' => 0, 'updated_at' => now()]);
                return $redir->with('success', 'Seat deactivated.');
            }

            if ($action === 'release') {
                DB::table('research_reading_room_seat')->where('id', (int) $request->input('seat_id'))
                    ->update(['status' => 'available', 'researcher_id' => null, 'updated_at' => now()]);
                return $redir->with('success', 'Seat released.');
            }

            if ($action === 'assign') {
                DB::table('research_reading_room_seat')->where('id', (int) $request->input('seat_id'))
                    ->update(['status' => 'occupied', 'researcher_id' => (int) $request->input('researcher_id'), 'updated_at' => now()]);
                return $redir->with('success', 'Seat assigned.');
            }

            if ($action === 'bulk_create') {
                $pattern = trim($request->input('pattern', ''));
                $seatType = $request->input('seat_type', 'standard');
                $zone = $request->input('zone') ?: null;
                $created = 0;

                if (preg_match('/^([A-Za-z]*)(\d+)-\1?(\d+)$/', $pattern, $m)) {
                    $prefix = $m[1];
                    $start = (int) $m[2];
                    $end = (int) $m[3];
                    $maxSort = DB::table('research_reading_room_seat')->where('reading_room_id', $roomId)->max('sort_order') ?? 0;
                    for ($i = $start; $i <= $end; $i++) {
                        $seatNum = $prefix . $i;
                        $exists = DB::table('research_reading_room_seat')
                            ->where('reading_room_id', $roomId)->where('seat_number', $seatNum)->exists();
                        if (!$exists) {
                            DB::table('research_reading_room_seat')->insert([
                                'reading_room_id' => $roomId, 'seat_number' => $seatNum,
                                'seat_type' => $seatType, 'zone' => $zone,
                                'has_power' => 1, 'has_lamp' => 1, 'is_active' => 1,
                                'sort_order' => ++$maxSort, 'created_at' => now(),
                            ]);
                            $created++;
                        }
                    }
                }
                return $redir->with('success', "{$created} seats created.");
            }
        }

        $seats = $roomId ? DB::table('research_reading_room_seat')->where('reading_room_id', $roomId)->orderBy('sort_order')->get()->toArray() : [];

        return view('research::research.seats', array_merge(
            $this->getSidebarData('seats'),
            compact('rooms', 'roomId', 'currentRoom', 'seats')
        ));
    }
}
