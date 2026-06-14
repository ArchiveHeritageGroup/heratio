<?php

/**
 * ResearchEquipmentController - Controller for Heratio
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
 * ResearchEquipmentController - Admin CRUD for loanable reading-room equipment.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Both endpoints live in the admin route group (RequireAdmin
 * middleware -> 403 for anonymous requests) and manage the research_equipment
 * and research_equipment_maintenance tables scoped to a reading room.
 *
 * No cross-calls to other ResearchController methods existed - the methods used
 * only the shared trait helper (getSidebarData) and the injected ResearchService
 * (getReadingRooms + getReadingRoom), so the move is a verbatim lift.
 */
class ResearchEquipmentController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function equipment(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $rooms = $this->service->getReadingRooms(false);
        $roomId = (int) $request->input('room_id');
        $currentRoom = $roomId ? $this->service->getReadingRoom($roomId) : null;

        if ($request->isMethod('post') && $roomId) {
            $action = $request->input('form_action');
            $redir = redirect()->route('research.equipment', ['room_id' => $roomId]);

            if ($action === 'create') {
                DB::table('research_equipment')->insert([
                    'reading_room_id' => $roomId,
                    'name' => $request->input('name'),
                    'code' => $request->input('code') ?: null,
                    'equipment_type' => $request->input('equipment_type'),
                    'location' => $request->input('location') ?: null,
                    'brand' => $request->input('brand') ?: null,
                    'model' => $request->input('model') ?: null,
                    'serial_number' => $request->input('serial_number') ?: null,
                    'max_booking_hours' => (int) ($request->input('max_booking_hours', 4)),
                    'description' => $request->input('description') ?: null,
                    'requires_training' => $request->has('requires_training') ? 1 : 0,
                    'is_available' => 1,
                    'condition_status' => 'good',
                    'created_at' => now(),
                ]);
                return $redir->with('success', 'Equipment added.');
            }

            if ($action === 'update') {
                DB::table('research_equipment')->where('id', (int) $request->input('equipment_id'))->update([
                    'name' => $request->input('name'),
                    'code' => $request->input('code') ?: null,
                    'equipment_type' => $request->input('equipment_type'),
                    'location' => $request->input('location') ?: null,
                    'brand' => $request->input('brand') ?: null,
                    'model' => $request->input('model') ?: null,
                    'serial_number' => $request->input('serial_number') ?: null,
                    'max_booking_hours' => (int) ($request->input('max_booking_hours', 4)),
                    'description' => $request->input('description') ?: null,
                    'requires_training' => $request->has('requires_training') ? 1 : 0,
                    'updated_at' => now(),
                ]);
                return $redir->with('success', 'Equipment updated.');
            }

            if ($action === 'maintenance') {
                $eqId = (int) $request->input('equipment_id');
                $eq = DB::table('research_equipment')->where('id', $eqId)->first();

                // Log to history
                DB::table('research_equipment_maintenance')->insert([
                    'equipment_id' => $eqId,
                    'description' => $request->input('maintenance_description'),
                    'condition_before' => $eq->condition_status ?? null,
                    'condition_after' => $request->input('new_condition', 'good'),
                    'next_maintenance_date' => $request->input('next_maintenance_date') ?: null,
                    'performed_by' => Auth::id(),
                    'performed_at' => now(),
                ]);

                // Update equipment record
                DB::table('research_equipment')->where('id', $eqId)->update([
                    'condition_status' => $request->input('new_condition', 'good'),
                    'last_maintenance_date' => date('Y-m-d'),
                    'next_maintenance_date' => $request->input('next_maintenance_date') ?: null,
                    'updated_at' => now(),
                ]);
                return $redir->with('success', 'Maintenance logged.');
            }
        }

        $equipment = $roomId ? DB::table('research_equipment')->where('reading_room_id', $roomId)->orderBy('name')->get()->toArray() : [];

        return view('research::research.equipment', array_merge(
            $this->getSidebarData('equipment'),
            compact('rooms', 'roomId', 'currentRoom', 'equipment')
        ));
    }

    public function equipmentHistory(int $id)
    {
        $logs = DB::table('research_equipment_maintenance as m')
            ->leftJoin('user as u', 'm.performed_by', '=', 'u.id')
            ->where('m.equipment_id', $id)
            ->select('m.*', 'u.username as performed_by_name')
            ->orderByDesc('m.performed_at')
            ->limit(50)
            ->get();

        return response()->json($logs);
    }
}
