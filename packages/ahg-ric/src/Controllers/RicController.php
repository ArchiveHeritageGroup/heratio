<?php

namespace AhgRic\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RicController extends Controller
{
    /**
     * Check whether all required RiC tables exist.
     */
    private function tablesExist(): bool
    {
        return Schema::hasTable('ric_sync_status')
            && Schema::hasTable('ric_sync_queue')
            && Schema::hasTable('ric_orphan_tracking')
            && Schema::hasTable('ric_sync_log');
    }

    /**
     * RiC Dashboard — index page.
     */
    public function index()
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        // Fuseki settings from ahg_settings
        $fusekiSettings = [];
        if (Schema::hasTable('ahg_settings')) {
            $fusekiSettings = DB::table('ahg_settings')
                ->where('setting_group', 'fuseki')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        }

        // Sync summary: counts by sync_status
        $syncSummary = DB::table('ric_sync_status')
            ->selectRaw("sync_status, COUNT(*) as cnt")
            ->groupBy('sync_status')
            ->pluck('cnt', 'sync_status')
            ->toArray();

        // Queue status: counts by status
        $queueStatus = DB::table('ric_sync_queue')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // Orphan count (detected only)
        $orphanCount = DB::table('ric_orphan_tracking')
            ->where('status', 'detected')
            ->count();

        // Recent 10 operations
        $recentOps = DB::table('ric_sync_log')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // 7-day sync trend
        $sevenDaysAgo = Carbon::now()->subDays(7)->startOfDay();
        $syncTrend = DB::table('ric_sync_log')
            ->selectRaw("DATE(created_at) as log_date, COUNT(*) as cnt")
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupByRaw("DATE(created_at)")
            ->orderBy('log_date')
            ->get();

        // Entity sync breakdown
        $entitySync = DB::table('ric_sync_status')
            ->selectRaw("entity_type,
                SUM(CASE WHEN sync_status = 'synced' THEN 1 ELSE 0 END) as synced,
                SUM(CASE WHEN sync_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN sync_status IN ('failed','error') THEN 1 ELSE 0 END) as failed")
            ->groupBy('entity_type')
            ->orderBy('entity_type')
            ->get();

        return view('ahg-ric::index', compact(
            'fusekiSettings',
            'syncSummary',
            'queueStatus',
            'orphanCount',
            'recentOps',
            'syncTrend',
            'entitySync'
        ));
    }

    /**
     * Sync Status — paginated list with filters.
     */
    public function syncStatus(Request $request)
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        $entityType = $request->input('entity_type', '');
        $status = $request->input('status', '');

        $query = DB::table('ric_sync_status')->orderByDesc('updated_at');

        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }
        if ($status !== '') {
            $query->where('sync_status', $status);
        }

        $items = $query->paginate(25)->withQueryString();

        // Get distinct entity types for filter dropdown
        $entityTypes = DB::table('ric_sync_status')
            ->distinct()
            ->pluck('entity_type')
            ->sort()
            ->values();

        $statuses = ['synced', 'pending', 'failed', 'error'];

        return view('ahg-ric::sync-status', compact('items', 'entityTypes', 'statuses', 'entityType', 'status'));
    }

    /**
     * Orphans — list with status tabs.
     */
    public function orphans(Request $request)
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        $tab = $request->input('status', 'all');

        $query = DB::table('ric_orphan_tracking')->orderByDesc('detected_at');

        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $items = $query->paginate(25)->withQueryString();

        // Badge counts per status
        $counts = DB::table('ric_orphan_tracking')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();
        $counts['all'] = array_sum($counts);

        return view('ahg-ric::orphans', compact('items', 'counts', 'tab'));
    }

    /**
     * Queue — list with status tabs.
     */
    public function queue(Request $request)
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        $tab = $request->input('status', 'all');

        $query = DB::table('ric_sync_queue')->orderByDesc('created_at');

        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $items = $query->paginate(25)->withQueryString();

        // Badge counts per status
        $counts = DB::table('ric_sync_queue')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();
        $counts['all'] = array_sum($counts);

        return view('ahg-ric::queue', compact('items', 'counts', 'tab'));
    }

    /**
     * Logs — list with filters.
     */
    public function logs(Request $request)
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        $operation = $request->input('operation', '');
        $status = $request->input('status', '');
        $entityType = $request->input('entity_type', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        $query = DB::table('ric_sync_log')->orderByDesc('created_at');

        if ($operation !== '') {
            $query->where('operation', $operation);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $items = $query->paginate(25)->withQueryString();

        // Distinct values for filter dropdowns
        $operations = DB::table('ric_sync_log')->distinct()->pluck('operation')->sort()->values();
        $statuses = DB::table('ric_sync_log')->distinct()->pluck('status')->sort()->values();
        $entityTypes = DB::table('ric_sync_log')->distinct()->pluck('entity_type')->sort()->values();

        return view('ahg-ric::logs', compact(
            'items', 'operations', 'statuses', 'entityTypes',
            'operation', 'status', 'entityType', 'dateFrom', 'dateTo'
        ));
    }
}
