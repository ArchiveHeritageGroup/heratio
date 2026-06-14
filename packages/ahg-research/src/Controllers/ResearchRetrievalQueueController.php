<?php

/**
 * ResearchRetrievalQueueController - Controller for Heratio
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
 * ResearchRetrievalQueueController - Reading-room material retrieval/paging queue.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). The single endpoint (retrievalQueue) is admin-gated
 * (middleware('admin') -> RequireAdmin in routes/web.php) and manages the
 * research_material_request paging workflow: per-request status transitions
 * (in_transit/delivered/returned) and batch status updates, plus the queue
 * listing joined across research_booking, research_researcher and the
 * information_object_i18n title. No cross-calls to other ResearchController
 * methods existed - the method uses only the shared trait helper
 * (getSidebarData) and the injected ResearchService (getReadingRooms), so the
 * move is a verbatim lift.
 */
class ResearchRetrievalQueueController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function retrievalQueue(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if (in_array($action, ['mark_in_transit', 'mark_delivered', 'mark_returned'])) {
                $newStatus = match($action) { 'mark_in_transit' => 'in_transit', 'mark_delivered' => 'delivered', 'mark_returned' => 'returned' };
                DB::table('research_material_request')
                    ->where('id', (int) $request->input('request_id'))
                    ->update(['status' => $newStatus, 'updated_at' => now()]);
                return redirect()->route('research.retrievalQueue', ['status' => $request->input('current_status')])->with('success', 'Status updated.');
            }

            if ($action === 'batch_update' && $request->input('new_status')) {
                $ids = $request->input('request_ids', []);
                if (!empty($ids)) {
                    DB::table('research_material_request')
                        ->whereIn('id', array_map('intval', $ids))
                        ->update(['status' => $request->input('new_status'), 'updated_at' => now()]);
                    return redirect()->route('research.retrievalQueue')->with('success', count($ids) . ' request(s) updated.');
                }
            }
        }

        $rooms = $this->service->getReadingRooms();
        $requests = DB::table('research_material_request as m')
            ->join('research_booking as b', 'm.booking_id', '=', 'b.id')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('m.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->select('m.*', 'b.booking_date', 'b.start_time', 'b.end_time', 'r.first_name', 'r.last_name', 'i18n.title as object_title')
            ->orderBy('b.booking_date')
            ->get()->toArray();

        return view('research::research.retrieval-queue', array_merge(
            $this->getSidebarData('retrievalQueue'),
            compact('rooms', 'requests')
        ));
    }
}
