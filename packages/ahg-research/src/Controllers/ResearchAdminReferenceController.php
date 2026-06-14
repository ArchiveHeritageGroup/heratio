<?php

/**
 * ResearchAdminReferenceController - Controller for Heratio
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
 * ResearchAdminReferenceController - Admin reference/config screens.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Covers the three admin-gated reference/config endpoints:
 * researcher types (admin-types), the statistics dashboard (admin-statistics),
 * and the institutions directory (institutions). All three sit behind the
 * 'admin' middleware group. No cross-calls to other ResearchController methods
 * existed - the methods used only the shared trait helper (getSidebarData) and
 * the injected ResearchService (getResearcherTypes), so the move is a verbatim
 * lift.
 */
class ResearchAdminReferenceController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function adminTypes(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            $data = [
                'name' => $request->input('name'),
                'code' => $request->input('code') ?: \Illuminate\Support\Str::slug($request->input('name'), '_'),
                'description' => $request->input('description') ?: null,
                'max_booking_days_advance' => (int) ($request->input('max_booking_days_advance', 14)),
                'max_booking_hours_per_day' => (int) ($request->input('max_booking_hours_per_day', 4)),
                'max_materials_per_booking' => (int) ($request->input('max_materials_per_booking', 10)),
                'can_remote_access' => $request->has('can_remote_access') ? 1 : 0,
                'can_request_reproductions' => $request->has('can_request_reproductions') ? 1 : 0,
                'can_export_data' => $request->has('can_export_data') ? 1 : 0,
                'requires_id_verification' => $request->has('requires_id_verification') ? 1 : 0,
                'auto_approve' => $request->has('auto_approve') ? 1 : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'expiry_months' => (int) ($request->input('expiry_months', 12)),
                'priority_level' => (int) ($request->input('priority_level', 5)),
                'sort_order' => (int) ($request->input('sort_order', 100)),
            ];

            if ($action === 'create') {
                $data['created_at'] = now();
                DB::table('research_researcher_type')->insert($data);
                return redirect()->route('research.adminTypes')->with('success', 'Type created.');
            }

            if ($action === 'update') {
                $data['updated_at'] = now();
                DB::table('research_researcher_type')->where('id', (int) $request->input('type_id'))->update($data);
                return redirect()->route('research.adminTypes')->with('success', 'Type updated.');
            }

            if ($action === 'delete') {
                DB::table('research_researcher_type')->where('id', (int) $request->input('type_id'))->delete();
                return redirect()->route('research.adminTypes')->with('success', 'Type deleted.');
            }
        }

        $types = $this->service->getResearcherTypes();
        return view('research::research.admin-types', array_merge(
            $this->getSidebarData('adminTypes'),
            compact('types')
        ));
    }

    public function adminStatistics(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo = $request->input('date_to', date('Y-m-d'));

        $stats = [
            'total_researchers' => DB::table('research_researcher')->count(),
            'approved_researchers' => DB::table('research_researcher')->where('status', 'approved')->count(),
            'total_bookings' => DB::table('research_booking')
                ->whereBetween('booking_date', [$dateFrom, $dateTo])->count(),
            'completed_bookings' => DB::table('research_booking')
                ->where('status', 'completed')
                ->whereBetween('booking_date', [$dateFrom, $dateTo])->count(),
            'total_collections' => DB::table('research_collection')->count(),
            'total_collection_items' => DB::table('research_collection_item')->count(),
            'total_annotations' => DB::table('research_annotation')->count(),
            'total_projects' => DB::table('research_project')->count(),
            'active_projects' => DB::table('research_project')->where('status', 'active')->count(),
            'new_projects_period' => DB::table('research_project')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count(),
            'no_show_bookings' => DB::table('research_booking')
                ->where('status', 'no_show')
                ->whereBetween('booking_date', [$dateFrom, $dateTo])->count(),
            'bookings_this_week' => DB::table('research_booking')
                ->whereBetween('booking_date', [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))])->count(),
            'materials_requested' => DB::table('research_material_request')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count(),
            'materials_in_use' => DB::table('research_material_request')
                ->where('status', 'in_use')->count(),
            'total_citations' => DB::table('research_citation_log')->count(),
            'total_views' => DB::table('research_activity_log')
                ->where('activity_type', 'view')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count(),
        ];

        // By type breakdown
        try {
            $stats['by_type'] = DB::table('research_researcher as r')
                ->leftJoin('research_researcher_type as t', 'r.researcher_type_id', '=', 't.id')
                ->select(DB::raw('COALESCE(t.name, "Unspecified") as name'), DB::raw('COUNT(*) as count'))
                ->groupBy('t.name')->orderByDesc('count')->get()->toArray();
        } catch (\Exception $e) { $stats['by_type'] = []; }

        try {
            $stats['projects_by_status'] = DB::table('research_project')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')->orderByDesc('count')->get()->toArray();
        } catch (\Exception $e) { $stats['projects_by_status'] = []; }

        try {
            $stats['reproductions_by_status'] = DB::table('research_reproduction_request')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')->orderByDesc('count')->get()->toArray();
        } catch (\Exception $e) { $stats['reproductions_by_status'] = []; }

        // Chart data: registrations over time (monthly)
        $regData = [];
        try {
            $regData = DB::table('research_researcher')
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as count")
                ->where('created_at', '>=', $dateFrom)
                ->where('created_at', '<=', $dateTo . ' 23:59:59')
                ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                ->orderBy('period')->get()->toArray();
        } catch (\Exception $e) {}

        // Chart data: bookings by room
        $roomData = [];
        try {
            $roomData = DB::table('research_booking as b')
                ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
                ->whereBetween('b.booking_date', [$dateFrom, $dateTo])
                ->select('rm.name as room_name', DB::raw('COUNT(*) as count'))
                ->groupBy('rm.name')->orderByDesc('count')->get()->toArray();
        } catch (\Exception $e) {}

        // Most active researchers (cloned from PSIS adminStatistics — includes view_count + citation_count)
        $activeResearchers = [];
        try {
            $activeResearchers = DB::table('research_researcher as r')
                ->select('r.id', 'r.first_name', 'r.last_name', 'r.institution',
                    DB::raw('(SELECT COUNT(*) FROM research_booking WHERE researcher_id = r.id) as booking_count'),
                    DB::raw('(SELECT COUNT(*) FROM research_collection WHERE researcher_id = r.id) as collection_count'),
                    DB::raw('(SELECT COUNT(*) FROM research_activity_log WHERE user_id = r.user_id AND activity_type = "view") as view_count'),
                    DB::raw('(SELECT COUNT(*) FROM research_citation_log WHERE researcher_id = r.id) as citation_count'))
                ->where('r.status', 'approved')
                ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM research_booking WHERE researcher_id = r.id)'))
                ->limit(10)->get()->toArray();
        } catch (\Exception $e) {}

        // Most viewed items (cloned from PSIS adminStatistics)
        $mostViewed = [];
        try {
            $mostViewed = DB::table('research_activity_log as a')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('ioi.id', '=', 'a.object_id')
                        ->where('ioi.culture', '=', app()->getLocale());
                })
                ->where('a.activity_type', 'view')
                ->whereBetween('a.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->select('ioi.title', DB::raw('COUNT(*) as view_count'))
                ->groupBy('a.object_id', 'ioi.title')
                ->orderByDesc('view_count')
                ->limit(10)
                ->get()->toArray();
        } catch (\Exception $e) {}

        // Most cited items (cloned from PSIS adminStatistics)
        $mostCited = [];
        try {
            $mostCited = DB::table('research_citation_log as c')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('ioi.id', '=', 'c.object_id')
                        ->where('ioi.culture', '=', app()->getLocale());
                })
                ->whereBetween('c.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->select('ioi.title', DB::raw('COUNT(*) as citation_count'))
                ->groupBy('c.object_id', 'ioi.title')
                ->orderByDesc('citation_count')
                ->limit(10)
                ->get()->toArray();
        } catch (\Exception $e) {}

        return view('research::research.admin-statistics', array_merge(
            $this->getSidebarData('adminStatistics'),
            compact('stats', 'dateFrom', 'dateTo', 'regData', 'roomData', 'activeResearchers', 'mostViewed', 'mostCited')
        ));
    }

    public function institutions(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');

        if ($request->isMethod('post')) {
            $action = $request->input('form_action');
            $data = [
                'name' => $request->input('name'),
                'code' => $request->input('code') ?: \Illuminate\Support\Str::slug($request->input('name'), '_'),
                'description' => $request->input('description') ?: null,
                'url' => $request->input('url') ?: null,
                'contact_name' => $request->input('contact_name') ?: null,
                'contact_email' => $request->input('contact_email') ?: null,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ];

            if ($action === 'create') {
                $data['created_at'] = now();
                DB::table('research_institution')->insert($data);
                return redirect()->route('research.institutions')->with('success', 'Institution added.');
            }
            if ($action === 'update') {
                $data['updated_at'] = now();
                DB::table('research_institution')->where('id', (int) $request->input('institution_id'))->update($data);
                return redirect()->route('research.institutions')->with('success', 'Institution updated.');
            }
            if ($action === 'delete') {
                DB::table('research_institution')->where('id', (int) $request->input('institution_id'))->delete();
                return redirect()->route('research.institutions')->with('success', 'Institution deleted.');
            }
        }

        $institutions = DB::table('research_institution')->orderBy('name')->get()->toArray();
        return view('research::research.institutions', array_merge(
            $this->getSidebarData('institutions'),
            compact('institutions')
        ));
    }
}
