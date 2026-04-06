<?php

/**
 * IntegrityController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgIntegrity\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use AhgIntegrity\Services\LegalHoldService;
use AhgIntegrity\Services\DestructionCertificateService;
use AhgIntegrity\Services\RetentionService;
use AhgIntegrity\Services\RecordDeclarationService;
use AhgIntegrity\Services\VitalRecordService;

class IntegrityController extends Controller
{
    protected LegalHoldService $legalHoldService;
    protected DestructionCertificateService $certService;
    protected RetentionService $retentionService;
    protected RecordDeclarationService $declarationService;
    protected VitalRecordService $vitalRecordService;

    public function __construct(
        LegalHoldService $legalHoldService,
        DestructionCertificateService $certService,
        RetentionService $retentionService,
        RecordDeclarationService $declarationService,
        VitalRecordService $vitalRecordService
    ) {
        $this->legalHoldService = $legalHoldService;
        $this->certService = $certService;
        $this->retentionService = $retentionService;
        $this->declarationService = $declarationService;
        $this->vitalRecordService = $vitalRecordService;
    }

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
    public function holds(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $repositoryId = $request->query('repository_id') ? (int) $request->query('repository_id') : null;
        $holdData = $this->legalHoldService->getActiveHolds($repositoryId, $page, 25);
        $counts = $this->legalHoldService->getHoldCounts();
        $culture = app()->getLocale();
        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-integrity::integrity.holds', [
            'holds'        => $holdData['data'],
            'total'        => $holdData['total'],
            'page'         => $holdData['page'],
            'perPage'      => $holdData['perPage'],
            'counts'       => $counts,
            'repositories' => $repositories,
            'repositoryId' => $repositoryId,
        ]);
    }
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

    // ─── Legal Hold CRUD ─────────────────────────────────────────────

    public function holdCreate()
    {
        return view('ahg-integrity::integrity.hold-create');
    }

    public function holdStore(Request $request)
    {
        $request->validate([
            'information_object_id' => 'required|integer|min:1',
            'reason'                => 'required|string|max:2000',
        ]);

        $userId = auth()->id() ?? 0;
        $ioId = (int) $request->input('information_object_id');

        // Verify IO exists
        $ioExists = DB::table('information_object')->where('id', $ioId)->exists();
        if (!$ioExists) {
            return back()->withInput()->with('error', 'Information object #' . $ioId . ' does not exist.');
        }

        // Check if already under hold
        if ($this->legalHoldService->isUnderHold($ioId)) {
            return back()->withInput()->with('error', 'Information object #' . $ioId . ' is already under an active legal hold.');
        }

        $holdId = $this->legalHoldService->placeHold(
            $ioId,
            $request->input('reason'),
            $userId
        );

        return redirect()->route('integrity.holds')->with('success', 'Legal hold #' . $holdId . ' placed successfully.');
    }

    public function holdRelease(Request $request, int $id)
    {
        $request->validate([
            'release_reason' => 'required|string|max:2000',
        ]);

        $userId = auth()->id() ?? 0;

        $released = $this->legalHoldService->releaseHold($id, $userId, $request->input('release_reason'));

        if (!$released) {
            return back()->with('error', 'Could not release hold #' . $id . '. It may already be released.');
        }

        return redirect()->route('integrity.holds')->with('success', 'Legal hold #' . $id . ' released.');
    }

    public function holdHistory(int $ioId)
    {
        $culture = app()->getLocale();
        $ioTitle = DB::table('information_object_i18n')
            ->where('id', $ioId)
            ->where('culture', $culture)
            ->value('title') ?? 'Unknown';

        $history = $this->legalHoldService->getHoldHistory($ioId);

        return view('ahg-integrity::integrity.hold-history', [
            'ioId'    => $ioId,
            'ioTitle' => $ioTitle,
            'history' => $history,
        ]);
    }

    public function holdCheck(int $ioId)
    {
        $underHold = $this->legalHoldService->isUnderHold($ioId);

        return response()->json([
            'information_object_id' => $ioId,
            'under_hold'            => $underHold,
        ]);
    }

    // ─── Destruction Certificates ────────────────────────────────────

    public function certificates(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $certData = $this->certService->getCertificates($page, 25);

        return view('ahg-integrity::integrity.certificates', [
            'certificates' => $certData['data'],
            'total'        => $certData['total'],
            'page'         => $certData['page'],
            'perPage'      => $certData['perPage'],
        ]);
    }

    public function certificateGenerate(int $dispositionId)
    {
        $disposition = DB::table('integrity_disposition_queue')
            ->where('id', $dispositionId)
            ->first();

        if (!$disposition) {
            abort(404, 'Disposition queue item not found.');
        }

        $culture = app()->getLocale();
        $ioTitle = DB::table('information_object_i18n')
            ->where('id', $disposition->information_object_id)
            ->where('culture', $culture)
            ->value('title') ?? 'Unknown';

        return view('ahg-integrity::integrity.certificate-generate', [
            'disposition' => $disposition,
            'ioTitle'     => $ioTitle,
        ]);
    }

    public function certificateStore(Request $request)
    {
        $request->validate([
            'disposition_id'     => 'required|integer|min:1',
            'authorized_by'      => 'required|string|max:255',
            'destruction_method' => 'required|string|max:50',
            'witness'            => 'nullable|string|max:255',
        ]);

        $result = $this->certService->generateCertificate(
            (int) $request->input('disposition_id'),
            $request->input('authorized_by'),
            $request->input('destruction_method'),
            $request->input('witness')
        );

        return redirect()->route('integrity.certificates.view', ['id' => $result['id']])
            ->with('success', 'Destruction certificate ' . $result['certificate_number'] . ' generated.');
    }

    public function certificateView(int $id)
    {
        $cert = $this->certService->getCertificate($id);

        if (!$cert) {
            abort(404, 'Certificate not found.');
        }

        return view('ahg-integrity::integrity.certificate-view', [
            'cert' => $cert,
        ]);
    }

    // ── P1.4: Retention Events ──────────────────────────────────────────

    public function retentionEvents(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $eventsData = $this->retentionService->getRetentionEvents($page, 50);
        $eventTypes = $this->retentionService->getEventTypes();
        $policies = $this->retentionService->getRetentionPolicies();

        return view('ahg-integrity::integrity.retention-events', [
            'events'     => $eventsData['data'],
            'total'      => $eventsData['total'],
            'page'       => $eventsData['page'],
            'perPage'    => $eventsData['per_page'],
            'eventTypes' => $eventTypes,
            'policies'   => $policies,
        ]);
    }

    public function retentionEventStore(Request $request)
    {
        $request->validate([
            'information_object_id' => 'required|integer|min:1',
            'event_type'            => 'required|string|max:50',
            'notes'                 => 'nullable|string|max:2000',
        ]);

        $userId = auth()->id() ?? 0;
        $this->retentionService->fireRetentionEvent(
            (int) $request->input('information_object_id'),
            $request->input('event_type'),
            $userId,
            $request->input('notes')
        );

        return redirect()->route('integrity.retention-events')
            ->with('success', 'Retention event recorded.');
    }

    // ── P1.5: Record Declarations ───────────────────────────────────────

    public function declarations(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $allData = $this->declarationService->getAllDeclarations($page, 25);
        $pending = $this->declarationService->getPendingDeclarations();

        return view('ahg-integrity::integrity.declarations', [
            'declarations' => $allData['data'],
            'total'        => $allData['total'],
            'page'         => $allData['page'],
            'perPage'      => $allData['per_page'],
            'pending'      => $pending,
        ]);
    }

    public function declareRecord(Request $request)
    {
        $request->validate([
            'information_object_id' => 'required|integer|min:1',
        ]);

        $userId = auth()->id() ?? 0;
        $result = $this->declarationService->declareRecord(
            (int) $request->input('information_object_id'),
            $userId
        );

        if ($result) {
            return redirect()->route('integrity.declarations')
                ->with('success', 'Record declaration submitted for approval.');
        }

        return redirect()->route('integrity.declarations')
            ->with('error', 'Could not submit record declaration. Table may not exist.');
    }

    public function approveDeclaration(Request $request, int $ioId)
    {
        $userId = auth()->id() ?? 0;
        $approved = $this->declarationService->approveDeclaration($ioId, $userId);

        if ($approved) {
            return redirect()->route('integrity.declarations')
                ->with('success', 'Record declaration approved.');
        }

        return redirect()->route('integrity.declarations')
            ->with('error', 'Could not approve declaration. It may not be in pending state.');
    }

    // ── P1.6: Vital Records ────────────────────────────────────────────

    public function vitalRecords(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $repositoryId = $request->query('repository_id') ? (int) $request->query('repository_id') : null;
        $vitalData = $this->vitalRecordService->getVitalRecords($repositoryId, $page, 25);
        $overdueCount = count($this->vitalRecordService->getOverdueReviews());

        $culture = app()->getLocale();
        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-integrity::integrity.vital-records', [
            'records'      => $vitalData['data'],
            'total'        => $vitalData['total'],
            'page'         => $vitalData['page'],
            'perPage'      => $vitalData['per_page'],
            'overdueCount' => $overdueCount,
            'repositories' => $repositories,
            'repositoryId' => $repositoryId,
        ]);
    }

    public function vitalRecordFlag(Request $request)
    {
        $request->validate([
            'information_object_id' => 'required|integer|min:1',
            'reason'                => 'required|string|max:2000',
            'review_cycle_days'     => 'required|integer|min:1|max:3650',
        ]);

        $userId = auth()->id() ?? 0;
        $this->vitalRecordService->flagAsVital(
            (int) $request->input('information_object_id'),
            $request->input('reason'),
            (int) $request->input('review_cycle_days'),
            $userId
        );

        return redirect()->route('integrity.vital-records')
            ->with('success', 'Information object flagged as vital record.');
    }

    public function vitalRecordUnflag(int $ioId)
    {
        $userId = auth()->id() ?? 0;
        $this->vitalRecordService->unflagVital($ioId, $userId);

        return redirect()->route('integrity.vital-records')
            ->with('success', 'Vital record flag removed.');
    }

    public function vitalRecordReview(int $id)
    {
        $userId = auth()->id() ?? 0;
        $reviewed = $this->vitalRecordService->reviewVitalRecord($id, $userId);

        if ($reviewed) {
            return redirect()->back()->with('success', 'Vital record marked as reviewed.');
        }

        return redirect()->back()->with('error', 'Could not mark vital record as reviewed.');
    }

    public function vitalRecordsOverdue()
    {
        $overdue = $this->vitalRecordService->getOverdueReviews();

        return view('ahg-integrity::integrity.vital-records-overdue', [
            'records' => $overdue,
        ]);
    }
}
