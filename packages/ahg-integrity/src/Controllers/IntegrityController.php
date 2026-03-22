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
                ->where('status', 'open')
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
        if (Schema::hasTable('integrity_ledger')) {
            $neverVerified = DB::table('digital_object')
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('integrity_ledger')
                        ->whereColumn('integrity_ledger.digital_object_id', 'digital_object.id');
                })->count();

            $throughput7d = DB::table('integrity_ledger')
                ->where('verified_at', '>=', now()->subDays(7))
                ->count();
        }

        // Repository breakdown
        $repoBreakdown = [];
        if (Schema::hasTable('integrity_ledger')) {
            $repoBreakdown = DB::table('integrity_ledger')
                ->whereNotNull('integrity_ledger.repository_id')
                ->join('actor_i18n', function ($j) use ($culture) {
                    $j->on('integrity_ledger.repository_id', '=', 'actor_i18n.id')
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
                ->select('failure_type as reason', DB::raw('COUNT(*) as cnt'))
                ->groupBy('failure_type')
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

    public function alerts() { $alerts = Schema::hasTable('integrity_alert') ? DB::table('integrity_alert')->orderBy('created_at', 'desc')->limit(100)->get() : collect(); return view('ahg-integrity::integrity.alerts', compact('alerts')); }
    public function deadLetter() { $deadLetters = Schema::hasTable('integrity_dead_letter') ? DB::table('integrity_dead_letter')->orderBy('created_at', 'desc')->limit(100)->get() : collect(); return view('ahg-integrity::integrity.dead-letter', compact('deadLetters')); }
    public function disposition() { $dispositions = Schema::hasTable('integrity_disposition') ? DB::table('integrity_disposition')->orderBy('created_at', 'desc')->get() : collect(); return view('ahg-integrity::integrity.disposition', compact('dispositions')); }
    public function export() { return view('ahg-integrity::integrity.export'); }
    public function holds() { $holds = Schema::hasTable('integrity_hold') ? DB::table('integrity_hold')->orderBy('created_at', 'desc')->get() : collect(); return view('ahg-integrity::integrity.holds', compact('holds')); }
    public function ledger() { $items = Schema::hasTable('integrity_ledger') ? DB::table('integrity_ledger')->orderBy('verified_at', 'desc')->limit(100)->get() : collect(); return view('ahg-integrity::integrity.ledger', compact('items')); }
    public function policies() { $items = Schema::hasTable('integrity_policy') ? DB::table('integrity_policy')->orderBy('name')->get() : collect(); return view('ahg-integrity::integrity.policies', compact('items')); }
    public function policyEdit(int $id) { $policy = Schema::hasTable('integrity_policy') ? DB::table('integrity_policy')->where('id', $id)->first() : null; if (!$policy) abort(404); return view('ahg-integrity::integrity.policy-edit', compact('policy')); }
    public function policyUpdate(\Illuminate\Http\Request $request, int $id) { if (Schema::hasTable('integrity_policy')) { DB::table('integrity_policy')->where('id', $id)->update($request->only(['name', 'description', 'frequency']) + ['is_active' => $request->boolean('is_active'), 'updated_at' => now()]); } return redirect()->route('integrity.policies')->with('success', 'Policy updated.'); }
    public function report() { $items = collect(); return view('ahg-integrity::integrity.report', compact('items')); }
    public function runs() { $items = Schema::hasTable('integrity_run') ? DB::table('integrity_run')->orderBy('started_at', 'desc')->limit(50)->get() : collect(); return view('ahg-integrity::integrity.runs', compact('items')); }
    public function runDetail(int $id) { $run = Schema::hasTable('integrity_run') ? DB::table('integrity_run')->where('id', $id)->first() : null; if (!$run) abort(404); $failures = Schema::hasTable('integrity_dead_letter') ? DB::table('integrity_dead_letter')->where('run_id', $id)->get() : collect(); return view('ahg-integrity::integrity.run-detail', compact('run', 'failures')); }
    public function schedules() { $items = Schema::hasTable('integrity_schedule') ? DB::table('integrity_schedule')->orderBy('name')->get() : collect(); return view('ahg-integrity::integrity.schedules', compact('items')); }
    public function scheduleEdit(int $id) { $schedule = Schema::hasTable('integrity_schedule') ? DB::table('integrity_schedule')->where('id', $id)->first() : null; if (!$schedule) abort(404); return view('ahg-integrity::integrity.schedule-edit', compact('schedule')); }
    public function scheduleUpdate(\Illuminate\Http\Request $request, int $id) { if (Schema::hasTable('integrity_schedule')) { DB::table('integrity_schedule')->where('id', $id)->update($request->only(['name', 'cron_expression']) + ['is_active' => $request->boolean('is_active'), 'updated_at' => now()]); } return redirect()->route('integrity.schedules')->with('success', 'Schedule updated.'); }
}
