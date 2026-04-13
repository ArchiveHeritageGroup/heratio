<?php

/**
 * PrivacyController - Controller for Heratio
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



namespace AhgPrivacy\Controllers;

use AhgPrivacy\Services\PrivacyService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrivacyController extends Controller
{
    protected PrivacyService $service;

    public function __construct()
    {
        $this->service = new PrivacyService();
    }

    public function complaintConfirmation() { return view('privacy::complaint-confirmation'); }

    public function complaint() { return view('privacy::complaint'); }

    public function dashboard(Request $request)
    {
        $currentJurisdiction = $request->input('jurisdiction', 'all');

        // Build jurisdictions map from DB
        $jurisdictions = [];
        $activeJurisdiction = null;
        if (Schema::hasTable('privacy_jurisdiction')) {
            $rows = DB::table('privacy_jurisdiction')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get();
            foreach ($rows as $j) {
                $jurisdictions[$j->code] = [
                    'name'            => $j->name,
                    'full_name'       => $j->full_name,
                    'country'         => $j->country,
                    'region'          => $j->region,
                    'icon'            => $j->icon ?: 'un',
                    'dsar_days'       => (int) ($j->dsar_days ?? 30),
                    'breach_hours'    => (int) ($j->breach_hours ?? 72),
                    'effective_date'  => $j->effective_date,
                    'regulator'       => $j->regulator,
                    'regulator_url'   => $j->regulator_url,
                ];
            }
            if ($currentJurisdiction !== 'all' && isset($rows)) {
                foreach ($rows as $j) {
                    if ($j->code === $currentJurisdiction) {
                        $activeJurisdiction = $j;
                        break;
                    }
                }
            }
            if (!$activeJurisdiction && $rows->count() > 0) {
                $activeJurisdiction = $rows->first();
            }
        }

        // Scope filter helper
        $scope = function ($q) use ($currentJurisdiction) {
            if ($currentJurisdiction !== 'all') {
                $q->where('jurisdiction', $currentJurisdiction);
            }
            return $q;
        };

        // DSAR stats
        $dsarPending = 0;
        $dsarOverdue = 0;
        $dsarTotal = 0;
        $dsarCompleted = 0;
        if (Schema::hasTable('privacy_dsar')) {
            $dsarPending = $scope(DB::table('privacy_dsar')
                ->whereNotIn('status', ['completed', 'rejected', 'withdrawn']))->count();
            $dsarOverdue = $scope(DB::table('privacy_dsar')
                ->whereDate('due_date', '<', now())
                ->whereNotIn('status', ['completed', 'rejected', 'withdrawn']))->count();
            $dsarTotal = $scope(DB::table('privacy_dsar'))->count();
            $dsarCompleted = $scope(DB::table('privacy_dsar')->where('status', 'completed'))->count();
        }

        // Breach stats
        $breachOpen = 0;
        $breachCritical = 0;
        $breachTotal = 0;
        if (Schema::hasTable('privacy_breach')) {
            $breachOpen = $scope(DB::table('privacy_breach')
                ->whereNotIn('status', ['resolved', 'closed']))->count();
            $breachCritical = $scope(DB::table('privacy_breach')->where('severity', 'critical'))->count();
            $breachTotal = $scope(DB::table('privacy_breach'))->count();
        }

        // ROPA stats
        $ropaApproved = 0;
        $ropaTotal = 0;
        $ropaDpia = 0;
        if (Schema::hasTable('privacy_processing_activity')) {
            $ropaApproved = $scope(DB::table('privacy_processing_activity')->where('status', 'approved'))->count();
            $ropaTotal = $scope(DB::table('privacy_processing_activity'))->count();
            $ropaDpia = $scope(DB::table('privacy_processing_activity')
                ->where('dpia_required', 1)
                ->where('dpia_completed', 0))->count();
        }

        // Consent stats (no jurisdiction column on this table)
        $consentActive = 0;
        if (Schema::hasTable('privacy_consent')) {
            $consentActive = DB::table('privacy_consent')->where('is_active', 1)->count();
        }

        // Compliance score heuristic:
        //  - start at 100
        //  - -10 per overdue DSAR (cap -40)
        //  - -15 per critical breach (cap -45)
        //  - -5 per ROPA needing DPIA (cap -20)
        //  - -20 if no active jurisdiction configured
        $score = 100;
        $score -= min(40, $dsarOverdue * 10);
        $score -= min(45, $breachCritical * 15);
        $score -= min(20, $ropaDpia * 5);
        if (!$activeJurisdiction) {
            $score -= 20;
        }
        $score = max(0, min(100, $score));

        $stats = [
            'compliance_score' => $score,
            'dsar' => [
                'pending'   => $dsarPending,
                'overdue'   => $dsarOverdue,
                'total'     => $dsarTotal,
                'completed' => $dsarCompleted,
            ],
            'breach' => [
                'open'     => $breachOpen,
                'critical' => $breachCritical,
                'total'    => $breachTotal,
            ],
            'ropa' => [
                'approved'       => $ropaApproved,
                'total'          => $ropaTotal,
                'requiring_dpia' => $ropaDpia,
            ],
            'consent' => [
                'active' => $consentActive,
            ],
        ];

        $notificationCount = 0;
        if (Schema::hasTable('privacy_notification')) {
            $notificationCount = DB::table('privacy_notification')
                ->where('is_read', 0)
                ->count();
        }

        return view('privacy::dashboard', compact(
            'stats',
            'jurisdictions',
            'currentJurisdiction',
            'activeJurisdiction',
            'notificationCount'
        ));
    }

    public function dsarConfirmation() { return view('privacy::dsar-confirmation'); }

    public function dsarRequest() { return view('privacy::dsar-request'); }

    public function dsarStatus() { return view('privacy::dsar-status'); }

    public function index() { return view('privacy::index'); }

    public function breachAdd() { return view('privacy::breach-add'); }

    public function breachEdit() { return view('privacy::breach-edit'); }

    public function breachList() { return view('privacy::breach-list'); }

    public function breachView() { return view('privacy::breach-view'); }

    public function complaintAdd() { return view('privacy::complaint-add'); }

    public function complaintEdit() { return view('privacy::complaint-edit'); }

    public function complaintList() { return view('privacy::complaint-list'); }

    public function complaintView() { return view('privacy::complaint-view'); }

    public function config(Request $request)
    {
        $jurisdictions = [];
        if (Schema::hasTable('privacy_jurisdiction')) {
            foreach (DB::table('privacy_jurisdiction')->where('is_active', 1)->orderBy('sort_order')->get() as $j) {
                $jurisdictions[$j->code] = [
                    'name' => $j->name,
                    'full_name' => $j->full_name,
                    'country' => $j->country,
                    'regulator' => $j->regulator,
                    'dsar_days' => $j->dsar_days,
                    'breach_hours' => $j->breach_hours,
                ];
            }
        }
        if (empty($jurisdictions)) {
            $jurisdictions = [
                'popia' => ['name' => 'POPIA', 'full_name' => 'Protection of Personal Information Act (South Africa)', 'country' => 'South Africa', 'regulator' => null, 'dsar_days' => 30, 'breach_hours' => 72],
                'gdpr' => ['name' => 'GDPR', 'full_name' => 'General Data Protection Regulation (EU)', 'country' => 'European Union', 'regulator' => null, 'dsar_days' => 30, 'breach_hours' => 72],
            ];
        }

        $currentJurisdiction = $request->input('jurisdiction', array_key_first($jurisdictions));
        $jurisdictionInfo = $jurisdictions[$currentJurisdiction] ?? reset($jurisdictions);

        $config = null;
        if (Schema::hasTable('privacy_config')) {
            $config = DB::table('privacy_config')->where('jurisdiction', $currentJurisdiction)->first();
        }

        if ($request->isMethod('post') && Schema::hasTable('privacy_config')) {
            $data = [
                'jurisdiction' => $currentJurisdiction,
                'organization_name' => $request->input('organization_name'),
                'registration_number' => $request->input('registration_number'),
                'data_protection_email' => $request->input('data_protection_email'),
                'dsar_response_days' => (int) $request->input('dsar_response_days', $jurisdictionInfo['dsar_days'] ?? 30),
                'breach_notification_hours' => (int) $request->input('breach_notification_hours', $jurisdictionInfo['breach_hours'] ?? 72),
                'retention_default_years' => (int) $request->input('retention_default_years', 5),
                'is_active' => 1,
                'updated_at' => now(),
            ];
            if ($config) {
                DB::table('privacy_config')->where('id', $config->id)->update($data);
            } else {
                $data['created_at'] = now();
                DB::table('privacy_config')->insert($data);
            }
            return redirect()->route('ahgprivacy.config', ['jurisdiction' => $currentJurisdiction])
                ->with('success', __('Privacy settings saved.'));
        }

        return view('privacy::config', compact('jurisdictions', 'currentJurisdiction', 'jurisdictionInfo', 'config'));
    }

    public function consentAdd() { return view('privacy::consent-add'); }

    public function consentEdit() { return view('privacy::consent-edit'); }

    public function consentList() { return view('privacy::consent-list'); }

    public function consentView() { return view('privacy::consent-view'); }

    public function dsarAdd() { return view('privacy::dsar-add'); }

    public function dsarEdit() { return view('privacy::dsar-edit'); }

    public function dsarList(Request $request)
    {
        $dsars = collect();
        if (Schema::hasTable('privacy_dsar')) {
            $dsars = $this->service->getDsarList([
                'status'       => $request->input('status'),
                'jurisdiction' => $request->input('jurisdiction'),
                'overdue'      => $request->input('overdue'),
            ]);
        }

        $jurisdiction = $request->input('jurisdiction', 'popia');
        $requestTypes = PrivacyService::getRequestTypes($jurisdiction);

        $users = collect();
        if (Schema::hasTable('user')) {
            $users = DB::table('user')->select('id', 'username', 'email')->orderBy('username')->get();
        }

        return view('privacy::dsar-list', compact('dsars', 'requestTypes', 'users'));
    }

    public function dsarView() { return view('privacy::dsar-view'); }

    public function jurisdictionAdd() { return view('privacy::jurisdiction-add'); }

    public function jurisdictionEdit() { return view('privacy::jurisdiction-edit'); }

    public function jurisdictionInfo() { return view('privacy::jurisdiction-info'); }

    public function jurisdictionList() { return view('privacy::jurisdiction-list'); }

    public function jurisdictions() { return view('privacy::jurisdictions'); }

    public function notifications() { return view('privacy::notifications'); }

    public function officerAdd() { return view('privacy::officer-add'); }

    public function officerEdit() { return view('privacy::officer-edit'); }

    public function officerList() { return view('privacy::officer-list'); }

    public function paiaAdd() { return view('privacy::paia-add'); }

    public function paiaList() { return view('privacy::paia-list'); }

    public function piiReview() { return view('privacy::pii-review'); }

    public function piiScanObject() { return view('privacy::pii-scan-object'); }

    public function piiScan() { return view('privacy::pii-scan'); }

    public function report() { return view('privacy::report'); }

    public function ropaAdd() { return view('privacy::ropa-add'); }

    public function ropaEdit() { return view('privacy::ropa-edit'); }

    public function ropaList(Request $request)
    {
        $query = DB::table('privacy_processing_activity');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($jurisdiction = $request->input('jurisdiction')) {
            $query->where('jurisdiction', $jurisdiction);
        }

        $activities = $query->orderBy('name')->get();

        // Lawful bases map used for label lookup on the list page. Jurisdiction-neutral:
        // the default is whichever jurisdiction the filter selects, else popia.
        $lawfulBases = $this->getLawfulBasesForJurisdiction(
            $request->input('jurisdiction') ?? 'popia'
        );

        return view('privacy::ropa-list', compact('activities', 'lawfulBases'));
    }

    /**
     * Jurisdiction-neutral lawful bases lookup. Mirrors PSIS
     * PrivacyJurisdictionService::getLawfulBases without hardcoding any
     * single regime as the default.
     */
    protected function getLawfulBasesForJurisdiction(string $jurisdiction): array
    {
        $popia = [
            'consent'              => ['code' => 'POPIA S11(1)(a)', 'label' => 'Consent'],
            'contract'             => ['code' => 'POPIA S11(1)(b)', 'label' => 'Contractual Necessity'],
            'legal_obligation'     => ['code' => 'POPIA S11(1)(c)', 'label' => 'Legal Obligation'],
            'vital_interests'      => ['code' => 'POPIA S11(1)(d)', 'label' => 'Vital Interests'],
            'public_body'          => ['code' => 'POPIA S11(1)(e)', 'label' => 'Public Body Function'],
            'legitimate_interests' => ['code' => 'POPIA S11(1)(f)', 'label' => 'Legitimate Interests'],
        ];
        $gdpr = [
            'consent'              => ['code' => 'GDPR Art.6(1)(a)', 'label' => 'Consent'],
            'contract'             => ['code' => 'GDPR Art.6(1)(b)', 'label' => 'Contract'],
            'legal_obligation'     => ['code' => 'GDPR Art.6(1)(c)', 'label' => 'Legal Obligation'],
            'vital_interests'      => ['code' => 'GDPR Art.6(1)(d)', 'label' => 'Vital Interests'],
            'public_task'          => ['code' => 'GDPR Art.6(1)(e)', 'label' => 'Public Task'],
            'legitimate_interests' => ['code' => 'GDPR Art.6(1)(f)', 'label' => 'Legitimate Interests'],
        ];

        return match ($jurisdiction) {
            'gdpr'  => $gdpr,
            'popia' => $popia,
            default => $popia,
        };
    }

    public function ropaView() { return view('privacy::ropa-view'); }

    public function visualRedactionEditor() { return view('privacy::visual-redaction-editor'); }

    // =====================================================================
    //  POST handlers (Phase X.2 — cloned from PSIS privacyAdmin actions)
    // =====================================================================

    public function dsarUpdate(Request $request)
    {
        $request->validate([
            'id'               => 'required|integer|min:1',
            'status'           => 'nullable|string|max:50',
            'priority'         => 'nullable|string|max:20',
            'assigned_to'      => 'nullable|integer',
            'outcome'          => 'nullable|string|max:50',
            'refusal_reason'   => 'nullable|string|max:2000',
            'is_verified'      => 'nullable|boolean',
            'fee_required'     => 'nullable|boolean',
            'fee_paid'         => 'nullable|boolean',
            'notes'            => 'nullable|string|max:10000',
            'response_summary' => 'nullable|string|max:10000',
        ]);

        $id = (int) $request->input('id');
        $this->service->updateDsar($id, $request->all(), (int) Auth::id());
        session()->flash('success', 'DSAR updated successfully');
        return redirect()->route('ahgprivacy.dsar-view', ['id' => $id]);
    }

    public function breachUpdate(Request $request)
    {
        $request->validate([
            'id'                       => 'required|integer|min:1',
            'breach_type'              => 'nullable|string|max:50',
            'severity'                 => 'nullable|string|in:low,medium,high,critical',
            'status'                   => 'nullable|string|max:50',
            'risk_to_rights'           => 'nullable|string|max:5000',
            'data_subjects_affected'   => 'nullable|integer|min:0',
            'data_categories_affected' => 'nullable|string|max:2000',
            'assigned_to'              => 'nullable|integer',
            'notification_required'    => 'nullable|boolean',
            'regulator_notified'       => 'nullable|boolean',
            'subjects_notified'        => 'nullable|boolean',
            'occurred_date'            => 'nullable|date',
            'contained_date'           => 'nullable|date',
            'resolved_date'            => 'nullable|date',
            'regulator_notified_date'  => 'nullable|date',
            'subjects_notified_date'   => 'nullable|date',
            'title'                    => 'nullable|string|max:255',
            'description'              => 'nullable|string|max:10000',
            'cause'                    => 'nullable|string|max:5000',
            'impact_assessment'        => 'nullable|string|max:10000',
            'remedial_actions'         => 'nullable|string|max:10000',
            'lessons_learned'          => 'nullable|string|max:10000',
        ]);

        $id = (int) $request->input('id');
        $this->service->updateBreach($id, $request->all(), (int) Auth::id());
        session()->flash('success', 'Breach updated successfully');
        return redirect()->route('ahgprivacy.breach-view', ['id' => $id]);
    }

    public function consentWithdraw(Request $request)
    {
        $request->validate([
            'id'     => 'required|integer|min:1',
            'reason' => 'nullable|string|max:2000',
        ]);

        $id = (int) $request->input('id');
        $this->service->withdrawConsent($id, $request->input('reason'), (int) Auth::id());
        session()->flash('success', 'Consent withdrawn successfully');
        return redirect()->route('ahgprivacy.consent-list');
    }

    public function ropaSubmit(Request $request)
    {
        $request->validate([
            'id'         => 'required|integer|min:1',
            'officer_id' => 'nullable|integer|min:1',
        ]);

        $id = (int) $request->input('id');
        $officerId = $request->input('officer_id');
        $officerId = $officerId !== null && $officerId !== '' ? (int) $officerId : null;

        if ($this->service->submitRopaForApproval($id, (int) Auth::id(), $officerId)) {
            session()->flash('success', 'Processing activity submitted for review');
        } else {
            session()->flash('error', 'Unable to submit for review. Only draft items can be submitted.');
        }
        return redirect()->route('ahgprivacy.ropa-view', ['id' => $id]);
    }

    public function ropaApprove(Request $request)
    {
        $request->validate([
            'id'      => 'required|integer|min:1',
            'comment' => 'nullable|string|max:2000',
        ]);

        $id = (int) $request->input('id');
        $user = Auth::user();
        $userId = (int) Auth::id();

        if (!$this->service->isPrivacyOfficer($userId) && !($user && ($user->is_admin ?? false))) {
            session()->flash('error', 'Only Privacy Officers can approve records');
            return redirect()->route('ahgprivacy.ropa-view', ['id' => $id]);
        }

        if ($this->service->approveRopa($id, $userId, $request->input('comment'))) {
            session()->flash('success', 'Processing activity approved');
        } else {
            session()->flash('error', 'Unable to approve. Only pending review items can be approved.');
        }
        return redirect()->route('ahgprivacy.ropa-view', ['id' => $id]);
    }

    public function ropaReject(Request $request)
    {
        $request->validate([
            'id'     => 'required|integer|min:1',
            'reason' => 'required|string|min:1|max:2000',
        ]);

        $id = (int) $request->input('id');
        $user = Auth::user();
        $userId = (int) Auth::id();

        if (!$this->service->isPrivacyOfficer($userId) && !($user && ($user->is_admin ?? false))) {
            session()->flash('error', 'Only Privacy Officers can reject records');
            return redirect()->route('ahgprivacy.ropa-view', ['id' => $id]);
        }

        $reason = trim((string) $request->input('reason'));
        if ($reason === '') {
            session()->flash('error', 'Please provide a reason for rejection');
            return redirect()->route('ahgprivacy.ropa-view', ['id' => $id]);
        }

        if ($this->service->rejectRopa($id, $userId, $reason)) {
            session()->flash('success', 'Processing activity returned for changes');
        } else {
            session()->flash('error', 'Unable to reject. Only pending review items can be rejected.');
        }
        return redirect()->route('ahgprivacy.ropa-view', ['id' => $id]);
    }
}
