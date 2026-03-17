<?php

namespace AhgHeritageManage\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HeritageController extends Controller
{
    /**
     * Heritage Admin Dashboard.
     */
    public function adminDashboard()
    {
        // User stats
        $totalUsers = 0;
        $activeUsers = 0;
        $newThisMonth = 0;

        if (Schema::hasTable('user')) {
            $totalUsers = DB::table('user')->count();
        }

        // Active users: users who have audit log entries in the last 30 days
        if (Schema::hasTable('ahg_audit_log')) {
            $activeUsers = DB::table('ahg_audit_log')
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');
        }

        // New users this month: users created this month via audit log (action = 'create', entity_type like user)
        if (Schema::hasTable('ahg_audit_log')) {
            $newThisMonth = DB::table('ahg_audit_log')
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('action', 'create')
                ->where('entity_type', 'LIKE', '%user%')
                ->count();
        }

        // Alert counts
        $activeAlerts = 0;
        if (Schema::hasTable('ahg_audit_log')) {
            $activeAlerts = DB::table('ahg_audit_log')
                ->where('status', '!=', 'success')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
        }

        return view('ahg-heritage-manage::admin-dashboard', compact(
            'totalUsers',
            'activeUsers',
            'newThisMonth',
            'activeAlerts'
        ));
    }

    /**
     * Heritage Analytics Dashboard.
     */
    public function analyticsDashboard(Request $request)
    {
        $days = (int) $request->input('days', 30);
        if (!in_array($days, [7, 30, 90])) {
            $days = 30;
        }

        $since = now()->subDays($days);

        $pageViews = 0;
        $searches = 0;
        $downloads = 0;
        $uniqueVisitors = 0;
        $avgResults = 0;
        $zeroResultRate = 0;
        $clickThroughRate = 0;
        $pendingRequests = 0;
        $approvalRate = 0;
        $popiaFlags = 0;

        if (Schema::hasTable('ahg_audit_log')) {
            // Page views: all entries with action 'view' or 'browse'
            $pageViews = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->whereIn('action', ['view', 'browse', 'index'])
                ->count();

            // Searches
            $searches = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->where('action', 'search')
                ->count();

            // Downloads
            $downloads = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->where('action', 'download')
                ->count();

            // Unique visitors by distinct user_id or ip_address
            $uniqueByUser = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');

            $uniqueByIp = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->whereNull('user_id')
                ->whereNotNull('ip_address')
                ->distinct('ip_address')
                ->count('ip_address');

            $uniqueVisitors = $uniqueByUser + $uniqueByIp;

            // Search performance from metadata JSON
            if ($searches > 0) {
                $searchLogs = DB::table('ahg_audit_log')
                    ->where('created_at', '>=', $since)
                    ->where('action', 'search')
                    ->whereNotNull('metadata')
                    ->select('metadata')
                    ->limit(1000)
                    ->get();

                $totalResults = 0;
                $zeroResults = 0;
                $clickedResults = 0;

                foreach ($searchLogs as $log) {
                    $meta = json_decode($log->metadata, true);
                    if (is_array($meta)) {
                        $resultCount = $meta['result_count'] ?? $meta['results'] ?? null;
                        if ($resultCount !== null) {
                            $totalResults += (int) $resultCount;
                            if ((int) $resultCount === 0) {
                                $zeroResults++;
                            }
                        }
                        if (!empty($meta['clicked'])) {
                            $clickedResults++;
                        }
                    }
                }

                $searchCount = $searchLogs->count();
                if ($searchCount > 0) {
                    $avgResults = round($totalResults / $searchCount, 1);
                    $zeroResultRate = round(($zeroResults / $searchCount) * 100, 1);
                    $clickThroughRate = round(($clickedResults / $searchCount) * 100, 1);
                }
            }

            // Access control stats
            $pendingRequests = DB::table('ahg_audit_log')
                ->where('action', 'access_request')
                ->where('status', 'pending')
                ->count();

            $totalAccessRequests = DB::table('ahg_audit_log')
                ->where('action', 'access_request')
                ->where('created_at', '>=', $since)
                ->count();

            $approvedRequests = DB::table('ahg_audit_log')
                ->where('action', 'access_request')
                ->where('status', 'success')
                ->where('created_at', '>=', $since)
                ->count();

            $approvalRate = $totalAccessRequests > 0
                ? round(($approvedRequests / $totalAccessRequests) * 100, 1)
                : 0;

            $popiaFlags = DB::table('ahg_audit_log')
                ->where('created_at', '>=', $since)
                ->where('security_classification', 'popia')
                ->count();
        }

        return view('ahg-heritage-manage::analytics-dashboard', compact(
            'days',
            'pageViews',
            'searches',
            'downloads',
            'uniqueVisitors',
            'avgResults',
            'zeroResultRate',
            'clickThroughRate',
            'pendingRequests',
            'approvalRate',
            'popiaFlags'
        ));
    }

    /**
     * Heritage Custodian Dashboard.
     */
    public function custodianDashboard()
    {
        $runningJobs = 0;
        $completedToday = 0;
        $itemsThisMonth = 0;

        if (Schema::hasTable('job')) {
            // Running jobs: status_id for running (typically status_id that is not completed)
            // AtoM job status: 1=running, 2=error, 3=completed
            $runningJobs = DB::table('job')
                ->where('status_id', 1)
                ->count();

            $completedToday = DB::table('job')
                ->whereNotNull('completed_at')
                ->whereDate('completed_at', today())
                ->count();

            $itemsThisMonth = DB::table('job')
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->startOfMonth())
                ->count();
        }

        // Recent activity from ahg_audit_log
        $recentActivity = collect();
        $topContributors = collect();
        $activityByCategory = collect();

        if (Schema::hasTable('ahg_audit_log')) {
            $recentActivity = DB::table('ahg_audit_log')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $topContributors = DB::table('ahg_audit_log')
                ->select('username', DB::raw('COUNT(*) as action_count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotNull('username')
                ->where('username', '!=', '')
                ->groupBy('username')
                ->orderByDesc('action_count')
                ->limit(10)
                ->get();

            $activityByCategory = DB::table('ahg_audit_log')
                ->select('action', DB::raw('COUNT(*) as total'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('action')
                ->orderByDesc('total')
                ->get();
        }

        return view('ahg-heritage-manage::custodian-dashboard', compact(
            'runningJobs',
            'completedToday',
            'itemsThisMonth',
            'recentActivity',
            'topContributors',
            'activityByCategory'
        ));
    }
}
