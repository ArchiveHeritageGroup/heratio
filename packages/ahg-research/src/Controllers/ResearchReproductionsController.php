<?php

/**
 * ResearchReproductionsController - Controller for Heratio
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
 * ResearchReproductionsController - Reproduction requests for the research portal.
 *
 * Extracted from ResearchController as stage 1 of the monolith decomposition
 * (issue #1253). Handles researcher-facing reproduction request creation,
 * listing, item management and lifecycle (submit / cancel).
 */
class ResearchReproductionsController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function reproductions(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        if ($request->isMethod('post') && $request->input('form_action') === 'create') {
            $refNum = 'RPR-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $reqId = DB::table('research_reproduction_request')->insertGetId([
                'researcher_id' => $researcher->id,
                'reference_number' => $refNum,
                'purpose' => $request->input('purpose'),
                'intended_use' => $request->input('urgency', 'normal'),
                'publication_details' => $request->input('publication_details') ?: null,
                'delivery_method' => $request->input('delivery_method', 'email'),
                'notes' => $request->input('notes') ?: null,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Add first item if provided
            $objectId = (int) $request->input('object_id');
            if ($objectId) {
                DB::table('research_reproduction_item')->insert([
                    'request_id' => $reqId,
                    'object_id' => $objectId,
                    'reproduction_type' => $request->input('reproduction_type', 'scan'),
                    'format' => $request->input('format', 'PDF'),
                    'special_instructions' => $request->input('specifications') ?: null,
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
            }

            return redirect()->route('research.viewReproduction', $reqId)->with('success', 'Request created.' . ($objectId ? ' Item added.' : ' Add items to continue.'));
        }

        $query = DB::table('research_reproduction_request as r')
            ->where('r.researcher_id', $researcher->id);
        if ($request->input('status')) $query->where('r.status', $request->input('status'));

        $requests = $query
            ->select('r.*', DB::raw('(SELECT COUNT(*) FROM research_reproduction_item WHERE request_id = r.id) as item_count'))
            ->orderBy('r.created_at', 'desc')
            ->get()->toArray();

        return view('research::research.reproductions', array_merge(
            $this->getSidebarData('reproductions'),
            compact('researcher', 'requests')
        ));
    }

    /**
     * View a single reproduction request with its items.
     */
    public function viewReproduction(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $reproRequest = DB::table('research_reproduction_request')
            ->where('id', $id)->where('researcher_id', $researcher->id)->first();
        if (!$reproRequest) abort(404);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');

            if ($action === 'add_item') {
                $objectId = (int) $request->input('object_id');
                if ($objectId) {
                    DB::table('research_reproduction_item')->insert([
                        'request_id' => $id,
                        'object_id' => $objectId,
                        'reproduction_type' => $request->input('reproduction_type', 'scan'),
                        'format' => $request->input('format', 'PDF'),
                        'quantity' => (int) ($request->input('quantity', 1)) ?: 1,
                        'special_instructions' => $request->input('special_instructions') ?: null,
                        'status' => 'pending',
                        'created_at' => now(),
                    ]);
                    return redirect()->route('research.viewReproduction', $id)->with('success', 'Item added.');
                }
            }

            if ($action === 'remove_item') {
                DB::table('research_reproduction_item')
                    ->where('id', (int) $request->input('item_id'))
                    ->where('request_id', $id)->delete();
                return redirect()->route('research.viewReproduction', $id)->with('success', 'Item removed.');
            }

            if ($action === 'submit') {
                DB::table('research_reproduction_request')->where('id', $id)
                    ->update(['status' => 'submitted', 'updated_at' => now()]);
                return redirect()->route('research.viewReproduction', $id)->with('success', 'Request submitted for processing.');
            }

            if ($action === 'cancel') {
                DB::table('research_reproduction_request')->where('id', $id)
                    ->update(['status' => 'cancelled', 'closed_at' => now(), 'updated_at' => now()]);
                return redirect()->route('research.reproductions')->with('success', 'Request cancelled.');
            }
        }

        $items = DB::table('research_reproduction_item as ri')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('ri.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'ri.object_id', '=', 's.object_id')
            ->where('ri.request_id', $id)
            ->select('ri.*', 'i18n.title as object_title', 's.slug as object_slug')
            ->orderBy('ri.created_at')
            ->get()->toArray();

        return view('research::research.view-reproduction', array_merge(
            $this->getSidebarData('reproductions'),
            compact('researcher', 'reproRequest', 'items')
        ));
    }
}
