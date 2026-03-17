<?php

namespace AhgIntegrity\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrityController extends Controller
{
    public function index(Request $request)
    {
        $culture = app()->getLocale();

        // Check if integrity tables exist
        $hasRunTable = Schema::hasTable('integrity_run');
        $hasDeadLetterTable = Schema::hasTable('integrity_dead_letter');
        $hasDigitalObject = Schema::hasTable('digital_object');
        $configured = $hasRunTable && $hasDeadLetterTable;

        // Stats
        $totalMasterObjects = 0;
        $totalVerifications = 0;
        $passRate = 0;
        $openDeadLetters = 0;

        if ($hasDigitalObject) {
            $totalMasterObjects = DB::table('digital_object')->count();
        }

        if ($hasRunTable) {
            $totalVerifications = DB::table('integrity_run')->count();

            $passedCount = DB::table('integrity_run')
                ->where('status', 'passed')
                ->count();

            $passRate = $totalVerifications > 0
                ? round(($passedCount / $totalVerifications) * 100, 1)
                : 0;
        }

        if ($hasDeadLetterTable) {
            $openDeadLetters = DB::table('integrity_dead_letter')
                ->where('resolved', false)
                ->count();
        }

        // Get repositories for filter dropdown
        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->select([
                'repository.id',
                'actor_i18n.authorized_form_of_name as name',
            ])
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        // Get recent verification runs
        $recentRuns = collect();
        if ($hasRunTable) {
            $recentRuns = DB::table('integrity_run')
                ->orderBy('started_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($run) {
                    // Calculate duration
                    if ($run->started_at && $run->completed_at) {
                        $start = \Carbon\Carbon::parse($run->started_at);
                        $end = \Carbon\Carbon::parse($run->completed_at);
                        $run->duration = $start->diffForHumans($end, true);
                    } else {
                        $run->duration = null;
                    }

                    return $run;
                });
        }

        // Additional stats
        $neverVerified = 0;
        $throughput7d = 0;
        if (Schema::hasTable('integrity_run')) {
            $neverVerified = DB::table('digital_object')
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('integrity_ledger')
                        ->whereColumn('integrity_ledger.object_id', 'digital_object.object_id');
                })->count();

            $throughput7d = DB::table('integrity_ledger')
                ->where('verified_at', '>=', now()->subDays(7))
                ->count();
        }

        // Repository breakdown
        $repoBreakdown = [];
        if (Schema::hasTable('integrity_ledger')) {
            $repoBreakdown = DB::table('integrity_ledger')
                ->join('information_object', 'integrity_ledger.object_id', '=', 'information_object.id')
                ->join('actor_i18n', function ($j) use ($culture) {
                    $j->on('information_object.repository_id', '=', 'actor_i18n.id')
                      ->where('actor_i18n.culture', '=', $culture);
                })
                ->select('actor_i18n.authorized_form_of_name as name',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN integrity_ledger.outcome = 'pass' THEN 1 ELSE 0 END) as passed"),
                    DB::raw("SUM(CASE WHEN integrity_ledger.outcome = 'fail' THEN 1 ELSE 0 END) as failed"))
                ->groupBy('actor_i18n.authorized_form_of_name')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()->toArray();
        }

        // Failure type breakdown
        $failureTypes = [];
        if (Schema::hasTable('integrity_dead_letter')) {
            $failureTypes = DB::table('integrity_dead_letter')
                ->select('reason', DB::raw('COUNT(*) as cnt'))
                ->groupBy('reason')
                ->orderBy('cnt', 'desc')
                ->limit(10)
                ->get()->toArray();
        }

        // Daily verification trend (last 30 days)
        $dailyTrend = [];
        if (Schema::hasTable('integrity_ledger')) {
            $dailyTrend = DB::table('integrity_ledger')
                ->where('verified_at', '>=', now()->subDays(30))
                ->select(DB::raw('DATE(verified_at) as day'), DB::raw('COUNT(*) as cnt'))
                ->groupBy(DB::raw('DATE(verified_at)'))
                ->orderBy('day')
                ->get()->toArray();
        }

        return view('ahg-integrity::index', [
            'configured' => $configured,
            'stats' => [
                'master_objects' => $totalMasterObjects,
                'total_verifications' => $totalVerifications,
                'pass_rate' => $passRate,
                'open_dead_letters' => $openDeadLetters,
                'never_verified' => $neverVerified,
                'throughput_7d' => $throughput7d,
            ],
            'repositories' => $repositories,
            'recentRuns' => $recentRuns,
            'repoBreakdown' => $repoBreakdown,
            'failureTypes' => $failureTypes,
            'dailyTrend' => $dailyTrend,
        ]);
    }
}
