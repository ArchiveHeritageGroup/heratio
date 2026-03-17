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

        return view('ahg-integrity::index', [
            'configured' => $configured,
            'stats' => [
                'master_objects' => $totalMasterObjects,
                'total_verifications' => $totalVerifications,
                'pass_rate' => $passRate,
                'open_dead_letters' => $openDeadLetters,
            ],
            'repositories' => $repositories,
            'recentRuns' => $recentRuns,
        ]);
    }
}
