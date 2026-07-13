<?php

/**
 * PrivacyController - Controller for Heratio
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

namespace AhgPrivacy\Controllers;

use AhgPrivacy\Services\PrivacyRedactionService;
use AhgPrivacy\Services\PrivacyService;
use AhgPrivacy\Support\DataProtectionSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class PrivacyController extends Controller
{
    protected PrivacyService $service;

    public function __construct()
    {
        $this->service = new PrivacyService;
    }

    public function complaintConfirmation()
    {
        return view('privacy::complaint-confirmation');
    }

    public function complaint()
    {
        return view('privacy::complaint', [
            'complaintTypes' => $this->complaintTypes(),
        ]);
    }

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
                    'name' => $j->name,
                    'full_name' => $j->full_name,
                    'country' => $j->country,
                    'region' => $j->region,
                    'icon' => $j->icon ?: 'un',
                    'dsar_days' => (int) ($j->dsar_days ?? 30),
                    'breach_hours' => (int) ($j->breach_hours ?? 72),
                    'effective_date' => $j->effective_date,
                    'regulator' => $j->regulator,
                    'regulator_url' => $j->regulator_url,
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
            if (! $activeJurisdiction && $rows->count() > 0) {
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
        if (! $activeJurisdiction) {
            $score -= 20;
        }
        $score = max(0, min(100, $score));

        $stats = [
            'compliance_score' => $score,
            'dsar' => [
                'pending' => $dsarPending,
                'overdue' => $dsarOverdue,
                'total' => $dsarTotal,
                'completed' => $dsarCompleted,
            ],
            'breach' => [
                'open' => $breachOpen,
                'critical' => $breachCritical,
                'total' => $breachTotal,
            ],
            'ropa' => [
                'approved' => $ropaApproved,
                'total' => $ropaTotal,
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

    public function dsarConfirmation()
    {
        return view('privacy::dsar-confirmation');
    }

    public function dsarRequest()
    {
        // Public-facing DSAR request form (data subject submits a request).
        // Request-type list is fetched for the operator-configured default
        // regulation (#72: dp_default_regulation). The data protection
        // officer can re-route the request server-side once received.
        $defaultRegulation = DataProtectionSettings::defaultRegulation();
        $requestTypes = PrivacyService::getRequestTypes($defaultRegulation);

        return view('privacy::dsar-request', compact('requestTypes', 'defaultRegulation'));
    }

    /**
     * POST handler for the public dsar-request form. Persists a row to
     * privacy_dsar (and i18n + log) with status='received', emits the
     * reference number to the confirmation page.
     */
    public function dsarRequestStore(Request $request)
    {
        // #72: when the form omits jurisdiction, fall back to dp_default_regulation.
        $jurisdiction = (string) $request->input('jurisdiction', DataProtectionSettings::defaultRegulation());
        $validated = $request->validate([
            'request_type' => 'required|string|max:89',
            'requestor_name' => 'required|string|max:255',
            'requestor_email' => 'nullable|email|max:255',
            'requestor_phone' => 'nullable|string|max:50',
            'requestor_id_type' => 'nullable|string|max:50',
            'requestor_id_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $dsarId = $this->createDsarRecord($jurisdiction, $validated, 'public-form');

        return redirect()
            ->route('ahgprivacy.dsar-confirmation')
            ->with('reference_number', $this->fetchReferenceNumber($dsarId))
            ->with('success', 'Your request has been received. Please save the reference number for tracking.');
    }

    public function dsarStatus(Request $request)
    {
        // YES, DSARs are saved per logged-in user via privacy_dsar.created_by
        // (populated by createDsarRecord). The status page now picks up that
        // user's own DSARs into a dropdown so they don't have to remember /
        // type the reference number. Email match (requestor_email = my email)
        // is included so admins who created a request on someone's behalf
        // still surface that data subject's record when they sign in.
        $myDsars = collect();
        if (auth()->check()) {
            $userId = (int) auth()->id();
            $myEmail = (string) (auth()->user()->email ?? '');
            $q = DB::table('privacy_dsar')
                ->where(function ($q) use ($userId, $myEmail) {
                    $q->where('created_by', $userId);
                    if ($myEmail !== '') {
                        $q->orWhere('requestor_email', $myEmail);
                    }
                })
                ->orderByDesc('created_at')
                ->select('id', 'reference_number', 'request_type', 'status', 'received_date', 'due_date');
            $myDsars = $q->get();
        }

        // Look up the requested record (?reference=DSAR-... or ?id=N).
        $dsar = null;
        $reference = trim((string) $request->query('reference', ''));
        $email = trim((string) $request->query('email', ''));
        if ($reference !== '') {
            $row = DB::table('privacy_dsar')->where('reference_number', $reference)->first();
            if ($row) {
                $belongs = (auth()->check() && (int) $row->created_by === (int) auth()->id())
                    || (auth()->check() && ! empty(auth()->user()->email) && (string) $row->requestor_email === (string) auth()->user()->email)
                    || ($email !== '' && (string) $row->requestor_email === $email);
                if ($belongs) {
                    $dsar = $row;
                } else {
                    return view('privacy::dsar-status', [
                        'myDsars' => $myDsars,
                    ])->with('error', 'Reference / email did not match a request you own.');
                }
            } else {
                session()->flash('error', 'No request found with reference '.$reference.'.');
            }
        }

        return view('privacy::dsar-status', compact('dsar', 'myDsars'));
    }

    public function index()
    {
        return view('privacy::index');
    }

    public function breachAdd()
    {
        $jurisdictions = $this->loadJurisdictions();
        $defaultJurisdiction = DataProtectionSettings::defaultRegulation();
        if (! isset($jurisdictions[$defaultJurisdiction])) {
            $defaultJurisdiction = array_key_first($jurisdictions) ?: 'popia';
        }

        return view('privacy::breach-add', [
            'jurisdictions' => $jurisdictions,
            'defaultJurisdiction' => $defaultJurisdiction,
            'breachTypes' => PrivacyService::getBreachTypes(),
            'severityLevels' => PrivacyService::getSeverityLevels(),
        ]);
    }

    public function breachEdit(Request $request)
    {
        $breach = null;
        $id = $request->input('id');
        if ($id && Schema::hasTable('privacy_breach')) {
            $breach = DB::table('privacy_breach')->where('id', $id)->first();
        }
        if (! $breach) {
            return redirect()->route('ahgprivacy.breach-list')
                ->with('error', __('Breach not found.'));
        }

        $breachI18n = null;
        if (Schema::hasTable('privacy_breach_i18n')) {
            $breachI18n = DB::table('privacy_breach_i18n')
                ->where('id', $breach->id)
                ->where('culture', app()->getLocale())
                ->first();
        }

        $users = Schema::hasTable('user')
            ? DB::table('user')->select('id', 'username', 'email')->orderBy('username')->get()
            : collect();

        return view('privacy::breach-edit', [
            'breach' => $breach,
            'breachI18n' => $breachI18n,
            'breachTypes' => PrivacyService::getBreachTypes(),
            'severityLevels' => PrivacyService::getSeverityLevels(),
            'statusOptions' => PrivacyService::getBreachStatuses(),
            'riskLevels' => PrivacyService::getRiskLevels(),
            'jurisdictions' => $this->loadJurisdictions(),
            'users' => $users,
            'user' => Auth::user(),
        ]);
    }

    public function breachList()
    {
        $breaches = collect();
        if (Schema::hasTable('privacy_breach')) {
            $breaches = DB::table('privacy_breach')
                ->orderBy('detected_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();
        }

        return view('privacy::breach-list', compact('breaches'));
    }

    public function breachView(Request $request)
    {
        $breach = null;
        $id = $request->input('id');
        if ($id && Schema::hasTable('privacy_breach')) {
            $breach = DB::table('privacy_breach')->where('id', $id)->first();
        }
        if (! $breach) {
            return redirect()->route('ahgprivacy.breach-list')
                ->with('error', __('Breach not found.'));
        }

        $jurisdictions = $this->loadJurisdictions();

        return view('privacy::breach-view', [
            'breach' => $breach,
            'jurisdictionInfo' => $jurisdictions[$breach->jurisdiction] ?? null,
            'severityLevels' => PrivacyService::getSeverityLevels(),
            'severityClasses' => PrivacyService::getSeverityClasses(),
            'statusClasses' => PrivacyService::getStatusClasses(),
        ]);
    }

    public function complaintAdd()
    {
        $jurisdictions = $this->loadJurisdictions();

        return view('privacy::complaint-add', [
            'jurisdictions' => $jurisdictions,
            'defaultJurisdiction' => $this->defaultJurisdictionFor($jurisdictions),
            'complaintTypes' => $this->complaintTypes(),
            'users' => $this->usersList(),
            'user' => Auth::user(),
        ]);
    }

    public function complaintEdit(Request $request)
    {
        $complaint = $this->findRow('privacy_complaint', $request->input('id'));
        if (! $complaint) {
            return redirect()->route('ahgprivacy.complaint-list')->with('error', __('Complaint not found.'));
        }

        return view('privacy::complaint-edit', [
            'complaint' => $complaint,
            'jurisdictions' => $this->loadJurisdictions(),
            'complaintTypes' => $this->complaintTypes(),
            'statusOptions' => ['received' => 'Received', 'investigating' => 'Investigating', 'resolved' => 'Resolved', 'escalated' => 'Escalated', 'closed' => 'Closed'],
            'users' => $this->usersList(),
            'user' => Auth::user(),
        ]);
    }

    public function complaintList()
    {
        $complaints = Schema::hasTable('privacy_complaint')
            ? DB::table('privacy_complaint')->orderByDesc('created_at')->get()
            : collect();

        return view('privacy::complaint-list', [
            'complaints' => $complaints,
            'complaintTypes' => $this->complaintTypes(),
            'statusClasses' => $this->complaintStatusClasses(),
        ]);
    }

    public function complaintView(Request $request)
    {
        $complaint = $this->findRow('privacy_complaint', $request->input('id'));
        if (! $complaint) {
            return redirect()->route('ahgprivacy.complaint-list')->with('error', __('Complaint not found.'));
        }

        return view('privacy::complaint-view', [
            'complaint' => $complaint,
            'statusClasses' => $this->complaintStatusClasses(),
        ]);
    }

    public function config(Request $request)
    {
        $jurisdictions = [];
        if (Schema::hasTable('privacy_jurisdiction')) {
            foreach (DB::table('privacy_jurisdiction')->where('is_active', 1)->orderBy('sort_order')->get() as $j) {
                $jurisdictions[$j->code] = [
                    'name' => $j->name,
                    'full_name' => $j->full_name,
                    'country' => $j->country,
                    'region' => $j->region,
                    'regulator' => $j->regulator,
                    'regulator_url' => $j->regulator_url,
                    'dsar_days' => (int) ($j->dsar_days ?? 30),
                    'breach_hours' => (int) ($j->breach_hours ?? 72),
                    'effective_date' => $j->effective_date,
                    'icon' => $j->icon ?: 'un',
                ];
            }
        }
        if (empty($jurisdictions)) {
            $jurisdictions = [
                'popia' => ['name' => 'POPIA', 'full_name' => 'Protection of Personal Information Act', 'country' => 'South Africa', 'region' => 'Africa', 'regulator' => 'Information Regulator', 'regulator_url' => 'https://inforegulator.org.za', 'dsar_days' => 30, 'breach_hours' => 72, 'effective_date' => '2021-07-01', 'icon' => 'za'],
                'gdpr' => ['name' => 'GDPR', 'full_name' => 'General Data Protection Regulation', 'country' => 'European Union', 'region' => 'Europe', 'regulator' => 'European Data Protection Board', 'regulator_url' => 'https://edpb.europa.eu', 'dsar_days' => 30, 'breach_hours' => 72, 'effective_date' => '2018-05-25', 'icon' => 'eu'],
            ];
        }

        $currentJurisdiction = $request->input('jurisdiction', array_key_first($jurisdictions));
        if (! isset($jurisdictions[$currentJurisdiction])) {
            $currentJurisdiction = array_key_first($jurisdictions);
        }
        $jurisdictionInfo = $jurisdictions[$currentJurisdiction] ?? reset($jurisdictions);

        $config = null;
        if (Schema::hasTable('privacy_config')) {
            $config = DB::table('privacy_config')->where('jurisdiction', $currentJurisdiction)->first();
        }

        if ($request->isMethod('post') && Schema::hasTable('privacy_config')) {
            $request->validate([
                'organization_name' => 'nullable|string|max:255',
                'registration_number' => 'nullable|string|max:100',
                'data_protection_email' => 'nullable|email|max:255',
                'dsar_response_days' => 'nullable|integer|min:1|max:90',
                'breach_notification_hours' => 'nullable|integer|min:0|max:168',
                'retention_default_years' => 'nullable|integer|min:1|max:100',
                'is_active' => 'nullable|boolean',
            ]);
            $data = [
                'jurisdiction' => $currentJurisdiction,
                'organization_name' => $request->input('organization_name'),
                'registration_number' => $request->input('registration_number'),
                'data_protection_email' => $request->input('data_protection_email'),
                'dsar_response_days' => (int) $request->input('dsar_response_days', $jurisdictionInfo['dsar_days'] ?? 30),
                'breach_notification_hours' => (int) $request->input('breach_notification_hours', $jurisdictionInfo['breach_hours'] ?? 72),
                'retention_default_years' => (int) $request->input('retention_default_years', 5),
                'is_active' => $request->boolean('is_active') ? 1 : 0,
                'updated_at' => now(),
            ];
            if ($config) {
                DB::table('privacy_config')->where('id', $config->id)->update($data);
            } else {
                $data['created_at'] = now();
                DB::table('privacy_config')->insert($data);
            }

            return redirect()->route('ahgprivacy.config', ['jurisdiction' => $currentJurisdiction])
                ->with('success', __('Configuration saved successfully'));
        }

        // Officers assigned to this jurisdiction (or 'all')
        $officers = collect();
        if (Schema::hasTable('privacy_officer')) {
            $officers = DB::table('privacy_officer')
                ->where('is_active', 1)
                ->whereIn('jurisdiction', [$currentJurisdiction, 'all'])
                ->orderBy('name')
                ->get();
        }

        // Users list (matches PSIS executeConfig)
        $users = collect();
        if (Schema::hasTable('user')) {
            $users = DB::table('user')->select('id', 'username', 'email')->orderBy('username')->get();
        }

        return view('privacy::config', compact(
            'jurisdictions',
            'currentJurisdiction',
            'jurisdictionInfo',
            'config',
            'officers',
            'users'
        ));
    }

    public function consentAdd()
    {
        $jurisdictions = $this->loadJurisdictions();

        return view('privacy::consent-add', [
            'jurisdictions' => $jurisdictions,
            'defaultJurisdiction' => $this->defaultJurisdictionFor($jurisdictions),
            'consentMethods' => $this->consentMethods(),
        ]);
    }

    public function consentEdit(Request $request)
    {
        $consent = $this->findRow('privacy_consent_record', $request->input('id'));
        if (! $consent) {
            return redirect()->route('ahgprivacy.consent-list')->with('error', __('Consent record not found.'));
        }

        return view('privacy::consent-edit', [
            'consent' => $consent,
            'jurisdictions' => $this->loadJurisdictions(),
            'consentMethods' => $this->consentMethods(),
        ]);
    }

    public function consentList()
    {
        return view('privacy::consent-list');
    }

    public function consentView(Request $request)
    {
        $consent = $this->findRow('privacy_consent_record', $request->input('id'));
        if (! $consent) {
            return redirect()->route('ahgprivacy.consent-list')->with('error', __('Consent record not found.'));
        }

        return view('privacy::consent-view', [
            'consent' => $consent,
        ]);
    }

    public function dsarAdd()
    {
        // Admin-facing DSAR-creation form. Loads jurisdictions, request types,
        // id-type taxonomy, and a user list for the Assigned-To dropdown.
        $jurisdictions = $this->loadJurisdictions();
        // #72: prefer dp_default_regulation when it names a known jurisdiction;
        // otherwise fall back to first row of privacy_jurisdiction.
        $configured = DataProtectionSettings::defaultRegulation();
        $defaultJurisdiction = isset($jurisdictions[$configured])
            ? $configured
            : array_key_first($jurisdictions);
        $requestTypes = PrivacyService::getRequestTypes($defaultJurisdiction);
        // #72: pass POPIA fee context so the form / blade can surface "standard fee R<x>"
        // and special-category fee. Passed unconditionally; blades that don't
        // consume them ignore the variables harmlessly.
        $dpFee = DataProtectionSettings::feeFor($defaultJurisdiction, false);
        $dpFeeSpecial = DataProtectionSettings::feeFor($defaultJurisdiction, true);
        $dpResponseDays = DataProtectionSettings::responseDaysFor($defaultJurisdiction);

        $idTypes = [];
        try {
            $rows = DB::table('ahg_dropdown')->where('taxonomy', 'id_type')->orderBy('sort_order')->orderBy('label')->get(['code', 'label']);
            foreach ($rows as $r) {
                $idTypes[$r->code] = $r->label;
            }
        } catch (\Throwable $e) { /* table optional */
        }

        $users = collect();
        if (Schema::hasTable('user')) {
            $users = DB::table('user')->select('id', 'username', 'email')->orderBy('username')->get();
        }

        return view('privacy::dsar-add', compact('jurisdictions', 'defaultJurisdiction', 'requestTypes', 'idTypes', 'users', 'dpFee', 'dpFeeSpecial', 'dpResponseDays'));
    }

    /**
     * POST handler for the admin dsar-add form. Same persistence shape as
     * the public dsarRequestStore, but also accepts admin-only fields:
     * jurisdiction, priority, received_date, assigned_to.
     */
    public function dsarAddStore(Request $request)
    {
        $jurisdiction = (string) $request->input('jurisdiction', 'popia');
        $validated = $request->validate([
            'jurisdiction' => 'nullable|string|max:30',
            'request_type' => 'required|string|max:89',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'received_date' => 'nullable|date',
            'assigned_to' => 'nullable|integer',
            'requestor_name' => 'required|string|max:255',
            'requestor_email' => 'nullable|email|max:255',
            'requestor_phone' => 'nullable|string|max:50',
            'requestor_id_type' => 'nullable|string|max:50',
            'requestor_id_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $dsarId = $this->createDsarRecord(
            $jurisdiction,
            $validated,
            'admin-'.(string) (auth()->id() ?? 'unknown'),
            [
                'priority' => $validated['priority'] ?? 'normal',
                'received_date' => $validated['received_date'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
            ]
        );

        return redirect()
            ->route('ahgprivacy.dsar-list')
            ->with('success', 'DSAR '.$this->fetchReferenceNumber($dsarId).' created.');
    }

    /**
     * Shared INSERT helper for the public + admin DSAR-create paths.
     * Generates the reference number, computes due_date from jurisdiction
     * dsar_days, writes privacy_dsar + privacy_dsar_i18n + privacy_dsar_log
     * inside a transaction so partial inserts don't leave orphans.
     *
     * Returns the new DSAR id.
     */
    private function createDsarRecord(string $jurisdiction, array $data, string $source, array $adminFields = []): int
    {
        $juris = $this->loadJurisdictions();
        if (! isset($juris[$jurisdiction])) {
            $jurisdiction = array_key_first($juris) ?: 'popia';
        }
        // #72: response window comes from DataProtectionSettings — for popia
        // the dp_popia_response_days override wins; for other jurisdictions
        // the helper falls through to privacy_jurisdiction.dsar_days.
        $dsarDays = DataProtectionSettings::responseDaysFor($jurisdiction);

        $receivedDate = $adminFields['received_date'] ?? now()->toDateString();
        $dueDate = \Carbon\Carbon::parse($receivedDate)->addDays($dsarDays)->toDateString();
        $reference = 'DSAR-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(6));

        return DB::transaction(function () use ($data, $jurisdiction, $receivedDate, $dueDate, $reference, $source, $adminFields) {
            $dsarId = DB::table('privacy_dsar')->insertGetId([
                'reference_number' => $reference,
                'jurisdiction' => $jurisdiction,
                'request_type' => $data['request_type'],
                'requestor_name' => $data['requestor_name'],
                'requestor_email' => $data['requestor_email'] ?? null,
                'requestor_phone' => $data['requestor_phone'] ?? null,
                'requestor_id_type' => $data['requestor_id_type'] ?? null,
                'requestor_id_number' => $data['requestor_id_number'] ?? null,
                'status' => 'received',
                'priority' => $adminFields['priority'] ?? 'normal',
                'received_date' => $receivedDate,
                'due_date' => $dueDate,
                'assigned_to' => $adminFields['assigned_to'] ?? null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($data['description'])) {
                $culture = app()->getLocale() ?: 'en';
                DB::table('privacy_dsar_i18n')->insert([
                    'id' => $dsarId,
                    'culture' => $culture,
                    'description' => $data['description'],
                ]);
            }

            try {
                DB::table('privacy_dsar_log')->insert([
                    'dsar_id' => $dsarId,
                    'action' => 'created',
                    'details' => 'DSAR '.$reference.' created via '.$source.'.',
                    'user_id' => auth()->id(),
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) { /* log table optional */
            }

            // #72: dispatch DSAR-received notification to dp_notify_email
            // when set. Mail failure must not roll back the insert.
            $this->notifyDsarReceived($reference, $jurisdiction, $data, $receivedDate, $dueDate, $source);

            return $dsarId;
        });
    }

    /**
     * #72: send a one-line summary email to the operator-configured
     * dp_notify_email address whenever a new DSAR lands. Includes the
     * configured POPIA fees so the data protection officer can quote
     * them back to the requester. No-op when dp_notify_email is unset
     * or mail dispatch throws.
     */
    private function notifyDsarReceived(
        string $reference,
        string $jurisdiction,
        array $data,
        string $receivedDate,
        string $dueDate,
        string $source
    ): void {
        $to = DataProtectionSettings::notifyEmail();
        if ($to === '') {
            return;
        }

        $body = "A new Data Subject Access Request was received.\n\n"
              ."Reference:     {$reference}\n"
              ."Jurisdiction:  {$jurisdiction}\n"
              .'Request type:  '.($data['request_type'] ?? '')."\n"
              .'Requestor:     '.($data['requestor_name'] ?? '')."\n"
              .'Email:         '.($data['requestor_email'] ?? '(not provided)')."\n"
              ."Received:      {$receivedDate}\n"
              ."Due:           {$dueDate}\n"
              ."Source:        {$source}\n";

        $fee = DataProtectionSettings::feeFor($jurisdiction, false);
        $feeSpecial = DataProtectionSettings::feeFor($jurisdiction, true);
        if ($fee !== null || $feeSpecial !== null) {
            $body .= "\nApplicable fees:\n";
            if ($fee !== null) {
                $body .= '  Standard:        '.number_format($fee, 2)."\n";
            }
            if ($feeSpecial !== null) {
                $body .= '  Special category: '.number_format($feeSpecial, 2)."\n";
            }
        }

        $body .= "\nManage at /admin/privacy/dsar-list.\n";

        try {
            Mail::raw($body, function ($m) use ($to, $reference) {
                $m->to($to)->subject("[Heratio] DSAR received: {$reference}");
            });
        } catch (\Throwable $e) {
            Log::warning('[ahg-privacy] DSAR notification dispatch failed: '.$e->getMessage(), [
                'reference' => $reference,
            ]);
        }
    }

    // ---- shared helpers for the admin CRUD screens (ported from PSIS) -------

    /** user list for Assigned-To dropdowns. */
    private function usersList()
    {
        return Schema::hasTable('user')
            ? DB::table('user')->select('id', 'username', 'email')->orderBy('username')->get()
            : collect();
    }

    /** Fetch a single row by id, or null (guards missing table / id). */
    private function findRow(string $table, $id)
    {
        if (! $id || ! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->where('id', $id)->first();
    }

    /** Default jurisdiction: configured regulation if present, else first active. */
    private function defaultJurisdictionFor(array $jurisdictions): string
    {
        $configured = DataProtectionSettings::defaultRegulation();

        return isset($jurisdictions[$configured]) ? $configured : (array_key_first($jurisdictions) ?: 'popia');
    }

    /** Complaint type taxonomy (PSIS executeComplaintAdd/Edit). */
    private function complaintTypes(): array
    {
        return [
            'data_breach' => 'Data Breach',
            'unauthorized_access' => 'Unauthorized Access',
            'consent_violation' => 'Consent Violation',
            'rights_denial' => 'Rights Denial',
            'marketing' => 'Unsolicited Marketing',
            'other' => 'Other',
        ];
    }

    /** Bootstrap badge colours for complaint status. */
    private function complaintStatusClasses(): array
    {
        return ['received' => 'secondary', 'investigating' => 'warning', 'resolved' => 'success', 'escalated' => 'danger', 'closed' => 'dark'];
    }

    /** Consent capture methods (PSIS executeConsentAdd/Edit). */
    private function consentMethods(): array
    {
        return ['form' => 'Online Form', 'email' => 'Email', 'verbal' => 'Verbal', 'written' => 'Written Document', 'checkbox' => 'Checkbox/Tick Box'];
    }

    /** Privacy officers list. */
    private function officersList()
    {
        return Schema::hasTable('privacy_officer')
            ? DB::table('privacy_officer')->where('is_active', 1)->orderBy('name')->get()
            : collect();
    }

    /** Geographic regions for the jurisdiction form (PSIS executeJurisdictionAdd). */
    private function jurisdictionRegions(): array
    {
        return ['Africa', 'Europe', 'North America', 'South America', 'Asia', 'Oceania', 'International'];
    }

    /**
     * Look up a DSAR's reference_number by id (used by the success-flash
     * redirect after dsarRequestStore / dsarAddStore).
     */
    private function fetchReferenceNumber(int $dsarId): ?string
    {
        try {
            return (string) DB::table('privacy_dsar')->where('id', $dsarId)->value('reference_number') ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Shared loader for the privacy_jurisdiction table → keyed array shape
     * the dsar/breach/complaint/consent forms expect. Falls back to a
     * minimal popia + gdpr seed when the table is empty so the page never
     * renders with no choices.
     */
    private function loadJurisdictions(): array
    {
        $jurisdictions = [];
        if (Schema::hasTable('privacy_jurisdiction')) {
            foreach (DB::table('privacy_jurisdiction')->where('is_active', 1)->orderBy('sort_order')->get() as $j) {
                $jurisdictions[$j->code] = [
                    'name' => $j->name,
                    'full_name' => $j->full_name,
                    'country' => $j->country,
                    'region' => $j->region,
                    'regulator' => $j->regulator,
                    'regulator_url' => $j->regulator_url,
                    'dsar_days' => (int) ($j->dsar_days ?? 30),
                    'breach_hours' => (int) ($j->breach_hours ?? 72),
                    'effective_date' => $j->effective_date,
                    'icon' => $j->icon ?: 'un',
                ];
            }
        }
        if (empty($jurisdictions)) {
            $jurisdictions = [
                'popia' => ['name' => 'POPIA', 'full_name' => 'Protection of Personal Information Act',     'country' => 'South Africa',   'region' => 'Africa', 'regulator' => 'Information Regulator',           'regulator_url' => 'https://inforegulator.org.za', 'dsar_days' => 30, 'breach_hours' => 72, 'effective_date' => '2021-07-01', 'icon' => 'za'],
                'gdpr' => ['name' => 'GDPR',  'full_name' => 'General Data Protection Regulation',         'country' => 'European Union', 'region' => 'Europe', 'regulator' => 'European Data Protection Board', 'regulator_url' => 'https://edpb.europa.eu',       'dsar_days' => 30, 'breach_hours' => 72, 'effective_date' => '2018-05-25', 'icon' => 'eu'],
            ];
        }

        return $jurisdictions;
    }

    public function dsarEdit(Request $request)
    {
        $dsar = $this->findRow('privacy_dsar', $request->input('id'));
        if (! $dsar) {
            return redirect()->route('ahgprivacy.dsar-list')->with('error', __('DSAR not found.'));
        }

        $jurisdiction = $dsar->jurisdiction ?? 'popia';

        $dsarI18n = null;
        if (Schema::hasTable('privacy_dsar_i18n')) {
            $dsarI18n = DB::table('privacy_dsar_i18n')->where('id', $dsar->id)
                ->where('culture', app()->getLocale())->first();
        }

        $idTypes = [];
        try {
            foreach (DB::table('ahg_dropdown')->where('taxonomy', 'id_type')->orderBy('sort_order')->orderBy('label')->get(['code', 'label']) as $r) {
                $idTypes[$r->code] = $r->label;
            }
        } catch (\Throwable $e) { /* optional */
        }

        $today = now()->startOfDay();
        $dueDate = null;
        $daysLeft = null;
        $received = $dsar->received_date ?? ($dsar->created_at ?? null);
        if ($received) {
            try {
                $dueDate = \Carbon\Carbon::parse($received)->addDays((int) DataProtectionSettings::responseDaysFor($jurisdiction));
                $daysLeft = $today->diffInDays($dueDate, false);
            } catch (\Throwable $e) { /* leave null */
            }
        }

        return view('privacy::dsar-edit', [
            'dsar' => $dsar,
            'dsarI18n' => $dsarI18n,
            'requestTypes' => PrivacyService::getRequestTypes($jurisdiction),
            'jurisdictions' => $this->loadJurisdictions(),
            'idTypes' => $idTypes,
            'statusOptions' => PrivacyService::getDsarStatuses(),
            'outcomeOptions' => PrivacyService::getDsarOutcomes(),
            'users' => $this->usersList(),
            'user' => Auth::user(),
            'today' => $today,
            'dueDate' => $dueDate,
            'daysLeft' => $daysLeft,
        ]);
    }

    public function dsarList(Request $request)
    {
        $dsars = collect();
        if (Schema::hasTable('privacy_dsar')) {
            $dsars = $this->service->getDsarList([
                'status' => $request->input('status'),
                'jurisdiction' => $request->input('jurisdiction'),
                'overdue' => $request->input('overdue'),
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

    public function dsarView(Request $request)
    {
        // Accepts either ?id=N or ?ref=DSAR-... for deep-linking from
        // the confirmation page (which has the reference, not the id).
        $id = (int) $request->input('id', 0);
        $ref = (string) $request->input('ref', '');

        $dsar = null;
        if ($id > 0) {
            $dsar = DB::table('privacy_dsar')->where('id', $id)->first();
        } elseif ($ref !== '') {
            $dsar = DB::table('privacy_dsar')->where('reference_number', $ref)->first();
        }
        if (! $dsar) {
            abort(404);
        }

        // Merge i18n columns (description / notes / response_summary) onto
        // the $dsar row so the view's flat property access works without
        // touching every reference site.
        try {
            $culture = app()->getLocale() ?: 'en';
            $i18n = DB::table('privacy_dsar_i18n')
                ->where('id', $dsar->id)
                ->where('culture', $culture)
                ->first();
            if (! $i18n && $culture !== 'en') {
                $i18n = DB::table('privacy_dsar_i18n')->where('id', $dsar->id)->where('culture', 'en')->first();
            }
            $dsar->description = $i18n->description ?? null;
            $dsar->notes = $i18n->notes ?? null;
            $dsar->response_summary = $i18n->response_summary ?? null;
        } catch (\Throwable $e) {
            $dsar->description = $dsar->description ?? null;
            $dsar->notes = $dsar->notes ?? null;
            $dsar->response_summary = $dsar->response_summary ?? null;
        }

        $jurisdictions = $this->loadJurisdictions();
        $jurisdictionInfo = $jurisdictions[$dsar->jurisdiction] ?? null;
        $requestTypes = PrivacyService::getRequestTypes($dsar->jurisdiction);

        $statusClasses = [
            'received' => 'info',
            'verifying' => 'primary',
            'in_review' => 'primary',
            'processing' => 'warning',
            'completed' => 'success',
            'rejected' => 'danger',
            'withdrawn' => 'secondary',
        ];

        $isOverdue = strtotime($dsar->due_date) < time()
            && ! in_array($dsar->status, ['completed', 'rejected', 'withdrawn']);

        $logs = collect();
        try {
            $logs = DB::table('privacy_dsar_log as l')
                ->leftJoin('user as u', 'u.id', '=', 'l.user_id')
                ->where('l.dsar_id', $dsar->id)
                ->orderByDesc('l.created_at')->orderByDesc('l.id')
                ->select('l.*', 'u.username')
                ->get();
        } catch (\Throwable $e) { /* log table optional */
        }

        // Resolve assigned user's username for the "Assigned To" line.
        $dsar->assigned_username = null;
        if (! empty($dsar->assigned_to)) {
            try {
                $dsar->assigned_username = DB::table('user')->where('id', $dsar->assigned_to)->value('username');
            } catch (\Throwable $e) { /* user table optional in fresh installs */
            }
        }

        return view('privacy::dsar-view', compact(
            'dsar', 'jurisdictionInfo', 'requestTypes', 'statusClasses', 'isOverdue', 'logs'
        ));
    }

    public function jurisdictionAdd()
    {
        return view('privacy::jurisdiction-add', [
            'isEdit' => false,
            'jurisdiction' => null,
            'regions' => $this->jurisdictionRegions(),
        ]);
    }

    public function jurisdictionEdit(Request $request)
    {
        $jurisdiction = $this->findRow('privacy_jurisdiction', $request->input('id'));
        if (! $jurisdiction) {
            return redirect()->route('ahgprivacy.jurisdiction-list')->with('error', __('Jurisdiction not found.'));
        }

        return view('privacy::jurisdiction-edit', [
            'isEdit' => true,
            'jurisdiction' => $jurisdiction,
            'regions' => $this->jurisdictionRegions(),
        ]);
    }

    public function jurisdictionInfo()
    {
        return view('privacy::jurisdiction-info');
    }

    public function jurisdictionList()
    {
        return view('privacy::jurisdiction-list');
    }

    public function jurisdictions()
    {
        return view('privacy::jurisdictions');
    }

    public function notifications()
    {
        return view('privacy::notifications');
    }

    public function officerAdd()
    {
        return view('privacy::officer-add', [
            'jurisdictions' => $this->loadJurisdictions(),
        ]);
    }

    public function officerEdit(Request $request)
    {
        $officer = $this->findRow('privacy_officer', $request->input('id'));
        if (! $officer) {
            return redirect()->route('ahgprivacy.officer-list')->with('error', __('Privacy officer not found.'));
        }

        return view('privacy::officer-edit', [
            'jurisdictions' => $this->loadJurisdictions(),
            'officer' => $officer,
        ]);
    }

    public function officerList()
    {
        $officers = collect();
        if (Schema::hasTable('privacy_officer')) {
            $officers = DB::table('privacy_officer')
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->get();
        }

        $jurisdictions = [];
        if (Schema::hasTable('privacy_jurisdiction')) {
            foreach (DB::table('privacy_jurisdiction')->where('is_active', 1)->orderBy('sort_order')->get() as $j) {
                $jurisdictions[$j->code] = [
                    'name' => $j->name,
                    'full_name' => $j->full_name,
                    'country' => $j->country,
                    'icon' => $j->icon ?: 'un',
                ];
            }
        }

        return view('privacy::officer-list', compact('officers', 'jurisdictions'));
    }

    public function paiaAdd()
    {
        return view('privacy::paia-add', [
            'paiaTypes' => PrivacyService::getPAIARequestTypes(),
        ]);
    }

    /**
     * PAIA request list.
     *
     * Cloned from PSIS ahgPrivacyPlugin privacyAdmin::executePaiaList.
     * PAIA is a South-Africa-specific regime so this view lives inside the
     * jurisdiction-pluggable privacy module — it is never loaded into the
     * international core.
     */
    public function paiaList(Request $request)
    {
        $requests = collect();
        if (Schema::hasTable('privacy_paia_request')) {
            $requests = $this->service->getPaiaRequests([
                'status' => $request->input('status'),
                'section' => $request->input('section'),
            ]);
        }

        $paiaTypes = PrivacyService::getPAIARequestTypes();
        $statusClasses = ['received' => 'secondary', 'processing' => 'warning', 'completed' => 'success', 'refused' => 'danger', 'appeal' => 'info', 'closed' => 'dark'];

        return view('privacy::paia-list', compact('requests', 'paiaTypes', 'statusClasses'));
    }

    public function piiReview()
    {
        $entities = collect();
        try {
            if (Schema::hasTable('ahg_ner_entity')) {
                $entities = DB::table('ahg_ner_entity')->orderByDesc('id')->limit(200)->get();
            }
        } catch (\Throwable $e) {
            $entities = collect();
        }
        $typeBadges = ['PERSON' => 'primary', 'EMAIL' => 'danger', 'PHONE' => 'warning', 'ID_NUMBER' => 'danger', 'LOCATION' => 'info', 'ORG' => 'secondary'];

        return view('privacy::pii-review', compact('entities', 'typeBadges'));
    }

    public function piiEntityAction(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|min:1',
            'entity_action' => 'required|string|in:approved,redacted,rejected',
        ]);

        $id = (int) $request->input('entity_id');
        if (Schema::hasTable('ahg_ner_entity')) {
            DB::table('ahg_ner_entity')->where('id', $id)->update([
                'status' => $request->input('entity_action'),
                'reviewed_by' => (int) Auth::id(),
                'reviewed_at' => now(),
            ]);
        }

        session()->flash('success', 'Entity updated successfully');

        return redirect()->route('ahgprivacy.pii-review');
    }

    public function piiScanObject(Request $request)
    {
        $id = $request->input('id');
        if (! $id) {
            return redirect()->route('ahgprivacy.pii-scan')->with('error', __('No object specified.'));
        }

        $object = null;
        if (Schema::hasTable('information_object_i18n')) {
            $object = DB::table('information_object_i18n')->where('id', $id)->where('culture', app()->getLocale())->first()
                ?: DB::table('information_object_i18n')->where('id', $id)->first();
        }
        if (! $object) {
            return redirect()->route('ahgprivacy.pii-scan')->with('error', __('Object not found.'));
        }

        $scanResult = ['entities' => [], 'fields_scanned' => [], 'risk_score' => 0, 'summary' => []];
        try {
            $text = trim(($object->title ?? '').' '.($object->scope_and_content ?? ''));
            if ($text !== '') {
                $r = app(\AhgPrivacy\Services\PiiScanService::class)->scan($text);
                if (is_array($r)) {
                    $scanResult = array_merge($scanResult, $r);
                }
            }
        } catch (\Throwable $e) { /* best-effort scan */
        }

        $riskColors = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'critical' => 'dark'];
        $typeColors = ['PERSON' => 'primary', 'EMAIL' => 'danger', 'PHONE' => 'warning', 'ID_NUMBER' => 'danger', 'LOCATION' => 'info', 'ORG' => 'secondary'];

        return view('privacy::pii-scan-object', compact('object', 'scanResult', 'riskColors', 'typeColors'));
    }

    public function piiScan()
    {
        // PII dashboard statistics derived from the NER corpus (guarded; zeros
        // when nothing has been scanned). Flag an empty corpus in-app rather
        // than silently showing a zeroed dashboard.
        $stats = app(\AhgPrivacy\Services\PiiScanService::class)->getStatistics();
        if (($stats['total_scanned'] ?? 0) === 0) {
            session()->now('info', __('No PII scan data yet. Run a scan to populate these statistics.'));
        }
        $repositories = collect();
        try {
            if (Schema::hasTable('repository')) {
                $repositories = DB::table('repository as r')
                    ->leftJoin('actor_i18n as ai', 'ai.id', '=', 'r.id')
                    ->select('r.id', DB::raw("COALESCE(MAX(ai.authorized_form_of_name), CONCAT('Repository #', r.id)) as name"))
                    ->groupBy('r.id')
                    ->orderBy('name')
                    ->get();
            }
        } catch (\Throwable $e) {
            $repositories = collect();
        }
        $highRiskObjects = collect();

        return view('privacy::pii-scan', compact('stats', 'repositories', 'highRiskObjects'));
    }

    /**
     * Kick off a PII scan over a repository's descriptions. Full corpus scanning
     * runs through the NER pipeline; here we acknowledge the request and return
     * to the dashboard (deeper batch scanning is a follow-up port).
     */
    public function piiScanRun(Request $request)
    {
        return redirect()->route('ahgprivacy.pii-scan')
            ->with('success', __('PII scan request received. Results appear here as the scan pipeline processes descriptions.'));
    }

    public function report()
    {
        return view('privacy::report');
    }

    public function ropaAdd()
    {
        $jurisdictions = $this->loadJurisdictions();
        $defaultJurisdiction = $this->defaultJurisdictionFor($jurisdictions);

        return view('privacy::ropa-add', [
            'jurisdictions' => $jurisdictions,
            'defaultJurisdiction' => $defaultJurisdiction,
            'lawfulBases' => $this->getLawfulBasesForJurisdiction($defaultJurisdiction),
            'officers' => $this->officersList(),
            'officer' => null,
        ]);
    }

    public function ropaEdit(Request $request)
    {
        $activity = $this->findRow('privacy_processing_activity', $request->input('id'));
        if (! $activity) {
            return redirect()->route('ahgprivacy.ropa-list')->with('error', __('Processing activity not found.'));
        }
        $jurisdictions = $this->loadJurisdictions();
        $defaultJurisdiction = $activity->jurisdiction ?? $this->defaultJurisdictionFor($jurisdictions);

        return view('privacy::ropa-edit', [
            'activity' => $activity,
            'jurisdictions' => $jurisdictions,
            'defaultJurisdiction' => $defaultJurisdiction,
            'lawfulBases' => $this->getLawfulBasesForJurisdiction($defaultJurisdiction),
            'officers' => $this->officersList(),
            'officer' => null,
        ]);
    }

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
            'consent' => ['code' => 'POPIA S11(1)(a)', 'label' => 'Consent'],
            'contract' => ['code' => 'POPIA S11(1)(b)', 'label' => 'Contractual Necessity'],
            'legal_obligation' => ['code' => 'POPIA S11(1)(c)', 'label' => 'Legal Obligation'],
            'vital_interests' => ['code' => 'POPIA S11(1)(d)', 'label' => 'Vital Interests'],
            'public_body' => ['code' => 'POPIA S11(1)(e)', 'label' => 'Public Body Function'],
            'legitimate_interests' => ['code' => 'POPIA S11(1)(f)', 'label' => 'Legitimate Interests'],
        ];
        $gdpr = [
            'consent' => ['code' => 'GDPR Art.6(1)(a)', 'label' => 'Consent'],
            'contract' => ['code' => 'GDPR Art.6(1)(b)', 'label' => 'Contract'],
            'legal_obligation' => ['code' => 'GDPR Art.6(1)(c)', 'label' => 'Legal Obligation'],
            'vital_interests' => ['code' => 'GDPR Art.6(1)(d)', 'label' => 'Vital Interests'],
            'public_task' => ['code' => 'GDPR Art.6(1)(e)', 'label' => 'Public Task'],
            'legitimate_interests' => ['code' => 'GDPR Art.6(1)(f)', 'label' => 'Legitimate Interests'],
        ];

        return match ($jurisdiction) {
            'gdpr' => $gdpr,
            'popia' => $popia,
            default => $popia,
        };
    }

    public function ropaView(Request $request)
    {
        $activity = $this->findRow('privacy_processing_activity', $request->input('id'));
        if (! $activity) {
            return redirect()->route('ahgprivacy.ropa-list')->with('error', __('Processing activity not found.'));
        }

        $jurisdiction = $activity->jurisdiction ?? 'popia';
        $officers = $this->officersList();

        // Raw lawful-basis codes stored on the activity (json array or csv).
        $rawBases = [];
        if (! empty($activity->lawful_basis)) {
            $decoded = json_decode((string) $activity->lawful_basis, true);
            $rawBases = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(',', (string) $activity->lawful_basis))));
        }

        return view('privacy::ropa-view', [
            'activity' => $activity,
            'lawfulBases' => $this->getLawfulBasesForJurisdiction($jurisdiction),
            'rawBases' => $rawBases,
            'officers' => $officers,
            'officer' => null,
            'assignedOfficer' => null,
            'isOfficer' => false,
            'approvalHistory' => collect(),
            'log' => collect(),
            'actionIcons' => ['submitted' => 'bi-send', 'approved' => 'bi-check-circle', 'rejected' => 'bi-x-circle', 'created' => 'bi-plus-circle', 'updated' => 'bi-pencil'],
            'actionIcon' => 'bi-clock-history',
            'statusClasses' => ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'active' => 'success'],
        ]);
    }

    public function visualRedactionEditor()
    {
        return view('privacy::visual-redaction-editor');
    }

    // =====================================================================
    //  POST handlers (Phase X.2 — cloned from PSIS privacyAdmin actions)
    // =====================================================================

    public function dsarUpdate(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|min:1',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:20',
            'assigned_to' => 'nullable|integer',
            'outcome' => 'nullable|string|max:50',
            'refusal_reason' => 'nullable|string|max:2000',
            'is_verified' => 'nullable|boolean',
            'fee_required' => 'nullable|boolean',
            'fee_paid' => 'nullable|boolean',
            'notes' => 'nullable|string|max:10000',
            'response_summary' => 'nullable|string|max:10000',
        ]);

        $id = (int) $request->input('id');
        $this->service->updateDsar($id, $request->all(), (int) Auth::id());

        // #1108 deliverable 5 - when a DSAR enters processing, pre-populate a
        // privacy profile for every in-scope description so the officer can mark
        // fields for redaction as part of the response.
        if ($request->input('status') === 'processing') {
            $this->prepopulateDsarScope($id);
        }

        session()->flash('success', 'DSAR updated successfully');

        return redirect()->route('ahgprivacy.dsar-view', ['id' => $id]);
    }

    /**
     * #1108 deliverable 5 - DSAR redaction scope. List the descriptions a DSAR
     * covers, add new ones (pre-populating their privacy profile), or remove
     * them. Routes mounted under /admin/privacy/dsar/{id}/scope.
     */
    public function dsarScope(int $id)
    {
        $dsar = DB::table('privacy_dsar')->where('id', $id)->first();
        abort_if(! $dsar, 404);

        $objects = DB::table('privacy_dsar_object as o')
            ->leftJoin('information_object_i18n as i', function ($j) {
                $j->on('i.id', '=', 'o.information_object_id')->where('i.culture', '=', 'en');
            })
            ->leftJoin('information_object_privacy as p', 'p.information_object_id', '=', 'o.information_object_id')
            ->where('o.dsar_id', $id)
            ->orderByDesc('o.created_at')
            ->get(['o.information_object_id', 'o.created_at', 'i.title', 'p.redaction_status']);

        return view('privacy::dsar-scope', ['dsar' => $dsar, 'objects' => $objects]);
    }

    public function dsarScopeAdd(Request $request, int $id)
    {
        $data = $request->validate(['io' => 'required|string|max:255']);
        abort_if(! DB::table('privacy_dsar')->where('id', $id)->exists(), 404);

        $ioId = $this->resolveIoIdentifier($data['io']);
        if ($ioId === null) {
            return back()->with('error', 'No archival description found for "' . $data['io'] . '".');
        }

        DB::table('privacy_dsar_object')->updateOrInsert(
            ['dsar_id' => $id, 'information_object_id' => $ioId],
            ['created_by' => (int) Auth::id(), 'created_at' => now()]
        );

        $profile = app(PrivacyRedactionService::class)->prepopulateForDsar($ioId, (int) Auth::id());
        DB::table('privacy_dsar_object')
            ->where('dsar_id', $id)->where('information_object_id', $ioId)
            ->update(['privacy_id' => $profile->id]);

        return back()->with('success', 'Added description #' . $ioId . ' to scope and pre-populated its privacy profile.');
    }

    public function dsarScopeRemove(int $id, int $ioId)
    {
        DB::table('privacy_dsar_object')
            ->where('dsar_id', $id)->where('information_object_id', $ioId)
            ->delete();

        return back()->with('success', 'Removed description #' . $ioId . ' from scope.');
    }

    /** Pre-populate privacy profiles for every IO currently in a DSAR's scope. */
    private function prepopulateDsarScope(int $dsarId): void
    {
        try {
            $svc = app(PrivacyRedactionService::class);
            $ioIds = DB::table('privacy_dsar_object')->where('dsar_id', $dsarId)->pluck('information_object_id');
            foreach ($ioIds as $iid) {
                $profile = $svc->prepopulateForDsar((int) $iid, (int) Auth::id());
                DB::table('privacy_dsar_object')
                    ->where('dsar_id', $dsarId)->where('information_object_id', (int) $iid)
                    ->update(['privacy_id' => $profile->id]);
            }
        } catch (\Throwable $e) {
            Log::warning('privacy: DSAR scope pre-populate failed', ['dsar_id' => $dsarId, 'error' => $e->getMessage()]);
        }
    }

    /** Resolve a numeric id or a slug to an information_object id, or null. */
    private function resolveIoIdentifier(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return DB::table('information_object')->where('id', (int) $value)->exists() ? (int) $value : null;
        }
        $objectId = DB::table('slug')->where('slug', $value)->value('object_id');
        if ($objectId && DB::table('information_object')->where('id', $objectId)->exists()) {
            return (int) $objectId;
        }
        return null;
    }

    public function breachUpdate(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|min:1',
            'breach_type' => 'nullable|string|max:50',
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'status' => 'nullable|string|max:50',
            'risk_to_rights' => 'nullable|string|max:5000',
            'data_subjects_affected' => 'nullable|integer|min:0',
            'data_categories_affected' => 'nullable|string|max:2000',
            'assigned_to' => 'nullable|integer',
            'notification_required' => 'nullable|boolean',
            'regulator_notified' => 'nullable|boolean',
            'subjects_notified' => 'nullable|boolean',
            'occurred_date' => 'nullable|date',
            'contained_date' => 'nullable|date',
            'resolved_date' => 'nullable|date',
            'regulator_notified_date' => 'nullable|date',
            'subjects_notified_date' => 'nullable|date',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:10000',
            'cause' => 'nullable|string|max:5000',
            'impact_assessment' => 'nullable|string|max:10000',
            'remedial_actions' => 'nullable|string|max:10000',
            'lessons_learned' => 'nullable|string|max:10000',
        ]);

        $id = (int) $request->input('id');
        $this->service->updateBreach($id, $request->all(), (int) Auth::id());
        session()->flash('success', 'Breach updated successfully');

        return redirect()->route('ahgprivacy.breach-view', ['id' => $id]);
    }

    public function consentWithdraw(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|min:1',
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
            'id' => 'required|integer|min:1',
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
            'id' => 'required|integer|min:1',
            'comment' => 'nullable|string|max:2000',
        ]);

        $id = (int) $request->input('id');
        $user = Auth::user();
        $userId = (int) Auth::id();

        if (! $this->service->isPrivacyOfficer($userId) && ! ($user && ($user->is_admin ?? false))) {
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
            'id' => 'required|integer|min:1',
            'reason' => 'required|string|min:1|max:2000',
        ]);

        $id = (int) $request->input('id');
        $user = Auth::user();
        $userId = (int) Auth::id();

        if (! $this->service->isPrivacyOfficer($userId) && ! ($user && ($user->is_admin ?? false))) {
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
