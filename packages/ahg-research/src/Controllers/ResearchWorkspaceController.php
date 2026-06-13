<?php

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResearchWorkspaceController extends Controller
{
    protected ResearchService $service;

    public function __construct()
    {
        $this->service = new ResearchService();
    }

    protected function getSidebarData(string $active): array
    {
        $unreadNotifications = 0;
        if (Auth::check()) {
            $researcher = $this->service->getResearcherByUserId(Auth::id());
            if ($researcher) {
                try {
                    $unreadNotifications = (int) DB::table('research_notification')
                        ->where('researcher_id', $researcher->id)
                        ->where('is_read', 0)
                        ->count();
                } catch (\Exception $e) {
                    // Table may not exist yet
                }
            }
        }

        $experienceLevel = 'intermediate';
        if (Auth::check()) {
            $r = $this->service->getResearcherByUserId(Auth::id());
            if ($r && !empty($r->experience_level)) {
                $experienceLevel = $r->experience_level;
            }
        }

        return [
            'sidebarActive' => $active,
            'unreadNotifications' => $unreadNotifications,
            'experienceLevel' => $experienceLevel,
        ];
    }

    public function workspace(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return redirect()->route('researcher.register');

        $collections = $this->service->getCollections($researcher->id);
        $savedSearches = $this->service->getSavedSearches($researcher->id);
        $annotations = $this->service->getAnnotations($researcher->id);

        $upcomingBookings = DB::table('research_booking as b')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.researcher_id', $researcher->id)
            ->where('b.booking_date', '>=', date('Y-m-d'))
            ->whereIn('b.status', ['pending', 'confirmed'])
            ->select('b.*', 'rm.name as room_name')
            ->orderBy('b.booking_date')->orderBy('b.start_time')
            ->limit(5)->get()->toArray();

        $pastBookings = DB::table('research_booking as b')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.researcher_id', $researcher->id)
            ->where(function ($q) {
                $q->where('b.booking_date', '<', date('Y-m-d'))
                  ->orWhere('b.status', 'completed');
            })
            ->select('b.*', 'rm.name as room_name')
            ->orderBy('b.booking_date', 'desc')->limit(5)->get()->toArray();

        $stats = [
            'total_bookings' => DB::table('research_booking')->where('researcher_id', $researcher->id)->count(),
            'total_collections' => count($collections),
            'total_saved_searches' => count($savedSearches),
            'total_annotations' => count($annotations),
            'total_items' => DB::table('research_collection_item as ci')
                ->join('research_collection as c', 'ci.collection_id', '=', 'c.id')
                ->where('c.researcher_id', $researcher->id)->count(),
        ];

        if ($request->isMethod('post')) {
            $action = $request->input('booking_action');
            if ($action === 'create_collection') {
                $name = trim($request->input('collection_name'));
                if ($name) {
                    $this->service->createCollection($researcher->id, [
                        'name' => $name,
                        'description' => trim($request->input('collection_description')),
                        'is_public' => $request->input('is_public') ? 1 : 0,
                    ]);
                    return redirect()->route('research.workspace')->with('success', 'Collection created successfully.');
                }
            }
        }

        $canUseFeatures = $researcher->status === 'approved'
            && (!($researcher->expires_at ?? null) || strtotime($researcher->expires_at) >= time());

        $weeklyActivity = [];
        try {
            $weeklyActivity = DB::table('research_access_decision')
                ->where('researcher_id', $researcher->id)
                ->where('evaluated_at', '>=', date('Y-m-d', strtotime('-7 days')))
                ->selectRaw('DATE(evaluated_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(evaluated_at)')
                ->orderBy('date')
                ->get()->toArray();
        } catch (\Exception $e) {}

        return view('research::research.workspace', array_merge(
            $this->getSidebarData('workspace'),
            compact('researcher', 'collections', 'savedSearches', 'annotations', 'upcomingBookings', 'pastBookings', 'stats', 'canUseFeatures', 'weeklyActivity')
        ));
    }

    public function workspaces(Request $request)
    {
        if (!Auth::check()) return redirect()->route('login');
        $filter = $request->input('filter', 'all');
        $query = $request->input('q');

        $statusFilter = ($filter !== 'all') ? $filter : null;
        $researchers = $this->service->getResearchers(['status' => $statusFilter, 'search' => $query]);

        $counts = [
            'all' => DB::table('research_researcher')->count(),
            'pending' => DB::table('research_researcher')->where('status', 'pending')->count(),
            'approved' => DB::table('research_researcher')->where('status', 'approved')->count(),
            'suspended' => DB::table('research_researcher')->where('status', 'suspended')->count(),
            'expired' => DB::table('research_researcher')->where('status', 'expired')->count(),
            'rejected' => DB::table('research_researcher')->where('status', 'rejected')->count(),
        ];

        return view('research::research.workspaces', array_merge(
            $this->getSidebarData('workspace'),
            compact('researchers', 'filter', 'counts', 'query')
        ));
    }

    public function viewWorkspace(Request $request, int $id)
    {
        if (!Auth::check()) return redirect()->route('login');
        $workspace = $this->service->getWorkspace($id);
        if (!$workspace) abort(404, 'Not found');

        return view('research::research.view-workspace', array_merge(
            $this->getSidebarData('workspace'),
            compact('workspace')
        ));
    }

    public function saveExperienceLevel(Request $request)
    {
        if (!Auth::check()) return response()->json(['error' => 'unauthenticated'], 401);
        $level = $request->input('level');
        if (!in_array($level, ['beginning', 'intermediate', 'advanced'], true)) {
            return response()->json(['error' => 'invalid_level'], 400);
        }

        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) return response()->json(['error' => 'no_researcher'], 404);

        $this->service->updateResearcher($researcher->id, ['experience_level' => $level]);

        return response()->json(['ok' => true, 'level' => $level]);
    }
}
